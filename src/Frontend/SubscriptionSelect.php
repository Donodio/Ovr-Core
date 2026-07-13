<?php
/**
 * Subscription Select screen.
 *
 * The forced stop for landlords without an active paid subscription: pick a
 * plan, then continue to payment. Rendered by the [ovr_subscription_select]
 * shortcode on the /subscription-select/ page.
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

use OVR\Core\Pages;
use OVR\Core\TemplateLoader;
use OVR\Subscription\AccessControl;
use OVR\Subscription\Plans;
use OVR\Subscription\UserSubscription;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SubscriptionSelect {

    public function init(): void {}

    public static function render(): string {
        if ( ! is_user_logged_in() ) {
            return '<p style="text-align:center;padding:32px">' .
                sprintf(
                    wp_kses( __( 'Please <a href="%s">sign in</a> to choose a subscription.', 'ovr-core' ), [ 'a' => [ 'href' => [] ] ] ),
                    esc_url( Pages::get_page_url( 'ovr_page_login' ) )
                ) . '</p>';
        }

        $user = wp_get_current_user();

        // Already subscribed (or admin) — nothing to do here.
        if ( AccessControl::user_has_access( $user->ID ) ) {
            return '<p style="text-align:center;padding:32px">' .
                sprintf(
                    wp_kses( __( 'Your subscription is active. Go to your <a href="%s">dashboard</a>.', 'ovr-core' ), [ 'a' => [ 'href' => [] ] ] ),
                    esc_url( Pages::get_page_url( 'ovr_page_dashboard' ) )
                ) . '</p>';
        }

        // Paid, active plans only — a free tier grants no landlord access.
        $plans = array_filter(
            Plans::get_plans(),
            static fn( $p ) => ! empty( $p['is_active'] ) && (float) ( $p['price'] ?? 0 ) > 0
        );
        uasort( $plans, static fn( $a, $b ) => ( (int) ( $a['sort_order'] ?? 0 ) ) <=> ( (int) ( $b['sort_order'] ?? 0 ) ) );

        $sub_status = UserSubscription::get_status( $user->ID );
        $is_expired  = ( UserSubscription::STATUS_EXPIRED === $sub_status );
        $is_pending  = ( UserSubscription::STATUS_PENDING === $sub_status );

        return TemplateLoader::get_rendered( 'auth/subscription-select.php', [
            'user'         => $user,
            'plans'        => $plans,
            'checkout_url' => Pages::get_page_url( 'ovr_page_checkout' ),
            'logout_url'   => wp_logout_url( Pages::get_page_url( 'ovr_page_login' ) ),
            'is_expired'   => $is_expired,
            'is_pending'   => $is_pending,
        ] );
    }
}
