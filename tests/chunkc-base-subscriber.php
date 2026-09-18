<?php
/**
 * Chunk C — Base Subscriber + Listing Pending Renewal Display
 *
 * Verifies:
 * - STATUS_NONE and STATUS_EXPIRED both present as "Base Subscriber"
 * - Active/Pending and Inactive/Pending display for expired listings
 * - 99/99/9999 never stored; never-activated uses no expiry date
 * - Renewal restores exact pre-expiry intent
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once '/Users/admin/Local Sites/our-village-rentals/app/public/wp-load.php';
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

use OVR\Subscription\UserSubscription;
use OVR\Subscription\SubscriptionManager;
use OVR\Property\PropertyQuery;

$pass = 0;
$fail = 0;

function ok( bool $cond, string $msg ): void {
    global $pass, $fail;
    if ( $cond ) {
        echo "  PASS: {$msg}\n";
        $pass++;
    } else {
        echo "  FAIL: {$msg}\n";
        $fail++;
    }
}

function create_landlord( string $email, string $password ): int {
    $uid = wp_create_user( $email, $password, $email );
    if ( is_wp_error( $uid ) ) {
        // Try to find existing user with this email
        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
            return (int) $existing->ID;
        }
        echo "  FAIL: Could not create landlord user: " . $uid->get_error_message() . "\n";
        return 0;
    }
    $user = new WP_User( $uid );
    $user->set_role( 'ovr_landlord' );
    return (int) $uid;
}

function make_email( string $suffix ): string {
    return "chunkc-{$suffix}@ovr-core.test";
}

function make_password(): string {
    return 'ChunkCpass!' . wp_generate_password( 8, false );
}

function activate_subscription( int $user_id, string $plan_slug ): void {
    SubscriptionManager::activate( $user_id, $plan_slug );
}

function expire_subscription( int $user_id ): void {
    SubscriptionManager::expire( $user_id );
}

function renew_subscription( int $user_id, string $plan_slug ): void {
    SubscriptionManager::renew( $user_id, $plan_slug );
}

function create_listing( int $user_id, array $meta = [] ): int {
    $post_id = wp_insert_post( [
        'post_title'   => 'ChunkC Listing ' . $user_id . '-' . time(),
        'post_content' => 'Chunk C test listing',
        'post_status'  => 'publish',
        'post_type'    => 'ovr_property',
        'post_author'  => $user_id,
    ] );
    foreach ( $meta as $k => $v ) {
        update_post_meta( $post_id, $k, $v );
    }
    return $post_id;
}

// ================================================================
// C. BASE SUBSCRIBER TERMINOLOGY
// ================================================================
echo "\n== C. Base Subscriber Terminology ==\n";

$user_c1 = create_landlord( make_email( 'c1' ), make_password() );
update_user_meta( $user_c1, UserSubscription::META_STATUS, UserSubscription::STATUS_NONE );
$label_none = UserSubscription::status_label( UserSubscription::STATUS_NONE );
ok( 'Base Subscriber' === $label_none, 'C1 STATUS_NONE label=Base Subscriber (actual=' . $label_none . ')' );

$user_c2 = create_landlord( make_email( 'c2' ), make_password() );
activate_subscription( $user_c2, 'standard_homeowner_5' );
expire_subscription( $user_c2 );
$label_expired = UserSubscription::status_label( UserSubscription::STATUS_EXPIRED );
ok( 'Base Subscriber' === $label_expired, 'C2 STATUS_EXPIRED label=Base Subscriber (actual=' . $label_expired . ')' );

ok( UserSubscription::is_base_subscriber( $user_c1 ), 'C3 STATUS_NONE user is_base_subscriber=true' );
ok( UserSubscription::is_base_subscriber( $user_c2 ), 'C4 STATUS_EXPIRED user is_base_subscriber=true' );

// ================================================================
// D. NEVER-ACTIVATED REPRESENTATION
// ================================================================
echo "\n== D. Never-Activated Representation ==\n";

$user_d = create_landlord( make_email( 'd' ), make_password() );
$expiry_d = get_user_meta( $user_d, UserSubscription::META_EXPIRES, true );
ok( '' === $expiry_d || null === $expiry_d, 'D1 never-activated has no expiry date (actual=' . var_export( $expiry_d, true ) . ')' );

$plan_d = get_user_meta( $user_d, UserSubscription::META_PLAN, true );
ok( '' === $plan_d || null === $plan_d, 'D2 never-activated has no plan (actual=' . var_export( $plan_d, true ) . ')' );

$status_d = UserSubscription::get_status( $user_d );
ok( UserSubscription::STATUS_NONE === $status_d, 'D3 never-activated status=none (actual=' . $status_d . ')' );

// Verify 99/99/9999 is NOT stored
global $wpdb;
$raw_expiry = $wpdb->get_var( $wpdb->prepare(
    "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
    $user_d, UserSubscription::META_EXPIRES
) );
ok( ! preg_match( '/99\/99\/9999/', (string) $raw_expiry ), 'D4 no invalid 99/99/9999 stored (actual=' . var_export( $raw_expiry, true ) . ')' );

// ================================================================
// E. LOGIN + ROUTING
// ================================================================
echo "\n== E. Login + Routing ==\n";

$user_e = create_landlord( make_email( 'e' ), make_password() );
wp_set_current_user( $user_e );
$redirect_e = SubscriptionManager::get_redirect_by_status( $user_e );
ok( false !== strpos( $redirect_e, 'subscription-select' ), 'E1 none user redirects to subscription-select (actual=' . $redirect_e . ')' );

$user_e2 = create_landlord( make_email( 'e2' ), make_password() );
activate_subscription( $user_e2, 'standard_homeowner_5' );
expire_subscription( $user_e2 );
$redirect_e2 = SubscriptionManager::get_redirect_by_status( $user_e2 );
ok( false !== strpos( $redirect_e2, 'renew' ), 'E2 expired user redirects to renew (actual=' . $redirect_e2 . ')' );

// ================================================================
// F. LISTING DISPLAY STATUS — ACTIVE/PENDING vs INACTIVE/PENDING
// ================================================================
echo "\n== F. Listing Display Status ==\n";

$user_f1 = create_landlord( make_email( 'f1' ), make_password() );
activate_subscription( $user_f1, 'standard_homeowner_5' );
$listing_f1_active = create_listing( $user_f1, [ '_ovr_listing_status' => 'active' ] );
$listing_f1_inactive = create_listing( $user_f1, [ '_ovr_listing_status' => 'inactive' ] );

expire_subscription( $user_f1 );

$display_active = PropertyQuery::listing_display_status( $listing_f1_active );
$display_inactive = PropertyQuery::listing_display_status( $listing_f1_inactive );

ok( 'active_pending_renewal' === $display_active, 'F1 active listing shows active_pending_renewal (actual=' . $display_active . ')' );
ok( 'inactive_pending_renewal' === $display_inactive, 'F2 inactive listing shows inactive_pending_renewal (actual=' . $display_inactive . ')' );

// Verify underlying status is still pending_renewal
$raw_active = get_post_meta( $listing_f1_active, '_ovr_listing_status', true );
$raw_inactive = get_post_meta( $listing_f1_inactive, '_ovr_listing_status', true );
ok( 'pending_renewal' === $raw_active, 'F3 underlying status remains pending_renewal for active (actual=' . $raw_active . ')' );
ok( 'pending_renewal' === $raw_inactive, 'F4 underlying status remains pending_renewal for inactive (actual=' . $raw_inactive . ')' );

// Verify public visibility excludes both
ok( ! PropertyQuery::is_publicly_visible( $listing_f1_active ), 'F5 active listing not publicly visible after expiry' );
ok( ! PropertyQuery::is_publicly_visible( $listing_f1_inactive ), 'F6 inactive listing not publicly visible after expiry' );

// ================================================================
// G. RENEWAL RESTORES INTENT
// ================================================================
echo "\n== G. Renewal Restores Intent ==\n";

renew_subscription( $user_f1, 'standard_homeowner_5' );

$restored_active = get_post_meta( $listing_f1_active, '_ovr_listing_status', true );
$restored_inactive = get_post_meta( $listing_f1_inactive, '_ovr_listing_status', true );
$prior_active = get_post_meta( $listing_f1_active, '_ovr_listing_status_pre_expiry', true );
$prior_inactive = get_post_meta( $listing_f1_inactive, '_ovr_listing_status_pre_expiry', true );

ok( 'active' === $restored_active, 'G1 active listing restored to active (actual=' . $restored_active . ')' );
ok( 'inactive' === $restored_inactive, 'G2 inactive listing restored to inactive (actual=' . $restored_inactive . ')' );
ok( '' === $prior_active, 'G3 pre_expiry cleared for active (actual=' . var_export( $prior_active, true ) . ')' );
ok( '' === $prior_inactive, 'G4 pre_expiry cleared for inactive (actual=' . var_export( $prior_inactive, true ) . ')' );

ok( PropertyQuery::is_publicly_visible( $listing_f1_active ), 'G5 active listing publicly visible after renewal' );
ok( ! PropertyQuery::is_publicly_visible( $listing_f1_inactive ), 'G6 inactive listing remains not publicly visible (owner inactive intent preserved)' );

// ================================================================
// H. IDEMPOTENCY
// ================================================================
echo "\n== H. Idempotency ==\n";

expire_subscription( $user_f1 );
expire_subscription( $user_f1 ); // run twice

$status_after = UserSubscription::get_status( $user_f1 );
$listing_after = get_post_meta( $listing_f1_active, '_ovr_listing_status', true );
ok( 'expired' === $status_after, 'H1 double expire leaves status=expired (actual=' . $status_after . ')' );
ok( 'pending_renewal' === $listing_after, 'H2 double expire leaves listing_status=pending_renewal (actual=' . $listing_after . ')' );

// ================================================================
// I. SUSPENDED NOT BASE SUBSCRIBER
// ================================================================
echo "\n== I. Suspended != Base Subscriber ==\n";

$user_i = create_landlord( make_email( 'i' ), make_password() );
activate_subscription( $user_i, 'standard_homeowner_5' );
update_user_meta( $user_i, UserSubscription::META_STATUS, UserSubscription::STATUS_SUSPENDED );

ok( ! UserSubscription::is_base_subscriber( $user_i ), 'I1 suspended is NOT base_subscriber' );

// ================================================================
// J. ADMIN BYPASS
// ================================================================
echo "\n== J. Admin Bypass ==\n";

$user_j = create_landlord( make_email( 'j' . microtime(true) ), make_password() );

// Find existing admin or create one
$admin_j = 0;
$existing_admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
if ( ! empty( $existing_admins ) ) {
    $admin_j = (int) $existing_admins[0];
} else {
    $admin_result = wp_create_user( make_email( 'jadmin' . microtime(true) ), make_password(), make_email( 'jadmin' . microtime(true) ) );
    if ( is_wp_error( $admin_result ) ) {
        echo "  SKIP: J1-J2 admin bypass (could not create admin: " . $admin_result->get_error_message() . ")\n";
    } else {
        $admin_j = (int) $admin_result;
        $admin_user = new WP_User( $admin_j );
        $admin_user->set_role( 'administrator' );
    }
}

if ( $admin_j > 0 ) {
    // Admin bypass is at AccessControl level (dashboard/page access), not at
    // has_listing_access() level. has_listing_access() is for landlord listing
    // creation, which admins manage via wp-admin.
    $access_error = \OVR\Subscription\AccessControl::check_access( $admin_j );
    ok( ! is_wp_error( $access_error ), 'J1 admin bypass: AccessControl grants admin without subscription' );

    $access_error_j = \OVR\Subscription\AccessControl::check_access( $user_j );
    ok( is_wp_error( $access_error_j ), 'J2 unpaid landlord blocked by AccessControl' );
}

// ================================================================
// K. BASE SUBSCRIBER DISPLAY TERMINOLOGY (C-046..C-050)
// ================================================================
echo "\n== K. Base Subscriber Display Terminology ==\n";

// C-046 — STATUS_NONE subscription tab renders "Base Subscriber" heading
$user_k1 = create_landlord( make_email( 'k1' ), make_password() );
wp_set_current_user( $user_k1 );
ob_start();
include OVR_PLUGIN_DIR . 'templates/dashboard/tab-subscription.php';
$tab_html_k1 = ob_get_clean();
ok( str_contains( $tab_html_k1, 'Base Subscriber' ), 'C046 STATUS_NONE subscription tab renders Base Subscriber' );

// C-047 — STATUS_EXPIRED subscription tab renders "Base Subscriber" but internal status remains expired
$user_k2 = create_landlord( make_email( 'k2' ), make_password() );
activate_subscription( $user_k2, 'standard_homeowner_5' );
expire_subscription( $user_k2 );
wp_set_current_user( $user_k2 );
ob_start();
include OVR_PLUGIN_DIR . 'templates/dashboard/tab-subscription.php';
$tab_html_k2 = ob_get_clean();
$internal_status = UserSubscription::get_status( $user_k2 );
ok( str_contains( $tab_html_k2, 'Base Subscriber' ), 'C047a expired subscription tab renders Base Subscriber' );
ok( UserSubscription::STATUS_EXPIRED === $internal_status, 'C047b expired internal status remains expired (actual=' . $internal_status . ')' );

// C-048 — Base Subscriber UI communicates payment requirement
$user_k3 = create_landlord( make_email( 'k3' ), make_password() );
wp_set_current_user( $user_k3 );
$sub_select_html_k3 = \OVR\Frontend\SubscriptionSelect::render();
ok( str_contains( $sub_select_html_k3, 'Subscription requires payment for activation or renewal' ), 'C048 none user sees payment-required message' );
expire_subscription( $user_k3 );
$sub_select_html_k3e = \OVR\Frontend\SubscriptionSelect::render();
ok( str_contains( $sub_select_html_k3e, 'Subscription requires payment for activation or renewal' ), 'C048 expired user sees payment-required message' );

// C-049 — STATUS_NONE not silently becoming STATUS_EXPIRED
$user_k4 = create_landlord( make_email( 'k4' ), make_password() );
$status_before = UserSubscription::get_status( $user_k4 );
ok( UserSubscription::STATUS_NONE === $status_before, 'C049a new user status=none (actual=' . $status_before . ')' );
// Simulate operations that should not change none -> expired
$redirect_none = SubscriptionManager::get_redirect_by_status( $user_k4 );
$status_after = UserSubscription::get_status( $user_k4 );
ok( UserSubscription::STATUS_NONE === $status_after, 'C049b status remains none after routing (actual=' . $status_after . ')' );

// C-050 — STATUS_EXPIRED remains expired internally after UI display
$user_k5 = create_landlord( make_email( 'k5' ), make_password() );
activate_subscription( $user_k5, 'standard_homeowner_5' );
expire_subscription( $user_k5 );
$label_k5 = UserSubscription::status_label( UserSubscription::STATUS_EXPIRED );
$internal_k5 = UserSubscription::get_status( $user_k5 );
ok( 'Base Subscriber' === $label_k5, 'C050a expired displays Base Subscriber (label=' . $label_k5 . ')' );
ok( UserSubscription::STATUS_EXPIRED === $internal_k5, 'C050b expired internal status remains expired (actual=' . $internal_k5 . ')' );

// ================================================================
// L. PENDING / CANCELLED / SUSPENDED DISTINCTIONS (C-051..C-053)
// ================================================================
echo "\n== L. Non-Base States ==\n";

// C-051 — STATUS_PENDING not Base Subscriber active entitlement
$user_l1 = create_landlord( make_email( 'l1' ), make_password() );
update_user_meta( $user_l1, UserSubscription::META_STATUS, UserSubscription::STATUS_PENDING );
ok( ! UserSubscription::is_base_subscriber( $user_l1 ), 'C051a pending is NOT base_subscriber' );
$redirect_l1 = SubscriptionManager::get_redirect_by_status( $user_l1 );
ok( false !== strpos( $redirect_l1, 'payment=pending' ), 'C051b pending routes to payment=pending (actual=' . $redirect_l1 . ')' );

// C-052 — STATUS_CANCELLED not normalized to none/expired
$user_l2 = create_landlord( make_email( 'l2' ), make_password() );
activate_subscription( $user_l2, 'standard_homeowner_5' );
update_user_meta( $user_l2, UserSubscription::META_STATUS, UserSubscription::STATUS_CANCELLED );
ok( ! UserSubscription::is_base_subscriber( $user_l2 ), 'C052a cancelled is NOT base_subscriber' );
$redirect_l2 = SubscriptionManager::get_redirect_by_status( $user_l2 );
ok( false !== strpos( $redirect_l2, 'renew=required' ), 'C052b cancelled routes to renew=required (actual=' . $redirect_l2 . ')' );

// C-053 — STATUS_SUSPENDED remains suspended
$user_l3 = create_landlord( make_email( 'l3' ), make_password() );
activate_subscription( $user_l3, 'standard_homeowner_5' );
update_user_meta( $user_l3, UserSubscription::META_STATUS, UserSubscription::STATUS_SUSPENDED );
ok( ! UserSubscription::is_base_subscriber( $user_l3 ), 'C053a suspended is NOT base_subscriber' );
$redirect_l3 = SubscriptionManager::get_redirect_by_status( $user_l3 );
ok( false !== strpos( $redirect_l3, 'suspended=1' ), 'C053b suspended routes to suspended=1 (actual=' . $redirect_l3 . ')' );

// ================================================================
// M. MULTIPLE LISTING MIXED-STATE RESTORATION (C-054)
// ================================================================
echo "\n== M. Multiple Listing Mixed-State Restoration ==\n";

$user_m = create_landlord( make_email( 'm' ), make_password() );
activate_subscription( $user_m, 'standard_homeowner_5' );
$listing_m_active = create_listing( $user_m, [ '_ovr_listing_status' => 'active' ] );
$listing_m_inactive = create_listing( $user_m, [ '_ovr_listing_status' => 'inactive' ] );

expire_subscription( $user_m );

$display_m_active = PropertyQuery::listing_display_status( $listing_m_active );
$display_m_inactive = PropertyQuery::listing_display_status( $listing_m_inactive );
ok( 'active_pending_renewal' === $display_m_active, 'C054a active listing -> Active/Pending (actual=' . $display_m_active . ')' );
ok( 'inactive_pending_renewal' === $display_m_inactive, 'C054b inactive listing -> Inactive/Pending (actual=' . $display_m_inactive . ')' );

renew_subscription( $user_m, 'standard_homeowner_5' );

$restored_m_active = get_post_meta( $listing_m_active, '_ovr_listing_status', true );
$restored_m_inactive = get_post_meta( $listing_m_inactive, '_ovr_listing_status', true );
ok( 'active' === $restored_m_active, 'C054c active listing restored to active (actual=' . $restored_m_active . ')' );
ok( 'inactive' === $restored_m_inactive, 'C054d inactive listing restored to inactive (actual=' . $restored_m_inactive . ')' );
ok( PropertyQuery::is_publicly_visible( $listing_m_active ), 'C054e active listing publicly visible after renewal' );
ok( ! PropertyQuery::is_publicly_visible( $listing_m_inactive ), 'C054f inactive listing remains not publicly visible after renewal' );

// ================================================================
// N. MISSING PRE-EXPIRY METADATA FALLBACK (C-055)
// ================================================================
echo "\n== N. Missing Pre-Expiry Metadata Fallback ==\n";

$user_n = create_landlord( make_email( 'n' ), make_password() );
activate_subscription( $user_n, 'standard_homeowner_5' );
$listing_n = create_listing( $user_n, [ '_ovr_listing_status' => 'active' ] );
expire_subscription( $user_n );

// Corrupt/missing pre_expiry metadata
delete_post_meta( $listing_n, '_ovr_listing_status_pre_expiry' );

$display_n = PropertyQuery::listing_display_status( $listing_n );
ok( 'active_pending_renewal' === $display_n || 'pending_renewal' === $display_n, 'C055a missing pre_expiry falls back safely (actual=' . $display_n . ')' );

$public_n = PropertyQuery::is_publicly_visible( $listing_n );
ok( ! $public_n, 'C055b missing pre_expiry listing not publicly visible (actual=' . var_export( $public_n, true ) . ')' );

// Renewal with missing pre_expiry should restore to active (safe fallback)
renew_subscription( $user_n, 'standard_homeowner_5' );
$restored_n = get_post_meta( $listing_n, '_ovr_listing_status', true );
ok( 'active' === $restored_n, 'C055c renewal restores to active when pre_expiry missing (actual=' . $restored_n . ')' );

// ================================================================
// O. FEATURED / SPOTLIGHT CANNOT BYPASS EXPIRED VISIBILITY (C-056..C-057)
// ================================================================
echo "\n== O. Featured/Spotlight Expired Visibility ==\n";

$user_o = create_landlord( make_email( 'o' ), make_password() );
activate_subscription( $user_o, 'standard_homeowner_5' );
$listing_o = create_listing( $user_o, [ '_ovr_listing_status' => 'active' ] );
update_post_meta( $listing_o, '_ovr_in_slider', '1' );
update_post_meta( $listing_o, '_ovr_slider_expires', date( 'Y-m-d', strtotime( '+1 year' ) ) );

expire_subscription( $user_o );

$visible_o = PropertyQuery::is_publicly_visible( $listing_o );
ok( ! $visible_o, 'C056 expired featured listing not publicly visible' );

$featured_q = PropertyQuery::get_featured( 12 );
$found_in_featured = false;
if ( $featured_q->have_posts() ) {
    foreach ( $featured_q->posts as $post ) {
        if ( (int) $post->ID === (int) $listing_o ) {
            $found_in_featured = true;
            break;
        }
    }
}
ok( ! $found_in_featured, 'C057 expired featured listing excluded from get_featured query' );

$slider_q = PropertyQuery::get_slider( 6 );
$found_in_slider = false;
if ( $slider_q->have_posts() ) {
    foreach ( $slider_q->posts as $post ) {
        if ( (int) $post === (int) $listing_o ) {
            $found_in_slider = true;
            break;
        }
    }
}
ok( ! $found_in_slider, 'C057 expired spotlight listing excluded from get_slider query' );

// ================================================================
// P. RENEWAL REQUIRES VERIFIED PAYMENT COMPLETION (C-058)
// ================================================================
echo "\n== P. Renewal Requires Verified Payment ==\n";

$user_p = create_landlord( make_email( 'p' ), make_password() );
activate_subscription( $user_p, 'standard_homeowner_5' );
expire_subscription( $user_p );

$status_before_renew = UserSubscription::get_status( $user_p );
$expiry_before_renew = get_user_meta( $user_p, UserSubscription::META_EXPIRES, true );

// Opening subscription-select alone must NOT activate
$sub_select_html_p = \OVR\Frontend\SubscriptionSelect::render();
$status_after_view = UserSubscription::get_status( $user_p );
$expiry_after_view = get_user_meta( $user_p, UserSubscription::META_EXPIRES, true );
ok( UserSubscription::STATUS_EXPIRED === $status_after_view, 'C058a viewing subscription-select does not activate (actual=' . $status_after_view . ')' );
ok( $expiry_before_renew === $expiry_after_view, 'C058b viewing subscription-select does not change expiry' );

// Only verified payment completion via SubscriptionManager::activate() restores
renew_subscription( $user_p, 'standard_homeowner_5' );
$status_after_renew = UserSubscription::get_status( $user_p );
ok( UserSubscription::STATUS_ACTIVE === $status_after_renew, 'C058c verified renewal activates subscription (actual=' . $status_after_renew . ')' );

// ================================================================
// Q. DUPLICATE PAYMENT COMPLETION IDEMPOTENCY (C-059)
// ================================================================
echo "\n== Q. Duplicate Payment Completion Idempotency ==\n";

$user_q = create_landlord( make_email( 'q' ), make_password() );

// Create a pending payment record for this user
global $wpdb;
$payment_id = $wpdb->insert( $wpdb->prefix . 'ovr_payments', [
    'user_id'      => $user_q,
    'payment_type' => 'subscription',
    'amount'       => '99.00',
    'currency'     => 'USD',
    'gateway'      => 'free',
    'status'       => 'pending',
    'description'  => 'Chunk C idempotency test',
], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ] );

if ( $payment_id ) {
    $payment_id = (int) $wpdb->insert_id;
    $handler = new \OVR\Payment\CheckoutHandler();
    
    // First completion succeeds
    $first_result = $handler->complete_payment_atomically( $payment_id, [] );
    $expiry_after_first = get_user_meta( $user_q, UserSubscription::META_EXPIRES, true );
    $status_after_first = UserSubscription::get_status( $user_q );
    
    ok( true === $first_result, 'C059a first payment completion succeeds' );
    ok( UserSubscription::STATUS_ACTIVE === $status_after_first, 'C059b subscription activated after first completion (actual=' . $status_after_first . ')' );
    
    // Replay completion returns false and does not mutate state
    $replay_result = $handler->complete_payment_atomically( $payment_id, [] );
    $expiry_after_replay = get_user_meta( $user_q, UserSubscription::META_EXPIRES, true );
    $status_after_replay = UserSubscription::get_status( $user_q );
    
    ok( false === $replay_result, 'C059c replay of completed payment returns false (idempotent)' );
    ok( $expiry_after_first === $expiry_after_replay, 'C059d replay does not change expiry (before=' . $expiry_after_first . ' after=' . $expiry_after_replay . ')' );
    ok( UserSubscription::STATUS_ACTIVE === $status_after_replay, 'C059e status remains active after replay' );
} else {
    echo "  SKIP: C059a-C059e (could not create payment record)\n";
}

// Verify SubscriptionManager::activate() on an already-active subscription
// extends from current expiry (correct early-renewal behavior).
$user_q2 = create_landlord( make_email( 'q2' ), make_password() );
activate_subscription( $user_q2, 'standard_homeowner_5' );
$expiry_q2_before = get_user_meta( $user_q2, UserSubscription::META_EXPIRES, true );
$future_ts = strtotime( $expiry_q2_before );
if ( $future_ts > time() ) {
    activate_subscription( $user_q2, 'standard_homeowner_5' );
    $expiry_q2_after = get_user_meta( $user_q2, UserSubscription::META_EXPIRES, true );
    $expected = date( 'Y-m-d', strtotime( '+1 year', $future_ts ) );
    $diff = abs( strtotime( $expiry_q2_after ) - strtotime( $expected ) );
    ok( $diff < DAY_IN_SECONDS, 'C059f early renewal extends from current expiry (actual=' . $expiry_q2_after . ' expected~=' . $expected . ')' );
}

// ================================================================
// R. EXPIRED-RENEWAL AMBIGUITY FROZEN (C-060)
// ================================================================
echo "\n== R. Expired-Renewal Ambiguity Frozen ==\n";

$user_r = create_landlord( make_email( 'r' ), make_password() );
activate_subscription( $user_r, 'standard_homeowner_5' );

// Set expiry to a known past date (expired)
$past_expiry = date( 'Y-m-d', strtotime( '-30 days' ) );
update_user_meta( $user_r, UserSubscription::META_EXPIRES, $past_expiry );
expire_subscription( $user_r );

$expiry_before_r = get_user_meta( $user_r, UserSubscription::META_EXPIRES, true );
$renewal_ts = time();
renew_subscription( $user_r, 'standard_homeowner_5' );
$expiry_after_r = get_user_meta( $user_r, UserSubscription::META_EXPIRES, true );

$expected_reactivation_base = date( 'Y-m-d', strtotime( '+1 year', $renewal_ts ) );
$diff = abs( strtotime( $expiry_after_r ) - strtotime( $expected_reactivation_base ) );
ok( $diff < DAY_IN_SECONDS, 'C060 expired renewal uses reactivation base (Rule B) — actual=' . $expiry_after_r . ' expected~=' . $expected_reactivation_base . ' diff=' . round( $diff / 3600, 1 ) . 'h' );

// ================================================================
// RESULTS
// ================================================================
echo "\n=== RESULTS: {$pass} passed, {$fail} failed ===\n";

// Cleanup synthetic users
$synth_users = get_users( [ 'meta_key' => 'ovr_registered_at', 'meta_compare' => 'EXISTS', 'fields' => 'ID' ] );
foreach ( $synth_users as $uid ) {
    if ( function_exists( 'wp_delete_user' ) ) {
        wp_delete_user( $uid );
    } else {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->users} WHERE ID = %d", $uid ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d", $uid ) );
    }
}

exit( $fail > 0 ? 1 : 0 );
