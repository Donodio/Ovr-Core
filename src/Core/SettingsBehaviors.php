<?php
/**
 * Settings behaviour bindings (Milestone 3 Feature 5).
 *
 * Turns the General / Media / Security settings into live behaviour so nothing
 * is a dead toggle: image compression quality, session lifetime, login
 * throttling, optional admin email-OTP 2FA, favicon output, and a shared
 * password-policy validator. Booted unconditionally (login + uploads happen
 * outside wp-admin).
 *
 * @package OVR\Core
 * @since   2.3.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingsBehaviors {

    public function init(): void {
        // Media: compression quality for generated images.
        add_filter( 'jpeg_quality', [ $this, 'image_quality' ], 20 );
        add_filter( 'wp_editor_set_quality', [ $this, 'image_quality' ], 20 );

        // Security: session lifetime.
        add_filter( 'auth_cookie_expiration', [ $this, 'session_expiration' ], 20, 3 );

        // Security: login throttling.
        add_filter( 'authenticate', [ $this, 'check_lockout' ], 30, 1 );
        add_action( 'wp_login_failed', [ $this, 'register_failure' ], 10, 1 );
        add_action( 'wp_login', [ $this, 'clear_failures' ], 10, 2 );

        // Security: optional admin email-OTP two-factor.
        add_filter( 'wp_authenticate_user', [ $this, 'maybe_two_factor' ], 30, 1 );
        add_action( 'login_form', [ $this, 'render_2fa_field' ] );

        // General: favicon on the front end.
        add_action( 'wp_head', [ $this, 'output_favicon' ], 5 );
    }

    private static function s(): array {
        return (array) get_option( 'ovr_settings', [] );
    }

    /** Media image quality (10–100). */
    public function image_quality( $quality ) {
        $q = (int) ( self::s()['image_quality'] ?? 0 );
        return $q >= 10 ? min( 100, $q ) : $quality;
    }

    /**
     * Session cookie lifetime from the configured hours (0 = WP default).
     *
     * @param int  $expiration
     * @param int  $user_id
     * @param bool $remember
     */
    public function session_expiration( $expiration, $user_id = 0, $remember = false ) {
        $hours = (int) ( self::s()['session_timeout_hours'] ?? 0 );
        if ( $hours <= 0 ) {
            return $expiration;
        }
        $base = $hours * HOUR_IN_SECONDS;
        return $remember ? $base * 2 : $base;
    }

    // ── Login throttling ──────────────────────────────────────────────

    private function lockout_key(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : 'cli';
        $ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
        return 'ovr_login_fail_' . md5( $ip );
    }

    /**
     * Block authentication when the IP is over the failure limit.
     *
     * @param mixed $user
     * @return mixed
     */
    public function check_lockout( $user ) {
        $limit = (int) ( self::s()['login_attempt_limit'] ?? 0 );
        if ( $limit <= 0 ) {
            return $user;
        }
        $fails = (int) get_transient( $this->lockout_key() );
        if ( $fails >= $limit ) {
            return new \WP_Error(
                'ovr_locked_out',
                sprintf(
                    /* translators: %d: minutes */
                    __( 'Too many failed login attempts. Please try again in about %d minutes.', 'ovr-core' ),
                    (int) ( self::s()['login_lockout_minutes'] ?? 15 )
                )
            );
        }
        return $user;
    }

    public function register_failure( $username ): void {
        $limit = (int) ( self::s()['login_attempt_limit'] ?? 0 );
        if ( $limit <= 0 ) {
            return;
        }
        $key   = $this->lockout_key();
        $fails = (int) get_transient( $key ) + 1;
        set_transient( $key, $fails, max( 1, (int) ( self::s()['login_lockout_minutes'] ?? 15 ) ) * MINUTE_IN_SECONDS );
    }

    public function clear_failures( $user_login, $user = null ): void {
        delete_transient( $this->lockout_key() );
    }

    // ── Two-factor (email OTP for privileged users) ───────────────────

    /**
     * After password verification, require an emailed code for users who can
     * manage the platform. Fails open (returns the user) if a code can't be
     * sent, and can be bypassed by defining OVR_DISABLE_2FA.
     *
     * @param mixed $user
     * @return mixed
     */
    public function maybe_two_factor( $user ) {
        if ( ! $user instanceof \WP_User ) {
            return $user;
        }
        if ( empty( self::s()['enable_2fa'] ) || ( defined( 'OVR_DISABLE_2FA' ) && OVR_DISABLE_2FA ) ) {
            return $user;
        }
        if ( ! user_can( $user, 'manage_options' ) ) {
            return $user; // 2FA applies to privileged accounts only.
        }

        $key       = 'ovr_2fa_' . $user->ID;
        $submitted = isset( $_POST['ovr_2fa_code'] ) ? preg_replace( '/\D/', '', (string) wp_unslash( $_POST['ovr_2fa_code'] ) ) : '';
        $expected  = (string) get_transient( $key );

        if ( '' !== $submitted && '' !== $expected && hash_equals( $expected, $submitted ) ) {
            delete_transient( $key );
            return $user; // verified.
        }

        // Generate + email a fresh code (valid 10 minutes).
        $code = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
        set_transient( $key, $code, 10 * MINUTE_IN_SECONDS );

        $sent = wp_mail(
            $user->user_email,
            sprintf( /* translators: %s: site name */ __( 'Your %s login code', 'ovr-core' ), get_bloginfo( 'name' ) ),
            sprintf( /* translators: %s: code */ __( "Your one-time login code is: %s\n\nIt expires in 10 minutes.", 'ovr-core' ), $code )
        );

        // Fail open: never lock an admin out because mail is misconfigured.
        if ( ! $sent ) {
            delete_transient( $key );
            return $user;
        }

        return new \WP_Error(
            'ovr_2fa_required',
            __( 'A one-time login code has been emailed to you. Enter it below to finish signing in.', 'ovr-core' )
        );
    }

    /** Render the optional one-time-code field on wp-login.php. */
    public function render_2fa_field(): void {
        if ( empty( self::s()['enable_2fa'] ) ) {
            return;
        }
        echo '<p><label for="ovr_2fa_code">' . esc_html__( 'One-time code (if emailed)', 'ovr-core' )
            . '<input type="text" name="ovr_2fa_code" id="ovr_2fa_code" class="input" inputmode="numeric" autocomplete="one-time-code" value="" size="20"></label></p>';
    }

    // ── Favicon ───────────────────────────────────────────────────────

    public function output_favicon(): void {
        $url = (string) ( self::s()['favicon_url'] ?? '' );
        if ( '' !== $url ) {
            echo '<link rel="icon" href="' . esc_url( $url ) . '">' . "\n";
        }
    }

    // ── Shared password policy ────────────────────────────────────────

    /**
     * Validate a password against the configured policy. Returns an error
     * message, or '' when the password is acceptable.
     */
    public static function password_error( string $password ): string {
        $s   = self::s();
        $min = max( 6, (int) ( $s['password_min_length'] ?? 8 ) );
        if ( strlen( $password ) < $min ) {
            return sprintf(
                /* translators: %d: minimum length */
                __( 'Password must be at least %d characters.', 'ovr-core' ),
                $min
            );
        }
        if ( ! empty( $s['password_require_mixed'] ) && ( ! preg_match( '/[A-Za-z]/', $password ) || ! preg_match( '/\d/', $password ) ) ) {
            return __( 'Password must contain both letters and numbers.', 'ovr-core' );
        }
        return '';
    }

    /** Configured per-listing document cap (0 = unlimited; default 3). */
    public static function max_documents(): int {
        $n = (int) ( self::s()['max_documents'] ?? 3 );
        return $n > 0 ? $n : 9999;
    }

    /** Configured per-listing photo cap (0 = unlimited). */
    public static function max_photos(): int {
        $n = (int) ( self::s()['max_photos'] ?? 0 );
        return $n > 0 ? $n : 0;
    }

    /** Default owner-facing status for new listings. */
    public static function default_listing_status(): string {
        $v = (string) ( self::s()['default_listing_status'] ?? 'active' );
        return in_array( $v, [ 'active', 'inactive' ], true ) ? $v : 'active';
    }
}
