<?php
namespace OVR\Frontend;

use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Navigation {
    public function init(): void {
        add_filter( 'wp_nav_menu_items', [ $this, 'add_auth_links' ], 10, 2 );
        // Mark feedback P5: navigation must open in the same tab. Strip any
        // "open in new tab" target from internal menu links (a menu-item setting
        // or theme walker may add it); genuinely external links keep theirs.
        add_filter( 'nav_menu_link_attributes', [ $this, 'force_same_tab' ], 10, 3 );
    }

    /**
     * Remove target="_blank" from same-site menu links so navigation stays in
     * the current tab. External links (different host) are left untouched.
     *
     * @param array<string,string> $atts
     * @param mixed                $item
     * @param mixed                $args
     * @return array<string,string>
     */
    public function force_same_tab( array $atts, $item = null, $args = null ): array {
        $href = $atts['href'] ?? '';
        if ( '' === $href ) {
            return $atts;
        }
        $host = wp_parse_url( $href, PHP_URL_HOST );
        $site = wp_parse_url( home_url(), PHP_URL_HOST );

        // Relative links (no host) or same-host links must never open a new tab.
        if ( empty( $host ) || 0 === strcasecmp( (string) $host, (string) $site ) ) {
            unset( $atts['target'] );
            if ( isset( $atts['rel'] ) ) {
                $atts['rel'] = trim( (string) preg_replace( '/\bnoopener\b|\bnoreferrer\b/', '', $atts['rel'] ) );
                if ( '' === $atts['rel'] ) {
                    unset( $atts['rel'] );
                }
            }
        }
        return $atts;
    }

    /**
     * Add dynamic auth/dashboard links to primary menu.
     */
    public function add_auth_links( string $items, object $args ): string {
        if ( 'primary' !== ( $args->theme_location ?? '' ) ) {
            return $items;
        }

        if ( is_user_logged_in() ) {
            $dashboard_url = Pages::get_page_url( 'ovr_page_dashboard' );
            $items .= '<li class="menu-item ovr-nav-dashboard"><a href="' . esc_url( $dashboard_url ) . '">' . esc_html__( 'Dashboard', 'ovr-core' ) . '</a></li>';
            if ( current_user_can( 'manage_options' ) ) {
                $items .= '<li class="menu-item ovr-nav-admin"><a href="' . esc_url( admin_url() ) . '">' . esc_html__( 'Site Admin', 'ovr-core' ) . '</a></li>';
            }
            $items .= '<li class="menu-item ovr-nav-logout"><a href="' . esc_url( wp_logout_url( home_url() ) ) . '">' . esc_html__( 'Logout', 'ovr-core' ) . '</a></li>';
        } else {
            $login_url    = Pages::get_page_url( 'ovr_page_login' );
            $register_url = Pages::get_page_url( 'ovr_page_register' );
            $items .= '<li class="menu-item ovr-nav-login"><a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Sign In', 'ovr-core' ) . '</a></li>';
            $items .= '<li class="menu-item ovr-nav-register"><a href="' . esc_url( $register_url ) . '" class="ovr-btn ovr-btn-primary">' . esc_html__( 'List Your Property', 'ovr-core' ) . '</a></li>';
        }

        return $items;
    }
}
