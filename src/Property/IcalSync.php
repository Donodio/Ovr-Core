<?php
/**
 * iCal Calendar Sync.
 *
 * Imports availability blocks from external iCal feeds (Airbnb, VRBO,
 * Booking.com, Google Calendar). Runs hourly via WP-Cron and on-demand
 * from the admin "Sync Now" button.
 *
 * Storage: rows go into wp_ovr_availability with source='ical' and a
 * stable ical_uid pulled from the feed's UID property. Each sync deletes
 * existing source='ical' rows for the property before inserting the
 * fresh set, so cancellations & shifted dates are reflected.
 *
 * @package OVR\Property
 * @since   1.0.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class IcalSync {

    /** @var string Cron hook name. */
    public const CRON_HOOK = 'ovr_ical_sync_event';

    /** @var string Cron schedule key. */
    public const CRON_SCHEDULE = 'hourly';

    /** @var string Last-sync meta key. */
    public const META_LAST_SYNC = '_ovr_ical_last_sync';
    public const META_LAST_RESULT = '_ovr_ical_last_result';

    public function init(): void {
        add_action( self::CRON_HOOK, [ $this, 'sync_all' ] );
        // Outbound feed: serve a property's blocked dates as a shareable .ics so
        // other platforms (Airbnb/VRBO) can import and avoid double bookings.
        add_action( 'init', [ $this, 'maybe_export' ] );
    }

    /**
     * A stable, hard-to-guess token for a property's export feed URL.
     */
    public static function export_token( int $post_id ): string {
        return substr( hash_hmac( 'sha256', 'ovr_ical_export_' . $post_id, (string) wp_salt( 'auth' ) ), 0, 20 );
    }

    /**
     * Public, shareable iCal export URL for a property.
     */
    public static function export_url( int $post_id ): string {
        return add_query_arg(
            [ 'ovr_ical_feed' => $post_id, 'token' => self::export_token( $post_id ) ],
            home_url( '/' )
        );
    }

    /**
     * If the request is for a property's iCal export feed, output it and exit.
     * Token-gated so the feed isn't enumerable by ID alone.
     */
    public function maybe_export(): void {
        if ( empty( $_GET['ovr_ical_feed'] ) ) {
            return;
        }
        $post_id = absint( $_GET['ovr_ical_feed'] );
        $token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

        if ( ! $post_id || ! hash_equals( self::export_token( $post_id ), $token ) ) {
            status_header( 403 );
            exit;
        }
        $post = get_post( $post_id );
        if ( ! $post || 'ovr_property' !== $post->post_type ) {
            status_header( 404 );
            exit;
        }

        nocache_headers();
        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="ovr-listing-' . $post_id . '.ics"' );
        echo $this->build_export( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Build a VCALENDAR string of every blocked range for a property (manual +
     * imported), so a remote platform blocks the same dates.
     */
    private function build_export( int $post_id ): string {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';
        $rows  = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE property_id = %d ORDER BY start_date ASC", $post_id ),
            ARRAY_A
        );

        $domain = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'ourvillagerentals';
        $stamp  = gmdate( 'Ymd\THis\Z' );

        $lines   = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//Our Village Rentals//OVR Core//EN';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'X-WR-CALNAME:' . $this->escape_text( get_the_title( $post_id ) . ' — Availability' );

        foreach ( (array) $rows as $row ) {
            $start = (string) ( $row['start_date'] ?? '' );
            $end   = (string) ( $row['end_date'] ?? '' );
            if ( '' === $start || '' === $end ) {
                continue;
            }
            // iCal DTEND for all-day events is end-exclusive: add a day.
            $dtend  = wp_date( 'Ymd', strtotime( $end . ' +1 day' ) );
            $dtstart = gmdate( 'Ymd', strtotime( $start ) );
            $uid    = ! empty( $row['ical_uid'] ) ? $row['ical_uid'] : ( 'ovr-' . $post_id . '-' . ( $row['id'] ?? '' ) . '@' . $domain );
            $summary = (string) ( $row['notes'] ?? '' );
            if ( '' === $summary ) {
                $summary = ucfirst( (string) ( $row['block_type'] ?? 'Blocked' ) );
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $this->escape_text( $uid );
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
            $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
            $lines[] = 'SUMMARY:' . $this->escape_text( $summary );
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        return implode( "\r\n", $lines ) . "\r\n";
    }

    /**
     * Escape a value for inclusion in an iCal text property.
     */
    private function escape_text( string $value ): string {
        return addcslashes( str_replace( [ "\r\n", "\n", "\r" ], '\\n', $value ), ",;\\" );
    }

    /**
     * Schedule the recurring sync (called from Activator).
     */
    public static function schedule_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 600, self::CRON_SCHEDULE, self::CRON_HOOK );
        }
    }

    /**
     * Unschedule (called from Deactivator).
     */
    public static function unschedule_cron(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Sync iCal feeds for every property that has one.
     */
    public function sync_all(): void {
        $query = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_ovr_ical_url',
                    'value'   => '',
                    'compare' => '!=',
                ],
            ],
            'no_found_rows'  => true,
        ] );

        if ( empty( $query->posts ) ) {
            return;
        }

        foreach ( $query->posts as $post_id ) {
            $this->sync_property( (int) $post_id );
        }
    }

    /**
     * Sync iCal for a single property.
     *
     * @return array{success:bool, imported:int, message:string}
     */
    public function sync_property( int $post_id ): array {
        $url = (string) get_post_meta( $post_id, '_ovr_ical_url', true );
        if ( ! $url ) {
            return $this->result( false, 0, __( 'No iCal URL configured.', 'ovr-core' ), $post_id );
        }

        $response = wp_remote_get( $url, [
            'timeout'     => 20,
            'redirection' => 3,
            'user-agent'  => 'OVR-Core/1.0 (+iCal sync)',
        ] );

        if ( is_wp_error( $response ) ) {
            return $this->result( false, 0,
                /* translators: %s: error message */
                sprintf( __( 'Network error: %s', 'ovr-core' ), $response->get_error_message() ),
                $post_id
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return $this->result( false, 0,
                /* translators: %d: HTTP status code */
                sprintf( __( 'iCal feed returned HTTP %d.', 'ovr-core' ), $code ),
                $post_id
            );
        }

        $body = (string) wp_remote_retrieve_body( $response );
        if ( ! $body ) {
            return $this->result( false, 0, __( 'iCal feed was empty.', 'ovr-core' ), $post_id );
        }

        $events = $this->parse( $body );
        if ( empty( $events ) ) {
            // Wipe stale rows so cancelled bookings disappear.
            $this->wipe_ical_rows( $post_id );
            return $this->result( true, 0, __( 'No upcoming events found in feed.', 'ovr-core' ), $post_id );
        }

        // Replace strategy: clear old ical rows, insert fresh.
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';

        $this->wipe_ical_rows( $post_id );

        $imported = 0;
        foreach ( $events as $ev ) {
            if ( empty( $ev['start'] ) || empty( $ev['end'] ) ) continue;

            $inserted = $wpdb->insert( $table, [
                'property_id'        => $post_id,
                'block_type'         => $ev['block_type'],
                'start_date'         => $ev['start'],
                'end_date'           => $ev['end'],
                'source'             => 'ical',
                'ical_uid'           => $ev['uid'],
                'notes'              => $ev['summary'],
                'show_as_available'  => 0,
            ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ] );

            if ( false !== $inserted ) {
                $imported++;
            }
        }

        // Bust the front-end calendar cache.
        wp_cache_delete( 'ovr_avail_' . $post_id, 'ovr' );

        return $this->result(
            true,
            $imported,
            /* translators: %d: number of events */
            sprintf( _n( 'Imported %d event from iCal feed.', 'Imported %d events from iCal feed.', $imported, 'ovr-core' ), $imported ),
            $post_id
        );
    }

    /**
     * Delete all source='ical' rows for a property.
     */
    private function wipe_ical_rows( int $post_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';
        $wpdb->delete( $table, [ 'property_id' => $post_id, 'source' => 'ical' ], [ '%d', '%s' ] );
    }

    /**
     * Persist last-sync metadata and return a normalized result array.
     *
     * @return array{success:bool, imported:int, message:string, timestamp:string}
     */
    private function result( bool $success, int $imported, string $message, int $post_id ): array {
        $now = current_time( 'mysql' );
        update_post_meta( $post_id, self::META_LAST_SYNC,   $now );
        update_post_meta( $post_id, self::META_LAST_RESULT, [
            'success'   => $success,
            'imported'  => $imported,
            'message'   => $message,
            'timestamp' => $now,
        ] );

        // Durable, cross-property sync history for the integration dashboard.
        \OVR\Core\SyncLog::record(
            'ical',
            $success ? 'success' : 'error',
            $imported,
            $message,
            [ 'property' => get_the_title( $post_id ) ],
            $post_id,
            (string) get_post_meta( $post_id, '_ovr_ical_url', true )
        );

        return [
            'success'   => $success,
            'imported'  => $imported,
            'message'   => $message,
            'timestamp' => $now,
        ];
    }

    /**
     * Minimal iCal parser. Handles VEVENT blocks with DTSTART, DTEND,
     * UID, SUMMARY, STATUS. Supports VALUE=DATE (date-only, end-exclusive)
     * and VALUE=DATE-TIME variants. Folded continuation lines are unfolded.
     *
     * @return array<int, array{start:string,end:string,uid:string,summary:string,block_type:string}>
     */
    public function parse( string $content ): array {
        // Normalize line endings.
        $content = preg_replace( "/\r\n|\r/", "\n", $content );

        // Unfold: per RFC 5545, continuation lines start with whitespace.
        $content = preg_replace( "/\n[ \t]/", '', $content );

        // Slice by VEVENT blocks.
        $events = [];
        if ( ! preg_match_all( '/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches ) ) {
            return [];
        }

        foreach ( $matches[1] as $block ) {
            $event = $this->parse_block( $block );
            if ( $event ) {
                $events[] = $event;
            }
        }
        return $events;
    }

    /**
     * Parse a single VEVENT block body.
     *
     * @return array{start:string,end:string,uid:string,summary:string,block_type:string}|null
     */
    private function parse_block( string $block ): ?array {
        $start_raw = '';
        $end_raw   = '';
        $is_date_only_start = false;
        $is_date_only_end   = false;
        $uid       = '';
        $summary   = '';
        $status    = '';

        $lines = explode( "\n", trim( $block ) );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) continue;

            // Split on the first colon to get NAME[;PARAMS] : VALUE.
            $colon = strpos( $line, ':' );
            if ( false === $colon ) continue;

            $left  = substr( $line, 0, $colon );
            $value = substr( $line, $colon + 1 );

            // Property name is everything before the first semicolon.
            $semi = strpos( $left, ';' );
            $name = strtoupper( false === $semi ? $left : substr( $left, 0, $semi ) );
            $params = false === $semi ? '' : substr( $left, $semi + 1 );

            switch ( $name ) {
                case 'DTSTART':
                    $start_raw = $value;
                    $is_date_only_start = $this->is_date_only( $params, $value );
                    break;
                case 'DTEND':
                    $end_raw = $value;
                    $is_date_only_end = $this->is_date_only( $params, $value );
                    break;
                case 'UID':
                    $uid = $value;
                    break;
                case 'SUMMARY':
                    $summary = $this->unescape( $value );
                    break;
                case 'STATUS':
                    $status = strtoupper( trim( $value ) );
                    break;
            }
        }

        if ( '' === $start_raw || '' === $end_raw ) {
            return null;
        }

        $start = $this->to_iso_date( $start_raw );
        $end   = $this->to_iso_date( $end_raw );

        if ( '' === $start || '' === $end ) {
            return null;
        }

        // iCal DTEND on a VALUE=DATE event is end-EXCLUSIVE. Subtract one day
        // so we store the actual last blocked night.
        if ( $is_date_only_end && $end > $start ) {
            $end = wp_date( 'Y-m-d', strtotime( $end . ' -1 day' ) );
        }

        // Drop past events to keep the table compact (we only show future).
        $today = current_time( 'Y-m-d' );
        if ( $end < $today ) {
            return null;
        }

        // Map STATUS → our block_type vocabulary. Tentative was removed
        // (Phase 5); treat tentative feed events as booked so the dates block.
        $block_type = 'blocked';
        if ( 'TENTATIVE' === $status ) {
            $block_type = 'booked';
        } elseif ( 'CANCELLED' === $status ) {
            return null;  // Skip cancelled events entirely.
        } elseif ( false !== stripos( $summary, 'reserved' ) ||
                   false !== stripos( $summary, 'booked' ) ) {
            $block_type = 'booked';
        }

        return [
            'start'      => $start,
            'end'        => $end,
            'uid'        => substr( $uid, 0, 250 ),
            'summary'    => substr( $summary, 0, 255 ),
            'block_type' => $block_type,
        ];
    }

    /**
     * Detect a date-only value: either VALUE=DATE in params or an 8-digit
     * YYYYMMDD value with no time portion.
     */
    private function is_date_only( string $params, string $value ): bool {
        if ( false !== stripos( $params, 'VALUE=DATE' ) && false === stripos( $params, 'VALUE=DATE-TIME' ) ) {
            return true;
        }
        $clean = preg_replace( '/[^0-9]/', '', $value );
        return 8 === strlen( $clean );
    }

    /**
     * Convert an iCal date or datetime to an ISO YYYY-MM-DD string.
     */
    private function to_iso_date( string $value ): string {
        $clean = preg_replace( '/[^0-9TZ]/', '', $value );
        if ( strlen( $clean ) < 8 ) return '';
        $y = substr( $clean, 0, 4 );
        $m = substr( $clean, 4, 2 );
        $d = substr( $clean, 6, 2 );
        return sprintf( '%04d-%02d-%02d', (int) $y, (int) $m, (int) $d );
    }

    /**
     * Unescape iCal text (\, \n, \;, \,).
     */
    private function unescape( string $value ): string {
        return strtr( $value, [
            '\\n'  => "\n",
            '\\N'  => "\n",
            '\\,'  => ',',
            '\\;'  => ';',
            '\\\\' => '\\',
        ] );
    }
}
