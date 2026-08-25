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
     * Default mega-menu configuration (the theme's curated menu).
     *
     * Mirrors the items the theme override ships in header-nav.php. Only
     * `label` (+ optionally `url`) is persisted via Settings > Header & Menu;
     * page URLs are resolved lazily by mega_menu() so they always reflect the
     * currently assigned pages.
     *
     * @return array<string, array{label:string, url:string}>
     */
    public static function mega_menu_defaults(): array {
        return [
            // Panel 1 — Search Rentals (Icon + Label, no paragraphs per Chunk 4).
            'search_trigger'     => [ 'label' => __( 'Search Rentals', 'ovr-core' ),     'url' => '' ],
            'search_col_rentals' => [ 'label' => __( 'Rentals', 'ovr-core' ),            'url' => '' ],
            'all_homes'          => [ 'label' => __( 'All Homes', 'ovr-core' ),          'url' => '' ],
            'deals_homes'        => [ 'label' => __( 'Deals & Cancellations', 'ovr-core' ), 'url' => '' ],
            'featured_homes'     => [ 'label' => __( 'Featured Homes', 'ovr-core' ),     'url' => '' ],
            'map_view'           => [ 'label' => __( 'Map View', 'ovr-core' ),           'url' => '' ],
            'new_listings'       => [ 'label' => __( 'New Listings', 'ovr-core' ),       'url' => '' ],
            'villages'           => [ 'label' => __( 'Villages', 'ovr-core' ),           'url' => '' ],
            'search_col_by_stay' => [ 'label' => __( 'By Stay', 'ovr-core' ),            'url' => '' ],
            'long_term'          => [ 'label' => __( 'Long-Term', 'ovr-core' ),          'url' => '' ],
            'short_term'         => [ 'label' => __( 'Short-Term', 'ovr-core' ),         'url' => '' ],
            // Pricing removed from public mega menu per Chunk 4 §70 — plans remain via List My Property flow.
            // Panel 2 — Villages Info.
            'villages_trigger'   => [ 'label' => __( 'Villages Info', 'ovr-core' ),      'url' => '' ],
            'villages_col_info'  => [ 'label' => __( 'Info', 'ovr-core' ),               'url' => '' ],
            'villages_link'      => [ 'label' => __( 'Villages', 'ovr-core' ),           'url' => '' ],
            'about'              => [ 'label' => __( 'About', 'ovr-core' ),              'url' => '' ],
            'contact'            => [ 'label' => __( 'Contact', 'ovr-core' ),            'url' => '' ],
            'villages_col_links' => [ 'label' => __( 'Community Links', 'ovr-core' ),    'url' => '' ],
            'villages_net'       => [ 'label' => __( 'Villages.net', 'ovr-core' ),       'url' => 'https://www.villages.net' ],
            'thevillages_com'    => [ 'label' => __( 'TheVillages.com', 'ovr-core' ),    'url' => 'https://www.thevillages.com' ],
            'golf_the_villages'  => [ 'label' => __( 'Golf the Villages', 'ovr-core' ),  'url' => 'https://www.golfthevillages.com' ],
            // Direct links (no panel) — Pricing removed.
            'featured_direct'    => [ 'label' => __( 'Featured', 'ovr-core' ),           'url' => '' ],
        ];
    }

    /**
     * Merge saved Settings > Header & Menu overrides into the defaults and
     * resolve the OVR page URLs so the theme can render one consistent menu.
     *
     * @return array<string, array{label:string, url:string}>
     */
    public static function mega_menu(): array {
        $defaults = self::mega_menu_defaults();
        $settings = (array) get_option( 'ovr_settings', [] );
        $saved    = (array) ( $settings['mega_menu'] ?? [] );

        // Page options used to resolve empty URLs. Keys that need a query arg
        // are handled separately below.
        $page_for = [
            'all_homes'       => 'ovr_page_search',
            'featured_homes'  => 'ovr_page_featured',
            'map_view'        => 'ovr_page_search',
            'new_listings'    => 'ovr_page_search',
            'villages'        => 'ovr_page_villages',
            'villages_link'   => 'ovr_page_villages',
            'about'           => 'ovr_page_about',
            'contact'         => 'ovr_page_contact',
            'featured_direct' => 'ovr_page_featured',
        ];

        $out = [];
        foreach ( $defaults as $key => $def ) {
            $label = $def['label'];
            $url   = $def['url'];
            if ( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
                $label = '' !== ( $saved[ $key ]['label'] ?? '' ) ? (string) $saved[ $key ]['label'] : $label;
                $url   = '' !== ( $saved[ $key ]['url'] ?? '' ) ? (string) $saved[ $key ]['url'] : $url;
            } elseif ( isset( $saved[ $key ] ) && is_string( $saved[ $key ] ) ) {
                // Heading rows persist as a plain string (the title).
                $label = '' !== $saved[ $key ] ? $saved[ $key ] : $label;
            }
            if ( '' === $url ) {
                if ( 'long_term' === $key ) {
                    $url = add_query_arg( 'rental_type', 'long-term-rental', Pages::get_page_url( 'ovr_page_search' ) );
                } elseif ( 'short_term' === $key ) {
                    $url = add_query_arg( 'rental_type', 'short-term-rental', Pages::get_page_url( 'ovr_page_search' ) );
                } elseif ( 'deals_homes' === $key ) {
                    $url = add_query_arg( 'deals_only', '1', Pages::get_page_url( 'ovr_page_search' ) );
                } elseif ( 'featured_homes' === $key || 'featured_direct' === $key ) {
                    // Featured now reuses the standard search results format
                    // (filtered to active Featured boosts) instead of a separate
                    // template, so every subset page shares one layout.
                    $url = add_query_arg( 'featured_only', '1', Pages::get_page_url( 'ovr_page_search' ) );
                } elseif ( 'map_view' === $key ) {
                    $url = add_query_arg( 'view', 'map', Pages::get_page_url( 'ovr_page_search' ) );
                } elseif ( 'new_listings' === $key ) {
                    $url = add_query_arg( 'sort', 'newest', Pages::get_page_url( 'ovr_page_search' ) );
                } elseif ( isset( $page_for[ $key ] ) ) {
                    $url = Pages::get_page_url( $page_for[ $key ] );
                }
            }
            $out[ $key ] = [ 'label' => $label, 'url' => $url ];
        }
        return $out;
    }

    /**
     * Build a search-results URL with optional query args.
     *
     * @param array<string, string> $params Query args appended to the bare search URL.
     */
    private static function search_url( array $params = [] ): string {
        $url = Pages::get_page_url( 'ovr_page_search' );
        return $params ? add_query_arg( $params, $url ) : $url;
    }

    /**
     * "Newest Listings" result cap (Settings > General; defaults to 12).
     */
    private static function newest_limit(): int {
        $s = (array) get_option( 'ovr_settings', [] );
        return max( 1, (int) ( $s['newest_listings_count'] ?? 12 ) );
    }

    /**
     * The two popup menus every visitor sees: Explore Rentals + Site Information.
     *
     * Shape mirrors the legacy dropdown contract (label/url/target/divider)
     * with two additions: `icon` (Material Symbols name) and `disabled`.
     *
     * @return array<string, array{label:string, url:string, icon:string, children?:array<int,array<string,mixed>>}>
     */
    public static function public_menu_groups(): array {
        return [
            'explore'   => [
                'label'    => __( 'Explore Rentals', 'ovr-core' ),
                'url'      => self::search_url(),
                'icon'     => 'travel_explore',
                'children' => [
                    [ 'label' => __( 'Search All Rentals', 'ovr-core' ), 'icon' => 'search',        'url' => self::search_url() ],
                    [ 'label' => __( 'Featured Properties', 'ovr-core' ), 'icon' => 'star',         'url' => self::search_url( [ 'featured_only' => '1' ] ) ],
                    [ 'label' => __( 'Deals & Cancellations', 'ovr-core' ), 'icon' => 'local_offer', 'url' => self::search_url( [ 'deals_only' => '1' ] ) ],
                    [ 'label' => __( 'Long Term Rentals', 'ovr-core' ), 'icon' => 'event_repeat',  'url' => self::search_url( [ 'rental_type' => 'long-term-rental' ] ) ],
                    [ 'label' => __( 'Newest Listings', 'ovr-core' ), 'icon' => 'fiber_new',        'url' => self::search_url( [ 'sort' => 'newest', 'per_page' => (string) self::newest_limit() ] ) ],
                    [ 'label' => __( 'Search by Village Section', 'ovr-core' ), 'icon' => 'map',    'url' => Pages::get_page_url( 'ovr_page_village_sections' ) ],
                    [ 'label' => __( 'Map Search', 'ovr-core' ), 'icon' => 'location_on',           'url' => self::search_url( [ 'view' => 'map' ] ) ],
                    [ 'divider' => true ],
                    [ 'label' => __( 'Renting in The Villages – An Overview', 'ovr-core' ), 'icon' => 'menu_book', 'url' => Pages::get_page_url( 'ovr_page_renting_overview' ) ],
                    [ 'label' => __( 'Verified Owners', 'ovr-core' ), 'icon' => 'verified',         'url' => Pages::get_page_url( 'ovr_page_verified_owners' ) ],
                ],
            ],
            'site_info' => [
                'label'    => __( 'Site Information', 'ovr-core' ),
                'url'      => '',
                'icon'     => 'info',
                'children' => [
                    [ 'label' => __( 'Rental Owner Information', 'ovr-core' ), 'icon' => 'real_estate_agent', 'url' => Pages::get_page_url( 'ovr_page_owner_information' ) ],
                    [ 'label' => __( 'The Villages Lifestyle', 'ovr-core' ), 'icon' => 'diversity_3', 'url' => 'https://www.thevillages.com/lifestyle/', 'target' => '_blank' ],
                    [ 'label' => __( 'The Villages Town Squares', 'ovr-core' ), 'icon' => 'storefront', 'url' => 'https://www.thevillages.com/shopping-dining/', 'target' => '_blank' ],
                    [ 'label' => __( 'Golf The Villages', 'ovr-core' ), 'icon' => 'golf_course', 'url' => 'https://www.golfthevillages.com', 'target' => '_blank' ],
                    [ 'label' => __( 'OVR User Agreement', 'ovr-core' ), 'icon' => 'gavel', 'url' => Pages::get_page_url( 'ovr_page_user_agreement' ) ],
                    [ 'divider' => true ],
                    [ 'label' => __( 'Forgot My Password', 'ovr-core' ), 'icon' => 'lock_reset', 'url' => Pages::get_page_url( 'ovr_page_forgot_password' ) ],
                    [ 'label' => __( 'Contact OVR', 'ovr-core' ), 'icon' => 'mail', 'url' => Pages::get_page_url( 'ovr_page_contact' ) ],
                    [ 'label' => __( 'Sign up to Advertise', 'ovr-core' ), 'icon' => 'campaign', 'url' => Pages::get_page_url( 'ovr_page_register' ) ],
                    [ 'divider' => true ],
                    [ 'label' => __( 'Site Testimonials', 'ovr-core' ), 'icon' => 'reviews', 'disabled' => true ],
                    [ 'label' => __( 'OVR Business Partners', 'ovr-core' ), 'icon' => 'handshake', 'disabled' => true ],
                ],
            ],
        ];
    }

    /**
     * Logged-in account menu (landlord capability users; admins keep the
     * visitor menus plus their Site Admin jump).
     *
     * @return array{label:string, url:string, icon:string, children?:array<int,array<string,mixed>>}
     */
    public static function account_menu_group(): array {
        $dash = Pages::get_page_url( 'ovr_page_dashboard' );
        return [
            'label'    => __( 'My Account', 'ovr-core' ),
            'url'      => $dash,
            'icon'     => 'account_circle',
            'children' => [
                [ 'label' => __( 'My Dashboard', 'ovr-core' ), 'icon' => 'dashboard',  'url' => $dash ],
                [ 'label' => __( 'My Listings', 'ovr-core' ), 'icon' => 'home_work',   'url' => add_query_arg( 'tab', 'properties', $dash ) ],
                [ 'label' => __( 'My Inquiries', 'ovr-core' ), 'icon' => 'forum',      'url' => add_query_arg( 'tab', 'inquiries', $dash ) ],
                [ 'label' => __( 'Online Villages ID Request', 'ovr-core' ), 'icon' => 'badge', 'url' => Pages::get_page_url( 'ovr_page_id_request' ) ],
                [ 'label' => __( 'Villages Guest Passes', 'ovr-core' ), 'icon' => 'confirmation_number', 'url' => 'https://gcs.thevillages.com/cgi-bin/gc100', 'target' => '_blank' ],
                [ 'divider' => true ],
                [ 'label' => __( 'Log Out', 'ovr-core' ), 'icon' => 'logout', 'url' => wp_logout_url( home_url( '/' ) ) ],
            ],
        ];
    }

    /**
     * Nav-menu location admins assign a menu to (Mark feedback P6.7). */
    public const MENU_LOCATION = 'ovr_primary';

    public static function nav_items(): array {
        // P6.7: navigation is fully configurable from the admin panel. When an
        // admin has assigned a menu to the "OVR Header Menu" location
        // (Appearance → Menus), that menu drives the top nav — labels, URLs,
        // order, external links, and visibility all come from there. Until one
        // is assigned we fall back to the role-aware built-in defaults so the
        // header is never empty.
        if ( has_nav_menu( self::MENU_LOCATION ) ) {
            $items = self::menu_nav_items();
            if ( ! empty( $items ) ) {
                return $items;
            }
        }

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
     * Build top-nav items from the menu assigned to the OVR header location.
     * Only top-level items are used (the header renders a flat bar); each item's
     * label, URL and "open in new tab" target come straight from the menu editor.
     *
     * @return array<string, array{label:string,url:string,target?:string,icon?:string,children?:array<int,array<string,mixed>>}>
     */
    public static function menu_nav_items(): array {
        $locations = (array) get_nav_menu_locations();
        $menu_id   = (int) ( $locations[ self::MENU_LOCATION ] ?? 0 );
        if ( ! $menu_id ) {
            return [];
        }
        $menu_items = wp_get_nav_menu_items( $menu_id );
        if ( ! $menu_items ) {
            return [];
        }

        $out = [];
        foreach ( $menu_items as $item ) {
            $entry = [
                'label'  => $item->title,
                'url'    => $item->url,
                'target' => '_blank' === $item->target ? '_blank' : '',
                'icon'   => '', // Admin-assigned custom menus carry no icon metadata.
            ];
            if ( (int) $item->menu_item_parent === 0 ) {
                $out[ 'item-' . (int) $item->ID ] = $entry;
            } else {
                $parent_key = 'item-' . (int) $item->menu_item_parent;
                if ( isset( $out[ $parent_key ] ) ) {
                    $out[ $parent_key ]['children'][] = $entry; // target kept: code-defined children honor it too.
                }
            }
        }
        return $out;
    }

    /**
     * Visitor top-nav: the two popup menus every visitor sees.
     *
     * @return array<string, array{label:string, url:string, icon:string, children?:array<int,array<string,mixed>>}>
     */
    public static function visitor_nav_items(): array {
        return self::public_menu_groups();
    }

    /**
     * Landlord top-nav: the public menus plus My Account deep links.
     *
     * @return array<string, array{label:string, url:string, icon:string, children?:array<int,array<string,mixed>>}>
     */
    public static function landlord_nav_items(): array {
        return array_merge( self::public_menu_groups(), [ 'account' => self::account_menu_group() ] );
    }

    public function init(): void {
        add_action( 'wp_body_open', [ $this, 'render' ], 1 );
        // P6.7: expose the "OVR Header Menu" location in Appearance → Menus so
        // admins can fully configure the top navigation (booted on plugins_loaded,
        // so after_setup_theme is still ahead of us).
        add_action( 'after_setup_theme', [ $this, 'register_menu_location' ] );
    }

    /**
     * Register the OVR header nav-menu location (Appearance → Menus).
     */
    public function register_menu_location(): void {
        register_nav_menu( self::MENU_LOCATION, __( 'OVR Header Menu (top navigation)', 'ovr-core' ) );
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
        $settings      = (array) get_option( 'ovr_settings', [] );
        $logo_height   = absint( $settings['logo_height'] ?? 0 ) ?: 36;
        $logo_url      = trim( (string) ( $settings['logo_url'] ?? '' ) );

        // The logo is set codelessly from OVR Settings → Header & Menu (or the
        // older "Logo URL" field under General), falling back to the WordPress
        // Customizer Site Identity logo. Admins never need to touch code.
        if ( '' !== $logo_url ) {
            $logo_html = '<img class="ovr-brand-logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Our Villages Rental', 'ovr-core' ) . '" style="height:' . $logo_height . 'px;width:auto;display:block">';
        } elseif ( $logo_id ) {
            $logo_html = wp_get_attachment_image(
                $logo_id,
                'full',
                false,
                [
                    'class' => 'ovr-brand-logo',
                    'style' => 'height:' . $logo_height . 'px;width:auto;display:block',
                    'alt'   => esc_attr__( 'Our Villages Rental', 'ovr-core' ),
                ]
            );
        } else {
            $logo_html = '<img class="ovr-brand-logo" src="' . esc_url( OVR_PLUGIN_URL . 'assets/images/ovr-logo.svg' ) . '" alt="' . esc_attr__( 'Our Villages Rental', 'ovr-core' ) . '" style="height:' . $logo_height . 'px;width:auto;max-width:240px;display:block">';
        }

        $vars = [
            'is_logged_in'        => $is_logged_in,
            'is_admin_user'       => current_user_can( 'manage_options' ),
            'admin_home_url'      => admin_url(),
            'nav_items'           => self::nav_items(),
            'mega_menu'           => self::mega_menu(),
            'active'              => $active,
            'active_group'        => self::detect_active_group(),
            'home_url'            => home_url( '/' ),
            'search_url'          => Pages::get_page_url( 'ovr_page_search' ),
            'villages_url'        => Pages::get_page_url( 'ovr_page_villages' ),
            'pricing_url'         => Pages::get_page_url( 'ovr_page_pricing' ),
            'login_url'           => Pages::get_page_url( 'ovr_page_login' ),
            'register_url'        => Pages::get_page_url( 'ovr_page_register' ),
            'dashboard_url'       => Pages::get_page_url( 'ovr_page_dashboard' ),
            'logout_url'          => wp_logout_url( home_url( '/' ) ),
            'current_user'        => $current_user,
            'logo_html'           => $logo_html,
            'site_name'           => get_bloginfo( 'name' ) ?: __( 'Our Villages Rental', 'ovr-core' ),
        ];
        return $vars;
    }

    // Kept only for the custom-menu override path: its slugs match admin-assigned flat menus.
    /**
     * Determine which nav item should be highlighted based on the current
     * request. Returns an empty string if no match.
     */
    private static function detect_active_nav(): string {
        if ( is_page( (int) get_option( 'ovr_page_search' ) ) ) {
            return 'search_listings';
        }
        if ( is_page( (int) get_option( 'ovr_page_pricing' ) ) ) {
            return 'advertise';
        }
        if ( is_page( (int) get_option( 'ovr_page_login' ) ) || is_page( (int) get_option( 'ovr_page_register' ) ) ) {
            return 'account';
        }
        return '';
    }

    /**
     * Which popup-menu TRIGGER should read as active for the current request.
     */
    private static function detect_active_group(): string {
        $in_explore = is_page( (int) get_option( 'ovr_page_search' ) )
            || is_page( (int) get_option( 'ovr_page_village_sections' ) )
            || is_tax( 'ovr_village' );
        if ( $in_explore ) {
            return 'explore';
        }
        $in_site_info = is_page( (int) get_option( 'ovr_page_owner_information' ) )
            || is_page( (int) get_option( 'ovr_page_user_agreement' ) )
            || is_page( (int) get_option( 'ovr_page_forgot_password' ) )
            || is_page( (int) get_option( 'ovr_page_contact' ) )
            || is_page( (int) get_option( 'ovr_page_register' ) )
            || is_page( (int) get_option( 'ovr_page_renting_overview' ) )
            || is_page( (int) get_option( 'ovr_page_verified_owners' ) );
        if ( $in_site_info ) {
            return 'site_info';
        }
        $in_account = is_page( (int) get_option( 'ovr_page_dashboard' ) )
            || is_page( (int) get_option( 'ovr_page_id_request' ) );
        if ( $in_account ) {
            return 'account';
        }
        return '';
    }
}
