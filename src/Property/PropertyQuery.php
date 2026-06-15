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
        $args = self::build_args( $filters );

        // When bump-first ordering is requested, attach a LEFT JOIN clause
        // (see boost_order_clauses) only for this query, then detach it so it
        // never leaks into other queries on the page.
        if ( ! empty( $args['_ovr_boost_first'] ) ) {
            add_filter( 'posts_clauses', [ self::class, 'boost_order_clauses' ], 10, 2 );
            $query = new \WP_Query( $args );
            remove_filter( 'posts_clauses', [ self::class, 'boost_order_clauses' ], 10 );
            return $query;
        }

        return new \WP_Query( $args );
    }

    /**
     * meta_query clauses that exclude listings hidden from the public site:
     * owner status = inactive, or admin status in (hidden/suspended/
     * pending_review). Each clause keeps listings whose meta is absent.
     *
     * @return array<int, array>
     */
    public static function visibility_clauses(): array {
        return [
            [
                'relation' => 'OR',
                [ 'key' => '_ovr_listing_status', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_ovr_listing_status', 'value' => 'inactive', 'compare' => '!=' ],
            ],
            [
                'relation' => 'OR',
                [ 'key' => '_ovr_admin_status', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_ovr_admin_status', 'value' => [ 'hidden', 'suspended', 'pending_review' ], 'compare' => 'NOT IN' ],
            ],
        ];
    }

    /**
     * Whether a single listing may be shown publicly (Phase 8B). The owner and
     * admins can always view their own listing regardless of status.
     */
    public static function is_publicly_visible( int $post_id ): bool {
        $owner = (string) get_post_meta( $post_id, '_ovr_listing_status', true );
        if ( 'inactive' === $owner ) {
            return false;
        }
        $admin = (string) get_post_meta( $post_id, '_ovr_admin_status', true );
        if ( in_array( $admin, [ 'hidden', 'suspended', 'pending_review' ], true ) ) {
            return false;
        }
        return true;
    }

    /**
     * Build a meta_query clause matching listings whose boost is live: the flag
     * is set AND the expiry is unset/empty/in the future.
     *
     * @return array
     */
    private static function active_boost_clause( string $flag_key, string $expires_key ): array {
        return [
            'relation' => 'AND',
            [ 'key' => $flag_key, 'value' => '1' ],
            [
                'relation' => 'OR',
                [ 'key' => $expires_key, 'compare' => 'NOT EXISTS' ],
                [ 'key' => $expires_key, 'value' => '', 'compare' => '=' ],
                [ 'key' => $expires_key, 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ],
            ],
        ];
    }

    /**
     * posts_clauses filter: order listings by (1) an ACTIVE paid "Top of Page"
     * promotion, then (2) recency — the greater of the publish date and the
     * free-bump timestamp (Feature F), so a bump floats the listing back to the
     * top. LEFT JOINs every signal so listings without the meta are kept (an
     * INNER JOIN via meta_key orderby would drop them). Only runs when the query
     * opted in via `_ovr_boost_first`.
     *
     * @param array     $clauses
     * @param \WP_Query  $q
     * @return array
     */
    public static function boost_order_clauses( array $clauses, \WP_Query $q ): array {
        if ( ! $q->get( '_ovr_boost_first' ) ) {
            return $clauses;
        }
        global $wpdb;
        $today = current_time( 'Y-m-d' );

        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ovr_bf ON ovr_bf.post_id = {$wpdb->posts}.ID AND ovr_bf.meta_key = '_ovr_is_bumped' ";
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ovr_be ON ovr_be.post_id = {$wpdb->posts}.ID AND ovr_be.meta_key = '_ovr_bump_expires' ";
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ovr_lb ON ovr_lb.post_id = {$wpdb->posts}.ID AND ovr_lb.meta_key = '" . Bump::META_LAST_BUMP . "' ";

        $is_bumped = $wpdb->prepare(
            "(CASE WHEN ovr_bf.meta_value = '1' AND ( ovr_be.meta_value IS NULL OR ovr_be.meta_value = '' OR ovr_be.meta_value >= %s ) THEN 1 ELSE 0 END)",
            $today
        );

        // Effective recency = max(publish time, last-bump time). Free bumps
        // store a Unix timestamp; un-bumped listings fall back to post_date.
        $recency = "GREATEST( UNIX_TIMESTAMP({$wpdb->posts}.post_date_gmt), COALESCE(ovr_lb.meta_value + 0, 0) )";

        $clauses['orderby']  = $is_bumped . ' DESC, ' . $recency . ' DESC, ' . $clauses['orderby'];
        $clauses['groupby']  = "{$wpdb->posts}.ID"; // dedupe rows from the joins
        return $clauses;
    }

    /**
     * Build the WP_Query args for the current filter set. Shared by the
     * paginated results query and the unpaginated map-points query so both
     * always apply identical filtering.
     *
     * @param array $filters Search/filter parameters.
     * @return array
     */
    private static function build_args( array $filters = [] ): array {
        $args = [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $filters['per_page'] ?? 12 ),
            'paged'          => absint( $filters['paged'] ?? 1 ),
            'meta_query'     => [ 'relation' => 'AND' ],
            'tax_query'      => [ 'relation' => 'AND' ],
        ];

        // Public visibility gate (Phase 8B). Hide listings the owner set to
        // Inactive, and listings an admin set to anything other than Approved.
        // "NOT EXISTS OR != / NOT IN" so legacy listings without the meta (which
        // predate these controls) stay visible by default.
        foreach ( self::visibility_clauses() as $clause ) {
            $args['meta_query'][] = $clause;
        }

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

        // Village filter — matches the free-text Village Name meta. Selected
        // names are matched exactly.
        $village_names = array_values( array_filter(
            array_map( static fn( $v ) => sanitize_text_field( (string) $v ), (array) ( $filters['village'] ?? [] ) ),
            'strlen'
        ) );
        if ( $village_names ) {
            $args['meta_query'][] = [
                'key'     => '_ovr_village_name',
                'value'   => $village_names,
                'compare' => 'IN',
            ];
        }

        // Village Section filter — the curated ovr_village taxonomy (the
        // required Section dropdown on each listing).
        $section_terms = $clean_slugs( $filters['village_section'] ?? [] );
        if ( $section_terms ) {
            $args['tax_query'][] = [
                'taxonomy' => 'ovr_village',
                'field'    => 'slug',
                'terms'    => $section_terms,
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

        // Views filter — match listings with ANY of the selected views.
        $view_terms = $clean_slugs( $filters['views'] ?? [] );
        if ( $view_terms ) {
            $args['tax_query'][] = [
                'taxonomy' => 'ovr_view',
                'field'    => 'slug',
                'terms'    => $view_terms,
                'operator' => 'IN',
            ];
        }

        // Features filter — must have ALL selected features.
        $feature_terms = $clean_slugs( $filters['features'] ?? [] );
        if ( $feature_terms ) {
            $args['tax_query'][] = [
                'taxonomy' => 'ovr_feature',
                'field'    => 'slug',
                'terms'    => $feature_terms,
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

        // Owner filter (Phase 22): restrict to a single landlord's listings.
        if ( ! empty( $filters['owner_id'] ) ) {
            $args['author'] = absint( $filters['owner_id'] );
        }

        // Availability search (Feature 2 + Feature 8): when a date range is
        // supplied, drop listings with a HARD block overlapping the stay. Soft
        // blocks / available overrides (show_as_available=1) stay searchable.
        if ( ! empty( $filters['checkin'] ) && ! empty( $filters['checkout'] ) ) {
            $busy = Availability::unavailable_property_ids(
                (string) $filters['checkin'],
                (string) $filters['checkout']
            );
            if ( $busy ) {
                $existing             = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : [];
                $args['post__not_in'] = array_values( array_unique( array_merge( $existing, $busy ) ) );
            }
        }

        // Featured only — flag set AND not expired.
        if ( ! empty( $filters['featured_only'] ) ) {
            $args['meta_query'][] = self::active_boost_clause( '_ovr_is_featured', '_ovr_featured_expires' );
        }

        // Homepage-slider only — flag set AND not expired.
        if ( ! empty( $filters['slider_only'] ) ) {
            $args['meta_query'][] = self::active_boost_clause( '_ovr_in_slider', '_ovr_slider_expires' );
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

        // Top of Page Priority: for the default (newest) sort, float listings
        // with an ACTIVE "bump" to the very top so they occupy the first rows
        // of the results for their village. Applied via a LEFT JOIN in query()
        // (a meta_key orderby would INNER-JOIN and drop un-bumped listings).
        // Explicit price/rating sorts are left strictly ordered.
        if ( 'newest' === $sort ) {
            $args['_ovr_boost_first'] = true;
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

        return $args;
    }

    /**
     * All matching properties' map coordinates for the current filters,
     * unpaginated. Used by the split map view so every result is plotted and
     * clustered, while the card list paginates separately.
     *
     * @param array $filters Search/filter parameters.
     * @param int   $max     Hard cap to avoid pathological payloads.
     * @return array<int, array>
     */
    public static function get_map_points( array $filters = [], int $max = 3000 ): array {
        // Versioned transient cache (M3 F12) — this query loops every matching
        // listing's meta/terms, so caching it is a meaningful win. The version
        // is bumped by Core\Performance whenever a listing is saved/deleted, so
        // results never go stale.
        $ver       = class_exists( '\OVR\Core\Performance' ) ? \OVR\Core\Performance::mappoints_version() : 1;
        $cache_key = 'ovr_mappoints_' . $ver . '_' . md5( wp_json_encode( [ $filters, $max ] ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $args = self::build_args( $filters );
        $args['posts_per_page']         = $max;
        $args['paged']                  = 1;
        $args['fields']                 = 'ids';
        $args['no_found_rows']          = true;
        $args['update_post_meta_cache'] = true;
        // Prime the term cache in bulk (M3 F10 reads each listing's property type
        // for its map pin icon) — far cheaper than per-post term queries.
        $args['update_post_term_cache'] = true;
        unset( $args['meta_key'], $args['orderby'], $args['order'] );

        $ids    = ( new \WP_Query( $args ) )->posts;
        $points = [];

        // Availability state for the map (M3 F10): one query for the listings
        // that are hard-blocked for tonight, so each pin can show available vs
        // booked without a per-point query.
        $today    = current_time( 'Y-m-d' );
        $tomorrow = gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) );
        $busy     = array_flip( Availability::unavailable_property_ids( $today, $tomorrow ) );

        foreach ( (array) $ids as $pid ) {
            $pid = (int) $pid;
            $lat = (float) get_post_meta( $pid, '_ovr_latitude', true );
            $lng = (float) get_post_meta( $pid, '_ovr_longitude', true );
            // Skip unset (0,0) coordinates AND anything outside the valid
            // geographic range. A single corrupt value (e.g. a longitude that
            // lost its decimal point) would otherwise blow up the map's
            // fitBounds and zoom the whole view out to nothing.
            if ( 0.0 === $lat || 0.0 === $lng
                || $lat < -90.0 || $lat > 90.0
                || $lng < -180.0 || $lng > 180.0
            ) {
                continue;
            }

            // Primary property type → pin icon category.
            $type_slug  = '';
            $type_label = '';
            $types      = wp_get_post_terms( $pid, 'ovr_property_type' );
            if ( ! is_wp_error( $types ) && ! empty( $types ) ) {
                $type_slug  = (string) $types[0]->slug;
                $type_label = (string) $types[0]->name;
            }

            // Active featured boost → gold pin.
            $featured = '1' === (string) get_post_meta( $pid, '_ovr_is_featured', true )
                && self::boost_unexpired( (string) get_post_meta( $pid, '_ovr_featured_expires', true ), $today );

            $points[] = [
                'id'       => $pid,
                'title'    => get_the_title( $pid ),
                'url'      => get_permalink( $pid ),
                'thumb'    => get_the_post_thumbnail_url( $pid, 'medium' ) ?: '',
                'price'    => (float) get_post_meta( $pid, '_ovr_base_price', true ),
                'beds'     => (int) get_post_meta( $pid, '_ovr_bedrooms', true ),
                'baths'    => (float) get_post_meta( $pid, '_ovr_bathrooms', true ),
                'lat'      => $lat,
                'lng'      => $lng,
                'type'     => $type_slug,
                'type_label' => $type_label,
                'featured' => $featured,
                'avail'    => isset( $busy[ $pid ] ) ? 'booked' : 'available',
            ];
        }

        set_transient( $cache_key, $points, 10 * MINUTE_IN_SECONDS );
        return $points;
    }

    /** True when a boost-expiry date is empty (no expiry) or still in the future. */
    private static function boost_unexpired( string $expires, string $today ): bool {
        return '' === $expires || $expires >= $today;
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
     * Get featured properties (active Featured boost only).
     */
    public static function get_featured( int $count = 6 ): \WP_Query {
        return self::query( [
            'featured_only' => true,
            'per_page'      => $count,
            'sort'          => 'newest',
        ] );
    }

    /**
     * Listings with an active Homepage Slider boost, for the homepage rail.
     * Falls back to featured listings when nobody has bought the slider, so
     * the homepage section is never empty.
     */
    public static function get_slider( int $count = 6 ): \WP_Query {
        // Manual ordering (M3 F9): when configured, show exactly these listings
        // in the admin-defined order (visibility gate still applies).
        $settings = (array) get_option( 'ovr_settings', [] );
        if ( 'manual' === ( $settings['homepage_featured_mode'] ?? 'auto' ) && ! empty( $settings['homepage_featured_ids'] ) ) {
            $ids = array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $settings['homepage_featured_ids'] ) ) ) );
            if ( $ids ) {
                $args = self::build_args( [ 'per_page' => $count, 'sort' => 'newest' ] );
                unset( $args['_ovr_boost_first'], $args['meta_key'], $args['orderby'], $args['order'] );
                $args['post__in']       = $ids;
                $args['orderby']        = 'post__in';
                $args['posts_per_page'] = $count;
                $manual = new \WP_Query( $args );
                if ( $manual->have_posts() ) {
                    return $manual;
                }
            }
        }

        $slider = self::query( [
            'slider_only' => true,
            'per_page'    => $count,
            'sort'        => 'newest',
        ] );

        if ( $slider->have_posts() ) {
            return $slider;
        }
        return self::get_featured( $count );
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
