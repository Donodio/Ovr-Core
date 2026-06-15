<?php
/**
 * Ad Banners repository + front-end display/click tracking (Milestone 3 F8).
 *
 * Admin-managed promotional banners (wp_ovr_ad_banners) rendered via the
 * [ovr_ad_banner placement="…"] shortcode. Each banner carries an image, a
 * destination link, a placement, an optional schedule window, and impression /
 * click counters. Clicks route through a tracked redirect so the counter is
 * accurate even when the page itself is cached.
 *
 * @package OVR\Frontend
 * @since   2.7.0
 */

namespace OVR\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdBanners {

    /** Click-tracking query var: /?ovr_ad_click=<id>. */
    public const CLICK_VAR = 'ovr_ad_click';

    /**
     * Placement slugs → human labels. Used by the admin select and to validate
     * stored/queried placements.
     *
     * @return array<string, string>
     */
    public static function placements(): array {
        return [
            'homepage'        => __( 'Homepage', 'ovr-core' ),
            'search_top'      => __( 'Search Results — Top', 'ovr-core' ),
            'search_sidebar'  => __( 'Search Results — Sidebar', 'ovr-core' ),
            'single_property' => __( 'Single Property', 'ovr-core' ),
            'dashboard'       => __( 'Owner Dashboard', 'ovr-core' ),
        ];
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_ad_banners';
    }

    /** Register the front-end click-tracking redirect. */
    public function init(): void {
        add_action( 'template_redirect', [ __CLASS__, 'maybe_handle_click' ] );
    }

    /**
     * All banners (admin view), ordered by placement then display order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array {
        global $wpdb;
        return (array) $wpdb->get_results(
            'SELECT * FROM ' . self::table() . ' ORDER BY placement ASC, sort_order ASC, id ASC',
            ARRAY_A
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Enabled banners for a placement whose schedule window covers today,
     * resolved for rendering (image URL + link). A blank start/end means
     * "no bound" on that side.
     *
     * @return array<int, array{id:int,title:string,image:string,link:string}>
     */
    public static function active( string $placement ): array {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        $rows  = (array) $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . '
                 WHERE is_enabled = 1
                   AND image_id > 0
                   AND placement = %s
                   AND ( starts_at IS NULL OR starts_at = "0000-00-00" OR starts_at <= %s )
                   AND ( ends_at   IS NULL OR ends_at   = "0000-00-00" OR ends_at   >= %s )
                 ORDER BY sort_order ASC, id ASC',
                $placement,
                $today,
                $today
            ),
            ARRAY_A
        );

        $out = [];
        foreach ( $rows as $row ) {
            $image = (string) wp_get_attachment_image_url( (int) $row['image_id'], 'large' );
            if ( '' === $image ) {
                continue;
            }
            $out[] = [
                'id'    => (int) $row['id'],
                'title' => (string) $row['title'],
                'image' => $image,
                'link'  => (string) $row['link_url'],
            ];
        }
        return $out;
    }

    /**
     * Render active banners for a placement and count an impression for each.
     * Returns '' when there is nothing to show (so callers can safely echo it).
     */
    public static function render( string $placement ): string {
        $placement = sanitize_key( $placement );
        if ( ! isset( self::placements()[ $placement ] ) ) {
            return '';
        }

        $banners = self::active( $placement );
        if ( empty( $banners ) ) {
            return '';
        }

        $html = '<div class="ovr-ad-banners ovr-ad-banners--' . esc_attr( $placement ) . '">';
        foreach ( $banners as $b ) {
            self::record_impression( $b['id'] );
            $img = '<img src="' . esc_url( $b['image'] ) . '" alt="' . esc_attr( $b['title'] ) . '" loading="lazy">';
            if ( '' !== $b['link'] ) {
                $click = add_query_arg( self::CLICK_VAR, $b['id'], home_url( '/' ) );
                $html .= '<a class="ovr-ad-banner" href="' . esc_url( $click ) . '" rel="nofollow sponsored">' . $img . '</a>';
            } else {
                $html .= '<span class="ovr-ad-banner ovr-ad-banner--static">' . $img . '</span>';
            }
        }
        $html .= '</div>';
        return $html;
    }

    public static function record_impression( int $id ): void {
        global $wpdb;
        $table = self::table();
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET impressions = impressions + 1 WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
    }

    /**
     * Handle /?ovr_ad_click=<id>: count the click and redirect to the banner's
     * stored destination. Redirecting only to the stored URL avoids an open
     * redirect.
     */
    public static function maybe_handle_click(): void {
        if ( empty( $_GET[ self::CLICK_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        $id     = (int) $_GET[ self::CLICK_VAR ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $banner = self::get( $id );
        if ( ! $banner || '' === (string) $banner['link_url'] ) {
            return;
        }

        global $wpdb;
        $table = self::table();
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET clicks = clicks + 1 WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB

        // Ad destinations are admin-entered and intentionally off-site, so a raw
        // redirect (not wp_safe_redirect, which forces same-host) is correct.
        wp_redirect( esc_url_raw( $banner['link_url'] ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
        exit;
    }

    /**
     * Insert or update a banner. Returns the row id.
     *
     * @param array<string, mixed> $data
     */
    public static function save( array $data, int $id = 0 ): int {
        global $wpdb;
        $table     = self::table();
        $placement = sanitize_key( (string) ( $data['placement'] ?? 'homepage' ) );
        if ( ! isset( self::placements()[ $placement ] ) ) {
            $placement = 'homepage';
        }

        $fields = [
            'title'      => substr( (string) ( $data['title'] ?? '' ), 0, 150 ),
            'image_id'   => (int) ( $data['image_id'] ?? 0 ),
            'link_url'   => substr( (string) ( $data['link_url'] ?? '' ), 0, 500 ),
            'placement'  => $placement,
            'starts_at'  => self::norm_date( $data['starts_at'] ?? '' ),
            'ends_at'    => self::norm_date( $data['ends_at'] ?? '' ),
            'sort_order' => (int) ( $data['sort_order'] ?? 0 ),
            'is_enabled' => empty( $data['is_enabled'] ) ? 0 : 1,
            'updated_at' => current_time( 'mysql' ),
        ];
        $formats = [ '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ];

        if ( $id > 0 ) {
            $wpdb->update( $table, $fields, [ 'id' => $id ], $formats, [ '%d' ] );
        } else {
            $fields['created_at'] = current_time( 'mysql' );
            $formats[]            = '%s';
            $wpdb->insert( $table, $fields, $formats );
            $id = (int) $wpdb->insert_id;
        }

        if ( class_exists( '\OVR\Core\AuditLog' ) ) {
            \OVR\Core\AuditLog::record( 'ad_banner.saved', 'ad_banner', $id, [ 'placement' => $placement ] );
        }
        return $id;
    }

    public static function delete( int $id ): void {
        global $wpdb;
        $wpdb->delete( self::table(), [ 'id' => $id ], [ '%d' ] );
        if ( class_exists( '\OVR\Core\AuditLog' ) ) {
            \OVR\Core\AuditLog::record( 'ad_banner.deleted', 'ad_banner', $id );
        }
    }

    public static function set_enabled( int $id, bool $enabled ): void {
        global $wpdb;
        $wpdb->update( self::table(), [ 'is_enabled' => $enabled ? 1 : 0, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $id ], [ '%d', '%s' ], [ '%d' ] );
    }

    /** Normalise a Y-m-d date string to itself or null. */
    private static function norm_date( $value ): ?string {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return null;
        }
        $d = \DateTime::createFromFormat( 'Y-m-d', $value );
        return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : null;
    }
}
