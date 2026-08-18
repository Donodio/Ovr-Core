<?php
/**
 * Authorize.net Gateway — hosted payment form "punchout" (SIM PAYMENT_FORM).
 *
 * start_checkout() builds a signed redirect to Authorize.net's hosted card
 * form (the card data is entered on Authorize.net, never on our servers) and
 * returns that URL. The buyer pays there and is redirected back to our
 * payment-success page, where finalize() re-verifies the transaction against
 * the Authorize.net API before marking the order paid. Credentials are read
 * from ovr_settings (authnet_{env}_login_id / authnet_{env}_transaction_key).
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

    private function env(): string {
        $s = get_option( 'ovr_settings', [] );
        return 'live' === ( $s['authnet_env'] ?? 'sandbox' ) ? 'live' : 'sandbox';
    }

    private function login_id(): string {
        $s     = get_option( 'ovr_settings', [] );
        $env   = $this->env();
        $value = (string) ( $s[ "authnet_{$env}_login_id" ] ?? '' );
        if ( '' === $value ) {
            $value = (string) ( $s['authnet_login_id'] ?? '' );
        }
        return $value;
    }

    private function transaction_key(): string {
        $s     = get_option( 'ovr_settings', [] );
        $env   = $this->env();
        $value = (string) ( $s[ "authnet_{$env}_transaction_key" ] ?? '' );
        if ( '' === $value ) {
            $value = (string) ( $s['authnet_transaction_key'] ?? '' );
        }
        return $value;
    }

    public function is_configured(): bool {
        return '' !== $this->login_id() && '' !== $this->transaction_key();
    }

    private function form_action(): string {
        return 'live' === $this->env()
            ? 'https://secure.authorize.net/gateway/transact.dll'
            : 'https://test.authorize.net/gateway/transact.dll';
    }

    private function api_endpoint(): string {
        return 'live' === $this->env()
            ? 'https://api.authorize.net/xml/v1/request.api'
            : 'https://apitest.authorize.net/xml/v1/request.api';
    }

    public function start_checkout( array $args ): array {
        $user_id  = (int) ( $args['user_id'] ?? 0 );
        $amount   = (float) ( $args['amount']  ?? 0 );
        $currency = strtoupper( substr( (string) ( $args['currency'] ?? 'USD' ), 0, 3 ) );

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

        $pending_redirect = add_query_arg( [
            'ovr_checkout' => 'pending',
            'gateway'      => 'authorize_net',
            'payment_id'   => $payment_id,
        ], $args['return_url'] ?? home_url( '/' ) );

        if ( ! $this->is_configured() ) {
            return [
                'success'      => true,
                'payment_id'   => $payment_id,
                'redirect_url' => $pending_redirect,
                'message'      => __( 'Authorize.net is not configured — payment recorded as pending. Add your API credentials to enable live checkout.', 'ovr-core' ),
            ];
        }

        $user      = get_userdata( $user_id );
        $email     = $user ? $user->user_email : '';
        $item_name = mb_substr( $this->payload_item_name( $args ), 0, 255 );

        $return = add_query_arg( [
            'ovr_gw'      => 'authorize_net',
            'payment_id'  => $payment_id,
        ], $args['return_url'] ?? home_url( '/' ) );

        $cancel = add_query_arg( [
            'ovr_checkout' => 'cancelled',
            'ovr_gw'       => 'authorize_net',
            'payment_id'   => $payment_id,
        ], $args['cancel_url'] ?? home_url( '/' ) );

        $sequence    = (int) ( microtime( true ) * 1000 ) . $payment_id;
        $timestamp   = time();
        $amount_str  = number_format( $amount, 2, '.', '' );
        $fingerprint = $this->fingerprint( $sequence, $timestamp, $amount_str, $currency );

        $fields = [
            'x_login'              => $this->login_id(),
            'x_type'               => 'AUTH_CAPTURE',
            'x_amount'             => $amount_str,
            'x_currency_code'      => $currency,
            'x_show_form'          => 'PAYMENT_FORM',
            'x_fp_sequence'        => (string) $sequence,
            'x_fp_timestamp'       => (string) $timestamp,
            'x_fp_hash'            => $fingerprint,
            'x_relay_response'     => 'FALSE',
            'x_receipt_link_url'   => $return,
            'x_receipt_link_method'=> 'GET',
            'x_cancel_url'         => $cancel,
            'x_cancel_url_method'  => 'GET',
            'x_invoice_num'        => (string) $payment_id,
            'x_description'        => $item_name,
            'x_cust_id'            => (string) $user_id,
            'x_email'              => $email,
            'x_test_request'       => 'FALSE',
        ];

        $redirect_url = $this->form_action() . '?' . http_build_query( $fields );

        return [
            'success'      => true,
            'payment_id'   => $payment_id,
            'redirect_url' => $redirect_url,
            'message'      => __( 'Redirecting to Authorize.net…', 'ovr-core' ),
        ];
    }

    private function fingerprint( int $sequence, int $timestamp, string $amount, string $currency ): string {
        $msg = '^' . $this->login_id() . '^' . $sequence . '^' . $timestamp . '^' . $amount . '^' . $currency . '^';
        return hash_hmac( 'sha512', $msg, $this->transaction_key() );
    }

    /**
     * Verify the returning transaction against the Authorize.net API.
     *
     * @param array $payment wp_ovr_payments row (assoc).
     * @return array{success:bool, failed?:bool, code?:string, message?:string}
     */
    public function finalize( array $payment ): array {
        if ( ! $this->is_configured() ) {
            return [ 'success' => false, 'message' => __( 'Payment could not be verified.', 'ovr-core' ) ];
        }

        $trans_id = isset( $_GET['x_trans_id'] ) ? sanitize_text_field( wp_unslash( $_GET['x_trans_id'] ) ) : '';
        if ( ! $trans_id ) {
            $trans_id = (string) ( $payment['transaction_id'] ?? '' );
        }

        $response_code = isset( $_GET['x_response_code'] ) ? sanitize_text_field( wp_unslash( $_GET['x_response_code'] ) ) : '';

        if ( '' !== $response_code && '1' !== $response_code ) {
            return [
                'success' => false,
                'failed'  => true,
                'code'    => $response_code,
                'message' => __( 'Authorize.net did not approve this payment.', 'ovr-core' ),
            ];
        }

        if ( ! $trans_id ) {
            return [ 'success' => false, 'message' => __( 'Authorize.net did not return a transaction id.', 'ovr-core' ) ];
        }

        $details = $this->get_transaction_details( $trans_id );
        if ( null === $details ) {
            return [ 'success' => false, 'message' => __( 'Could not reach Authorize.net to verify the payment.', 'ovr-core' ) ];
        }

        $status = (string) ( $details['transactionStatus'] ?? '' );
        $good   = [ 'settledSuccessfully', 'capturedPendingSettlement', 'authorizedPendingCapture', 'underReview', 'approvedReview' ];
        $amount = (float) ( $details['authAmount'] ?? $details['settleAmount'] ?? 0 );

        $expected = (float) ( $payment['amount'] ?? 0 );
        if ( ! in_array( $status, $good, true ) ) {
            return [
                'success' => false,
                'failed'  => true,
                'code'    => $status,
                'message' => __( 'Authorize.net reports this payment was not completed.', 'ovr-core' ),
            ];
        }

        if ( $expected > 0 && abs( $amount - $expected ) > 0.01 ) {
            return [
                'success' => false,
                'failed'  => true,
                'code'    => 'amount_mismatch',
                'message' => __( 'The verified amount does not match this order.', 'ovr-core' ),
            ];
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'ovr_payments',
            [ 'transaction_id' => $trans_id ],
            [ 'id' => (int) $payment['id'] ],
            [ '%s' ],
            [ '%d' ]
        );

        return [ 'success' => true ];
    }

    /**
     * Call getTransactionDetails and return the transaction node, or null on error.
     */
    private function get_transaction_details( string $trans_id ): ?array {
        $resp = wp_remote_post( $this->api_endpoint(), [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 20,
            'body'    => wp_json_encode( [
                'getTransactionDetailsRequest' => [
                    'merchantAuthentication' => [
                        'name'           => $this->login_id(),
                        'transactionKey' => $this->transaction_key(),
                    ],
                    'transId' => $trans_id,
                ],
            ] ),
        ] );

        if ( is_wp_error( $resp ) ) {
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! is_array( $data ) || 'Error' === ( $data['messages']['resultCode'] ?? '' ) ) {
            return null;
        }

        return is_array( $data['transaction'] ?? null ) ? $data['transaction'] : [];
    }

    public function handle_webhook( array $payload ): array {
        do_action( 'ovr_authnet_webhook', $payload );
        return [ 'success' => true, 'message' => 'Authorize.net webhook received.' ];
    }
}
