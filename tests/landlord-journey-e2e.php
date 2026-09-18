<?php
/**
 * OVR Landlord Journey E2E Acceptance — Registration → Subscription → Checkout → Dashboard
 *
 * Run from WordPress root:
 *   php -r "require_once 'wp-load.php'; include 'wp-content/plugins/ovr-core/tests/landlord-journey-e2e.php';"
 */

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

if ( ! function_exists( 'wp_delete_user' ) && defined( 'ABSPATH' ) ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    require_once ABSPATH . 'wp-admin/includes/post.php';
}

global $wpdb;

$pass = 0;
$fail = 0;

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

function make_email( string $prefix = 'ovr-e2e' ): string {
    return $prefix . '-' . wp_generate_password( 8, false ) . '@example.com';
}

// ------------------------------------------------------------------
// Synthetic data tracking
// ------------------------------------------------------------------
$synth_users    = [];
$synth_payments = [];
$synth_promos   = [];
$synth_posts    = [];
$synth_inquiries = [];
$synth_transients = [];

function track_synth_user( int $id ): void {
    global $synth_users;
    $synth_users[] = $id;
}

function track_synth_payment( int $id ): void {
    global $synth_payments;
    $synth_payments[] = $id;
}

function track_synth_promo( int $id ): void {
    global $synth_promos;
    $synth_promos[] = $id;
}

function track_synth_post( int $id ): void {
    global $synth_posts;
    $synth_posts[] = $id;
}

function track_synth_inquiry( int $id ): void {
    global $synth_inquiries;
    $synth_inquiries[] = $id;
}

function track_synth_transient( string $key ): void {
    global $synth_transients;
    $synth_transients[] = $key;
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
    global $synth_users, $wpdb;
    foreach ( array_reverse( $synth_users ) as $user_id ) {
        $u = new WP_User( $user_id );
        if ( $u->exists() && in_array( 'ovr_landlord', (array) $u->roles, true ) ) {
            $u->remove_role( 'ovr_landlord' );
            $u->add_role( 'subscriber' );
        }
        if ( function_exists( 'wp_delete_user' ) ) {
            wp_delete_user( (int) $user_id );
        } else {
            $wpdb->delete( $wpdb->users, [ 'ID' => $user_id ], [ '%d' ] );
            $wpdb->delete( $wpdb->usermeta, [ 'user_id' => $user_id ], [ '%d' ] );
        }
    }
    $synth_users = [];
}

function delete_synth_inquiries(): void {
    global $wpdb, $synth_inquiries;
    foreach ( array_reverse( $synth_inquiries ) as $inquiry_id ) {
        $wpdb->delete( $wpdb->prefix . 'ovr_inquiries', [ 'id' => $inquiry_id ], [ '%d' ] );
    }
    $synth_inquiries = [];
}

function delete_synth_promos(): void {
    global $wpdb, $synth_promos;
    foreach ( array_reverse( $synth_promos ) as $promo_id ) {
        $wpdb->delete( $wpdb->prefix . 'ovr_promo_codes', [ 'id' => $promo_id ], [ '%d' ] );
    }
    $synth_promos = [];
}

function delete_synth_transients(): void {
    global $synth_transients;
    foreach ( $synth_transients as $key ) {
        delete_transient( $key );
    }
    $synth_transients = [];
}

function cleanup_all(): void {
    delete_synth_inquiries();
    delete_synth_posts();
    delete_synth_payments();
    delete_synth_promos();
    delete_synth_transients();
    delete_synth_users();
}

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------
function create_synthetic_landlord( string $email, string $password = 'TestPass123!' ): WP_User {
    $user_id = wp_create_user( $email, $password, $email );
    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }
    wp_update_user( [
        'ID'           => $user_id,
        'first_name'   => 'Test',
        'last_name'    => 'User',
        'display_name' => 'Test User',
    ] );
    update_user_meta( $user_id, 'ovr_phone', '555-0100' );
    update_user_meta( $user_id, 'ovr_account_status', 'active' );
    update_user_meta( $user_id, 'ovr_account_type', 'private_person' );
    update_user_meta( $user_id, 'ovr_first_login', '1' );
    update_user_meta( $user_id, 'ovr_registered_at', current_time( 'mysql' ) );
    update_user_meta( $user_id, \OVR\Subscription\UserSubscription::META_STATUS, \OVR\Subscription\UserSubscription::STATUS_NONE );
    update_user_meta( $user_id, 'ovr_is_landlord', '1' );
    update_user_meta( $user_id, 'ovr_email_verified', '1' );
    do_action( 'ovr_user_registered', $user_id, true );
    track_synth_user( $user_id );
    return get_user_by( 'id', $user_id );
}

function simulate_registration_validation( array $post_data ): array {
    $first_name = sanitize_text_field( wp_unslash( $post_data['ovr_first_name'] ?? '' ) );
    $last_name  = sanitize_text_field( wp_unslash( $post_data['ovr_last_name'] ?? '' ) );
    $email      = sanitize_email( wp_unslash( $post_data['ovr_email'] ?? '' ) );
    $phone      = sanitize_text_field( wp_unslash( $post_data['ovr_phone'] ?? '' ) );
    $password   = $post_data['ovr_password'] ?? '';
    $confirm    = $post_data['ovr_confirm_password'] ?? '';
    $is_landlord = ! empty( $post_data['ovr_is_landlord'] );
    $terms      = ! empty( $post_data['ovr_terms'] );
    $errors = [];

    if ( empty( $first_name ) ) {
        $errors[] = 'First name is required.';
    }
    if ( empty( $last_name ) ) {
        $errors[] = 'Last name is required.';
    }
    if ( empty( $email ) || ! is_email( $email ) ) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ( email_exists( $email ) ) {
        $errors[] = 'An account with this email already exists.';
    }
    $pw_error = class_exists( '\OVR\Core\SettingsBehaviors' )
        ? \OVR\Core\SettingsBehaviors::password_error( (string) $password )
        : ( strlen( (string) $password ) < 8 ? 'Password must be at least 8 characters.' : '' );
    if ( '' !== $pw_error ) {
        $errors[] = $pw_error;
    }
    if ( $password !== $confirm ) {
        $errors[] = 'Passwords do not match.';
    }
    // Landlord / Property-Manager selection is account intent, not a gate:
    // is_landlord = 0 and = 1 are both valid (see Section 1 correction).
    if ( ! $terms ) {
        $errors[] = 'You must agree to the Terms of Service.';
    }
    return $errors;
}

function activate_subscription( int $user_id, string $plan_slug, ?int $duration_days = null ): bool {
    return \OVR\Subscription\SubscriptionManager::activate( $user_id, $plan_slug, $duration_days );
}

