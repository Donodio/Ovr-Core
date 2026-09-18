<?php
/**
 * Subscription Offer — canonical server-authoritative offer engine (Section 2).
 *
 * Produces the authoritative price + duration for a plan, optionally adjusted by
 * a promo code, and persists an immutable offer record that later Sections (3+)
 * can trust. The browser may display these values; it can never author them.
 *
 * Money follows the existing OVR convention (decimal(10,2), rounded to cents).
 * Duration is a canonical integer count of DAYS. Section 3 (payment) consumes
 * `final_price` and `duration_override_days` from the persisted offer.
 *
 * @package OVR\Subscription
 * @since   1.3.3
 */

namespace OVR\Subscription;

use OVR\Payment\PromoCode;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SubscriptionOffer {

    /** Offer lifetime before it must be rebuilt from current authoritative state. */
    public const TTL_MINUTES = 45;

    public const STATUS_OPEN       = 'open';
    public const STATUS_CONSUMED   = 'consumed';
    public const STATUS_EXPIRED    = 'expired';
    public const STATUS_SUPERSEDED = 'superseded';

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_subscription_offers';
    }

    /**
     * Canonical base duration in DAYS for a plan billing period.
     *
     * The plan model stores a named period (monthly/quarterly/annually); the
     * offer model — and Mark's promo spec — express duration in days. This is
     * the single mapping between the two.
     */
    public static function canonical_duration_days( string $period ): int {
        $map = [
            'monthly'   => 30,
            'quarterly' => 90,
            'annually'  => 365,
            'yearly'    => 365,
        ];
        $days = $map[ strtolower( $period ) ] ?? 365;
        return (int) apply_filters( 'ovr_canonical_period_days', $days, $period );
    }

    /**
     * Build the authoritative offer. Never throws; a bad promo degrades to the
     * base plan offer (with a `promo_error`) so the user can still purchase.
     *
     * @param int    $user_id    Authenticated user (server-derived; never input).
     * @param string $plan_slug  Plan slug (validated against the plan repository).
     * @param string $context    'new' | 'renewal'.
     * @param string $promo_code Optional promo code (any case/whitespace).
     * @return array|\WP_Error
     */
    public static function build( int $user_id, string $plan_slug, string $context = 'new', string $promo_code = '' ) {
        $plan_slug = sanitize_key( $plan_slug );
        $plan      = Plans::get_plan( $plan_slug );
        if ( ! $plan || empty( $plan['is_active'] ) ) {
            return new \WP_Error( 'invalid_plan', __( 'That subscription plan is not available.', 'ovr-core' ) );
        }

        $context = in_array( $context, [ 'new', 'renewal' ], true ) ? $context : 'new';

        $base_price = round( (float) ( $plan['price'] ?? 0 ), 2 );
        $base_days  = self::canonical_duration_days( (string) ( $plan['period'] ?? 'annually' ) );

        $promo_code      = PromoCode::normalize_code( $promo_code );
        $promo_id        = null;
        $promo_price     = null;
        $promo_days      = null;
        $promo_error     = '';
        $promo_row       = null;

        if ( '' !== $promo_code ) {
            $check = PromoCode::validate( $promo_code, $plan_slug );
            if ( empty( $check['valid'] ) ) {
                $promo_error = (string) ( $check['message'] ?? __( 'This promo code is not valid.', 'ovr-core' ) );
                $promo_code  = '';
            } else {
                $promo_row = $check['row'];
            }
        }

        $resolved = PromoCode::apply_to_plan( $promo_row, $base_price, $base_days );

        if ( $promo_row ) {
            $promo_id    = (int) ( $promo_row['id'] ?? 0 ) ?: null;
            $promo_price = $resolved['promo_price'];
            $promo_days  = $resolved['promo_duration_days'];
        }

        $final_price = max( 0.0, round( (float) $resolved['final_price'], 2 ) );
        $final_days  = max( 1, (int) $resolved['final_duration_days'] );

        $offer = [
            'user_id'            => $user_id,
            'plan_slug'          => $plan_slug,
            'plan_name'          => (string) ( $plan['name'] ?? $plan_slug ),
            'purchase_context'   => $context,
            'base_price'         => $base_price,
            'base_duration_days' => $base_days,
            'promo_code'         => $promo_code,
            'promo_id'           => $promo_id,
            'promo_price'        => $promo_price,
            'promo_duration_days'=> $promo_days,
            'final_price'        => $final_price,
            'final_duration_days'=> $final_days,
            // Null = no duration override (Section 3 should use the plan period);
            // an integer = promo-authorized term in days.
            'duration_override_days' => ( null !== $promo_days ) ? (int) $promo_days : null,
            'promo_applied'      => ( '' !== $promo_code && '' === $promo_error ),
            'promo_error'        => $promo_error,
            'currency'           => 'USD',
            'status'             => self::STATUS_OPEN,
        ];

        $offer['offer_id']       = wp_generate_uuid4();
        $offer['integrity_hash'] = self::integrity_hash( $offer );
        $offer['expires_at']     = gmdate( 'Y-m-d H:i:s', time() + ( self::TTL_MINUTES * MINUTE_IN_SECONDS ) );

        return $offer;
    }

    /**
     * Persist a built offer. Supersedes any earlier open offers for the user so
     * the table cannot accumulate stale open rows.
     *
     * @param array $offer Result of build().
     * @return array|\WP_Error Persisted offer (same shape).
     */
    public static function persist( array $offer ) {
        global $wpdb;
        $table = self::table();

        $wpdb->update(
            $table,
            [ 'status' => self::STATUS_SUPERSEDED, 'updated_at' => current_time( 'mysql' ) ],
            [ 'user_id' => (int) $offer['user_id'], 'status' => self::STATUS_OPEN ],
            [ '%s', '%s' ],
            [ '%d', '%s' ]
        );

        $inserted = $wpdb->insert(
            $table,
            [
                'offer_id'               => (string) $offer['offer_id'],
                'user_id'                => (int) $offer['user_id'],
                'plan_slug'              => (string) $offer['plan_slug'],
                'plan_name'              => (string) $offer['plan_name'],
                'purchase_context'       => (string) $offer['purchase_context'],
                'base_price'             => (float) $offer['base_price'],
                'base_duration_days'     => (int) $offer['base_duration_days'],
                'promo_code'             => '' !== (string) ( $offer['promo_code'] ?? '' ) ? (string) $offer['promo_code'] : null,
                'promo_id'               => $offer['promo_id'] ?? null,
                'promo_price'            => $offer['promo_price'],
                'promo_duration_days'    => $offer['promo_duration_days'],
                'final_price'            => (float) $offer['final_price'],
                'final_duration_days'    => (int) $offer['final_duration_days'],
                'duration_override_days' => $offer['duration_override_days'],
                'currency'               => (string) ( $offer['currency'] ?? 'USD' ),
                'status'                 => self::STATUS_OPEN,
                'integrity_hash'         => (string) $offer['integrity_hash'],
                'created_at'             => current_time( 'mysql' ),
                'updated_at'             => current_time( 'mysql' ),
                'expires_at'             => (string) $offer['expires_at'],
            ],
            [ '%s', '%d', '%s', '%s', '%s', '%f', '%d', '%s', '%d', '%f', '%d', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            return new \WP_Error( 'offer_persist_failed', __( 'Could not prepare your subscription offer. Please try again.', 'ovr-core' ) );
        }

        return $offer;
    }

    /**
     * Load a persisted offer by public offer id.
     *
     * @return array|null
     */
    public static function get( string $offer_id ): ?array {
        global $wpdb;
        $offer_id = sanitize_text_field( $offer_id );
        if ( '' === $offer_id ) {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE offer_id = %s LIMIT 1', $offer_id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Verify a persisted row has not been tampered with and matches its snapshot.
     */
    public static function verify( array $row ): bool {
        $expected = self::integrity_hash( $row );
        return is_string( $row['integrity_hash'] ?? null ) && hash_equals( $expected, (string) $row['integrity_hash'] );
    }

    /**
     * Server-authoritative integrity hash over the security-sensitive fields.
     */
    public static function integrity_hash( array $offer ): string {
        $parts = [
            (string) ( $offer['user_id'] ?? '' ),
            (string) ( $offer['plan_slug'] ?? '' ),
            (string) ( $offer['purchase_context'] ?? '' ),
            number_format( (float) ( $offer['base_price'] ?? 0 ), 2, '.', '' ),
            (string) ( $offer['base_duration_days'] ?? '' ),
            (string) ( $offer['promo_code'] ?? '' ),
            ( null === ( $offer['promo_price'] ?? null ) ) ? 'null' : number_format( (float) $offer['promo_price'], 2, '.', '' ),
            ( null === ( $offer['promo_duration_days'] ?? null ) ) ? 'null' : (string) $offer['promo_duration_days'],
            number_format( (float) ( $offer['final_price'] ?? 0 ), 2, '.', '' ),
            (string) ( $offer['final_duration_days'] ?? '' ),
        ];
        return hash_hmac( 'sha256', implode( '|', $parts ), wp_salt( 'ovr_subscription_offer' ) );
    }

    /**
     * Resolve an offer for checkout: must belong to the user, be intact, open,
     * and unexpired. Returns the row or WP_Error (safe message).
     *
     * @return array|\WP_Error
     */
    public static function resolve_for_checkout( string $offer_id, int $user_id ) {
        $row = self::get( $offer_id );
        if ( ! $row ) {
            return new \WP_Error( 'offer_not_found', __( 'Your subscription offer could not be found. Please choose a plan again.', 'ovr-core' ) );
        }
        if ( (int) $row['user_id'] !== (int) $user_id ) {
            // Do not disclose that another user's offer exists.
            return new \WP_Error( 'offer_not_found', __( 'Your subscription offer could not be found. Please choose a plan again.', 'ovr-core' ) );
        }
        if ( ! self::verify( $row ) ) {
            return new \WP_Error( 'offer_invalid', __( 'Your subscription offer is no longer valid. Please choose a plan again.', 'ovr-core' ) );
        }
        if ( self::STATUS_OPEN !== (string) $row['status'] ) {
            return new \WP_Error( 'offer_consumed', __( 'This offer has already been used. Please choose a plan again.', 'ovr-core' ) );
        }
        if ( ! empty( $row['expires_at'] ) && strtotime( (string) $row['expires_at'] ) < time() ) {
            self::mark_status( (string) $row['offer_id'], self::STATUS_EXPIRED );
            return new \WP_Error( 'offer_expired', __( 'Your offer has expired. Please choose a plan again to get current pricing.', 'ovr-core' ) );
        }
        return $row;
    }

    /**
     * Atomically claim an open offer for checkout. Returns true only for the
     * request that flips it open → consumed (double-submit guard).
     */
    public static function claim( string $offer_id ): bool {
        global $wpdb;
        $updated = $wpdb->update(
            self::table(),
            [ 'status' => self::STATUS_CONSUMED, 'updated_at' => current_time( 'mysql' ) ],
            [ 'offer_id' => $offer_id, 'status' => self::STATUS_OPEN ],
            [ '%s', '%s' ],
            [ '%s', '%s' ]
        );
        return 1 === $updated;
    }

    public static function mark_status( string $offer_id, string $status, ?int $payment_id = null ): void {
        global $wpdb;
        $data   = [ 'status' => $status, 'updated_at' => current_time( 'mysql' ) ];
        $format = [ '%s', '%s' ];
        if ( null !== $payment_id ) {
            $data['payment_id'] = $payment_id;
            $format[]           = '%d';
        }
        $wpdb->update( self::table(), $data, [ 'offer_id' => $offer_id ], $format, [ '%s' ] );
    }

    /**
     * Display-ready presentation of an offer (escaping happens in templates).
     */
    public static function present( array $offer ): array {
        $settings = (array) get_option( 'ovr_settings', [] );
        $symbol   = (string) ( $settings['currency_symbol'] ?? '$' );

        return [
            'offer_id'            => $offer['offer_id'],
            'plan_slug'           => $offer['plan_slug'],
            'plan_name'           => $offer['plan_name'],
            'purchase_context'    => $offer['purchase_context'],
            'base_price'          => (float) $offer['base_price'],
            'base_duration_days'  => (int) $offer['base_duration_days'],
            'promo_code'          => (string) ( $offer['promo_code'] ?? '' ),
            'promo_price'         => null === $offer['promo_price'] ? null : (float) $offer['promo_price'],
            'promo_duration_days' => null === $offer['promo_duration_days'] ? null : (int) $offer['promo_duration_days'],
            'final_price'         => (float) $offer['final_price'],
            'final_duration_days' => (int) $offer['final_duration_days'],
            'promo_applied'       => ! empty( $offer['promo_applied'] ),
            'promo_error'         => (string) ( $offer['promo_error'] ?? '' ),
            'currency_symbol'     => $symbol,
            'base_price_display'  => $symbol . number_format( (float) $offer['base_price'], 2 ),
            'final_price_display' => $symbol . number_format( (float) $offer['final_price'], 2 ),
            'expires_at'          => (string) ( $offer['expires_at'] ?? '' ),
        ];
    }
}
