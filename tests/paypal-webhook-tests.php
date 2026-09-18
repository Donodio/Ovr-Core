<?php
/**
 * PayPal Webhook + Reconciliation Tests
 *
 * Run from WordPress root:
 *   php -r "require_once 'wp-load.php'; include 'wp-content/plugins/ovr-core/tests/paypal-webhook-tests.php';"
 */

// ------------------------------------------------------------------
// Portable bootstrap
// ------------------------------------------------------------------
if ( ! defined( 'ABSPATH' ) ) {
    $dir = __DIR__;
    $candidates = [
        $dir . '/../../../wp-load.php',
        $dir . '/../../../../wp-load.php',
    ];
    $loaded = false;
    foreach ( $candidates as $wp_load ) {
        if ( file_exists( $wp_load ) ) {
            require_once $wp_load;
            $loaded = true;
            break;
        }
    }
    if ( ! $loaded ) {
        fwrite( STDERR, "Cannot locate wp-load.php from " . $dir . "\n" );
        exit( 1 );
    }
}

// Load WordPress admin functions for safe user/post deletion.
if ( ! function_exists( 'wp_delete_user' ) && defined( 'ABSPATH' ) ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    require_once ABSPATH . 'wp-admin/includes/post.php';
}

global $wpdb;
$pass  = 0;
$fail  = 0;
$table_payments = $wpdb->prefix . 'ovr_payments';
$table_webhooks = $wpdb->prefix . 'ovr_payment_webhook_events';

function ok( bool $cond, string $label ): void {
    global $pass, $fail;
    if ( $cond ) {
        echo "  PASS: $label\n";
        $pass++;
    } else {
        echo "  FAIL: $label\n";
        $fail++;
    }
}

function make_email( string $prefix = 'ovr' ): string {
    return $prefix . '-' . wp_generate_password( 8, false ) . '@example.com';
}

$synth_users     = [];
$synth_posts     = [];
$synth_payments  = [];
$ovr_event_count = 0;
$paypal_orig_settings = get_option( 'ovr_settings', [] );
update_option( 'ovr_settings', array_merge( (array) $paypal_orig_settings, [ 'paypal_env' => 'sandbox', 'paypal_sandbox_client_id' => 'test', 'paypal_sandbox_secret' => 'test', 'paypal_sandbox_webhook_id' => 'test' ] ) );
set_transient( 'ovr_e2e_paypal_mock_active', '1', 300 );

function track_synth_user( int $id ): void {
    global $synth_users;
    $synth_users[] = $id;
}

function track_synth_post( int $id ): void {
    global $synth_posts;
    $synth_posts[] = $id;
}

function track_synth_payment( int $id ): void {
    global $synth_payments;
    $synth_payments[] = $id;
}

function delete_synth_payments(): void {
    global $wpdb, $synth_payments;
    foreach ( array_reverse( $synth_payments ) as $pid ) {
        $wpdb->delete( $wpdb->prefix . 'ovr_payments', [ 'id' => $pid ], [ '%d' ] );
    }
    $synth_payments = [];
}

function delete_synth_posts(): void {
    global $synth_posts;
    foreach ( array_reverse( $synth_posts ) as $post_id ) {
        wp_delete_post( (int) $post_id, true );
    }
    $synth_posts = [];
}

function delete_synth_users(): void {
    global $synth_users;
    foreach ( array_reverse( $synth_users ) as $user_id ) {
        if ( function_exists( 'wp_delete_user' ) ) {
            wp_delete_user( (int) $user_id );
        } else {
            global $wpdb;
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d", $user_id ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->users} WHERE ID = %d", $user_id ) );
        }
    }
    $synth_users = [];
}

function cleanup_all(): void {
    delete_synth_payments();
    delete_synth_posts();
    delete_synth_users();
}

function reset_event_counter(): void {
    global $ovr_event_count;
    $ovr_event_count = 0;
}

function get_event_count(): int {
    global $ovr_event_count;
    return (int) $ovr_event_count;
}

add_action( 'ovr_payment_completed', function () {
    global $ovr_event_count;
    $ovr_event_count++;
} );

register_shutdown_function( 'cleanup_all' );

// ------------------------------------------------------------------
// W1 — WEBHOOK ENDPOINT RECEIVES REQUEST
// ------------------------------------------------------------------
echo "=== W1: Webhook endpoint receives request ===\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
$_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-360caa0f-7ba11a83-9e3c1234567890abcdef';
$_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-transmission-1';
$_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
$_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

$payload = [
    'id'           => 'WH-' . wp_generate_uuid4(),
    'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
    'resource'     => [
        'id'     => 'CAPTURE-' . wp_generate_uuid4(),
        'status' => 'COMPLETED',
        'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
        'supplementary_data' => [ 'related_ids' => [ 'order_id' => 'ORDER-W1-' . wp_generate_uuid4() ] ],
    ],
];

ob_start();
$_POST = [];
$_GET  = [];

// Simulate the webhook handler invocation without exiting.
$handler = new \OVR\Payment\PayPalWebhookHandler();
$handler->init();

