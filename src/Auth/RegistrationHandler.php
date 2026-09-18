<?php
/**
 * Registration Handler.
 *
 * @package OVR\Auth
 * @since   1.0.0
 */

namespace OVR\Auth;

use OVR\Core\Pages;
use OVR\Core\TemplateLoader;
use OVR\Email\Mailer;
use OVR\Subscription\UserSubscription;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RegistrationHandler {

    public function init(): void {
        add_action( 'init', [ $this, 'process_registration' ] );
        add_action( 'admin_post_nopriv_ovr_verify_email', [ $this, 'verify_email' ] );
        add_action( 'admin_post_ovr_verify_email', [ $this, 'verify_email' ] );
        add_action( 'admin_post_nopriv_ovr_confirm_email_change', [ $this, 'confirm_email_change' ] );
        add_action( 'admin_post_ovr_confirm_email_change', [ $this, 'confirm_email_change' ] );
    }

    /**
     * Build the email-verification confirm URL for a user + one-time token.
     */
    public static function verification_url( int $user_id, string $token ): string {
        return add_query_arg(
            [ 'action' => 'ovr_verify_email', 'uid' => $user_id, 'token' => $token ],
            admin_url( 'admin-post.php' )
        );
    }

    /**
     * Build the email-change confirmation URL.
     */
    public static function email_change_url( int $user_id, string $token ): string {
        return add_query_arg(
            [ 'action' => 'ovr_confirm_email_change', 'uid' => $user_id, 'token' => $token ],
            admin_url( 'admin-post.php' )
        );
    }

    /**
     * Confirm an email-verification token (admin-post handler, no login needed).
     */
    public function verify_email(): void {
        $uid   = (int) ( $_GET['uid'] ?? 0 );
        $token = (string) ( $_GET['token'] ?? '' );

        $ok = false;
        if ( $uid && '' !== $token ) {
            $user = get_userdata( $uid );
            if ( $user ) {
                $hash = (string) get_user_meta( $uid, 'ovr_email_verification_token', true );
                if ( '' !== $hash && wp_check_password( $token, $hash ) ) {
                    delete_user_meta( $uid, 'ovr_email_verification_token' );
                    update_user_meta( $uid, 'ovr_email_verified', '1' );
                    $ok = true;
                }
            }
        }

        $redirect = Pages::get_page_url( 'ovr_page_login' );
        $redirect = add_query_arg( $ok ? 'verified' : 'verified', $ok ? '1' : '0', $redirect );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Confirm a pending email-change token (admin-post handler, no login needed).
     */
    public function confirm_email_change(): void {
        $uid   = (int) ( $_GET['uid'] ?? 0 );
        $token = (string) ( $_GET['token'] ?? '' );

        $ok = false;
        if ( $uid && '' !== $token ) {
            $user = get_userdata( $uid );
            if ( $user ) {
                $pending = (string) get_user_meta( $uid, 'ovr_pending_email', true );
                $hash    = (string) get_user_meta( $uid, 'ovr_email_change_token', true );
                $expires = (int) get_user_meta( $uid, 'ovr_email_change_expires', true );

                if ( '' !== $pending && '' !== $hash && time() <= $expires && wp_check_password( $token, $hash ) ) {
                    $old_email = (string) $user->user_email;
                    $new_email = $pending;

                    // Update the canonical email + login identity.
                    wp_update_user( [ 'ID' => $uid, 'user_email' => $new_email ] );
                    global $wpdb;
                    $wpdb->update(
                        $wpdb->users,
                        [
                            'user_login'    => $new_email,
                            'user_nicename' => sanitize_title( $new_email ),
                        ],
                        [ 'ID' => $uid ]
                    );
                    clean_user_cache( $uid );

                    // Backfill the denormalized owner-email copy on listings.
                    $this->backfill_owner_email( $uid, $old_email, $new_email );

                    // Notify the old address that the login email moved.
                    Mailer::send( 'email_change_notification', [
                        'user_name' => $user->display_name ?: $old_email,
                        'new_email' => $new_email,
                    ], [ 'user_email' => $old_email ] );

                    // Confirm the new address is now the active login.
                    Mailer::send( 'email_change_notification', [
                        'user_name' => $user->display_name ?: $new_email,
                        'new_email' => $new_email,
                    ], [ 'user_id' => $uid ] );

                    // Clear pending state.
                    delete_user_meta( $uid, 'ovr_pending_email' );
                    delete_user_meta( $uid, 'ovr_email_change_token' );
                    delete_user_meta( $uid, 'ovr_email_change_expires' );

                    $ok = true;
                }
            }
        }

        $redirect = Pages::get_page_url( 'ovr_page_dashboard' );
        $redirect = add_query_arg( 'email_changed', $ok ? '1' : '0', $redirect );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Backfill _ovr_owner_email on all listings owned by a user after an
     * email change, so admin search and front-end display stay consistent.
     */
    private function backfill_owner_email( int $user_id, string $old_email, string $new_email ): void {
        $posts = get_posts( [
            'post_type'      => 'ovr_property',
            'post_author'    => $user_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
        foreach ( $posts as $post_id ) {
            $current = (string) get_post_meta( $post_id, '_ovr_owner_email', true );
            if ( '' === $current || $current === $old_email ) {
                update_post_meta( $post_id, '_ovr_owner_email', $new_email );
            }
        }
    }

    private function registration_rate_limit_key(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : 'cli';
        $ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
        return 'ovr_registration_attempts_' . md5( $ip );
    }

    private function enforce_registration_rate_limit(): void {
        $limit = 5;
        $window = 15 * MINUTE_IN_SECONDS;
        $key = $this->registration_rate_limit_key();
        $attempts = (int) get_transient( $key );
        if ( $attempts >= $limit ) {
            wp_die(
                esc_html__( 'Too many registration attempts. Please try again in about 15 minutes.', 'ovr-core' ),
                esc_html__( 'Rate limit exceeded', 'ovr-core' ),
                [ 'response' => 429 ]
            );
        }
        set_transient( $key, $attempts + 1, $window );
    }

    public function process_registration(): void {
        if ( ! isset( $_POST['ovr_register_submit'] ) ) {
            return;
        }

        if ( ! isset( $_POST['ovr_register_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_register_nonce'] ) ), 'ovr_register_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ovr-core' ) );
        }

        $this->enforce_registration_rate_limit();

        $result = self::validate_registration( [
            'first_name' => wp_unslash( $_POST['ovr_first_name'] ?? '' ),
            'last_name'  => wp_unslash( $_POST['ovr_last_name'] ?? '' ),
            'email'      => wp_unslash( $_POST['ovr_email'] ?? '' ),
            'phone'      => wp_unslash( $_POST['ovr_phone'] ?? '' ),
            'password'   => $_POST['ovr_password'] ?? '',
            'confirm'    => $_POST['ovr_confirm_password'] ?? '',
            'is_landlord'=> ! empty( $_POST['ovr_is_landlord'] ),
            'terms'      => ! empty( $_POST['ovr_terms'] ),
        ] );

        if ( ! empty( $result['errors'] ) ) {
            set_transient( 'ovr_register_errors', $result['errors'], 60 );
            set_transient( 'ovr_register_data', [
                'first_name' => $result['data']['first_name'],
                'last_name'  => $result['data']['last_name'],
                'email'      => $result['data']['email'],
                'phone'      => $result['data']['phone'],
                'is_landlord'=> $result['data']['is_landlord'],
            ], 60 );
            return;
        }

        // Create the account. On a race (double submit) the second request
        // fails here on the unique login — exactly one account survives.
        $user_id = self::create_account( $result['data'] );

        if ( is_wp_error( $user_id ) ) {
            set_transient( 'ovr_register_errors', [ $user_id->get_error_message() ], 60 );
            set_transient( 'ovr_register_data', [
                'first_name' => $result['data']['first_name'],
                'last_name'  => $result['data']['last_name'],
                'email'      => $result['data']['email'],
                'phone'      => $result['data']['phone'],
                'is_landlord'=> $result['data']['is_landlord'],
            ], 60 );
            return;
        }

        // Auto-login.
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );

        // Brand-new user → subscription selection (Batch 3 handoff). The
        // onboarding template still exists for direct /welcome/ visits and
        // clears the first-login flag on render, but the primary post-
        // registration flow now lands on subscription selection as approved.
        wp_safe_redirect( Pages::get_page_url( 'ovr_page_subscription_select' ) );
        exit;
    }

    /**
     * Normalize a person-name field: trim, collapse internal whitespace,
     * strip tags via sanitize_text_field, cap length.
     */
    public static function normalize_name( $raw ): string {
        $name = sanitize_text_field( (string) $raw );
        $name = trim( preg_replace( '/\s+/u', ' ', $name ) ?? '' );
        if ( function_exists( 'mb_substr' ) ) {
            $name = mb_substr( $name, 0, 100 );
        } else {
            $name = substr( $name, 0, 100 );
        }
        return $name;
    }

    /**
     * Normalize an email to its canonical login identity.
     */
    public static function normalize_email( $raw ): string {
        return strtolower( sanitize_email( trim( (string) $raw ) ) );
    }

    /**
     * Validate a phone number without imposing a single-country format:
     * allowed characters plus a minimum of 5 digits.
     */
    public static function phone_error( string $phone ): string {
        $phone = trim( $phone );
        if ( '' === $phone ) {
            return __( 'Please enter your phone number.', 'ovr-core' );
        }
        if ( strlen( $phone ) > 40 ) {
            return __( 'Please enter a valid phone number.', 'ovr-core' );
        }
        if ( ! preg_match( '/^[+\d][\d\s().\-]*$/', $phone ) ) {
            return __( 'Please enter a valid phone number.', 'ovr-core' );
        }
        $digits = preg_replace( '/\D/', '', $phone ) ?? '';
        if ( strlen( $digits ) < 5 ) {
            return __( 'Please enter a valid phone number.', 'ovr-core' );
        }
        return '';
    }

    /**
     * Server-side registration validation (authoritative; client-side is UX only).
     *
     * Only ever reads the explicit allowlisted fields below — there is no
     * mass assignment, and no request value can select a role or capability:
     * landlord intent is a boolean that maps to account intent metadata only.
     *
     * @param array $input Raw field values (see process_registration).
     * @return array{errors: string[], data: array{first_name: string, last_name: string, email: string, phone: string, password: string, is_landlord: bool}}
     */
    public static function validate_registration( array $input ): array {
        $first_name  = self::normalize_name( $input['first_name'] ?? '' );
        $last_name   = self::normalize_name( $input['last_name'] ?? '' );
        $email       = self::normalize_email( $input['email'] ?? '' );
        $phone       = sanitize_text_field( trim( (string) ( $input['phone'] ?? '' ) ) );
        $password    = (string) ( $input['password'] ?? '' );
        $confirm     = (string) ( $input['confirm'] ?? '' );
        $is_landlord = ! empty( $input['is_landlord'] );
        $terms       = ! empty( $input['terms'] );

        $errors = [];

        if ( '' === $first_name ) {
            $errors[] = __( 'First name is required.', 'ovr-core' );
        }
        if ( '' === $last_name ) {
            $errors[] = __( 'Last name is required.', 'ovr-core' );
        }
        if ( '' === $email || ! is_email( $email ) ) {
            $errors[] = __( 'Please enter a valid email address.', 'ovr-core' );
        } elseif ( email_exists( $email ) ) {
            // Case-insensitive where the DB collation is (standard WP installs).
            // Guidance included without disclosing any account details.
            $errors[] = __( 'An account with this email already exists. Please sign in or reset your password.', 'ovr-core' );
        }
        $phone_problem = self::phone_error( $phone );
        if ( '' !== $phone_problem ) {
            $errors[] = $phone_problem;
        }
        // Password policy (M3 F5 Security settings; falls back to 8-char min).
        $pw_error = class_exists( '\OVR\Core\SettingsBehaviors' )
            ? \OVR\Core\SettingsBehaviors::password_error( $password )
            : ( strlen( $password ) < 8 ? __( 'Password must be at least 8 characters.', 'ovr-core' ) : '' );
        if ( '' !== $pw_error ) {
            $errors[] = $pw_error;
        }
        if ( $password !== $confirm ) {
            $errors[] = __( 'Passwords do not match.', 'ovr-core' );
        }
        // Landlord / Property-Manager selection is ACCOUNT INTENT, not a
        // precondition for registering: both `ovr_is_landlord = 1` and `= 0`
        // are valid Step-1 states. The value is recorded as intent metadata
        // only and never grants a role, plan, capability, listing access or
        // payment state. SubscriptionManager::activate() grants ovr_landlord
        // after the subscription/payment workflow.
        if ( ! $terms ) {
            $errors[] = __( 'You must agree to the Terms of Service.', 'ovr-core' );
        }

        return [
            'errors' => $errors,
            'data'   => [
                'first_name'  => $first_name,
                'last_name'   => $last_name,
                'email'       => $email,
                'phone'       => $phone,
                'password'    => $password,
                'is_landlord' => $is_landlord,
            ],
        ];
    }

    /**
     * Create the WordPress user + OVR profile for validated registration data.
     *
     * Produces a REGISTERED / UNPAID account: default subscriber role, no plan,
     * subscription status `none`, no listing access, no payment records. The
     * ovr_landlord role is granted later by SubscriptionManager::activate()
     * after a paid plan is purchased.
     *
     * Partial-failure safety: the wp_users row is the one record WordPress
     * cannot create transactionally, so it is created first and never deleted
     * here (delete-and-recreate would risk orphaning data on retry). Every
     * downstream write failure is logged (user ID only — never secrets) and
     * flagged via `ovr_profile_init_incomplete` for admin repair, while the
     * valid account still logs in. Email-send failures likewise never destroy
     * the account; they are flagged for retry.
     *
     * @param array $clean Validated data from validate_registration()['data'].
     * @return int|\WP_Error New user ID, or WP_Error (duplicate, wp_create_user failure).
     */
    public static function create_account( array $clean ) {
        $email = (string) ( $clean['email'] ?? '' );

        // Re-check inside the creation path so two near-simultaneous requests
        // for the same email cannot both proceed past validation.
        if ( email_exists( $email ) ) {
            return new \WP_Error(
                'existing_user_email',
                __( 'An account with this email already exists. Please sign in or reset your password.', 'ovr-core' )
            );
        }

        // The email IS the login identity (user_login = email).
        $user_id = wp_create_user( $email, (string) ( $clean['password'] ?? '' ), $email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }
        $user_id = (int) $user_id;

        // Mandatory profile writes — tracked so a downstream failure can never
        // silently pass as a fully-initialized account.
        $profile_ok = true;

        $updated = wp_update_user( [
            'ID'           => $user_id,
            'first_name'   => (string) ( $clean['first_name'] ?? '' ),
            'last_name'    => (string) ( $clean['last_name'] ?? '' ),
            'display_name' => trim( (string) ( $clean['first_name'] ?? '' ) . ' ' . (string) ( $clean['last_name'] ?? '' ) ),
        ] );
        if ( is_wp_error( $updated ) ) {
            $profile_ok = false;
        }

        $meta = [
            'ovr_phone'            => (string) ( $clean['phone'] ?? '' ),
            'ovr_account_status'   => 'active',
            'ovr_account_type'     => 'private_person',
            'ovr_first_login'      => '1',
            'ovr_registered_at'    => current_time( 'mysql' ),
            UserSubscription::META_STATUS => UserSubscription::STATUS_NONE,
            // Landlord intent only — never a role. Public input cannot grant
            // capabilities; SubscriptionManager::activate() grants
            // ovr_landlord after payment.
            'ovr_is_landlord'      => ! empty( $clean['is_landlord'] ) ? '1' : '0',
            // Terms/Privacy consent record (Section 1).
            'ovr_terms_accepted_at'=> current_time( 'mysql' ),
            'ovr_terms_version'    => 'v1:user-agreement',
        ];
        foreach ( $meta as $key => $value ) {
            if ( false === update_user_meta( $user_id, $key, $value ) && '' === (string) get_user_meta( $user_id, $key, true ) && '' !== (string) $value ) {
                $profile_ok = false;
            }
        }

        if ( ! $profile_ok ) {
            update_user_meta( $user_id, 'ovr_profile_init_incomplete', '1' );
            error_log( 'OVR registration: profile init incomplete for user ' . $user_id );
        }

        // Email verification: mint a one-time token (hashed), mark unverified,
        // and email the confirm link. Login still works, but the account is
        // flagged until the visitor clicks the link. A send failure is flagged
        // for retry and never invalidates the account.
        $verify_token = wp_generate_password( 32, false );
        update_user_meta( $user_id, 'ovr_email_verified', '0' );
        update_user_meta( $user_id, 'ovr_email_verification_token', wp_hash_password( $verify_token ) );
        $verify_sent = Mailer::send( 'email_verification', [
            'user_name'  => (string) ( $clean['first_name'] ?? '' ),
            'verify_url' => self::verification_url( $user_id, $verify_token ),
        ], [ 'user_id' => $user_id ] );
        if ( ! $verify_sent ) {
            update_user_meta( $user_id, 'ovr_email_verification_pending', '1' );
            error_log( 'OVR registration: verification email failed for user ' . $user_id );
        }

        /**
         * Fires after successful OVR registration.
         *
         * @param int  $user_id     New user ID.
         * @param bool $is_landlord Whether user registered as landlord.
         */
        do_action( 'ovr_user_registered', $user_id, ! empty( $clean['is_landlord'] ) );

        return $user_id;
    }

    public static function get_errors(): array {
        $errors = get_transient( 'ovr_register_errors' );
        delete_transient( 'ovr_register_errors' );
        return $errors ?: [];
    }

    public static function get_old_data(): array {
        $data = get_transient( 'ovr_register_data' );
        delete_transient( 'ovr_register_data' );
        return $data ?: [];
    }

    public static function render(): string {
        // Already-logged-in users are redirected on template_redirect by
        // AuthRedirects::redirect_authed_users_away_from_auth_pages. By the
        // time this shortcode runs, output has already started, so a
        // header-based redirect here would warn. Render a graceful fallback.
        if ( is_user_logged_in() ) {
            $url = Pages::get_page_url( 'ovr_page_dashboard' );
            return '<p style="text-align:center;padding:32px">' .
                sprintf(
                    /* translators: %s: dashboard URL */
                    wp_kses( __( 'You are already signed in. Go to your <a href="%s">dashboard</a>.', 'ovr-core' ), [ 'a' => [ 'href' => [] ] ] ),
                    esc_url( $url )
                ) .
                '</p>';
        }

        return TemplateLoader::get_rendered( 'auth/register.php', [
            'errors'      => self::get_errors(),
            'old_data'    => self::get_old_data(),
            'login_url'   => Pages::get_page_url( 'ovr_page_login' ),
            'terms_url'   => self::terms_url(),
            'privacy_url' => self::privacy_url(),
        ] );
    }

    /**
     * Canonical Terms destination: the OVR User Agreement page.
     */
    public static function terms_url(): string {
        return Pages::get_page_url( 'ovr_page_user_agreement' );
    }

    /**
     * Canonical Privacy destination: the WP privacy policy page when one is
     * assigned, otherwise the OVR User Agreement page.
     */
    public static function privacy_url(): string {
        if ( function_exists( 'get_privacy_policy_url' ) ) {
            $url = (string) get_privacy_policy_url();
            if ( '' !== $url ) {
                return $url;
            }
        }
        return self::terms_url();
    }
}