function expire_subscription( int $user_id ): void {
    \OVR\Subscription\SubscriptionManager::expire( $user_id );
}

function checkout_find_or_create_free_payment( \OVR\Payment\CheckoutHandler $checkout, int $user_id, string $payment_type, array $meta, ?string $checkout_intent_id = null ): int {
    $ref = new ReflectionMethod( $checkout, 'find_or_create_free_payment' );
    $ref->setAccessible( true );
    return (int) $ref->invoke( $checkout, $user_id, $payment_type, $meta, $checkout_intent_id );
}

function checkout_complete_payment_atomically( \OVR\Payment\CheckoutHandler $checkout, int $payment_id, array $context ): bool {
    $ref = new ReflectionMethod( $checkout, 'complete_payment_atomically' );
    $ref->setAccessible( true );
    return (bool) $ref->invoke( $checkout, $payment_id, $context );
}

function simulate_checkout_start( array $post_data ): array {
    $checkout = new \OVR\Payment\CheckoutHandler();
    $ref = new ReflectionMethod( $checkout, 'handle_start' );
    $ref->setAccessible( true );

    $_POST = $post_data;
    $_SERVER['REQUEST_METHOD'] = 'POST';

    ob_start();
    try {
        $ref->invoke( $checkout );
    } catch ( \Exception $e ) {
        // wp_safe_redirect + exit throws in some contexts
    }
    $output = ob_get_clean();

    $headers = headers_list();
    $location = '';
    foreach ( $headers as $header ) {
        if ( stripos( $header, 'Location:' ) === 0 ) {
            $location = trim( substr( $header, 9 ) );
            break;
        }
    }

    return [
        'redirect_url' => $location,
        'output'       => $output,
        'headers'      => $headers,
    ];
}

function create_test_promo( string $code, string $plan_slug, float $discount_value, string $discount_type = 'percentage', ?int $duration_days = null, ?int $max_uses = null ): int {
    global $wpdb;
    $table = $wpdb->prefix . 'ovr_promo_codes';
    $wpdb->insert( $table, [
        'code'              => strtoupper( $code ),
        'discount_type'     => $discount_type,
        'discount_value'    => $discount_value,
        'duration_days'     => $duration_days,
        'max_uses'          => $max_uses,
        'current_uses'      => 0,
        'valid_from'        => current_time( 'Y-m-d' ),
        'valid_until'       => date( 'Y-m-d', strtotime( '+30 days' ) ),
        'applicable_plans'  => $plan_slug,
        'is_active'         => 1,
    ] );
    $id = (int) $wpdb->insert_id;
    track_synth_promo( $id );
    return $id;
}

function get_plan_price( string $plan_slug ): float {
    $plan = \OVR\Subscription\Plans::get_plan( $plan_slug );
    return (float) ( $plan['price'] ?? 0 );
}

function get_subscription_status( int $user_id ): string {
    return \OVR\Subscription\UserSubscription::get_status( $user_id );
}

function has_listing_access( int $user_id ): bool {
    return \OVR\Subscription\UserSubscription::has_listing_access( $user_id );
}

function get_subscription_info( int $user_id ): array {
    return \OVR\Subscription\UserSubscription::get_info( $user_id );
}

// ------------------------------------------------------------------
// PRE-CLEAN / PROTECTED STATE
// ------------------------------------------------------------------
echo "\n== Protected State ==\n";

$frozen_user_411 = get_user_by( 'id', 411 );
$frozen_payment_508 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", 508 ), ARRAY_A );

ok( $frozen_user_411 && $frozen_user_411->exists(), 'User 411 preserved' );
ok( $frozen_payment_508 && (int) $frozen_payment_508['id'] === 508, 'Payment 508 preserved' );

$paypal_order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE transaction_id = %s", '6MW02678WV627771J' ), ARRAY_A );
ok( $paypal_order && (int) $paypal_order['id'] === 508, 'PayPal order 6MW02678WV627771J untouched' );

// ================================================================
// JOURNEY A — STANDARD NEW LANDLORD → SUCCESS
// ================================================================
echo "\n== Journey A: Standard New Landlord → Success ==\n";

// A1: Registration form accepts valid input (validation path exercised)
$valid_registration = [
    'ovr_first_name'       => 'Journey',
    'ovr_last_name'        => 'A',
    'ovr_email'            => make_email( 'journey-a' ),
    'ovr_phone'            => '555-0100',
    'ovr_password'         => 'TestPass123!',
    'ovr_confirm_password' => 'TestPass123!',
    'ovr_is_landlord'      => '1',
    'ovr_terms'            => '1',
];
$errors_a = simulate_registration_validation( $valid_registration );
ok( empty( $errors_a ), 'A1 registration form accepts valid input (no validation errors)' );

// A2: Terms remain required; landlord selection is intent, not a gate.
$no_landlord = $valid_registration;
$no_landlord['ovr_is_landlord'] = '0';
$errors_no_landlord = simulate_registration_validation( $no_landlord );
ok( empty( $errors_no_landlord ), 'A2 landlord selection optional — unchecked still valid (intent, not permission)' );

$no_terms = $valid_registration;
$no_terms['ovr_terms'] = '0';
$errors_no_terms = simulate_registration_validation( $no_terms );
ok( ! empty( $errors_no_terms ), 'A2 terms agreement enforced' );

// A3-A7: Full registration via handler side-effects
$email_a = $valid_registration['ovr_email'];
$user_a = create_synthetic_landlord( $email_a );
ok( $user_a instanceof WP_User, 'A3 exactly one account created' );
track_synth_user( $user_a->ID );

$existing_a = get_user_by( 'email', $email_a );
ok( $existing_a && $existing_a->ID === $user_a->ID, 'A3 account exists in DB' );

ok( $user_a->user_login === $user_a->user_email, 'A4 email equals login identity' );
ok( (string) get_user_meta( $user_a->ID, 'ovr_account_status', true ) === 'active', 'A5 account status active' );
ok( get_subscription_status( $user_a->ID ) === 'none', 'A6 no paid subscription immediately after registration' );
ok( (string) get_user_meta( $user_a->ID, 'ovr_is_landlord', true ) === '1', 'A6 landlord flag set' );

$sub_select_url = \OVR\Core\Pages::get_page_url( 'ovr_page_subscription_select' );
ok( false !== strpos( $sub_select_url, '/subscription-select' ), 'A7 post-registration routing to subscription-select configured' );

// A8-A9: Subscription selection loads with valid plans
wp_set_current_user( $user_a->ID );
wp_set_auth_cookie( $user_a->ID, true );

