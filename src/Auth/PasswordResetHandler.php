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
            $reset_key = get_password_reset_key( $user );

            if ( ! is_wp_error( $reset_key ) ) {
                $reset_url = add_query_arg( [
                    'action' => 'rp',
                    'key'    => $reset_key,
                    'login'  => rawurlencode( $user->user_login ),
                ], Pages::get_page_url( 'ovr_page_login' ) );

                // Route through the admin-editable template system (M3 F6).
                $sent = class_exists( '\OVR\Email\Mailer' )
                    ? \OVR\Email\Mailer::send( 'password_reset', [
                        'user_name' => $user->display_name,
                        'reset_url' => $reset_url,
                    ], [ 'user_email' => $email ] )
                    : false;

                // Safety net: if the template is missing/disabled, still send a
                // plain reset email so account recovery never breaks.
                if ( ! $sent ) {
                    $message = sprintf(
                        /* translators: 1: Site name, 2: Reset URL */
                        __( "Hello,\n\nSomeone requested a password reset for your account at %1\$s.\n\nTo reset your password, click the link below:\n%2\$s\n\nIf you didn't request this, you can safely ignore this email.\n\nThanks,\nOur Village Rentals", 'ovr-core' ),
                        get_bloginfo( 'name' ),
                        $reset_url
                    );
                    wp_mail(
                        $email,
                        sprintf(
                            /* translators: %s: Site name */
                            __( '[%s] Password Reset Request', 'ovr-core' ),
                            get_bloginfo( 'name' )
                        ),
                        $message
                    );
                }
            }
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
