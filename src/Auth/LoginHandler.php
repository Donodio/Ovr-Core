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
use OVR\Frontend\ProfileCompletion;
use OVR\Subscription\UserSubscription;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LoginHandler {

    public function init(): void {
        add_action( 'init', [ $this, 'process_login' ] );
    }

    /**
     * Process login form submission.
     *
     * Onboarding behaviour (per Group 1 of the OVR site-chrome fix):
     *   • Freshly-registered user (ovr_first_login === '1' AND profile < 100%)
     *     → redirect to /welcome/ once. The onboarding template clears the
     *     flag on render, so subsequent visits go straight to dashboard.
     *   • Returning user OR user whose profile is already complete
     *     → redirect straight to the dashboard. Onboarding is NEVER shown
     *     just because someone is logging in.
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
            // Surface the security challenges verbatim so privileged users are
            // never stuck behind a generic "invalid credentials" message:
            //   • ovr_2fa_required  → an emailed one-time code is expected.
            //   • ovr_locked_out    → the IP is temporarily throttled.
            // Everything else stays generic to avoid account enumeration.
            $message = __( 'Invalid email or password. Please try again.', 'ovr-core' );
            foreach ( [ 'ovr_2fa_required', 'ovr_locked_out' ] as $code ) {
                $specific = $user->get_error_message( $code );
                if ( is_string( $specific ) && '' !== $specific ) {
                    $message = $specific;
                    break;
                }
            }
            $this->store_errors( [ $message ] );
            return;
        }

        // Check account status.
        $account_status = get_user_meta( $user->ID, 'ovr_account_status', true );
        if ( 'inactive' === $account_status ) {
            wp_logout();
            $this->store_errors( [ __( 'Your account has been deactivated. Please contact support.', 'ovr-core' ) ] );
            return;
        }

        // Admins belong in wp-admin. Never funnel the site owner or an
        // administrator into the landlord/renter membership flow (subscription
        // selection, dashboard, onboarding) just because they signed in through
        // the front-end login page.
        if ( user_can( $user, 'manage_options' ) ) {
            $redirect = apply_filters( 'ovr_login_redirect', admin_url(), $user );
            wp_safe_redirect( $redirect );
            exit;
        }

        // Subscription-based redirect. Status determines where the user goes.
        $status   = UserSubscription::get_status( $user->ID );
        $redirect = '';

        switch ( $status ) {
            case UserSubscription::STATUS_NONE:
                $redirect = Pages::get_page_url( 'ovr_page_subscription_select' );
                break;
            case UserSubscription::STATUS_PENDING:
                $redirect = add_query_arg(
                    'payment', 'pending',
                    Pages::get_page_url( 'ovr_page_subscription_select' )
                );
                break;
            case UserSubscription::STATUS_EXPIRED:
            case UserSubscription::STATUS_CANCELLED:
                $redirect = add_query_arg(
                    'renew', 'required',
                    Pages::get_page_url( 'ovr_page_subscription_select' )
                );
                break;
            case UserSubscription::STATUS_SUSPENDED:
                $redirect = add_query_arg( 'suspended', '1', home_url() );
                break;
            case UserSubscription::STATUS_ACTIVE:
                // Active subscriber — check onboarding for first login.
                $profile_complete = (int) ProfileCompletion::percent( $user->ID );
                $is_first_login   = (string) get_user_meta( $user->ID, 'ovr_first_login', true ) === '1';
                if ( $is_first_login && $profile_complete < 100 ) {
                    $redirect = Pages::get_page_url( 'ovr_page_onboarding' );
                } else {
                    $redirect = add_query_arg(
                        [ 'tab' => isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : '' ],
                        Pages::get_page_url( 'ovr_page_dashboard', true )
                    );
                }
                break;
        }

        if ( ! $redirect ) {
            $redirect = Pages::get_page_url( 'ovr_page_subscription_select' );
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
            'errors'      => self::get_errors(),
            'login_url'   => Pages::get_page_url( 'ovr_page_login' ),
            'register_url' => Pages::get_page_url( 'ovr_page_register' ),
            'forgot_url'  => Pages::get_page_url( 'ovr_page_forgot_password' ),
            'enable_2fa'  => ! ( defined( 'OVR_DISABLE_2FA' ) && OVR_DISABLE_2FA )
                && ! empty( (array) get_option( 'ovr_settings', [] )['enable_2fa'] ),
        ] );
    }
}