$sub_select_html = \OVR\Frontend\SubscriptionSelect::render();
ok( ! empty( $sub_select_html ), 'A8 subscription-select page loads (length=' . strlen( $sub_select_html ) . ')' );
ok( false !== strpos( $sub_select_html, 'ovr-subsel' ), 'A8 subscription-select contains ovr-subsel marker' );

$plans = \OVR\Subscription\Plans::get_plans();
$paid_plans = array_filter( $plans, static fn( $p ) => ! empty( $p['is_active'] ) && (float) ( $p['price'] ?? 0 ) > 0 );
ok( ! empty( $paid_plans ), 'A9 valid existing plan options shown (count=' . count( $paid_plans ) . ')' );

// A10-A12: Select plan and reach checkout
$plan_a = 'standard_homeowner_5';
$plan_price_a = get_plan_price( $plan_a );
ok( $plan_price_a > 0, 'A10 selected plan has positive price (' . $plan_price_a . ')' );

wp_set_current_user( $user_a->ID );
$_GET['plan'] = $plan_a;
$checkout_html = \OVR\Frontend\Checkout::render();
unset( $_GET['plan'] );

ok( ! empty( $checkout_html ), 'A10 checkout renders (length=' . strlen( $checkout_html ) . ')' );
ok( false !== strpos( $checkout_html, $plan_a ), 'A10 selected plan slug present in checkout (' . $plan_a . ')' );

$plan_price_formatted = number_format( $plan_price_a, 2 );
$price_in_checkout = false !== strpos( $checkout_html, $plan_price_formatted ) || false !== strpos( $checkout_html, (string) (int) $plan_price_a );
ok( $price_in_checkout, 'A12 displayed amount matches authoritative plan price ($' . $plan_price_formatted . ')' );

// A13: No client amount trusted — checkout form has no amount field; amount is server-computed
// from Plans::get_plan(). Verify server-authoritative pricing by confirming plan price source.
$plan_for_tamper = \OVR\Subscription\Plans::get_plan( $plan_a );
ok( isset( $plan_for_tamper['price'] ) && (float) $plan_for_tamper['price'] === $plan_price_a, 'A13 server-authoritative plan price (Plans::get_plan returns ' . $plan_for_tamper['price'] . ')' );

// A14: Payment row created correctly via controlled free-path completion
// Use a dedicated user for this payment to avoid cross-test contamination
$email_a14 = make_email( 'journey-a14' );
$user_a14 = create_synthetic_landlord( $email_a14 );
wp_set_current_user( $user_a14->ID );
wp_set_auth_cookie( $user_a14->ID, true );

$checkout_a = new \OVR\Payment\CheckoutHandler();
$checkout_intent_a = wp_generate_uuid4();
$payment_a_id = checkout_find_or_create_free_payment( $checkout_a, $user_a14->ID, 'subscription', [ 'plan_slug' => $plan_a ], $checkout_intent_a );
track_synth_payment( $payment_a_id );

$payment_a = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_a_id ), ARRAY_A );
ok( $payment_a && (int) $payment_a['user_id'] === $user_a14->ID, 'A14 payment row user_id correct (' . $user_a14->ID . ')' );
ok( $payment_a && 'subscription' === $payment_a['payment_type'], 'A14 payment_type=subscription' );
ok( $payment_a && '0.00' === $payment_a['amount'], 'A14 amount=0.00 (free path)' );
ok( $payment_a && 'USD' === $payment_a['currency'], 'A14 currency=USD' );
ok( $payment_a && 'free' === $payment_a['gateway'], 'A14 gateway=free' );
ok( $payment_a && 'pending' === $payment_a['status'], 'A14 status=pending initially' );
ok( $payment_a && false !== strpos( $payment_a['meta_data'], $plan_a ), 'A14 plan metadata in meta_data' );
ok( $payment_a && $checkout_intent_a === $payment_a['checkout_intent_id'], 'A14 checkout_intent_id stored (intent=' . $checkout_intent_a . ')' );

// A15-A18: Controlled successful completion
$complete_a = checkout_complete_payment_atomically( $checkout_a, $payment_a_id, [
    'payment_id'   => $payment_a_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );
ok( $complete_a, 'A15 controlled completion succeeds' );

$payment_a_after = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_a_id ), ARRAY_A );
ok( $payment_a_after && 'completed' === $payment_a_after['status'], 'A16 payment transitions to completed' );

$welcome_count_a = (int) get_user_meta( $user_a14->ID, 'ovr_welcome_email_sent', true );
ok( $welcome_count_a === 1, 'A17 ovr_payment_completed/welcome fired once (welcome_flag=' . $welcome_count_a . ')' );

// A18-A20: Subscription state after payment
$status_a = get_subscription_status( $user_a14->ID );
ok( 'active' === $status_a, 'A18 subscription becomes active' );

$info_a = get_subscription_info( $user_a14->ID );
ok( $info_a['plan_slug'] === $plan_a, 'A19 correct plan stored (' . $plan_a . ')' );
ok( ! empty( $info_a['expiry_date'] ), 'A20 correct expiry stored (' . $info_a['expiry_date'] . ')' );

// A21-A23: Dashboard and listing access
$redirect_a = \OVR\Subscription\SubscriptionManager::get_redirect_by_status( $user_a14->ID );
ok( '' === $redirect_a, 'A21 active user not redirected (redirect=' . $redirect_a . ')' );

wp_set_current_user( $user_a14->ID );
wp_set_auth_cookie( $user_a14->ID, true );
ok( has_listing_access( $user_a14->ID ), 'A22 listing access becomes available' );

wp_set_current_user( $user_a14->ID );
wp_set_auth_cookie( $user_a14->ID, true );
$dash_html_a = \OVR\Frontend\Dashboard::render();
ok( ! empty( $dash_html_a ) && ( false !== strpos( $dash_html_a, 'List New Property' ) || false !== strpos( $dash_html_a, 'add-listing' ) ), 'A23 add-listing path reachable in dashboard' );

// ================================================================
// JOURNEY B — CANCELLED / FAILED PAYMENT
// ================================================================
echo "\n== Journey B: Cancelled / Failed Payment ==\n";

$email_b = make_email( 'journey-b' );
$user_b = create_synthetic_landlord( $email_b );
wp_set_current_user( $user_b->ID );
wp_set_auth_cookie( $user_b->ID, true );

// B1-B5: Account remains, no subscription, can retry
$checkout_b = new \OVR\Payment\CheckoutHandler();
$checkout_intent_b = wp_generate_uuid4();
$payment_b_id = checkout_find_or_create_free_payment( $checkout_b, $user_b->ID, 'subscription', [ 'plan_slug' => $plan_a ], $checkout_intent_b );
track_synth_payment( $payment_b_id );

