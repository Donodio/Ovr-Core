<?php
/**
 * Search Handler.
 *
 * @package OVR\Search
 * @since   1.0.0
 */

namespace OVR\Search;

use OVR\Core\TemplateLoader;
use OVR\Core\Pages;
use OVR\Property\PropertyQuery;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SearchHandler {

    /**
     * Bump to force a one-time rewrite flush after adding/changing rules.
     */
    private const REWRITE_VERSION = '2';

    public function init(): void {
        add_action( 'init', [ self::class, 'register_search_pagination_rewrite' ] );
    }

    /**
     * Pretty pagination (/search/page/2/) is otherwise swallowed by the core
     * `search/(.+?)/?$` rule, which resolves it as a keyword search for
     * "page/2". Register an explicit rule (built from the real search-page
     * slug) at the top of the rules list so paging reaches our page.
     */
    public static function register_search_pagination_rewrite(): void {
        $page_id = absint( get_option( 'ovr_page_search' ) );
        if ( ! $page_id ) {
            return;
        }
        $slug = get_post_field( 'post_name', $page_id );
        if ( ! $slug ) {
            return;
        }

        $escaped = preg_quote( $slug, '#' );
        add_rewrite_rule(
            '^' . $escaped . '/page/([0-9]{1,})/?$',
            'index.php?pagename=' . rawurlencode( $slug ) . '&paged=$matches[1]',
            'top'
        );

        // One-time flush so the new rule is persisted (mirrors Pages.php).
        if ( get_option( 'ovr_rewrite_version' ) !== self::REWRITE_VERSION ) {
            flush_rewrite_rules();
            update_option( 'ovr_rewrite_version', self::REWRITE_VERSION );
        }
    }

    /**
     * Render search results page.
     */
    public static function render(): string {
        $filters = self::get_filters_from_request();
        $query   = PropertyQuery::query( $filters );

        $debug = '';
        if ( ! empty( $_GET['ovr_debug'] ) ) {
            $debug = self::render_debug_panel( $filters, $query );
        }

        $html = self::render_region( $filters, $query, sanitize_key( $_GET['view'] ?? 'grid' ) );

        return $debug . $html;
    }

    /**
     * Render just the results region (works for both the full page and the
     * village-chip AJAX refresh, where only this block is swapped in).
     *
     * @param array     $filters Sanitized search filters.
     * @param \WP_Query $query   The already-run results query.
     * @param string    $view    Active results view ('grid' | 'list' | 'map').
     */
    public static function render_region( array $filters, \WP_Query $query, string $view = 'grid' ): string {
        return TemplateLoader::get_rendered( 'search/results.php', [
            'query'     => $query,
            'filters'   => $filters,
            'total'     => $query->found_posts,
            'max_pages' => $query->max_num_pages,
            'paged'     => $filters['paged'],
            'view'      => $view,
        ] );
    }

    /**
     * Build the canonical search URL for a filter set (used by the chip AJAX
     * handler so the address bar always matches the rendered results).
     *
     * @param array  $filters Sanitized search filters.
     * @param string $view    Active results view (grid/list/map), '' to omit.
     */
    public static function filter_url( array $filters, string $view = '' ): string {
        $clean  = array_filter( $filters, static fn( $v ) => $v !== '' && $v !== 0 && $v !== [] && $v !== false );
        $params = http_build_query( $clean );
        if ( in_array( $view, [ 'grid', 'list', 'map' ], true ) ) {
            $params .= ( '' !== $params ? '&' : '' ) . 'view=' . rawurlencode( $view );
        }
        return Pages::get_page_url( 'ovr_page_search' ) . '?' . $params;
    }

    /**
     * Render a diagnostic panel showing exactly what the search received,
     * what was resolved, and what WP_Query produced.
     * Activated by appending `?ovr_debug=1` to the URL.
     */
    private static function render_debug_panel( array $filters, \WP_Query $query ): string {
        global $wpdb;

        $keyword = (string) ( $filters['keyword'] ?? '' );

        // Try direct village resolution.
        $matched_ids = [];
        $village_hits = [];
        $title_hits   = [];
        if ( '' !== $keyword ) {
            $matched_ids = PropertyQuery::resolve_keyword_to_post_ids( $keyword );

            $like = '%' . $wpdb->esc_like( $keyword ) . '%';

            $village_hits = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.count
                 FROM {$wpdb->terms} t
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
                 WHERE tt.taxonomy IN ('ovr_village','ovr_property_type','ovr_amenity','ovr_rental_type')
                   AND t.name LIKE %s",
                $like
            ), ARRAY_A );

            $title_hits = $wpdb->get_results( $wpdb->prepare(
                "SELECT ID, post_title, post_status FROM {$wpdb->posts}
                 WHERE post_type = 'ovr_property'
                   AND ( post_title LIKE %s OR post_content LIKE %s )",
                $like, $like
            ), ARRAY_A );
        }

        // Sample of all ovr_property posts so we can compare.
        $all_props = $wpdb->get_results(
            "SELECT ID, post_title, post_status FROM {$wpdb->posts}
             WHERE post_type = 'ovr_property'
             ORDER BY ID DESC
             LIMIT 20",
            ARRAY_A
        );

        // For each property, list its village terms.
        $prop_villages = [];
        foreach ( $all_props as $row ) {
            $terms = wp_get_post_terms( (int) $row['ID'], 'ovr_village', [ 'fields' => 'names' ] );
            $prop_villages[ $row['ID'] ] = is_wp_error( $terms ) ? '(error)' : ( $terms ? implode( ', ', $terms ) : '— none —' );
        }

        // WP_Query result.
        $found        = $query->found_posts;
        $sql          = $query->request;
        $wp_query_ids = wp_list_pluck( $query->posts ?? [], 'ID' );

        ob_start();
        ?>
        <div style="background:#fff8dc;border:2px solid #b8860b;padding:16px;margin:16px;font:13px/1.5 monospace;border-radius:8px">
            <h3 style="margin:0 0 8px 0;color:#b8860b">🔍 OVR Search Diagnostic</h3>
            <p><strong>Received keyword:</strong> <code><?php echo esc_html( $keyword ?: '(empty)' ); ?></code></p>
            <p><strong>All $_GET parameters:</strong> <code><?php echo esc_html( wp_json_encode( $_GET ) ); ?></code></p>
            <p><strong>resolve_keyword_to_post_ids() returned:</strong> <code><?php echo esc_html( wp_json_encode( $matched_ids ) ); ?></code> (<?php echo count( $matched_ids ); ?> ids)</p>

            <h4 style="margin:12px 0 4px 0">Taxonomy term-name matches for "<?php echo esc_html( $keyword ); ?>":</h4>
            <?php if ( $village_hits ) : ?>
                <ul style="margin:0 0 8px 16px">
                    <?php foreach ( $village_hits as $v ) : ?>
                        <li>term_id=<?php echo (int) $v['term_id']; ?> name=<strong><?php echo esc_html( $v['name'] ); ?></strong> slug=<?php echo esc_html( $v['slug'] ); ?> taxonomy=<?php echo esc_html( $v['taxonomy'] ); ?> count=<?php echo (int) $v['count']; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p style="color:#c00">⚠ NO taxonomy terms match "<?php echo esc_html( $keyword ); ?>". Either the term is named differently, or it's in a different taxonomy.</p>
            <?php endif; ?>

            <h4 style="margin:12px 0 4px 0">Title/content matches for "<?php echo esc_html( $keyword ); ?>":</h4>
            <?php if ( $title_hits ) : ?>
                <ul style="margin:0 0 8px 16px">
                    <?php foreach ( $title_hits as $h ) : ?>
                        <li>ID=<?php echo (int) $h['ID']; ?> title="<?php echo esc_html( $h['post_title'] ); ?>" status=<?php echo esc_html( $h['post_status'] ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p>(none)</p>
            <?php endif; ?>

            <h4 style="margin:12px 0 4px 0">All ovr_property posts (latest 20):</h4>
            <table style="border-collapse:collapse;width:100%">
                <thead><tr style="background:#eee"><th style="text-align:left;padding:4px;border:1px solid #ccc">ID</th><th style="text-align:left;padding:4px;border:1px solid #ccc">Title</th><th style="text-align:left;padding:4px;border:1px solid #ccc">Status</th><th style="text-align:left;padding:4px;border:1px solid #ccc">Villages assigned</th></tr></thead>
                <tbody>
                <?php foreach ( $all_props as $row ) : ?>
                    <tr>
                        <td style="padding:4px;border:1px solid #ccc"><?php echo (int) $row['ID']; ?></td>
                        <td style="padding:4px;border:1px solid #ccc"><?php echo esc_html( $row['post_title'] ); ?></td>
                        <td style="padding:4px;border:1px solid #ccc"><?php echo esc_html( $row['post_status'] ); ?></td>
                        <td style="padding:4px;border:1px solid #ccc"><?php echo esc_html( $prop_villages[ $row['ID'] ] ?? '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h4 style="margin:12px 0 4px 0">WP_Query result:</h4>
            <p><strong>found_posts:</strong> <?php echo (int) $found; ?></p>
            <p><strong>returned IDs:</strong> <code><?php echo esc_html( wp_json_encode( $wp_query_ids ) ); ?></code></p>
            <p><strong>SQL:</strong></p>
            <pre style="background:#fff;padding:8px;border:1px solid #ddd;white-space:pre-wrap;word-break:break-all"><?php echo esc_html( $sql ); ?></pre>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Extract and sanitize filter parameters from the request.
     *
     * IMPORTANT: We `array_filter` after `array_map` for the taxonomy
     * filters. Without it, a form input like `property_type=""` becomes
     * `[""]` — and `! empty( [""] )` is true, so PropertyQuery would add
     * a tax_query for the empty slug "", which WP_Tax_Query short-circuits
     * to `( 0 = 1 )` — silently killing every search result. Stripping
     * empty values here means an unset/blank dropdown contributes nothing.
     */
    public static function get_filters_from_request(): array {
        $raw = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        // Pretty-permalink paging (/page/2/) arrives via query_var, not $_GET.
        if ( empty( $raw['paged'] ) ) {
            $raw['paged'] = get_query_var( 'paged', 1 );
        }
        // Robustness: some contexts (e.g. core paginate_links or third-party
        // code) emit `?page=N` instead of `?paged=N`. Read it as a fallback so
        // pagination never silently collapses to page 1.
        if ( empty( $raw['paged'] ) && ! empty( $raw['page'] ) ) {
            $raw['paged'] = $raw['page'];
        }
        return self::sanitize_filters( $raw );
    }

    /**
     * Sanitize a raw (unsanitized) filter array into the canonical shape.
     * The front-end posts the chip's target query string for the AJAX
     * refresh; `wp_parse_str` rehydrates it into an array and the same
     * laundering logic applies, so the server-side result set matches what a
     * GET request would produce.
     *
     * @param array $raw Raw values (typically $_GET or a parsed query string).
     */
    public static function sanitize_filters( array $raw ): array {
        $clean_slugs = static function ( $raw_slugs ): array {
            return array_values( array_filter( array_map( 'sanitize_key', (array) $raw_slugs ), 'strlen' ) );
        };

        return [
            'keyword'         => sanitize_text_field( wp_unslash( (string) ( $raw['keyword'] ?? '' ) ) ),
            // Village is the free-text Village Name (matched against meta).
            'village'         => isset( $raw['village'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) wp_unslash( $raw['village'] ) ), 'strlen' ) ) : [],
            // Free-text Village Name search (phase 21 sidebar input).
            'village_name'    => sanitize_text_field( wp_unslash( (string) ( $raw['village_name'] ?? '' ) ) ),
            // Village Section is the ovr_village taxonomy slug (curated facet).
            'village_section' => $clean_slugs( $raw['village_section'] ?? [] ),
            'property_type'   => $clean_slugs( $raw['property_type'] ?? [] ),
            // Rental term — the ovr_rental_type taxonomy (Long-Term / Short-Term).
            'rental_type'     => $clean_slugs( $raw['rental_type'] ?? [] ),
            'amenities'       => $clean_slugs( $raw['amenities'] ?? [] ),
            'views'           => $clean_slugs( $raw['views'] ?? [] ),
            'features'        => $clean_slugs( $raw['features'] ?? [] ),
            'bedrooms'        => absint( $raw['bedrooms'] ?? 0 ),
            'bathrooms'       => floatval( $raw['bathrooms'] ?? 0 ),
            // Free-text street-address search (matched against the _ovr_address meta).
            'address'         => sanitize_text_field( wp_unslash( (string) ( $raw['address'] ?? '' ) ) ),
            'price_min'       => floatval( $raw['price_min'] ?? 0 ),
            'price_max'       => floatval( $raw['price_max'] ?? 0 ),
            'guests'          => absint( $raw['guests'] ?? 0 ),
            'pets'            => ! empty( $raw['pets'] ),
            // Availability date range (Feature 2/8): excludes listings hard-
            // blocked over the stay. Only ISO YYYY-MM-DD values are honoured.
            'checkin'         => self::clean_date( $raw['checkin'] ?? '' ),
            'checkout'        => self::clean_date( $raw['checkout'] ?? '' ),
            // Owner filter (Phase 22): show only one landlord's listings.
            'owner_id'        => absint( $raw['owner_id'] ?? 0 ),
            'sort'            => sanitize_key( (string) ( $raw['sort'] ?? 'newest' ) ),
            'per_page'        => absint( $raw['per_page'] ?? 12 ),
            'paged'           => absint( $raw['paged'] ?? 1 ),
            // Deals & Cancellations view: constrains results to properties with
            // an active (paid, unexpired) deals promotion.
            'deals_only'      => ! empty( $raw['deals_only'] ) || 'deals' === ( $raw['view'] ?? '' ),
            // Featured view: constrains results to properties with an active
            // (paid, unexpired) Featured boost — reuses the standard search
            // results format instead of a separate template.
            'featured_only'   => ! empty( $raw['featured_only'] ),
        ];
    }

    /**
     * Accept only a strict YYYY-MM-DD date; anything else becomes ''.
     */
    private static function clean_date( $raw ): string {
        $value = sanitize_text_field( wp_unslash( (string) $raw ) );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
    }
}
