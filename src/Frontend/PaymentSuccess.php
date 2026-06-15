<?php
/**
 * Payment Success / Thank-You page renderer.
 *
 * Landing page after checkout. Loads the user's payment row by id and renders
 * an order summary. Messaging adapts to the real payment status — "Payment
 * Successful" for completed payments, "Order Received" for the Phase-1 pending
 * stubs (admin follows up).
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Core\Pages;
use OVR\Subscription\Plans;
use OVR\Subscription\ListingUpgrades;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PaymentSuccess {

    public function init(): void {}

    public static function render(): string {
        if ( ! is_user_logged_in() ) {
            return self::notice(
                __( 'Please sign in to view your order.', 'ovr-core' ),
                Pages::get_page_url( 'ovr_page_login' ),
                __( 'Sign in', 'ovr-core' )
            );
        }

        $payment_id = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : 0;
        $user_id    = get_current_user_id();

        global $wpdb;
        $payment = $payment_id
            ? $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d AND user_id = %d",
                    $payment_id,
                    $user_id
                ),
                ARRAY_A
            )
            : null;

        if ( ! $payment ) {
            return self::notice(
                __( 'We could not find that order.', 'ovr-core' ),
                Pages::get_page_url( 'ovr_page_dashboard' ),
                __( 'Go to dashboard', 'ovr-core' )
            );
        }

        $settings = (array) get_option( 'ovr_settings', [] );
        $meta     = json_decode( (string) ( $payment['meta_data'] ?? '' ), true ) ?: [];

        // Resolve the purchased item's label.
        $item_label = __( 'Subscription', 'ovr-core' );
        if ( ! empty( $meta['plan_slug'] ) ) {
            $plan       = Plans::get_plan( (string) $meta['plan_slug'] );
            $item_label = $plan['name'] ?? $item_label;
        } elseif ( ! empty( $meta['upgrade'] ) ) {
            $product    = ListingUpgrades::get_product( (string) $meta['upgrade'] );
            $term       = (int) ( $meta['term'] ?? 14 );
            /* translators: 1: upgrade name, 2: term in days */
            $item_label = $product
                ? sprintf( __( '%1$s (%2$d-day)', 'ovr-core' ), $product['name'], $term )
                : __( 'Listing Upgrade', 'ovr-core' );
        }

        $gateways = [
            'stripe'        => __( 'Card (Stripe)', 'ovr-core' ),
            'paypal'        => __( 'PayPal', 'ovr-core' ),
            'authorize_net' => __( 'Card (Authorize.net)', 'ovr-core' ),
            'wallet'        => __( 'OVR Balance', 'ovr-core' ),
            'free'          => __( 'Free Plan', 'ovr-core' ),
        ];
        $gateway = (string) ( $payment['gateway'] ?? '' );

        return TemplateLoader::get_rendered( 'pages/payment-success.php', [
            'status'         => (string) ( $payment['status'] ?? 'pending' ),
            'order_no'       => ! empty( $payment['transaction_id'] )
                ? (string) $payment['transaction_id']
                : 'OVR-' . str_pad( (string) $payment['id'], 6, '0', STR_PAD_LEFT ),
            'date'           => mysql2date( get_option( 'date_format' ) ?: 'M j, Y', (string) $payment['created_at'] ),
            'amount'         => ( $settings['currency_symbol'] ?? '$' ) . number_format( (float) $payment['amount'], 2 ),
            'is_upgrade'     => ! empty( $meta['upgrade'] ),
            'item_label'     => $item_label,
            'gateway_label'  => $gateways[ $gateway ] ?? ucwords( str_replace( '_', ' ', $gateway ) ),
            'gateway_icon'   => 'wallet' === $gateway ? 'account_balance_wallet' : ( 'paypal' === $gateway ? 'account_balance' : 'credit_card' ),
            'listings_url'   => add_query_arg( 'tab', 'properties', Pages::get_page_url( 'ovr_page_dashboard' ) ),
            'subscription_url' => add_query_arg( 'tab', 'subscription', Pages::get_page_url( 'ovr_page_dashboard' ) ),
        ] );
    }

    private static function notice( string $message, string $url, string $cta ): string {
        return sprintf(
            '<div class="ovr-wrap" style="font-family:Inter,system-ui,sans-serif;max-width:520px;margin:64px auto;padding:40px 28px;text-align:center;background:#fff;border:1px solid #bec9c8;border-radius:16px">'
            . '<p style="font-size:18px;color:#181c1c;margin:0 0 20px">%1$s</p>'
            . '<a href="%2$s" style="display:inline-block;background:#004c4c;color:#fff;text-decoration:none;font-weight:600;padding:12px 28px;border-radius:10px">%3$s</a>'
            . '</div>',
            esc_html( $message ),
            esc_url( $url ),
            esc_html( $cta )
        );
    }
}
