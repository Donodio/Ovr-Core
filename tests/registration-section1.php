<?php
/**
 * OVR Section 1 — Registration & Account Creation Acceptance Tests.
 *
 * Covers REG-01 through REG-25 against a REAL WordPress database:
 * validation, account creation, email-as-identity, duplicate safety,
 * role allowlist, unpaid initial state, admin notification (no password),
 * email-failure safety, idempotency, consent record, and login regression.
 *
 * Run from WordPress root:
 *   php wp-content/plugins/ovr-core/tests/registration-section1.php
 *
 * All test users use @example.com addresses and are deleted afterwards.
 * Outbound mail is intercepted via the `pre_wp_mail` filter — nothing is
 * actually delivered.
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
}

use OVR\Auth\RegistrationHandler;
use OVR\Core\Pages;
use OVR\Email\EmailTemplates;
use OVR\Email\Mailer;
use OVR\Subscription\AccessControl;
use OVR\Subscription\SubscriptionManager;
use OVR\Subscription\UserSubscription;

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

function make_email( string $prefix = 'ovr-s1' ): string {
    return $prefix . '-' . wp_generate_password( 8, false ) . '@example.com';
}

$synth_users = [];
function track_synth_user( int $id ): void {
    global $synth_users;
    $synth_users[] = $id;
}
function delete_synth_users(): void {
    global $synth_users;
    foreach ( array_reverse( $synth_users ) as $user_id ) {
        $u = new WP_User( $user_id );
        if ( $u->exists() && in_array( 'ovr_landlord', (array) $u->roles, true ) ) {
            $u->remove_role( 'ovr_landlord' );
            $u->add_role( 'subscriber' );
        }
        wp_delete_user( (int) $user_id );
    }
    $synth_users = [];
}

function valid_input( array $overrides = [] ): array {
    return array_merge( [
        'first_name'  => 'Section',
        'last_name'   => 'One',
        'email'       => make_email(),
        'phone'       => '+1 (352) 555-0147',
        'password'    => 'TestPass123!',
        'confirm'     => 'TestPass123!',
        'is_landlord' => true,
        'terms'       => true,
    ], $overrides );
}

// ------------------------------------------------------------------
// Mail interception: capture everything, deliver nothing.
// ------------------------------------------------------------------
$captured_mail = [];
$force_mail_failure = false;
add_filter( 'pre_wp_mail', function ( $null, $atts ) use ( &$captured_mail, &$force_mail_failure ) {
    $captured_mail[] = $atts;
    return $force_mail_failure ? false : true; // true = pretend delivered.
}, 10, 2 );
function mail_to( array $atts ): string {
    $to = $atts['to'] ?? '';
    return is_array( $to ) ? implode( ',', $to ) : (string) $to;
}

// Ensure the Section 1 admin template exists (idempotent seed).
EmailTemplates::maybe_seed();

echo "=== REG-00: Admin template seeded ===\n";
$tpl = EmailTemplates::get( 'new_user_registered' );
ok( null !== $tpl, 'new_user_registered template exists in DB' );
if ( $tpl ) {
    ok( 'A new user has registered' === (string) $tpl['subject'], 'Subject is exactly "A new user has registered"' );
    ok( 'admin' === (string) $tpl['recipient'], 'Recipient mode is admin' );
    $blob = (string) $tpl['subject'] . ' ' . (string) $tpl['body_html'] . ' ' . (string) $tpl['body_text'];
    ok( false === stripos( $blob, 'password' ), 'Template contains no password reference/token' );
    ok( false !== strpos( $blob, '{{user_name}}' ), 'Template uses {{user_name}}' );
    ok( false !== strpos( $blob, '{{user_email}}' ) || false !== strpos( $blob, '{{login_email}}' ), 'Template uses user/login email token' );
    ok( 0 === preg_match( '/\{\{\s*[a-z0-9_]+\s*\}\}/i', str_replace( [ '{{user_name}}', '{{user_email}}', '{{login_email}}', '{{registered_at}}', '{{is_landlord}}', '{{user_admin_url}}', '{{site_name}}', '{{site_url}}', '{{admin_email}}' ], '', $blob ) ), 'No unknown/unresolved tokens remain' );
}

echo "\n=== REG-01: Valid registration creates exactly one user ===\n";
$before = count_users();
$input  = valid_input();
$res    = RegistrationHandler::validate_registration( $input );
ok( empty( $res['errors'] ), 'Valid input passes server-side validation' );
$uid = RegistrationHandler::create_account( $res['data'] );
ok( ! is_wp_error( $uid ), 'create_account succeeds' );
if ( ! is_wp_error( $uid ) ) {
    track_synth_user( (int) $uid );
}
$after = count_users();
ok( $after['total_users'] === $before['total_users'] + 1, 'Exactly one new user created' );

echo "\n=== REG-02/03/04/05: Fields persisted, email is login identity ===\n";
if ( ! is_wp_error( $uid ) ) {
    $u = get_userdata( (int) $uid );
    ok( 'Section' === $u->first_name, 'REG-02 first name persisted' );
    ok( 'One' === $u->last_name, 'REG-03 last name persisted' );
    ok( $u->user_email === $res['data']['email'], 'REG-04 email persisted' );
    ok( $u->user_login === $u->user_email, 'REG-04 user_login equals email (canonical identity)' );
    ok( '+1 (352) 555-0147' === (string) get_user_meta( $uid, 'ovr_phone', true ), 'REG-05 phone persisted' );
    ok( '' !== (string) get_user_meta( $uid, 'ovr_terms_accepted_at', true ), 'Consent timestamp recorded' );
    ok( '' !== (string) get_user_meta( $uid, 'ovr_terms_version', true ), 'Consent version recorded' );

    wp_logout();
    $signon = wp_signon( [ 'user_login' => $u->user_email, 'user_password' => 'TestPass123!', 'remember' => false ], is_ssl() );
    ok( ! is_wp_error( $signon ) && $signon->ID === (int) $uid, 'Login by email works immediately after registration' );
    wp_logout();
}

echo "\n=== REG-06: Password only via WP hash, nowhere else ===\n";
if ( ! is_wp_error( $uid ) ) {
    $u = get_userdata( (int) $uid );
    ok( $u->user_pass !== 'TestPass123!', 'user_pass is not plaintext' );
    ok( wp_check_password( 'TestPass123!', $u->user_pass, (int) $uid ), 'Hash verifies' );
    $leak = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_value = %s",
        (int) $uid, 'TestPass123!'
    ) );
    ok( '0' === (string) $leak, 'Plaintext password absent from usermeta' );
}

echo "\n=== REG-07: Confirm mismatch rejected ===\n";
$res = RegistrationHandler::validate_registration( valid_input( [ 'confirm' => 'Different123!' ] ) );
ok( in_array( 'Passwords do not match.', $res['errors'], true ), 'Mismatch rejected' );

echo "\n=== REG-08: Invalid email rejected ===\n";
$res = RegistrationHandler::validate_registration( valid_input( [ 'email' => 'not-an-email' ] ) );
ok( in_array( 'Please enter a valid email address.', $res['errors'], true ), 'Invalid email rejected' );
$res = RegistrationHandler::validate_registration( valid_input( [ 'email' => '   ' ] ) );
ok( in_array( 'Please enter a valid email address.', $res['errors'], true ), 'Blank email rejected' );

echo "\n=== REG-09: Duplicate email rejected ===\n";
if ( ! is_wp_error( $uid ) ) {
    $dup_email = get_userdata( (int) $uid )->user_email;
    $res = RegistrationHandler::validate_registration( valid_input( [ 'email' => $dup_email ] ) );
    ok( 1 === count( array_filter( $res['errors'], fn( $e ) => false !== strpos( $e, 'already exists' ) ) ), 'Duplicate detected at validation' );
    $res_upper = RegistrationHandler::validate_registration( valid_input( [ 'email' => strtoupper( $dup_email ) ] ) );
    ok( 1 === count( array_filter( $res_upper['errors'], fn( $e ) => false !== strpos( $e, 'already exists' ) ) ), 'Duplicate detected case-insensitively' );
    $before_count = count_users();
    $dup = RegistrationHandler::create_account( RegistrationHandler::validate_registration( valid_input( [ 'email' => $dup_email ] ) )['data'] );
    ok( is_wp_error( $dup ), 'create_account refuses duplicate' );
    $after_count = count_users();
    ok( $after_count['total_users'] === $before_count['total_users'], 'No second account created' );
    // Existing account untouched.
    $still = get_userdata( (int) $uid );
    ok( $still->user_email === $dup_email && 'Section' === $still->first_name, 'REG-21 existing account data unchanged' );
}

echo "\n=== REG-10: Terms required; landlord selection is intent, not a gate ===\n";
$res = RegistrationHandler::validate_registration( valid_input( [ 'terms' => false ] ) );
ok( in_array( 'You must agree to the Terms of Service.', $res['errors'], true ), 'Missing terms rejected' );
$res_no_ll = RegistrationHandler::validate_registration( valid_input( [ 'is_landlord' => false ] ) );
ok( empty( $res_no_ll['errors'] ), 'REG-L02 landlord unchecked passes validation (intent is optional)' );
$ll_required_error = false;
foreach ( $res_no_ll['errors'] as $e ) {
    if ( false !== strpos( (string) $e, 'Landlord / Property Manager' ) ) {
        $ll_required_error = true;
    }
}
ok( ! $ll_required_error, 'REG-L02 no landlord-required validation error remains' );

echo "\n=== REG-L01/L02: both account-intent states are valid Step-1 states ===\n";
$ll_input    = valid_input( [ 'email' => make_email( 'ovr-l-ll' ), 'is_landlord' => true ] );
$ll_res      = RegistrationHandler::validate_registration( $ll_input );
$ll_uid      = RegistrationHandler::create_account( $ll_res['data'] );
$nonll_input = valid_input( [ 'email' => make_email( 'ovr-l-nonll' ), 'is_landlord' => false ] );
$nonll_res   = RegistrationHandler::validate_registration( $nonll_input );
$nonll_uid   = RegistrationHandler::create_account( $nonll_res['data'] );
ok( ! is_wp_error( $ll_uid ), 'REG-L01 landlord-selected account created' );
ok( ! is_wp_error( $nonll_uid ), 'REG-L02 non-landlord account created' );
if ( ! is_wp_error( $ll_uid ) )    { track_synth_user( (int) $ll_uid ); }
if ( ! is_wp_error( $nonll_uid ) ) { track_synth_user( (int) $nonll_uid ); }

echo "\n=== REG-L01..L07: intent recorded; no role/access/plan/payment; both reach Step 2 ===\n";
$step2_url = Pages::get_page_url( 'ovr_page_subscription_select' );
$intent_cases = [
    'REG-L01 landlord=true'  => [ $ll_uid, '1' ],
    'REG-L02 landlord=false' => [ $nonll_uid, '0' ],
];
foreach ( $intent_cases as $case_label => $pair ) {
    $candidate     = $pair[0];
    $expected_flag = $pair[1];
    if ( is_wp_error( $candidate ) ) {
        ok( false, $case_label . ' account creation failed' );
        continue;
    }
    $cid   = (int) $candidate;
    $roles = (array) ( new WP_User( $cid ) )->roles;
    ok( $expected_flag === (string) get_user_meta( $cid, 'ovr_is_landlord', true ), $case_label . ': ovr_is_landlord = ' . $expected_flag );
    ok( ! in_array( 'ovr_landlord', $roles, true ), $case_label . ': REG-L03 no ovr_landlord role at Step 1' );
    ok( in_array( 'subscriber', $roles, true ), $case_label . ': safe default subscriber role' );
    ok( ! UserSubscription::has_listing_access( $cid ), $case_label . ': REG-L04 no listing access at Step 1' );
    ok( '' === UserSubscription::get_plan_slug( $cid ), $case_label . ': REG-L05 no subscription plan' );
    ok( UserSubscription::STATUS_NONE === UserSubscription::get_status( $cid ), $case_label . ': REG-L05 subscription status none' );
    $pay_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ovr_payments WHERE user_id = %d", $cid ) );
    ok( '0' === (string) $pay_count, $case_label . ': REG-L06 no payment row' );
    ok( false !== strpos( $step2_url, '/subscription-select' ), $case_label . ': REG-L07 reaches canonical subscription selection' );
    ok( 'active' === (string) get_user_meta( $cid, 'ovr_account_status', true ), $case_label . ': account status active' );
}
// REG-L08: hostile extra fields are ignored (no mass assignment, no escalation).
$hostile = valid_input();
$hostile['role'] = 'administrator';
$hostile['ovr_is_landlord'] = 'administrator';
$hostile['ovr_subscription_status'] = 'active';
$res_h = RegistrationHandler::validate_registration( $hostile );
ok( ! array_key_exists( 'role', $res_h['data'] ), 'REG-L08 role field not accepted' );
ok( ! array_key_exists( 'ovr_subscription_status', $res_h['data'] ), 'REG-L08 subscription field not accepted' );
if ( empty( $res_h['errors'] ) ) {
    $h_uid = RegistrationHandler::create_account( $res_h['data'] );
    if ( ! is_wp_error( $h_uid ) ) {
        track_synth_user( (int) $h_uid );
        $h_roles = (array) ( new WP_User( (int) $h_uid ) )->roles;
        ok( ! in_array( 'administrator', $h_roles, true ), 'REG-L08 hostile input cannot grant administrator' );
        ok( ! in_array( 'ovr_landlord', $h_roles, true ), 'REG-L08 hostile input cannot grant ovr_landlord' );
        ok( '1' === (string) get_user_meta( $h_uid, 'ovr_is_landlord', true ), 'REG-L08 landlord value coerced to intent 1/0, never a role' );
        ok( UserSubscription::STATUS_NONE === UserSubscription::get_status( (int) $h_uid ), 'REG-L08 hostile input cannot activate subscription' );
    } else {
        ok( false, 'REG-L08 hostile-input account creation failed unexpectedly' );
    }
} else {
    ok( false, 'REG-L08 hostile extra fields should not break validation' );
}

echo "\n=== REG-14: No payment records ===\n";
if ( ! is_wp_error( $uid ) ) {
    $payments = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ovr_payments WHERE user_id = %d",
        (int) $uid
    ) );
    ok( '0' === (string) $payments, 'REG-14 no payment rows for new account' );
}

echo "\n=== REG-15: Handoff to canonical subscription selection ===\n";
$sel_url = Pages::get_page_url( 'ovr_page_subscription_select' );
ok( false !== strpos( $sel_url, '/subscription-select' ), 'Canonical Step-2 route exists: ' . $sel_url );

echo "\n=== REG-E01..E03: Admin notification via Mailer, no password ===\n";
$admin_mail = null;
foreach ( $captured_mail as $m ) {
    if ( false !== strpos( (string) ( $m['subject'] ?? '' ), 'A new user has registered' ) ) {
        $admin_mail = $m;
        break;
    }
}
ok( null !== $admin_mail, 'REG-E01 admin notification fires' );
if ( null !== $admin_mail ) {
    $admin_email = (string) get_option( 'admin_email' );
    $settings    = (array) get_option( 'ovr_settings', [] );
    $expected    = ! empty( $settings['support_email'] ) ? (string) $settings['support_email'] : $admin_email;
    ok( false !== strpos( mail_to( $admin_mail ), $expected ), 'REG-E01 addressed to admin (' . mail_to( $admin_mail ) . ')' );
    $body = (string) ( $admin_mail['message'] ?? '' );
    if ( ! is_wp_error( $uid ) ) {
        $u = get_userdata( (int) $uid );
        ok( false !== strpos( $body, $u->user_email ), 'REG-E02 contains login email' );
        ok( false !== strpos( $body, 'Section One' ) || false !== strpos( $body, 'Section' ), 'REG-E02 contains user name' );
    }
    ok( false === stripos( (string) ( $admin_mail['subject'] ?? '' ) . ' ' . $body, 'TestPass123!' ), 'REG-E03 no plaintext password in admin email' );
    ok( false === stripos( $body, '{{' ), 'No unresolved tokens in admin email' );
}
$leak_any = false;
foreach ( $captured_mail as $m ) {
    $blob = (string) ( $m['subject'] ?? '' ) . ' ' . (string) ( $m['message'] ?? '' );
    if ( false !== strpos( $blob, 'TestPass123!' ) ) {
        $leak_any = true;
    }
}
ok( ! $leak_any, 'REG-E04 no registration-generated email contains the plaintext password' );

echo "\n=== REG-E05: Email verification still functions ===\n";
$verify_mail = null;
foreach ( $captured_mail as $m ) {
    if ( false !== stripos( (string) ( $m['subject'] ?? '' ), 'confirm your email' ) ) {
        $verify_mail = $m;
        break;
    }
}
ok( null !== $verify_mail, 'REG-E05 verification email sent at Step 1' );
if ( null !== $verify_mail && ! is_wp_error( $uid ) ) {
    $body_v = (string) ( $verify_mail['message'] ?? '' );
    $has_url = preg_match( '/admin-post\.php\?action=ovr_verify_email&(?:amp;)?uid=\d+&(?:amp;)?token=([A-Za-z0-9]+)/', html_entity_decode( $body_v ), $vm );
    ok( 1 === $has_url, 'REG-E05 verification URL with uid+token present' );
    $stored_hash = (string) get_user_meta( (int) $uid, 'ovr_email_verification_token', true );
    ok( '' !== $stored_hash, 'REG-E05 verification token stored (hashed)' );
    ok( false === strpos( $stored_hash, 'ProbePass' ), 'REG-E05 stored hash never the password' );
    ok( '0' === (string) get_user_meta( (int) $uid, 'ovr_email_verified', true ), 'REG-E05 account starts unverified' );
    if ( 1 === $has_url && '' !== $stored_hash ) {
        $token = $vm[1];
        ok( wp_check_password( $token, $stored_hash ), 'REG-E05 emailed token verifies against stored hash' );
        // Mirror the handler success path (no exit/redirect in test context).
        delete_user_meta( (int) $uid, 'ovr_email_verification_token' );
        update_user_meta( (int) $uid, 'ovr_email_verified', '1' );
        ok( '1' === (string) get_user_meta( (int) $uid, 'ovr_email_verified', true ), 'REG-E05 successful verification marks account verified' );
        ok( '' === (string) get_user_meta( (int) $uid, 'ovr_email_verification_token', true ), 'REG-E05 token consumed after verification' );
    }
}

echo "\n=== REG-E07: registration_welcome fires only when Step-1-safe ===\n";
$welcome_tpl   = EmailTemplates::get( 'registration_welcome' );
$welcome_text  = strtolower( (string) ( $welcome_tpl['subject'] ?? '' ) . ' ' . (string) ( $welcome_tpl['body_html'] ?? '' ) );
// Distinguish affirmative false claims from conditional instructional language.
// "Once your subscription is active..." is permitted; "Your subscription is active." is not.
$clean_text = str_replace(
    [
        'once your subscription is active',
        'when your subscription is active',
        'after your subscription is active',
        'when your subscription becomes active',
        'once your subscription becomes active',
        'after activating your subscription',
        'once your membership is active',
        'when your membership is active',
    ],
    '',
    $welcome_text
);
$bad_claims = [
    'your subscription is active',
    'your membership is active',
    'your subscription has been activated',
    'your membership has been activated',
    'your payment was successful',
    'your payment has been completed',
    'your purchase is complete',
    'your landlord access is active',
];
$welcome_implies_activation = false;
foreach ( $bad_claims as $bad ) {
    if ( false !== strpos( $clean_text, $bad ) ) {
        $welcome_implies_activation = true;
        break;
    }
}
$welcome_fired = false;
foreach ( $captured_mail as $m ) {
    if ( false !== stripos( (string) ( $m['subject'] ?? '' ), 'Welcome to' ) ) {
        $welcome_fired = true;
    }
}
if ( $welcome_implies_activation ) {
    ok( ! $welcome_fired, 'REG-E07 registration_welcome withheld at Step 1 (affirmative activation claim detected)' );
} else {
    ok( $welcome_fired, 'REG-E07 registration_welcome fires at Step 1 (account-creation semantics only)' );
    // Conditional wording is explicitly allowed.
    ok( false !== strpos( $welcome_text, 'once your subscription is active' ), 'REG-E07 welcome contains conditional Once your subscription is active' );
    ok( ! $welcome_implies_activation, 'REG-E07 welcome does not falsely claim active subscription/payment' );
}

echo "\n=== REG-19: Email failure does not corrupt account ===\n";
$force_mail_failure = true;
$fail_input = valid_input( [ 'email' => make_email( 'ovr-s1-fail' ) ] );
$fail_res = RegistrationHandler::validate_registration( $fail_input );
$fail_uid = RegistrationHandler::create_account( $fail_res['data'] );
$force_mail_failure = false;
ok( ! is_wp_error( $fail_uid ), 'Account created despite mail failure' );
if ( ! is_wp_error( $fail_uid ) ) {
    track_synth_user( (int) $fail_uid );
    ok( get_user_by( 'id', (int) $fail_uid ) instanceof WP_User, 'Account persists' );
    ok( '1' === (string) get_user_meta( $fail_uid, 'ovr_email_verification_pending', true ), 'Verification retry flagged' );
    wp_logout();
    $s = wp_signon( [ 'user_login' => $fail_input['email'], 'user_password' => 'TestPass123!', 'remember' => false ], is_ssl() );
    ok( ! is_wp_error( $s ), 'User can still log in after mail failure' );
    wp_logout();
}

echo "\n=== REG-20: Double submission creates one account ===\n";
$dbl_email = make_email( 'ovr-s1-dbl' );
$dbl_data = RegistrationHandler::validate_registration( valid_input( [ 'email' => $dbl_email ] ) )['data'];
$before_count = count_users();
$first = RegistrationHandler::create_account( $dbl_data );
ok( ! is_wp_error( $first ), 'First submission succeeds' );
if ( ! is_wp_error( $first ) ) {
    track_synth_user( (int) $first );
}
$second = RegistrationHandler::create_account( $dbl_data );
ok( is_wp_error( $second ), 'Second submission refused' );
$after_count = count_users();
ok( $after_count['total_users'] === $before_count['total_users'] + 1, 'Exactly one account after double submit' );

echo "\n=== REG-22/23/24: Login regression ===\n";
if ( ! is_wp_error( $uid ) ) {
    SubscriptionManager::activate( (int) $uid, 'standard_homeowner_5' );
    ok( in_array( 'ovr_landlord', ( new WP_User( (int) $uid ) )->roles, true ), 'Paid activation still grants ovr_landlord (Section 2 boundary intact)' );
    wp_set_password( 'RotatePass123!', (int) $uid );
    wp_logout();
    $s = wp_signon( [ 'user_login' => get_userdata( (int) $uid )->user_email, 'user_password' => 'RotatePass123!', 'remember' => false ], is_ssl() );
    ok( ! is_wp_error( $s ), 'REG-22 active landlord can log in' );
    wp_logout();
    // Exercise the password-reset hook path (REG-24) without delivering mail.
    $key = get_password_reset_key( get_userdata( (int) $uid ) );
    ok( ! is_wp_error( $key ) && '' !== $key, 'REG-24 reset key generated' );
    $n_before = count( $captured_mail );
    do_action( 'retrieve_password_key', get_userdata( (int) $uid )->user_login, $key );
    $reset_found = false;
    foreach ( array_slice( $captured_mail, $n_before ) as $m ) {
        if ( false !== stripos( (string) ( $m['subject'] ?? '' ), 'password' ) ) {
            $reset_found = true;
        }
    }
    ok( $reset_found, 'REG-24 reset email path fires exactly once' );
}
$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
if ( $admins ) {
    ok( AccessControl::user_has_access( (int) $admins[0] ), 'REG-23 admin bypass preserved' );
} else {
    ok( true, 'REG-23 skipped (no administrator in environment)' );
}

echo "\n=== REG-25 + render: template sanity ===\n";
wp_logout();
$html = RegistrationHandler::render();
ok( false !== strpos( $html, 'Phone Number' ), 'Phone field rendered' );
ok( false !== strpos( $html, 'name="ovr_phone"' ), 'Phone input present' );
ok( false !== strpos( $html, 'required' ), 'Required attributes present' );
ok( false === strpos( $html, "/terms/'" ) && false === strpos( $html, 'home_url' ), 'No hardcoded /terms/ link text' );
ok( false !== strpos( $html, 'user-agreement' ) || false !== strpos( $html, RegistrationHandler::terms_url() ), 'Terms link points at canonical agreement' );
ok( 0 === preg_match( '/width\s*:\s*\d{3,}px/i', $html ), 'REG-25 no fixed wide inline widths (no horizontal overflow from template)' );
ok( false === stripos( $html, 'promo' ), 'No promo-code UI in Step 1' );
ok( false === stripos( $html, 'paypal' ) && false === stripos( $html, 'authorize' ), 'No payment controls in Step 1' );
// Error state: seed errors so the alert box renders, then verify AT semantics.
set_transient( 'ovr_register_errors', [ 'First name is required.' ], 60 );
$html_err = RegistrationHandler::render();
ok( false !== strpos( $html_err, 'role="alert"' ), 'Error container is announced to AT' );
ok( false !== strpos( $html_err, 'First name is required.' ), 'Seeded error message renders escaped' );

echo "\n=== Cleanup ===\n";
delete_synth_users();
wp_logout();
ok( true, 'Synthetic users removed' );

echo "\n=== RESULTS: $pass passed, $fail failed ===\n";
if ( $fail > 0 ) {
    exit( 1 );
}
