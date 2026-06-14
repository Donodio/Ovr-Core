<?php
/**
 * Address Geocoder (Phase 2).
 *
 * Turns a listing's address into map coordinates automatically so every valid
 * listing displays a map without the landlord ever picking a point by hand.
 * Uses the free OpenStreetMap Nominatim service (no API key). Results are
 * cached, and a recurring backfill fills in coordinates for existing listings.
 *
 * Nominatim usage policy: a descriptive User-Agent and at most ~1 request per
 * second. We send the site URL as the agent and sleep between backfill calls.
 *
 * @package OVR\Property
 * @since   1.0.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Geocoder {

    /** @var string Nominatim search endpoint. */
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /** @var string Cron hook that backfills coordinates for existing listings. */
    public const CRON_HOOK = 'ovr_geocode_backfill';

    public function init(): void {
        add_action( self::CRON_HOOK, [ $this, 'backfill' ] );
    }

    /**
     * Schedule the recurring backfill (called from Activator).
     */
    public static function schedule_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
        }
    }

    /**
     * Unschedule (called from Deactivator).
     */
    public static function unschedule_cron(): void {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::CRON_HOOK );
        }
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Geocode an address to coordinates, or null when it can't be resolved.
     * Successful and empty results are both cached to stay within rate limits.
     *
     * @return array{lat:float, lng:float}|null
     */
    public static function geocode( string $address, string $city, string $state, string $zip, string $country = 'USA' ): ?array {
        $parts = array_filter( [ trim( $address ), trim( $city ), trim( $state ), trim( $zip ), $country ] );
        if ( empty( $parts ) ) {
            return null;
        }
        $query     = implode( ', ', $parts );
        $cache_key = 'ovr_geo_' . md5( strtolower( $query ) );

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return is_array( $cached ) && isset( $cached['lat'] ) ? $cached : null;
        }

        $url = add_query_arg(
            [
                'q'            => rawurlencode( $query ),
                'format'       => 'jsonv2',
                'limit'        => 1,
                'countrycodes' => 'us',
            ],
            self::ENDPOINT
        );

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'headers'    => [
                'User-Agent' => self::user_agent(),
                'Accept'     => 'application/json',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return null; // transient network issue — don't cache, allow retry.
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return null;
        }

        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || empty( $body[0]['lat'] ) || empty( $body[0]['lon'] ) ) {
            // Negative cache for a day so we don't re-query an unresolvable address.
            set_transient( $cache_key, [ 'empty' => 1 ], DAY_IN_SECONDS );
            return null;
        }

        $result = [ 'lat' => (float) $body[0]['lat'], 'lng' => (float) $body[0]['lon'] ];
        set_transient( $cache_key, $result, MONTH_IN_SECONDS );
        return $result;
    }

    /**
     * Geocode a single listing from its stored address meta when its address has
     * changed since the last geocode, or it has no coordinates yet. Stores
     * _ovr_latitude / _ovr_longitude and a signature of the address used.
     *
     * @return bool True when coordinates were (re)written.
     */
    public static function geocode_listing( int $post_id ): bool {
        $address = (string) get_post_meta( $post_id, '_ovr_address', true );
        $city    = (string) get_post_meta( $post_id, '_ovr_city', true );
        $state   = (string) get_post_meta( $post_id, '_ovr_state', true );
        $zip     = (string) get_post_meta( $post_id, '_ovr_zip', true );

        // Need at least a street address or a city to attempt a lookup.
        if ( '' === trim( $address . $city . $state . $zip ) ) {
            return false;
        }

        $sig      = md5( strtolower( $address . '|' . $city . '|' . $state . '|' . $zip ) );
        $prev_sig = (string) get_post_meta( $post_id, '_ovr_geo_sig', true );
        $lat      = (float) get_post_meta( $post_id, '_ovr_latitude', true );
        $lng      = (float) get_post_meta( $post_id, '_ovr_longitude', true );

        // Address unchanged and already located → nothing to do.
        if ( $sig === $prev_sig && $lat && $lng ) {
            return false;
        }

        $coords = self::geocode( $address, $city, $state, $zip );
        if ( ! $coords ) {
            return false; // leave the signature unset so we retry next time.
        }

        update_post_meta( $post_id, '_ovr_latitude', $coords['lat'] );
        update_post_meta( $post_id, '_ovr_longitude', $coords['lng'] );
        update_post_meta( $post_id, '_ovr_geo_sig', $sig );
        return true;
    }

    /**
     * Cron worker: geocode a small batch of published listings that have an
     * address but no coordinates yet, so existing listings regain their maps.
     */
    public function backfill(): void {
        $query = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [ 'key' => '_ovr_latitude', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_ovr_latitude', 'value' => '', 'compare' => '=' ],
                    [ 'key' => '_ovr_latitude', 'value' => '0', 'compare' => '=' ],
                ],
                [ 'key' => '_ovr_geo_sig', 'compare' => 'NOT EXISTS' ],
            ],
        ] );

        if ( empty( $query->posts ) ) {
            return;
        }

        foreach ( $query->posts as $i => $post_id ) {
            self::geocode_listing( (int) $post_id );
            // Respect Nominatim's ~1 request/second policy between live lookups.
            if ( $i < count( $query->posts ) - 1 ) {
                sleep( 1 );
            }
        }
    }

    /**
     * Descriptive User-Agent per Nominatim policy (identifies the site).
     */
    private static function user_agent(): string {
        $ver = defined( 'OVR_VERSION' ) ? OVR_VERSION : '1.0';
        return 'OVR-Core/' . $ver . ' (' . home_url( '/' ) . ')';
    }
}
