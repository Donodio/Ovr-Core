<?php
/**
 * Access Control — centralized authorization layer for all landlord features.
 *
 * Every protected route (dashboard, listings, calendar, messages, etc.)
 * MUST pass through this class. Never duplicate the subscription check.
 *
 * @package OVR\Subscription
 * @since   2.0.0
 */

namespace OVR\Subscription;

use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AccessControl {

    /**
     * Whether the current user may access landlord features.
     * Admins always pass. Everyone else needs an active paid subscription.
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
     * Redirect the user away if they don't have access.
     * Safe to call at the top of any protected route. Exits if redirect needed.
     */
    public static function require_active_subscription(): void {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_login' ) );
            exit;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! self::user_has_access() ) {
            $redirect = SubscriptionManager::get_redirect_by_status();
            if ( $redirect ) {
                wp_safe_redirect( $redirect );
                exit;
            }
        }
    }

    /**
     * Return a WP_Error or true for AJAX/REST endpoints (no redirect).
     *
     * @param int $user_id
     * @return true|\WP_Error
     */
    public static function check_access( int $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( self::user_has_access( $user_id ) ) {
            return true;
        }
        return new \WP_Error(
            'subscription_required',
            __( 'Your subscription is inactive. Please renew or purchase a subscription to continue.', 'ovr-core' )
        );
    }

    /**
     * Protected page IDs that require an active subscription.
     *
     * @return int[]
     */
    public static function get_protected_page_ids(): array {
        return array_filter( [
            (int) get_option( 'ovr_page_dashboard' ),
        ] );
    }
}
