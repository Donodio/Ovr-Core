<?php
/**
 * Checkout page renderer.
 *
 * Review-and-pay step for a subscription plan. Renders the order summary + a
 *  payment form, then hands off to the existing `ovr_start_checkout` flow
 *  (CheckoutHandler) on submit. Card fields in the template are display-only and
 *  are never submitted to the server — card capture is delegated to the
 *  provider's hosted checkout (Authorize.Net / PayPal).
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Core\Pages;
use OVR\Subscription\Plans;
use OVR\Subscription\ListingUpgrades;
use OVR\Payment\Wallet;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Checkout {

    public function init(): void {}

    public static function render(): string {
        // Shortcodes render mid-content (headers already sent), so we cannot
        // wp_safe_redirect here — return a graceful prompt instead.
        if ( ! is_user_logged_in() ) {
            return self::notice(
                __( 'Please sign in to continue to checkout.', 'ovr-core' ),
                Pages::get_page_url( 'ovr_page_login' ),
                __( 'Sign in', 'ovr-core' )
            );
        }

        $order       = null;
        $cancel_url  = add_query_arg( 'tab', 'subscription', Pages::get_page_url( 'ovr_page_dashboard' ) );

        // Subscription plan checkout.
        // Section 2: an authoritative offer handed off from subscription selection.
        $offer_id = sanitize_text_field( wp_unslash( $_GET['offer_id'] ?? '' ) );
        if ( '' !== $offer_id ) {
            $offer = \OVR\Subscription\SubscriptionOffer::resolve_for_checkout( $offer_id, get_current_user_id() );
            if ( is_wp_error( $offer ) ) {
                return self::notice(
                    $offer->get_error_message(),
                    Pages::get_page_url( 'ovr_page_subscription_select' ),
                    __( 'Choose a plan', 'ovr-core' )
                );
            }
            $ctx   = (string) $offer['purchase_context'];
            $order = [
                'type'             => 'plan',
                'purchase_context' => $ctx,
                'eyebrow'          => ( 'renewal' === $ctx ) ? __( 'Subscription Renewal', 'ovr-core' ) : __( 'Subscription', 'ovr-core' ),
                /* translators: %d: number of days */
                'sub'              => sprintf( __( '%d-day term', 'ovr-core' ), (int) $offer['final_duration_days'] ),
                'name'             => (string) $offer['plan_name'],
                'regular_price'    => (float) $offer['base_price'],
                'price'            => (float) $offer['final_price'],
                'discount'         => max( 0.0, (float) $offer['base_price'] - (float) $offer['final_price'] ),
                'duration_days'    => (int) $offer['final_duration_days'],
                'base_duration_days' => (int) $offer['base_duration_days'],
                'fields'           => [
                    'plan'         => (string) $offer['plan_slug'],
                    'ovr_offer_id' => (string) $offer['offer_id'],
                ],
            ];
            if ( ! empty( $offer['promo_code'] ) ) {
                $order['fields']['promo_code'] = (string) $offer['promo_code'];
            }
        } elseif ( ! empty( $_GET['plan'] ) ) {
            $slug = sanitize_key( wp_unslash( $_GET['plan'] ) );
            $plan = Plans::get_plan( $slug );
            if ( $plan && ! empty( $plan['is_active'] ) ) {
                // Single canonical calculation (same engine as Section 2).
                $offer = \OVR\Subscription\SubscriptionOffer::build(
                    get_current_user_id(),
                    $slug,
                    'new',
                    sanitize_text_field( wp_unslash( $_GET['promo_code'] ?? '' ) )
                );
                if ( is_wp_error( $offer ) ) {
                    $offer = null;
                }
                if ( $offer ) {
                    $order = [
                        'type'             => 'plan',
                        'purchase_context' => (string) ( $offer['purchase_context'] ?? 'new' ),
                        'eyebrow'          => __( 'Subscription', 'ovr-core' ),
                        'sub'              => ( 'annually' === ( $plan['period'] ?? 'monthly' ) || 'yearly' === ( $plan['period'] ?? '' ) )
                            ? __( 'Annual Subscription', 'ovr-core' )
                            : __( 'Monthly Subscription', 'ovr-core' ),
                        'name'             => (string) ( $plan['name'] ?? '' ),
                        'regular_price'    => (float) $offer['base_price'],
                        'price'            => (float) $offer['final_price'],
                        'discount'         => max( 0.0, (float) $offer['base_price'] - (float) $offer['final_price'] ),
                        'duration_days'    => (int) $offer['final_duration_days'],
                        'base_duration_days' => (int) $offer['base_duration_days'],
                        'fields'           => [ 'plan' => $slug, 'ovr_checkout_intent' => wp_generate_uuid4() ],
                    ];
                    if ( ! empty( $offer['promo_code'] ) ) {
                        $order['fields']['promo_code'] = (string) $offer['promo_code'];
                    }
                }
            }
        // Listing upgrade checkout. A boost must target a specific listing the
        // buyer owns — that context arrives via the listing's "Bump" button.
        } elseif ( ! empty( $_GET['service'] ) || ! empty( $_GET['upgrade'] ) ) {
            $id      = sanitize_title( wp_unslash( $_GET['service'] ?? $_GET['upgrade'] ) );
            $product = ListingUpgrades::get_product( $id );
            $term    = (int) ( $product['duration_days'] ?? 14 );

            $property_id = isset( $_GET['property'] ) ? absint( $_GET['property'] ) : 0;
            $property    = $property_id ? get_post( $property_id ) : null;
            // A landlord may only boost their own listing; administrators are
            // authorized to manage any listing (admin override).
            $owns        = $property
                && 'ovr_property' === $property->post_type
                && ( (int) $property->post_author === get_current_user_id() || current_user_can( 'manage_options' ) );

            // Upgrade selected but no (valid) listing → guide them to Bump.
            if ( $product && ! $owns ) {
                return self::notice(
                    __( 'Choose which listing to boost: open My Listings and click “Bump” on the property you want to promote.', 'ovr-core' ),
                    add_query_arg( 'tab', 'properties', Pages::get_page_url( 'ovr_page_dashboard' ) ),
                    __( 'Go to My Listings', 'ovr-core' )
                );
            }

            if ( $product && $owns ) {
                $thumb_url = get_the_post_thumbnail_url( $property_id, 'thumbnail' );
                $order = [
                    'type'    => 'upgrade',
                    'eyebrow' => __( 'Listing Upgrade', 'ovr-core' ),
                    'name'    => (string) ( $product['name'] ?? '' ),
                    /* translators: 1: term in days, 2: listing title */
                    'sub'     => sprintf( __( '%1$d-Day Boost · %2$s', 'ovr-core' ), $term, $property->post_title ?: __( 'your listing', 'ovr-core' ) ),
                    'price'   => ListingUpgrades::price_for( $product, $term ),
                    'thumb'   => $thumb_url ?: '',
                    'fields'  => [ 'upgrade' => $id, 'term' => (string) $term, 'property_id' => (string) $property_id, 'ovr_checkout_intent' => wp_generate_uuid4() ],
                ];
                $cancel_url = add_query_arg( 'tab', 'upgrades', Pages::get_page_url( 'ovr_page_dashboard' ) );
            }
        }

        // Nothing valid to buy → guide the user back.
        if ( null === $order ) {
            return self::notice(
                __( 'Select a plan or upgrade to check out.', 'ovr-core' ),
                add_query_arg( 'tab', 'subscription', Pages::get_page_url( 'ovr_page_dashboard' ) ),
                __( 'Choose a plan', 'ovr-core' )
            );
        }

        $user     = wp_get_current_user();
        $settings = (array) get_option( 'ovr_settings', [] );
        $is_subscription_checkout = ( 'plan' === ( $order['type'] ?? '' ) );

        return TemplateLoader::get_rendered( 'pages/checkout.php', [
            'default_gateway'          => 'authorize_net',
            'order'                    => $order,
            'is_subscription_checkout' => $is_subscription_checkout,
            'symbol'                   => $settings['currency_symbol'] ?? '$',
            'balance'                  => Wallet::get_balance( $user->ID ),
            'user'                     => $user,
            'checkout_action'          => admin_url( 'admin-post.php' ),
            'cancel_url'               => $cancel_url,
            'promo_nonce'              => wp_create_nonce( 'ovr_public_nonce' ),
            'ajax_url'                 => admin_url( 'admin-ajax.php' ),
        ] );
    }

    /**
     * Minimal centered notice with a call-to-action link, used when checkout
     * can't proceed (not logged in, or no plan selected).
     */
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
