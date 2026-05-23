<?php
/**
 * Wallet / Balance.
 *
 * Per-user balance held in user meta. Every change is logged to
 * wp_ovr_wallet_transactions for the "My Balance" tab + audit trail.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Wallet {

    public const META_BALANCE = 'ovr_balance';

    public function init(): void {
        // Recharge → credit handler.
        add_action( 'ovr_payment_completed', [ $this, 'maybe_credit_topup' ], 10, 2 );
    }

    public static function get_balance( int $user_id ): float {
        return (float) get_user_meta( $user_id, self::META_BALANCE, true );
    }

    /**
     * Credit the user's balance. Returns the new balance.
     */
    public static function credit( int $user_id, float $amount, string $description = '', ?int $payment_id = null ): float {
        return self::record( $user_id, 'credit', abs( $amount ), $description, $payment_id );
    }

    /**
     * Debit the user's balance. Returns new balance, or WP_Error if insufficient funds.
     */
    public static function debit( int $user_id, float $amount, string $description = '', ?int $payment_id = null ) {
        $balance = self::get_balance( $user_id );
        if ( $balance + 0.0001 < $amount ) {
            return new \WP_Error( 'insufficient_funds', __( 'Insufficient wallet balance.', 'ovr-core' ) );
        }
        return self::record( $user_id, 'debit', abs( $amount ), $description, $payment_id );
    }

    /**
     * Apply a transaction and return the new balance.
     */
    private static function record( int $user_id, string $kind, float $amount, string $description, ?int $payment_id ): float {
        $current = self::get_balance( $user_id );
        $new     = ( 'credit' === $kind ) ? $current + $amount : $current - $amount;

        update_user_meta( $user_id, self::META_BALANCE, round( $new, 2 ) );

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_wallet_transactions';
        $wpdb->insert( $table, [
            'user_id'            => $user_id,
            'kind'               => $kind,
            'amount'             => $amount,
            'currency'           => self::default_currency(),
            'balance_after'      => round( $new, 2 ),
            'description'        => substr( $description, 0, 255 ),
            'related_payment_id' => $payment_id,
        ], [ '%d', '%s', '%f', '%s', '%f', '%s', '%d' ] );

        do_action( 'ovr_wallet_changed', $user_id, $kind, $amount, round( $new, 2 ) );

        return round( $new, 2 );
    }

    /**
     * Recent transaction list for the dashboard.
     */
    public static function get_transactions( int $user_id, int $limit = 50 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_wallet_transactions';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * If a completed payment is flagged as a wallet topup (kind='topup'),
     * credit the wallet automatically.
     */
    public function maybe_credit_topup( int $user_id, array $context = [] ): void {
        if ( ( $context['payment_type'] ?? '' ) !== 'topup' ) return;
        $amount = (float) ( $context['amount'] ?? 0 );
        if ( $amount <= 0 ) return;
        self::credit(
            $user_id,
            $amount,
            __( 'Wallet topup', 'ovr-core' ),
            (int) ( $context['payment_id'] ?? 0 ) ?: null
        );
    }

    private static function default_currency(): string {
        $settings = get_option( 'ovr_settings', [] );
        return strtoupper( substr( (string) ( $settings['currency'] ?? 'USD' ), 0, 3 ) );
    }
}
