<?php
/**
 * Homepage Hero Slides repository (Milestone 3 Feature 7).
 *
 * A DB-backed slideshow CMS (wp_ovr_hero_slides) that feeds the Elementor
 * "OVR Hero Section" widget. Each slide carries a background image plus an
 * optional per-slide heading / subtitle / CTA. The homepage stays fully
 * Elementor-native — the widget simply reads enabled slides from here.
 *
 * @package OVR\Frontend
 * @since   2.6.0
 */

namespace OVR\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HeroSlides {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_hero_slides';
    }

    /**
     * All slides (admin view), ordered by display order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array {
        global $wpdb;
        return (array) $wpdb->get_results(
            'SELECT * FROM ' . self::table() . ' ORDER BY sort_order ASC, id ASC',
            ARRAY_A
        );
    }

    /**
     * Enabled slides resolved for front-end rendering: image URL (large) +
     * heading / subtitle / cta. Slides without a usable image are skipped so
     * the widget never renders a blank frame.
     *
     * @param string $size Image size to resolve (default 'large').
     * @return array<int, array{image:string,heading:string,subtitle:string,cta_text:string,cta_url:string}>
     */
    public static function enabled( string $size = 'large' ): array {
        global $wpdb;
        $rows = (array) $wpdb->get_results(
            'SELECT * FROM ' . self::table() . ' WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC',
            ARRAY_A
        );

        $out = [];
        foreach ( $rows as $row ) {
            $image_id = (int) ( $row['image_id'] ?? 0 );
            $image    = $image_id ? (string) wp_get_attachment_image_url( $image_id, $size ) : '';
            if ( '' === $image ) {
                continue;
            }
            $out[] = [
                'image'    => $image,
                'heading'  => (string) ( $row['heading'] ?? '' ),
                'subtitle' => (string) ( $row['subtitle'] ?? '' ),
                'cta_text' => (string) ( $row['cta_text'] ?? '' ),
                'cta_url'  => (string) ( $row['cta_url'] ?? '' ),
            ];
        }
        return $out;
    }

    /** Number of enabled slides — cheap check for the widget. */
    public static function has_enabled(): bool {
        global $wpdb;
        return (int) $wpdb->get_var(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_enabled = 1 AND image_id > 0'
        ) > 0;
    }

    /**
     * Replace the entire slide set with the supplied ordered rows. Mirrors the
     * whole-form save used by Featured Cities: simplest correct semantics for a
     * small ordered list. Rows arrive in display order; sort_order is assigned
     * from the index. Rows with no image are dropped.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public static function replace( array $rows ): void {
        global $wpdb;
        $table = self::table();
        $now   = current_time( 'mysql' );

        // Wipe and re-insert. A slideshow is tiny, so the churn is negligible
        // and this keeps ordering/state perfectly in sync with the form.
        $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB

        $order = 0;
        foreach ( $rows as $row ) {
            $image_id = (int) ( $row['image_id'] ?? 0 );
            if ( $image_id <= 0 ) {
                continue;
            }
            $wpdb->insert(
                $table,
                [
                    'image_id'   => $image_id,
                    'heading'    => substr( (string) ( $row['heading'] ?? '' ), 0, 255 ),
                    'subtitle'   => (string) ( $row['subtitle'] ?? '' ),
                    'cta_text'   => substr( (string) ( $row['cta_text'] ?? '' ), 0, 120 ),
                    'cta_url'    => substr( (string) ( $row['cta_url'] ?? '' ), 0, 500 ),
                    'sort_order' => $order++,
                    'is_enabled' => empty( $row['is_enabled'] ) ? 0 : 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [ '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
            );
        }

        if ( class_exists( '\OVR\Core\AuditLog' ) ) {
            \OVR\Core\AuditLog::record( 'hero_slides.changed', 'hero_slides', null, [ 'count' => $order ] );
        }
    }
}
