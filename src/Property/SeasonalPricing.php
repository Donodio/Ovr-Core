<?php
/**
 * Seasonal Pricing.
 *
 * @package OVR\Property
 * @since   1.0.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SeasonalPricing {

    public function init(): void {}

    /**
     * Get seasonal pricing rows for a property.
     */
    public static function get_pricing( int $property_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_seasonal_pricing';

        $cache_key = 'ovr_pricing_' . $property_id;
        $results   = wp_cache_get( $cache_key, 'ovr' );

        if ( false === $results ) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE property_id = %d ORDER BY sort_order ASC, start_date ASC",
                    $property_id
                ),
                ARRAY_A
            );
            wp_cache_set( $cache_key, $results, 'ovr', HOUR_IN_SECONDS );
        }

        return $results ?: [];
    }

    /**
     * Get the current nightly rate for a property based on date.
     */
    public static function get_current_rate( int $property_id, string $date = '' ): float {
        if ( empty( $date ) ) {
            $date = current_time( 'Y-m-d' );
        }

        $pricing = self::get_pricing( $property_id );

        foreach ( $pricing as $season ) {
            if ( $date >= $season['start_date'] && $date <= $season['end_date'] ) {
                return (float) $season['nightly_rate'];
            }
        }

        // Fallback to base price.
        return (float) PropertyMeta::get( $property_id, 'base_price', 0 );
    }
}
