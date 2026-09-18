<?php
/**
 * Password Reset Handler.
 *
 * @package OVR\Auth
 * @since   1.0.0
 */

namespace OVR\Auth;

use OVR\Core\Pages;
use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PasswordResetHandler {

    public function init(): void {
        add_action( 'init', [ $this, 'process_reset_request' ] );
    }

    public function process_reset_request(): void {
        if ( ! isset( $_POST['ovr_forgot_submit'] ) ) {
            return;
        }

        if ( ! isset( $_POST['ovr_forgot_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_forgot_nonce'] ) ), 'ovr_forgot_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ovr-core' ) );
        }

        $email = sanitize_email( wp_unslash( $_POST['ovr_email'] ?? '' ) );

        if ( empty( $email ) || ! is_email( $email ) ) {
            set_transient( 'ovr_forgot_errors', [ __( 'Please enter a valid email address.', 'ovr-core' ) ], 60 );
            return;
        }

        $user = get_user_by( 'email', $email );

        // Always show success message to prevent email enumeration.
        if ( $user ) {
            get_password_reset_key( $user );
        }

        set_transient( 'ovr_forgot_success', true, 60 );
        set_transient( 'ovr_forgot_email', $email, 60 );
    }

    public static function get_errors(): array {
        $errors = get_transient( 'ovr_forgot_errors' );
        delete_transient( 'ovr_forgot_errors' );
        return $errors ?: [];
    }

    public static function is_success(): bool {
        $success = get_transient( 'ovr_forgot_success' );
        delete_transient( 'ovr_forgot_success' );
        return (bool) $success;
    }

    public static function get_email(): string {
        $email = get_transient( 'ovr_forgot_email' );
        delete_transient( 'ovr_forgot_email' );
        return $email ?: '';
    }

    public static function render(): string {
        if ( is_user_logged_in() ) {
            $url = Pages::get_page_url( 'ovr_page_dashboard' );
            return '<p style="text-align:center;padding:32px">' .
                sprintf(
                    /* translators: %s: dashboard URL */
                    wp_kses( __( 'You are already signed in. Go to your <a href="%s">dashboard</a>.', 'ovr-core' ), [ 'a' => [ 'href' => [] ] ] ),
                    esc_url( $url )
                ) .
                '</p>';
        }

        return TemplateLoader::get_rendered( 'auth/forgot-password.php', [
            'errors'    => self::get_errors(),
            'success'   => self::is_success(),
            'email'     => self::get_email(),
            'login_url' => Pages::get_page_url( 'ovr_page_login' ),
        ] );
    }
}
