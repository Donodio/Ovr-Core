<?php
/**
 * Account Credit.
 *
 * A per-user store-credit balance held in user meta, logged to
 * wp_ovr_wallet_transactions for an audit trail. Credit may ONLY be granted by
 * an admin (referral bonus, overpayment, goodwill) and spent against a
 * subscription renewal or listing-upgrade purchase. It can never be topped up
 * by the user, withdrawn, transferred, or cashed out — this site stores no
 * landlord financial information.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Wallet {

    public const META_BALANCE = 'ovr_balance';

    public function init(): void {}

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

    private static function default_currency(): string {
        $settings = get_option( 'ovr_settings', [] );
        return strtoupper( substr( (string) ( $settings['currency'] ?? 'USD' ), 0, 3 ) );
    }
}
