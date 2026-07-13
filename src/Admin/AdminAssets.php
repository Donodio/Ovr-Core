<?php
/**
 * Admin Asset Enqueuing.
 *
 * Loads the premium admin CSS and the property-edit-screen JS only on
 * ovr_property edit screens (and the property list table for the badges).
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AdminAssets {

    public function init(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    /**
     * Conditionally enqueue admin assets on OVR screens.
     */
    public function enqueue( string $hook ): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) return;

        // OVR admin surface = the property CPT (and its submenu pages, which
        // all carry post_type=ovr_property), the testimonials CPT, and the
        // property taxonomy editors. The shared admin design system + fonts
        // load across all of them so every OVR screen reads the same.
        $ovr_post_types = [ 'ovr_property', 'ovr_testimonial' ];
        $ovr_taxonomies = [ 'ovr_village', 'ovr_property_type', 'ovr_amenity', 'ovr_rental_type', 'ovr_view', 'ovr_feature' ];

        // Some OVR admin pages (e.g. All Properties, which redirects to
        // admin.php?page=ovr-properties, and the Platform Overview) do not carry
        // a post_type context on their screen, so the checks above miss them and
        // the shared fonts/design system never load — icons then render as their
        // ligature text. Treat any admin page whose ?page slug begins with "ovr"
        // as an OVR screen too.
        $page_slug   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $is_ovr_page = '' !== $page_slug && 0 === strpos( $page_slug, 'ovr' );

        $is_ovr_screen = $is_ovr_page
            || in_array( $screen->post_type, $ovr_post_types, true )
            || in_array( (string) $screen->taxonomy, $ovr_taxonomies, true );
        if ( ! $is_ovr_screen ) return;

        // Inter font for admin parity with frontend.
        wp_enqueue_style(
            'ovr-admin-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            OVR_VERSION
        );

        // Material Symbols for admin tab icons.
        wp_enqueue_style(
            'ovr-admin-icons',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap',
            [],
            OVR_VERSION
        );

        wp_enqueue_style(
            'ovr-admin',
            OVR_PLUGIN_URL . 'assets/css/ovr-admin.css',
            [ 'ovr-admin-fonts', 'ovr-admin-icons' ],
            OVR_VERSION
        );

        // Shared OVR admin design system (.ovr-adm) — one source of truth for
        // the navy/blue/gold scheme used across every standardized screen,
        // plus the "chrome" rules for native WP list/taxonomy/editor screens.
        wp_enqueue_style(
            'ovr-admin-ui',
            OVR_PLUGIN_URL . 'assets/css/ovr-admin-ui.css',
            [ 'ovr-admin-fonts', 'ovr-admin-icons' ],
            OVR_VERSION
        );

        // Property edit screen only — load media library + the tab/repeater JS.
        if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            wp_enqueue_media();

            wp_enqueue_script(
                'ovr-admin-property',
                OVR_PLUGIN_URL . 'assets/js/ovr-admin-property.js',
                [ 'jquery', 'wp-i18n' ],
                OVR_VERSION,
                true
            );

            wp_localize_script( 'ovr-admin-property', 'ovrAdmin', [
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'pluginUrl'  => OVR_PLUGIN_URL,
                'nonce'      => wp_create_nonce( 'ovr_admin_nonce' ),
                'mediaTitle' => esc_html__( 'Select Property Photos', 'ovr-core' ),
                'mediaUse'   => esc_html__( 'Use these photos', 'ovr-core' ),
                'i18n'       => [
                    'remove'   => esc_html__( 'Remove', 'ovr-core' ),
                    'primary'  => esc_html__( 'Primary', 'ovr-core' ),
                    'makePrimary' => esc_html__( 'Make primary', 'ovr-core' ),
                    'addRow'   => esc_html__( 'Add row', 'ovr-core' ),
                    'confirm'  => esc_html__( 'Are you sure?', 'ovr-core' ),
                    'noImages' => esc_html__( 'No photos selected yet. Click "Add photos" to upload or pick from the media library.', 'ovr-core' ),
                ],
            ] );
        }
    }
}