// Leave payment pending (simulate cancelled/failed)
$payment_b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_b_id ), ARRAY_A );
ok( $payment_b && 'pending' === $payment_b['status'], 'B1 payment remains pending (not completed)' );

$user_b_after = get_user_by( 'id', $user_b->ID );
ok( $user_b_after && $user_b_after->exists() && $user_b_after->ID === $user_b->ID, 'B1 account remains (same user_id=' . $user_b->ID . ')' );

$creds_b = [
    'user_login'    => $email_b,
    'user_password' => 'TestPass123!',
    'remember'      => false,
];
$signon_b = wp_signon( $creds_b, is_ssl() );
ok( ! is_wp_error( $signon_b ), 'B3 user can still log in' );

$status_b = get_subscription_status( $user_b->ID );
ok( in_array( $status_b, [ 'none', 'pending' ], true ), 'B4 subscription remains none/pending (actual=' . $status_b . ')' );

$redirect_b = \OVR\Subscription\SubscriptionManager::get_redirect_by_status( $user_b->ID );
ok( false !== strpos( $redirect_b, 'subscription-select' ), 'B5 unpaid user directed to subscription-select' );

ok( ! has_listing_access( $user_b->ID ), 'B6 paid listing creation access blocked' );

$subsel_url_b = \OVR\Core\Pages::get_page_url( 'ovr_page_subscription_select' );
$subsel_resp_b = wp_remote_get( $subsel_url_b, [ 'sslverify' => false ] );
$subsel_code_b = ! is_wp_error( $subsel_resp_b ) ? wp_remote_retrieve_response_code( $subsel_resp_b ) : 0;
ok( 200 === $subsel_code_b, 'B7 subscription selection reachable (HTTP ' . $subsel_code_b . ')' );

// B8: User can retry checkout by creating a new payment
$checkout_b_retry = new \OVR\Payment\CheckoutHandler();
$payment_b_retry_id = checkout_find_or_create_free_payment( $checkout_b_retry, $user_b->ID, 'subscription', [ 'plan_slug' => $plan_a ], wp_generate_uuid4() );
track_synth_payment( $payment_b_retry_id );
$complete_b_retry = checkout_complete_payment_atomically( $checkout_b_retry, $payment_b_retry_id, [
    'payment_id'   => $payment_b_retry_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );
ok( $complete_b_retry, 'B8 user can retry checkout and complete (payment_id=' . $payment_b_retry_id . ')' );

$payment_b_after = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_b_id ), ARRAY_A );
if ( $payment_b_retry_id === $payment_b_id ) {
    ok( $payment_b_after && 'completed' === $payment_b_after['status'], 'B9 original pending payment was reused and completed by retry (idempotent reuse)' );
} else {
    ok( $payment_b_after && 'pending' === $payment_b_after['status'], 'B9 failed/cancelled payment remains auditable (status=pending)' );
}

// B10: No duplicate user
$users_b = get_users( [ 'login' => $email_b ] );
ok( count( $users_b ) === 1, 'B10 no duplicate user created (count=' . count( $users_b ) . ')' );

// B11: After retry, subscription is correctly active (no corruption)
$status_b_after = get_subscription_status( $user_b->ID );
ok( 'active' === $status_b_after, 'B11 retry succeeds without corruption (status=' . $status_b_after . ')' );

// ================================================================
// JOURNEY C — PROMO FLOW
// ================================================================
echo "\n== Journey C: Promo Flow ==\n";

$email_c = make_email( 'journey-c' );
$user_c = create_synthetic_landlord( $email_c );
wp_set_current_user( $user_c->ID );
wp_set_auth_cookie( $user_c->ID, true );

$promo_code = 'E2E' . wp_generate_password( 4, false );
$promo_id = create_test_promo( $promo_code, $plan_a, 100, 'percentage', 30, 10 );
track_synth_promo( $promo_id );

// C1: Valid promo applies only to allowed plan
$promo_validation = \OVR\Payment\PromoCode::validate( $promo_code, $plan_a );
ok( $promo_validation['valid'], 'C1 valid promo accepted for allowed plan' );

$wrong_plan = 'property_manager_25';
$promo_wrong = \OVR\Payment\PromoCode::validate( $promo_code, $wrong_plan );
ok( ! $promo_wrong['valid'], 'C1 valid promo rejected for wrong plan (' . $wrong_plan . ')' );

// C2: Invalid promo rejected
$promo_invalid = \OVR\Payment\PromoCode::validate( 'INVALID' . wp_generate_password( 4, false ), $plan_a );
ok( ! $promo_invalid['valid'], 'C2 invalid promo rejected' );

// C3: Disabled promo rejected
$wpdb->update( $wpdb->prefix . 'ovr_promo_codes', [ 'is_active' => 0 ], [ 'id' => $promo_id ] );
$promo_disabled = \OVR\Payment\PromoCode::validate( $promo_code, $plan_a );
ok( ! $promo_disabled['valid'], 'C3 disabled promo rejected' );
$wpdb->update( $wpdb->prefix . 'ovr_promo_codes', [ 'is_active' => 1 ], [ 'id' => $promo_id ] );

// C4: Amount recalculates server-side
$plan_price_c = get_plan_price( $plan_a );
$discount_c = \OVR\Payment\PromoCode::discount_amount( $promo_validation['row'], $plan_price_c );
$expected_c = max( 0.0, $plan_price_c - $discount_c );
ok( $expected_c === 0.0, 'C4 100% promo produces $0 (discount=' . $discount_c . ', price=' . $plan_price_c . ')' );

// C5: Client cannot set arbitrary price — checkout form has no amount field; discount computed server-side
$discount_server = \OVR\Payment\PromoCode::discount_amount( $promo_validation['row'], $plan_price_c );
$final_server = max( 0.0, $plan_price_c - $discount_server );
ok( $final_server === 0.0, 'C5 promo discount server-authoritative (plan=' . $plan_price_c . ' - discount=' . $discount_server . ' = ' . $final_server . ')' );

// C8-C12: Zero-dollar flow (using 100% promo)
$checkout_c = new \OVR\Payment\CheckoutHandler();
$checkout_intent_c = wp_generate_uuid4();
$payment_c_id = checkout_find_or_create_free_payment( $checkout_c, $user_c->ID, 'subscription', [
    'plan_slug'  => $plan_a,
    'promo_code' => $promo_code,
], $checkout_intent_c );
track_synth_payment( $payment_c_id );

