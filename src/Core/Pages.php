<?php
/**
 * Auto-Create Plugin Pages.
 *
 * @package OVR\Core
 * @since   1.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pages {

    /**
     * Version of the page set. Bump when adding a new plugin page so the
     * one-time self-heal below creates it without requiring reactivation.
     */
    private const PAGES_VERSION = '7';

    public function init(): void {
        add_action( 'init', [ $this, 'maybe_sync_pages' ] );
    }

    /**
     * Create any missing plugin pages once after a page-set version bump.
     */
    public function maybe_sync_pages(): void {
        if ( self::PAGES_VERSION === get_option( 'ovr_pages_version' ) ) {
            return;
        }
        self::create_pages();
        update_option( 'ovr_pages_version', self::PAGES_VERSION );
    }

    /**
     * Create all required plugin pages on activation.
     */
    public static function create_pages(): void {
        $pages = [
            'ovr_page_login'           => [ 'Login', '[ovr_login]', 'login' ],
            'ovr_page_register'        => [ 'Create Account', '[ovr_register]', 'register' ],
            'ovr_page_forgot_password' => [ 'Forgot Password', '[ovr_forgot_password]', 'forgot-password' ],
            'ovr_page_pricing'         => [ 'Pricing Plans', '[ovr_pricing_plans]', 'pricing' ],
            'ovr_page_subscription_select' => [ 'Choose Your Subscription', '[ovr_subscription_select]', 'subscription-select' ],
            'ovr_page_checkout'        => [ 'Checkout', '[ovr_checkout]', 'checkout' ],
            'ovr_page_payment_success' => [ 'Payment Successful', '[ovr_payment_success]', 'payment-success' ],
            'ovr_page_search'          => [ 'Search Properties', '[ovr_search_results]', 'search' ],
            'ovr_page_map'             => [ 'Map', '[ovr_map]', 'map' ],
            'ovr_page_featured'        => [ 'Featured Properties', '[ovr_featured_listings]', 'featured' ],
            'ovr_page_villages'        => [ 'Villages', '[ovr_villages]', 'villages' ],
            'ovr_page_onboarding'      => [ 'Welcome', '[ovr_onboarding]', 'welcome' ],
            'ovr_page_dashboard'       => [ 'Dashboard', '[ovr_dashboard]', 'dashboard' ],
            'ovr_page_about'           => [ 'About Us', self::default_about_content(), 'about' ],
            'ovr_page_contact'         => [ 'Contact', self::default_contact_content(), 'contact' ],
        ];

        foreach ( $pages as $option_key => $data ) {
            self::maybe_create_page( $option_key, $data[0], $data[1], $data[2] );
        }
    }

    private static function maybe_create_page( string $key, string $title, string $content, string $slug ): void {
        $existing_id = get_option( $key );
        if ( $existing_id && get_post_status( $existing_id ) ) {
            return;
        }

        $existing_page = get_page_by_path( $slug );
        if ( $existing_page ) {
            update_option( $key, $existing_page->ID );
            return;
        }

        $page_id = wp_insert_post( [
            'post_title'     => $title,
            'post_content'   => $content,
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_name'      => $slug,
            'post_author'    => 1,
            'comment_status' => 'closed',
        ] );

        if ( ! is_wp_error( $page_id ) ) {
            update_option( $key, $page_id );
        }
    }

    /**
     * Default editable content for the auto-created About page.
     */
    private static function default_about_content(): string {
        return "<!-- wp:paragraph --><p>Our Villages Rental connects guests directly with local property owners across The Villages. "
            . "We make it simple to discover, compare and book vacation and long-term rentals — owner-direct, with no middleman.</p><!-- /wp:paragraph -->\n\n"
            . "<!-- wp:paragraph --><p>This page is editable from the WordPress admin. Replace this text with your own story, mission and team.</p><!-- /wp:paragraph -->";
    }

    /**
     * Default editable content for the auto-created Contact page.
     */
    private static function default_contact_content(): string {
        $email = get_option( 'admin_email' );
        return "<!-- wp:heading --><h2>Get in touch</h2><!-- /wp:heading -->\n\n"
            . "<!-- wp:paragraph --><p>Questions about a listing or your account? We're happy to help.</p><!-- /wp:paragraph -->\n\n"
            . '<!-- wp:paragraph --><p><strong>Email:</strong> <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . "</a></p><!-- /wp:paragraph -->\n\n"
            . "<!-- wp:paragraph --><p>This page is editable from the WordPress admin — add a contact form or phone number as needed.</p><!-- /wp:paragraph -->";
    }

    /**
     * Get a plugin page URL by option key.
     */
    public static function get_page_url( string $option_key ): string {
        $page_id = absint( get_option( $option_key ) );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }
        return home_url( '/' );
    }
}
