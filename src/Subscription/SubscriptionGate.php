<?php
/**
 * Subscription Gate.
 *
 * Enforces the rule: no active paid subscription => no landlord access.
 *
 * Runs on `template_redirect` before any landlord page renders. Bounces
 * logged-in non-admin users who lack an active paid subscription to the
 * subscription workflow. Auth, checkout, pricing, and onboarding pages
 * are exempt so the user can complete payment.
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
        return AccessControl::user_has_access( $user_id );
    }

    /**
     * Bounce unpaid landlords away from protected pages.
     */
    public function guard(): void {
        if ( ! is_user_logged_in() || is_admin() ) {
            return;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        // Pages that are part of the subscription purchase/auth flow — exempt.
        $exempt = array_filter( [
            (int) get_option( 'ovr_page_subscription_select' ),
            (int) get_option( 'ovr_page_checkout' ),
            (int) get_option( 'ovr_page_pricing' ),
            (int) get_option( 'ovr_page_payment_success' ),
            (int) get_option( 'ovr_page_login' ),
            (int) get_option( 'ovr_page_register' ),
            (int) get_option( 'ovr_page_forgot_password' ),
            (int) get_option( 'ovr_page_onboarding' ),
        ] );

        if ( $exempt && is_page( $exempt ) ) {
            return;
        }

        // Only check on protected plugin pages.
        $protected = AccessControl::get_protected_page_ids();
        if ( ! $protected || ! is_page( $protected ) ) {
            return;
        }

        // Check access via centralized status system.
        if ( ! AccessControl::user_has_access() ) {
            $redirect = SubscriptionManager::get_redirect_by_status();
            wp_safe_redirect( $redirect ?: home_url() );
            exit;
        }
    }
}
