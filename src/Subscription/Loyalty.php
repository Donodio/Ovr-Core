<?php
/**
 * Loyalty programme (Feature 10 — Membership).
 *
 * A points + store-credit ledger (`ovr_loyalty_ledger`) with admin-configurable
 * earning rules: points per dollar spent, a renewal bonus, referral credit and
 * an upgrade discount. Points are awarded automatically when a payment is
 * confirmed (the same `ovr_payment_completed` hook the boosts use).
 *
 * Settings live in the shared `ovr_settings` option under loyalty_* keys and are
 * edited from the Membership admin screen.
 *
 * @package OVR\Subscription
 * @since   2.0.0
 */

namespace OVR\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loyalty {

    /** @var array<string, mixed> Default loyalty settings. */
    public const DEFAULTS = [
        'loyalty_enabled'      => false,
        'points_per_dollar'    => 1,
        'renewal_bonus_points' => 50,
        'referral_credit'      => 10.0,
        'upgrade_discount_pct' => 0,
    ];

    public function init(): void {
        // Award points whenever a payment is confirmed (any completion path).
        add_action( 'ovr_payment_completed', [ $this, 'on_payment_completed' ], 20, 2 );
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_loyalty_ledger';
    }

    /**
     * Resolved loyalty settings (defaults merged with stored ovr_settings).
     *
     * @return array<string, mixed>
     */
    public static function settings(): array {
        $s = (array) get_option( 'ovr_settings', [] );
        return [
            'loyalty_enabled'      => ! empty( $s['loyalty_enabled'] ),
            'points_per_dollar'    => isset( $s['points_per_dollar'] ) ? (float) $s['points_per_dollar'] : self::DEFAULTS['points_per_dollar'],
            'renewal_bonus_points' => isset( $s['renewal_bonus_points'] ) ? (int) $s['renewal_bonus_points'] : self::DEFAULTS['renewal_bonus_points'],
            'referral_credit'      => isset( $s['referral_credit'] ) ? (float) $s['referral_credit'] : self::DEFAULTS['referral_credit'],
            'upgrade_discount_pct' => isset( $s['upgrade_discount_pct'] ) ? (float) $s['upgrade_discount_pct'] : self::DEFAULTS['upgrade_discount_pct'],
        ];
    }

    public static function is_enabled(): bool {
        return (bool) self::settings()['loyalty_enabled'];
    }

    /**
     * Current points balance for a user.
     */
    public static function balance( int $user_id ): int {
        global $wpdb;
        $val = $wpdb->get_var( $wpdb->prepare(
            'SELECT balance_after FROM ' . self::table() . ' WHERE user_id = %d AND kind = %s ORDER BY id DESC LIMIT 1',
            $user_id,
            'points'
        ) );
        return null === $val ? 0 : (int) $val;
    }

    /**
     * Record a points movement (earn/spend). Returns the new balance.
     */
    public static function adjust_points( int $user_id, int $points, string $direction, string $reason, ?int $related_id = null ): int {
        global $wpdb;
        if ( $user_id <= 0 || 0 === $points ) {
            return self::balance( $user_id );
        }
        $direction = 'spend' === $direction ? 'spend' : 'earn';
        $delta     = 'spend' === $direction ? -abs( $points ) : abs( $points );
        $new       = max( 0, self::balance( $user_id ) + $delta );

        $wpdb->insert( self::table(), [
            'user_id'       => $user_id,
            'kind'          => 'points',
            'direction'     => $direction,
            'points'        => abs( $points ),
            'credit_amount' => 0,
            'balance_after' => $new,
            'reason'        => substr( $reason, 0, 255 ),
            'related_id'    => $related_id,
            'created_at'    => current_time( 'mysql' ),
        ] );

        do_action( 'ovr_loyalty_adjusted', $user_id, $delta, $new, $reason );
        return $new;
    }

    /**
     * Record store credit earned (e.g. a referral). Logged to the ledger; the
     * spendable balance is held by the Wallet, so we also push it there.
     */
    public static function add_credit( int $user_id, float $amount, string $reason, ?int $related_id = null ): void {
        global $wpdb;
        if ( $user_id <= 0 || $amount <= 0 ) {
            return;
        }
        $wpdb->insert( self::table(), [
            'user_id'       => $user_id,
            'kind'          => 'credit',
            'direction'     => 'earn',
            'points'        => 0,
            'credit_amount' => round( $amount, 2 ),
            'balance_after' => self::balance( $user_id ),
            'reason'        => substr( $reason, 0, 255 ),
            'related_id'    => $related_id,
            'created_at'    => current_time( 'mysql' ),
        ] );

        // Mirror to the account-credit wallet so it is actually spendable.
        if ( class_exists( '\OVR\Payment\Wallet' ) && method_exists( '\OVR\Payment\Wallet', 'credit' ) ) {
            \OVR\Payment\Wallet::credit( $user_id, $amount, $reason );
        }
    }

    /**
     * Recent ledger entries for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function history( int $user_id, int $limit = 50 ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d',
            $user_id,
            $limit
        ), ARRAY_A );
        return $rows ?: [];
    }

    /**
     * Award points (and any renewal bonus) when a payment is confirmed.
     *
     * @param int   $user_id Buyer.
     * @param array $context { payment_id, amount, payment_type, ... }.
     */
    public function on_payment_completed( int $user_id, array $context = [] ): void {
        if ( ! self::is_enabled() || $user_id <= 0 ) {
            return;
        }
        $settings = self::settings();
        $amount   = (float) ( $context['amount'] ?? 0 );
        $type     = (string) ( $context['payment_type'] ?? '' );
        $ref      = (int) ( $context['payment_id'] ?? 0 );

        $points = (int) floor( $amount * (float) $settings['points_per_dollar'] );
        if ( $points > 0 ) {
            /* translators: %s: payment type */
            self::adjust_points( $user_id, $points, 'earn', sprintf( __( 'Earned on %s payment', 'ovr-core' ), $type ?: 'purchase' ), $ref );
        }

        if ( 'subscription' === $type && (int) $settings['renewal_bonus_points'] > 0 ) {
            self::adjust_points( $user_id, (int) $settings['renewal_bonus_points'], 'earn', __( 'Subscription renewal bonus', 'ovr-core' ), $ref );
        }
    }

    /**
     * Total points + credit issued across all users (for the admin dashboard).
     *
     * @return array{points_outstanding:int, credit_issued:float, members:int}
     */
    public static function totals(): array {
        global $wpdb;
        $t = self::table();
        return [
            'points_outstanding' => (int) $wpdb->get_var(
                "SELECT COALESCE(SUM(CASE WHEN direction='earn' THEN points ELSE -points END),0) FROM {$t} WHERE kind='points'"
            ),
            'credit_issued'      => (float) $wpdb->get_var(
                "SELECT COALESCE(SUM(credit_amount),0) FROM {$t} WHERE kind='credit' AND direction='earn'"
            ),
            'members'            => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$t}" ),
        ];
    }
}
