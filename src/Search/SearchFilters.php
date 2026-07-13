<?php
/**
 * Search Filters.
 *
 * Provides filter option data for the sidebar.
 *
 * @package OVR\Search
 * @since   1.0.0
 */

namespace OVR\Search;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SearchFilters {

    public function init(): void {}

    /**
     * Get all village SECTIONS (taxonomy terms). Kept for the homepage/featured
     * strips; the search facet now uses the free-text village names below.
     */
    public static function get_villages(): array {
        $terms = get_terms( [
            'taxonomy'   => 'ovr_village',
            'hide_empty' => true,
            'orderby'    => 'name',
        ] );
        return ! is_wp_error( $terms ) ? $terms : [];
    }

    /**
     * Distinct Village Names actually entered on published listings — powers the
     * search-sidebar dropdown. Free text, so the list grows itself (no taxonomy
     * to maintain). Cached briefly.
     *
     * @return string[]
     */
    public static function get_village_names(): array {
        global $wpdb;

        $cached = wp_cache_get( 'ovr_village_names', 'ovr' );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $rows = $wpdb->get_col(
            "SELECT DISTINCT m.meta_value
             FROM {$wpdb->postmeta} m
             INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE m.meta_key = '_ovr_village_name'
               AND m.meta_value <> ''
               AND p.post_type = 'ovr_property'
               AND p.post_status = 'publish'
             ORDER BY m.meta_value ASC"
        );
        $names = array_values( array_filter( array_map( 'trim', (array) $rows ), 'strlen' ) );

        wp_cache_set( 'ovr_village_names', $names, 'ovr', 5 * MINUTE_IN_SECONDS );
        return $names;
    }

    /**
     * Get property types for filter.
     */
    public static function get_property_types(): array {
        $terms = get_terms( [
            'taxonomy'   => 'ovr_property_type',
            'hide_empty' => true,
            'orderby'    => 'name',
        ] );
        return ! is_wp_error( $terms ) ? $terms : [];
    }

    /**
     * Get amenities for filter.
     */
    public static function get_amenities(): array {
        $terms = get_terms( [
            'taxonomy'   => 'ovr_amenity',
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );
        return ! is_wp_error( $terms ) ? $terms : [];
    }

    /**
     * Get views for filter.
     */
    public static function get_views(): array {
        $terms = get_terms( [
            'taxonomy'   => 'ovr_view',
            'hide_empty' => false,
            'orderby'    => 'name',
        ] );
        return ! is_wp_error( $terms ) ? $terms : [];
    }

    /**
     * Get features for filter.
     */
    public static function get_features(): array {
        $terms = get_terms( [
            'taxonomy'   => 'ovr_feature',
            'hide_empty' => false,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );
        return ! is_wp_error( $terms ) ? $terms : [];
    }

    /**
     * Get price range for slider.
     */
    public static function get_price_range(): array {
        global $wpdb;

        $cache_key = 'ovr_price_range';
        $range     = wp_cache_get( $cache_key, 'ovr' );

        if ( false === $range ) {
            $range = $wpdb->get_row(
                "SELECT MIN(CAST(pm.meta_value AS DECIMAL(10,2))) as min_price,
                        MAX(CAST(pm.meta_value AS DECIMAL(10,2))) as max_price
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_ovr_base_price'
                   AND p.post_type = 'ovr_property'
                   AND p.post_status = 'publish'
                   AND pm.meta_value > 0",
                ARRAY_A
            );
            wp_cache_set( $cache_key, $range, 'ovr', HOUR_IN_SECONDS );
        }

        return [
            'min' => (float) ( $range['min_price'] ?? 0 ),
            'max' => (float) ( $range['max_price'] ?? 10000 ),
        ];
    }

    /**
     * Resolve a display image URL for a village term.
     *
     * Resolution order: attachment-ID term meta → URL term meta →
     * `ovr_village_default_images` filter (slug-keyed map) → placeholder.
     * Shared by the Villages slider widget and the search "Featured
     * Villages" strip so both render the same artwork.
     *
     * @param \WP_Term $term Village term.
     */
    public static function get_village_image( \WP_Term $term ): string {
        $att_id = (int) get_term_meta( $term->term_id, 'ovr_village_image_id', true );
        if ( $att_id ) {
            $url = wp_get_attachment_image_url( $att_id, 'large' );
            if ( $url ) {
                return $url;
            }
        }

        $url = (string) get_term_meta( $term->term_id, 'ovr_village_image', true );
        if ( '' !== $url ) {
            return $url;
        }

        $map = apply_filters( 'ovr_village_default_images', self::default_village_images() );
        if ( isset( $map[ $term->slug ] ) ) {
            return (string) $map[ $term->slug ];
        }

        return OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';
    }

    /**
     * Default slug-keyed image map for the well-known Villages town squares.
     *
     * Single source of truth (the Villages slider widget delegates here).
     * NOTE: these URLs come from the design mockup's temporary CDN and may
     * expire. Replace them by setting a term image (term meta
     * `ovr_village_image_id`) or via the `ovr_village_default_images` filter.
     *
     * @return array<string,string>
     */
    public static function default_village_images(): array {
        return [];
    }

    /**
     * Get bedroom options.
     */
    public static function get_bedroom_options(): array {
        return [
            1 => __( '1+', 'ovr-core' ),
            2 => __( '2+', 'ovr-core' ),
            3 => __( '3+', 'ovr-core' ),
            4 => __( '4+', 'ovr-core' ),
            5 => __( '5+', 'ovr-core' ),
        ];
    }
}
