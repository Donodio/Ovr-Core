<?php
namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Onboarding {
    public function init(): void {}

    public static function render(): string {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_login' ) );
            exit;
        }

        $user = wp_get_current_user();
        $profile_complete = self::calculate_profile_completion( $user->ID );

        return TemplateLoader::get_rendered( 'auth/onboarding.php', [
            'user'              => $user,
            'profile_complete'  => $profile_complete,
            'pricing_url'      => Pages::get_page_url( 'ovr_page_pricing' ),
            'dashboard_url'    => Pages::get_page_url( 'ovr_page_dashboard' ),
        ] );
    }

    private static function calculate_profile_completion( int $user_id ): int {
        $checks = [
            ! empty( get_user_meta( $user_id, 'first_name', true ) ),
            ! empty( get_user_meta( $user_id, 'last_name', true ) ),
            ! empty( get_user_meta( $user_id, 'ovr_phone', true ) ),
            ! empty( get_user_meta( $user_id, 'description', true ) ),
        ];
        $done = count( array_filter( $checks ) );
        return (int) round( ( $done / count( $checks ) ) * 100 );
    }
}
