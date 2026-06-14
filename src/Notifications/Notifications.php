<?php
/**
 * Notifications.
 *
 * Listens for plugin events and dispatches transactional emails:
 *
 *   ovr_user_registered      → welcome email to new user
 *   ovr_inquiry_submitted    → landlord notification + guest confirmation
 *   ovr_property_saved       → (future) approval notice
 *
 * Uses TemplateLoader so each email has a theme-overridable HTML body
 * plus an auto-generated plain-text fallback.
 *
 * @package OVR\Notifications
 * @since   1.0.0
 */

namespace OVR\Notifications;

use OVR\Core\Pages;
use OVR\Email\Mailer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Notifications {

    public function init(): void {
        add_action( 'ovr_user_registered',   [ $this, 'on_user_registered' ], 10, 2 );
        add_action( 'ovr_inquiry_submitted', [ $this, 'on_inquiry_submitted' ], 10, 2 );
    }

    /**
     * Welcome email after a successful registration (M3 F6: admin-editable
     * `registration_welcome` template via the Mailer).
     *
     * @param int  $user_id     New user ID.
     * @param bool $is_landlord Whether the user signed up as a landlord.
     */
    public function on_user_registered( int $user_id, bool $is_landlord = false ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        Mailer::send( 'registration_welcome', [
            'user_name'     => $user->display_name,
            'user_email'    => $user->user_email,
            'login_url'     => Pages::get_page_url( 'ovr_page_login' ),
            'dashboard_url' => Pages::get_page_url( 'ovr_page_dashboard' ),
        ], [ 'user_id' => $user_id ] );
    }

    /**
     * Sends two emails when an inquiry is submitted: landlord notification +
     * guest confirmation (admin-editable `inquiry_landlord` / `inquiry_guest`).
     */
    public function on_inquiry_submitted( int $inquiry_id, int $property_id ): void {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_inquiries WHERE id = %d", $inquiry_id ),
            ARRAY_A
        );
        if ( ! $row ) return;

        $property = get_post( $property_id );
        if ( ! $property ) return;

        $landlord     = get_userdata( (int) $row['landlord_id'] );
        $property_url = get_permalink( $property_id );

        if ( $landlord && $landlord->user_email ) {
            Mailer::send( 'inquiry_landlord', [
                'guest_name'      => (string) $row['guest_name'],
                'listing_title'   => $property->post_title,
                'property_id'     => (int) $property_id,
                'property_url'    => $property_url,
                'inquiry_message' => (string) ( $row['message'] ?? '' ),
                'dashboard_url'   => Pages::get_page_url( 'ovr_page_dashboard' ),
            ], [ 'user_email' => $landlord->user_email ] );
        }

        if ( ! empty( $row['guest_email'] ) ) {
            Mailer::send( 'inquiry_guest', [
                'guest_name'    => (string) $row['guest_name'],
                'listing_title' => $property->post_title,
                'property_url'  => $property_url,
            ], [ 'user_email' => (string) $row['guest_email'] ] );
        }
    }
}
