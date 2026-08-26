<?php
/**
 * Shortcode Manager.
 *
 * Registers all OVR shortcodes and maps them to their renderers.
 *
 * @package OVR\Shortcodes
 * @since   1.0.0
 */

namespace OVR\Shortcodes;

use OVR\Auth\LoginHandler;
use OVR\Auth\RegistrationHandler;
use OVR\Auth\PasswordResetHandler;
use OVR\Frontend\Homepage;
use OVR\Frontend\MapPage;
use OVR\Frontend\SearchResults;
use OVR\Frontend\FeaturedListings;
use OVR\Frontend\VillagePage;
use OVR\Frontend\VillagesArchive;
use OVR\Frontend\Onboarding;
use OVR\Frontend\SubscriptionSelect;
use OVR\Frontend\Checkout;
use OVR\Frontend\PaymentSuccess;
use OVR\Subscription\PricingDisplay;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShortcodeManager {

    public function init(): void {
        add_action( 'init', [ $this, 'register_shortcodes' ] );
    }

    public function register_shortcodes(): void {
        // Auth shortcodes.
        add_shortcode( 'ovr_login', [ $this, 'shortcode_login' ] );
        add_shortcode( 'ovr_register', [ $this, 'shortcode_register' ] );
        add_shortcode( 'ovr_forgot_password', [ $this, 'shortcode_forgot_password' ] );
        add_shortcode( 'ovr_onboarding', [ $this, 'shortcode_onboarding' ] );
        add_shortcode( 'ovr_subscription_select', [ $this, 'shortcode_subscription_select' ] );

        // Page shortcodes.
        add_shortcode( 'ovr_homepage', [ $this, 'shortcode_homepage' ] );
        add_shortcode( 'ovr_search_results', [ $this, 'shortcode_search_results' ] );
        add_shortcode( 'ovr_map', [ $this, 'shortcode_map' ] );
        add_shortcode( 'ovr_featured_listings', [ $this, 'shortcode_featured' ] );
        add_shortcode( 'ovr_village_page', [ $this, 'shortcode_village' ] );
        add_shortcode( 'ovr_villages', [ $this, 'shortcode_villages_archive' ] );
        add_shortcode( 'ovr_village_sections', [ $this, 'shortcode_village_sections' ] );
        add_shortcode( 'ovr_pricing_plans', [ $this, 'shortcode_pricing' ] );
		add_shortcode( 'ovr_checkout', [ $this, 'shortcode_checkout' ] );
		add_shortcode( 'ovr_payment_success', [ $this, 'shortcode_payment_success' ] );
		add_shortcode( 'ovr_contact_form', [ $this, 'shortcode_contact_form' ] );
		add_shortcode( 'ovr_id_request', [ $this, 'shortcode_id_request' ] );

        // Component shortcodes.
        add_shortcode( 'ovr_property_card', [ $this, 'shortcode_property_card' ] );
        add_shortcode( 'ovr_search_bar', [ $this, 'shortcode_search_bar' ] );

        add_shortcode( 'ovr_dashboard', [ $this, 'shortcode_dashboard' ] );

        // Ad banner placement (M3 F8).
        add_shortcode( 'ovr_ad_banner', [ $this, 'shortcode_ad_banner' ] );
    }

    public function shortcode_ad_banner( $atts = [] ): string {
        $atts = shortcode_atts( [ 'placement' => 'homepage' ], (array) $atts, 'ovr_ad_banner' );
        return \OVR\Frontend\AdBanners::render( (string) $atts['placement'] );
    }

    public function shortcode_login(): string {
        return LoginHandler::render();
    }

    public function shortcode_register(): string {
        return RegistrationHandler::render();
    }

    public function shortcode_forgot_password(): string {
        return PasswordResetHandler::render();
    }

    public function shortcode_onboarding(): string {
        return Onboarding::render();
    }
    public function shortcode_subscription_select(): string {
        return SubscriptionSelect::render();
    }

    public function shortcode_homepage(): string {
        return Homepage::render();
    }

    public function shortcode_search_results(): string {
        return SearchResults::render();
    }

    public function shortcode_map(): string {
        return MapPage::render();
    }

    public function shortcode_featured(): string {
        return FeaturedListings::render();
    }

    public function shortcode_village( array $atts = [] ): string {
        $atts = shortcode_atts( [ 'slug' => '' ], $atts, 'ovr_village_page' );
        return VillagePage::render( sanitize_key( $atts['slug'] ) );
    }

    public function shortcode_villages_archive(): string {
        return VillagesArchive::render();
    }

    public function shortcode_village_sections(): string {
        return \OVR\Frontend\VillageSections::render();
    }

    public function shortcode_pricing( $atts = [] ): string {
        $atts = shortcode_atts( [
            'columns'        => 0,
            'layout'         => 'cards',
            'limit'          => 0,
            'featured_first' => 0,
            'show_compare'   => 1,
            'show_promo'     => 1,
            'only'           => '',
            'exclude'        => '',
        ], (array) $atts, 'ovr_pricing_plans' );

        return PricingDisplay::render( [
            'columns'        => (int)  $atts['columns'],
            'layout'         => sanitize_key( $atts['layout'] ),
            'limit'          => (int)  $atts['limit'],
            'featured_first' => (bool) $atts['featured_first'],
            'show_compare'   => (bool) $atts['show_compare'],
            'show_promo'     => (bool) $atts['show_promo'],
            'only'           => (string) $atts['only'],
            'exclude'        => (string) $atts['exclude'],
        ] );
    }

    public function shortcode_checkout(): string {
        return Checkout::render();
    }

    public function shortcode_payment_success(): string {
        return PaymentSuccess::render();
    }

    public function shortcode_contact_form(): string {
        return \OVR\Frontend\ContactForm::render();
    }

    public function shortcode_id_request(): string {
        return \OVR\Frontend\IdRequest::render();
    }

    public function shortcode_property_card( array $atts = [] ): string {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'ovr_property_card' );
        $id = absint( $atts['id'] );
        if ( ! $id ) {
            return '';
        }
        return \OVR\Property\PropertyCard::render_grid( $id );
    }

    public function shortcode_search_bar(): string {
        return \OVR\Core\TemplateLoader::get_rendered( 'components/search-bar.php' );
    }

    public function shortcode_dashboard(): string {
        return \OVR\Frontend\Dashboard::render();
    }
}