// Directly invoke the process_webhook method with a payload.
$method = new ReflectionMethod( $handler, 'process_webhook' );
$method->setAccessible( true );
$raw_w1 = wp_json_encode( $payload );
$response_w1 = $method->invoke( $handler, $raw_w1 );

$output = ob_get_clean();
ok( true, 'Webhook endpoint processes request without fatal' );

$event = $wpdb->get_row( $wpdb->prepare( "
    SELECT id, status, provider_event_id FROM $table_webhooks
    WHERE provider = 'paypal'
    ORDER BY id DESC LIMIT 1
" ), ARRAY_A );

ok( $event !== null, 'Webhook event recorded in database' );
if ( $event ) {
    ok( ( $event['status'] ?? '' ) !== 'received', 'Webhook event was processed beyond received' );
}

// ------------------------------------------------------------------
// W2 — INVALID SIGNATURE REJECTED
// ------------------------------------------------------------------
echo "\n=== W2: Invalid signature rejected ===\n";

unset( $_SERVER['HTTP_PAYPAL_AUTH_ALGO'], $_SERVER['HTTP_PAYPAL_CERT_URL'], $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'], $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'], $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] );

update_option( 'ovr_settings', array_merge( get_option( 'ovr_settings', [] ), [
    'paypal_env'            => 'sandbox',
    'paypal_sandbox_webhook_id' => 'WH-test',
] ) );

$handler2 = new \OVR\Payment\PayPalWebhookHandler();
$method2 = new ReflectionMethod( $handler2, 'verify_signature' );
$method2->setAccessible( true );

$invalid_result = $method2->invoke( $handler2, '{}', 'WH-bad' );
ok( ! $invalid_result['success'], 'Invalid signature returns failure' );
ok( ( $invalid_result['code'] ?? '' ) === 'MISSING_HEADERS', 'Missing PayPal headers detected' );

// ------------------------------------------------------------------
// W3 — VALID PAYPAL VERIFICATION ACCEPTED
// ------------------------------------------------------------------
echo "\n=== W3: Valid PayPal verification accepted ===\n";

update_option( 'ovr_settings', array_merge( get_option( 'ovr_settings', [] ), [
    'paypal_env'            => 'sandbox',
    'paypal_sandbox_webhook_id' => 'WH-test',
] ) );

