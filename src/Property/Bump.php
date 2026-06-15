<?php
/**
 * Listing Bump (Feature F).
 *
 * A "Bump Listing" is a FREE landlord action that refreshes a listing's
 * position in the default (newest) search ordering — distinct from the paid
 * "Top of Page" promotion (UpgradeActivator). Each landlord may bump up to a
 * configurable number of times per day (default 12). Every bump is recorded in
 * wp_ovr_bump_log (user, listing, timestamp, IP) for limit enforcement + audit.
 *
 * The recency signal is the `_ovr_last_bump` post meta (a Unix timestamp);
 * PropertyQuery::boost_order_clauses orders listings by the greater of their
 * publish date and last-bump time, so a bump floats the listing back to the top.
 *
 * @package OVR\Property
 * @since   2.1.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bump {

    /** Post meta storing the last-bump Unix timestamp. */
    public const META_LAST_BUMP = '_ovr_last_bump';

    /** Default daily bump cap when the setting is unset. */
    public const DEFAULT_DAILY_LIMIT = 12;

    /**
     * The admin-configured daily bump limit (Settings → Listings). Minimum 1.
     */
    public static function daily_limit(): int {
        $settings = (array) get_option( 'ovr_settings', [] );
        $limit    = (int) ( $settings['bump_daily_limit'] ?? self::DEFAULT_DAILY_LIMIT );
        return max( 1, $limit );
    }

    /**
     * How many bumps the user has already made in the current local day.
     */
    public static function count_today( int $user_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_bump_log';
        $start = current_time( 'Y-m-d' ) . ' 00:00:00';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND created_at >= %s",
            $user_id,
            $start
        ) );
    }

    /**
     * Remaining bumps for the user today (never negative).
     */
    public static function remaining_today( int $user_id ): int {
        return max( 0, self::daily_limit() - self::count_today( $user_id ) );
    }

    /**
     * Whether the user may bump again today.
     */
    public static function can_bump( int $user_id ): bool {
        return self::count_today( $user_id ) < self::daily_limit();
    }

    /**
     * Perform a bump on behalf of $user_id for $property_id. Enforces the daily
     * limit, records the event, and refreshes the listing's recency signal.
     *
     * @return array{success:bool, message:string, remaining:int}
     */
    public static function bump( int $property_id, int $user_id ): array {
        if ( ! self::can_bump( $user_id ) ) {
            return [
                'success'   => false,
                'message'   => sprintf(
                    /* translators: %d: daily bump limit */
                    __( 'You have reached your daily limit of %d bumps. Try again tomorrow.', 'ovr-core' ),
                    self::daily_limit()
                ),
                'remaining' => 0,
            ];
        }

        update_post_meta( $property_id, self::META_LAST_BUMP, time() );
        self::record( $user_id, $property_id );

        do_action( 'ovr_listing_bumped', $property_id, $user_id );

        return [
            'success'   => true,
            'message'   => __( 'Your listing has been bumped to the top of its results.', 'ovr-core' ),
            'remaining' => self::remaining_today( $user_id ),
        ];
    }

    /**
     * Insert a bump-log row (user, listing, IP, timestamp).
     */
    private static function record( int $user_id, int $property_id ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'ovr_bump_log',
            [
                'user_id'     => $user_id,
                'property_id' => $property_id,
                'ip_address'  => self::client_ip(),
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s' ]
        );
    }

    /**
     * Best-effort client IP, capped to the column width (IPv6-safe).
     */
    public static function client_ip(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
        $ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
        return substr( $ip, 0, 45 );
    }
}
