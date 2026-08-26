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
        // Register the core front-end handles first so they are always known to
        // WordPress/Elementor. Elementor widgets declare these via
        // get_style_depends(), which only resolves if the handle is registered —
        // this keeps the slider (and other widgets) styled on sites with
        // "optimized asset loading" or asset-stripping cache plugins, where the
        // unconditional enqueue below could be removed.
        wp_register_style(
            'ovr-google-fonts',
            'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:ital,wght@0,200..800;1,200..800&display=swap',
            [],
            OVR_VERSION
        );
        // Material Symbols is SELF-HOSTED (assets/css/ovr-icons.css + the
        // bundled woff2) so every icon renders on any environment — no Google
        // Fonts CDN dependency, so CDN blocks / cache plugins that strip
        // third-party stylesheets can never blank out the icons.
        wp_register_style(
            'ovr-material-symbols',
            OVR_PLUGIN_URL . 'assets/css/ovr-icons.css',
            [],
            OVR_VERSION
        );
        wp_register_style(
            'ovr-public',
            OVR_PLUGIN_URL . 'assets/css/ovr-public.css',
            [],
            OVR_VERSION
        );

        // Enqueue them site-wide (the common case).
        wp_enqueue_style( 'ovr-google-fonts' );
        wp_enqueue_style( 'ovr-material-symbols' );
        wp_enqueue_style( 'ovr-public' );

        // Auth pages stylesheet (conditional).
        if ( $this->is_auth_page() ) {
            wp_enqueue_style(
                'ovr-auth',
                OVR_PLUGIN_URL . 'assets/css/ovr-auth.css',
                [ 'ovr-public' ],
                OVR_VERSION
            );
        }

        // Leaflet map styles — on the search "Map" view, the /map/ page, and
        // single property pages that carry coordinates (client: the embedded
        // OSM iframe had a broken zoom-out control; the Leaflet map fixes it).
        if ( $this->needs_map() ) {
            wp_enqueue_style(
                'ovr-leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                [],
                '1.9.4'
            );

            // Marker clustering (numbered cluster bubbles).
            wp_enqueue_style(
                'ovr-leaflet-cluster',
                'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
                [ 'ovr-leaflet' ],
                '1.5.3'
            );
            wp_enqueue_style(
                'ovr-leaflet-cluster-default',
                'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
                [ 'ovr-leaflet-cluster' ],
                '1.5.3'
            );
        }

        // Single-property map (Leaflet, no clustering) — loaded when the listing
        // has coordinates. Marker is a thumb-tack, not the exact home.
        if ( $this->needs_single_map() ) {
            wp_enqueue_style(
                'ovr-leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                [],
                '1.9.4'
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

        // Testimonials carousel — registered (not enqueued) so the Elementor
        // widget can pull it in on demand via get_script_depends().
        wp_register_script(
            'ovr-testimonials',
            OVR_PLUGIN_URL . 'assets/js/ovr-testimonials.js',
            [],
            OVR_VERSION,
            true
        );

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

        // Search & filter scripts (conditional). Also loaded on the /map/ page,
        // which reuses ovr-search.js (setupMap) to plot its clustered markers.
        if ( $this->is_search_page() || $this->is_property_page() || $this->is_map_page() ) {
            $search_deps = [ 'ovr-public' ];

            // Load Leaflet ahead of ovr-search.js when a map is on the page so
            // it can initialise. Leaflet is only pulled in on demand.
            if ( $this->needs_map() ) {
                wp_enqueue_script(
                    'ovr-leaflet',
                    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                    [],
                    '1.9.4',
                    true
                );
                wp_enqueue_script(
                    'ovr-leaflet-cluster',
                    'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
                    [ 'ovr-leaflet' ],
                    '1.5.3',
                    true
                );
                $search_deps[] = 'ovr-leaflet';
                $search_deps[] = 'ovr-leaflet-cluster';
            }

            wp_enqueue_script(
                'ovr-search',
                OVR_PLUGIN_URL . 'assets/js/ovr-search.js',
                $search_deps,
                OVR_VERSION,
                true
            );
        }

        // Property page scripts (conditional).
        if ( $this->is_property_page() ) {
            $property_deps = [ 'ovr-public' ];

            // Single-property map: Leaflet (no cluster) so the zoom controls
            // work — the old OSM iframe embed could not zoom out.
            if ( $this->needs_single_map() ) {
                wp_enqueue_script(
                    'ovr-leaflet',
                    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                    [],
                    '1.9.4',
                    true
                );
                $property_deps[] = 'ovr-leaflet';
            }

            wp_enqueue_script(
                'ovr-property',
                OVR_PLUGIN_URL . 'assets/js/ovr-property.js',
                $property_deps,
                OVR_VERSION,
                true
            );
        }

        // Contact form page (conditional).
        if ( is_page( (int) get_option( 'ovr_page_contact' ) ) ) {
            wp_enqueue_script(
                'ovr-contact',
                OVR_PLUGIN_URL . 'assets/js/ovr-contact.js',
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
     * Whether the search results are being viewed as a map (?view=map).
     *
     * @return bool
     * @since  1.0.1
     */
    private function is_map_view(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
        return 'map' === sanitize_text_field( wp_unslash( $_GET['view'] ?? '' ) );
    }

    /**
     * Whether the current request is the standalone /map/ page ([ovr_map]).
     *
     * @return bool
     */
    private function is_map_page(): bool {
        $map_page = absint( get_option( 'ovr_page_map' ) );
        return $map_page && is_page( $map_page );
    }

    /**
     * Whether a Leaflet map needs to load on this request — either the search
     * results in Map view, or the dedicated /map/ page.
     *
     * @return bool
     */
    private function needs_map(): bool {
        return ( $this->is_search_page() && $this->is_map_view() ) || $this->is_map_page();
    }

    /**
     * Whether a single property page carries coordinates and should load the
     * Leaflet single-property map instead of the placeholder iframe.
     *
     * @return bool
     */
    private function needs_single_map(): bool {
        if ( ! $this->is_property_page() ) {
            return false;
        }
        $id = (int) get_the_ID();
        if ( ! $id ) {
            return false;
        }
        $lat = (float) get_post_meta( $id, '_ovr_latitude', true );
        $lng = (float) get_post_meta( $id, '_ovr_longitude', true );
        if ( 0.0 !== $lat && 0.0 !== $lng
            && $lat >= -90.0 && $lat <= 90.0
            && $lng >= -180.0 && $lng <= 180.0 ) {
            return true;
        }
        // Fall back to the village name so listings without precise
        // coordinates still load the map (approximate location). The single
        // property template geocodes the village name *or* the first ovr_village
        // taxonomy term, so both must be honoured here or the map container
        // renders without Leaflet being enqueued (blank map).
        if ( '' !== trim( (string) get_post_meta( $id, '_ovr_village_name', true ) ) ) {
            return true;
        }
        $village_terms = get_the_terms( $id, 'ovr_village' );
        return ! empty( $village_terms ) && ! is_wp_error( $village_terms );
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
