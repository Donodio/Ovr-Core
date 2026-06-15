<?php
/**
 * Testimonial Repository.
 *
 * Fetches and normalizes testimonials from two sources:
 *
 *   - "testimonial"  Manually-entered ovr_testimonial posts.
 *   - "review"       Approved property reviews (wp_ovr_reviews).
 *
 * Both are gated by a minimum star rating (reputation management): only
 * testimonials/reviews at or above the configured threshold ever surface.
 * Every item is returned in one normalized shape so the carousel widget can
 * render them uniformly.
 *
 * Normalized item shape:
 *   [
 *     'name'           => string,
 *     'role'           => string,   // role / location / "Verified Guest · Property"
 *     'quote'          => string,
 *     'rating'         => int (1–5),
 *     'avatar'         => string,   // image URL ('' → initial fallback)
 *     'property_id'    => int,      // 0 when none
 *     'property_title' => string,
 *     'property_url'   => string,
 *     'date'           => string,   // Y-m-d H:i:s
 *     'source'         => 'testimonial' | 'review',
 *   ]
 *
 * @package OVR\Testimonials
 * @since   1.1.0
 */

namespace OVR\Testimonials;

use OVR\Property\Reviews;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TestimonialRepository {

    /** Default minimum rating when no setting is stored. */
    public const DEFAULT_MIN_RATING = 4;

    /**
     * Configured minimum public rating (reputation gate). Falls back to 4.
     */
    public static function min_rating(): int {
        $settings = (array) get_option( 'ovr_settings', [] );
        $min      = isset( $settings['min_display_rating'] ) ? (int) $settings['min_display_rating'] : self::DEFAULT_MIN_RATING;
        return max( 1, min( 5, $min ) );
    }

    /**
     * Get normalized testimonials.
     *
     * @param string $source     'testimonial', 'review', or 'both'.
     * @param int    $limit       Max items to return.
     * @param int    $min_rating  Override min rating (0 = use global setting).
     * @return array<int, array>
     */
    public static function get( string $source = 'both', int $limit = 12, int $min_rating = 0 ): array {
        $limit = max( 1, $limit );
        $min   = $min_rating > 0 ? max( 1, min( 5, $min_rating ) ) : self::min_rating();

        $items = [];

        if ( 'review' !== $source ) {
            $items = array_merge( $items, self::get_manual( $min, $limit ) );
        }

        if ( 'testimonial' !== $source ) {
            $items = array_merge( $items, self::get_reviews( $min, $limit ) );
        }

        // For "both", interleave by rating then recency so the strongest
        // social proof leads, then trim to the limit.
        if ( 'both' === $source ) {
            usort( $items, static function ( $a, $b ) {
                if ( $a['rating'] === $b['rating'] ) {
                    return strcmp( (string) $b['date'], (string) $a['date'] );
                }
                return $b['rating'] <=> $a['rating'];
            } );
        }

        return array_slice( $items, 0, $limit );
    }

    /**
     * Manually-entered testimonial posts, gated by rating.
     *
     * @return array<int, array>
     */
    public static function get_manual( int $min_rating, int $limit ): array {
        $q = new \WP_Query( [
            'post_type'              => TestimonialPostType::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'orderby'                => [ 'menu_order' => 'ASC', 'date' => 'DESC' ],
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'meta_query'             => [
                [
                    'key'     => TestimonialPostType::META_RATING,
                    'value'   => $min_rating,
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ] );

        $items = [];
        foreach ( $q->posts as $post ) {
            $pid          = (int) $post->ID;
            $property_id  = (int) get_post_meta( $pid, TestimonialPostType::META_PROPERTY, true );
            $rating       = (int) get_post_meta( $pid, TestimonialPostType::META_RATING, true );
            $items[]      = [
                'name'           => get_the_title( $pid ),
                'role'           => (string) get_post_meta( $pid, TestimonialPostType::META_ROLE, true ),
                'quote'          => (string) get_post_meta( $pid, TestimonialPostType::META_QUOTE, true ),
                'rating'         => max( 1, min( 5, $rating ?: 5 ) ),
                'avatar'         => (string) get_the_post_thumbnail_url( $pid, 'thumbnail' ),
                'property_id'    => $property_id,
                'property_title' => $property_id ? get_the_title( $property_id ) : '',
                'property_url'   => $property_id ? (string) get_permalink( $property_id ) : '',
                'date'           => (string) get_post_field( 'post_date', $pid ),
                'source'         => 'testimonial',
            ];
        }
        return $items;
    }

    /**
     * Approved property reviews (≥ min rating), normalized to testimonials.
     *
     * @return array<int, array>
     */
    public static function get_reviews( int $min_rating, int $limit ): array {
        $rows  = Reviews::get_top_reviews( $min_rating, $limit );
        $items = [];

        foreach ( $rows as $row ) {
            $property_id    = (int) ( $row['property_id'] ?? 0 );
            $property_title = $property_id ? get_the_title( $property_id ) : '';
            $role           = $property_title
                ? sprintf( /* translators: %s: property name */ __( 'Verified Guest · %s', 'ovr-core' ), $property_title )
                : __( 'Verified Guest', 'ovr-core' );

            $items[] = [
                'name'           => (string) ( $row['guest_name'] ?? __( 'Guest', 'ovr-core' ) ),
                'role'           => $role,
                'quote'          => (string) ( $row['body'] ?? '' ),
                'rating'         => max( 1, min( 5, (int) ( $row['rating'] ?? 5 ) ) ),
                'avatar'         => '',
                'property_id'    => $property_id,
                'property_title' => $property_title,
                'property_url'   => $property_id ? (string) get_permalink( $property_id ) : '',
                'date'           => (string) ( $row['created_at'] ?? '' ),
                'source'         => 'review',
            ];
        }
        return $items;
    }
}
