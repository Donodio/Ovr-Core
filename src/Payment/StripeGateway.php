<?php
/**
 * Stripe Gateway — Checkout Sessions (sandbox + live).
 *
 * start_checkout() creates a real Stripe Checkout Session and returns its URL;
 * finalize() (called when Stripe redirects back) retrieves the session and
 * reports whether it was paid. Credentials resolve from the active environment
 * (stripe_env = sandbox|live) with a fallback to the legacy flat keys.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class StripeGateway implements PaymentGateway {

    use GatewayPayload;

    private const API_BASE = 'https://api.stripe.com';

    public function get_id(): string {
        return 'stripe';
    }

    public function get_label(): string {
        return __( 'Stripe', 'ovr-core' );
    }

    /**
     * Active environment: 'sandbox' or 'live'.
     */
    private function env(): string {
        $s = get_option( 'ovr_settings', [] );
        return 'live' === ( $s['stripe_env'] ?? 'sandbox' ) ? 'live' : 'sandbox';
    }

    /**
     * Secret API key for the active environment (falls back to legacy key).
     */
    private function secret_key(): string {
        $s   = get_option( 'ovr_settings', [] );
        $env = $this->env();
        return (string) ( $s[ "stripe_{$env}_secret_key" ] ?? '' ) ?: (string) ( $s['stripe_secret_key'] ?? '' );
    }

    public function is_configured(): bool {
        return '' !== $this->secret_key();
    }

    public function start_checkout( array $args ): array {
        $user_id   = (int) ( $args['user_id']   ?? 0 );
        $amount    = (float) ( $args['amount']  ?? 0 );
        $currency  = strtolower( substr( (string) ( $args['currency'] ?? 'USD' ), 0, 3 ) );

        if ( ! $user_id ) {
            return [ 'success' => false, 'message' => __( 'Authentication required.', 'ovr-core' ) ];
        }
        if ( ! $this->payload_valid( $args ) ) {
            return [ 'success' => false, 'message' => __( 'Invalid purchase or amount.', 'ovr-core' ) ];
        }

        $payment_type = $this->payload_type( $args );
        $meta         = $this->payload_meta( $args );

        // Always create a pending row so the admin sees the intent.
        global $wpdb;
        $table    = $wpdb->prefix . 'ovr_payments';
        $inserted = $wpdb->insert( $table, [
            'user_id'        => $user_id,
            'payment_type'   => $payment_type,
            'amount'         => $amount,
            'currency'       => strtoupper( $currency ),
            'gateway'        => $this->get_id(),
            'transaction_id' => '',
            'status'         => 'pending',
            'meta_data'      => wp_json_encode( $meta ),
        ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

        if ( false === $inserted ) {
            return [ 'success' => false, 'message' => __( 'Could not create payment record.', 'ovr-core' ) ];
        }
        $payment_id = (int) $wpdb->insert_id;

        do_action( 'ovr_checkout_started', $payment_id, $args, $this->get_id() );

        // Not configured → fall back to the pending-notice page (no live charge).
        if ( ! $this->is_configured() ) {
            return [
                'success'      => true,
                'payment_id'   => $payment_id,
                'redirect_url' => add_query_arg( [ 'ovr_checkout' => 'pending', 'payment_id' => $payment_id ], $args['return_url'] ?? home_url( '/' ) ),
                'message'      => __( 'Stripe is not configured — payment recorded as pending.', 'ovr-core' ),
            ];
        }

        $item_name = $this->payload_item_name( $args );
        $return    = $args['return_url'] ?? home_url( '/' );

        // success_url MUST keep the literal {CHECKOUT_SESSION_ID} placeholder.
        $success  = add_query_arg( [ 'ovr_gw' => 'stripe', 'payment_id' => $payment_id ], $return );
        $success .= ( false !== strpos( $success, '?' ) ? '&' : '?' ) . 'session_id={CHECKOUT_SESSION_ID}';
        $cancel   = add_query_arg( 'ovr_checkout', 'cancelled', $args['cancel_url'] ?? home_url( '/' ) );

        $body = [
            'mode'                                          => 'payment',
            'success_url'                                   => $success,
            'cancel_url'                                    => $cancel,
            'client_reference_id'                           => (string) $payment_id,
            'metadata[payment_id]'                          => (string) $payment_id,
            'metadata[payment_type]'                        => $payment_type,
            'line_items[0][quantity]'                       => '1',
            'line_items[0][price_data][currency]'           => $currency,
            'line_items[0][price_data][unit_amount]'        => (string) (int) round( $amount * 100 ),
            'line_items[0][price_data][product_data][name]' => $item_name,
        ];
        if ( ! empty( $meta['plan_slug'] ) ) {
            $body['metadata[plan_slug]'] = (string) $meta['plan_slug'];
        }

        $resp = wp_remote_post( self::API_BASE . '/v1/checkout/sessions', [
            'headers' => [ 'Authorization' => 'Bearer ' . $this->secret_key() ],
            'body'    => $body,
            'timeout' => 20,
        ] );

        if ( is_wp_error( $resp ) ) {
            return [ 'success' => false, 'payment_id' => $payment_id, 'message' => $resp->get_error_message() ];
        }

        $code = (int) wp_remote_retrieve_response_code( $resp );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( 200 !== $code || empty( $data['url'] ) ) {
            $msg = $data['error']['message'] ?? __( 'Stripe could not start checkout.', 'ovr-core' );
            return [ 'success' => false, 'payment_id' => $payment_id, 'message' => $msg ];
        }

        // Save the session id so finalize() can verify it on return.
        $wpdb->update( $table, [ 'transaction_id' => (string) $data['id'] ], [ 'id' => $payment_id ], [ '%s' ], [ '%d' ] );

        return [
            'success'      => true,
            'payment_id'   => $payment_id,
            'redirect_url' => (string) $data['url'],
            'message'      => __( 'Redirecting to Stripe…', 'ovr-core' ),
        ];
    }

    /**
     * Verify a returning payment by retrieving its Checkout Session.
     *
     * @param array $payment wp_ovr_payments row (assoc).
     * @return array{success:bool, message?:string}
     */
    public function finalize( array $payment ): array {
        $session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
        if ( ! $session_id ) {
            $session_id = (string) ( $payment['transaction_id'] ?? '' );
        }
        if ( ! $session_id || ! $this->is_configured() ) {
            return [ 'success' => false, 'message' => __( 'Payment could not be verified.', 'ovr-core' ) ];
        }

        $resp = wp_remote_get( self::API_BASE . '/v1/checkout/sessions/' . rawurlencode( $session_id ), [
            'headers' => [ 'Authorization' => 'Bearer ' . $this->secret_key() ],
            'timeout' => 20,
        ] );

        if ( is_wp_error( $resp ) ) {
            return [ 'success' => false, 'message' => $resp->get_error_message() ];
        }

        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        $paid = is_array( $data ) && ( 'paid' === ( $data['payment_status'] ?? '' ) || 'complete' === ( $data['status'] ?? '' ) );

        return $paid
            ? [ 'success' => true ]
            : [ 'success' => false, 'message' => __( 'Stripe reports this payment is not complete yet.', 'ovr-core' ) ];
    }

    public function handle_webhook( array $payload ): array {
        do_action( 'ovr_stripe_webhook', $payload );
        return [ 'success' => true ];
    }
}
