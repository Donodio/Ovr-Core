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

use OVR\Core\TemplateLoader;
use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Notifications {

    public function init(): void {
        add_action( 'ovr_user_registered',   [ $this, 'on_user_registered' ], 10, 2 );
        add_action( 'ovr_inquiry_submitted', [ $this, 'on_inquiry_submitted' ], 10, 2 );

        // Force HTML content type for our wp_mail calls only.
        add_filter( 'wp_mail_content_type', [ $this, 'force_html_content_type' ] );
    }

    public function force_html_content_type( string $type ): string {
        return ! empty( $GLOBALS['ovr_html_email'] ) ? 'text/html' : $type;
    }

    /**
     * Welcome email after a successful registration.
     *
     * @param int  $user_id     New user ID.
     * @param bool $is_landlord Whether the user signed up as a landlord.
     */
    public function on_user_registered( int $user_id, bool $is_landlord = false ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        // Backstop: if the hook didn't pass a value, infer from roles.
        if ( ! $is_landlord ) {
            $is_landlord = in_array( 'ovr_landlord', (array) $user->roles, true );
        }

        $body_html = TemplateLoader::get_rendered( 'emails/welcome.php', [
            'user'        => $user,
            'is_landlord' => $is_landlord,
            'login_url'   => Pages::get_page_url( 'ovr_page_login' ),
            'dashboard_url' => Pages::get_page_url( 'ovr_page_dashboard' ),
            'site_name'   => get_bloginfo( 'name' ),
            'site_url'    => home_url( '/' ),
        ] );

        $subject = sprintf(
            /* translators: %s: site name */
            __( 'Welcome to %s', 'ovr-core' ),
            get_bloginfo( 'name' )
        );

        $this->send_html( $user->user_email, $subject, $body_html );
    }

    /**
     * Sends two emails when an inquiry is submitted: one to the landlord
     * (the action), one to the guest (the confirmation).
     */
    public function on_inquiry_submitted( int $inquiry_id, int $property_id ): void {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ovr_inquiries WHERE id = %d",
                $inquiry_id
            ),
            ARRAY_A
        );
        if ( ! $row ) return;

        $property = get_post( $property_id );
        if ( ! $property ) return;

        $landlord = get_userdata( (int) $row['landlord_id'] );
        $property_url = get_permalink( $property_id );

        // Landlord notification.
        if ( $landlord && $landlord->user_email ) {
            $body = TemplateLoader::get_rendered( 'emails/inquiry-landlord.php', [
                'landlord'     => $landlord,
                'property'     => $property,
                'property_url' => $property_url,
                'inquiry'      => $row,
                'dashboard_url' => Pages::get_page_url( 'ovr_page_dashboard' ),
                'site_name'    => get_bloginfo( 'name' ),
            ] );

            $subject = sprintf(
                /* translators: 1: guest name, 2: property title */
                __( 'New inquiry from %1$s for %2$s', 'ovr-core' ),
                $row['guest_name'],
                $property->post_title
            );
            $this->send_html( $landlord->user_email, $subject, $body );
        }

        // Guest confirmation.
        if ( ! empty( $row['guest_email'] ) ) {
            $body = TemplateLoader::get_rendered( 'emails/inquiry-guest.php', [
                'guest_name'   => $row['guest_name'],
                'property'     => $property,
                'property_url' => $property_url,
                'inquiry'      => $row,
                'site_name'    => get_bloginfo( 'name' ),
                'site_url'     => home_url( '/' ),
            ] );

            $subject = sprintf(
                /* translators: %s: property title */
                __( 'Your inquiry about %s has been received', 'ovr-core' ),
                $property->post_title
            );
            $this->send_html( $row['guest_email'], $subject, $body );
        }
    }

    /**
     * Send an HTML email through wp_mail with sensible from-headers.
     */
    private function send_html( string $to, string $subject, string $body_html ): bool {
        $GLOBALS['ovr_html_email'] = true;

        $from_name  = get_bloginfo( 'name' );
        $from_email = $this->get_from_email();

        $headers = [
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
        ];

        $sent = wp_mail( $to, $subject, $body_html, $headers );

        unset( $GLOBALS['ovr_html_email'] );

        return (bool) $sent;
    }

    /**
     * Resolve the From: address. Prefers an OVR settings value, falls
     * back to wordpress@<host>.
     */
    private function get_from_email(): string {
        $settings = get_option( 'ovr_settings', [] );
        if ( ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
            return $settings['from_email'];
        }
        $admin = get_option( 'admin_email' );
        if ( $admin && is_email( $admin ) ) {
            return $admin;
        }
        $host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'example.com';
        return 'no-reply@' . preg_replace( '/^www\./', '', $host );
    }
}
