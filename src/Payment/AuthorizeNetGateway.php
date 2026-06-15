<?php
/**
 * Authorize.net Gateway — Phase 1 stub.
 *
 * Same shape as Stripe / PayPal stubs. Phase 2 swaps in a real Accept Hosted
 * payment-form redirect or AIM transaction.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AuthorizeNetGateway implements PaymentGateway {

    use GatewayPayload;

    public function get_id(): string {
        return 'authorize_net';
    }

    public function get_label(): string {
        return __( 'Authorize.net', 'ovr-core' );
    }

    public function is_configured(): bool {
        $s = get_option( 'ovr_settings', [] );
        return ! empty( $s['authnet_login_id'] ) && ! empty( $s['authnet_transaction_key'] );
    }

    public function start_checkout( array $args ): array {
        $user_id = (int) ( $args['user_id'] ?? 0 );
        $amount  = (float) ( $args['amount'] ?? 0 );

        if ( ! $this->payload_valid( $args ) ) {
            return [ 'success' => false, 'message' => __( 'Invalid checkout request.', 'ovr-core' ) ];
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'ovr_payments';
        $inserted = $wpdb->insert( $table, [
            'user_id'        => $user_id,
            'payment_type'   => $this->payload_type( $args ),
            'amount'         => $amount,
            'currency'       => strtoupper( substr( (string) ( $args['currency'] ?? 'USD' ), 0, 3 ) ),
            'gateway'        => $this->get_id(),
            'transaction_id' => '',
            'status'         => 'pending',
            'meta_data'      => wp_json_encode( $this->payload_meta( $args ) ),
        ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

        if ( false === $inserted ) {
            return [ 'success' => false, 'message' => __( 'Could not record payment.', 'ovr-core' ) ];
        }

        $payment_id = (int) $wpdb->insert_id;

        do_action( 'ovr_checkout_started', $payment_id, $args, $this->get_id() );

        return [
            'success'      => true,
            'payment_id'   => $payment_id,
            'redirect_url' => add_query_arg( [
                'ovr_checkout' => 'pending',
                'gateway'      => 'authorize_net',
                'payment_id'   => $payment_id,
            ], $args['return_url'] ?? home_url( '/' ) ),
            'message'      => $this->is_configured()
                ? __( 'Redirecting to Authorize.net…', 'ovr-core' )
                : __( 'Authorize.net is not configured yet — payment recorded as pending. Configure your API credentials in Phase 2 to enable live checkout.', 'ovr-core' ),
        ];
    }

    public function handle_webhook( array $payload ): array {
        do_action( 'ovr_authnet_webhook', $payload );
        return [ 'success' => true, 'message' => 'Stub — implemented in Phase 2.' ];
    }
}
