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
     * Get all villages for filter dropdown.
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
        return [
            'spanish-springs'     => 'https://lh3.googleusercontent.com/aida-public/AB6AXuC1YsABaBy8WoSIubfYsTyuqB7NW4wTFqh-j5wV_88XOwrqJ5r3dH5JIbi8-sOR_GGWleTF8R-yqqRgm3nxkn8FUkMtMqs8IYpUiM0tNU3beMOQOwCNqYXUcGteHwXsZoserQe9gSVOgebZRpzdOpurn70JqWTO8-CTfSAUX-khoyATK2Fdhs92pzKx2eiSnTLbybaRvtCQKAfbrsG0UmT5HR6p0QVV6mkqDsL1_NnuPJKPBpBTyYLIJkp3HYxG4xlSJ1jSpHVIRJA',
            'lake-sumter-landing' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCqTrKzoFWLVrTX1HaQEIvRXvpdIVVQ8zVu1rdrbiElbtGu9sWNOyDWSQ5e4rhmmgvpeeFldV8ywrKTlVcADYpYIY__pd6M4eKJmDF1VY_y52oH6ALHseplnGAZTvyXvUyWU8SckB8P1dK8AWDAZ1PsvfnzjvO282YUYIqPNeTFgp7wgNQxyOxhWGglRygmqEj2_W8q4MJ1ln77K5r5mAW87Nn04B7KoM3LJJ_iUcH-QE5UY-q3yz1KcJZuT5RzlA0MjCqrka0j9po',
            'brownwood'           => 'https://lh3.googleusercontent.com/aida-public/AB6AXuD7Rry-5U3dUW-EV1UkaaZwOmnmf1YOqMs4-RyT5v3X79Mcvp1elcz_BEtqM6--l29C_4WncFg01UNcyoekAheJ5YOsSRUBh0AtHr8CSdjqeVzz5QFLIBHWairGsAmboqcV4jkuK7ePPRZNbnOfA-UmqQekaWAIRvtYD_tbUqyqupEcsbOcSkULJe91xeP40Awf0Dnixq4z_ednVxwxAzPIoLwlB1CPfPoXyAN1cN-WEM8-rkogUiso49UEF9fRyUt01HrOvHG3C9c',
            'sawgrass'            => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDiEtvuNzzDvPZ40zz9dzi1lo36EXIrlW4ab7yZFkCPlou3gVM3_7A4547cGV1CwMmls5jyNUx0iM-TGM6RgP8T2b_LyJ4o7-MOX6D_d9K_I6nBiUIgtqpc8nwvH2MjT7G2GuVajUXzPudYZ1QrE5sbnwmyarG2IVFZGAz7P8P-1NZDXIZAyzabBJPDZAnAUc15grnSXWo2Dk3LXyIF8oGbzvBOAUAw4AKFSAw0O8zKDLqnsefGwGFENprSFpfmRRouxk471uLK-kg',
            'eastport'            => 'https://lh3.googleusercontent.com/aida-public/AB6AXuClP_zhhYZZr2clqU1AjxE0_iWBAUvCxN4ENAlZ3ksCrrywrxBmlWN8mqjji9jiMvSLdTCVqKfUgHk6VP34f-Hkch6dqWkqRORJmCdlOn8tols0lqrNyly5fDuxEMut20oZs2ReVrf_2DRkGJ3mhFw-yCGgXMwIZHlQ64_k8hAk-0oqYBDl4ikXw1LGf6a6gAZJ27FM3lfnQGJVHwUwg9vbV-yqhVbIpKYGaMxjJPIGdUXDXzSiTjh_Oxn9uu6cnf3NKRxiC1OFj8w',
        ];
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
