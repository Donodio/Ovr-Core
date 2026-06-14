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
use OVR\Subscription\UserSubscription;

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
            // Logged-in users are bounced away from the login/register/forgot
            // screens — they have nothing to do there. The onboarding page is
            // intentionally NOT in this list: it controls its own copy and
            // shows a neutral greeting when a returning landlord lands on it,
            // so it's safe to leave accessible to logged-in users. (See
            // OVR\Frontend\Onboarding::render().)
            (int) get_option( 'ovr_page_login' ),
            (int) get_option( 'ovr_page_register' ),
            (int) get_option( 'ovr_page_forgot_password' ),
        ] );

        if ( $auth_pages && is_page( $auth_pages ) ) {
            // Admins belong in wp-admin. Landlords go to their dashboard only if
            // they have an active paid subscription — otherwise to plan selection.
            if ( current_user_can( 'manage_options' ) ) {
                $destination = admin_url();
            } elseif ( UserSubscription::has_listing_access() ) {
                $destination = Pages::get_page_url( 'ovr_page_dashboard' );
            } else {
                $destination = Pages::get_page_url( 'ovr_page_subscription_select' );
            }
            wp_safe_redirect( $destination );
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

        // Two separate doors:
        //   • Site owner / admin → native wp-login.php + /wp-admin (this method
        //     lets those requests through untouched).
        //   • Landlords / renters → branded /login/ page.
        //
        // The explicit ?admin=1 entry point keeps wp-login.php reachable (bookmark
        // it as the admin door), and a logged-in admin visiting wp-login.php
        // directly (e.g. to switch accounts) is never bounced to the landlord page.
        if ( isset( $_GET['admin'] ) ) {
            return;
        }
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            return;
        }

        // Any login bound for the admin area stays on the native screen. This is
        // what fires when a logged-out site owner opens /wp-admin: WordPress sends
        // them here with redirect_to pointing at wp-admin, and we let it render.
        $redirect_to = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( (string) $_REQUEST['redirect_to'] ) : '';
        if ( $this->targets_admin_area( $redirect_to ) ) {
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
        // Keep the admin door native: a login aimed at wp-admin (e.g. the site
        // owner opening the dashboard while logged out) uses wp-login.php, not the
        // branded landlord page.
        if ( $this->targets_admin_area( $redirect ) ) {
            return $login_url;
        }

        $url = Pages::get_page_url( 'ovr_page_login' );
        if ( ! empty( $redirect ) ) {
            $url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
        }
        return $url;
    }

    /**
     * Whether a redirect target points at the WordPress admin area.
     *
     * Handles both absolute URLs (https://site/wp-admin/…) and bare paths
     * (/wp-admin/…), which is what core hands us in different code paths.
     */
    private function targets_admin_area( string $url ): bool {
        if ( '' === $url ) {
            return false;
        }
        $url = html_entity_decode( $url );

        if ( 0 === strpos( $url, admin_url() ) ) {
            return true;
        }

        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        return ( '' !== $path && false !== strpos( $path, '/wp-admin' ) );
    }

    public function custom_register_url( string $url ): string {
        return Pages::get_page_url( 'ovr_page_register' );
    }

    public function custom_lostpassword_url( string $url, string $redirect ): string {
        return Pages::get_page_url( 'ovr_page_forgot_password' );
    }
}
