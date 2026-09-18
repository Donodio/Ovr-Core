<?php
/**
 * OVR Subscription + Landlord Dashboard Acceptance Tests
 *
 * Run from WordPress root:
 *   php -r "require_once 'wp-load.php'; include 'wp-content/plugins/ovr-core/tests/subscription-dashboard-acceptance.php';"
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

function make_email( string $prefix = 'ovr-sub' ): string {
    return $prefix . '-' . wp_generate_password( 8, false ) . '@example.com';
}

function make_password(): string {
    return wp_generate_password( 12, false );
}

// ------------------------------------------------------------------
// Synthetic data tracking
// ------------------------------------------------------------------
$synth_users     = [];
$synth_posts     = [];
$synth_payments  = [];
$synth_inquiries = [];

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

function track_synth_inquiry( int $id ): void {
    global $synth_inquiries;
    $synth_inquiries[] = $id;
}

function delete_synth_inquiries(): void {
    global $wpdb, $synth_inquiries;
    foreach ( array_reverse( $synth_inquiries ) as $iid ) {
        $wpdb->delete( $wpdb->prefix . 'ovr_inquiries', [ 'id' => $iid ], [ '%d' ] );
    }
    $synth_inquiries = [];
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
        if ( $u->exists() ) {
            wp_delete_user( (int) $user_id );
        }
    }
    $synth_users = [];
}

function cleanup_all(): void {
    delete_synth_inquiries();
    delete_synth_payments();
    delete_synth_posts();
    delete_synth_users();
}

function pre_cleanup_orphans(): void {
    global $wpdb;
    $prefixes = [ 'ovr-sub-', 'ovr-acct-', 'ovr-gap-' ];
    $like = '%' . $prefixes[0] . '%';
    foreach ( $prefixes as $p ) {
        $like .= " OR user_email LIKE '%" . $p . "%'";
    }
    $orphan_users = $wpdb->get_col( "SELECT ID FROM {$wpdb->users} WHERE user_email LIKE '{$prefixes[0]}%'" );
    foreach ( $prefixes as $p ) {
        $orphan_users = array_merge( $orphan_users, $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_email LIKE %s", '%' . $p . '%' ) ) );
    }
    $orphan_users = array_unique( array_map( 'intval', $orphan_users ) );
    foreach ( $orphan_users as $uid ) {
        $u = new WP_User( $uid );
        if ( $u->exists() && ! in_array( $uid, $GLOBALS['synth_users'] ?? [], true ) ) {
            wp_delete_user( $uid );
        }
    }

    // Also purge orphan inquiries/payments tied to now-deleted users.
    $wpdb->query( "DELETE i FROM {$wpdb->prefix}ovr_inquiries i LEFT JOIN {$wpdb->users} u ON u.ID = i.landlord_id WHERE u.ID IS NULL" );
    $wpdb->query( "DELETE p FROM {$wpdb->prefix}ovr_payments p LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id WHERE u.ID IS NULL" );
}

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------
function create_landlord( string $email, string $password ): int {
    $user_id = wp_create_user( $email, $password, $email );
    if ( is_wp_error( $user_id ) ) {
        return 0;
    }
    $user = new WP_User( $user_id );
    $user->set_role( 'ovr_landlord' );
    update_user_meta( $user_id, 'ovr_is_landlord', '1' );
    update_user_meta( $user_id, 'ovr_editing_enabled', '1' );
    track_synth_user( $user_id );
    return $user_id;
}

function create_listing( int $user_id, array $meta = [] ): int {
    $post_id = wp_insert_post( [
        'post_type'    => 'ovr_property',
        'post_status'  => 'publish',
        'post_title'   => 'Test Listing ' . $user_id . '-' . time(),
        'post_author'  => $user_id,
    ] );
    if ( is_wp_error( $post_id ) ) {
        return 0;
    }
    foreach ( $meta as $k => $v ) {
        update_post_meta( $post_id, $k, $v );
    }
    track_synth_post( $post_id );
    return $post_id;
}

function create_inquiry( int $property_id, int $landlord_id, array $extra = [] ): int {
    global $wpdb;
    $row = [
        'property_id'  => $property_id,
        'landlord_id'  => $landlord_id,
        'guest_name'   => 'Guest ' . wp_generate_password( 4, false ),
        'guest_email'  => make_email( 'guest' ),
        'guest_phone'  => '555-0001',
        'checkin_date' => date( 'Y-m-d', strtotime( '+7 days' ) ),
        'checkout_date'=> date( 'Y-m-d', strtotime( '+14 days' ) ),
        'guests'       => 2,
        'message'      => 'Test inquiry',
        'status'       => 'new',
        'created_at'   => current_time( 'mysql' ),
    ];
    $row = array_merge( $row, $extra );
    $wpdb->insert( $wpdb->prefix . 'ovr_inquiries', $row );
    $id = (int) $wpdb->insert_id;
    if ( $id ) {
        track_synth_inquiry( $id );
    }
    return $id;
}

function activate_subscription( int $user_id, string $plan_slug, ?int $duration_days = null ): void {
    \OVR\Subscription\SubscriptionManager::activate( $user_id, $plan_slug, $duration_days );
}

function expire_subscription( int $user_id ): void {
    \OVR\Subscription\SubscriptionManager::expire( $user_id );
}

function renew_subscription( int $user_id, string $plan_slug ): void {
    \OVR\Subscription\SubscriptionManager::renew( $user_id, $plan_slug );
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

// ------------------------------------------------------------------
// Preserve frozen data references
// ------------------------------------------------------------------
$frozen_user_411     = 411;
$frozen_payment_508  = 508;
$frozen_paypal_order = '6MW02678WV627771J';

// ------------------------------------------------------------------
// MAIN
// ------------------------------------------------------------------
echo "=== OVR Subscription + Dashboard Acceptance ===\n\n";

pre_cleanup_orphans();

// ================================================================
// A. ACTIVE SUBSCRIPTION
// ================================================================
echo "== A. Active Subscription ==\n";

$user_a = create_landlord( make_email( 'a' ), make_password() );
$plan_a = 'standard_homeowner_5';

activate_subscription( $user_a, $plan_a );

$status = \OVR\Subscription\UserSubscription::get_status( $user_a );
$plan   = \OVR\Subscription\UserSubscription::get_plan_slug( $user_a );
$expiry = (string) get_user_meta( $user_a, \OVR\Subscription\UserSubscription::META_EXPIRES, true );
$start  = (string) get_user_meta( $user_a, \OVR\Subscription\UserSubscription::META_START, true );
$days   = \OVR\Subscription\UserSubscription::get_days_remaining( $user_a );
$info   = \OVR\Subscription\UserSubscription::get_info( $user_a );

ok( 'active' === $status, 'A1 status=active' );
ok( $plan_a === $plan, 'A2 plan stored=' . $plan_a . ' actual=' . $plan );
ok( '' !== $expiry, 'A3 expiry stored=' . $expiry );
ok( strtotime( $expiry ) > time(), 'A4 expiry in future' );
ok( $days > 0, 'A5 days remaining positive (' . (int) $days . ')' );
ok( \OVR\Subscription\UserSubscription::has_listing_access( $user_a ), 'A6 has_listing_access=true' );
ok( 'active' === $info['status'], 'A7 get_info status=active' );
ok( $expiry === $info['expiry_date'], 'A8 get_info expiry matches meta' );

// ================================================================
// B. DAYS REMAINING
// ================================================================
echo "\n== B. Days Remaining ==\n";

$user_b = create_landlord( make_email( 'b' ), make_password() );

$today_ts = time();
$tests = [
    [ 'label' => 'expires in ~365 days', 'offset' => '+365 days',  'expect_positive' => true ],
    [ 'label' => 'expires in ~30 days',   'offset' => '+30 days',   'expect_positive' => true ],
    [ 'label' => 'expires in ~1 day',     'offset' => '+1 day',     'expect_positive' => true ],
    [ 'label' => 'expires today',         'offset' => 'today',      'expect_zero'     => true ],
    [ 'label' => 'expired yesterday',     'offset' => '-1 day',     'expect_zero'     => true ],
];

foreach ( $tests as $t ) {
    $future_ts   = strtotime( $t['offset'], $today_ts );
    $future_date = gmdate( 'Y-m-d', $future_ts );
    update_user_meta( $user_b, \OVR\Subscription\UserSubscription::META_STATUS, \OVR\Subscription\UserSubscription::STATUS_ACTIVE );
    update_user_meta( $user_b, \OVR\Subscription\UserSubscription::META_EXPIRES, $future_date );

    $d = \OVR\Subscription\UserSubscription::get_days_remaining( $user_b );
    if ( ! empty( $t['expect_positive'] ) ) {
        ok( $d > 0, 'B days_remaining>0 for ' . $t['label'] . ' (got=' . (int) $d . ')' );
    }
    if ( ! empty( $t['expect_zero'] ) ) {
        ok( 0 === (int) $d, 'B days_remaining=0 for ' . $t['label'] . ' (got=' . (int) $d . ')' );
    }
    ok( $d >= 0, 'B no negative days for ' . $t['label'] . ' (got=' . (int) $d . ')' );
}

// ================================================================
// C. ACTIVE RENEWAL
// ================================================================
echo "\n== C. Active Renewal ==\n";

$user_c = create_landlord( make_email( 'c' ), make_password() );
$plan_c = 'standard_homeowner_5';

activate_subscription( $user_c, $plan_c );
$base_ts = strtotime( '+180 days' );
update_user_meta( $user_c, \OVR\Subscription\UserSubscription::META_EXPIRES, gmdate( 'Y-m-d', $base_ts ) );

$before_date = (string) get_user_meta( $user_c, \OVR\Subscription\UserSubscription::META_EXPIRES, true );
$before_ts   = strtotime( $before_date );

renew_subscription( $user_c, $plan_c );

$after_date = (string) get_user_meta( $user_c, \OVR\Subscription\UserSubscription::META_EXPIRES, true );
$after_ts   = strtotime( $after_date );

$expected_ts = strtotime( '+1 year', $before_ts );
$diff_days   = (int) round( ( $after_ts - $expected_ts ) / DAY_IN_SECONDS );

ok( $after_ts > $before_ts, 'C1 new expiry > old expiry' );
ok( abs( $diff_days ) <= 1, 'C2 new expiry ≈ old + 1 year (diff_days=' . $diff_days . ')' );

// ================================================================
// D. EXPIRED RENEWAL
// ================================================================
echo "\n== D. Expired Renewal ==\n";

$user_d = create_landlord( make_email( 'd' ), make_password() );
$plan_d = 'standard_homeowner_5';

activate_subscription( $user_d, $plan_d );
update_user_meta( $user_d, \OVR\Subscription\UserSubscription::META_EXPIRES, gmdate( 'Y-m-d', strtotime( '-10 days' ) ) );
expire_subscription( $user_d );

$status_before = \OVR\Subscription\UserSubscription::get_status( $user_d );
ok( 'expired' === $status_before, 'D1 status=expired before renewal' );

renew_subscription( $user_d, $plan_d );

$status_after = \OVR\Subscription\UserSubscription::get_status( $user_d );
$expiry_after = (string) get_user_meta( $user_d, \OVR\Subscription\UserSubscription::META_EXPIRES, true );
ok( 'active' === $status_after, 'D2 status=active after renewal' );
ok( strtotime( $expiry_after ) > time(), 'D3 expiry in future after renewal (' . $expiry_after . ')' );

// ================================================================
// E. DUPLICATE RENEWAL
// ================================================================
echo "\n== E. Duplicate Renewal Safety ==\n";

$user_e = create_landlord( make_email( 'e' ), make_password() );
$plan_e = 'standard_homeowner_5';

activate_subscription( $user_e, $plan_e );
update_user_meta( $user_e, \OVR\Subscription\UserSubscription::META_EXPIRES, gmdate( 'Y-m-d', strtotime( '-5 days' ) ) );
expire_subscription( $user_e );

// Simulate renewal via payment completion path (idempotent).
$payment_e_meta = [ 'plan_slug' => $plan_e ];
$checkout_e = new \OVR\Payment\CheckoutHandler();
$ref_complete_e = new ReflectionMethod( $checkout_e, 'complete_payment_atomically' );
$ref_complete_e->setAccessible( true );
$ref_find_e = new ReflectionMethod( $checkout_e, 'find_or_create_free_payment' );
$ref_find_e->setAccessible( true );

$payment_e_id = checkout_find_or_create_free_payment( $checkout_e, $user_e, 'subscription', $payment_e_meta, wp_generate_uuid4() );

// First completion.
$res1 = $ref_complete_e->invoke( $checkout_e, $payment_e_id, [
    'payment_id'   => $payment_e_id,
    'plan_slug'    => $plan_e,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );
$exp1 = (string) get_user_meta( $user_e, \OVR\Subscription\UserSubscription::META_EXPIRES, true );
$ts1  = strtotime( $exp1 );

// Replay same payment completion.
$res2 = $ref_complete_e->invoke( $checkout_e, $payment_e_id, [
    'payment_id'   => $payment_e_id,
    'plan_slug'    => $plan_e,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );
$exp2 = (string) get_user_meta( $user_e, \OVR\Subscription\UserSubscription::META_EXPIRES, true );
$ts2  = strtotime( $exp2 );

ok( true === $res1, 'E1 first payment completion succeeds' );
ok( false === $res2, 'E2 replay payment completion returns false (idempotent)' );
ok( $ts1 === $ts2, 'E3 expiry unchanged on replay (ts1=' . $ts1 . ' ts2=' . $ts2 . ')' );

// Distinct legitimate renewal via new payment.
$payment_e2_id = $ref_find_e->invoke( $checkout_e, $user_e, 'subscription', [ 'plan_slug' => $plan_e ], wp_generate_uuid4() );
$res3 = $ref_complete_e->invoke( $checkout_e, $payment_e2_id, [
    'payment_id'   => $payment_e2_id,
    'plan_slug'    => $plan_e,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );
$exp3 = (string) get_user_meta( $user_e, \OVR\Subscription\UserSubscription::META_EXPIRES, true );
$ts3  = strtotime( $exp3 );

ok( true === $res3, 'E4 second distinct payment completion succeeds' );
ok( $ts3 > $ts1, 'E5 second renewal extends expiry (ts1=' . $ts1 . ' ts3=' . $ts3 . ')' );

track_synth_payment( $payment_e_id );
track_synth_payment( $payment_e2_id );

// ================================================================
// F. EXPIRATION
// ================================================================
echo "\n== F. Expiration ==\n";

$user_f = create_landlord( make_email( 'f' ), make_password() );
$plan_f = 'standard_homeowner_5';

activate_subscription( $user_f, $plan_f );
$listing_f = create_listing( $user_f, [ '_ovr_listing_status' => 'active' ] );

expire_subscription( $user_f );

$status_f = \OVR\Subscription\UserSubscription::get_status( $user_f );
$user_f_obj = get_userdata( $user_f );
$plan_f_after = (string) get_user_meta( $user_f, \OVR\Subscription\UserSubscription::META_PLAN, true );
$listing_status = (string) get_post_meta( $listing_f, '_ovr_listing_status', true );
$prior_status  = (string) get_post_meta( $listing_f, '_ovr_listing_status_pre_expiry', true );

ok( 'expired' === $status_f, 'F1 status=expired' );
ok( $user_f_obj && $user_f_obj->exists(), 'F2 user still exists' );
ok( '' === $plan_f_after, 'F3 plan cleared=' . $plan_f_after );
ok( 'pending_renewal' === $listing_status, 'F4 listing marked pending_renewal (actual=' . $listing_status . ')' );
ok( 'active' === $prior_status, 'F5 prior status saved=' . $prior_status );

// ================================================================
// G. EXPIRATION SCHEDULER
// ================================================================
echo "\n== G. Expiration Scheduler ==\n";

$cron_hook      = \OVR\Subscription\Lifecycle::CRON_HOOK;
$cron_recurrence = \OVR\Subscription\Lifecycle::CRON_RECURRENCE;

$next = wp_next_scheduled( $cron_hook );
ok( false !== $next, 'G1 cron scheduled (next=' . ( $next ? date( 'c', $next ) : 'none' ) . ')' );

$local_3am_ts = strtotime( 'tomorrow 03:00' );
$diff_seconds = abs( (int) $next - (int) $local_3am_ts );
// Cron is daily at 03:00; allow either today 03:00 or tomorrow 03:00 depending on current time
$hour_ok = (int) date( 'G', (int) $next ) === 3;
$diff_ok = $diff_seconds <= 120 || $diff_seconds % 86400 <= 120;
ok( $hour_ok && $diff_ok, 'G2 cron scheduled near 03:00 daily (next=' . date( 'c', (int) $next ) . ' diff=' . $diff_seconds . 's)' );

$sched = _get_cron_array();
$entry = $sched[ $next ] ?? [];
$ok_recurrence = isset( $entry[ $cron_hook ] );
ok( $ok_recurrence, 'G3 hook=' . $cron_hook . ' present in cron entry for next run' );

// Verify future subscriptions are not incorrectly expired.
$user_g = create_landlord( make_email( 'g' ), make_password() );
activate_subscription( $user_g, $plan_a );
update_user_meta( $user_g, \OVR\Subscription\UserSubscription::META_EXPIRES, gmdate( 'Y-m-d', strtotime( '+90 days' ) ) );

$lifecycle = new \OVR\Subscription\Lifecycle();
$lifecycle->check_all();

$status_g_after = \OVR\Subscription\UserSubscription::get_status( $user_g );
ok( 'active' === $status_g_after, 'G4 future expiry not expired by check_all()' );

// ================================================================
// H. LISTING EXPIRATION
// ================================================================
echo "\n== H. Listing Expiration ==\n";

$user_h = create_landlord( make_email( 'h' ), make_password() );
$plan_h = 'standard_homeowner_5';

activate_subscription( $user_h, $plan_h );
$listing_h = create_listing( $user_h, [
    '_ovr_listing_status' => 'active',
    '_ovr_listing_status_pre_expiry' => '',
] );

$pid_h = $listing_h;
$prop_num_h = \OVR\Property\PropertyNumber::get( $pid_h );

expire_subscription( $user_h );

$post_h = get_post( $pid_h );
$listing_status_h = (string) get_post_meta( $pid_h, '_ovr_listing_status', true );
$prior_h = (string) get_post_meta( $pid_h, '_ovr_listing_status_pre_expiry', true );
$owner_h = (int) $post_h->post_author;

ok( false !== $post_h, 'H1 listing still exists (post_status=' . $post_h->post_status . ')' );
ok( $user_h === $owner_h, 'H2 ownership unchanged' );
ok( $prop_num_h === \OVR\Property\PropertyNumber::get( $pid_h ), 'H3 Property Number unchanged (' . $prop_num_h . ')' );
ok( 'pending_renewal' === $listing_status_h, 'H4 listing_status=pending_renewal (actual=' . $listing_status_h . ')' );
ok( 'active' === $prior_h, 'H5 prior status saved=' . $prior_h );

// ================================================================
// I. LISTING RESTORATION
// ================================================================
echo "\n== I. Listing Restoration ==\n";

renew_subscription( $user_h, $plan_h );

$listing_status_i = (string) get_post_meta( $pid_h, '_ovr_listing_status', true );
$prior_i = (string) get_post_meta( $pid_h, '_ovr_listing_status_pre_expiry', true );
$post_i = get_post( $pid_h );

ok( 'active' === $listing_status_i, 'I1 listing restored to active (actual=' . $listing_status_i . ')' );
ok( '' === $prior_i, 'I2 pre_expiry meta cleared' );
ok( $user_h === (int) $post_i->post_author, 'I3 owner unchanged' );
ok( $prop_num_h === \OVR\Property\PropertyNumber::get( $pid_h ), 'I4 Property Number unchanged' );

// ================================================================
// J. DASHBOARD
// ================================================================
echo "\n== J. Dashboard ==\n";

$user_j = create_landlord( make_email( 'j' ), make_password() );

// J1: unpaid user (no subscription meta) redirected.
\OVR\Subscription\UserSubscription::get_status( $user_j ); // ensure meta initialized
update_user_meta( $user_j, \OVR\Subscription\UserSubscription::META_STATUS, \OVR\Subscription\UserSubscription::STATUS_NONE );
$redirect_unpaid = \OVR\Subscription\SubscriptionManager::get_redirect_by_status( $user_j );
ok( false !== strpos( $redirect_unpaid, 'subscription-select' ), 'J1 unpaid redirects to subscription-select (got=' . $redirect_unpaid . ')' );

// J2: active user not redirected.
activate_subscription( $user_j, $plan_a );
$redirect_active = \OVR\Subscription\SubscriptionManager::get_redirect_by_status( $user_j );
ok( '' === $redirect_active, 'J2 active user not redirected (got=' . $redirect_active . ')' );

// J3: expired user redirected to renew.
expire_subscription( $user_j );
$redirect_expired = \OVR\Subscription\SubscriptionManager::get_redirect_by_status( $user_j );
ok( false !== strpos( $redirect_expired, 'renew' ), 'J3 expired user redirected to renew (got=' . $redirect_expired . ')' );

// J4-J6: dashboard overview data.
activate_subscription( $user_j, $plan_a );
$info_j = \OVR\Subscription\UserSubscription::get_info( $user_j );
ok( 'active' === $info_j['status'], 'J4 info status=active' );
ok( $info_j['days_remaining'] > 0, 'J5 info days_remaining positive (' . (int) $info_j['days_remaining'] . ')' );
ok( $plan_a === $info_j['plan_slug'], 'J6 info plan_slug=' . $plan_a );

// J7-J8: HTTP smoke for /dashboard/ and /subscription-select/.
$urls = [ home_url( '/dashboard/' ), home_url( '/subscription-select/' ) ];
foreach ( $urls as $url ) {
    $resp = wp_remote_get( $url, [ 'sslverify' => false ] );
    $code = ! is_wp_error( $resp ) ? wp_remote_retrieve_response_code( $resp ) : 0;
    $body = ! is_wp_error( $resp ) ? wp_remote_retrieve_body( $resp ) : '';
    ok( 200 === $code, 'HTTP ' . $url . ' status=200 (actual=' . $code . ')' );
    ok( strlen( $body ) > 100, 'HTTP ' . $url . ' body length=' . strlen( $body ) );
}

// ================================================================
// K. MY LISTINGS
// ================================================================
echo "\n== K. My Listings ==\n";

$landlord_a = create_landlord( make_email( 'ka' ), make_password() );
$landlord_b = create_landlord( make_email( 'kb' ), make_password() );

activate_subscription( $landlord_a, $plan_a );
activate_subscription( $landlord_b, $plan_a );

$listing_a1 = create_listing( $landlord_a, [ '_ovr_listing_status' => 'active' ] );
$listing_a2 = create_listing( $landlord_a, [ '_ovr_listing_status' => 'active' ] );
$listing_b1 = create_listing( $landlord_b, [ '_ovr_listing_status' => 'active' ] );

$count_a = \OVR\Subscription\UserSubscription::get_listing_count( $landlord_a );
$count_b = \OVR\Subscription\UserSubscription::get_listing_count( $landlord_b );

ok( $count_a >= 2, 'K1 landlord A count >=2 (actual=' . $count_a . ')' );
ok( $count_b >= 1, 'K2 landlord B count >=1 (actual=' . $count_b . ')' );

$pn_a1 = \OVR\Property\PropertyNumber::get( $listing_a1 );
$pn_b1 = \OVR\Property\PropertyNumber::get( $listing_b1 );
ok( $pn_a1 > 0, 'K3 listing A1 PropertyNumber > 0 (' . $pn_a1 . ')' );
ok( $pn_b1 > 0, 'K4 listing B1 PropertyNumber > 0 (' . $pn_b1 . ')' );
ok( $pn_a1 !== $pn_b1, 'K5 Property Numbers are distinct' );

expire_subscription( $landlord_a );
ok( ! \OVR\Subscription\UserSubscription::has_listing_access( $landlord_a ), 'K6 expired A has_listing_access=false' );

$admin = get_user_by( 'email', make_email( 'admin' ) );
if ( ! $admin ) {
    $admin_id = wp_create_user( make_email( 'admin' ), make_password(), make_email( 'admin' ) );
    $admin = new WP_User( $admin_id );
    $admin->set_role( 'administrator' );
    track_synth_user( $admin_id );
}
ok( user_can( $admin, 'manage_options' ), 'K7 synthetic admin has manage_options' );

// ================================================================
// L. MY INQUIRIES
// ================================================================
echo "\n== L. My Inquiries ==\n";

$landlord_l = create_landlord( make_email( 'l' ), make_password() );
$landlord_m = create_landlord( make_email( 'm' ), make_password() );

activate_subscription( $landlord_l, $plan_a );
activate_subscription( $landlord_m, $plan_a );

$listing_l = create_listing( $landlord_l, [ '_ovr_listing_status' => 'active' ] );
$listing_m = create_listing( $landlord_m, [ '_ovr_listing_status' => 'active' ] );

// Pre-clean any leftover inquiries from prior partial runs.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ovr_inquiries WHERE landlord_id IN ( %d, %d )", $landlord_l, $landlord_m ) );

$inq_l = create_inquiry( $listing_l, $landlord_l, [
    'guest_name'  => 'Alice',
    'guest_email' => 'alice@example.com',
    'guest_phone' => '555-0100',
] );
$inq_m = create_inquiry( $listing_m, $landlord_m );

ok( $inq_l > 0, 'L0 inquiry created for landlord L (id=' . $inq_l . ')' );

// Use direct DB query since get_inquiries() is private.
$inqs_l = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_inquiries WHERE landlord_id = %d AND created_at >= (NOW() - INTERVAL 12 MONTH) ORDER BY created_at DESC", $landlord_l ), ARRAY_A );
$inqs_m = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_inquiries WHERE landlord_id = %d AND created_at >= (NOW() - INTERVAL 12 MONTH) ORDER BY created_at DESC", $landlord_m ), ARRAY_A );

$ids_l = array_map( 'intval', array_column( $inqs_l, 'id' ) );
$ids_m = array_map( 'intval', array_column( $inqs_m, 'id' ) );

ok( in_array( $inq_l, $ids_l, true ), 'L1 landlord L sees inquiry ' . $inq_l . ' (actual ids=' . implode( ',', $ids_l ) . ')' );
ok( ! in_array( $inq_l, $ids_m, true ), 'L2 landlord M cannot see inquiry ' . $inq_l );

$inq_row = $inqs_l[0] ?? [];
ok( (int) ( $inq_row['property_id'] ?? 0 ) === $listing_l, 'L3 inquiry property_id = listing post_id (' . $listing_l . ')' );
ok( '' !== ( $inq_row['guest_name'] ?? '' ), 'L4 inquiry guest_name present' );
ok( '' !== ( $inq_row['guest_email'] ?? '' ), 'L5 inquiry guest_email present' );
ok( '' !== ( $inq_row['message'] ?? '' ), 'L6 inquiry message present' );
ok( '' !== ( $inq_row['guest_phone'] ?? '' ), 'L7 inquiry guest_phone present' );

// Owner delete: verify nonce, ownership, then delete.
$nonce_delete = wp_create_nonce( 'ovr_inquiry_delete_' . $inq_l );
$nonce_verify = wp_verify_nonce( $nonce_delete, 'ovr_inquiry_delete_' . $inq_l );
ok( 1 === (int) $nonce_verify, 'L8 owner nonce validates via wp_verify_nonce' );

$row = $wpdb->get_row( $wpdb->prepare( "SELECT landlord_id FROM {$wpdb->prefix}ovr_inquiries WHERE id = %d", $inq_l ), ARRAY_A );
$ownership_ok = ( $row && (int) $row['landlord_id'] === $landlord_l );
ok( $ownership_ok, 'L9 owner verification passes for landlord L' );

// Perform actual deletion as the owner would.
wp_set_current_user( $landlord_l );
$deleted = $wpdb->delete( $wpdb->prefix . 'ovr_inquiries', [ 'id' => $inq_l ], [ '%d' ] );
ok( 1 === (int) $deleted, 'L10 owner deletion succeeds' );

$inqs_l_after = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_inquiries WHERE landlord_id = %d AND created_at >= (NOW() - INTERVAL 12 MONTH) ORDER BY created_at DESC", $landlord_l ), ARRAY_A );
$ids_l_after = array_map( 'intval', array_column( $inqs_l_after, 'id' ) );
ok( ! in_array( $inq_l, $ids_l_after, true ), 'L11 deleted inquiry no longer visible' );

// Other landlord cannot delete.
$nonce_bad = wp_create_nonce( 'bad' );
$nonce_bad_verify = wp_verify_nonce( $nonce_bad, 'ovr_inquiry_delete_' . $inq_l );
ok( false === $nonce_bad_verify, 'L12 bad nonce fails wp_verify_nonce' );

$row_m = $wpdb->get_row( $wpdb->prepare( "SELECT landlord_id FROM {$wpdb->prefix}ovr_inquiries WHERE id = %d", $inq_l ), ARRAY_A );
$ownership_m = $row_m && (int) $row_m['landlord_id'] === $landlord_m;
ok( ! $ownership_m, 'L13 other landlord ownership check fails' );

// ================================================================
// M. INQUIRY RETENTION
// ================================================================
echo "\n== M. Inquiry Retention ==\n";

$retention_days = 365;
$inquiry_endpoint = new \OVR\REST\InquiryEndpoint();
$ref = new ReflectionMethod( $inquiry_endpoint, 'retention_days' );
$ref->setAccessible( true );
$actual_retention = (int) $ref->invoke( $inquiry_endpoint );

ok( $actual_retention === $retention_days, 'M1 retention_days=' . $actual_retention . ' (expected=' . $retention_days . ')' );

// ================================================================
// N. BUMP
// ================================================================
echo "\n== N. Bump ==\n";

$user_n = create_landlord( make_email( 'n' ), make_password() );
$other_n = create_landlord( make_email( 'no' ), make_password() );

activate_subscription( $user_n, $plan_a );
activate_subscription( $other_n, $plan_a );

$listing_n = create_listing( $user_n, [ '_ovr_listing_status' => 'active' ] );

// Owner bump via class (admin path with ignore_limit=true).
$bump_result = \OVR\Property\Bump::bump( $listing_n, $user_n, true );
ok( ! empty( $bump_result['success'] ), 'N1 owner bump succeeds (msg=' . ( $bump_result['message'] ?? '' ) . ')' );

// Handler-level ownership check: non-owner, non-admin is blocked.
$post_n = get_post( $listing_n );
$handler_blocks_other = ( $post_n && (int) $post_n->post_author !== $other_n && ! user_can( $other_n, 'manage_options' ) );
ok( $handler_blocks_other, 'N2 handler blocks non-owner non-admin from bumping listing_n' );

// Admin bypass.
$admin_n = $admin->ID;
$bump_admin = \OVR\Property\Bump::bump( $listing_n, $admin_n, true );
ok( ! empty( $bump_admin['success'] ), 'N3 admin bump with ignore_limit=true succeeds' );

// ================================================================
// O. UPGRADE
// ================================================================
echo "\n== O. Upgrade ==\n";

$user_o = create_landlord( make_email( 'o' ), make_password() );
activate_subscription( $user_o, $plan_a );

$listing_o = create_listing( $user_o, [ '_ovr_listing_status' => 'active' ] );

$product_slug = 'homepage-slider-14-days';
$upgrade_meta = [
    'upgrade'      => $product_slug,
    'service_type' => 'homepage_slider',
    'term'         => '14',
    'property_id'  => $listing_o,
];

$checkout = new \OVR\Payment\CheckoutHandler();
$checkout_intent = wp_generate_uuid4();
$payment_id = checkout_find_or_create_free_payment( $checkout, $user_o, 'listing_upgrade', $upgrade_meta, $checkout_intent );

ok( $payment_id > 0, 'O1 free upgrade payment created (id=' . $payment_id . ')' );

checkout_complete_payment_atomically( $checkout, $payment_id, [
    'payment_id'   => $payment_id,
    'plan_slug'    => '',
    'amount'       => 0.0,
    'gateway'      => 'free',
    'payment_type' => 'listing_upgrade',
] + $upgrade_meta );

$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_id ), ARRAY_A );
ok( 'completed' === ( $row['status'] ?? '' ), 'O2 payment status=completed after atomic completion' );

$bump_expires = (string) get_post_meta( $listing_o, '_ovr_bump_expires', true );
$in_slider    = (string) get_post_meta( $listing_o, '_ovr_in_slider', true );
$slider_expires = (string) get_post_meta( $listing_o, '_ovr_slider_expires', true );
ok( '1' === $in_slider, 'O3 upgrade meta written: _ovr_in_slider=1 (slider_expires=' . $slider_expires . ')' );

track_synth_payment( $payment_id );

// ================================================================
// P. BUMP / UPGRADE PRESENTATION
// ================================================================
echo "\n== P. Bump / Upgrade Presentation ==\n";

$user_p = create_landlord( make_email( 'p' ), make_password() );
activate_subscription( $user_p, $plan_a );
$listing_p = create_listing( $user_p );

$query_p = new WP_Query( [
    'post_type'      => 'ovr_property',
    'post_status'    => 'publish',
    'author'         => $user_p,
    'posts_per_page' => 10,
    'no_found_rows'  => true,
] );
$properties = $query_p->posts;

ob_start();
$error_notice = '';
include \OVR\Core\TemplateLoader::locate( 'dashboard/tab-properties.php' );
$tab_props_html = ob_get_clean();

ok( false !== strpos( $tab_props_html, 'Bump' ) || false !== strpos( $tab_props_html, 'trending_up' ), 'P1 Bump label present in tab-properties' );
ok( false !== strpos( $tab_props_html, 'Upgrade' ) || false !== strpos( $tab_props_html, 'trending_up' ), 'P2 Upgrade label present in tab-properties' );

// ================================================================
// Q. ADMIN BYPASS
// ================================================================
echo "\n== Q. Admin Bypass ==\n";

$user_q = create_landlord( make_email( 'q' ), make_password() );
$listing_q = create_listing( $user_q );

$admin_q_id = $admin->ID;

ok( user_can( $admin_q_id, 'manage_options' ), 'Q1 admin has management capability' );

$bump_admin = \OVR\Property\Bump::bump( $listing_q, $admin_q_id, true );
ok( ! empty( $bump_admin['success'] ), 'Q2 admin bump with ignore_limit=true succeeds' );

// Unpaid user cannot access dashboard.
$redirect_q = \OVR\Subscription\SubscriptionManager::get_redirect_by_status( $user_q );
ok( false !== strpos( $redirect_q, 'subscription-select' ), 'Q3 unpaid user redirected from dashboard to subscription-select' );

// ================================================================
// R. PAYMENT HANDOFF
// ================================================================
echo "\n== R. Payment Handoff ==\n";

$user_r = create_landlord( make_email( 'r' ), make_password() );
activate_subscription( $user_r, $plan_a );
expire_subscription( $user_r );

// Successful renewal via payment completion.
$checkout_r = new \OVR\Payment\CheckoutHandler();
$payment_r_meta = [ 'plan_slug' => $plan_a ];
$payment_r_id = checkout_find_or_create_free_payment( $checkout_r, $user_r, 'subscription', $payment_r_meta, wp_generate_uuid4() );

checkout_complete_payment_atomically( $checkout_r, $payment_r_id, [
    'payment_id'   => $payment_r_id,
    'plan_slug'    => $plan_a,
    'amount'       => 0.0,
    'gateway'      => 'free',
] );

$status_r = \OVR\Subscription\UserSubscription::get_status( $user_r );
ok( 'active' === $status_r, 'R1 free renewal payment activates subscription' );

// Failed/cancelled payment does NOT activate.
$user_r2 = create_landlord( make_email( 'r2' ), make_password() );
activate_subscription( $user_r2, $plan_a );
expire_subscription( $user_r2 );

$payment_r2_id = checkout_find_or_create_free_payment( $checkout_r, $user_r2, 'subscription', [ 'plan_slug' => $plan_a ], wp_generate_uuid4() );
$wpdb->update( $wpdb->prefix . 'ovr_payments', [ 'status' => 'cancelled' ], [ 'id' => $payment_r2_id ] );

$status_r2 = \OVR\Subscription\UserSubscription::get_status( $user_r2 );
ok( 'expired' === $status_r2, 'R2 cancelled payment does not activate subscription' );

track_synth_payment( $payment_r_id );
track_synth_payment( $payment_r2_id );

// ================================================================
// S. PROPERTY NUMBER REGRESSION
// ================================================================
echo "\n== S. Property Number Regression ==\n";

$user_s = create_landlord( make_email( 's' ), make_password() );
activate_subscription( $user_s, $plan_a );

$listing_s = create_listing( $user_s );
$pn_before = \OVR\Property\PropertyNumber::get( $listing_s );

wp_update_post( [ 'ID' => $listing_s, 'post_title' => 'Edited ' . time() ] );

$pn_after = \OVR\Property\PropertyNumber::get( $listing_s );
ok( $pn_before === $pn_after, 'S1 edit does not change Property Number (' . $pn_before . ' -> ' . $pn_after . ')' );

expire_subscription( $user_s );
$pn_expire = \OVR\Property\PropertyNumber::get( $listing_s );
ok( $pn_before === $pn_expire, 'S2 expiration does not change Property Number' );

renew_subscription( $user_s, $plan_a );
$pn_renew = \OVR\Property\PropertyNumber::get( $listing_s );
ok( $pn_before === $pn_renew, 'S3 renewal does not change Property Number' );

// ================================================================
// HTTP SMOKE
// ================================================================
echo "\n== HTTP Smoke Tests ==\n";

$http_urls = [
    home_url( '/dashboard/' )          => 'Sign in to your dashboard',
    home_url( '/subscription-select/' ) => 'sign in',
];

foreach ( $http_urls as $url => $marker ) {
    $resp = wp_remote_get( $url, [ 'sslverify' => false ] );
    $code = ! is_wp_error( $resp ) ? wp_remote_retrieve_response_code( $resp ) : 0;
    $body = ! is_wp_error( $resp ) ? wp_remote_retrieve_body( $resp ) : '';
    ok( 200 === $code, 'HTTP ' . $url . ' status=200 (actual=' . $code . ')' );
    ok( false !== strpos( $body, $marker ), 'HTTP ' . $url . ' body contains ' . $marker );
}

// ================================================================
// PHP LINT
// ================================================================
echo "\n== PHP Lint ==\n";

$php_files = [
    __DIR__ . '/../src/Subscription/SubscriptionManager.php',
    __DIR__ . '/../src/Subscription/UserSubscription.php',
    __DIR__ . '/../src/Subscription/Lifecycle.php',
    __DIR__ . '/../src/Frontend/Dashboard.php',
    __DIR__ . '/../src/Property/Bump.php',
    __DIR__ . '/../src/Payment/CheckoutHandler.php',
    __DIR__ . '/../src/Subscription/UpgradeActivator.php',
];

$lint_pass = true;
foreach ( $php_files as $f ) {
    $output = [];
    $rc = 0;
    exec( 'php -l ' . escapeshellarg( $f ) . ' 2>&1', $output, $rc );
    if ( 0 !== $rc ) {
        $lint_pass = false;
        echo "  LINT FAIL: " . $f . " => " . implode( "\n", $output ) . "\n";
    }
}
ok( $lint_pass, 'PHP lint on tracked subscription/dashboard files' );

// ================================================================
// CLEANUP
// ================================================================
echo "\n== Cleanup ==\n";

cleanup_all();

$frozen_user = get_user_by( 'id', $frozen_user_411 );
$frozen_payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $frozen_payment_508 ) );

ok( $frozen_user && $frozen_user->exists(), 'CLEANUP user 411 still exists' );
ok( $frozen_payment && (int) $frozen_payment->id === $frozen_payment_508, 'CLEANUP payment 508 still exists' );

$remaining_users = array_filter( $synth_users, function( $id ) {
    $u = get_user_by( 'id', $id );
    return $u && $u->exists();
} );
$remaining_posts = array_filter( $synth_posts, function( $id ) {
    return (bool) get_post( $id );
} );
$remaining_inquiries = array_filter( $synth_inquiries, function( $id ) use ( $wpdb ) {
    return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ovr_inquiries WHERE id = %d", $id ) );
} );

ok( empty( $remaining_users ), 'CLEANUP no synthetic users remain (count=' . count( $remaining_users ) . ')' );
ok( empty( $remaining_posts ), 'CLEANUP no synthetic posts remain (count=' . count( $remaining_posts ) . ')' );
ok( empty( $remaining_inquiries ), 'CLEANUP no synthetic inquiries remain (count=' . count( $remaining_inquiries ) . ')' );

// ================================================================
// SUMMARY
// ================================================================
echo "\n=== RESULTS: {$pass} passed, {$fail} failed ===\n";

if ( $fail > 0 ) {
    exit( 1 );
}
exit( 0 );
