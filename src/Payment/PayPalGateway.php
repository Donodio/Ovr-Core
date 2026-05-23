<?php
/**
 * PayPal Gateway — Phase 1 stub.
 *
 * Records a 'pending' payment row and returns a placeholder redirect.
 * Phase 2 swaps `start_checkout()` for a real PayPal Smart Buttons /
 * Orders API call. Webhook handler signature is reserved.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PayPalGateway implements PaymentGateway {

    public function get_id(): string {
        return 'paypal';
    }

    public function get_label(): string {
        return __( 'PayPal', 'ovr-core' );
    }

    public function is_configured(): bool {
        $s = get_option( 'ovr_settings', [] );
        return ! empty( $s['paypal_client_id'] ) && ! empty( $s['paypal_secret'] );
    }

    public function start_checkout( array $args ): array {
        $user_id   = (int) ( $args['user_id']   ?? 0 );
        $plan_slug = (string) ( $args['plan_slug'] ?? '' );
        $amount    = (float) ( $args['amount']  ?? 0 );

        if ( ! $user_id || ! $plan_slug || $amount <= 0 ) {
            return [ 'success' => false, 'message' => __( 'Invalid checkout request.', 'ovr-core' ) ];
        }

        $payment_type = $args['payment_type'] ?? 'subscription';

        global $wpdb;
        $table    = $wpdb->prefix . 'ovr_payments';
        $inserted = $wpdb->insert( $table, [
            'user_id'        => $user_id,
            'payment_type'   => $payment_type,
            'amount'         => $amount,
            'currency'       => strtoupper( substr( (string) ( $args['currency'] ?? 'USD' ), 0, 3 ) ),
            'gateway'        => $this->get_id(),
            'transaction_id' => '',
            'status'         => 'pending',
            'meta_data'      => wp_json_encode( [ 'plan_slug' => $plan_slug ] ),
        ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

        if ( false === $inserted ) {
            return [ 'success' => false, 'message' => __( 'Could not record payment.', 'ovr-core' ) ];
        }

        $payment_id = (int) $wpdb->insert_id;

        do_action( 'ovr_checkout_started', $payment_id, $args, $this->get_id() );

        $redirect_url = add_query_arg( [
            'ovr_checkout' => 'pending',
            'gateway'      => 'paypal',
            'payment_id'   => $payment_id,
        ], $args['return_url'] ?? home_url( '/' ) );

        $message = __( 'PayPal is not configured yet — payment recorded as pending.', 'ovr-core' );

        if ( $this->is_configured() ) {
            $s = get_option( 'ovr_settings', [] );
            $client_id = $s['paypal_client_id'];
            $secret    = $s['paypal_secret'];
            $mode      = $s['paypal_mode'] ?? 'sandbox';

            $base_url = 'sandbox' === $mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

            // 1. Get Access Token
            $auth_response = wp_remote_post( $base_url . '/v1/oauth2/token', [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
                ],
                'body' => [ 'grant_type' => 'client_credentials' ],
            ] );

            if ( ! is_wp_error( $auth_response ) && 200 === wp_remote_retrieve_response_code( $auth_response ) ) {
                $auth_body = json_decode( wp_remote_retrieve_body( $auth_response ), true );
                $access_token = $auth_body['access_token'] ?? '';

                if ( $access_token ) {
                    // 2. Create Order
                    $order_payload = [
                        'intent' => 'CAPTURE',
                        'purchase_units' => [
                            [
                                'reference_id' => 'payment_' . $payment_id,
                                'amount' => [
                                    'currency_code' => strtoupper( substr( (string) ( $args['currency'] ?? 'USD' ), 0, 3 ) ),
                                    'value'         => number_format( $amount, 2, '.', '' ),
                                ],
                            ],
                        ],
                        'application_context' => [
                            'return_url' => $args['return_url'] ?? home_url( '/' ),
                            'cancel_url' => $args['cancel_url'] ?? home_url( '/' ),
                        ],
                    ];

                    $order_response = wp_remote_post( $base_url . '/v2/checkout/orders', [
                        'headers' => [
                            'Content-Type'  => 'application/json',
                            'Authorization' => 'Bearer ' . $access_token,
                        ],
                        'body' => wp_json_encode( $order_payload ),
                    ] );

                    if ( ! is_wp_error( $order_response ) && 201 === wp_remote_retrieve_response_code( $order_response ) ) {
                        $order_body = json_decode( wp_remote_retrieve_body( $order_response ), true );

                        // Save transaction ID
                        if ( ! empty( $order_body['id'] ) ) {
                            $wpdb->update( $table, [ 'transaction_id' => $order_body['id'] ], [ 'id' => $payment_id ], [ '%s' ], [ '%d' ] );
                        }

                        // Find approve link
                        if ( ! empty( $order_body['links'] ) ) {
                            foreach ( $order_body['links'] as $link ) {
                                if ( 'approve' === $link['rel'] ) {
                                    $redirect_url = $link['href'];
                                    $message = __( 'Redirecting to PayPal…', 'ovr-core' );
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'success'      => true,
            'payment_id'   => $payment_id,
            'redirect_url' => $redirect_url,
            'message'      => $message,
        ];
    }

    public function handle_webhook( array $payload ): array {
        do_action( 'ovr_paypal_webhook', $payload );
        return [ 'success' => true, 'message' => 'Stub — implemented in Phase 2.' ];
    }
}
