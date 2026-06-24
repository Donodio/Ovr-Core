<?php
/**
 * Search Handler.
 *
 * @package OVR\Search
 * @since   1.0.0
 */

namespace OVR\Search;

use OVR\Core\TemplateLoader;
use OVR\Property\PropertyQuery;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SearchHandler {

    public function init(): void {}

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

        $html = TemplateLoader::get_rendered( 'search/results.php', [
            'query'    => $query,
            'filters'  => $filters,
            'total'    => $query->found_posts,
            'max_pages'=> $query->max_num_pages,
            'paged'    => $filters['paged'],
            'view'     => sanitize_key( $_GET['view'] ?? 'grid' ),
        ] );

        return $debug . $html;
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
        $clean_slugs = static function ( $raw ): array {
            return array_values( array_filter( array_map( 'sanitize_key', (array) $raw ), 'strlen' ) );
        };

        return [
            'keyword'       => sanitize_text_field( wp_unslash( $_GET['keyword'] ?? '' ) ),
            // Village is the free-text Village Name (matched against meta).
            'village'       => isset( $_GET['village'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['village'] ) ), 'strlen' ) ) : [],
            // Village Section is the ovr_village taxonomy slug (curated facet).
            'village_section' => isset( $_GET['village_section'] ) ? $clean_slugs( $_GET['village_section'] ) : [],
            'property_type' => isset( $_GET['property_type'] ) ? $clean_slugs( $_GET['property_type'] ) : [],
            // Rental term — the ovr_rental_type taxonomy (Long-Term / Short-Term).
            'rental_type'   => isset( $_GET['rental_type'] ) ? $clean_slugs( $_GET['rental_type'] ) : [],
            'amenities'     => isset( $_GET['amenities'] )     ? $clean_slugs( $_GET['amenities'] )     : [],
            'views'         => isset( $_GET['views'] )         ? $clean_slugs( $_GET['views'] )         : [],
            'features'      => isset( $_GET['features'] )      ? $clean_slugs( $_GET['features'] )      : [],
            'bedrooms'      => absint( $_GET['bedrooms'] ?? 0 ),
            'bathrooms'     => floatval( $_GET['bathrooms'] ?? 0 ),
            // Free-text street-address search (matched against the _ovr_address meta).
            'address'       => sanitize_text_field( wp_unslash( $_GET['address'] ?? '' ) ),
            'price_min'     => floatval( $_GET['price_min'] ?? 0 ),
            'price_max'     => floatval( $_GET['price_max'] ?? 0 ),
            'guests'        => absint( $_GET['guests'] ?? 0 ),
            'pets'          => ! empty( $_GET['pets'] ),
            // Availability date range (Feature 2/8): excludes listings hard-
            // blocked over the stay. Only ISO YYYY-MM-DD values are honoured.
            'checkin'       => self::clean_date( $_GET['checkin'] ?? '' ),
            'checkout'      => self::clean_date( $_GET['checkout'] ?? '' ),
            // Owner filter (Phase 22): show only one landlord's listings.
            'owner_id'      => absint( $_GET['owner_id'] ?? 0 ),
            'sort'          => sanitize_key( $_GET['sort'] ?? 'newest' ),
            'per_page'      => absint( $_GET['per_page'] ?? 12 ),
            'paged'         => absint( $_GET['paged'] ?? get_query_var( 'paged', 1 ) ),
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
