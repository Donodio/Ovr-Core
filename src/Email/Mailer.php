<?php
/**
 * Transactional mailer (Milestone 3 Feature 6).
 *
 * Central send path for every automated email. Loads the admin-editable
 * template by key, substitutes {{variables}}, wraps the body in a branded HTML
 * layout, resolves recipients (user/admin/both/custom), and sends via wp_mail.
 *
 * @package OVR\Email
 * @since   2.3.0
 */

namespace OVR\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Mailer {

    /**
     * Render a template to [subject, html] without sending (used by preview).
     *
     * @param array<string, scalar> $vars
     * @return array{subject:string, html:string, text:string}|null
     */
    public static function render( string $key, array $vars = [] ): ?array {
        $tpl = EmailTemplates::get( $key );
        if ( ! $tpl ) {
            return null;
        }
        $vars    = array_merge( self::globals(), $vars );
        $subject = self::substitute( (string) $tpl['subject'], $vars );
        $body    = self::substitute( (string) $tpl['body_html'], $vars );
        $text    = '' !== (string) $tpl['body_text']
            ? self::substitute( (string) $tpl['body_text'], $vars )
            : self::text_fallback( $body );

        return [
            'subject' => $subject,
            'html'    => self::wrap( $subject, $body ),
            'text'    => $text,
        ];
    }

    /**
     * Send a template. Returns true if at least one recipient was mailed.
     *
     * @param array<string, scalar> $vars        Token replacements.
     * @param array<string, mixed>  $ctx         Recipient context: user_email, user_id.
     */
    public static function send( string $key, array $vars = [], array $ctx = [] ): bool {
        $tpl = EmailTemplates::get( $key );
        if ( ! $tpl || empty( $tpl['is_enabled'] ) ) {
            return false; // disabled or unknown → silently skip.
        }

        $recipients = self::resolve_recipients( (string) $tpl['recipient'], (string) $tpl['custom_email'], $ctx );
        $recipients = array_values( array_unique( array_filter( $recipients, 'is_email' ) ) );
        if ( empty( $recipients ) ) {
            return false;
        }

        $rendered = self::render( $key, $vars );
        if ( ! $rendered ) {
            return false;
        }

        $from_name  = get_bloginfo( 'name' );
        $from_email = self::from_email();
        $headers    = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
        ];

        $sent_any = false;
        foreach ( $recipients as $to ) {
            $sent_any = wp_mail( $to, $rendered['subject'], $rendered['html'], $headers ) || $sent_any;
        }
        return $sent_any;
    }

    /**
     * Resolve the recipient address list for a template's recipient mode.
     *
     * @param array<string, mixed> $ctx
     * @return string[]
     */
    private static function resolve_recipients( string $mode, string $custom, array $ctx ): array {
        $user  = '';
        if ( ! empty( $ctx['user_email'] ) ) {
            $user = (string) $ctx['user_email'];
        } elseif ( ! empty( $ctx['user_id'] ) ) {
            $u    = get_userdata( (int) $ctx['user_id'] );
            $user = $u ? $u->user_email : '';
        }
        $admin = self::admin_email();

        switch ( $mode ) {
            case 'admin':
                return [ $admin ];
            case 'both':
                return [ $user, $admin ];
            case 'custom':
                // Allow comma-separated custom recipients.
                return array_map( 'trim', explode( ',', $custom ) );
            case 'user':
            default:
                return [ $user ];
        }
    }

    /**
     * Replace {{token}} occurrences. Unknown tokens are removed.
     *
     * @param array<string, scalar> $vars
     */
    private static function substitute( string $content, array $vars ): string {
        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static function ( $m ) use ( $vars ) {
                $k = strtolower( $m[1] );
                return array_key_exists( $k, $vars ) ? (string) $vars[ $k ] : '';
            },
            $content
        );
    }

    /**
     * Wrap an inner HTML body in the shared branded layout so every email
     * shares the same header, footer, and brand colours.
     */
    private static function wrap( string $subject, string $body ): string {
        $content   = $body;
        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url( '/' );
        ob_start();
        include __DIR__ . '/../../templates/emails/_layout.php';
        return (string) ob_get_clean();
    }

    /**
     * Build a usable plain-text fallback from an HTML body, preserving links
     * as "label (url)" so action URLs survive in the text part.
     */
    private static function text_fallback( string $body ): string {
        $body = preg_replace_callback(
            '/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            static function ( $m ) {
                $label = trim( wp_strip_all_tags( $m[2] ) );
                return $label ? $label . ' (' . $m[1] . ')' : $m[1];
            },
            $body
        );
        $text = wp_strip_all_tags( $body );
        return (string) preg_replace( '/[ \t]+/', ' ', $text );
    }

    /**
     * Global tokens available to every template.
     *
     * @return array<string, string>
     */
    public static function globals(): array {
        return [
            'site_name'   => (string) get_bloginfo( 'name' ),
            'site_url'    => (string) home_url( '/' ),
            'admin_email' => self::admin_email(),
        ];
    }

    private static function from_email(): string {
        $s = (array) get_option( 'ovr_settings', [] );
        if ( ! empty( $s['from_email'] ) && is_email( $s['from_email'] ) ) {
            return (string) $s['from_email'];
        }
        $admin = get_option( 'admin_email' );
        if ( $admin && is_email( $admin ) ) {
            return (string) $admin;
        }
        $host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'example.com';
        return 'no-reply@' . preg_replace( '/^www\./', '', $host );
    }

    private static function admin_email(): string {
        $s = (array) get_option( 'ovr_settings', [] );
        foreach ( [ 'support_email', 'from_email' ] as $k ) {
            if ( ! empty( $s[ $k ] ) && is_email( $s[ $k ] ) ) {
                return (string) $s[ $k ];
            }
        }
        return (string) get_option( 'admin_email' );
    }
}
