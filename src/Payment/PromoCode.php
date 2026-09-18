<?php
/**
 * Promo Code Model — attached to subscription plans.
 *
 * Promo codes live in wp_ovr_promo_codes. Each code stores which subscription
 * plan slugs it applies to (applicable_plans as JSON/text). Validation checks
 * active flag, date window, usage cap, and plan applicability.
 *
 * @package OVR\Payment
 * @since   1.2.3
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PromoCode {

    /**
     * Canonical promo-code representation: trimmed, upper-cased. All storage
     * and matching goes through this so "mark365", "MARK365" and " Mark365 "
     * resolve to the same code.
     */
    public static function normalize_code( string $code ): string {
        return strtoupper( trim( $code ) );
    }

    /**
     * Fetch a promo code row by code (case-insensitive).
     *
     * @return array|null
     */
    public static function get_by_code( string $code ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_promo_codes';
        $code = self::normalize_code( $code );
        if ( '' === $code ) {
            return null;
        }
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE UPPER(code) = %s LIMIT 1", $code ), ARRAY_A );
        return $row ?: null;
    }

    /**
     * Resolve the authoritative final price/duration for a plan given an
     * optional promo row. This is the single promo→offer calculation.
     *
     * Price precedence: explicit promo_price → legacy discount → base price.
     * Duration precedence: promo duration_days → base duration.
     *
     * A promo_price of 0 is a legitimate free offer and is distinct from
     * "not configured" (NULL).
     *
     * @param array|null $row       Promo row (or null for no promo).
     * @param float      $base_price Base plan price.
     * @param int        $base_days  Base plan duration in days.
     * @return array{promo_price:?float,promo_duration_days:?int,final_price:float,final_duration_days:int}
     */
    public static function apply_to_plan( ?array $row, float $base_price, int $base_days ): array {
        $base_price = max( 0.0, round( $base_price, 2 ) );
        $base_days  = max( 1, (int) $base_days );

        $promo_price = null;
        $promo_days  = null;
        $final_price = $base_price;

        if ( is_array( $row ) ) {
            if ( isset( $row['promo_price'] ) && null !== $row['promo_price'] && '' !== (string) $row['promo_price'] ) {
                $promo_price = max( 0.0, round( (float) $row['promo_price'], 2 ) );
            }
            if ( isset( $row['duration_days'] ) && (int) $row['duration_days'] > 0 ) {
                $promo_days = (int) $row['duration_days'];
            }

            if ( null !== $promo_price ) {
                $final_price = $promo_price;
            } elseif ( 'percentage' === ( $row['discount_type'] ?? '' ) || 'fixed' === ( $row['discount_type'] ?? '' ) ) {
                // Legacy discount semantics (existing codes) still work.
                $final_price = max( 0.0, round( $base_price - self::discount_amount( $row, $base_price ), 2 ) );
            }
        }

        return [
            'promo_price'          => $promo_price,
            'promo_duration_days'  => $promo_days,
            'final_price'          => $final_price,
            'final_duration_days'  => $promo_days ?? $base_days,
        ];
    }

    /**
     * Validate a promo code for a given plan slug.
     *
     * @return array{valid:bool, message:string, row?:array}
     */
    public static function validate( string $code, string $plan_slug ): array {
        $row = self::get_by_code( $code );
        if ( ! $row ) {
            return [ 'valid' => false, 'message' => __( 'Promo code not found.', 'ovr-core' ) ];
        }
        if ( empty( $row['is_active'] ) ) {
            return [ 'valid' => false, 'message' => __( 'This promo code is no longer active.', 'ovr-core' ) ];
        }
        $today = current_time( 'Y-m-d' );
        if ( ! empty( $row['valid_from'] ) && $today < $row['valid_from'] ) {
            return [ 'valid' => false, 'message' => __( 'This promo code is not yet valid.', 'ovr-core' ) ];
        }
        if ( ! empty( $row['valid_until'] ) && $today > $row['valid_until'] ) {
            return [ 'valid' => false, 'message' => __( 'This promo code has expired.', 'ovr-core' ) ];
        }
        if ( null !== $row['max_uses'] && '' !== $row['max_uses'] && (int) $row['current_uses'] >= (int) $row['max_uses'] ) {
            return [ 'valid' => false, 'message' => __( 'This promo code has reached its usage limit.', 'ovr-core' ) ];
        }
        // Check plan applicability.
        $applicable = $row['applicable_plans'] ?? '';
        if ( '' !== trim( (string) $applicable ) ) {
            $plans = self::parse_applicable_plans( $applicable );
            if ( ! empty( $plans ) && ! in_array( $plan_slug, $plans, true ) ) {
                return [ 'valid' => false, 'message' => __( 'This promo code does not apply to the selected plan.', 'ovr-core' ) ];
            }
        }
        return [ 'valid' => true, 'message' => __( 'Promo code applied.', 'ovr-core' ), 'row' => $row ];
    }

    /**
     * Calculate discount amount for a given price and promo row.
     */
    public static function discount_amount( array $row, float $price ): float {
        if ( 'percentage' === $row['discount_type'] ) {
            $disc = $price * ( (float) $row['discount_value'] / 100 );
        } else {
            $disc = (float) $row['discount_value'];
        }
        if ( $disc > $price ) {
            $disc = $price;
        }
        if ( $disc < 0 ) {
            $disc = 0;
        }
        return round( $disc, 2 );
    }

    /**
     * Increment current_uses after a successful payment.
     */
    public static function increment_use( string $code ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_promo_codes';
        $code = self::normalize_code( $code );
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET current_uses = current_uses + 1 WHERE UPPER(code) = %s", $code ) );
    }

    /**
     * Parse applicable_plans field (JSON array or comma-separated).
     *
     * @return string[]
     */
    public static function parse_applicable_plans( string $raw ): array {
        $raw = trim( $raw );
        if ( '' === $raw ) {
            return [];
        }
        // Try JSON first.
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            return array_values( array_filter( array_map( 'sanitize_key', $decoded ) ) );
        }
        // Fallback: comma-separated.
        return array_values( array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) ) );
    }

    /**
     * Get all promo codes.
     *
     * @return array[]
     */
    public static function all(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_promo_codes';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A ) ?: [];
    }

    /**
     * Get promo codes applicable to a specific plan.
     *
     * @return array[]
     */
    public static function for_plan( string $plan_slug ): array {
        $all = self::all();
        $out = [];
        foreach ( $all as $row ) {
            if ( empty( $row['is_active'] ) ) {
                continue;
            }
            $plans = self::parse_applicable_plans( (string) ( $row['applicable_plans'] ?? '' ) );
            if ( empty( $plans ) || in_array( $plan_slug, $plans, true ) ) {
                $out[] = $row;
            }
        }
        return $out;
    }
}