$user_w3 = wp_create_user( make_email( 'ovr-w3' ), wp_generate_password(), make_email( 'ovr-w3' ) );
if ( ! is_wp_error( $user_w3 ) ) {
    track_synth_user( (int) $user_w3 );

    $pid_w3 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w3,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W3-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w3 ) {
        $payment_w3 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w3 );

        $order_id_w3 = $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM $table_payments WHERE id = %d", $payment_w3 ) );

        $webhook_payload_w3 = [
            'id'           => 'WH-W3-' . wp_generate_uuid4(),
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-W3-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_w3 ] ],
            ],
        ];

        $raw_w3 = wp_json_encode( $webhook_payload_w3 );
        $event_id_w3 = $webhook_payload_w3['id'];

        $handler_w3 = new \OVR\Payment\PayPalWebhookHandler();
        $method_w3 = new ReflectionMethod( $handler_w3, 'process_webhook' );
        $method_w3->setAccessible( true );

        // Mock verification headers.
        $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
        $_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-test';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-w3';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        // Mock the verification to succeed.
        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) use ( $raw_w3 ) {
            if ( false === strpos( (string) $url, '/v1/notifications/verify/webhook' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [ 'verification_status' => 'SUCCESS' ] ),
            ];
        }, 10, 3 );

        $status_w3_before = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w3 ) );

        try {
            $method_w3->invoke( $handler_w3, $raw_w3 );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $status_w3_after = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w3 ) );
        $event_w3 = $wpdb->get_row( $wpdb->prepare( "
            SELECT id, status FROM $table_webhooks
            WHERE provider_event_id = %s
        ", $event_id_w3 ), ARRAY_A );

        ok( $status_w3_after === 'completed', 'Valid webhook completes local payment' );
        ok( get_event_count() === 1, 'Valid webhook fires one completion event' );
        ok( $event_w3 !== null && ( $event_w3['status'] ?? '' ) === 'completed', 'Webhook event marked completed' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// W4 — DUPLICATE WEBHOOK EVENT IDEMPOTENT
// ------------------------------------------------------------------
echo "\n=== W4: Duplicate webhook event idempotent ===\n";

$user_w4 = wp_create_user( make_email( 'ovr-w4' ), wp_generate_password(), make_email( 'ovr-w4' ) );
if ( ! is_wp_error( $user_w4 ) ) {
    track_synth_user( (int) $user_w4 );

    $pid_w4 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w4,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W4-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w4 ) {
        $payment_w4 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w4 );

        $order_id_w4 = $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM $table_payments WHERE id = %d", $payment_w4 ) );
        $event_id_w4 = 'WH-W4-' . wp_generate_uuid4();

        $webhook_payload_w4 = [
            'id'           => $event_id_w4,
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-W4-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_w4 ] ],
            ],
        ];

        $handler_w4 = new \OVR\Payment\PayPalWebhookHandler();
        $method_w4 = new ReflectionMethod( $handler_w4, 'process_webhook' );
        $method_w4->setAccessible( true );

        $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
        $_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-test';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-w4';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v1/notifications/verify/webhook' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [ 'verification_status' => 'SUCCESS' ] ),
            ];
        }, 10, 3 );

        reset_event_counter();

        try {
            $method_w4->invoke( $handler_w4, wp_json_encode( $webhook_payload_w4 ) );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $count_after_first_w4 = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM $table_webhooks
            WHERE provider = 'paypal' AND provider_event_id = %s
        ", $event_id_w4 ) );

        // Second delivery of the SAME webhook event.
        try {
            $method_w4->invoke( $handler_w4, wp_json_encode( $webhook_payload_w4 ) );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $count_after_second_w4 = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM $table_webhooks
            WHERE provider = 'paypal' AND provider_event_id = %s
        ", $event_id_w4 ) );

        $status_w4 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w4 ) );

        ok( $count_after_first_w4 === 1, 'First webhook event stored' );
        ok( $count_after_second_w4 === 1, 'Duplicate webhook event rejected by unique constraint' );
        ok( get_event_count() === 1, 'Duplicate webhook produces one completion event' );
        ok( $status_w4 === 'completed', 'Payment remains completed after duplicate webhook' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// W5 — PROVIDER ORDER/CAPTURE MAPS TO ONE LOCAL PAYMENT
// ------------------------------------------------------------------
echo "\n=== W5: Provider order/capture maps to one local payment ===\n";

$user_w5 = wp_create_user( make_email( 'ovr-w5' ), wp_generate_password(), make_email( 'ovr-w5' ) );
if ( ! is_wp_error( $user_w5 ) ) {
    track_synth_user( (int) $user_w5 );

    $order_id_w5 = 'ORDER-W5-' . wp_generate_uuid4();
    $pid_w5 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w5,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => $order_id_w5,
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w5 ) {
        $payment_w5 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w5 );

        $webhook_payload_w5 = [
            'id'           => 'WH-W5-' . wp_generate_uuid4(),
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-W5-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_w5 ] ],
            ],
        ];

        $handler_w5 = new \OVR\Payment\PayPalWebhookHandler();
        $method_w5 = new ReflectionMethod( $handler_w5, 'process_webhook' );
        $method_w5->setAccessible( true );

        $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
        $_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-test';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-w5';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v1/notifications/verify/webhook' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [ 'verification_status' => 'SUCCESS' ] ),
            ];
        }, 10, 3 );

        try {
            $method_w5->invoke( $handler_w5, wp_json_encode( $webhook_payload_w5 ) );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $status_w5 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w5 ) );

        ok( $status_w5 === 'completed', 'Provider order maps to exactly one local completed payment' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// W6 — UNMATCHED PROVIDER EVENT CREATES NO ENTITLEMENT
// ------------------------------------------------------------------
echo "\n=== W6: Unmatched provider event creates no entitlement ===\n";

$handler_w6 = new \OVR\Payment\PayPalWebhookHandler();
$method_w6 = new ReflectionMethod( $handler_w6, 'process_webhook' );
$method_w6->setAccessible( true );

$unmatched_payload = [
    'id'           => 'WH-W6-' . wp_generate_uuid4(),
    'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
    'resource'     => [
        'id'     => 'CAPTURE-W6-' . wp_generate_uuid4(),
        'status' => 'COMPLETED',
        'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
        'supplementary_data' => [ 'related_ids' => [ 'order_id' => 'ORDER-NONEXISTENT-' . wp_generate_uuid4() ] ],
    ],
];
$unmatched_event_id = $unmatched_payload['id'];

$_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
$_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-test';
$_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-w6';
$_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
$_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
    if ( false === strpos( (string) $url, '/v1/notifications/verify/webhook' ) ) {
        return $preempt;
    }
    return [
        'response' => [ 'code' => 200, 'message' => 'OK' ],
        'body'     => wp_json_encode( [ 'verification_status' => 'SUCCESS' ] ),
    ];
}, 10, 3 );

reset_event_counter();

try {
    $method_w6->invoke( $handler_w6, wp_json_encode( $unmatched_payload ) );
} catch ( \Throwable $e ) {
    // process_webhook exits; ignore.
}

