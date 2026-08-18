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

        // Price / rating sorts: LEFT JOIN the sort meta and COALESCE so listings
        // without that meta are kept (unpriced/rated sort last) instead of being
        // INNER-JOINed away.
        if ( ! empty( $args['_ovr_sort_meta'] ) ) {
            add_filter( 'posts_clauses', [ self::class, 'sort_order_clauses' ], 10, 2 );
            $query = new \WP_Query( $args );
            remove_filter( 'posts_clauses', [ self::class, 'sort_order_clauses' ], 10 );
            return $query;
        }

        return new \WP_Query( $args );
    }

    /**
     * posts_clauses filter for price/rating sorts. LEFT JOINs the sort meta so
     * every matching listing is retained (a meta_key orderby would INNER-JOIN
     * and drop listings missing the meta) and orders by COALESCE(meta, sentinel)
     * — unpriced/untated listings sort to the end in ascending order.
     */
    public static function sort_order_clauses( array $clauses, \WP_Query $q ): array {
        $spec = $q->get( '_ovr_sort_meta' );
        if ( ! is_array( $spec ) || empty( $spec['key'] ) ) {
            return $clauses;
        }
        global $wpdb;
        $meta_key = sanitize_key( (string) $spec['key'] );
        $dir      = ( 'DESC' === strtoupper( (string) ( $spec['dir'] ?? 'ASC' ) ) ) ? 'DESC' : 'ASC';

        $clauses['join'] .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} ovr_sort ON ovr_sort.post_id = {$wpdb->posts}.ID AND ovr_sort.meta_key = %s ", $meta_key );
        // ASC: missing → +infinity (sorts last). DESC: missing → -infinity.
        $clauses['orderby'] = ( 'DESC' === $dir )
            ? " COALESCE( ovr_sort.meta_value + 0, -1 * 9e18 ) DESC, {$wpdb->posts}.post_date DESC"
            : " COALESCE( ovr_sort.meta_value + 0, 9e18 ) ASC, {$wpdb->posts}.post_date DESC";
        $clauses['groupby'] = "{$wpdb->posts}.ID";
        return $clauses;
    }

    /**
     * Owner-side listing statuses that must never appear on the public site:
     * switched off by the owner, or parked because their subscription lapsed
     * (SubscriptionManager::expire() writes pending_renewal).
     */
    public const HIDDEN_OWNER_STATUSES = [ 'inactive', 'pending_renewal' ];

    /**
     * Golf-cart condition slugs. These live in BOTH the ovr_feature and
     * ovr_amenity taxonomies (legacy data was imported as amenities), so any
     * code that reads golf-cart state must check both.
     */
    public const GOLF_CART_SLUGS = [ 'golf-cart-included', 'golf-cart-extra-charge' ];

    /**
     * Whether a listing offers a golf cart. True when the listing carries any
     * golf-cart condition term in the ovr_feature OR ovr_amenity taxonomy.
     */
    public static function has_golf_cart( int $post_id ): bool {
        foreach ( self::GOLF_CART_SLUGS as $slug ) {
            if ( has_term( $slug, 'ovr_feature', $post_id ) || has_term( $slug, 'ovr_amenity', $post_id ) ) {
                return true;
            }
        }

        // Fallback: legacy/imported listings store the golf-cart condition under
        // many term names ("Gas Golf Cart", "Electric Golf Cart", "Golf Cart
        // Included", …). Match any ovr_feature / ovr_amenity term whose name
        // contains "golf cart" so the spec strip reflects the real selection.
        foreach ( [ 'ovr_feature', 'ovr_amenity' ] as $tax ) {
            $terms = wp_get_post_terms( $post_id, $tax );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $t ) {
                    if ( preg_match( '/golf\s*cart/i', (string) $t->name ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * meta_query clauses that exclude listings hidden from the public site:
     * owner status in (inactive/pending_renewal), or admin status in
     * (hidden/suspended/pending_review). Each clause keeps listings whose meta
     * is absent.
     *
     * @return array<int, array>
     */
    public static function visibility_clauses(): array {
        return [
            [
                'relation' => 'OR',
                [ 'key' => '_ovr_listing_status', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_ovr_listing_status', 'value' => self::HIDDEN_OWNER_STATUSES, 'compare' => 'NOT IN' ],
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
        // Soft-deleted (archived) and any non-public post status must never
        // render publicly. Public search already scopes to 'publish', so this
        // guard is the backstop for direct single-listing access.
        if ( \OVR\PostTypes\PropertyPostType::STATUS_ARCHIVED === get_post_status( $post_id )
            || 'trash' === get_post_status( $post_id ) ) {
            return false;
        }
        $owner = (string) get_post_meta( $post_id, '_ovr_listing_status', true );
        if ( in_array( $owner, self::HIDDEN_OWNER_STATUSES, true ) ) {
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
     * posts_clauses filter: order listings by (1) an ACTIVE "Featured Property"
     * promotion, then (2) an ACTIVE "Top of Page"/Priority Placement (or free
     * bump), then (3) recency — the greater of the publish date and the free-bump
     * timestamp (Feature F). LEFT JOINs every signal so listings without the meta
     * are kept (an INNER JOIN via meta_key orderby would drop them). Only runs
     * when the query opted in via `_ovr_boost_first`.
     *
     * IMPORTANT (Mark feedback P2): this reorders the ALREADY-filtered result set.
     * A Featured listing therefore floats above standard listings ONLY when it
     * satisfies the current search filters — a featured listing that doesn't match
     * the filters is simply not in the set, so it is never shown. Homepage-Slider
     * boosts are deliberately NOT a signal here: the slider is a homepage-only
     * placement and must never affect search rankings.
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

        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ovr_ff ON ovr_ff.post_id = {$wpdb->posts}.ID AND ovr_ff.meta_key = '_ovr_is_featured' ";
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ovr_fe ON ovr_fe.post_id = {$wpdb->posts}.ID AND ovr_fe.meta_key = '_ovr_featured_expires' ";
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ovr_bf ON ovr_bf.post_id = {$wpdb->posts}.ID AND ovr_bf.meta_key = '_ovr_is_bumped' ";
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ovr_be ON ovr_be.post_id = {$wpdb->posts}.ID AND ovr_be.meta_key = '_ovr_bump_expires' ";
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ovr_lb ON ovr_lb.post_id = {$wpdb->posts}.ID AND ovr_lb.meta_key = '" . Bump::META_LAST_BUMP . "' ";
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ovr_ba ON ovr_ba.post_id = {$wpdb->posts}.ID AND ovr_ba.meta_key = '_ovr_bump_at' ";

        // Active "Featured Property" boost → very top (within the filtered set).
        $is_featured = $wpdb->prepare(
            "(CASE WHEN ovr_ff.meta_value = '1' AND ( ovr_fe.meta_value IS NULL OR ovr_fe.meta_value = '' OR ovr_fe.meta_value >= %s ) THEN 1 ELSE 0 END)",
            $today
        );
        // Active "Top of Page"/Priority Placement or free bump → next tier.
        $is_bumped = $wpdb->prepare(
            "(CASE WHEN ovr_bf.meta_value = '1' AND ( ovr_be.meta_value IS NULL OR ovr_be.meta_value = '' OR ovr_be.meta_value >= %s ) THEN 1 ELSE 0 END)",
            $today
        );

        // Effective recency = max(last-updated time, last-bump time). Free bumps
        // store a Unix timestamp; listings that were never bumped fall back to the
        // post_modified date so the most recently updated listings surface first.
        $recency = "GREATEST( UNIX_TIMESTAMP({$wpdb->posts}.post_modified_gmt), COALESCE(ovr_lb.meta_value + 0, 0) )";

        // Newest bump first *within* the bumped tier: a freshly-(re)purchased paid
        // bump ("Priority Listing") floats above older bumps, so the order is
        // Featured → Bumped (newest first) → recency → normal. ovr_ba is the
        // dedicated paid-bump timestamp; it's absent on non-bumped listings so
        // they fall through to recency ordering untouched.
        $clauses['orderby']  = $is_featured . ' DESC, ' . $is_bumped . ' DESC, COALESCE(ovr_ba.meta_value + 0, 0) DESC, ' . $recency . ' DESC, ' . $clauses['orderby'];
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

        // Village filter — matches the listing's specific-village meta
        // (_ovr_village_name). Selected names are matched exactly.
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

        // Free-text Village Name search — substring of the listing's
        // specific-village meta (phase 21 sidebar text input). LIKE keeps
        // partial typing ("Mallory") useful instead of requiring an exact name.
        $village_name_search = sanitize_text_field( (string) ( $filters['village_name'] ?? '' ) );
        if ( '' !== $village_name_search ) {
            $args['meta_query'][] = [
                'key'     => '_ovr_village_name',
                'value'   => $village_name_search,
                'compare' => 'LIKE',
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

        // Rental term filter — the ovr_rental_type taxonomy (Long/Short-Term).
        $rental_terms = $clean_slugs( $filters['rental_type'] ?? [] );
        if ( $rental_terms ) {
            $args['tax_query'][] = [
                'taxonomy' => 'ovr_rental_type',
                'field'    => 'slug',
                'terms'    => $rental_terms,
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
            // Golf-cart terms exist in BOTH the ovr_feature and ovr_amenity
            // taxonomies (legacy listings were imported with the golf-cart
            // condition stored as an amenity). Match either taxonomy so the
            // filter returns every property that actually carries a golf cart,
            // regardless of where the term was saved.
            $golf_slugs   = self::GOLF_CART_SLUGS;
            $golf_matches = array_intersect( $feature_terms, $golf_slugs );
            $other        = array_values( array_diff( $feature_terms, $golf_slugs ) );

            if ( $golf_matches ) {
                $args['tax_query'][] = [
                    'relation' => 'OR',
                    [
                        'taxonomy' => 'ovr_feature',
                        'field'    => 'slug',
                        'terms'    => $golf_matches,
                        'operator' => 'AND',
                    ],
                    [
                        'taxonomy' => 'ovr_amenity',
                        'field'    => 'slug',
                        'terms'    => $golf_matches,
                        'operator' => 'AND',
                    ],
                ];
            }
            if ( $other ) {
                $args['tax_query'][] = [
                    'taxonomy' => 'ovr_feature',
                    'field'    => 'slug',
                    'terms'    => $other,
                    'operator' => 'AND',
                ];
            }
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

        // Bathrooms filter (stored as a decimal, e.g. 2.5).
        if ( ! empty( $filters['bathrooms'] ) ) {
            $args['meta_query'][] = [
                'key'     => '_ovr_bathrooms',
                'value'   => floatval( $filters['bathrooms'] ),
                'compare' => '>=',
                'type'    => 'DECIMAL(10,1)',
            ];
        }

        // Street-address search — substring match on the listing address meta.
        if ( ! empty( $filters['address'] ) ) {
            $args['meta_query'][] = [
                'key'     => '_ovr_address',
                'value'   => sanitize_text_field( (string) $filters['address'] ),
                'compare' => 'LIKE',
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

        // Deals & Cancellations only — active paid deal (flag set AND not
        // expired). Eligibility is date-driven, so an expired promotion never
        // appears even if the flag meta lingers.
        if ( ! empty( $filters['deals_only'] ) ) {
            $args['meta_query'][] = self::active_boost_clause( '_ovr_is_deal', '_ovr_deal_expires' );
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
            case 'price_high':
                // CRITICAL: do NOT use meta_key+orderby=meta_value_num OR a
                // meta_query EXISTS here — both INNER-JOIN wp_postmeta and
                // silently drop every listing that has no _ovr_base_price.
                // Keep the sort key out of the query entirely and let
                // sort_order_clauses LEFT JOIN + COALESCE it, preserving all
                // posts (unpriced ones sort to the end).
                $args['_ovr_sort_meta'] = [ 'key' => '_ovr_base_price', 'dir' => ( 'price_high' === $sort ? 'DESC' : 'ASC' ) ];
                break;
            case 'rating':
                $args['_ovr_sort_meta'] = [ 'key' => '_ovr_rating_avg', 'dir' => 'DESC' ];
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

        // Rebuild the current results URL so every map-popup "View listing"
        // link can carry ?ovr_ref= and the property page's Back button returns
        // to these exact filters (not the bare search page).
        $ref_url = '';
        if ( isset( $_GET['view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $allowed = array_intersect_key(
                wp_unslash( $_GET ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                array_fill_keys( array_keys( $filters ), true )
            );
            $allowed = array_filter( $allowed, static function ( $v ) { return $v !== '' && $v !== null; } );
            if ( $allowed ) {
                $ref_url = add_query_arg( $allowed, \OVR\Core\Pages::get_page_url( 'ovr_page_search' ) );
            }
        }
        if ( '' !== $ref_url ) {
            $ref_url = rawurlencode( $ref_url );
        }

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
                'url'      => '' !== $ref_url ? add_query_arg( 'ovr_ref', $ref_url, get_permalink( $pid ) ) : get_permalink( $pid ),
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

        // (c) Free-text Village Name meta match. The autocomplete suggestions
        // are sourced from this meta, so the keyword query MUST be able to
        // resolve a picked suggestion back to its listings — otherwise a user
        // selecting e.g. "Spanish Springs" from the dropdown gets zero results.
        $meta_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key   = '_ovr_village_name'
               AND p.post_type   = 'ovr_property'
               AND p.post_status = 'publish'
               AND pm.meta_value LIKE %s",
            $like
        ) );

        $ids = array_map( 'absint', array_unique( array_merge( (array) $title_ids, (array) $tax_ids, (array) $meta_ids ) ) );
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

    /**
     * Ordered property IDs for the Sponsored Property Carousel.
     *
     * Composition order (left → right), de-duplicated (first wins), truncated to
     * $count:
     *   1. Sponsored   — active Featured boost, newest first.
     *   2. Curated     — owner's "Homepage Carousel" picks, in stored order.
     *   3. Recent fill — newest-published listings to reach $count.
     *
     * @param int $count Max number of cards.
     * @return int[]
     */
    public static function get_carousel_ids( int $count = 4 ): array {
        $count = max( 1, min( 120, absint( $count ) ) );

        $sponsored = array_map( 'absint', (array) ( new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => array_merge(
                [ 'relation' => 'AND' ],
                self::visibility_clauses(),
                [ self::active_boost_clause( '_ovr_is_featured', '_ovr_featured_expires' ) ]
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
        ] ) )->posts );

        $settings = (array) get_option( 'ovr_settings', [] );
        $picks    = preg_split( '/[\s,]+/', (string) ( $settings['homepage_carousel_ids'] ?? '' ) );
        $picks    = array_map( 'absint', (array) $picks );
        $picks    = array_values( array_filter( $picks ) );

        $curated = [];
        if ( $picks ) {
            $curated = array_map( 'absint', (array) ( new \WP_Query( [
                'post_type'      => 'ovr_property',
                'post_status'    => 'publish',
                'posts_per_page' => count( $picks ),
                'post__in'       => $picks,
                'orderby'        => 'post__in',
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => array_merge( [ 'relation' => 'AND' ], self::visibility_clauses() ),
            ] ) )->posts );
        }

        $combined = [];
        foreach ( array_merge( $sponsored, $curated ) as $id ) {
            if ( $id > 0 && ! isset( $combined[ $id ] ) ) {
                $combined[ $id ] = true;
            }
        }

        if ( count( $combined ) < $count ) {
            $recent = array_map( 'absint', (array) ( new \WP_Query( [
                'post_type'      => 'ovr_property',
                'post_status'    => 'publish',
                'posts_per_page' => $count - count( $combined ),
                'post__not_in'   => $combined ? array_keys( $combined ) : [ 0 ],
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => array_merge( [ 'relation' => 'AND' ], self::visibility_clauses() ),
                'orderby'        => 'date',
                'order'          => 'DESC',
            ] ) )->posts );
            foreach ( $recent as $id ) {
                if ( $id > 0 && ! isset( $combined[ $id ] ) ) {
                    $combined[ $id ] = true;
                }
            }
        }

        return array_slice( array_keys( $combined ), 0, $count );
    }
}
