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

    /** Supported rate types and the column each one's amount is stored in. */
    public const RATE_TYPES = [
        'nightly' => 'nightly_rate',
        'weekly'  => 'weekly_rate',
        'monthly' => 'monthly_rate',
        'flat'    => 'flat_rate',
    ];

    /**
     * The client's "Per" dropdown (Phase 4): Per Day / Per Week / Per Month /
     * Flat Rate. Each maps to a rate_type (and thus its amount column) plus a
     * singular unit used to label the price and minimum term in displays.
     *
     * @var array<string, array{rate_type:string, label:string}>
     */
    public const PER_UNITS = [
        'per_day'   => [ 'rate_type' => 'nightly', 'label' => 'day' ],
        'per_week'  => [ 'rate_type' => 'weekly',  'label' => 'week' ],
        'per_month' => [ 'rate_type' => 'monthly', 'label' => 'month' ],
        'flat'      => [ 'rate_type' => 'flat',    'label' => '' ],
    ];

    public function init(): void {}

    /**
     * Replace a property's pricing rows with the production-compatible table
     * (Phase 4). Each input row: [ season_name (Month/Season Name-Year),
     * start_date (From), end_date (To), price (free text, e.g. "$1,200"),
     * per (per_day|per_week|per_month|flat), min_stay (Minimum Term, numeric) ].
     *
     * The price is stored in the amount column matching the "Per" unit so the
     * stored structure maps 1:1 to the current production database. Fully-empty
     * rows are dropped.
     *
     * @param array $rows
     */
    public static function save_pricing( int $property_id, $rows ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_seasonal_pricing';

        $wpdb->delete( $table, [ 'property_id' => $property_id ], [ '%d' ] );

        if ( is_array( $rows ) ) {
            $sort = 0;
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                $period   = sanitize_text_field( wp_unslash( $row['season_name'] ?? '' ) );
                $price    = self::parse_price( (string) ( $row['price'] ?? '' ) );
                $per      = sanitize_key( (string) ( $row['per'] ?? 'per_month' ) );
                if ( ! isset( self::PER_UNITS[ $per ] ) ) {
                    $per = 'per_month';
                }
                $rate_type = self::PER_UNITS[ $per ]['rate_type'];
                $start     = self::clean_date( (string) ( $row['start_date'] ?? '' ) );
                $end       = self::clean_date( (string) ( $row['end_date'] ?? '' ) );
                $min_term  = max( 0, (int) ( $row['min_stay'] ?? 0 ) );

                // Drop fully-empty rows.
                if ( '' === $period && $price <= 0.0 && '' === $start && '' === $end ) {
                    continue;
                }

                // Put the amount in the column matching the chosen unit; others 0.
                $amounts = [ 'nightly_rate' => 0.0, 'weekly_rate' => 0.0, 'monthly_rate' => 0.0, 'flat_rate' => 0.0 ];
                $amounts[ self::RATE_TYPES[ $rate_type ] ] = $price;

                $wpdb->insert(
                    $table,
                    [
                        'property_id'    => $property_id,
                        'season_name'    => $period,
                        'start_date'     => $start ?: null,
                        'end_date'       => $end ?: null,
                        'nightly_rate'   => $amounts['nightly_rate'],
                        'weekly_rate'    => $amounts['weekly_rate'],
                        'monthly_rate'   => $amounts['monthly_rate'],
                        'flat_rate'      => $amounts['flat_rate'],
                        'rate_type'      => $rate_type,
                        'min_stay'       => $min_term,
                        'min_stay_label' => '',
                        'sort_order'     => $sort++,
                    ],
                    [ '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%d' ]
                );
            }
        }

        wp_cache_delete( 'ovr_pricing_' . $property_id, 'ovr' );
    }

    /**
     * Parse a free-text price (e.g. "$1,200", "1500", "1,200.50") to a float.
     */
    public static function parse_price( string $value ): float {
        return (float) preg_replace( '/[^0-9.]/', '', $value );
    }

    /**
     * Validate a YYYY-MM-DD date string, returning it or '' if invalid.
     */
    private static function clean_date( string $value ): string {
        $value = sanitize_text_field( wp_unslash( $value ) );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return '';
        }
        [ $y, $m, $d ] = array_map( 'intval', explode( '-', $value ) );
        return checkdate( $m, $d, $y ) ? $value : '';
    }

    /**
     * Whether the listing opted to hide the pricing table and direct renters to
     * the description instead ("Check Description For Pricing", Phase 4A). The
     * pricing rows are still preserved in the database.
     */
    public static function is_hidden( int $property_id ): bool {
        return (bool) get_post_meta( $property_id, '_ovr_hide_pricing', true );
    }

    /**
     * Resolve a stored row to the display shape used across the site (Phase 4):
     * Name-Year, From/To dates, price, per-unit label, and minimum term. Tolerant
     * of legacy rows (free-text min_stay_label, rate_type='custom').
     *
     * @return array{period:string, from:string, to:string, price:float, per:string, per_key:string, min:string}
     */
    public static function row_display( array $row ): array {
        $rr        = self::row_rate( $row ); // picks the populated rate column + type
        $per_key   = self::rate_type_to_per( $rr['type'] );
        $per_label = self::PER_UNITS[ $per_key ]['label'] ?? '';

        // Minimum term: prefer a legacy free-text label, else compose from the
        // numeric term + the per-unit (e.g. "3 months").
        $min   = (string) ( $row['min_stay_label'] ?? '' );
        $term  = (int) ( $row['min_stay'] ?? 0 );
        if ( '' === $min && $term > 0 && '' !== $per_label ) {
            /* translators: 1: number, 2: unit (day/week/month) */
            $min = sprintf( '%d %s', $term, 1 === $term ? $per_label : $per_label . 's' );
        }

        return [
            'period'  => (string) ( $row['season_name'] ?? '' ),
            'from'    => (string) ( $row['start_date'] ?? '' ),
            'to'      => (string) ( $row['end_date'] ?? '' ),
            'price'   => (float) $rr['amount'],
            'per'     => $per_label,
            'per_key' => $per_key,
            'min'     => $min,
        ];
    }

    /**
     * Map an internal rate_type to the "Per" dropdown key. Legacy/unknown types
     * fall back to a flat rate.
     */
    private static function rate_type_to_per( string $rate_type ): string {
        foreach ( self::PER_UNITS as $key => $info ) {
            if ( $info['rate_type'] === $rate_type ) {
                return $key;
            }
        }
        return 'flat';
    }

    /**
     * Resolve a stored row's rate type + amount for display/editing, tolerant of
     * legacy rows saved before rate_type existed (infers from populated columns).
     *
     * @return array{type:string, amount:float}
     */
    public static function row_rate( array $row ): array {
        $type = (string) ( $row['rate_type'] ?? '' );
        if ( isset( self::RATE_TYPES[ $type ] ) && (float) ( $row[ self::RATE_TYPES[ $type ] ] ?? 0 ) > 0 ) {
            return [ 'type' => $type, 'amount' => (float) $row[ self::RATE_TYPES[ $type ] ] ];
        }
        // Legacy / unset: pick the first populated rate column.
        foreach ( self::RATE_TYPES as $t => $col ) {
            if ( (float) ( $row[ $col ] ?? 0 ) > 0 ) {
                return [ 'type' => $t, 'amount' => (float) $row[ $col ] ];
            }
        }
        return [ 'type' => $type ?: 'nightly', 'amount' => 0.0 ];
    }

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
     * Short price label for cards/sliders, built from the first pricing row:
     * e.g. "$2,500 / Monthly". Returns "See Description For Pricing" when the
     * listing has no pricing rows (matches the single-listing fallback).
     */
    public static function price_summary( int $property_id ): string {
        $rows = self::get_pricing( $property_id );
        if ( empty( $rows ) || self::is_hidden( $property_id ) ) {
            return __( 'See Description For Pricing', 'ovr-core' );
        }

        $settings = (array) get_option( 'ovr_settings', [] );
        $symbol   = $settings['currency_symbol'] ?? '$';

        $d = self::row_display( (array) $rows[0] );
        if ( $d['price'] <= 0 ) {
            return __( 'See Description For Pricing', 'ovr-core' );
        }

        $amount = $symbol . number_format( $d['price'], 0 );
        // "$1,500 / month" when a per-unit applies, else the bare amount.
        return '' !== $d['per'] ? sprintf( '%s / %s', $amount, $d['per'] ) : $amount;
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