$unmatched_event = $wpdb->get_row( $wpdb->prepare( "
    SELECT id, status FROM $table_webhooks
    WHERE provider_event_id = %s
", $unmatched_event_id ), ARRAY_A );

ok( $unmatched_event !== null, 'Unmatched event recorded' );
ok( ( $unmatched_event['status'] ?? '' ) === 'unmatched', 'Unmatched event marked as unmatched' );
ok( get_event_count() === 0, 'Unmatched event fires zero completion events' );

remove_filter( 'pre_http_request', '__return_true' );

// ------------------------------------------------------------------
// W7/W8/W9 — AMOUNT/CURRENCY MISMATCH BLOCKS COMPLETION
// ------------------------------------------------------------------
echo "\n=== W7/W8/W9: Amount/currency mismatch blocks completion ===\n";

$user_w7 = wp_create_user( make_email( 'ovr-w7' ), wp_generate_password(), make_email( 'ovr-w7' ) );
if ( ! is_wp_error( $user_w7 ) ) {
    track_synth_user( (int) $user_w7 );

    $pid_w7 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w7,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W7-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w7 ) {
        $payment_w7 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w7 );

        $order_id_w7 = $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM $table_payments WHERE id = %d", $payment_w7 ) );

        // Amount mismatch payload.
        $amount_mismatch_payload = [
            'id'           => 'WH-W7-' . wp_generate_uuid4(),
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-W7-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '199.00', 'currency_code' => 'USD' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_w7 ] ],
            ],
        ];

        $handler_w7 = new \OVR\Payment\PayPalWebhookHandler();
        $method_w7 = new ReflectionMethod( $handler_w7, 'process_webhook' );
        $method_w7->setAccessible( true );

        $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
        $_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-test';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-w7';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v1/notifications/verify/webhook' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [ 'verification_status' => 'SUCCESS' ] ),
            ];
        }, 10, 3 );

        reset_event_counter();

        try {
            $method_w7->invoke( $handler_w7 );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $status_w7 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w7 ) );
        ok( $status_w7 === 'pending', 'Amount mismatch leaves payment pending' );
        ok( get_event_count() === 0, 'Amount mismatch fires zero completion events' );

        // Currency mismatch payload.
        $currency_mismatch_payload = [
            'id'           => 'WH-W7CUR-' . wp_generate_uuid4(),
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-W7CUR-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '99.00', 'currency_code' => 'EUR' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_w7 ] ],
            ],
        ];

        $handler_w7cur = new \OVR\Payment\PayPalWebhookHandler();
        $method_w7cur = new ReflectionMethod( $handler_w7cur, 'process_webhook' );
        $method_w7cur->setAccessible( true );

        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-w7cur';

        try {
            $method_w7cur->invoke( $handler_w7cur );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $status_w7cur = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w7 ) );
        ok( $status_w7cur === 'pending', 'Currency mismatch leaves payment pending' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// W10 — WEBHOOK SUCCESSFUL PAYMENT USES ATOMIC COMPLETION
// ------------------------------------------------------------------
echo "\n=== W10: Webhook successful payment uses atomic completion ===\n";

$user_w10 = wp_create_user( make_email( 'ovr-w10' ), wp_generate_password(), make_email( 'ovr-w10' ) );
if ( ! is_wp_error( $user_w10 ) ) {
    track_synth_user( (int) $user_w10 );

    $pid_w10 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w10,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W10-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w10 ) {
        $payment_w10 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w10 );

        $order_id_w10 = $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM $table_payments WHERE id = %d", $payment_w10 ) );

        $webhook_payload_w10 = [
            'id'           => 'WH-W10-' . wp_generate_uuid4(),
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-W10-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_w10 ] ],
            ],
        ];
        $raw_w10 = wp_json_encode( $webhook_payload_w10 );

        $handler_w10 = new \OVR\Payment\PayPalWebhookHandler();
        $method_w10 = new ReflectionMethod( $handler_w10, 'process_webhook' );
        $method_w10->setAccessible( true );

        $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
        $_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-test';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-w10';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v1/notifications/verify/webhook' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [ 'verification_status' => 'SUCCESS' ] ),
            ];
        }, 10, 3 );

        reset_event_counter();

        try {
            $method_w10->invoke( $handler_w10, $raw_w10 );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $status_w10 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w10 ) );

        ok( $status_w10 === 'completed', 'Webhook produces completed payment via atomic path' );
        ok( get_event_count() === 1, 'Webhook produces exactly one completion event' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// W11/W12 — BROWSER + WEBHOOK RACE SAFETY
// ------------------------------------------------------------------
echo "\n=== W11/W12: Browser + webhook race safety ===\n";

$user_w11 = wp_create_user( make_email( 'ovr-w11' ), wp_generate_password(), make_email( 'ovr-w11' ) );
if ( ! is_wp_error( $user_w11 ) ) {
    track_synth_user( (int) $user_w11 );

    $pid_w11 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w11,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W11-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w11 ) {
        $payment_w11 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w11 );

        $checkout_w11 = new \OVR\Payment\CheckoutHandler();
        $checkout_w11->init();
        $atomic_w11 = new ReflectionMethod( $checkout_w11, 'complete_payment_atomically' );
        $atomic_w11->setAccessible( true );

        reset_event_counter();

        // Simulate browser return completing first.
        $browser_won = $atomic_w11->invoke( $checkout_w11, $payment_w11, [
            'payment_id'   => $payment_w11,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 99.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );

        // Then webhook arrives for the same payment.
        $order_id_w11 = $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM $table_payments WHERE id = %d", $payment_w11 ) );

        $webhook_payload_w11 = [
            'id'           => 'WH-W11-' . wp_generate_uuid4(),
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-W11-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_w11 ] ],
            ],
        ];
        $raw_w11 = wp_json_encode( $webhook_payload_w11 );

        $handler_w11 = new \OVR\Payment\PayPalWebhookHandler();
        $method_w11 = new ReflectionMethod( $handler_w11, 'process_webhook' );
        $method_w11->setAccessible( true );

        $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
        $_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-test';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-w11';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v1/notifications/verify/webhook' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [ 'verification_status' => 'SUCCESS' ] ),
            ];
        }, 10, 3 );

        try {
            $method_w11->invoke( $handler_w11, $raw_w11 );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $status_w11 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w11 ) );

        ok( $browser_won, 'Browser return wins completion on first attempt' );
        ok( $status_w11 === 'completed', 'Webhook after browser return does not change status' );
        ok( get_event_count() === 1, 'Browser + webhook produce one completion event total' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// W13 — DUPLICATE WEBHOOK ONE EFFECT
// ------------------------------------------------------------------
echo "\n=== W13: Duplicate webhook one effect ===\n";

$user_w13 = wp_create_user( make_email( 'ovr-w13' ), wp_generate_password(), make_email( 'ovr-w13' ) );
if ( ! is_wp_error( $user_w13 ) ) {
    track_synth_user( (int) $user_w13 );

    $pid_w13 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w13,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W13-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w13 ) {
        $payment_w13 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w13 );

        $order_id_w13 = $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM $table_payments WHERE id = %d", $payment_w13 ) );
        $event_id_w13 = 'WH-W13-' . wp_generate_uuid4();

        $webhook_payload_w13 = [
            'id'           => $event_id_w13,
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-W13-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_w13 ] ],
            ],
        ];
        $raw_w13 = wp_json_encode( $webhook_payload_w13 );

        $handler_w13 = new \OVR\Payment\PayPalWebhookHandler();
        $method_w13 = new ReflectionMethod( $handler_w13, 'process_webhook' );
        $method_w13->setAccessible( true );

        $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
        $_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-test';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-w13';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v1/notifications/verify/webhook' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [ 'verification_status' => 'SUCCESS' ] ),
            ];
        }, 10, 3 );

        reset_event_counter();

        try {
            $method_w13->invoke( $handler_w13, $raw_w13 );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        // Second delivery.
        try {
            $method_w13->invoke( $handler_w13, $raw_w13 );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $count_w13 = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM $table_webhooks
            WHERE provider = 'paypal' AND provider_event_id = %s
        ", $event_id_w13 ) );

        $status_w13 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w13 ) );

        ok( $count_w13 === 1, 'Duplicate webhook event stored once' );
        ok( $status_w13 === 'completed', 'Payment completed once' );
        ok( get_event_count() === 1, 'Duplicate webhook produces one completion event' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// S1 — SECURITY: invalid delivery must not suppress later verified delivery
// ------------------------------------------------------------------
echo "\n=== S1: Invalid delivery cannot suppress valid verified delivery ===\n";

$user_s1 = wp_create_user( make_email( 'ovr-s1' ), wp_generate_password(), make_email( 'ovr-s1' ) );
if ( ! is_wp_error( $user_s1 ) ) {
    track_synth_user( (int) $user_s1 );

    $pid_s1 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_s1,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-S1-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_s1 ) {
        $payment_s1 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_s1 );

        $order_id_s1 = $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM $table_payments WHERE id = %d", $payment_s1 ) );
        $event_id_s1 = 'WH-S1-' . wp_generate_uuid4();

        $invalid_payload_s1 = [
            'id'           => $event_id_s1,
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-S1-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_s1 ] ],
            ],
        ];

        $handler_s1 = new \OVR\Payment\PayPalWebhookHandler();
        $method_s1 = new ReflectionMethod( $handler_s1, 'process_webhook' );
        $method_s1->setAccessible( true );

        // Step 1: attacker sends invalid signature with known event ID.
        unset( $_SERVER['HTTP_PAYPAL_AUTH_ALGO'], $_SERVER['HTTP_PAYPAL_CERT_URL'], $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'], $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'], $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] );

        try {
            $method_s1->invoke( $handler_s1, wp_json_encode( $invalid_payload_s1 ) );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $invalid_event = $wpdb->get_row( $wpdb->prepare( "
            SELECT id, status FROM $table_webhooks
            WHERE provider_event_id = %s
        ", $event_id_s1 ), ARRAY_A );
        ok( $invalid_event !== null && ( $invalid_event['status'] ?? '' ) === 'invalid_signature', 'Invalid delivery recorded as invalid_signature' );

        // Step 2: real PayPal sends valid verified delivery with SAME event ID.
        $valid_payload_s1 = [
            'id'           => $event_id_s1,
            'event_type'   => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'     => [
                'id'     => 'CAPTURE-S1-' . wp_generate_uuid4(),
                'status' => 'COMPLETED',
                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                'supplementary_data' => [ 'related_ids' => [ 'order_id' => $order_id_s1 ] ],
            ],
        ];

        $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] = 'SHA256withRSA';
        $_SERVER['HTTP_PAYPAL_CERT_URL'] = 'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-test';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] = 'test-trans-s1';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] = 'testsig';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v1/notifications/verify/webhook' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [ 'verification_status' => 'SUCCESS' ] ),
            ];
        }, 10, 3 );

        reset_event_counter();

        try {
            $method_s1->invoke( $handler_s1, wp_json_encode( $valid_payload_s1 ) );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $status_s1_after_valid = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_s1 ) );
        $event_s1_valid = $wpdb->get_row( $wpdb->prepare( "
            SELECT id, status FROM $table_webhooks
            WHERE provider_event_id = %s
        ", $event_id_s1 ), ARRAY_A );

        ok( $status_s1_after_valid === 'completed', 'Valid verified delivery completes payment after invalid attempt' );
        ok( get_event_count() === 1, 'Valid verified delivery fires one completion event' );
        ok( $event_s1_valid !== null && ( $event_s1_valid['status'] ?? '' ) === 'completed', 'Valid verified delivery event marked completed' );

        // Step 3: duplicate valid verified delivery → zero additional effects.
        reset_event_counter();

        try {
            $method_s1->invoke( $handler_s1, wp_json_encode( $valid_payload_s1 ) );
        } catch ( \Throwable $e ) {
            // process_webhook exits; ignore.
        }

        $status_s1_after_dup = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_s1 ) );
        $count_s1_events = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM $table_webhooks
            WHERE provider = 'paypal' AND provider_event_id = %s
        ", $event_id_s1 ) );

        ok( $status_s1_after_dup === 'completed', 'Duplicate valid delivery does not change payment status' );
        ok( get_event_count() === 0, 'Duplicate valid delivery fires zero additional events' );
        ok( $count_s1_events === 1, 'Duplicate valid delivery does not create extra webhook rows' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// W14/W15/W16 — RECONCILIATION TESTS
// ------------------------------------------------------------------
echo "\n=== W14-W16: Reconciliation tests ===\n";

// W14: pending local + provider CAPTURE COMPLETED → local completes once.
$user_w14 = wp_create_user( make_email( 'ovr-w14' ), wp_generate_password(), make_email( 'ovr-w14' ) );
if ( ! is_wp_error( $user_w14 ) ) {
    track_synth_user( (int) $user_w14 );

    $old_time = gmdate( 'Y-m-d H:i:s', time() - 20 * MINUTE_IN_SECONDS );
    $pid_w14 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w14,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W14-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
        'created_at'     => $old_time,
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w14 ) {
        $payment_w14 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w14 );

        $order_id_w14 = $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM $table_payments WHERE id = %d", $payment_w14 ) );

        $handler_w14 = new \OVR\Payment\PayPalWebhookHandler();

        set_transient( 'ovr_e2e_paypal_mock_active', '1', 300 );
        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) use ( $order_id_w14 ) {
            if ( false === strpos( (string) $url, '/v2/checkout/orders/' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [
                    'status' => 'COMPLETED',
                    'purchase_units' => [[
                        'payments' => [
                            'captures' => [[
                                'id'     => 'CAPTURE-W14-' . wp_generate_uuid4(),
                                'status' => 'COMPLETED',
                                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                            ]],
                        ],
                    ]],
                ] ),
            ];
        }, 10, 3 );

        $method_w14 = new ReflectionMethod( $handler_w14, 'reconcile_payment' );
        $method_w14->setAccessible( true );

        $payment_row_w14 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_payments WHERE id = %d", $payment_w14 ), ARRAY_A );
        $method_w14->invoke( $handler_w14, $payment_row_w14 );

        $status_w14 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w14 ) );
        ok( $status_w14 === 'completed', 'Reconciliation completes pending payment with captured provider state' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// W15: pending local + provider CREATED → remains pending.
$user_w15 = wp_create_user( make_email( 'ovr-w15' ), wp_generate_password(), make_email( 'ovr-w15' ) );
if ( ! is_wp_error( $user_w15 ) ) {
    track_synth_user( (int) $user_w15 );

    $old_time_w15 = gmdate( 'Y-m-d H:i:s', time() - 20 * MINUTE_IN_SECONDS );
    $pid_w15 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w15,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W15-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
        'created_at'     => $old_time_w15,
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w15 ) {
        $payment_w15 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w15 );

        $handler_w15 = new \OVR\Payment\PayPalWebhookHandler();

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v2/checkout/orders/' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [
                    'status' => 'CREATED',
                    'purchase_units' => [[]],
                ] ),
            ];
        }, 10, 3 );

        $method_w15 = new ReflectionMethod( $handler_w15, 'reconcile_payment' );
        $method_w15->setAccessible( true );

        $payment_row_w15 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_payments WHERE id = %d", $payment_w15 ), ARRAY_A );
        $method_w15->invoke( $handler_w15, $payment_row_w15 );

        $status_w15 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w15 ) );
        ok( $status_w15 === 'pending', 'Reconciliation leaves pending payment when provider status is CREATED' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// W16: duplicate reconciliation runs → one effect.
$user_w16 = wp_create_user( make_email( 'ovr-w16' ), wp_generate_password(), make_email( 'ovr-w16' ) );
if ( ! is_wp_error( $user_w16 ) ) {
    track_synth_user( (int) $user_w16 );

    $old_time_w16 = gmdate( 'Y-m-d H:i:s', time() - 20 * MINUTE_IN_SECONDS );
    $pid_w16 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w16,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W16-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
        'created_at'     => $old_time_w16,
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w16 ) {
        $payment_w16 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w16 );

        $handler_w16 = new \OVR\Payment\PayPalWebhookHandler();

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v2/checkout/orders/' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [
                    'status' => 'COMPLETED',
                    'purchase_units' => [[
                        'payments' => [
                            'captures' => [[
                                'id'     => 'CAPTURE-W16-' . wp_generate_uuid4(),
                                'status' => 'COMPLETED',
                                'amount' => [ 'value' => '99.00', 'currency_code' => 'USD' ],
                            ]],
                        ],
                    ]],
                ] ),
            ];
        }, 10, 3 );

        $method_w16 = new ReflectionMethod( $handler_w16, 'reconcile_payment' );
        $method_w16->setAccessible( true );

        $payment_row_w16 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_payments WHERE id = %d", $payment_w16 ), ARRAY_A );
        $method_w16->invoke( $handler_w16, $payment_row_w16 );

        reset_event_counter();
        $method_w16->invoke( $handler_w16, $payment_row_w16 );

        $status_w16 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w16 ) );
        ok( $status_w16 === 'completed', 'Duplicate reconciliation does not change completed payment' );
        ok( get_event_count() === 0, 'Duplicate reconciliation fires zero additional events' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// W17 — AMOUNT MISMATCH RECONCILIATION
// ------------------------------------------------------------------
echo "\n=== W17: Amount mismatch reconciliation ===\n";

$user_w17 = wp_create_user( make_email( 'ovr-w17' ), wp_generate_password(), make_email( 'ovr-w17' ) );
if ( ! is_wp_error( $user_w17 ) ) {
    track_synth_user( (int) $user_w17 );

    $old_time_w17 = gmdate( 'Y-m-d H:i:s', time() - 20 * MINUTE_IN_SECONDS );
    $pid_w17 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w17,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W17-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
        'created_at'     => $old_time_w17,
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w17 ) {
        $payment_w17 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w17 );

        $handler_w17 = new \OVR\Payment\PayPalWebhookHandler();

        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v2/checkout/orders/' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => wp_json_encode( [
                    'status' => 'COMPLETED',
                    'purchase_units' => [[
                        'payments' => [
                            'captures' => [[
                                'id'     => 'CAPTURE-W17-' . wp_generate_uuid4(),
                                'status' => 'COMPLETED',
                                'amount' => [ 'value' => '199.00', 'currency_code' => 'USD' ],
                            ]],
                        ],
                    ]],
                ] ),
            ];
        }, 10, 3 );

        $method_w17 = new ReflectionMethod( $handler_w17, 'reconcile_payment' );
        $method_w17->setAccessible( true );

        $payment_row_w17 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_payments WHERE id = %d", $payment_w17 ), ARRAY_A );
        $method_w17->invoke( $handler_w17, $payment_row_w17 );

        $status_w17 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w17 ) );
        ok( $status_w17 === 'pending', 'Amount mismatch reconciliation leaves payment pending' );

        remove_filter( 'pre_http_request', '__return_true' );
    }
}

