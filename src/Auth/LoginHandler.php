<?php
/**
 * Login Handler.
 *
 * Processes custom login form submissions, validates credentials,
 * and handles redirects.
 *
 * @package OVR\Auth
 * @since   1.0.0
 */

namespace OVR\Auth;

use OVR\Core\Pages;
use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LoginHandler {

    public function init(): void {
        add_action( 'init', [ $this, 'process_login' ] );
    }

    /**
     * Process login form submission.
     */
    public function process_login(): void {
        if ( ! isset( $_POST['ovr_login_submit'] ) ) {
            return;
        }

        // Verify nonce.
        if ( ! isset( $_POST['ovr_login_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_login_nonce'] ) ), 'ovr_login_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ovr-core' ) );
        }

        $email    = sanitize_email( wp_unslash( $_POST['ovr_email'] ?? '' ) );
        $password = isset( $_POST['ovr_password'] ) ? $_POST['ovr_password'] : '';
        $remember = ! empty( $_POST['ovr_remember'] );

        // Validate inputs.
        $errors = [];

        if ( empty( $email ) ) {
            $errors[] = __( 'Please enter your email address.', 'ovr-core' );
        }

        if ( empty( $password ) ) {
            $errors[] = __( 'Please enter your password.', 'ovr-core' );
        }

        if ( ! empty( $errors ) ) {
            $this->store_errors( $errors );
            return;
        }

        // Attempt login.
        $user = wp_signon( [
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => $remember,
        ] );

        if ( is_wp_error( $user ) ) {
            $this->store_errors( [ __( 'Invalid email or password. Please try again.', 'ovr-core' ) ] );
            return;
        }

        // Check account status.
        $account_status = get_user_meta( $user->ID, 'ovr_account_status', true );
        if ( 'inactive' === $account_status ) {
            wp_logout();
            $this->store_errors( [ __( 'Your account has been deactivated. Please contact support.', 'ovr-core' ) ] );
            return;
        }

        // Determine redirect.
        $is_first_login = get_user_meta( $user->ID, 'ovr_first_login', true );
        if ( ! $is_first_login || '1' === $is_first_login ) {
            update_user_meta( $user->ID, 'ovr_first_login', '0' );
            $redirect = Pages::get_page_url( 'ovr_page_onboarding' );
        } else {
            $redirect = Pages::get_page_url( 'ovr_page_dashboard' );
        }

        /**
         * Filter login redirect URL.
         *
         * @param string   $redirect Redirect URL.
         * @param \WP_User $user     Logged-in user.
         */
        $redirect = apply_filters( 'ovr_login_redirect', $redirect, $user );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Store login errors in a transient for display.
     */
    private function store_errors( array $errors ): void {
        set_transient( 'ovr_login_errors', $errors, 60 );
    }

    /**
     * Get and clear stored errors.
     */
    public static function get_errors(): array {
        $errors = get_transient( 'ovr_login_errors' );
        delete_transient( 'ovr_login_errors' );
        return $errors ?: [];
    }

    /**
     * Render the login form via shortcode.
     */
    public static function render(): string {
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

        return TemplateLoader::get_rendered( 'auth/login.php', [
            'errors'     => self::get_errors(),
            'login_url'  => Pages::get_page_url( 'ovr_page_login' ),
            'register_url' => Pages::get_page_url( 'ovr_page_register' ),
            'forgot_url' => Pages::get_page_url( 'ovr_page_forgot_password' ),
        ] );
    }
}
