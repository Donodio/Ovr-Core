<?php
/**
 * PayPal Webhook Handler — receiver, signature verification, correlation,
 * and idempotent local completion for PAYMENT.CAPTURE.COMPLETED events.
 *
 * @package OVR\Payment
 * @since   2.13.0
 */

namespace OVR\Payment;

use OVR\Core\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayPalWebhookHandler {

    private const WEBHOOK_EVENTS_TABLE = 'ovr_payment_webhook_events';
    private const RECONCILIATION_HOOK  = 'ovr_paypal_reconciliation';
    private const RECONCILIATION_SCHEDULE = 'ovr_fifteen_minutes';
    private const BATCH_SIZE           = 25;
    private const RECONCILIATION_GRACE_MINUTES = 15;

    /**
     * Register webhook route, reconciliation cron, and audit listeners.
     */
    public function init(): void {
        add_action( 'admin_post_nopriv_ovr_paypal_webhook', [ $this, 'handle_webhook' ] );
        $this->register_reconciliation_cron();
    }

    // ------------------------------------------------------------------
    // Webhook configuration helpers
    // ------------------------------------------------------------------

    /**
     * Resolve the configured PayPal webhook ID for the active environment.
     */
    public static function webhook_id(): string {
        $s = get_option( 'ovr_settings', [] );
        $e = 'live' === ( $s['paypal_env'] ?? 'sandbox' ) ? 'live' : 'sandbox';
        return (string) ( $s[ "paypal_{$e}_webhook_id" ] ?? '' );
    }

    /**
     * Resolve the PayPal API base for the active environment.
     */
    private static function api_base(): string {
        $s = get_option( 'ovr_settings', [] );
        return 'live' === ( $s['paypal_env'] ?? 'sandbox' ) ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    // ------------------------------------------------------------------
    // Webhook endpoint
    // ------------------------------------------------------------------

    /**
     * Handle incoming PayPal webhook POST.
     */
    public function handle_webhook(): void {
        $raw_body = file_get_contents( 'php://input' );
        $response = $this->process_webhook( $raw_body );
        $this->webhook_response( $response['code'], $response['message'] );
    }

    /**
     * Process a raw webhook body. Separated from handle_webhook() to allow
     * testing without php://input and to keep the verification logic reusable.
     */
    public function process_webhook( string $raw_body ): array {
        if ( empty( $raw_body ) ) {
            return [ 'code' => 400, 'message' => 'Empty body' ];
        }

        $payload = json_decode( $raw_body, true );
        if ( ! is_array( $payload ) ) {
            return [ 'code' => 400, 'message' => 'Invalid JSON' ];
        }

        $event_type = (string) ( $payload['event_type'] ?? '' );
        $provider_event_id = (string) ( $payload['id'] ?? '' );

        if ( empty( $provider_event_id ) || empty( $event_type ) ) {
            return [ 'code' => 400, 'message' => 'Missing event id or type' ];
        }

        // Idempotency: only suppress events that already reached a terminal
        // state. An unverified delivery MUST NOT permanently block a later
        // genuine verified delivery of the same provider event.
        global $wpdb;
        $table = $wpdb->prefix . self::WEBHOOK_EVENTS_TABLE;
        $existing = $wpdb->get_row( $wpdb->prepare( "
            SELECT id, status FROM {$table}
            WHERE provider = 'paypal'
              AND provider_event_id = %s
            LIMIT 1
        ", $provider_event_id ), ARRAY_A );

        if ( $existing ) {
            $terminal_statuses = [
                'completed',
                'verified',
                'ignored',
                'unmatched',
                'amount_mismatch',
                'currency_mismatch',
                'already_completed',
            ];
            if ( in_array( $existing['status'], $terminal_statuses, true ) ) {
                return [ 'code' => 200, 'message' => 'Duplicate' ];
            }
            // Non-terminal state (received / invalid_signature): the event
            // has not been successfully processed yet, so a later verified
            // delivery must be allowed to proceed. Reuse the existing row.
            $event_id = (int) $existing['id'];
        } else {
            // Record receipt before processing so we never re-process on crash.
            $inserted = $wpdb->insert( $table, [
                'provider'          => 'paypal',
                'provider_event_id' => $provider_event_id,
                'event_type'        => $event_type,
                'status'            => 'received',
                'payload_hash'      => sha1( $raw_body ),
                'received_at'       => current_time( 'mysql', true ),
            ], [ '%s', '%s', '%s', '%s', '%s', '%s' ] );

            if ( ! $inserted ) {
                // Concurrent insert lost the unique-key race. Resolve by
                // re-reading the winner and applying the same terminal-state
                // rule above.
                $resolved = $wpdb->get_row( $wpdb->prepare( "
                    SELECT id, status FROM {$table}
                    WHERE provider = 'paypal'
                      AND provider_event_id = %s
                    LIMIT 1
                ", $provider_event_id ), ARRAY_A );

                if ( ! $resolved ) {
                    return [ 'code' => 200, 'message' => 'Accepted-no-effect' ];
                }

                $terminal_statuses = [
                    'completed',
                    'verified',
                    'ignored',
                    'unmatched',
                    'amount_mismatch',
                    'currency_mismatch',
                    'already_completed',
                ];
                if ( in_array( $resolved['status'], $terminal_statuses, true ) ) {
                    return [ 'code' => 200, 'message' => 'Duplicate' ];
                }

                $event_id = (int) $resolved['id'];
            } else {
                $event_id = (int) $wpdb->insert_id;
            }
        }

        // Verify PayPal signature.
        $verify = $this->verify_signature( $raw_body, $provider_event_id );
        if ( ! $verify['success'] ) {
            $wpdb->update( $table, [
                'status'       => 'invalid_signature',
                'error_code'   => (string) ( $verify['code'] ?? '' ),
                'error_message' => 'Signature verification failed',
                'processed_at' => current_time( 'mysql', true ),
            ], [ 'id' => $event_id ], [ '%s', '%s', '%s', '%d' ] );
            return [ 'code' => 200, 'message' => 'Accepted-no-effect' ];
        }

        $wpdb->update( $table, [ 'status' => 'verified' ], [ 'id' => $event_id ], [ '%s' ], [ '%d' ] );

        // Only process capture-completed events for payment reconciliation.
        if ( 'PAYMENT.CAPTURE.COMPLETED' !== $event_type ) {
            $wpdb->update( $table, [
                'status'       => 'ignored',
                'error_message' => 'Unsupported event type',
                'processed_at' => current_time( 'mysql', true ),
            ], [ 'id' => $event_id ], [ '%s', '%s', '%s', '%d' ] );
            return [ 'code' => 200, 'message' => 'Accepted-no-effect' ];
        }

        // Extract provider capture/order identity from the resource.
        $resource = $payload['resource'] ?? [];
        $capture_id = (string) ( $resource['id'] ?? '' );
        $order_id = (string) ( $resource['supplementary_data']['related_ids']['order_id'] ?? '' );
        if ( '' === $order_id && ! empty( $payload['transactions'][0]['related_resources'][0]['sale']['id'] ?? '' ) ) {
            $order_id = (string) $payload['transactions'][0]['related_resources'][0]['sale']['id'];
        }
        if ( '' === $order_id ) {
            $order_id = (string) ( $resource['order_id'] ?? '' );
        }

        $capture_amount = (string) ( $resource['amount']['value'] ?? '' );
        $capture_currency = strtoupper( (string) ( $resource['amount']['currency_code'] ?? '' ) );

        // Correlate to local payment by PayPal order ID stored in transaction_id.
        $payment = null;
        if ( $order_id ) {
            $payment = $wpdb->get_row( $wpdb->prepare( "
                SELECT * FROM {$wpdb->prefix}ovr_payments
                WHERE gateway = 'paypal'
                  AND transaction_id = %s
                ORDER BY id DESC
                LIMIT 1
            ", $order_id ), ARRAY_A );
        }

        if ( ! $payment ) {
            $wpdb->update( $table, [
                'status'       => 'unmatched',
                'error_message' => 'No local payment for order_id=' . $order_id,
                'processed_at' => current_time( 'mysql', true ),
            ], [ 'id' => $event_id ], [ '%s', '%s', '%s', '%d' ] );
            return [ 'code' => 200, 'message' => 'Accepted-no-effect' ];
        }

        $payment_id = (int) $payment['id'];

        // Amount/currency verification before any state change.
        if ( '' !== $capture_amount && (string) $payment['amount'] !== $capture_amount ) {
            $wpdb->update( $table, [
                'status'       => 'amount_mismatch',
                'payment_id'   => $payment_id,
                'error_message' => 'Provider amount ' . $capture_amount . ' != local ' . $payment['amount'],
                'processed_at' => current_time( 'mysql', true ),
            ], [ 'id' => $event_id ], [ '%s', '%d', '%s', '%s', '%d' ] );
            return [ 'code' => 200, 'message' => 'Accepted-no-effect' ];
        }

        if ( '' !== $capture_currency && strtoupper( $payment['currency'] ) !== $capture_currency ) {
            $wpdb->update( $table, [
                'status'       => 'currency_mismatch',
                'payment_id'   => $payment_id,
                'error_message' => 'Provider currency ' . $capture_currency . ' != local ' . $payment['currency'],
                'processed_at' => current_time( 'mysql', true ),
            ], [ 'id' => $event_id ], [ '%s', '%d', '%s', '%s', '%d' ] );
            return [ 'code' => 200, 'message' => 'Accepted-no-effect' ];
        }

        // Atomic completion through the existing payment authority.
        $checkout = new CheckoutHandler();
        $checkout->init();
        $completed = $checkout->complete_payment_atomically( $payment_id, [
            'payment_id'   => $payment_id,
            'plan_slug'    => $this->plan_slug_from_payment( $payment ),
            'amount'       => (float) $payment['amount'],
            'gateway'      => 'paypal',
            'payment_type' => (string) $payment['payment_type'],
            'capture_id'   => $capture_id,
            'order_id'     => $order_id,
        ] );

        $final_status = $completed ? 'completed' : 'already_completed';
        $wpdb->update( $table, [
            'status'       => $final_status,
            'payment_id'   => $payment_id,
            'processed_at' => current_time( 'mysql', true ),
        ], [ 'id' => $event_id ], [ '%s', '%d', '%s', '%d' ] );

        return [ 'code' => 200, 'message' => 'Accepted' ];
    }

    // ------------------------------------------------------------------
    // PayPal signature verification
    // ------------------------------------------------------------------

    /**
     * Verify PayPal webhook signature using the official verification API.
     */
    private function verify_signature( string $raw_body, string $provider_event_id ): array {
        $webhook_id = self::webhook_id();
        if ( empty( $webhook_id ) ) {
            return [ 'success' => false, 'code' => 'NO_WEBHOOK_ID' ];
        }

        $headers = $this->verification_headers();
        if ( empty( $headers ) ) {
            return [ 'success' => false, 'code' => 'MISSING_HEADERS' ];
        }

        $url = self::api_base() . '/v1/notifications/verify/webhook';
        $body = wp_json_encode( array_merge( $headers, [
            'webhook_id'   => $webhook_id,
            'webhook_event'=> json_decode( $raw_body, true ),
        ] ) );

        $resp = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $body,
            'timeout' => 20,
        ] );

        if ( is_wp_error( $resp ) ) {
            return [ 'success' => false, 'code' => 'TRANSPORT_ERROR' ];
        }

        $code = (int) wp_remote_retrieve_response_code( $resp );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );

        return [ 'success' => ( 200 === $code && 'SUCCESS' === ( $data['verification_status'] ?? '' ) ) ];
    }

    /**
     * Collect PayPal webhook verification headers.
     */
    private function verification_headers(): array {
        $headers = [];
        $map = [
            'PAYPAL-AUTH-ALGO',
            'PAYPAL-CERT-URL',
            'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-TRANSMISSION-SIG',
            'PAYPAL-TRANSMISSION-TIME',
        ];
        foreach ( $map as $h ) {
            $v = isset( $_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', $h ) ) ] )
                ? sanitize_text_field( wp_unslash( $_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', $h ) ) ] ) )
                : '';
            if ( '' !== $v ) {
                $headers[ $h ] = $v;
            }
        }
        return $headers;
    }

    /**
     * Send minimal webhook response.
     */
    private function webhook_response( int $code, string $message ): void {
        if ( ! headers_sent() ) {
            status_header( $code );
        }
        echo $message;
        exit;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Extract plan_slug from payment meta_data for audit context.
     */
    private function plan_slug_from_payment( array $payment ): string {
        $meta = json_decode( (string) ( $payment['meta_data'] ?? '' ), true );
        return is_array( $meta ) ? (string) ( $meta['plan_slug'] ?? $meta['upgrade'] ?? '' ) : '';
    }

    // ------------------------------------------------------------------
    // Reconciliation
    // ------------------------------------------------------------------

    /**
     * Register bounded reconciliation cron.
     */
    public function register_reconciliation_cron(): void {
        add_filter( 'cron_schedules', static function ( $schedules ) {
            if ( ! isset( $schedules[ self::RECONCILIATION_SCHEDULE ] ) ) {
                $schedules[ self::RECONCILIATION_SCHEDULE ] = [
                    'interval' => 15 * MINUTE_IN_SECONDS,
                    'display'  => __( 'Every 15 Minutes (OVR)', 'ovr-core' ),
                ];
            }
            return $schedules;
        } );

        add_action( self::RECONCILIATION_HOOK, [ $this, 'run_reconciliation' ] );

        if ( ! wp_next_scheduled( self::RECONCILIATION_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, self::RECONCILIATION_SCHEDULE, self::RECONCILIATION_HOOK );
        }
    }

    /**
     * Run reconciliation for stuck pending PayPal payments.
     */
    public function run_reconciliation(): void {
        $lock_name = 'ovr_paypal_reconciliation_lock';
        if ( ! $this->acquire_lock( $lock_name ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - self::RECONCILIATION_GRACE_MINUTES * MINUTE_IN_SECONDS );

        $pending = $wpdb->get_results( $wpdb->prepare( "
            SELECT id, transaction_id, amount, currency, payment_type, meta_data
            FROM {$table}
            WHERE gateway = 'paypal'
              AND status = 'pending'
              AND transaction_id != ''
              AND created_at <= %s
            ORDER BY id ASC
            LIMIT %d
        ", $cutoff, self::BATCH_SIZE ), ARRAY_A );

        foreach ( $pending as $payment ) {
            $this->reconcile_payment( (array) $payment );
        }

        $this->release_lock( $lock_name );
    }

    /**
     * Reconcile a single pending PayPal payment against the provider API.
     */
    private function reconcile_payment( array $payment ): void {
        $order_id = (string) $payment['transaction_id'];
        if ( '' === $order_id ) {
            return;
        }

        $order = $this->fetch_paypal_order( $order_id );
        if ( ! $order ) {
            AuditLog::record( 'paypal.reconciliation.api_failure', 'payment', (int) $payment['id'], [
                'order_id' => $order_id,
                'message'  => 'PayPal order lookup failed',
            ], null );
            return;
        }

        $status = strtoupper( (string) ( $order['status'] ?? '' ) );

        if ( 'COMPLETED' === $status ) {
            $capture = $this->extract_capture( $order );
            $capture_amount = (string) ( $capture['amount']['value'] ?? '' );
            $capture_currency = strtoupper( (string) ( $capture['amount']['currency_code'] ?? '' ) );

            if ( (string) $payment['amount'] !== $capture_amount ) {
                AuditLog::record( 'paypal.reconciliation.amount_mismatch', 'payment', (int) $payment['id'], [
                    'order_id'         => $order_id,
                    'local_amount'     => $payment['amount'],
                    'provider_amount'  => $capture_amount,
                ], null );
                return;
            }

            if ( strtoupper( $payment['currency'] ) !== $capture_currency ) {
                AuditLog::record( 'paypal.reconciliation.currency_mismatch', 'payment', (int) $payment['id'], [
                    'order_id'          => $order_id,
                    'local_currency'    => $payment['currency'],
                    'provider_currency' => $capture_currency,
                ], null );
                return;
            }

            $checkout = new CheckoutHandler();
            $checkout->init();
            $checkout->complete_payment_atomically( (int) $payment['id'], [
                'payment_id'   => (int) $payment['id'],
                'plan_slug'    => $this->plan_slug_from_payment( $payment ),
                'amount'       => (float) $payment['amount'],
                'gateway'      => 'paypal',
                'payment_type' => (string) ( $payment['payment_type'] ?? 'subscription' ),
                'capture_id'   => (string) ( $capture['id'] ?? '' ),
                'order_id'     => $order_id,
            ] );

            AuditLog::record( 'paypal.reconciliation.completed_payment', 'payment', (int) $payment['id'], [
                'order_id'    => $order_id,
                'capture_id'  => (string) ( $capture['id'] ?? '' ),
            ], null );
            return;
        }

        if ( in_array( $status, [ 'CREATED', 'APPROVED' ], true ) ) {
            AuditLog::record( 'paypal.reconciliation.pending_provider', 'payment', (int) $payment['id'], [
                'order_id' => $order_id,
                'status'   => $status,
            ], null );
            return;
        }

        AuditLog::record( 'paypal.reconciliation.failed', 'payment', (int) $payment['id'], [
            'order_id' => $order_id,
            'status'   => $status,
        ], null );
    }

    /**
     * Fetch a PayPal order by ID.
     */
    private function fetch_paypal_order( string $order_id ): ?array {
        $token = $this->paypal_access_token();
        if ( '' === $token ) {
            return null;
        }

        $url = self::api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id );
        $resp = wp_remote_get( $url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 20,
        ] );

        if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            return null;
        }

        return json_decode( wp_remote_retrieve_body( $resp ), true );
    }

    /**
     * Extract the first successful capture from an order resource.
     */
    private function extract_capture( array $order ): array {
        foreach ( (array) ( $order['purchase_units'][0]['payments']['captures'] ?? [] ) as $capture ) {
            if ( 'COMPLETED' === strtoupper( (string) ( $capture['status'] ?? '' ) ) ) {
                return $capture;
            }
        }
        return [];
    }

    /**
     * Fetch PayPal OAuth token.
     */
    private function paypal_access_token(): string {
        $s = get_option( 'ovr_settings', [] );
        $e = 'live' === ( $s['paypal_env'] ?? 'sandbox' ) ? 'live' : 'sandbox';
        $client_id = (string) ( $s[ "paypal_{$e}_client_id" ] ?? '' );
        $secret    = (string) ( $s[ "paypal_{$e}_secret" ] ?? '' );

        if ( '' === $client_id || '' === $secret ) {
            return '';
        }

        $resp = wp_remote_post( self::api_base() . '/v1/oauth2/token', [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
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

    /**
     * Simple named-lock helpers using MySQL GET_LOCK/RELEASE_LOCK.
     */
    private function acquire_lock( string $name ): bool {
        global $wpdb;
        $result = $wpdb->query( $wpdb->prepare( "SELECT GET_LOCK(%s, 0)", $name ) );
        return 1 === (int) $result;
    }

    private function release_lock( string $name ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $name ) );
    }
}
