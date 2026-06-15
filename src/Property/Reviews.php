<?php
/**
 * Property Reviews.
 *
 * Submission, listing, and aggregation. After every approved insert,
 * recomputes the property's _ovr_rating_avg and _ovr_rating_count meta
 * keys so the search and card displays stay accurate.
 *
 * @package OVR\Property
 * @since   1.0.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Reviews {

    public function init(): void {
        // Auto-recompute aggregates whenever a row is inserted/updated/deleted
        // via the public API. (Direct $wpdb writes also work because admins
        // can manually call self::recompute_aggregates.)
    }

    /**
     * Submit a review. If review_approval is true (default), the row lands
     * in 'pending' status and an admin must approve it before it counts.
     *
     * @return int|\WP_Error Review ID, or WP_Error.
     */
    public static function submit( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $property_id = absint( $data['property_id'] ?? 0 );
        $rating      = max( 1, min( 5, (int) ( $data['rating'] ?? 0 ) ) );
        $title       = sanitize_text_field( wp_unslash( $data['title']       ?? '' ) );
        $body        = sanitize_textarea_field( wp_unslash( $data['body']    ?? '' ) );
        $name        = sanitize_text_field( wp_unslash( $data['guest_name']  ?? '' ) );
        $email       = sanitize_email(     wp_unslash( $data['guest_email'] ?? '' ) );
        $user_id     = (int) ( $data['user_id'] ?? get_current_user_id() );
        $stay_raw    = sanitize_text_field( wp_unslash( $data['stay_date'] ?? '' ) );
        $stay_date   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $stay_raw ) ? $stay_raw : null;

        if ( ! $property_id || ! $body || ! $rating ) {
            return new \WP_Error( 'invalid_review', __( 'Property, rating, and review text are required.', 'ovr-core' ) );
        }
        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', __( 'A valid email address is required.', 'ovr-core' ) );
        }

        $post = get_post( $property_id );
        if ( ! $post || 'ovr_property' !== $post->post_type ) {
            return new \WP_Error( 'invalid_property', __( 'Property not found.', 'ovr-core' ) );
        }

        $settings = get_option( 'ovr_settings', [] );
        $auto_approve = empty( $settings['review_approval'] );
        $status = $auto_approve ? 'approved' : 'pending';

        $result = $wpdb->insert( $table, [
            'property_id' => $property_id,
            'user_id'     => $user_id ?: null,
            'guest_name'  => $name ?: __( 'Anonymous', 'ovr-core' ),
            'guest_email' => $email,
            'rating'      => $rating,
            'title'       => substr( $title, 0, 255 ),
            'body'        => $body,
            'stay_date'   => $stay_date,
            'status'      => $status,
        ], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ] );

        if ( false === $result ) {
            return new \WP_Error( 'db_error', __( 'Failed to save review.', 'ovr-core' ) );
        }

        $review_id = (int) $wpdb->insert_id;

        if ( 'approved' === $status ) {
            self::recompute_aggregates( $property_id );
        }

        do_action( 'ovr_review_submitted', $review_id, $property_id, $status );

        return $review_id;
    }

    /**
     * Recompute and persist the rating_avg + rating_count meta for a property.
     */
    public static function recompute_aggregates( int $property_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) AS cnt, AVG(rating) AS avg_r
                 FROM {$table}
                 WHERE property_id = %d AND status = 'approved'",
                $property_id
            ),
            ARRAY_A
        );

        $count = (int) ( $row['cnt'] ?? 0 );
        $avg   = $count ? round( (float) ( $row['avg_r'] ?? 0 ), 2 ) : 0;

        update_post_meta( $property_id, '_ovr_rating_count', $count );
        update_post_meta( $property_id, '_ovr_rating_avg',   $avg );
    }

    /**
     * Approved reviews for a property.
     *
     * @return array<int, array>
     */
    public static function get_for_property( int $property_id, int $limit = 20 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE property_id = %d AND status = 'approved'
                 ORDER BY created_at DESC
                 LIMIT %d",
                $property_id,
                $limit
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Site-wide approved reviews, gated by a minimum star rating. Used to feed
     * the Testimonials Carousel widget with real guest reviews (reputation
     * management — only high-rated reviews surface publicly).
     *
     * @param int $min_rating Minimum star rating to include (1–5).
     * @param int $limit       Max rows.
     * @return array<int, array>
     */
    public static function get_top_reviews( int $min_rating = 4, int $limit = 12 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $min_rating = max( 1, min( 5, $min_rating ) );
        $limit      = max( 1, $limit );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status = 'approved' AND rating >= %d
                 ORDER BY rating DESC, created_at DESC
                 LIMIT %d",
                $min_rating,
                $limit
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Approve or reject a pending review (admin-only call).
     */
    public static function set_status( int $review_id, string $status ): bool {
        if ( ! in_array( $status, [ 'pending', 'approved', 'rejected' ], true ) ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT property_id, status FROM {$table} WHERE id = %d", $review_id ), ARRAY_A );
        if ( ! $row ) return false;

        $data = [ 'status' => $status ];
        $fmt  = [ '%s' ];
        if ( 'approved' === $status ) {
            $data['approved_at'] = current_time( 'mysql' );
            $fmt[]               = '%s';
        }
        $wpdb->update( $table, $data, [ 'id' => $review_id ], $fmt, [ '%d' ] );
        self::recompute_aggregates( (int) $row['property_id'] );

        // Audit (Milestone 3 F2) + email trigger (F6): record/notify on change.
        if ( (string) $row['status'] !== $status ) {
            if ( class_exists( '\OVR\Core\AuditLog' ) ) {
                \OVR\Core\AuditLog::record(
                    'review.' . $status,
                    'review',
                    $review_id,
                    [ 'property_id' => (int) $row['property_id'] ],
                    null,
                    [ 'old' => (string) $row['status'], 'new' => $status ]
                );
            }
            do_action( 'ovr_review_status_changed', $review_id, $status, (string) $row['status'], (int) $row['property_id'] );
        }

        return true;
    }

    /**
     * Bulk approve/reject. Returns the number of rows whose status changed.
     *
     * @param int[] $review_ids
     */
    public static function bulk_set_status( array $review_ids, string $status ): int {
        $changed = 0;
        foreach ( $review_ids as $id ) {
            if ( self::set_status( (int) $id, $status ) ) {
                $changed++;
            }
        }
        return $changed;
    }

    /**
     * Edit a review's rating, title, and body in place (admin moderation).
     * Recomputes the property's aggregates in case the rating moved.
     */
    public static function update_content( int $review_id, int $rating, string $body, string $title = '' ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $rating = max( 1, min( 5, $rating ) );
        $body   = sanitize_textarea_field( wp_unslash( $body ) );
        $title  = sanitize_text_field( wp_unslash( $title ) );

        if ( '' === $body ) {
            return false;
        }

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT property_id FROM {$table} WHERE id = %d", $review_id ), ARRAY_A );
        if ( ! $row ) return false;

        $wpdb->update(
            $table,
            [ 'rating' => $rating, 'body' => $body, 'title' => substr( $title, 0, 255 ) ],
            [ 'id' => $review_id ],
            [ '%d', '%s', '%s' ],
            [ '%d' ]
        );
        self::recompute_aggregates( (int) $row['property_id'] );

        return true;
    }

    /**
     * Permanently delete a review and refresh the property's aggregates.
     */
    public static function delete( int $review_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT property_id FROM {$table} WHERE id = %d", $review_id ), ARRAY_A );
        if ( ! $row ) return false;

        $wpdb->delete( $table, [ 'id' => $review_id ], [ '%d' ] );
        self::recompute_aggregates( (int) $row['property_id'] );

        return true;
    }

    /**
     * A single review row (admin), or null.
     *
     * @return array<string, mixed>|null
     */
    public static function get( int $review_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $review_id ), ARRAY_A );
        return $row ?: null;
    }

    /**
     * Review counts grouped by status, plus an 'all' total. Keys always present.
     *
     * @return array{all:int, pending:int, approved:int, rejected:int}
     */
    public static function count_by_status(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $counts = [ 'all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0 ];
        $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status", ARRAY_A );
        foreach ( (array) $rows as $r ) {
            $st = (string) $r['status'];
            if ( isset( $counts[ $st ] ) ) {
                $counts[ $st ] = (int) $r['cnt'];
            }
            $counts['all'] += (int) $r['cnt'];
        }
        return $counts;
    }

    /**
     * Moderation analytics (Milestone 3 F3): average approved rating + the
     * properties with the most approved reviews.
     *
     * @return array{avg_rating:float, total_approved:int, per_property:array<int,array{property_id:int,title:string,count:int,avg:float}>}
     */
    public static function analytics( int $top = 5 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $avg   = (float) $wpdb->get_var( "SELECT AVG(rating) FROM {$table} WHERE status = 'approved'" );
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'approved'" );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT property_id, COUNT(*) AS cnt, AVG(rating) AS av
             FROM {$table} WHERE status = 'approved'
             GROUP BY property_id ORDER BY cnt DESC LIMIT %d",
            $top
        ), ARRAY_A );

        $per = [];
        foreach ( (array) $rows as $r ) {
            $per[] = [
                'property_id' => (int) $r['property_id'],
                'title'       => get_the_title( (int) $r['property_id'] ) ?: ( '#' . (int) $r['property_id'] ),
                'count'       => (int) $r['cnt'],
                'avg'         => round( (float) $r['av'], 1 ),
            ];
        }

        return [
            'avg_rating'     => round( $avg, 2 ),
            'total_approved' => $total,
            'per_property'   => $per,
        ];
    }

    /**
     * Paginated review list for the moderation screen, joined to each
     * property's title. Pass status 'all' for no status filter.
     *
     * @return array{rows:array<int,array>, total:int, pages:int}
     */
    public static function get_admin_list( string $status = 'all', int $paged = 1, int $per_page = 20 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $paged    = max( 1, $paged );
        $per_page = max( 1, $per_page );
        $offset   = ( $paged - 1 ) * $per_page;

        $where  = '';
        $params = [];
        if ( in_array( $status, [ 'pending', 'approved', 'rejected' ], true ) ) {
            $where    = 'WHERE r.status = %s';
            $params[] = $status;
        }

        $total = (int) $wpdb->get_var(
            $params
                ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} r {$where}", $params )
                : "SELECT COUNT(*) FROM {$table} r"
        );

        $query  = "SELECT r.*, p.post_title AS property_title
                   FROM {$table} r
                   LEFT JOIN {$wpdb->posts} p ON p.ID = r.property_id
                   {$where}
                   ORDER BY r.created_at DESC
                   LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results(
            $wpdb->prepare( $query, array_merge( $params, [ $per_page, $offset ] ) ),
            ARRAY_A
        );

        return [
            'rows'  => $rows ?: [],
            'total' => $total,
            'pages' => (int) ceil( $total / $per_page ),
        ];
    }
}
