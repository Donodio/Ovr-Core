<?php
/**
 * Subscription Gate.
 *
 * Enforces the rule: no active paid subscription => no landlord access.
 *
 * This is the WordPress equivalent of route middleware. It runs on
 * `template_redirect` (before any landlord page renders) and bounces logged-in
 * non-admin users who lack an active paid plan to the subscription-select page.
 * Pages required to actually *buy* a subscription (select / checkout / success /
 * pricing) and the auth pages are exempt so the user can complete payment.
 *
 * @package OVR\Subscription
 * @since   1.0.0
 */

namespace OVR\Subscription;

use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SubscriptionGate {

    public function init(): void {
        add_action( 'template_redirect', [ $this, 'guard' ], 1 );
    }

    /**
     * URL of the subscription-select screen (the gate destination).
     */
    public static function select_url(): string {
        return Pages::get_page_url( 'ovr_page_subscription_select' );
    }

    /**
     * Whether the current user may use landlord tools. Admins always may.
     */
    public static function user_has_access( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return false;
        }
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }
        return UserSubscription::has_listing_access( $user_id );
    }

    /**
     * Bounce unpaid landlords away from landlord pages.
     */
    public function guard(): void {
        if ( ! is_user_logged_in() || is_admin() ) {
            return;
        }
        // Site owner/admins are never gated.
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }
        // Active paid subscribers pass through.
        if ( UserSubscription::has_listing_access() ) {
            return;
        }

        // Page IDs that require an active subscription. The onboarding
        // page is intentionally exempt — brand-new landlords haven't had
        // a chance to subscribe yet, and the welcome screen explains
        // their options (including choosing a plan).
        $gated = array_filter( [
            (int) get_option( 'ovr_page_dashboard' ),
        ] );

        if ( ! $gated || ! is_page( $gated ) ) {
            return;
        }

        wp_safe_redirect( add_query_arg( 'subscribe', 'required', self::select_url() ) );
        exit;
    }
}
