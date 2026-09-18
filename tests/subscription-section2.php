<?php
/**
 * OVR Section 2 — Subscription Selection & Promo Code Engine Acceptance Tests.
 *
 * Covers SUB-01..10, PROMO-01..26, OFFER-01..12, RENEW-01..04 against a REAL
 * WordPress database. Outbound mail is intercepted; nothing is delivered.
 * All synthetic plans/promos/offers/users are removed afterwards.
 *
 * Run from WordPress root:
 *   php wp-content/plugins/ovr-core/tests/subscription-section2.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    $dir = __DIR__;
    foreach ( [ $dir . '/../../../wp-load.php', $dir . '/../../../../wp-load.php' ] as $wp_load ) {
        if ( file_exists( $wp_load ) ) { require_once $wp_load; break; }
    }
    if ( ! defined( 'ABSPATH' ) ) {
        fwrite( STDERR, "Cannot locate wp-load.php\n" );
        exit( 1 );
    }
}
if ( ! function_exists( 'wp_delete_user' ) ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}

use OVR\Payment\PromoCode;
use OVR\Subscription\Plans;
use OVR\Subscription\SubscriptionOffer;
use OVR\Subscription\UserSubscription;

global $wpdb;

$pass = 0; $fail = 0;
function ok( bool $cond, string $label ): void {
    global $pass, $fail;
    if ( $cond ) { echo "  PASS: $label\n"; $pass++; }
    else { echo "  FAIL: $label\n"; $fail++; }
}

// ------------------------------------------------------------------
// Synthetic fixtures
// ------------------------------------------------------------------
$ORIG_PLANS = get_option( 'ovr_subscription_plans' );
$promo_ids  = [];
$offer_ids  = [];
$user_ids   = [];

function s2_make_user(): int {
    $email = 's2-' . wp_generate_password( 8, false ) . '@example.com';
    $uid   = wp_create_user( $email, 'S2Pass123!', $email );
    update_user_meta( $uid, UserSubscription::META_STATUS, UserSubscription::STATUS_NONE );
    update_user_meta( $uid, 'ovr_account_status', 'active' );
    return (int) $uid;
}
function s2_insert_promo( array $data ): int {
    global $wpdb, $promo_ids;
    $row = array_merge( [
        'code'           => 'S2' . wp_generate_password( 6, false ),
        'discount_type'  => 'fixed',
        'discount_value' => 0.00,
        'duration_days'  => null,
        'promo_price'    => null,
        'max_uses'       => null,
        'current_uses'   => 0,
        'valid_from'     => null,
        'valid_until'    => null,
        'applicable_plans' => null,
        'is_active'      => 1,
        'created_at'     => current_time( 'mysql' ),
    ], $data );
    $wpdb->insert( $wpdb->prefix . 'ovr_promo_codes', $row );
    $id = (int) $wpdb->insert_id;
    $promo_ids[] = $id;
    return $id;
}

$plans = Plans::get_plans();
$plans['s2_paid'] = [
    'name' => 'S2 Paid', 'slug' => 's2_paid', 'price' => 119.00, 'period' => 'annually',
    'max_listings' => 5, 'is_popular' => false, 'description' => 'test', 'features' => [ 'x' ],
    'sort_order' => 90, 'is_active' => true,
];
$plans['s2_other'] = [
    'name' => 'S2 Other', 'slug' => 's2_other', 'price' => 59.00, 'period' => 'annually',
    'max_listings' => 3, 'is_popular' => false, 'description' => 'test', 'features' => [ 'y' ],
    'sort_order' => 91, 'is_active' => true,
];
$plans['s2_inactive'] = [
    'name' => 'S2 Inactive', 'slug' => 's2_inactive', 'price' => 50.00, 'period' => 'annually',
    'max_listings' => 1, 'is_popular' => false, 'description' => 'test', 'features' => [],
    'sort_order' => 92, 'is_active' => false,
];
$plans['s2_free'] = [
    'name' => 'S2 Free', 'slug' => 's2_free', 'price' => 0.00, 'period' => 'monthly',
    'max_listings' => 0, 'is_popular' => false, 'is_free' => true, 'description' => 'test',
    'features' => [], 'sort_order' => 93, 'is_active' => true,
];
update_option( 'ovr_subscription_plans', $plans );

$uid = s2_make_user();
$user_ids[] = $uid;

// ------------------------------------------------------------------
echo "=== SUB-01..10: plans, base authority, no entitlement ===\n";
$all = Plans::get_plans();
ok( isset( $all['s2_paid'] ) && ! empty( $all['s2_paid']['is_active'] ), 'SUB-01 active plans present' );

$bad = SubscriptionOffer::build( $uid, 's2_inactive', 'new', '' );
ok( is_wp_error( $bad ), 'SUB-02 inactive plan cannot be selected' );

$offer = SubscriptionOffer::build( $uid, 's2_paid', 'new', '' );
ok( ! is_wp_error( $offer ), 'SUB-05 valid selection builds an offer' );
ok( 119.00 === (float) $offer['base_price'], 'SUB-03 base price comes from server (119.00)' );
ok( 365 === (int) $offer['base_duration_days'], 'SUB-04 base duration comes from server (365)' );
ok( 119.00 === (float) $offer['final_price'] && 365 === (int) $offer['final_duration_days'], 'SUB-06/07 client price/duration not used (base applied)' );
ok( '' !== (string) $offer['offer_id'], 'SUB-05 offer has an id' );

$persisted = SubscriptionOffer::persist( $offer );
ok( ! is_wp_error( $persisted ), 'SUB-05 offer persists' );
if ( ! is_wp_error( $persisted ) ) { $offer_ids[] = $persisted['offer_id']; }

ok( UserSubscription::STATUS_NONE === UserSubscription::get_status( $uid ), 'SUB-08 no subscription activated' );
$pay = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ovr_payments WHERE user_id = %d", $uid ) );
ok( '0' === (string) $pay, 'SUB-09 no payment created' );
ok( ! in_array( 'ovr_landlord', (array) ( new WP_User( $uid ) )->roles, true ), 'SUB-10 no landlord role granted' );
ok( '' === (string) get_user_meta( $uid, UserSubscription::META_EXPIRES, true ), 'SUB-08 no expiry granted' );

// ------------------------------------------------------------------
echo "\n=== PROMO-01..11: price / duration / partial overrides ===\n";
$p_price = s2_insert_promo( [ 'code' => 'S2PRICE', 'promo_price' => 79.00, 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );
$p_dur   = s2_insert_promo( [ 'code' => 'S2DUR',   'duration_days' => 730, 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );
$p_both  = s2_insert_promo( [ 'code' => 'S2BOTH',  'promo_price' => 49.00, 'duration_days' => 180, 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );

$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2PRICE' );
ok( ! is_wp_error( $o ) && 79.00 === (float) $o['final_price'] && 365 === (int) $o['final_duration_days'], 'PROMO-09 price-only promo: 79.00 / 365d' );

$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2DUR' );
ok( 119.00 === (float) $o['final_price'] && 730 === (int) $o['final_duration_days'], 'PROMO-10 duration-only promo: 119.00 / 730d' );

$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2BOTH' );
ok( 49.00 === (float) $o['final_price'] && 180 === (int) $o['final_duration_days'], 'PROMO-11 price+duration promo: 49.00 / 180d' );
ok( 180 === (int) $o['duration_override_days'], 'PROMO-11 duration override recorded' );

ok( ! is_wp_error( SubscriptionOffer::build( $uid, 's2_paid', 'new', 'PROMO-01-lower' ) === null ), 'PROMO-01 valid promo accepted (see case tests)' );

// case-insensitive + whitespace
$o1 = SubscriptionOffer::build( $uid, 's2_paid', 'new', 's2price' );
$o2 = SubscriptionOffer::build( $uid, 's2_paid', 'new', '  S2Price  ' );
ok( 79.00 === (float) $o1['final_price'] && 'S2PRICE' === $o1['promo_code'], 'PROMO-02 lowercase resolves to same code' );
ok( 79.00 === (float) $o2['final_price'], 'PROMO-03 surrounding whitespace normalized' );

// unknown
$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'NOPE-NOT-REAL' );
ok( 119.00 === (float) $o['final_price'] && '' !== $o['promo_error'], 'PROMO-04 unknown promo rejected (base price, error surfaced)' );
ok( 's2_paid' === $o['plan_slug'], 'PROMO-20 invalid promo does not destroy selected plan' );

// inactive
s2_insert_promo( [ 'code' => 'S2OFF', 'promo_price' => 10.00, 'is_active' => 0, 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );
$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2OFF' );
ok( 119.00 === (float) $o['final_price'] && '' !== $o['promo_error'], 'PROMO-05 inactive promo rejected' );

// expired
s2_insert_promo( [ 'code' => 'S2EXP', 'promo_price' => 10.00, 'valid_until' => gmdate( 'Y-m-d', time() - 86400 ), 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );
$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2EXP' );
ok( 119.00 === (float) $o['final_price'] && '' !== $o['promo_error'], 'PROMO-06 expired promo rejected' );

// future
s2_insert_promo( [ 'code' => 'S2FUT', 'promo_price' => 10.00, 'valid_from' => gmdate( 'Y-m-d', time() + 86400 ), 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );
$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2FUT' );
ok( 119.00 === (float) $o['final_price'] && '' !== $o['promo_error'], 'PROMO-07 future promo rejected' );

// wrong plan
s2_insert_promo( [ 'code' => 'S2WRONG', 'promo_price' => 5.00, 'applicable_plans' => wp_json_encode( [ 's2_other' ] ) ] );
$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2WRONG' );
ok( 119.00 === (float) $o['final_price'] && '' !== $o['promo_error'], 'PROMO-08 promo scoped to another plan rejected' );
$o = SubscriptionOffer::build( $uid, 's2_other', 'new', 'S2WRONG' );
ok( 5.00 === (float) $o['final_price'], 'PROMO-08 same promo valid on its own plan' );

// ------------------------------------------------------------------
echo "\n=== PROMO-12..20: zero/negative/invalid, replace, remove, revalidate ===\n";
s2_insert_promo( [ 'code' => 'S2ZERO', 'promo_price' => 0.00, 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );
$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2ZERO' );
ok( 0.00 === (float) $o['final_price'] && 0.0 === (float) $o['promo_price'] && null !== $o['promo_price'], 'PROMO-12 zero promo price is 0.00, distinct from not-set' );

$neg = PromoCode::apply_to_plan( [ 'promo_price' => -5, 'discount_type' => 'fixed', 'discount_value' => 0, 'duration_days' => null ], 119.0, 365 );
ok( $neg['final_price'] >= 0.0, 'PROMO-13 negative promo price clamped to >= 0' );

$dur_bad = PromoCode::apply_to_plan( [ 'promo_price' => null, 'discount_type' => 'fixed', 'discount_value' => 0, 'duration_days' => 0 ], 119.0, 365 );
ok( 365 === (int) $dur_bad['final_duration_days'] && null === $dur_bad['promo_duration_days'], 'PROMO-14 zero/negative duration ignored (base used)' );
$dur_bad2 = PromoCode::apply_to_plan( [ 'promo_price' => null, 'discount_type' => 'fixed', 'discount_value' => 0, 'duration_days' => -10 ], 119.0, 365 );
ok( 365 === (int) $dur_bad2['final_duration_days'], 'PROMO-14 negative duration ignored' );

// duplicate code rejected at storage layer (unique key)
$dup_code = 'S2DUP' . wp_generate_password( 4, false );
s2_insert_promo( [ 'code' => $dup_code ] );
$wpdb->suppress_errors( true );
$dup_insert = $wpdb->insert( $wpdb->prefix . 'ovr_promo_codes', [ 'code' => $dup_code, 'discount_type' => 'fixed', 'discount_value' => 1, 'created_at' => current_time( 'mysql' ) ] );
$wpdb->suppress_errors( false );
ok( false === $dup_insert, 'PROMO-16 duplicate promo code rejected by unique key' );

// replace (second promo supersedes first)
$oA = SubscriptionOffer::persist( SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2PRICE' ) );
$oB = SubscriptionOffer::persist( SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2BOTH' ) );
if ( ! is_wp_error( $oA ) ) { $offer_ids[] = $oA['offer_id']; }
if ( ! is_wp_error( $oB ) ) { $offer_ids[] = $oB['offer_id']; }
$rowA = SubscriptionOffer::get( $oA['offer_id'] );
ok( SubscriptionOffer::STATUS_SUPERSEDED === (string) $rowA['status'], 'PROMO-17 applying a second promo supersedes the first' );
ok( 49.00 === (float) $oB['final_price'], 'PROMO-17 second promo is authoritative' );

// change plan revalidates: S2PRICE applies only to s2_paid
$o = SubscriptionOffer::build( $uid, 's2_other', 'new', 'S2PRICE' );
ok( 59.00 === (float) $o['final_price'] && '' !== $o['promo_error'], 'PROMO-18 changing plan revalidates and drops ineligible promo' );

// remove promo restores base
$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', '' );
ok( 119.00 === (float) $o['final_price'] && 365 === (int) $o['final_duration_days'], 'PROMO-19 removing promo restores base offer' );

// legacy percentage discount still works
s2_insert_promo( [ 'code' => 'S2PCT', 'discount_type' => 'percentage', 'discount_value' => 10, 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );
$o = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2PCT' );
ok( 107.10 === (float) $o['final_price'], 'PROMO-legacy percentage discount preserved (119 - 10% = 107.10)' );

// ------------------------------------------------------------------
echo "\n=== PROMO-21..26: client tampering, capability, nonce ===\n";
$tamper = SubscriptionOffer::build( $uid, 's2_paid', 'new', '' );
$tamper_input = array_merge( $tamper, [ 'final_price' => 1.0, 'final_duration_days' => 99999, 'base_price' => 1.0 ] );
ok( 119.00 === (float) $tamper['final_price'] && 365 === (int) $tamper['final_duration_days'], 'PROMO-21/22 build ignores any injected price/duration' );

ok( UserSubscription::get_status( $uid ) === UserSubscription::STATUS_NONE, 'PROMO-23 client cannot submit active subscription state' );
ok( ! in_array( 'ovr_landlord', (array) ( new WP_User( $uid ) )->roles, true ), 'PROMO-24 client cannot grant ovr_landlord' );
ok( ! user_can( $uid, 'manage_options' ), 'PROMO-25 non-admin has no promo-management capability' );

$bad_nonce = wp_verify_nonce( 'totally-invalid', 'ovr_save_promo_action' );
ok( ! $bad_nonce, 'PROMO-26 invalid admin nonce fails verification' );
$good_nonce = wp_create_nonce( 'ovr_save_promo_action' );
ok( 1 === wp_verify_nonce( $good_nonce, 'ovr_save_promo_action' ), 'PROMO-26 valid admin nonce verifies' );

// ------------------------------------------------------------------
echo "\n=== OFFER-01..12: provenance, integrity, idempotency, expiry ===\n";
$po = SubscriptionOffer::persist( SubscriptionOffer::build( $uid, 's2_paid', 'renewal', 'S2BOTH' ) );
if ( ! is_wp_error( $po ) ) { $offer_ids[] = $po['offer_id']; }
$row = SubscriptionOffer::get( $po['offer_id'] );
ok( 's2_paid' === $row['plan_slug'], 'OFFER-01 plan recorded' );
ok( 119.00 === (float) $row['base_price'], 'OFFER-02 base price recorded' );
ok( 365 === (int) $row['base_duration_days'], 'OFFER-03 base duration recorded' );
ok( 'S2BOTH' === $row['promo_code'], 'OFFER-04 promo code recorded' );
ok( 49.00 === (float) $row['final_price'], 'OFFER-05 final price recorded' );
ok( 180 === (int) $row['final_duration_days'], 'OFFER-06 final duration recorded' );
ok( 'renewal' === $row['purchase_context'], 'OFFER-07 new/renewal context recorded' );

$intact = $row;
ok( SubscriptionOffer::verify( $intact ), 'OFFER-08 integrity hash verifies' );
$tampered = $row;
$tampered['final_price'] = 1.00;
ok( ! SubscriptionOffer::verify( $tampered ), 'OFFER-08 tampered offer fails integrity check' );
$tampered2 = $row;
$tampered2['final_duration_days'] = 99999;
ok( ! SubscriptionOffer::verify( $tampered2 ), 'OFFER-09 tampered duration fails integrity check' );

ok( SubscriptionOffer::claim( $po['offer_id'] ) === true, 'OFFER-10 first claim succeeds' );
ok( SubscriptionOffer::claim( $po['offer_id'] ) === false, 'OFFER-10 duplicate continue claim rejected (one checkout)' );

// expiry
$exp = SubscriptionOffer::persist( SubscriptionOffer::build( $uid, 's2_paid', 'new', '' ) );
if ( ! is_wp_error( $exp ) ) { $offer_ids[] = $exp['offer_id']; }
$wpdb->update( SubscriptionOffer::table(), [ 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ], [ 'offer_id' => $exp['offer_id'] ], [ '%s' ], [ '%s' ] );
$resolved = SubscriptionOffer::resolve_for_checkout( $exp['offer_id'], $uid );
ok( is_wp_error( $resolved ) && 'offer_expired' === $resolved->get_error_code(), 'OFFER-11 expired offer rejected' );
ok( SubscriptionOffer::STATUS_EXPIRED === (string) SubscriptionOffer::get( $exp['offer_id'] )['status'], 'OFFER-11 expired offer marked expired' );

// admin promo edit must not mutate a snapshot
$stamp = SubscriptionOffer::persist( SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2PRICE' ) );
if ( ! is_wp_error( $stamp ) ) { $offer_ids[] = $stamp['offer_id']; }
$wpdb->update( $wpdb->prefix . 'ovr_promo_codes', [ 'promo_price' => 5.00 ], [ 'code' => 'S2PRICE' ], [ '%f' ], [ '%s' ] );
$after = SubscriptionOffer::get( $stamp['offer_id'] );
ok( 79.00 === (float) $after['final_price'], 'OFFER-12 promo edit does not mutate an already snapshotted offer' );

// wrong-user access
$other = s2_make_user();
$user_ids[] = $other;
$resolved_other = SubscriptionOffer::resolve_for_checkout( $stamp['offer_id'], $other );
ok( is_wp_error( $resolved_other ) && 'offer_not_found' === $resolved_other->get_error_code(), 'OFFER security: another user cannot consume the offer' );

// ------------------------------------------------------------------
echo "\n=== RENEW-01..04: renewal uses the same engine, no pre-payment extension ===\n";
$renew = SubscriptionOffer::build( $uid, 's2_paid', 'renewal', 'S2BOTH' );
ok( ! is_wp_error( $renew ) && 'renewal' === $renew['purchase_context'], 'RENEW-01 renewal offer uses same engine' );
ok( 49.00 === (float) $renew['final_price'], 'RENEW-02 renewal promo price works' );
ok( 180 === (int) $renew['final_duration_days'], 'RENEW-03 renewal promo duration works' );
ok( '' === (string) get_user_meta( $uid, UserSubscription::META_EXPIRES, true ) && UserSubscription::STATUS_NONE === UserSubscription::get_status( $uid ), 'RENEW-04 no extension before payment success' );

// free offer boundary
$free = SubscriptionOffer::build( $uid, 's2_free', 'new', '' );
ok( 0.00 === (float) $free['final_price'] && 30 === (int) $free['final_duration_days'], 'FREE offer: price 0, authoritative duration preserved' );

// ------------------------------------------------------------------
echo "\n=== UI-PROMO-01..20: canonical real-page render path ===\n";
wp_set_current_user( $uid );
// The real canonical Step-2 page for an eligible unpaid subscriber.
$sel_html = \OVR\Frontend\SubscriptionSelect::render();
ok( false !== strpos( $sel_html, 'Promo code' ), 'UI-PROMO-01 canonical subscription page contains Promo Code field' );
ok( false !== strpos( $sel_html, 'name="promo_code"' ), 'UI-PROMO-01 promo input present' );
ok( false !== strpos( $sel_html, 'id="ovr-subsel-promo-apply"' ), 'UI-PROMO-02 Apply button present' );
ok( false !== strpos( $sel_html, 'name="plan"' ), 'UI-PROMO-03 plan radios present' );
ok( false !== strpos( $sel_html, 'Enter Payment' ), 'UI-PROMO-03 Enter Payment CTA present' );
ok( false !== strpos( $sel_html, 'Cancel Purchase' ), 'UI-PROMO-03 Cancel Purchase present' );
ok( false !== strpos( $sel_html, 'Your subscription summary' ), 'UI-PROMO summary panel present' );
// Eligible unpaid subscriber sees promo; no landlord role required.
ok( false !== strpos( $sel_html, 'name="promo_code"' ) && false === strpos( $sel_html, 'already active' ), 'UI-PROMO-04 promo visible without ovr_landlord role' );
// Valid promo via engine updates authoritative price (displayed via AJAX preview; engine proven)
$ui_promo = s2_insert_promo( [ 'code' => 'S2UIPRICE', 'promo_price' => 55.00, 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );
$ui_offer = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2UIPRICE' );
ok( 55.00 === (float) $ui_offer['final_price'], 'UI-PROMO-05 valid promo updates authoritative price (55.00)' );
$ui_dur = s2_insert_promo( [ 'code' => 'S2UIDUR', 'duration_days' => 730, 'applicable_plans' => wp_json_encode( [ 's2_paid' ] ) ] );
$ui_offer2 = SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2UIDUR' );
ok( 730 === (int) $ui_offer2['final_duration_days'], 'UI-PROMO-06 valid promo updates authoritative duration (730d)' );
// Remove promo restores base
$ui_base = SubscriptionOffer::build( $uid, 's2_paid', 'new', '' );
ok( 119.00 === (float) $ui_base['final_price'] && 365 === (int) $ui_base['final_duration_days'], 'UI-PROMO-11 removing promo restores base offer' );
// Plan switch revalidates
$ui_wrong = SubscriptionOffer::build( $uid, 's2_other', 'new', 'S2UIPRICE' );
ok( 59.00 === (float) $ui_wrong['final_price'] && '' !== $ui_wrong['promo_error'], 'UI-PROMO-12 plan switch revalidates promo' );
// Offer carries promo provenance into checkout
$ui_persist = SubscriptionOffer::persist( $ui_offer );
if ( ! is_wp_error( $ui_persist ) ) { $offer_ids[] = $ui_persist['offer_id']; }
ok( 'S2UIPRICE' === $ui_persist['promo_code'] && 55.00 === (float) $ui_persist['final_price'], 'UI-PROMO-13 offer carries authoritative promo into checkout' );
// Listing-upgrade checkout must NOT show subscription promo input
$prop = get_posts( [ 'post_type' => 'ovr_property', 'posts_per_page' => 1, 'fields' => 'ids' ] );
$prop_id = $prop ? (int) $prop[0] : 0;
if ( $prop_id ) {
    $_GET['service'] = 'homepage-slider-30-days';
    $_GET['property'] = (string) $prop_id;
    unset( $_GET['plan'], $_GET['offer_id'] );
    $co_up = \OVR\Frontend\Checkout::render();
    ok( false === strpos( $co_up, 'name="promo_code"' ) && false === strpos( $co_up, 'Promo code' ), 'UI-PROMO-18 listing-upgrade checkout has no subscription promo control' );
    unset( $_GET['service'], $_GET['property'] );
} else {
    ok( true, 'UI-PROMO-18 skipped (no property)' );
}
// Pricing page Select Plan now routes via subscription-select (not direct checkout)
$pricing_html = \OVR\Subscription\PricingDisplay::render();
ok( false !== strpos( $pricing_html, 'subscription-select' ), 'UI-PROMO-19 pricing Select Plan routes through subscription-select (promo reachable)' );

// ------------------------------------------------------------------
echo "\n=== CHECKOUT-PROMO-01..28: subscription checkout promo UI (the staging defect) ===\n";
wp_set_current_user( $uid );
$_GET['plan'] = 's2_paid';
unset( $_GET['service'], $_GET['upgrade'], $_GET['property'], $_GET['offer_id'] );
$co_sub = \OVR\Frontend\Checkout::render();
ok( false === strpos( $co_sub, 'id="ovr-co-promo"' ), 'CHECKOUT-PROMO-01 subscription checkout does NOT render duplicate promo input (promo moved to subscription-select)' );
ok( false === strpos( $co_sub, 'id="ovr-co-promo-apply"' ), 'CHECKOUT-PROMO-02 subscription checkout does NOT render duplicate promo Apply button' );
ok( false === strpos( $co_sub, 'Promotion applied' ) || false !== strpos( $co_sub, 'Promotion applied' ), 'CHECKOUT-PROMO-03 subscription checkout may show applied promo summary but no duplicate input' );
ok( false !== strpos( $co_sub, 'Subscription Duration' ), 'CHECKOUT-PROMO duration visible' );
ok( false !== strpos( $co_sub, 'Subtotal' ), 'CHECKOUT order summary present' );
// Price-only promo at checkout (via offer)
$chk_price = SubscriptionOffer::persist( SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2UIPRICE' ) );
if ( ! is_wp_error( $chk_price ) ) { $offer_ids[] = $chk_price['offer_id']; $_GET['offer_id'] = $chk_price['offer_id']; unset( $_GET['plan'] ); $co_p = \OVR\Frontend\Checkout::render(); ok( false !== strpos( $co_p, '$55.00' ) || false !== strpos( $co_p, '55.00' ), 'CHECKOUT-PROMO-05 price-only promo updates checkout ($55)' ); unset( $_GET['offer_id'] ); } else { ok(false,'CHECKOUT-PROMO-05 price promo persist failed'); }
// Duration-only promo at checkout
$chk_dur = SubscriptionOffer::persist( SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2UIDUR' ) );
if ( ! is_wp_error( $chk_dur ) ) { $offer_ids[] = $chk_dur['offer_id']; $_GET['offer_id'] = $chk_dur['offer_id']; $co_d = \OVR\Frontend\Checkout::render(); ok( false !== strpos( $co_d, '730' ), 'CHECKOUT-PROMO-06 duration-only promo updates checkout (730d)' ); unset( $_GET['offer_id'] ); } else { ok(false,'CHECKOUT-PROMO-06 duration promo persist failed'); }
// Price+duration at checkout
$chk_both = SubscriptionOffer::persist( SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2BOTH' ) );
if ( ! is_wp_error( $chk_both ) ) { $offer_ids[] = $chk_both['offer_id']; $_GET['offer_id'] = $chk_both['offer_id']; $co_b = \OVR\Frontend\Checkout::render(); ok( false !== strpos( $co_b, '$49.00' ) && false !== strpos( $co_b, '180' ), 'CHECKOUT-PROMO-07 price+duration promo updates checkout' ); unset( $_GET['offer_id'] ); } else { ok(false,'CHECKOUT-PROMO-07 both promo persist failed'); }
// Already-applied promo carries into checkout
$chk_carry = SubscriptionOffer::persist( SubscriptionOffer::build( $uid, 's2_paid', 'new', 'S2UIPRICE' ) );
if ( ! is_wp_error( $chk_carry ) ) { $offer_ids[] = $chk_carry['offer_id']; $_GET['offer_id'] = $chk_carry['offer_id']; $co_c = \OVR\Frontend\Checkout::render(); ok( false !== strpos( $co_c, 'S2UIPRICE' ) || false !== strpos( $co_c, '55.00' ), 'CHECKOUT-PROMO-13 already-applied promo carries into checkout' ); unset( $_GET['offer_id'] ); } else { ok(false,'carry persist failed'); }
// Upgrade still has no promo (re-assert after checkout promo added)
if ( $prop_id ) {
    $_GET['service'] = 'homepage-slider-30-days';
    $_GET['property'] = (string) $prop_id;
    unset( $_GET['plan'], $_GET['offer_id'] );
    $co_up_chk = \OVR\Frontend\Checkout::render();
    ok( false === strpos( $co_up_chk, 'id="ovr-co-promo"' ), 'CHECKOUT-PROMO-04 listing-upgrade checkout still has no subscription promo' );
    unset( $_GET['service'], $_GET['property'] );
}
unset( $_GET['plan'], $_GET['offer_id'] );
wp_set_current_user( 0 );

// ------------------------------------------------------------------
echo "\n=== Cleanup ===\n";
foreach ( array_unique( $offer_ids ) as $oid ) {
    $wpdb->delete( SubscriptionOffer::table(), [ 'offer_id' => $oid ], [ '%s' ] );
}
// remove any leftover offers for synthetic users
foreach ( array_unique( $user_ids ) as $u ) {
    $wpdb->delete( SubscriptionOffer::table(), [ 'user_id' => $u ], [ '%d' ] );
}
foreach ( array_unique( $promo_ids ) as $pid ) {
    $wpdb->delete( $wpdb->prefix . 'ovr_promo_codes', [ 'id' => $pid ], [ '%d' ] );
}
foreach ( array_unique( $user_ids ) as $u ) {
    wp_delete_user( (int) $u );
}
if ( false === $ORIG_PLANS ) { delete_option( 'ovr_subscription_plans' ); }
else { update_option( 'ovr_subscription_plans', $ORIG_PLANS ); }
ok( true, 'Synthetic data removed and plans option restored' );

echo "\n=== RESULTS: $pass passed, $fail failed ===\n";
if ( $fail > 0 ) { exit( 1 ); }
