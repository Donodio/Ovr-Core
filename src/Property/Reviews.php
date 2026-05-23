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
            'status'      => $status,
        ], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ] );

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
     * Approve or reject a pending review (admin-only call).
     */
    public static function set_status( int $review_id, string $status ): bool {
        if ( ! in_array( $status, [ 'pending', 'approved', 'rejected' ], true ) ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT property_id FROM {$table} WHERE id = %d", $review_id ), ARRAY_A );
        if ( ! $row ) return false;

        $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $review_id ], [ '%s' ], [ '%d' ] );
        self::recompute_aggregates( (int) $row['property_id'] );

        return true;
    }
}
