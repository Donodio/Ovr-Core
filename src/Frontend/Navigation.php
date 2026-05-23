<?php
namespace OVR\Frontend;

use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Navigation {
    public function init(): void {
        add_filter( 'wp_nav_menu_items', [ $this, 'add_auth_links' ], 10, 2 );
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
