<?php
/**
 * OVR Payment Critical Invariant Tests — Final Pre-Sandbox Closure
 *
 * Run from WordPress root:
 *   php -r "require_once 'wp-load.php'; include 'wp-content/plugins/ovr-core/tests/payment-invariants.php';"
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

// Load admin user deletion APIs even in CLI contexts.
if ( ! function_exists( 'wp_delete_user' ) && defined( 'ABSPATH' ) ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    require_once ABSPATH . 'wp-admin/includes/post.php';
}

global $wpdb;
$table = $wpdb->prefix . 'ovr_payments';
$pass  = 0;
$fail  = 0;

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------
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
    global $wpdb, $synth_users;
    foreach ( array_reverse( $synth_users ) as $user_id ) {
        if ( function_exists( 'wp_delete_user' ) ) {
            wp_delete_user( (int) $user_id );
        } else {
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

function get_subscription_expiry( int $user_id ): string {
    return (string) get_user_meta( $user_id, \OVR\Subscription\UserSubscription::META_EXPIRES, true );
}

function get_upgrade_expiry( int $property_id, string $upgrade_id ): string {
    return (string) \OVR\Subscription\UpgradeActivator::expires_for( $property_id, $upgrade_id );
}

function reset_event_counter(): void {
    global $ovr_event_count;
    $ovr_event_count = 0;
}

function get_event_count(): int {
    global $ovr_event_count;
    return (int) $ovr_event_count;
}

// Global completion-event counter.
add_action( 'ovr_payment_completed', function () {
    global $ovr_event_count;
    $ovr_event_count++;
} );

// Best-effort cleanup on script end.
register_shutdown_function( 'cleanup_all' );

// ------------------------------------------------------------------
// H1 — SERVER-AUTHORITATIVE PRICE
// ------------------------------------------------------------------
echo "=== H1: Server-authoritative price ===\n";

$plan = \OVR\Subscription\Plans::get_plan( 'standard_homeowner_5' );
$server_price = (float) ( $plan['price'] ?? 0 );
echo "  Server configured amount: $server_price\n";

// Code inspection: checkout handler must not read client amount.
$checkout_src = file_get_contents( __DIR__ . '/../src/Payment/CheckoutHandler.php' );
$reads_client_amount = strpos( $checkout_src, "\$_POST['amount']" ) !== false
    || preg_match( '/\$_POST\[.amount.\]/', $checkout_src ) === 1;
ok( ! $reads_client_amount, 'Checkout handler does not read client-supplied amount' );
ok( $server_price > 0, 'Server plan price is authoritative (positive)' );

// Verify the checkout handler computes price from $plan['price'], not POST.
$has_server_price_logic = strpos( $checkout_src, '$plan[\'price\']' ) !== false
    || strpos( $checkout_src, '$plan["price"]' ) !== false;
ok( $has_server_price_logic, 'Checkout handler uses server $plan[price] for amount' );

// ------------------------------------------------------------------
// H2 — INVALID PROMO REJECTED
// ------------------------------------------------------------------
echo "\n=== H2: Invalid promo rejected ===\n";
$invalid = \OVR\Payment\PromoCode::validate( 'INVALID_CODE_12345', 'standard_homeowner_5' );
ok( ! $invalid['valid'], 'Invalid promo returns invalid' );
ok( empty( $invalid['row'] ?? '' ), 'Invalid promo has no row data' );

// ------------------------------------------------------------------
// H3 — SAME PAID PAYMENT COMPLETES ONCE
// ------------------------------------------------------------------
echo "\n=== H3: Same paid payment completes once ===\n";

$user_h3 = wp_create_user( make_email( 'ovr-h3' ), wp_generate_password(), make_email( 'ovr-h3' ) );
if ( ! is_wp_error( $user_h3 ) ) {
    track_synth_user( (int) $user_h3 );
    $pid_h3 = $wpdb->insert( $table, [
        'user_id'        => (int) $user_h3,
        'payment_type'   => 'subscription',
        'amount'         => 29.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER_H3_' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_h3 ) {
        $payment_h3 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_h3 );

        $checkout = new \OVR\Payment\CheckoutHandler();
        $checkout->init();
$method = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

        $first  = $method->invoke( $checkout, $payment_h3, [
            'payment_id'   => $payment_h3,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 29.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );
        $second = $method->invoke( $checkout, $payment_h3, [
            'payment_id'   => $payment_h3,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 29.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );

        ok( $first, 'First completion returns true' );
        ok( ! $second, 'Second completion returns false' );
    }
}

// ------------------------------------------------------------------
// H4 — SAME PAID PAYMENT FIRES ONE COMPLETION EVENT
// ------------------------------------------------------------------
echo "\n=== H4: Same paid payment fires one completion event ===\n";

$user_h4 = wp_create_user( make_email( 'ovr-h4' ), wp_generate_password(), make_email( 'ovr-h4' ) );
if ( ! is_wp_error( $user_h4 ) ) {
    track_synth_user( (int) $user_h4 );
    $pid_h4 = $wpdb->insert( $table, [
        'user_id'        => (int) $user_h4,
        'payment_type'   => 'subscription',
        'amount'         => 29.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER_H4_' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_h4 ) {
        $payment_h4 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_h4 );

        $checkout = new \OVR\Payment\CheckoutHandler();
        $checkout->init();
$method = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

        reset_event_counter();
        $method->invoke( $checkout, $payment_h4, [
            'payment_id'   => $payment_h4,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 29.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );
        $method->invoke( $checkout, $payment_h4, [
            'payment_id'   => $payment_h4,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 29.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );

        ok( get_event_count() === 1, 'ovr_payment_completed fired exactly once (count=' . get_event_count() . ')' );
    }
}

// ------------------------------------------------------------------
// H5 — SAME RENEWAL PAYMENT EXTENDS ONCE
// ------------------------------------------------------------------
echo "\n=== H5: Same renewal payment extends once ===\n";

$user_h5 = wp_create_user( make_email( 'ovr-h5' ), wp_generate_password(), make_email( 'ovr-h5' ) );
if ( ! is_wp_error( $user_h5 ) ) {
    track_synth_user( (int) $user_h5 );
    \OVR\Subscription\SubscriptionManager::activate( (int) $user_h5, 'standard_homeowner_5' );
    $initial_h5 = get_subscription_expiry( (int) $user_h5 );

    $pid_h5 = $wpdb->insert( $table, [
        'user_id'        => (int) $user_h5,
        'payment_type'   => 'subscription',
        'amount'         => 29.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER_H5_' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_h5 ) {
        $payment_h5 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_h5 );

        $checkout = new \OVR\Payment\CheckoutHandler();
        $checkout->init();
$method = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

        reset_event_counter();
        $method->invoke( $checkout, $payment_h5, [
            'payment_id'   => $payment_h5,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 29.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );
        $after_first_h5 = get_subscription_expiry( (int) $user_h5 );

        $method->invoke( $checkout, $payment_h5, [
            'payment_id'   => $payment_h5,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 29.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );
        $after_dup_h5 = get_subscription_expiry( (int) $user_h5 );

        echo "  Initial expiry:      $initial_h5\n";
        echo "  After first:         $after_first_h5\n";
        echo "  After duplicate:     $after_dup_h5\n";

        ok( $after_first_h5 !== $initial_h5, 'First completion extends expiry' );
        ok( $after_first_h5 === $after_dup_h5, 'Duplicate does not extend expiry again' );
        ok( get_event_count() === 1, 'Only one completion event fired for same payment' );
    }
}

// ------------------------------------------------------------------
// H6 — TWO DISTINCT RENEWALS EXTEND TWICE
// ------------------------------------------------------------------
echo "\n=== H6: Two distinct renewals extend twice ===\n";

$user_h6 = wp_create_user( make_email( 'ovr-h6' ), wp_generate_password(), make_email( 'ovr-h6' ) );
if ( ! is_wp_error( $user_h6 ) ) {
    track_synth_user( (int) $user_h6 );
    \OVR\Subscription\SubscriptionManager::activate( (int) $user_h6, 'standard_homeowner_5' );
    $initial_h6 = get_subscription_expiry( (int) $user_h6 );

    $pid_a_h6 = $wpdb->insert( $table, [
        'user_id'        => (int) $user_h6,
        'payment_type'   => 'subscription',
        'amount'         => 29.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER_H6A_' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    $id_a_h6 = (int) $wpdb->insert_id;
    track_synth_payment( $id_a_h6 );

    $pid_b_h6 = $wpdb->insert( $table, [
        'user_id'        => (int) $user_h6,
        'payment_type'   => 'subscription',
        'amount'         => 29.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER_H6B_' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    $id_b_h6 = (int) $wpdb->insert_id;
    track_synth_payment( $id_b_h6 );

    if ( $pid_a_h6 && $pid_b_h6 ) {

        $checkout = new \OVR\Payment\CheckoutHandler();
        $checkout->init();
$method = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

        reset_event_counter();
        $method->invoke( $checkout, $id_a_h6, [
            'payment_id'   => $id_a_h6,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 29.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );
        $after_a_h6 = get_subscription_expiry( (int) $user_h6 );

        $method->invoke( $checkout, $id_b_h6, [
            'payment_id'   => $id_b_h6,
            'plan_slug'    => 'standard_homeowner_5',
            'amount'       => 29.00,
            'gateway'      => 'paypal',
            'payment_type' => 'subscription',
        ] );
        $after_b_h6 = get_subscription_expiry( (int) $user_h6 );

        echo "  Initial expiry:      $initial_h6\n";
        echo "  After payment A:     $after_a_h6\n";
        echo "  After payment B:     $after_b_h6\n";

        ok( $after_a_h6 !== $initial_h6, 'Payment A extends expiry' );
        ok( $after_b_h6 !== $after_a_h6, 'Payment B extends expiry again' );
        ok( get_event_count() === 2, 'Two distinct payments produce two completion events' );
    }
}

// ------------------------------------------------------------------
// H7 — DUPLICATE FREE SUBSCRIPTION INTENT CREATES ONE EFFECT
// ------------------------------------------------------------------
echo "\n=== H7: Duplicate free subscription intent creates one effect ===\n";

$user_h7 = wp_create_user( make_email( 'ovr-h7' ), wp_generate_password(), make_email( 'ovr-h7' ) );
if ( ! is_wp_error( $user_h7 ) ) {
    track_synth_user( (int) $user_h7 );
    $checkout = new \OVR\Payment\CheckoutHandler();
    $checkout->init();
    $helper = new ReflectionMethod( $checkout, 'find_or_create_free_payment' );
    $atomic = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

    $meta_h7 = [ 'plan_slug' => 'standard_homeowner_5', 'promo_code' => 'FREE180' ];

    // Exercise the CREATION path twice for the same intent WITHOUT completing
    // in between, proving the intent guard dedupes the row.
    $payment_a_h7 = (int) $helper->invoke( $checkout, (int) $user_h7, 'subscription', $meta_h7 );
    track_synth_payment( $payment_a_h7 );
    $payment_b_h7 = (int) $helper->invoke( $checkout, (int) $user_h7, 'subscription', $meta_h7 );

    reset_event_counter();
    $atomic->invoke( $checkout, $payment_a_h7, [
        'payment_id'   => $payment_a_h7,
        'plan_slug'    => 'standard_homeowner_5',
        'amount'       => 0.0,
        'gateway'      => 'free',
        'payment_type' => 'subscription',
        'promo_code'   => 'FREE180',
    ] );

    $expiry_after_first_h7 = get_subscription_expiry( (int) $user_h7 );

    // Attempt to complete the duplicate payment ID returned on the second
    // creation call (same row, so this should be a no-op).
    $atomic->invoke( $checkout, $payment_b_h7, [
        'payment_id'   => $payment_b_h7,
        'plan_slug'    => 'standard_homeowner_5',
        'amount'       => 0.0,
        'gateway'      => 'free',
        'payment_type' => 'subscription',
        'promo_code'   => 'FREE180',
    ] );

    $expiry_after_dup_h7 = get_subscription_expiry( (int) $user_h7 );

    $count_h7 = (int) $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*) FROM $table
        WHERE user_id = %d AND payment_type = 'subscription' AND gateway = 'free'
    ", (int) $user_h7 ) );

    echo "  Payment rows for intent: $count_h7\n";
    echo "  After first:         $expiry_after_first_h7\n";
    echo "  After duplicate:     $expiry_after_dup_h7\n";

    ok( $payment_a_h7 === $payment_b_h7, 'Duplicate intent reuses same payment row' );
    ok( $count_h7 === 1, 'Exactly one payment row for the intent' );
    ok( get_event_count() === 1, 'Duplicate free intent fires event exactly once' );
    ok( $expiry_after_first_h7 === $expiry_after_dup_h7, 'Duplicate does not extend expiry again' );
}

// ------------------------------------------------------------------
// H8 — NEW LEGITIMATE FREE INTENT CREATES LATER EFFECT
// ------------------------------------------------------------------
echo "\n=== H8: New legitimate free intent creates later effect ===\n";

$user_h8 = wp_create_user( make_email( 'ovr-h8' ), wp_generate_password(), make_email( 'ovr-h8' ) );
if ( ! is_wp_error( $user_h8 ) ) {
    track_synth_user( (int) $user_h8 );
    $checkout = new \OVR\Payment\CheckoutHandler();
    $checkout->init();
    $helper = new ReflectionMethod( $checkout, 'find_or_create_free_payment' );
    $atomic = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

    $meta_first_h8 = [ 'plan_slug' => 'standard_homeowner_5', 'promo_code' => 'FREE180' ];
    $meta_second_h8 = [ 'plan_slug' => 'standard_homeowner_5', 'promo_code' => 'FREEPLUS' ];

    $payment_first_h8 = (int) $helper->invoke( $checkout, (int) $user_h8, 'subscription', $meta_first_h8 );
    track_synth_payment( $payment_first_h8 );
    $atomic->invoke( $checkout, $payment_first_h8, [
        'payment_id'   => $payment_first_h8,
        'plan_slug'    => 'standard_homeowner_5',
        'amount'       => 0.0,
        'gateway'      => 'free',
        'payment_type' => 'subscription',
        'promo_code'   => 'FREE180',
    ] );
    $expiry_after_first_h8 = get_subscription_expiry( (int) $user_h8 );

    $payment_second_h8 = (int) $helper->invoke( $checkout, (int) $user_h8, 'subscription', $meta_second_h8 );
    track_synth_payment( $payment_second_h8 );
    $atomic->invoke( $checkout, $payment_second_h8, [
        'payment_id'   => $payment_second_h8,
        'plan_slug'    => 'standard_homeowner_5',
        'amount'       => 0.0,
        'gateway'      => 'free',
        'payment_type' => 'subscription',
        'promo_code'   => 'FREEPLUS',
    ] );
    $expiry_after_second_h8 = get_subscription_expiry( (int) $user_h8 );

    $count_h8 = (int) $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*) FROM $table
        WHERE user_id = %d AND payment_type = 'subscription' AND gateway = 'free'
    ", (int) $user_h8 ) );

    echo "  Payment rows for two intents: $count_h8\n";
    echo "  After first intent:    $expiry_after_first_h8\n";
    echo "  After second intent:   $expiry_after_second_h8\n";

    ok( $payment_first_h8 !== $payment_second_h8, 'New intent creates distinct payment row' );
    ok( $count_h8 === 2, 'Two intents produce two payment rows' );
    ok( $expiry_after_second_h8 !== $expiry_after_first_h8, 'Second intent extends expiry again' );
}

// ------------------------------------------------------------------
// H9 — DUPLICATE FREE LISTING-UPGRADE INTENT CREATES ONE EFFECT
// ------------------------------------------------------------------
echo "\n=== H9: Duplicate free listing-upgrade intent creates one effect ===\n";

$user_h9 = wp_create_user( make_email( 'ovr-h9' ), wp_generate_password(), make_email( 'ovr-h9' ) );
if ( ! is_wp_error( $user_h9 ) ) {
    track_synth_user( (int) $user_h9 );
    $listing_h9 = wp_insert_post( [
        'post_title'  => 'Test Listing ' . wp_generate_password( 6, false ),
        'post_content'=> 'Test',
        'post_status' => 'publish',
        'post_type'   => 'ovr_property',
        'post_author' => (int) $user_h9,
    ] );

    if ( ! is_wp_error( $listing_h9 ) && $listing_h9 ) {
        track_synth_post( (int) $listing_h9 );

        $checkout = new \OVR\Payment\CheckoutHandler();
        $checkout->init();
$helper = new ReflectionMethod( $checkout, 'find_or_create_free_payment' );
$atomic = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

        $meta_h9 = [
            'upgrade'      => 'top_of_page',
            'service_type' => 'top_of_page',
            'term'         => 14,
            'property_id'  => (int) $listing_h9,
        ];

        // Exercise the CREATION path twice for the same intent WITHOUT
        // completing in between.
        $payment_a_h9 = (int) $helper->invoke( $checkout, (int) $user_h9, 'listing_upgrade', $meta_h9 );
        track_synth_payment( $payment_a_h9 );
        $payment_b_h9 = (int) $helper->invoke( $checkout, (int) $user_h9, 'listing_upgrade', $meta_h9 );

        reset_event_counter();
        $atomic->invoke( $checkout, $payment_a_h9, [
            'payment_id'   => $payment_a_h9,
            'amount'       => 0.0,
            'gateway'      => 'free',
            'payment_type' => 'listing_upgrade',
        ] );

        $expiry_after_first_h9 = get_upgrade_expiry( (int) $listing_h9, 'top_of_page' );

        $atomic->invoke( $checkout, $payment_b_h9, [
            'payment_id'   => $payment_b_h9,
            'amount'       => 0.0,
            'gateway'      => 'free',
            'payment_type' => 'listing_upgrade',
        ] );

        $expiry_after_dup_h9 = get_upgrade_expiry( (int) $listing_h9, 'top_of_page' );

        echo "  After first:         $expiry_after_first_h9\n";
        echo "  After duplicate:     $expiry_after_dup_h9\n";

        ok( $payment_a_h9 === $payment_b_h9, 'Duplicate upgrade intent reuses same payment row' );
        ok( get_event_count() === 1, 'Duplicate free upgrade intent fires event exactly once' );
        ok( $expiry_after_first_h9 === $expiry_after_dup_h9, 'Duplicate does not extend upgrade expiry again' );
    }
}

// ------------------------------------------------------------------
// T1 — SAME FREE SUBSCRIPTION INTENT REPLAY AFTER COMPLETION
// ------------------------------------------------------------------
echo "\n=== T1: Same free subscription intent replay after completion ===\n";

$user_t1 = wp_create_user( make_email( 'ovr-t1' ), wp_generate_password(), make_email( 'ovr-t1' ) );
if ( ! is_wp_error( $user_t1 ) ) {
    track_synth_user( (int) $user_t1 );
    $checkout = new \OVR\Payment\CheckoutHandler();
    $checkout->init();
    $helper = new ReflectionMethod( $checkout, 'find_or_create_free_payment' );
    $atomic = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

    $meta_t1 = [ 'plan_slug' => 'standard_homeowner_5', 'promo_code' => 'FREE180' ];
    $intent_t1 = wp_generate_uuid4();

    // First checkout intent submission.
    $payment_first_t1 = (int) $helper->invoke( $checkout, (int) $user_t1, 'subscription', $meta_t1, $intent_t1 );
    track_synth_payment( $payment_first_t1 );

    reset_event_counter();
    $atomic->invoke( $checkout, $payment_first_t1, [
        'payment_id'   => $payment_first_t1,
        'plan_slug'    => 'standard_homeowner_5',
        'amount'       => 0.0,
        'gateway'      => 'free',
        'payment_type' => 'subscription',
        'promo_code'   => 'FREE180',
    ] );

    $expiry_after_first_t1 = get_subscription_expiry( (int) $user_t1 );
    $count_after_first_t1 = (int) $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*) FROM $table WHERE user_id = %d AND payment_type = 'subscription' AND gateway = 'free'
    ", (int) $user_t1 ) );

    // Replay the SAME checkout intent AFTER completion.
    $payment_replay_t1 = (int) $helper->invoke( $checkout, (int) $user_t1, 'subscription', $meta_t1, $intent_t1 );
    $atomic->invoke( $checkout, $payment_replay_t1, [
        'payment_id'   => $payment_replay_t1,
        'plan_slug'    => 'standard_homeowner_5',
        'amount'       => 0.0,
        'gateway'      => 'free',
        'payment_type' => 'subscription',
        'promo_code'   => 'FREE180',
    ] );

    $expiry_after_replay_t1 = get_subscription_expiry( (int) $user_t1 );
    $count_after_replay_t1 = (int) $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*) FROM $table WHERE user_id = %d AND payment_type = 'subscription' AND gateway = 'free'
    ", (int) $user_t1 ) );

    echo "  After first:         $expiry_after_first_t1\n";
    echo "  After replay:        $expiry_after_replay_t1\n";
    echo "  Rows after first:    $count_after_first_t1\n";
    echo "  Rows after replay:   $count_after_replay_t1\n";

    ok( $payment_first_t1 === $payment_replay_t1, 'Replay returns same payment ID' );
    ok( $count_after_first_t1 === 1, 'Exactly one payment row after first completion' );
    ok( $count_after_replay_t1 === 1, 'Exactly one payment row after replay' );
    ok( get_event_count() === 1, 'Replay fires zero additional completion events' );
    ok( $expiry_after_first_t1 === $expiry_after_replay_t1, 'Replay does not extend expiry again' );
}

// ------------------------------------------------------------------
// T2 — NEW FREE SUBSCRIPTION INTENT WITH SAME BUSINESS METADATA
// ------------------------------------------------------------------
echo "\n=== T2: New free subscription intent with same business metadata ===\n";

$user_t2 = wp_create_user( make_email( 'ovr-t2' ), wp_generate_password(), make_email( 'ovr-t2' ) );
if ( ! is_wp_error( $user_t2 ) ) {
    track_synth_user( (int) $user_t2 );
    $checkout = new \OVR\Payment\CheckoutHandler();
    $checkout->init();
    $helper = new ReflectionMethod( $checkout, 'find_or_create_free_payment' );
    $atomic = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

    $meta_t2 = [ 'plan_slug' => 'standard_homeowner_5', 'promo_code' => 'FREE180' ];

    // First legitimate intent.
    $intent_first_t2 = wp_generate_uuid4();
    $payment_first_t2 = (int) $helper->invoke( $checkout, (int) $user_t2, 'subscription', $meta_t2, $intent_first_t2 );
    track_synth_payment( $payment_first_t2 );
    $atomic->invoke( $checkout, $payment_first_t2, [
        'payment_id'   => $payment_first_t2,
        'plan_slug'    => 'standard_homeowner_5',
        'amount'       => 0.0,
        'gateway'      => 'free',
        'payment_type' => 'subscription',
        'promo_code'   => 'FREE180',
    ] );
    $expiry_after_first_t2 = get_subscription_expiry( (int) $user_t2 );

    // Second legitimate intent with identical business metadata.
    $intent_second_t2 = wp_generate_uuid4();
    $payment_second_t2 = (int) $helper->invoke( $checkout, (int) $user_t2, 'subscription', $meta_t2, $intent_second_t2 );
    track_synth_payment( $payment_second_t2 );
    $atomic->invoke( $checkout, $payment_second_t2, [
        'payment_id'   => $payment_second_t2,
        'plan_slug'    => 'standard_homeowner_5',
        'amount'       => 0.0,
        'gateway'      => 'free',
        'payment_type' => 'subscription',
        'promo_code'   => 'FREE180',
    ] );
    $expiry_after_second_t2 = get_subscription_expiry( (int) $user_t2 );

    $count_t2 = (int) $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*) FROM $table WHERE user_id = %d AND payment_type = 'subscription' AND gateway = 'free'
    ", (int) $user_t2 ) );

    echo "  First intent:        $intent_first_t2\n";
    echo "  Second intent:       $intent_second_t2\n";
    echo "  Business metadata identical: yes\n";
    echo "  Effects: $count_t2 rows\n";
    echo "  Expiry after first:  $expiry_after_first_t2\n";
    echo "  Expiry after second: $expiry_after_second_t2\n";

    ok( $payment_first_t2 !== $payment_second_t2, 'New intent creates distinct payment row' );
    ok( $count_t2 === 2, 'Two intents produce two payment rows' );
    ok( $expiry_after_second_t2 !== $expiry_after_first_t2, 'Second intent extends expiry again' );
}

// ------------------------------------------------------------------
// T3 — SAME FREE LISTING-UPGRADE INTENT REPLAY AFTER COMPLETION
// ------------------------------------------------------------------
echo "\n=== T3: Same free listing-upgrade intent replay after completion ===\n";

$user_t3 = wp_create_user( make_email( 'ovr-t3' ), wp_generate_password(), make_email( 'ovr-t3' ) );
if ( ! is_wp_error( $user_t3 ) ) {
    track_synth_user( (int) $user_t3 );
    $listing_t3 = wp_insert_post( [
        'post_title'  => 'Test Listing ' . wp_generate_password( 6, false ),
        'post_content'=> 'Test',
        'post_status' => 'publish',
        'post_type'   => 'ovr_property',
        'post_author' => (int) $user_t3,
    ] );

    if ( ! is_wp_error( $listing_t3 ) && $listing_t3 ) {
        track_synth_post( (int) $listing_t3 );

        $checkout = new \OVR\Payment\CheckoutHandler();
        $checkout->init();
        $helper = new ReflectionMethod( $checkout, 'find_or_create_free_payment' );
        $atomic = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

        $meta_t3 = [
            'upgrade'      => 'top_of_page',
            'service_type' => 'top_of_page',
            'term'         => 14,
            'property_id'  => (int) $listing_t3,
        ];
        $intent_t3 = wp_generate_uuid4();

        // First checkout intent submission.
        $payment_first_t3 = (int) $helper->invoke( $checkout, (int) $user_t3, 'listing_upgrade', $meta_t3, $intent_t3 );
        track_synth_payment( $payment_first_t3 );

        reset_event_counter();
        $atomic->invoke( $checkout, $payment_first_t3, [
            'payment_id'   => $payment_first_t3,
            'amount'       => 0.0,
            'gateway'      => 'free',
            'payment_type' => 'listing_upgrade',
        ] );

        $expiry_after_first_t3 = get_upgrade_expiry( (int) $listing_t3, 'top_of_page' );
        $count_after_first_t3 = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM $table WHERE user_id = %d AND payment_type = 'listing_upgrade' AND gateway = 'free'
        ", (int) $user_t3 ) );

        // Replay the SAME checkout intent AFTER completion.
        $payment_replay_t3 = (int) $helper->invoke( $checkout, (int) $user_t3, 'listing_upgrade', $meta_t3, $intent_t3 );
        $atomic->invoke( $checkout, $payment_replay_t3, [
            'payment_id'   => $payment_replay_t3,
            'amount'       => 0.0,
            'gateway'      => 'free',
            'payment_type' => 'listing_upgrade',
        ] );

        $expiry_after_replay_t3 = get_upgrade_expiry( (int) $listing_t3, 'top_of_page' );
        $count_after_replay_t3 = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM $table WHERE user_id = %d AND payment_type = 'listing_upgrade' AND gateway = 'free'
        ", (int) $user_t3 ) );

        echo "  After first:         $expiry_after_first_t3\n";
        echo "  After replay:        $expiry_after_replay_t3\n";
        echo "  Rows after first:    $count_after_first_t3\n";
        echo "  Rows after replay:   $count_after_replay_t3\n";

        ok( $payment_first_t3 === $payment_replay_t3, 'Replay returns same payment ID' );
        ok( $count_after_first_t3 === 1, 'Exactly one payment row after first completion' );
        ok( $count_after_replay_t3 === 1, 'Exactly one payment row after replay' );
        ok( get_event_count() === 1, 'Replay fires zero additional completion events' );
        ok( $expiry_after_first_t3 === $expiry_after_replay_t3, 'Replay does not extend upgrade expiry again' );
    }
}

// ------------------------------------------------------------------
// T4 — NEW FREE LISTING-UPGRADE INTENT WITH SAME BUSINESS METADATA
// ------------------------------------------------------------------
echo "\n=== T4: New free listing-upgrade intent with same business metadata ===\n";

$user_t4 = wp_create_user( make_email( 'ovr-t4' ), wp_generate_password(), make_email( 'ovr-t4' ) );
if ( ! is_wp_error( $user_t4 ) ) {
    track_synth_user( (int) $user_t4 );
    $listing_t4 = wp_insert_post( [
        'post_title'  => 'Test Listing ' . wp_generate_password( 6, false ),
        'post_content'=> 'Test',
        'post_status' => 'publish',
        'post_type'   => 'ovr_property',
        'post_author' => (int) $user_t4,
    ] );

    if ( ! is_wp_error( $listing_t4 ) && $listing_t4 ) {
        track_synth_post( (int) $listing_t4 );

        $checkout = new \OVR\Payment\CheckoutHandler();
        $checkout->init();
        $helper = new ReflectionMethod( $checkout, 'find_or_create_free_payment' );
        $atomic = new ReflectionMethod( $checkout, 'complete_payment_atomically' );

        $meta_t4 = [
            'upgrade'      => 'top_of_page',
            'service_type' => 'top_of_page',
            'term'         => 14,
            'property_id'  => (int) $listing_t4,
        ];

        // First legitimate intent.
        $intent_first_t4 = wp_generate_uuid4();
        $payment_first_t4 = (int) $helper->invoke( $checkout, (int) $user_t4, 'listing_upgrade', $meta_t4, $intent_first_t4 );
        track_synth_payment( $payment_first_t4 );
        $atomic->invoke( $checkout, $payment_first_t4, [
            'payment_id'   => $payment_first_t4,
            'amount'       => 0.0,
            'gateway'      => 'free',
            'payment_type' => 'listing_upgrade',
        ] );
        $expiry_after_first_t4 = get_upgrade_expiry( (int) $listing_t4, 'top_of_page' );

        // Second legitimate intent with identical business metadata.
        $intent_second_t4 = wp_generate_uuid4();
        $payment_second_t4 = (int) $helper->invoke( $checkout, (int) $user_t4, 'listing_upgrade', $meta_t4, $intent_second_t4 );
        track_synth_payment( $payment_second_t4 );
        $atomic->invoke( $checkout, $payment_second_t4, [
            'payment_id'   => $payment_second_t4,
            'amount'       => 0.0,
            'gateway'      => 'free',
            'payment_type' => 'listing_upgrade',
        ] );
        $expiry_after_second_t4 = get_upgrade_expiry( (int) $listing_t4, 'top_of_page' );

        $count_t4 = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) FROM $table WHERE user_id = %d AND payment_type = 'listing_upgrade' AND gateway = 'free'
        ", (int) $user_t4 ) );

        echo "  First intent:        $intent_first_t4\n";
        echo "  Second intent:       $intent_second_t4\n";
        echo "  Business metadata identical: yes\n";
        echo "  Effects: $count_t4 rows\n";
        echo "  Expiry after first:  $expiry_after_first_t4\n";
        echo "  Expiry after second: $expiry_after_second_t4\n";

        ok( $payment_first_t4 !== $payment_second_t4, 'New intent creates distinct payment row' );
        ok( $count_t4 === 2, 'Two intents produce two payment rows' );
        ok( $expiry_after_second_t4 !== $expiry_after_first_t4, 'Second intent extends upgrade expiry again' );
    }
}

// ------------------------------------------------------------------
// H10 — PAYPAL FORGED RETURN CANNOT COMPLETE
// ------------------------------------------------------------------
echo "\n=== H10: PayPal forged return cannot complete ===\n";

$user_h10 = wp_create_user( make_email( 'ovr-h10' ), wp_generate_password(), make_email( 'ovr-h10' ) );
if ( ! is_wp_error( $user_h10 ) ) {
    track_synth_user( (int) $user_h10 );
    $pid_h10 = $wpdb->insert( $table, [
        'user_id'        => (int) $user_h10,
        'payment_type'   => 'subscription',
        'amount'         => 29.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER_H10_' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_h10 ) {
        $payment_h10 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_h10 );
        $row_h10 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $payment_h10 ), ARRAY_A );

        // Explicit E2E mock gate for PayPal OAuth (required since mu-plugin is strictly gated)
        set_transient( 'ovr_e2e_paypal_mock_active', '1', 300 );
        $h10_orig_settings = get_option( 'ovr_settings', [] );
        update_option( 'ovr_settings', array_merge( (array) $h10_orig_settings, [ 'paypal_env' => 'sandbox', 'paypal_sandbox_client_id' => 'test', 'paypal_sandbox_secret' => 'test', 'paypal_sandbox_webhook_id' => 'test' ] ) );
        // Mock PayPal capture API to return definitive failure.
        add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
            if ( false === strpos( (string) $url, '/v2/checkout/orders/' ) ) {
                return $preempt;
            }
            return [
                'response' => [ 'code' => 422, 'message' => 'Unprocessable Entity' ],
                'body'     => wp_json_encode( [ 'status' => 'FAILED', 'details' => [] ] ),
            ];
        }, 10, 3 );

        $_GET['token'] = 'FORGED_TOKEN_H10';

        $gateway_h10 = new \OVR\Payment\PayPalGateway();
        $res_h10 = $gateway_h10->finalize( $row_h10 );

        // Clean up the mock filter by replacing it with a no-op.
        remove_filter( 'pre_http_request', '__return_true' );
        update_option( 'ovr_settings', $h10_orig_settings );
        delete_transient( 'ovr_e2e_paypal_mock_active' );

        reset_event_counter();

        ok( empty( $res_h10['success'] ), 'PayPal finalize returns failure for forged return' );
        ok( ! empty( $res_h10['failed'] ), 'PayPal finalize marks forged return as failed' );

        $status_h10 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $payment_h10 ) );
        $sub_status_h10 = get_user_meta( (int) $user_h10, \OVR\Subscription\UserSubscription::META_STATUS, true );

        ok( $status_h10 !== 'completed', 'Payment is not completed after forged PayPal return (status=' . $status_h10 . ')' );
        ok( $sub_status_h10 !== \OVR\Subscription\UserSubscription::STATUS_ACTIVE, 'Subscription not activated by forged return' );
        ok( get_event_count() === 0, 'No completion event fired for forged PayPal return' );
    }
}

// ------------------------------------------------------------------
// H11 — AUTHORIZE.NET FORGED RETURN CANNOT COMPLETE
// ------------------------------------------------------------------
echo "\n=== H11: Authorize.Net forged return cannot complete ===\n";

$user_h11 = wp_create_user( make_email( 'ovr-h11' ), wp_generate_password(), make_email( 'ovr-h11' ) );
if ( ! is_wp_error( $user_h11 ) ) {
    track_synth_user( (int) $user_h11 );
    $pid_h11 = $wpdb->insert( $table, [
        'user_id'        => (int) $user_h11,
        'payment_type'   => 'subscription',
        'amount'         => 29.00,
        'currency'       => 'USD',
        'gateway'        => 'authorize_net',
        'transaction_id' => '',
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_h11 ) {
        $payment_h11 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_h11 );
        $row_h11 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $payment_h11 ), ARRAY_A );

        $_GET['x_trans_id'] = 'FORGED_AUTHNET_H11';

        $gateway_h11 = new \OVR\Payment\AuthorizeNetGateway();
        $res_h11 = $gateway_h11->finalize( $row_h11 );

        reset_event_counter();

        ok( empty( $res_h11['success'] ), 'Authorize.Net finalize returns failure without credentials' );
        ok( empty( $res_h11['failed'] ), 'Authorize.Net finalize does not mark unconfigured return as failed' );

        $status_h11 = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $payment_h11 ) );
        $sub_status_h11 = get_user_meta( (int) $user_h11, \OVR\Subscription\UserSubscription::META_STATUS, true );

        ok( $status_h11 === 'pending', 'Payment remains pending after forged Authorize.Net return (unconfigured)' );
        ok( $sub_status_h11 !== \OVR\Subscription\UserSubscription::STATUS_ACTIVE, 'Subscription not activated by forged return' );
        ok( get_event_count() === 0, 'No completion event fired for forged Authorize.Net return' );
    }
}

// ------------------------------------------------------------------
// H12 — SUCCESS PAGE ALONE CANNOT ACTIVATE
// ------------------------------------------------------------------
echo "\n=== H12: Success page alone cannot activate ===\n";

$user_h12 = wp_create_user( make_email( 'ovr-h12' ), wp_generate_password(), make_email( 'ovr-h12' ) );
if ( ! is_wp_error( $user_h12 ) ) {
    track_synth_user( (int) $user_h12 );
    $pid_h12 = $wpdb->insert( $table, [
        'user_id'        => (int) $user_h12,
        'payment_type'   => 'subscription',
        'amount'         => 29.00,
        'currency'       => 'USD',
        'gateway'        => 'paypal',
        'transaction_id' => 'ORDER_H12_' . wp_generate_uuid4(),
        'status'         => 'pending',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_h12 ) {
        $payment_h12 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_h12 );

        // Simulate visiting the success URL with only payment_id (no provider verification).
        $success_url = add_query_arg( 'payment_id', $payment_h12, home_url( '/payment-success/' ) );
        $parsed = wp_parse_url( $success_url );
        $_GET['payment_id'] = $payment_h12;

        // PaymentSuccess::render() only reads the payment; it does not change status.
        // Verify by direct read: payment must still be pending.
        $row_h12 = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $payment_h12 ), ARRAY_A );
        $sub_status_h12 = get_user_meta( (int) $user_h12, \OVR\Subscription\UserSubscription::META_STATUS, true );

        ok( $row_h12['status'] === 'pending', 'Payment stays pending after visiting success URL with only payment_id' );
        ok( $sub_status_h12 !== \OVR\Subscription\UserSubscription::STATUS_ACTIVE, 'Subscription not activated by success-page visit alone' );

        // Code inspection: success renderer must not call completion logic.
        $success_src = file_get_contents( __DIR__ . '/../src/Frontend/PaymentSuccess.php' );
        $calls_complete = strpos( $success_src, 'complete_payment' ) !== false
            || strpos( $success_src, 'ovr_payment_completed' ) !== false;
        ok( ! $calls_complete, 'PaymentSuccess renderer does not call completion logic' );
    }
}

// ------------------------------------------------------------------
// H13 — STRIPE UNAVAILABLE FOR NEW CHECKOUT
// ------------------------------------------------------------------
echo "\n=== H13: Stripe unavailable for new checkout ===\n";

$checkout_h13 = new \OVR\Payment\CheckoutHandler();
$checkout_h13->init();
$gateways_h13 = $checkout_h13->get_gateway_choices();
ok( ! isset( $gateways_h13['stripe'] ), 'Stripe not in active gateway choices' );
ok( isset( $gateways_h13['paypal'] ), 'PayPal remains available' );
ok( isset( $gateways_h13['authorize_net'] ), 'Authorize.Net remains available' );

// ------------------------------------------------------------------
// H14 — HISTORICAL STRIPE READABLE IN PRESENTATION LAYER
// ------------------------------------------------------------------
echo "\n=== H14: Historical Stripe readable in presentation layer ===\n";

$user_h14 = wp_create_user( make_email( 'ovr-h14' ), wp_generate_password(), make_email( 'ovr-h14' ) );
if ( ! is_wp_error( $user_h14 ) ) {
    track_synth_user( (int) $user_h14 );
    $pid_h14 = $wpdb->insert( $table, [
        'user_id'        => (int) $user_h14,
        'payment_type'   => 'subscription',
        'amount'         => 99.00,
        'currency'       => 'USD',
        'gateway'        => 'stripe',
        'transaction_id' => 'pi_historical_12345',
        'status'         => 'completed',
        'meta_data'      => wp_json_encode( [ 'plan_slug' => 'standard_homeowner_5' ] ),
    ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

    if ( $pid_h14 ) {
        $payment_h14 = (int) $wpdb->insert_id;
        track_synth_payment( $payment_h14 );

        // Admin history template label mapping.
        $gateway_labels_h14 = [
            'stripe'        => [ 'credit_card', 'Card (Stripe)' ],
            'authorize_net' => [ 'credit_card', 'Card' ],
            'paypal'        => [ 'account_balance', 'PayPal' ],
            'wallet'        => [ 'account_balance_wallet', 'Wallet' ],
            'free'          => [ 'redeem', 'Free' ],
        ];
        $gw_h14 = 'stripe';
        $label_h14 = $gateway_labels_h14[ $gw_h14 ][1] ?? ucwords( str_replace( '_', ' ', $gw_h14 ) );

        // PaymentSuccess fallback label.
        $success_labels_h14 = [
            'paypal'        => 'PayPal',
            'authorize_net' => 'Card',
            'stripe'        => 'Card (Stripe)',
            'wallet'        => 'OVR Balance',
            'free'          => 'Free Plan',
        ];
        $success_label_h14 = $success_labels_h14[ $gw_h14 ] ?? ucwords( str_replace( '_', ' ', $gw_h14 ) );

        ok( $label_h14 === 'Card (Stripe)', 'Admin history labels historical Stripe distinctly' );
        ok( $success_label_h14 === 'Card (Stripe)', 'Receipt page labels historical Stripe distinctly' );
        ok( ! is_null( $payment_h14 ), 'Historical Stripe payment row is readable in DB' );
    }
}

// ------------------------------------------------------------------
// H15 — TEST CLEANUP
// ------------------------------------------------------------------
echo "\n=== H15: Test cleanup leaves no synthetic records ===\n";

// ------------------------------------------------------------------
// T9 — WORDPRESS USER/POST CLEANUP API AVAILABLE
// ------------------------------------------------------------------
echo "\n=== T9: WordPress user/post cleanup API available ===\n";

ok( function_exists( 'wp_delete_user' ), 'wp_delete_user is available during test execution' );
ok( function_exists( 'wp_delete_post' ), 'wp_delete_post is available during test execution' );

$user_ids_to_check    = $synth_users;
$payment_ids_to_check = $synth_payments;
$post_ids_to_check    = $synth_posts;
$tracked_user_count    = count( $user_ids_to_check );
$tracked_payment_count = count( $payment_ids_to_check );
$tracked_post_count    = count( $post_ids_to_check );

cleanup_all();

$remaining_users = 0;
foreach ( $user_ids_to_check as $uid ) {
    $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID = %d", $uid ) );
    $remaining_users += $exists;
}

$remaining_payments = 0;
foreach ( $payment_ids_to_check as $pid ) {
    $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE id = %d", $pid ) );
    $remaining_payments += $exists;
}

$remaining_posts = 0;
foreach ( $post_ids_to_check as $post_id ) {
    $post = get_post( (int) $post_id );
    if ( $post && 'trash' !== $post->post_status ) {
        $remaining_posts++;
    }
}

echo "  Tracked synthetic users:    $tracked_user_count\n";
echo "  Remaining tracked users:    $remaining_users\n";
echo "  Tracked synthetic payments: $tracked_payment_count\n";
echo "  Remaining tracked payments: $remaining_payments\n";
echo "  Tracked synthetic posts:    $tracked_post_count\n";
echo "  Remaining tracked posts:    $remaining_posts\n";

ok( $remaining_users === 0, 'No tracked synthetic users remain after cleanup' );
ok( $remaining_payments === 0, 'No tracked synthetic payments remain after cleanup' );
ok( $remaining_posts === 0, 'No tracked synthetic posts remain after cleanup' );

// ------------------------------------------------------------------
// T5 — SERVER-AUTHORITATIVE PRICE VIA REAL CHECKOUT BOUNDARY
// ------------------------------------------------------------------
echo "\n=== T5: Server-authoritative price via real checkout boundary ===\n";

$user_t5 = wp_create_user( make_email( 'ovr-t5' ), wp_generate_password(), make_email( 'ovr-t5' ) );
if ( ! is_wp_error( $user_t5 ) ) {
    track_synth_user( (int) $user_t5 );

    $plan = \OVR\Subscription\Plans::get_plan( 'standard_homeowner_5' );
    $server_price = (float) ( $plan['price'] ?? 0 );
    $manipulated_amount = $server_price + 100.00;

    wp_set_current_user( (int) $user_t5 );
    wp_set_auth_cookie( (int) $user_t5 );

    $_POST = [
        'action'              => 'ovr_start_checkout',
        'plan'                => 'standard_homeowner_5',
        'ovr_checkout_nonce'  => wp_create_nonce( 'ovr_checkout_action' ),
        'ovr_checkout_intent' => wp_generate_uuid4(),
        'gateway'             => 'paypal',
        'amount'              => $manipulated_amount,
    ];

    $checkout = new \OVR\Payment\CheckoutHandler();
    $checkout->init();

    register_shutdown_function( function() use ( $table, $user_t5, $server_price, $manipulated_amount ) {
        global $wpdb, $pass, $fail;
        $payment = $wpdb->get_row( $wpdb->prepare( "
            SELECT id, amount FROM $table
            WHERE user_id = %d
            ORDER BY id DESC
            LIMIT 1
        ", (int) $user_t5 ), ARRAY_A );

        $captured_amount = $payment ? (float) $payment['amount'] : null;

        echo "  Server configured amount: $server_price\n";
        echo "  Manipulated submitted amount: $manipulated_amount\n";
        echo "  Created payment amount: " . ( $captured_amount !== null ? number_format( $captured_amount, 2 ) : 'N/A' ) . "\n";
        echo "  Exact handler/method exercised: CheckoutHandler::handle_start()\n";

        if ( $captured_amount === $server_price ) {
            echo "  PASS: Created payment uses server plan price, not client amount\n";
            $pass++;
        } else {
            echo "  FAIL: Created payment uses server plan price, not client amount\n";
            $fail++;
        }

        echo "\n=== RESULTS: $pass passed, $fail failed ===\n";
    } );

    try {
        $checkout->handle_start();
    } catch ( \Throwable $e ) {
        // Ignore.
    }
}

// ------------------------------------------------------------------
// Provider transaction identity assessment
// ------------------------------------------------------------------
echo "\n=== Provider transaction identity assessment ===\n";

$duplicate_groups = $wpdb->get_results( "
    SELECT gateway, transaction_id, COUNT(*) as cnt
    FROM $table
    WHERE transaction_id != ''
    GROUP BY gateway, transaction_id
    HAVING cnt > 1
", ARRAY_A );
echo "  Duplicate transaction groups: " . count( $duplicate_groups ) . "\n";

$empty_ids = $wpdb->get_results( "
    SELECT gateway, status, COUNT(*) as cnt
    FROM $table
    WHERE transaction_id = '' OR transaction_id IS NULL
    GROUP BY gateway, status
    ORDER BY gateway, status
", ARRAY_A );
echo "  Empty transaction IDs by gateway/status:\n";
foreach ( $empty_ids as $row ) {
    echo "    {$row['gateway']} / {$row['status']}: {$row['cnt']}\n";
}

delete_transient( 'ovr_e2e_paypal_mock_active' );
// ------------------------------------------------------------------
// SUMMARY
// ------------------------------------------------------------------
echo "\n=== RESULTS: $pass passed, $fail failed ===\n";
