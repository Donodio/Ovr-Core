<?php
namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Onboarding {
    public function init(): void {}

    /**
     * Render the /welcome/ screen.
     *
     * Reachable by:
     *   • Brand-new landlords who just completed registration
     *     (ovr_first_login === '1') — the screen is shown exactly once.
     *   • Anyone who lands on /welcome/ directly — the flag is cleared
     *     on render so a refresh routes to dashboard thereafter.
     *
     * If a logged-in user is fully onboarded (profile 100% AND no
     * ovr_first_login flag) the welcome screen renders a neutral
     * "Welcome back" greeting — it does NOT celebrate as if the account
     * were just created.
     */
    public static function render(): string {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_login' ) );
            exit;
        }

        $user = wp_get_current_user();

        $is_first_login  = (string) get_user_meta( $user->ID, 'ovr_first_login', true ) === '1';
        $profile_complete = ProfileCompletion::percent( $user->ID );

        // Clear the first-login flag on render so a refresh (or the next
        // login) routes to dashboard, not back to /welcome/.
        if ( $is_first_login ) {
            delete_user_meta( $user->ID, 'ovr_first_login' );
        }

        $dashboard_url   = Pages::get_page_url( 'ovr_page_dashboard' );
        $profile_url     = add_query_arg( 'tab', 'profile', $dashboard_url );
        $add_listing_url = add_query_arg( 'tab', 'add-listing', $dashboard_url );
        $search_url      = Pages::get_page_url( 'ovr_page_search' );
        $pricing_url     = Pages::get_page_url( 'ovr_page_pricing' );

        return TemplateLoader::get_rendered( 'auth/onboarding.php', [
            'user'              => $user,
            'is_first_login'    => $is_first_login,
            'profile_complete'  => $profile_complete,
            'dashboard_url'     => $dashboard_url,
            'profile_url'       => $profile_url,
            'add_listing_url'   => $add_listing_url,
            'search_url'        => $search_url,
            'pricing_url'       => $pricing_url,
        ] );
    }
}
