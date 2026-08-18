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

    public function process_registration(): void {
        if ( ! isset( $_POST['ovr_register_submit'] ) ) {
            return;
        }

        if ( ! isset( $_POST['ovr_register_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_register_nonce'] ) ), 'ovr_register_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ovr-core' ) );
        }

        $first_name = sanitize_text_field( wp_unslash( $_POST['ovr_first_name'] ?? '' ) );
        $last_name  = sanitize_text_field( wp_unslash( $_POST['ovr_last_name'] ?? '' ) );
        $email      = sanitize_email( wp_unslash( $_POST['ovr_email'] ?? '' ) );
        $phone      = sanitize_text_field( wp_unslash( $_POST['ovr_phone'] ?? '' ) );
        $password   = $_POST['ovr_password'] ?? '';
        $confirm    = $_POST['ovr_confirm_password'] ?? '';
        $is_landlord = ! empty( $_POST['ovr_is_landlord'] );
        $terms      = ! empty( $_POST['ovr_terms'] );

        $errors = [];

        if ( empty( $first_name ) ) {
            $errors[] = __( 'First name is required.', 'ovr-core' );
        }
        if ( empty( $last_name ) ) {
            $errors[] = __( 'Last name is required.', 'ovr-core' );
        }
        if ( empty( $email ) || ! is_email( $email ) ) {
            $errors[] = __( 'Please enter a valid email address.', 'ovr-core' );
        }
        if ( email_exists( $email ) ) {
            $errors[] = __( 'An account with this email already exists.', 'ovr-core' );
        }
        // Password policy (M3 F5 Security settings; falls back to 8-char min).
        $pw_error = class_exists( '\OVR\Core\SettingsBehaviors' )
            ? \OVR\Core\SettingsBehaviors::password_error( (string) $password )
            : ( strlen( (string) $password ) < 8 ? __( 'Password must be at least 8 characters.', 'ovr-core' ) : '' );
        if ( '' !== $pw_error ) {
            $errors[] = $pw_error;
        }
        if ( $password !== $confirm ) {
            $errors[] = __( 'Passwords do not match.', 'ovr-core' );
        }
        if ( ! $terms ) {
            $errors[] = __( 'You must agree to the Terms of Service.', 'ovr-core' );
        }

        if ( ! empty( $errors ) ) {
            set_transient( 'ovr_register_errors', $errors, 60 );
            set_transient( 'ovr_register_data', [
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $email,
                'phone'      => $phone,
                'is_landlord'=> $is_landlord,
            ], 60 );
            return;
        }

        // Create user.
        $user_id = wp_create_user( $email, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            set_transient( 'ovr_register_errors', [ $user_id->get_error_message() ], 60 );
            return;
        }

        // Set user meta.
        wp_update_user( [
            'ID'         => $user_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ] );

        update_user_meta( $user_id, 'ovr_phone', $phone );
        update_user_meta( $user_id, 'ovr_account_status', 'active' );
        update_user_meta( $user_id, 'ovr_first_login', '1' );
        update_user_meta( $user_id, 'ovr_registered_at', current_time( 'mysql' ) );
        update_user_meta( $user_id, UserSubscription::META_STATUS, UserSubscription::STATUS_NONE );

        // User stays as a subscriber until they purchase a plan; the
        // ovr_landlord role (with its capabilities) is granted in
        // SubscriptionManager::activate() when they choose a plan. Record
        // their landlord intent so onboarding/subscription can use it.
        update_user_meta( $user_id, 'ovr_is_landlord', $is_landlord ? '1' : '0' );

        // Email verification: mint a one-time token (hashed), mark unverified,
        // and email the confirm link. Login still works, but the account is
        // flagged until the visitor clicks the link.
        $verify_token = wp_generate_password( 32, false );
        update_user_meta( $user_id, 'ovr_email_verified', '0' );
        update_user_meta( $user_id, 'ovr_email_verification_token', wp_hash_password( $verify_token ) );
        Mailer::send( 'email_verification', [
            'user_name'  => $first_name,
            'verify_url' => self::verification_url( $user_id, $verify_token ),
        ], [ 'user_id' => $user_id ] );

        // Auto-login.
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );

        /**
         * Fires after successful OVR registration.
         *
         * @param int  $user_id     New user ID.
         * @param bool $is_landlord Whether user registered as landlord.
         */
        do_action( 'ovr_user_registered', $user_id, $is_landlord );

        // Brand-new user → onboarding flow. The onboarding template clears the
        // first-login flag on render, so subsequent visits land on the
        // dashboard. The subscription gate then steers them to plan selection.
        wp_safe_redirect( Pages::get_page_url( 'ovr_page_onboarding' ) );
        exit;
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
            'errors'   => self::get_errors(),
            'old_data' => self::get_old_data(),
            'login_url'=> Pages::get_page_url( 'ovr_page_login' ),
        ] );
    }
}
