<?php
/**
 * WordPress booking import (Feature 4 + Feature 11).
 *
 * Pulls reservation data from a configured WordPress source (a JSON endpoint
 * protected by an Application Password) and imports it as bookings. Runs from
 * the "New Booking (WordPress Sync)" button and, when enabled, on a schedule.
 *
 * Remote payloads are mapped flexibly: each record may use any common key
 * (name/guest_name, checkin/checkin_date/start, etc.). Records are matched to
 * a local listing by id, by an external-id meta, or by title, and are
 * deduplicated on (source=wordpress, external_ref).
 *
 * @package OVR\Sync
 * @since   2.0.0
 */

namespace OVR\Sync;

use OVR\Core\SyncLog;
use OVR\Booking\BookingRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordPressSync {

    public const CRON_HOOK = 'ovr_wordpress_sync_event';

    public function init(): void {
        add_action( self::CRON_HOOK, [ self::class, 'run_scheduled' ] );
    }

    /**
     * Read the integration settings block.
     *
     * @return array<string, mixed>
     */
    private static function settings(): array {
        $s = get_option( 'ovr_settings', [] );
        return [
            'url'      => (string) ( $s['wp_sync_url'] ?? '' ),
            'user'     => (string) ( $s['wp_sync_user'] ?? '' ),
            'pass'     => (string) ( $s['wp_sync_pass'] ?? '' ),
            'schedule' => (string) ( $s['wp_sync_schedule'] ?? 'manual' ),
            'enabled'  => ! empty( $s['wp_sync_enabled'] ),
        ];
    }

    /**
     * Cron entry point — only runs when the integration is enabled.
     */
    public static function run_scheduled(): void {
        $cfg = self::settings();
        if ( ! $cfg['enabled'] || '' === $cfg['url'] ) {
            return;
        }
        self::run( 'cron' );
    }

    /**
     * Execute an import. Returns a result array (always logged to SyncLog).
     *
     * @return array{success:bool,imported:int,skipped:int,errors:int,message:string}
     */
    public static function run( string $trigger = 'manual' ): array {
        $cfg = self::settings();

        if ( '' === $cfg['url'] ) {
            return self::finish( false, 0, 0, 0, __( 'No WordPress source URL configured.', 'ovr-core' ), $cfg['url'] );
        }

        $headers = [ 'Accept' => 'application/json' ];
        if ( '' !== $cfg['user'] && '' !== $cfg['pass'] ) {
            $headers['Authorization'] = 'Basic ' . base64_encode( $cfg['user'] . ':' . $cfg['pass'] );
        }

        $response = wp_remote_get( $cfg['url'], [
            'timeout'     => 25,
            'redirection' => 3,
            'headers'     => $headers,
            'user-agent'  => 'OVR-Core/2.0 (+wordpress-sync)',
        ] );

        if ( is_wp_error( $response ) ) {
            return self::finish( false, 0, 0, 0,
                /* translators: %s: error message */
                sprintf( __( 'Network error: %s', 'ovr-core' ), $response->get_error_message() ),
                $cfg['url']
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return self::finish( false, 0, 0, 0,
                /* translators: %d: HTTP status */
                sprintf( __( 'Source returned HTTP %d.', 'ovr-core' ), $code ),
                $cfg['url']
            );
        }

        $body    = (string) wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return self::finish( false, 0, 0, 0, __( 'Source did not return valid JSON.', 'ovr-core' ), $cfg['url'] );
        }

        // Accept either a bare array or { bookings: [...] } / { data: [...] }.
        $records = $decoded;
        if ( isset( $decoded['bookings'] ) && is_array( $decoded['bookings'] ) ) {
            $records = $decoded['bookings'];
        } elseif ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
            $records = $decoded['data'];
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ( $records as $raw ) {
            if ( ! is_array( $raw ) ) {
                $errors++;
                continue;
            }
            $mapped = self::map_record( $raw );
            if ( ! $mapped['property_id'] || '' === $mapped['guest_name'] ) {
                $errors++;
                continue;
            }
            if ( self::already_imported( $mapped['external_ref'] ) ) {
                $skipped++;
                continue;
            }
            BookingRepository::create( $mapped );
            $imported++;
        }

        $status  = $errors > 0 ? ( $imported > 0 ? 'partial' : 'error' ) : 'success';
        $message = sprintf(
            /* translators: 1: imported 2: skipped 3: errors */
            __( 'Imported %1$d, skipped %2$d duplicate(s), %3$d error(s).', 'ovr-core' ),
            $imported, $skipped, $errors
        );

        return self::finish( $imported > 0 || 0 === $errors, $imported, $skipped, $errors, $message, $cfg['url'], $status, $trigger );
    }

    /**
     * Map a flexible remote record onto BookingRepository::create() input.
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private static function map_record( array $r ): array {
        $first = static function ( array $keys, $default = '' ) use ( $r ) {
            foreach ( $keys as $k ) {
                if ( isset( $r[ $k ] ) && '' !== $r[ $k ] ) {
                    return $r[ $k ];
                }
            }
            return $default;
        };

        return [
            'property_id'   => self::resolve_property( $first( [ 'property_id', 'listing_id', 'property', 'listing' ] ) ),
            'guest_name'    => (string) $first( [ 'guest_name', 'name', 'customer_name' ] ),
            'guest_email'   => (string) $first( [ 'guest_email', 'email', 'customer_email' ] ),
            'guest_phone'   => (string) $first( [ 'guest_phone', 'phone', 'telephone' ] ),
            'checkin_date'  => self::norm_date( (string) $first( [ 'checkin_date', 'checkin', 'start', 'start_date', 'arrival' ] ) ),
            'checkout_date' => self::norm_date( (string) $first( [ 'checkout_date', 'checkout', 'end', 'end_date', 'departure' ] ) ),
            'amount'        => (float) $first( [ 'amount', 'total', 'price', 'total_amount' ], 0 ),
            'status'        => (string) $first( [ 'status' ], 'booked' ),
            'source'        => 'wordpress',
            'external_ref'  => (string) $first( [ 'external_ref', 'id', 'booking_id', 'reservation_id', 'uid' ] ),
            'notes'         => (string) $first( [ 'notes', 'note', 'comment' ] ),
        ];
    }

    /**
     * Resolve a remote listing reference to a local ovr_property post id.
     * Tries: numeric local id → external-id meta → exact title match.
     */
    private static function resolve_property( $ref ): int {
        $ref = is_scalar( $ref ) ? trim( (string) $ref ) : '';
        if ( '' === $ref ) {
            return 0;
        }

        // Direct local id.
        if ( ctype_digit( $ref ) ) {
            $post = get_post( (int) $ref );
            if ( $post && 'ovr_property' === $post->post_type ) {
                return (int) $post->ID;
            }
        }

        global $wpdb;
        // External-id meta written by a prior mapping.
        $by_meta = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ovr_external_listing_id' AND meta_value = %s LIMIT 1",
            $ref
        ) );
        if ( $by_meta ) {
            return (int) $by_meta;
        }

        // Exact title match as a last resort.
        $by_title = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ovr_property' AND post_title = %s LIMIT 1",
            $ref
        ) );
        return $by_title ? (int) $by_title : 0;
    }

    /**
     * Whether a WordPress-sourced booking with this external ref already exists.
     */
    private static function already_imported( string $external_ref ): bool {
        if ( '' === $external_ref ) {
            return false;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_bookings';
        $found = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE source = 'wordpress' AND external_ref = %s AND deleted_at IS NULL LIMIT 1",
            $external_ref
        ) );
        return (bool) $found;
    }

    /**
     * Normalise a remote date string to YYYY-MM-DD (or '').
     */
    private static function norm_date( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }
        $ts = strtotime( $value );
        return $ts ? gmdate( 'Y-m-d', $ts ) : '';
    }

    /**
     * Log the run and return the normalised result array.
     *
     * @return array{success:bool,imported:int,skipped:int,errors:int,message:string}
     */
    private static function finish( bool $success, int $imported, int $skipped, int $errors, string $message, string $url, string $status = '', string $trigger = 'manual' ): array {
        if ( '' === $status ) {
            $status = $success ? 'success' : 'error';
        }
        SyncLog::record( 'wordpress', $status, $imported, $message, [
            'skipped' => $skipped,
            'errors'  => $errors,
            'trigger' => $trigger,
        ], null, $url );

        return [
            'success'  => $success,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => $message,
        ];
    }
}
