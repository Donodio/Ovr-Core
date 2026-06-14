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

    use GatewayPayload;

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
        $user_id = (int) ( $args['user_id'] ?? 0 );
        $amount  = (float) ( $args['amount'] ?? 0 );

        if ( ! $this->payload_valid( $args ) ) {
            return [ 'success' => false, 'message' => __( 'Invalid checkout request.', 'ovr-core' ) ];
        }

        $balance = Wallet::get_balance( $user_id );
        if ( $balance + 0.0001 < $amount ) {
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: 1: required amount, 2: available credit */
                    __( 'Your available credit (%2$s) does not cover the %1$s total. Please choose another payment method.', 'ovr-core' ),
                    number_format( $amount, 2 ),
                    number_format( $balance, 2 )
                ),
            ];
        }

        $payment_type = $this->payload_type( $args );
        $meta         = $this->payload_meta( $args );
        $item_name    = $this->payload_item_name( $args );

        // Record the (already-paid) payment.
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';
        $inserted = $wpdb->insert( $table, [
            'user_id'        => $user_id,
            'payment_type'   => $payment_type,
            'amount'         => $amount,
            'currency'       => strtoupper( substr( (string) ( $args['currency'] ?? 'USD' ), 0, 3 ) ),
            'gateway'        => $this->get_id(),
            'transaction_id' => 'wallet_' . wp_generate_uuid4(),
            'status'         => 'completed',
            'meta_data'      => wp_json_encode( $meta ),
        ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

        if ( false === $inserted ) {
            return [ 'success' => false, 'message' => __( 'Could not record payment.', 'ovr-core' ) ];
        }

        $payment_id = (int) $wpdb->insert_id;

        // Debit balance.
        Wallet::debit( $user_id, $amount, $item_name, $payment_id );

        // Fire downstream hooks (Lifecycle + UpgradeActivator listen to this).
        do_action( 'ovr_payment_completed', $user_id, [
            'payment_id'   => $payment_id,
            'plan_slug'    => (string) ( $meta['plan_slug'] ?? '' ),
            'amount'       => $amount,
            'gateway'      => $this->get_id(),
            'payment_type' => $payment_type,
        ] );

        return [
            'success'      => true,
            'payment_id'   => $payment_id,
            'redirect_url' => add_query_arg( [
                'ovr_checkout' => 'completed',
                'payment_id'   => $payment_id,
            ], $args['return_url'] ?? home_url( '/' ) ),
            'message'      => __( 'Payment completed from your balance.', 'ovr-core' ),
        ];
    }

    public function handle_webhook( array $payload ): array {
        return [ 'success' => true ];
    }
}
