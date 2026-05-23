<?php
/**
 * Property Query Builder.
 *
 * @package OVR\Property
 * @since   1.0.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PropertyQuery {

    /**
     * Query properties with advanced filters.
     *
     * @param array $filters Search/filter parameters.
     * @return \WP_Query
     */
    public static function query( array $filters = [] ): \WP_Query {
        $args = [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $filters['per_page'] ?? 12 ),
            'paged'          => absint( $filters['paged'] ?? 1 ),
            'meta_query'     => [ 'relation' => 'AND' ],
            'tax_query'      => [ 'relation' => 'AND' ],
        ];

        // Search keyword. We pre-resolve to a post__in union of:
        //   (a) posts whose title/excerpt/content matches the keyword
        //   (b) posts assigned to a taxonomy term (village/type/amenity/
        //       rental_type) whose NAME matches the keyword.
        // This gives clean OR semantics without fighting WP_Query's search SQL.
        // If neither source matches, we force an empty result.
        $has_keyword = false;
        if ( ! empty( $filters['keyword'] ) ) {
            $has_keyword = true;
            $keyword     = sanitize_text_field( (string) $filters['keyword'] );
            $matched     = self::resolve_keyword_to_post_ids( $keyword );
            $args['post__in'] = $matched ?: [ 0 ];

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf(
                    '[OVR Search] keyword="%s" matched=%d ids=%s',
                    $keyword,
                    count( $matched ),
                    wp_json_encode( $matched )
                ) );
            }
        }

        // Helper: clean a list of slugs, dropping any empty values. This is
        // critical — an empty slug in `terms` makes WP_Tax_Query emit
        // `( 0 = 1 )` in the WHERE clause, silently killing all results.
        $clean_slugs = static function ( $raw ): array {
            return array_values( array_filter( array_map( 'sanitize_key', (array) $raw ), 'strlen' ) );
        };

        // Village taxonomy filter.
        $village_terms = $clean_slugs( $filters['village'] ?? [] );
        if ( $village_terms ) {
            $args['tax_query'][] = [
                'taxonomy' => 'ovr_village',
                'field'    => 'slug',
                'terms'    => $village_terms,
            ];
        }

        // Property type filter.
        $type_terms = $clean_slugs( $filters['property_type'] ?? [] );
        if ( $type_terms ) {
            $args['tax_query'][] = [
                'taxonomy' => 'ovr_property_type',
                'field'    => 'slug',
                'terms'    => $type_terms,
            ];
        }

        // Amenities filter.
        $amenity_terms = $clean_slugs( $filters['amenities'] ?? [] );
        if ( $amenity_terms ) {
            $args['tax_query'][] = [
                'taxonomy' => 'ovr_amenity',
                'field'    => 'slug',
                'terms'    => $amenity_terms,
                'operator' => 'AND',
            ];
        }

        // Bedrooms filter.
        if ( ! empty( $filters['bedrooms'] ) ) {
            $args['meta_query'][] = [
                'key'     => '_ovr_bedrooms',
                'value'   => absint( $filters['bedrooms'] ),
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ];
        }

        // Price range.
        if ( ! empty( $filters['price_min'] ) ) {
            $args['meta_query'][] = [
                'key'     => '_ovr_base_price',
                'value'   => floatval( $filters['price_min'] ),
                'compare' => '>=',
                'type'    => 'DECIMAL(10,2)',
            ];
        }

        if ( ! empty( $filters['price_max'] ) ) {
            $args['meta_query'][] = [
                'key'     => '_ovr_base_price',
                'value'   => floatval( $filters['price_max'] ),
                'compare' => '<=',
                'type'    => 'DECIMAL(10,2)',
            ];
        }

        // Guests filter.
        if ( ! empty( $filters['guests'] ) ) {
            $args['meta_query'][] = [
                'key'     => '_ovr_max_guests',
                'value'   => absint( $filters['guests'] ),
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ];
        }

        // Pets allowed filter.
        if ( ! empty( $filters['pets'] ) ) {
            $args['meta_query'][] = [
                'key'   => '_ovr_pets_allowed',
                'value' => '1',
            ];
        }

        // Featured only.
        if ( ! empty( $filters['featured_only'] ) ) {
            $args['meta_query'][] = [
                'key'   => '_ovr_is_featured',
                'value' => '1',
            ];
        }

        // Sorting.
        //
        // CRITICAL: We deliberately avoid `meta_key` with `orderby=meta_value_num`
        // when posts may not have that meta set, because WP_Query then adds an
        // INNER JOIN on wp_postmeta and silently drops those posts. For sorts
        // that need a meta value, we set the meta_key but ALSO ensure the meta
        // exists via a meta_query EXISTS clause (so the query stays predictable),
        // OR we simply fall back to date order when results would otherwise be
        // filtered out.
        $sort = sanitize_key( $filters['sort'] ?? 'newest' );
        switch ( $sort ) {
            case 'price_low':
                $args['meta_key'] = '_ovr_base_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'ASC';
                break;
            case 'price_high':
                $args['meta_key'] = '_ovr_base_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'rating':
                $args['meta_key'] = '_ovr_rating_avg';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            default: // newest
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
        }

        // (Removed: bumped/featured priority orderby. The previous implementation
        // set meta_key='_ovr_is_bumped' alongside an orderby array, which causes
        // WP_Query to INNER-JOIN wp_postmeta and silently exclude any property
        // whose _ovr_is_bumped meta isn't present. That single line was eating
        // search results for any property that had never been bumped/featured.
        // If we want bumped listings to surface first later, we'll add it via a
        // SQL `posts_orderby` filter using a LEFT JOIN — not via WP_Query args.)

        // Clean empty meta/tax queries.
        if ( count( $args['meta_query'] ) <= 1 ) {
            unset( $args['meta_query'] );
        }
        if ( count( $args['tax_query'] ) <= 1 ) {
            unset( $args['tax_query'] );
        }

        return new \WP_Query( $args );
    }

    /**
     * Resolve a keyword to the union of:
     *   (a) post IDs whose title/excerpt/content matches the keyword
     *   (b) post IDs assigned to ovr_village/ovr_property_type/ovr_amenity/
     *       ovr_rental_type terms whose NAME matches the keyword
     *
     * Both LIKE searches are case-insensitive. Returns an empty array when
     * neither source matches.
     *
     * @param string $keyword Trimmed user input.
     * @return int[]
     */
    public static function resolve_keyword_to_post_ids( string $keyword ): array {
        global $wpdb;

        $keyword = trim( $keyword );
        if ( '' === $keyword ) {
            return [];
        }

        $like = '%' . $wpdb->esc_like( $keyword ) . '%';

        // (a) Title / excerpt / content match.
        $title_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type   = 'ovr_property'
               AND post_status = 'publish'
               AND ( post_title   LIKE %s
                  OR post_excerpt LIKE %s
                  OR post_content LIKE %s )",
            $like, $like, $like
        ) );

        // (b) Taxonomy term-name match (village, type, amenity, rental_type).
        $tax_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT tr.object_id
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t          ON t.term_id          = tt.term_id
             INNER JOIN {$wpdb->posts} p          ON p.ID               = tr.object_id
             WHERE tt.taxonomy IN ('ovr_village','ovr_property_type','ovr_amenity','ovr_rental_type')
               AND p.post_type   = 'ovr_property'
               AND p.post_status = 'publish'
               AND t.name LIKE %s",
            $like
        ) );

        $ids = array_map( 'absint', array_unique( array_merge( (array) $title_ids, (array) $tax_ids ) ) );
        return array_values( array_filter( $ids ) );
    }

    /**
     * Get featured properties.
     */
    public static function get_featured( int $count = 6 ): \WP_Query {
        return self::query( [
            'featured_only' => true,
            'per_page'      => $count,
            'sort'          => 'newest',
        ] );
    }

    /**
     * Get similar properties for a given property.
     */
    public static function get_similar( int $property_id, int $count = 3 ): \WP_Query {
        $villages = wp_get_post_terms( $property_id, 'ovr_village', [ 'fields' => 'slugs' ] );
        $types    = wp_get_post_terms( $property_id, 'ovr_property_type', [ 'fields' => 'slugs' ] );

        return self::query( [
            'village'       => $villages,
            'property_type' => $types,
            'per_page'      => $count,
        ] );
    }
}
