<?php
/**
 * Global Site Header.
 *
 * Renders the same OVR top nav on every page of the site. Replaces the
 * two near-duplicate headers that previously lived in
 *   • themes/ovr-villages/header.php (the theme's hard-coded nav)
 *   • templates/pages/homepage.php  (the plugin's inline nav)
 *
 * The header is hooked to `wp_body_open` so it appears as the first
 * child of <body>. The theme's header.php still owns the <!doctype> /
 * <head> / opening <body> markup; this class only renders the visible
 * chrome between <body> and the page content.
 *
 * Themes that want to keep their own header (e.g. an Elementor-built
 * header) can `remove_action( 'wp_body_open', [ Header::class, 'render' ] )`.
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

use OVR\Core\Pages;
use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Header {

    /**
     * Role-aware top-nav items (Feature 9). The menu changes by who is viewing:
     *   - Visitor: marketing pages.
     *   - Landlord: entry points into their dashboard tabs.
     *   - Admin: jump links into the wp-admin OVR tooling.
     * Functional landlord/admin sub-tools also live in the dashboard sidebar
     * and the wp-admin menu, which are themselves role-aware.
     *
     * @return array<string, array{label:string,url:string}>
     */
    public static function nav_items(): array {
        // Admins manage from wp-admin; their front-end top nav stays the
        // visitor marketing set plus a Site Admin jump (added in the actions
        // area), so the public header never turns into an admin console.
        if ( is_user_logged_in() && ! current_user_can( 'manage_options' )
            && current_user_can( 'ovr_view_dashboard' ) ) {
            return self::landlord_nav_items();
        }
        return self::visitor_nav_items();
    }

    /**
     * @return array<string, array{label:string,url:string}>
     */
    public static function visitor_nav_items(): array {
        $items = [
            'explore'  => [ 'label' => __( 'Explore', 'ovr-core' ),  'url' => Pages::get_page_url( 'ovr_page_search' ) ],
            'villages' => [ 'label' => __( 'Villages', 'ovr-core' ), 'url' => Pages::get_page_url( 'ovr_page_villages' ) ],
            'featured' => [ 'label' => __( 'Featured', 'ovr-core' ), 'url' => Pages::get_page_url( 'ovr_page_featured' ) ],
            'pricing'  => [ 'label' => __( 'Pricing', 'ovr-core' ),  'url' => Pages::get_page_url( 'ovr_page_pricing' ) ],
        ];
        if ( get_option( 'ovr_page_about' ) ) {
            $items['about'] = [ 'label' => __( 'About', 'ovr-core' ), 'url' => Pages::get_page_url( 'ovr_page_about' ) ];
        }
        if ( get_option( 'ovr_page_contact' ) ) {
            $items['contact'] = [ 'label' => __( 'Contact', 'ovr-core' ), 'url' => Pages::get_page_url( 'ovr_page_contact' ) ];
        }
        return $items;
    }

    /**
     * Landlord top-nav: deep links into the dashboard tabs that exist.
     *
     * @return array<string, array{label:string,url:string}>
     */
    public static function landlord_nav_items(): array {
        $dash = Pages::get_page_url( 'ovr_page_dashboard' );
        $tab  = static fn( string $t ): string => add_query_arg( 'tab', $t, $dash );
        return [
            'dashboard'    => [ 'label' => __( 'Dashboard', 'ovr-core' ),  'url' => $dash ],
            'listings'     => [ 'label' => __( 'Listings', 'ovr-core' ),   'url' => $tab( 'properties' ) ],
            'inquiries'    => [ 'label' => __( 'Inquiries', 'ovr-core' ),  'url' => $tab( 'inquiries' ) ],
            'reviews'      => [ 'label' => __( 'Reviews', 'ovr-core' ),    'url' => $tab( 'reviews' ) ],
            'membership'   => [ 'label' => __( 'Membership', 'ovr-core' ), 'url' => $tab( 'subscription' ) ],
            'explore'      => [ 'label' => __( 'Explore', 'ovr-core' ),    'url' => Pages::get_page_url( 'ovr_page_search' ) ],
        ];
    }

    public function init(): void {
        add_action( 'wp_body_open', [ $this, 'render' ], 1 );
    }

    /**
     * Echo the site header at the top of <body>.
     */
    public function render(): void {
        $vars = self::template_vars();
        echo TemplateLoader::get_rendered( 'components/header-nav.php', $vars ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Variables passed to the header-nav template.
     *
     * @return array<string, mixed>
     */
    public static function template_vars(): array {
        $current_user  = wp_get_current_user();
        $is_logged_in  = is_user_logged_in();
        $logo_id       = (int) get_theme_mod( 'custom_logo' );
        $active        = self::detect_active_nav();

        $vars = [
            'is_logged_in'        => $is_logged_in,
            'is_admin_user'       => current_user_can( 'manage_options' ),
            'admin_home_url'      => admin_url(),
            'nav_items'           => self::nav_items(),
            'active'              => $active,
            'home_url'            => home_url( '/' ),
            'search_url'          => Pages::get_page_url( 'ovr_page_search' ),
            'villages_url'        => Pages::get_page_url( 'ovr_page_villages' ),
            'pricing_url'         => Pages::get_page_url( 'ovr_page_pricing' ),
            'login_url'           => Pages::get_page_url( 'ovr_page_login' ),
            'register_url'        => Pages::get_page_url( 'ovr_page_register' ),
            'dashboard_url'       => Pages::get_page_url( 'ovr_page_dashboard' ),
            'logout_url'          => wp_logout_url( home_url( '/' ) ),
            'current_user'        => $current_user,
            'logo_html'           => $logo_id ? wp_get_attachment_image(
                $logo_id,
                'full',
                false,
                [
                    'class' => 'ovr-brand-logo',
                    'style' => 'height:36px;width:auto;display:block',
                    'alt'   => esc_attr__( 'Our Village Rentals', 'ovr-core' ),
                ]
            ) : '',
            'site_name'           => get_bloginfo( 'name' ) ?: __( 'Our Village Rentals', 'ovr-core' ),
        ];
        return $vars;
    }

    /**
     * Determine which nav item should be highlighted based on the current
     * request. Returns an empty string if no match.
     */
    private static function detect_active_nav(): string {
        if ( is_page( (int) get_option( 'ovr_page_search' ) ) ) {
            return 'explore';
        }
        if ( is_page( (int) get_option( 'ovr_page_villages' ) ) ) {
            return 'villages';
        }
        if ( is_page( (int) get_option( 'ovr_page_pricing' ) ) ) {
            return 'pricing';
        }
        return '';
    }
}
