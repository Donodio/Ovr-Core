<?php
/**
 * PayPal Gateway — Orders API (sandbox + live).
 *
 * start_checkout() creates a PayPal Order (intent CAPTURE) and redirects to the
 * approve link; finalize() (on return) captures the approved order. Credentials
 * resolve from the active environment (paypal_env = sandbox|live) with a
 * fallback to the legacy flat keys.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PayPalGateway implements PaymentGateway {

    use GatewayPayload;

    public function get_id(): string {
        return 'paypal';
    }

    public function get_label(): string {
        return __( 'PayPal', 'ovr-core' );
    }

    private function env(): string {
        $s = get_option( 'ovr_settings', [] );
        return 'live' === ( $s['paypal_env'] ?? 'sandbox' ) ? 'live' : 'sandbox';
    }

    private function client_id(): string {
        $s = get_option( 'ovr_settings', [] );
        $e = $this->env();
        return (string) ( $s[ "paypal_{$e}_client_id" ] ?? '' ) ?: (string) ( $s['paypal_client_id'] ?? '' );
    }

    private function secret(): string {
        $s = get_option( 'ovr_settings', [] );
        $e = $this->env();
        return (string) ( $s[ "paypal_{$e}_secret" ] ?? '' ) ?: (string) ( $s['paypal_secret'] ?? '' );
    }

    private function api_base(): string {
        return 'live' === $this->env() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    public function is_configured(): bool {
        return '' !== $this->client_id() && '' !== $this->secret();
    }

    /**
     * Fetch an OAuth2 access token, or '' on failure.
     */
    private function access_token(): string {
        $resp = wp_remote_post( $this->api_base() . '/v1/oauth2/token', [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $this->client_id() . ':' . $this->secret() ),
            ],
            'body'    => [ 'grant_type' => 'client_credentials' ],
            'timeout' => 20,
        ] );

        if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            return '';
        }
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        return (string) ( $body['access_token'] ?? '' );
    }

    public function start_checkout( array $args ): array {
        $user_id   = (int) ( $args['user_id']   ?? 0 );
        $amount    = (float) ( $args['amount']  ?? 0 );
        $currency  = strtoupper( substr( (string) ( $args['currency'] ?? 'USD' ), 0, 3 ) );

        if ( ! $this->payload_valid( $args ) ) {
            return [ 'success' => false, 'message' => __( 'Invalid checkout request.', 'ovr-core' ) ];
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'ovr_payments';
        $inserted = $wpdb->insert( $table, [
            'user_id'        => $user_id,
            'payment_type'   => $this->payload_type( $args ),
            'amount'         => $amount,
            'currency'       => $currency,
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

        $pending_redirect = add_query_arg( [ 'ovr_checkout' => 'pending', 'payment_id' => $payment_id ], $args['return_url'] ?? home_url( '/' ) );

        if ( ! $this->is_configured() ) {
            return [
                'success'      => true,
                'payment_id'   => $payment_id,
                'redirect_url' => $pending_redirect,
                'message'      => __( 'PayPal is not configured — payment recorded as pending.', 'ovr-core' ),
            ];
        }

        $token = $this->access_token();
        if ( ! $token ) {
            return [ 'success' => true, 'payment_id' => $payment_id, 'redirect_url' => $pending_redirect, 'message' => __( 'PayPal authentication failed.', 'ovr-core' ) ];
        }

        // PayPal appends ?token=<orderID>&PayerID=… to these on return.
        $return = add_query_arg( [ 'ovr_gw' => 'paypal', 'payment_id' => $payment_id ], $args['return_url'] ?? home_url( '/' ) );
        $cancel = add_query_arg( 'ovr_checkout', 'cancelled', $args['cancel_url'] ?? home_url( '/' ) );

        $order_resp = wp_remote_post( $this->api_base() . '/v2/checkout/orders', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 20,
            'body'    => wp_json_encode( [
                'intent'         => 'CAPTURE',
                'purchase_units' => [ [
                    'reference_id' => 'payment_' . $payment_id,
                    'description'  => mb_substr( $this->payload_item_name( $args ), 0, 127 ),
                    'amount'       => [ 'currency_code' => $currency, 'value' => number_format( $amount, 2, '.', '' ) ],
                ] ],
                'application_context' => [ 'return_url' => $return, 'cancel_url' => $cancel ],
            ] ),
        ] );

        if ( is_wp_error( $order_resp ) || 201 !== (int) wp_remote_retrieve_response_code( $order_resp ) ) {
            return [ 'success' => true, 'payment_id' => $payment_id, 'redirect_url' => $pending_redirect, 'message' => __( 'PayPal could not create the order.', 'ovr-core' ) ];
        }

        $order = json_decode( wp_remote_retrieve_body( $order_resp ), true );
        if ( ! empty( $order['id'] ) ) {
            $wpdb->update( $table, [ 'transaction_id' => (string) $order['id'] ], [ 'id' => $payment_id ], [ '%s' ], [ '%d' ] );
        }

        foreach ( (array) ( $order['links'] ?? [] ) as $link ) {
            if ( 'approve' === ( $link['rel'] ?? '' ) && ! empty( $link['href'] ) ) {
                return [ 'success' => true, 'payment_id' => $payment_id, 'redirect_url' => (string) $link['href'], 'message' => __( 'Redirecting to PayPal…', 'ovr-core' ) ];
            }
        }

        return [ 'success' => true, 'payment_id' => $payment_id, 'redirect_url' => $pending_redirect, 'message' => __( 'PayPal did not return an approval link.', 'ovr-core' ) ];
    }

    /**
     * Capture the approved order when PayPal redirects back.
     *
     * @param array $payment wp_ovr_payments row (assoc).
     * @return array{success:bool, message?:string}
     */
    public function finalize( array $payment ): array {
        $order_id = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        if ( ! $order_id ) {
            $order_id = (string) ( $payment['transaction_id'] ?? '' );
        }
        if ( ! $order_id || ! $this->is_configured() ) {
            return [ 'success' => false, 'message' => __( 'Payment could not be verified.', 'ovr-core' ) ];
        }

        $token = $this->access_token();
        if ( ! $token ) {
            return [ 'success' => false, 'message' => __( 'PayPal authentication failed.', 'ovr-core' ) ];
        }

        $resp = wp_remote_post( $this->api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 20,
            'body'    => '',
        ] );

        if ( is_wp_error( $resp ) ) {
            return [ 'success' => false, 'message' => $resp->get_error_message() ];
        }

        $code = (int) wp_remote_retrieve_response_code( $resp );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );

        // 201 Created on a fresh capture; 422 ORDER_ALREADY_CAPTURED is also "paid".
        $completed = ( in_array( $code, [ 200, 201 ], true ) && 'COMPLETED' === ( $data['status'] ?? '' ) )
            || ( 422 === $code && false !== strpos( wp_remote_retrieve_body( $resp ), 'ORDER_ALREADY_CAPTURED' ) );

        return $completed
            ? [ 'success' => true ]
            : [ 'success' => false, 'message' => __( 'PayPal did not confirm this payment.', 'ovr-core' ) ];
    }

    public function handle_webhook( array $payload ): array {
        do_action( 'ovr_paypal_webhook', $payload );
        return [ 'success' => true ];
    }
}