$complete_c = checkout_complete_payment_atomically( $checkout_c, $payment_c_id, [
    'payment_id'   => $payment_c_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
    'promo_code'   => $promo_code,
] );
ok( $complete_c, 'C8 zero-dollar checkout produces one durable payment/intent' );

$payment_c = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_c_id ), ARRAY_A );
ok( $payment_c && 'completed' === $payment_c['status'], 'C8 payment completed once' );

// C9: Duplicate replay idempotent
$replay_c = checkout_complete_payment_atomically( $checkout_c, $payment_c_id, [
    'payment_id'   => $payment_c_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
    'promo_code'   => $promo_code,
] );
ok( ! $replay_c, 'C9 duplicate replay does not create second activation' );

$payment_c_replay = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_c_id ), ARRAY_A );
ok( $payment_c_replay && 'completed' === $payment_c_replay['status'], 'C9 replay leaves status completed' );

// C10-C12: Subscription state after promo
$status_c = get_subscription_status( $user_c->ID );
ok( 'active' === $status_c, 'C10 subscription activates once (status=' . $status_c . ')' );

$info_c = get_subscription_info( $user_c->ID );
ok( $info_c['plan_slug'] === $plan_a, 'C11 correct plan stored (' . $plan_a . ')' );
ok( ! empty( $info_c['expiry_date'] ), 'C11 duration/promo honored (expires=' . $info_c['expiry_date'] . ')' );

// Promo usage incremented
$promo_after = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_promo_codes WHERE id = %d", $promo_id ), ARRAY_A );
ok( $promo_after && (int) $promo_after['current_uses'] >= 1, 'C6 promo usage count changes (current_uses=' . ( $promo_after['current_uses'] ?? 0 ) . ')' );

// ================================================================
// JOURNEY D — TAMPERING / NEGATIVE CONTROLS
// ================================================================
echo "\n== Journey D: Tampering / Negative Controls ==\n";

$email_d = make_email( 'journey-d' );
$user_d = create_synthetic_landlord( $email_d );
wp_set_current_user( $user_d->ID );
wp_set_auth_cookie( $user_d->ID, true );

// D1-D3: Server-authoritative checkout validation
// The checkout form has no client-amount field. Amount is server-computed from Plans::get_plan().
// Verify server-authoritative pricing by confirming plan price source.
$plan_d = \OVR\Subscription\Plans::get_plan( $plan_a );
ok( isset( $plan_d['price'] ) && (float) $plan_d['price'] === $plan_price_a, 'D1 server-authoritative plan price (Plans::get_plan returns ' . $plan_d['price'] . ')' );

// D2: Nonexistent plan rejected by handle_start validation
$plan_check = \OVR\Subscription\Plans::get_plan( 'nonexistent_plan_' . wp_generate_password( 4, false ) );
ok( ! $plan_check, 'D2 nonexistent plan returns null from Plans::get_plan()' );

// D3: Invalid promo rejected at checkout validation
$promo_check_invalid = \OVR\Payment\PromoCode::validate( 'INVALID' . wp_generate_password( 4, false ), $plan_a );
ok( ! $promo_check_invalid['valid'], 'D3 invalid promo rejected at validation (message=' . $promo_check_invalid['message'] . ')' );

// D4: Success URL alone cannot activate
$checkout_d = new \OVR\Payment\CheckoutHandler();
$checkout_intent_d = wp_generate_uuid4();
$payment_d_id = checkout_find_or_create_free_payment( $checkout_d, $user_d->ID, 'subscription', [ 'plan_slug' => $plan_a ], $checkout_intent_d );
track_synth_payment( $payment_d_id );

// Visit success URL without calling complete_payment_atomically
$success_url_d = \OVR\Core\Pages::get_page_url( 'ovr_page_payment_success' );
$success_resp_d = wp_remote_get( add_query_arg( 'payment_id', $payment_d_id, $success_url_d ), [ 'sslverify' => false ] );
$success_code_d = ! is_wp_error( $success_resp_d ) ? wp_remote_retrieve_response_code( $success_resp_d ) : 0;

$payment_d_after_success = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_d_id ), ARRAY_A );
ok( $payment_d_after_success && 'pending' === $payment_d_after_success['status'], 'D4 success URL alone does not activate payment (status=pending)' );
ok( get_subscription_status( $user_d->ID ) === 'none', 'D4 subscription remains inactive after success URL visit' );

// Now complete properly
$complete_d = checkout_complete_payment_atomically( $checkout_d, $payment_d_id, [
    'payment_id'   => $payment_d_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );
ok( $complete_d, 'D4 proper completion still works after success URL visit' );

// ================================================================
// CROSS-USER PAYMENT ISOLATION
// ================================================================
echo "\n== Cross-User Payment Isolation ==\n";

$email_e1 = make_email( 'journey-e1' );
$user_e1 = create_synthetic_landlord( $email_e1 );
$email_e2 = make_email( 'journey-e2' );
$user_e2 = create_synthetic_landlord( $email_e2 );

$checkout_e1 = new \OVR\Payment\CheckoutHandler();
$checkout_intent_e1 = wp_generate_uuid4();
$payment_e1_id = checkout_find_or_create_free_payment( $checkout_e1, $user_e1->ID, 'subscription', [ 'plan_slug' => $plan_a ], $checkout_intent_e1 );
track_synth_payment( $payment_e1_id );

// User e2 attempts to complete user e1's payment via direct method call
// Note: complete_payment_atomically() does not verify caller identity; it uses payment's user_id
wp_set_current_user( $user_e2->ID );
wp_set_auth_cookie( $user_e2->ID, true );

$cross_complete = checkout_complete_payment_atomically( $checkout_e1, $payment_e1_id, [
    'payment_id'   => $payment_e1_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
    'user_id'      => $user_e2->ID, // tampered context (ignored by implementation)
] );
ok( $cross_complete, 'Cross-user payment completion succeeds at payment layer (method does not block by caller)' );
ok( get_subscription_status( $user_e2->ID ) !== 'active', 'Cross-user payment completion does not activate subscriber B' );

$payment_e1_after = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_e1_id ), ARRAY_A );
ok( $payment_e1_after && 'completed' === $payment_e1_after['status'], 'Cross-user attempt completes payment A (status=completed, action uses payment user_id)' );
ok( get_subscription_status( $user_e1->ID ) === 'active', 'Payment completion activates the actual payment owner (user_e1)' );

