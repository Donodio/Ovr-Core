<?php
/**
 * Auth Redirects.
 *
 * Redirects wp-login.php to custom OVR pages and controls access.
 *
 * @package OVR\Auth
 * @since   1.0.0
 */

namespace OVR\Auth;

use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuthRedirects {

    public function init(): void {
        add_action( 'login_init', [ $this, 'redirect_wp_login' ] );
        add_filter( 'logout_redirect', [ $this, 'logout_redirect' ], 10, 3 );
        add_filter( 'login_url', [ $this, 'custom_login_url' ], 10, 3 );
        add_filter( 'register_url', [ $this, 'custom_register_url' ] );
        add_filter( 'lostpassword_url', [ $this, 'custom_lostpassword_url' ], 10, 2 );

        // Redirect already-logged-in users away from auth pages BEFORE the
        // template renders. This avoids "headers already sent" warnings that
        // happen when other plugins (e.g. Elementor) buffer output earlier in
        // the page lifecycle and our shortcode-time redirect fires too late.
        add_action( 'template_redirect', [ $this, 'redirect_authed_users_away_from_auth_pages' ] );
    }

    /**
     * If a logged-in user lands on /login/, /register/, or /forgot-password/,
     * push them straight to the dashboard.
     */
    public function redirect_authed_users_away_from_auth_pages(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        // Don't interfere with the post-submit redirect chains.
        if ( ! is_page() ) {
            return;
        }

        $auth_pages = array_filter( [
            (int) get_option( 'ovr_page_login' ),
            (int) get_option( 'ovr_page_register' ),
            (int) get_option( 'ovr_page_forgot_password' ),
        ] );

        if ( $auth_pages && is_page( $auth_pages ) ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_dashboard' ) );
            exit;
        }
    }

    /**
     * Redirect wp-login.php to custom login page (except for admins and AJAX).
     */
    public function redirect_wp_login(): void {
        // Don't redirect admin users, AJAX requests, or POST login submissions.
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

        // Allow specific wp-login actions to pass through.
        $allowed_actions = [ 'logout', 'postpass', 'rp', 'resetpass', 'confirmaction' ];
        if ( in_array( $action, $allowed_actions, true ) ) {
            return;
        }

        // Redirect registration.
        if ( 'register' === $action ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_register' ) );
            exit;
        }

        // Redirect forgot password.
        if ( 'lostpassword' === $action ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_forgot_password' ) );
            exit;
        }

        // Redirect login page (only for non-POST requests).
        if ( 'GET' === $_SERVER['REQUEST_METHOD'] && empty( $action ) ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_login' ) );
            exit;
        }
    }

    /**
     * Redirect after logout to custom login page.
     */
    public function logout_redirect( string $redirect_to, string $requested, \WP_User $user ): string {
        return add_query_arg( 'logged_out', '1', Pages::get_page_url( 'ovr_page_login' ) );
    }

    public function custom_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
        $url = Pages::get_page_url( 'ovr_page_login' );
        if ( ! empty( $redirect ) ) {
            $url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
        }
        return $url;
    }

    public function custom_register_url( string $url ): string {
        return Pages::get_page_url( 'ovr_page_register' );
    }

    public function custom_lostpassword_url( string $url, string $redirect ): string {
        return Pages::get_page_url( 'ovr_page_forgot_password' );
    }
}
