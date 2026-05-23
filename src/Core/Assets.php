<?php
/**
 * Asset Management.
 *
 * Registers and conditionally enqueues all CSS and JavaScript assets.
 * Public assets load only on pages that need them. Admin assets load
 * only on OVR admin screens.
 *
 * @package OVR\Core
 * @since   1.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets {

    /**
     * Initialize asset hooks.
     *
     * @since 1.0.0
     */
    public function init(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_styles' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_head', [ $this, 'preload_fonts' ], 1 );
    }

    /**
     * Preload Google Fonts for performance.
     *
     * @since 1.0.0
     */
    public function preload_fonts(): void {
        if ( is_admin() ) {
            return;
        }
        ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <?php
    }

    /**
     * Enqueue public-facing stylesheets.
     *
     * @since 1.0.0
     */
    public function enqueue_public_styles(): void {
        // Google Fonts: Atkinson Hyperlegible Next, matching the Stitch redesign.
        wp_enqueue_style(
            'ovr-google-fonts',
            'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:ital,wght@0,200..800;1,200..800&display=swap',
            [],
            OVR_VERSION
        );

        // Material Symbols for icons.
        wp_enqueue_style(
            'ovr-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap',
            [],
            OVR_VERSION
        );

        // Main public stylesheet.
        wp_enqueue_style(
            'ovr-public',
            OVR_PLUGIN_URL . 'assets/css/ovr-public.css',
            [],
            OVR_VERSION
        );

        // Auth pages stylesheet (conditional).
        if ( $this->is_auth_page() ) {
            wp_enqueue_style(
                'ovr-auth',
                OVR_PLUGIN_URL . 'assets/css/ovr-auth.css',
                [ 'ovr-public' ],
                OVR_VERSION
            );
        }
    }

    /**
     * Enqueue public-facing JavaScript.
     *
     * @since 1.0.0
     */
    public function enqueue_public_scripts(): void {
        // Main public script.
        wp_enqueue_script(
            'ovr-public',
            OVR_PLUGIN_URL . 'assets/js/ovr-public.js',
            [],
            OVR_VERSION,
            true
        );

        // Localize script data.
        wp_localize_script( 'ovr-public', 'ovrData', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'ovr/v1/' ),
            'nonce'    => wp_create_nonce( 'ovr_public_nonce' ),
            'siteUrl'  => home_url( '/' ),
            'pluginUrl'=> OVR_PLUGIN_URL,
            'i18n'     => [
                'loading'    => esc_html__( 'Loading...', 'ovr-core' ),
                'error'      => esc_html__( 'Something went wrong. Please try again.', 'ovr-core' ),
                'noResults'  => esc_html__( 'No properties found matching your criteria.', 'ovr-core' ),
                'confirm'    => esc_html__( 'Are you sure?', 'ovr-core' ),
            ],
        ] );

        // Auth form validation (conditional).
        if ( $this->is_auth_page() ) {
            wp_enqueue_script(
                'ovr-auth',
                OVR_PLUGIN_URL . 'assets/js/ovr-auth.js',
                [ 'ovr-public' ],
                OVR_VERSION,
                true
            );
        }

        // Search & filter scripts (conditional).
        if ( $this->is_search_page() || $this->is_property_page() ) {
            wp_enqueue_script(
                'ovr-search',
                OVR_PLUGIN_URL . 'assets/js/ovr-search.js',
                [ 'ovr-public' ],
                OVR_VERSION,
                true
            );
        }

        // Property page scripts (conditional).
        if ( $this->is_property_page() ) {
            wp_enqueue_script(
                'ovr-property',
                OVR_PLUGIN_URL . 'assets/js/ovr-property.js',
                [ 'ovr-public' ],
                OVR_VERSION,
                true
            );
        }
    }

    /**
     * Enqueue admin-only assets on OVR pages.
     *
     * @param string $hook Current admin page hook.
     * @since 1.0.0
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Only load on OVR admin pages (Phase 2+).
        if ( strpos( $hook, 'ovr' ) === false && strpos( $hook, 'ovr-core' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'ovr-admin',
            OVR_PLUGIN_URL . 'assets/css/ovr-admin.css',
            [],
            OVR_VERSION
        );
    }

    /**
     * Check if the current page is an OVR auth page.
     *
     * @return bool
     * @since  1.0.0
     */
    private function is_auth_page(): bool {
        $auth_page_ids = [
            absint( get_option( 'ovr_page_login' ) ),
            absint( get_option( 'ovr_page_register' ) ),
            absint( get_option( 'ovr_page_forgot_password' ) ),
            absint( get_option( 'ovr_page_onboarding' ) ),
        ];

        return is_page( array_filter( $auth_page_ids ) );
    }

    /**
     * Check if the current page is a search/results page.
     *
     * @return bool
     * @since  1.0.0
     */
    private function is_search_page(): bool {
        $search_page = absint( get_option( 'ovr_page_search' ) );
        return ( $search_page && is_page( $search_page ) ) || is_post_type_archive( 'ovr_property' );
    }

    /**
     * Check if the current page is a single property page.
     *
     * @return bool
     * @since  1.0.0
     */
    private function is_property_page(): bool {
        return is_singular( 'ovr_property' );
    }
}
