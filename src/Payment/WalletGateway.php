<?php
/**
 * Wallet Gateway — pay using internal balance.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WalletGateway implements PaymentGateway {

    public function get_id(): string {
        return 'wallet';
    }

    public function get_label(): string {
        return __( 'Wallet (Balance)', 'ovr-core' );
    }

    public function is_configured(): bool {
        return true;
    }

    public function start_checkout( array $args ): array {
        $user_id   = (int) ( $args['user_id']   ?? 0 );
        $plan_slug = (string) ( $args['plan_slug'] ?? '' );
        $amount    = (float) ( $args['amount']  ?? 0 );

        if ( ! $user_id || ! $plan_slug || $amount <= 0 ) {
            return [ 'success' => false, 'message' => __( 'Invalid checkout request.', 'ovr-core' ) ];
        }

        $balance = Wallet::get_balance( $user_id );
        if ( $balance + 0.0001 < $amount ) {
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: 1: required amount, 2: current balance */
                    __( 'Not enough balance. Plan costs %1$s but your balance is %2$s. Add funds or pick another payment method.', 'ovr-core' ),
                    number_format( $amount, 2 ),
                    number_format( $balance, 2 )
                ),
            ];
        }

        // Record the payment.
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';
        $inserted = $wpdb->insert( $table, [
            'user_id'        => $user_id,
            'payment_type'   => 'subscription',
            'amount'         => $amount,
            'currency'       => strtoupper( substr( (string) ( $args['currency'] ?? 'USD' ), 0, 3 ) ),
            'gateway'        => $this->get_id(),
            'transaction_id' => 'wallet_' . wp_generate_uuid4(),
            'status'         => 'completed',
            'meta_data'      => wp_json_encode( [ 'plan_slug' => $plan_slug ] ),
        ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

        if ( false === $inserted ) {
            return [ 'success' => false, 'message' => __( 'Could not record payment.', 'ovr-core' ) ];
        }

        $payment_id = (int) $wpdb->insert_id;

        // Debit balance.
        Wallet::debit(
            $user_id,
            $amount,
            sprintf( __( 'Subscription: %s', 'ovr-core' ), $plan_slug ),
            $payment_id
        );

        // Fire downstream hooks (Lifecycle listens to this).
        do_action( 'ovr_payment_completed', $user_id, [
            'payment_id' => $payment_id,
            'plan_slug'  => $plan_slug,
            'amount'     => $amount,
            'gateway'    => $this->get_id(),
        ] );

        return [
            'success'      => true,
            'payment_id'   => $payment_id,
            'redirect_url' => add_query_arg( [
                'ovr_checkout' => 'completed',
                'payment_id'   => $payment_id,
            ], $args['return_url'] ?? home_url( '/' ) ),
            'message'      => __( 'Subscription activated from wallet.', 'ovr-core' ),
        ];
    }

    public function handle_webhook( array $payload ): array {
        return [ 'success' => true ];
    }
}