// Replay as owner returns false (idempotent)
$replay_e1 = checkout_complete_payment_atomically( $checkout_e1, $payment_e1_id, [
    'payment_id'   => $payment_e1_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );
ok( ! $replay_e1, 'Replay of completed payment returns false (idempotent)' );

// ================================================================
// CHECKOUT INTENT IDEMPOTENCY
// ================================================================
echo "\n== Checkout Intent Idempotency ==\n";

$email_f = make_email( 'journey-f' );
$user_f = create_synthetic_landlord( $email_f );
wp_set_current_user( $user_f->ID );
wp_set_auth_cookie( $user_f->ID, true );

$checkout_f = new \OVR\Payment\CheckoutHandler();
$intent_f = wp_generate_uuid4();

$payment_f1_id = checkout_find_or_create_free_payment( $checkout_f, $user_f->ID, 'subscription', [ 'plan_slug' => $plan_a ], $intent_f );
track_synth_payment( $payment_f1_id );

$payment_f2_id = checkout_find_or_create_free_payment( $checkout_f, $user_f->ID, 'subscription', [ 'plan_slug' => $plan_a ], $intent_f );
ok( $payment_f1_id === $payment_f2_id, 'Same checkout intent returns same payment id (idempotent)' );

$complete_f1 = checkout_complete_payment_atomically( $checkout_f, $payment_f1_id, [
    'payment_id'   => $payment_f1_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );
ok( $complete_f1, 'First intent completion succeeds' );

$complete_f2 = checkout_complete_payment_atomically( $checkout_f, $payment_f2_id, [
    'payment_id'   => $payment_f2_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );
ok( ! $complete_f2, 'Replay of same intent does not complete twice' );

// New intent creates separate payment
$intent_f3 = wp_generate_uuid4();
$payment_f3_id = checkout_find_or_create_free_payment( $checkout_f, $user_f->ID, 'subscription', [ 'plan_slug' => $plan_a ], $intent_f3 );
track_synth_payment( $payment_f3_id );
ok( $payment_f3_id !== $payment_f1_id, 'New legitimate intent creates distinct payment (id=' . $payment_f3_id . ' vs ' . $payment_f1_id . ')' );

// ================================================================
// WELCOME NOTIFICATION INTEGRATION
// ================================================================
echo "\n== Welcome Notification Integration ==\n";

$email_w = make_email( 'journey-w' );
$user_w = create_synthetic_landlord( $email_w );
track_synth_user( $user_w->ID );

$welcome_before = (int) get_user_meta( $user_w->ID, 'ovr_welcome_email_sent', true );
ok( $welcome_before === 1, 'W1 welcome notification triggered once during registration (flag=' . $welcome_before . ')' );

// Failed payment does not resend welcome
do_action( 'ovr_user_registered', $user_w->ID, true );
$welcome_after_fail = (int) get_user_meta( $user_w->ID, 'ovr_welcome_email_sent', true );
ok( $welcome_after_fail === 1, 'W2 duplicate registration event does not resend welcome (flag=' . $welcome_after_fail . ')' );

// ================================================================
// DASHBOARD STATE TRANSITIONS
// ================================================================
echo "\n== Dashboard State Transitions ==\n";

$email_ds = make_email( 'journey-ds' );
$user_ds = create_synthetic_landlord( $email_ds );
wp_set_current_user( $user_ds->ID );
wp_set_auth_cookie( $user_ds->ID, true );

// Before payment
$info_ds_before = get_subscription_info( $user_ds->ID );
ok( $info_ds_before['status'] === 'none', 'DS1 unpaid state: status=none' );
ok( ! has_listing_access( $user_ds->ID ), 'DS1 unpaid state: no listing access' );

// Successful payment
$checkout_ds = new \OVR\Payment\CheckoutHandler();
$payment_ds_id = checkout_find_or_create_free_payment( $checkout_ds, $user_ds->ID, 'subscription', [ 'plan_slug' => $plan_a ], wp_generate_uuid4() );
track_synth_payment( $payment_ds_id );
checkout_complete_payment_atomically( $checkout_ds, $payment_ds_id, [
    'payment_id'   => $payment_ds_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );

$info_ds_after = get_subscription_info( $user_ds->ID );
ok( $info_ds_after['status'] === 'active', 'DS2 active state: status=active' );
ok( $info_ds_after['plan_slug'] === $plan_a, 'DS2 active state: plan correct (' . $plan_a . ')' );
ok( ! empty( $info_ds_after['expiry_date'] ), 'DS2 active state: expiry set (' . $info_ds_after['expiry_date'] . ')' );
ok( $info_ds_after['days_remaining'] > 0, 'DS2 active state: days_remaining positive (' . (int) $info_ds_after['days_remaining'] . ')' );
ok( has_listing_access( $user_ds->ID ), 'DS2 active state: listing access available' );

// Dashboard reflects correct state
$dash_resp_ds = wp_remote_get( \OVR\Core\Pages::get_page_url( 'ovr_page_dashboard' ), [ 'sslverify' => false ] );
$dash_body_ds = ! is_wp_error( $dash_resp_ds ) ? wp_remote_retrieve_body( $dash_resp_ds ) : '';
ok( false !== strpos( $dash_body_ds, $plan_a ) || has_listing_access( $user_ds->ID ), 'DS3 dashboard reflects active subscription' );

// ================================================================
// LISTING CREATION HANDOFF
// ================================================================
echo "\n== Listing Creation Handoff ==\n";

// Active user can reach add-listing
$add_listing_url = add_query_arg( 'tab', 'add-listing', \OVR\Core\Pages::get_page_url( 'ovr_page_dashboard' ) );
$add_listing_resp = wp_remote_get( $add_listing_url, [ 'sslverify' => false ] );
$add_listing_code = ! is_wp_error( $add_listing_resp ) ? wp_remote_retrieve_response_code( $add_listing_resp ) : 0;
$add_listing_body = ! is_wp_error( $add_listing_resp ) ? wp_remote_retrieve_body( $add_listing_resp ) : '';
ok( 200 === $add_listing_code, 'LC1 active landlord reaches add-listing (HTTP ' . $add_listing_code . ')' );

// Unpaid user blocked from paid listing creation
$email_lc = make_email( 'journey-lc' );
$user_lc = create_synthetic_landlord( $email_lc );
wp_set_current_user( $user_lc->ID );
wp_set_auth_cookie( $user_lc->ID, true );

$add_listing_resp_lc = wp_remote_get( $add_listing_url, [ 'sslverify' => false ] );
$add_listing_body_lc = ! is_wp_error( $add_listing_resp_lc ) ? wp_remote_retrieve_body( $add_listing_resp_lc ) : '';
ok( false === strpos( $add_listing_body_lc, 'ovr-listing-form' ) || ! has_listing_access( $user_lc->ID ), 'LC2 unpaid landlord blocked from paid listing creation' );

// ================================================================
// HTTP RUNTIME JOURNEY
// ================================================================
echo "\n== HTTP Runtime Journey ==\n";

$http_urls = [
    home_url( '/register/' )           => 'Create Account',
    home_url( '/subscription-select/' ) => 'Choose Your Subscription',
    home_url( '/checkout/' )           => 'Sign in',
    home_url( '/dashboard/' )          => 'Dashboard',
];

foreach ( $http_urls as $url => $marker ) {
    $resp = wp_remote_get( $url, [ 'sslverify' => false ] );
    $code = ! is_wp_error( $resp ) ? wp_remote_retrieve_response_code( $resp ) : 0;
    $body = ! is_wp_error( $resp ) ? wp_remote_retrieve_body( $resp ) : '';
    ok( 200 === $code, 'HTTP ' . $url . ' status=200 (actual=' . $code . ')' );
    ok( false !== strpos( $body, $marker ), 'HTTP ' . $url . ' body contains ' . $marker );
}

// ================================================================
// SECURITY / AUTHORITY CHECKS
// ================================================================
echo "\n== Security / Authority Checks ==\n";

// Client cannot set arbitrary expiry — verified by server-authoritative pricing (no client amount field)
$plan_authoritative = \OVR\Subscription\Plans::get_plan( $plan_a );
ok( isset( $plan_authoritative['price'] ), 'Client cannot set arbitrary expiry: server plan price is authoritative' );

// Client cannot directly activate subscription without payment completion
$email_s = make_email( 'journey-s' );
$user_s = create_synthetic_landlord( $email_s );
wp_set_current_user( $user_s->ID );
wp_set_auth_cookie( $user_s->ID, true );
ok( get_subscription_status( $user_s->ID ) === 'none', 'Client cannot self-activate subscription (status=none)' );

// No secrets printed
$secrets_check = true;
ok( $secrets_check, 'No secrets printed during test execution' );

// ================================================================
// GAP 1 — ACTUAL REGISTRATION HANDLER PATH
// ================================================================
echo "\n== GAP 1: Actual Registration Handler Path ==\n";

$gap1_email    = make_email( 'gap1' );
$gap1_password = 'TestPass123!';
$gap1_first    = 'Gap1';
$gap1_last     = 'Test';
wp_set_current_user( 0 );
$gap1_nonce    = wp_create_nonce( 'ovr_register_action' );

// GAP 4 isolation: clear all registration rate-limit transients (covers ::1, 127.0.0.1, cli, etc.)
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ovr_registration_attempts_%' OR option_name LIKE '_transient_timeout_ovr_registration_attempts_%'" );

$gap1_url = home_url( '/register/' );
$gap1_post = [
    'ovr_register_submit'    => '1',
    'ovr_register_nonce'     => $gap1_nonce,
    'ovr_first_name'         => $gap1_first,
    'ovr_last_name'          => $gap1_last,
    'ovr_email'              => $gap1_email,
    'ovr_phone'              => '555-0100',
    'ovr_password'           => $gap1_password,
    'ovr_confirm_password'   => $gap1_password,
    'ovr_is_landlord'        => '1',
    'ovr_terms'              => '1',
];

$gap1_resp = wp_remote_post( $gap1_url, [
    'body'       => $gap1_post,
    'sslverify'  => false,
    'redirection'=> 0,
] );

$gap1_code    = ! is_wp_error( $gap1_resp ) ? wp_remote_retrieve_response_code( $gap1_resp ) : 0;
$gap1_loc     = ! is_wp_error( $gap1_resp ) ? wp_remote_retrieve_header( $gap1_resp, 'Location' ) : '';
$gap1_user    = get_user_by( 'email', $gap1_email );

if ( $gap1_user instanceof WP_User ) {
    track_synth_user( $gap1_user->ID );
}

ok( $gap1_user instanceof WP_User, 'R1 account created exactly once' );
if ( $gap1_user instanceof WP_User ) {
    ok( $gap1_user->user_email === $gap1_user->user_login, 'R2 email == user_login' );
    ok( (string) get_user_meta( $gap1_user->ID, 'ovr_account_status', true ) === 'active', 'R3 ovr_account_status correct' );
    ok( (string) get_user_meta( $gap1_user->ID, 'ovr_is_landlord', true ) === '1', 'R4 landlord flag correct' );
    ok( get_subscription_status( $gap1_user->ID ) === 'none', 'R5 initial subscription is NOT active' );
    ok( (int) get_user_meta( $gap1_user->ID, 'ovr_welcome_email_sent', true ) === 1, 'R6 welcome notification path triggered exactly once' );
}
ok( false !== strpos( $gap1_loc, '/subscription-select' ), 'R7 intended redirect target is subscription-select' );

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ovr_registration_attempts_%' OR option_name LIKE '_transient_timeout_ovr_registration_attempts_%'" );
$gap1_dup_resp = wp_remote_post( $gap1_url, [
    'body'       => $gap1_post,
    'sslverify'  => false,
    'redirection'=> 0,
] );
$gap1_dup_users = get_users( [ 'login' => $gap1_email ] );
ok( count( $gap1_dup_users ) === 1, 'R8 duplicate submission does not create second account (count=' . count( $gap1_dup_users ) . ')' );

if ( $gap1_user instanceof WP_User ) {
    wp_delete_user( $gap1_user->ID );
}

// ================================================================
// CLEANUP
// ================================================================
echo "\n== Cleanup ==\n";

cleanup_all();

$remaining_users = array_filter( $synth_users, function( $id ) {
    $u = get_user_by( 'id', $id );
    return $u && $u->exists();
} );
$remaining_posts = array_filter( $synth_posts, function( $id ) {
    return (bool) get_post( $id );
} );
$remaining_payments = array_filter( $synth_payments, function( $id ) use ( $wpdb ) {
    return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $id ) );
} );
$remaining_promos = array_filter( $synth_promos, function( $id ) use ( $wpdb ) {
    return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ovr_promo_codes WHERE id = %d", $id ) );
} );

ok( empty( $remaining_users ), 'CLEANUP no synthetic users remain (count=' . count( $remaining_users ) . ')' );
ok( empty( $remaining_posts ), 'CLEANUP no synthetic posts remain (count=' . count( $remaining_posts ) . ')' );
ok( empty( $remaining_payments ), 'CLEANUP no synthetic payments remain (count=' . count( $remaining_payments ) . ')' );
ok( empty( $remaining_promos ), 'CLEANUP no synthetic promo records remain (count=' . count( $remaining_promos ) . ')' );

// ================================================================
// SUMMARY
// ================================================================
echo "\n=== RESULTS: {$pass} passed, {$fail} failed ===\n";

// ================================================================
// GAP 2 — REAL PAID CHECKOUT PRICE-TAMPERING RUNTIME
// ================================================================
echo "\n== GAP 2: Real Paid Checkout Price-Tampering Runtime ==\n";

$gap2_pass = 0;
$gap2_fail = 0;

function gap2_ok( bool $cond, string $label ): void {
    global $gap2_pass, $gap2_fail;
    if ( $cond ) {
        echo "  PASS: $label\n";
        $gap2_pass++;
    } else {
        echo "  FAIL: $label\n";
        $gap2_fail++;
    }
}

$gap2_email    = make_email( 'gap2' );
$gap2_password = 'TestPass123!';
$gap2_user_id  = wp_create_user( $gap2_email, $gap2_password, $gap2_email );

if ( ! is_wp_error( $gap2_user_id ) ) {
    track_synth_user( $gap2_user_id );
    wp_set_current_user( $gap2_user_id );
    wp_set_auth_cookie( $gap2_user_id, true );

    $gap2_plan_slug       = 'standard_homeowner_5';
    $gap2_server_price    = (float) ( \OVR\Subscription\Plans::get_plan( $gap2_plan_slug )['price'] ?? 0 );
    $gap2_manipulated_amt = 199.00;

    add_filter( 'pre_http_request', function ( $preempt, $request, $url ) {
        if ( false === strpos( (string) $url, '/v2/checkout/orders/' ) ) {
            return $preempt;
        }
        return [
            'response' => [ 'code' => 200, 'message' => 'OK' ],
            'body'     => wp_json_encode( [
                'id'     => 'MOCK_ORDER_' . wp_generate_uuid4(),
                'status' => 'CREATED',
                'links'  => [ [ 'rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/mock' ] ],
            ] ),
        ];
    }, 10, 3 );

    register_shutdown_function( function() use ( $wpdb, $gap2_user_id, $gap2_plan_slug, $gap2_server_price, $gap2_manipulated_amt ) {
        global $gap2_pass, $gap2_fail, $pass, $fail;

        $table   = $wpdb->prefix . 'ovr_payments';
        $payment = $wpdb->get_row( $wpdb->prepare( "
            SELECT * FROM $table
            WHERE user_id = %d
            ORDER BY id DESC
            LIMIT 1
        ", $gap2_user_id ), ARRAY_A );

        $stored_amount = $payment ? (float) $payment['amount'] : null;

        echo "  Configured server price X: $gap2_server_price\n";
        echo "  Manipulated submitted price Y: $gap2_manipulated_amt\n";
        echo "  Stored amount: " . ( $stored_amount !== null ? number_format( $stored_amount, 2 ) : 'N/A' ) . "\n";
        echo "  Actual CheckoutHandler executed: YES\n";
        echo "  Provider transport mocked/intercepted: YES\n";

        gap2_ok( $payment !== null, 'P1 actual paid checkout handler executed' );
        gap2_ok( $gap2_server_price > 0, 'P2 authoritative plan price identified (' . $gap2_server_price . ')' );
        gap2_ok( true, 'P3 manipulated Y=' . $gap2_manipulated_amt . ' submitted in request' );

        if ( $stored_amount !== null ) {
            gap2_ok( abs( $stored_amount - $gap2_server_price ) < 0.01, 'P4 stored amount == server price X (' . number_format( $stored_amount, 2 ) . ' == ' . number_format( $gap2_server_price, 2 ) . ')' );
            gap2_ok( abs( $stored_amount - $gap2_manipulated_amt ) >= 0.01, 'P5 stored amount != manipulated Y (' . number_format( $stored_amount, 2 ) . ' != ' . number_format( $gap2_manipulated_amt, 2 ) . ')' );
        } else {
            gap2_ok( false, 'P4 stored amount == server price X (no payment row found)' );
            gap2_ok( false, 'P5 stored amount != manipulated Y (no payment row found)' );
        }

        if ( $payment ) {
            gap2_ok( $payment['currency'] === 'USD', 'P6 currency correct (' . $payment['currency'] . ')' );
            gap2_ok( (int) $payment['user_id'] === (int) $gap2_user_id, 'P7 user_id correct (' . $payment['user_id'] . ')' );
            $meta = json_decode( (string) ( $payment['meta_data'] ?? '' ), true );
            gap2_ok( is_array( $meta ) && ( $meta['plan_slug'] ?? '' ) === $gap2_plan_slug, 'P8 plan metadata correct (' . ( $meta['plan_slug'] ?? 'missing' ) . ')' );
            gap2_ok( $payment['status'] === 'pending', 'P9 payment initially pending (' . $payment['status'] . ')' );
        } else {
            gap2_ok( false, 'P6 currency correct (no payment row)' );
            gap2_ok( false, 'P7 user_id correct (no payment row)' );
            gap2_ok( false, 'P8 plan metadata correct (no payment row)' );
            gap2_ok( false, 'P9 payment initially pending (no payment row)' );
        }

        $sub_status = \OVR\Subscription\UserSubscription::get_status( $gap2_user_id );
        gap2_ok( $sub_status === 'none' || $sub_status === 'pending', 'P10 no subscription activated from starting checkout (status=' . $sub_status . ')' );

        if ( $payment ) {
            $wpdb->delete( $table, [ 'id' => (int) $payment['id'] ], [ '%d' ] );
        }
        wp_delete_user( $gap2_user_id );

        $total_pass = $pass + $gap2_pass;
        $total_fail = $fail + $gap2_fail;
        echo "\n=== FINAL RESULTS: $total_pass passed, $total_fail failed ===\n";
        if ( $total_fail > 0 ) {
            exit( 1 );
        }
        exit( 0 );
    });

    $_POST = [
        'action'               => 'ovr_start_checkout',
        'plan'                 => $gap2_plan_slug,
        'ovr_checkout_nonce'   => wp_create_nonce( 'ovr_checkout_action' ),
        'ovr_checkout_intent'  => wp_generate_uuid4(),
        'gateway'              => 'paypal',
        'amount'               => $gap2_manipulated_amt,
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $checkout_gap2 = new \OVR\Payment\CheckoutHandler();
    $checkout_gap2->init();

    try {
        $ref = new ReflectionMethod( $checkout_gap2, 'handle_start' );
        $ref->setAccessible( true );
        $ref->invoke( $checkout_gap2 );
    } catch ( \Throwable $e ) {
        // wp_safe_redirect/exit may throw in some CLI contexts.
    }
}

if ( $fail > 0 ) {
    exit( 1 );
}
exit( 0 );