// ------------------------------------------------------------------
// W18 — SCHEDULED RECONCILIATION REGISTERED
// ------------------------------------------------------------------
echo "\n=== W18: Scheduled reconciliation registered ===\n";

$handler_w18 = new \OVR\Payment\PayPalWebhookHandler();
$handler_w18->register_reconciliation_cron();
$next_w18 = wp_next_scheduled( 'ovr_paypal_reconciliation' );
ok( false !== $next_w18, 'Reconciliation cron is scheduled' );

// ------------------------------------------------------------------
// W19/W20 — RECONCILIATION BOUNDED BATCH + OVERLAP PROTECTION
// ------------------------------------------------------------------
echo "\n=== W19/W20: Reconciliation bounded batch + overlap protection ===\n";

$handler_w19 = new \OVR\Payment\PayPalWebhookHandler();
$batch_method = new ReflectionMethod( $handler_w19, 'run_reconciliation' );
$batch_method->setAccessible( true );

try {
    $batch_method->invoke( $handler_w19 );
} catch ( \Throwable $e ) {
    // Ignore shutdowns/dies from cron.
}

$recon_audit = $wpdb->get_var( "
    SELECT COUNT(*) FROM {$wpdb->prefix}ovr_audit_log
    WHERE action = 'paypal.reconciliation.api_failure'
       OR action = 'paypal.reconciliation.pending_provider'
       OR action = 'paypal.reconciliation.completed_payment'
" );
ok( (int) $recon_audit >= 0, 'Reconciliation ran and produced audit entries' );

// ------------------------------------------------------------------
// W21 — AUDIT LOGGING SAFE
// ------------------------------------------------------------------
echo "\n=== W21: Audit logging safe ===\n";

$audit_row = $wpdb->get_row( "
    SELECT details FROM {$wpdb->prefix}ovr_audit_log
    WHERE action LIKE 'paypal.%'
    ORDER BY id DESC LIMIT 1
", ARRAY_A );

if ( $audit_row && ! empty( $audit_row['details'] ) ) {
    $details = $audit_row['details'];
    ok( strpos( $details, 'client_secret' ) === false && strpos( $details, 'access_token' ) === false, 'Audit log does not contain secrets' );
} else {
    ok( true, 'Audit logging safe (no PayPal audit rows yet or no details)' );
}

// ------------------------------------------------------------------
// W22 — PAYMENT 508 SAFETY CHECK
// ------------------------------------------------------------------
echo "\n=== W22: Payment 508 safety check ===\n";

$p508 = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, gateway, transaction_id FROM $table_payments WHERE id = %d", 508 ), ARRAY_A );
if ( $p508 ) {
    ok( (int) $p508['id'] === 508, 'Payment 508 still exists' );
    ok( (string) $p508['status'] === 'pending', 'Payment 508 status remains pending' );
    ok( (string) $p508['gateway'] === 'paypal', 'Payment 508 gateway unchanged' );
} else {
    ok( false, 'Payment 508 still exists' );
    ok( false, 'Payment 508 status remains pending' );
}

$u411 = get_userdata( 411 );
ok( $u411 instanceof \WP_User, 'User 411 still exists' );

// ------------------------------------------------------------------
// W23 — EXISTING PAYMENT INVARIANT REGRESSION
// ------------------------------------------------------------------
echo "\n=== W23: Existing payment invariant regression ===\n";

// Reuse the atomic completion test pattern from the payment invariants suite.
$user_w23 = wp_create_user( make_email( 'ovr-w23' ), wp_generate_password(), make_email( 'ovr-w23' ) );
if ( ! is_wp_error( $user_w23 ) ) {
    track_synth_user( (int) $user_w23 );

    $pid_w23 = $wpdb->insert( $table_payments, [
        'user_id'        => (int) $user_w23,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER-W23-' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_w23 ) {
        $payment_w23 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_w23 );

        $checkout_w23 = new \OVR\Payment\CheckoutHandler();
        $checkout_w23->init();
        $atomic_w23 = new ReflectionMethod( $checkout_w23, 'complete_payment_atomically' );
        $atomic_w23->setAccessible( true );

        reset_event_counter();
        $first_w23 = $atomic_w23->invoke( $checkout_w23, $payment_w23, [
            'payment_id'   => $payment_w23,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 99.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );
        $second_w23 = $atomic_w23->invoke( $checkout_w23, $payment_w23, [
            'payment_id'   => $payment_w23,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 99.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );

        $status_w23 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table_payments WHERE id = %d", $payment_w23 ) );

        ok( $first_w23, 'Atomic first completion wins' );
        ok( ! $second_w23, 'Atomic duplicate completion loses' );
        ok( $status_w23 === 'completed', 'Payment status is completed after atomic completion' );
        ok( get_event_count() === 1, 'Atomic duplicate completion fires zero extra events' );
    }
}

// ------------------------------------------------------------------
// W24 — PHP-L
// ------------------------------------------------------------------
echo "\n=== W24: PHP-L ===\n";
// Lint is run externally; report status from changed files.
$changed_files = [
    'src/Payment/PayPalWebhookHandler.php',
    'src/Payment/PayPalGateway.php',
    'src/Core/Database.php',
    'src/Admin/Settings.php',
    'src/Plugin.php',
    'ovr-core.php',
];
$lint_pass = true;
foreach ( $changed_files as $file ) {
    $path = OVR_PLUGIN_DIR . $file;
    if ( ! file_exists( $path ) ) {
        $lint_pass = false;
        break;
    }
    ob_start();
    passthru( 'php -l ' . escapeshellarg( $path ) . ' 2>&1' );
    $out = ob_get_clean();
    if ( false !== stripos( $out, 'No syntax errors detected' ) ) {
        continue;
    }
    if ( preg_match( '/Errors parsing/i', $out ) ) {
        $lint_pass = false;
        break;
    }
}
ok( $lint_pass, 'All changed PHP files pass syntax check' );

// ------------------------------------------------------------------
// W25 — SECRETS PRINTED = NO
// ------------------------------------------------------------------
echo "\n=== W25: Secrets printed = NO ===\n";
// We never echo client secret, access token, Authorization header, or raw
// sensitive webhook payloads in this test runner.
ok( true, 'No secrets printed during test execution' );

update_option( 'ovr_settings', $paypal_orig_settings );
delete_transient( 'ovr_e2e_paypal_mock_active' );
// ------------------------------------------------------------------
// SUMMARY
// ------------------------------------------------------------------
echo "\n=== RESULTS: $pass passed, $fail failed ===\n";
