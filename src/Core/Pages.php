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

    public function init(): void {}

    /**
     * Create all required plugin pages on activation.
     */
    public static function create_pages(): void {
        $pages = [
            'ovr_page_login'           => [ 'Login', '[ovr_login]', 'login' ],
            'ovr_page_register'        => [ 'Create Account', '[ovr_register]', 'register' ],
            'ovr_page_forgot_password' => [ 'Forgot Password', '[ovr_forgot_password]', 'forgot-password' ],
            'ovr_page_pricing'         => [ 'Pricing Plans', '[ovr_pricing_plans]', 'pricing' ],
            'ovr_page_search'          => [ 'Search Properties', '[ovr_search_results]', 'search' ],
            'ovr_page_featured'        => [ 'Featured Properties', '[ovr_featured_listings]', 'featured' ],
            'ovr_page_onboarding'      => [ 'Welcome', '[ovr_onboarding]', 'welcome' ],
            'ovr_page_dashboard'       => [ 'Dashboard', '[ovr_dashboard]', 'dashboard' ],
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
