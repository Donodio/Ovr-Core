<?php
/**
 * Testimonial Custom Post Type.
 *
 * A centrally-managed store of testimonials/quotes that the OVR Testimonials
 * Carousel widget can pull from. Each testimonial carries an author name
 * (post title), an avatar (featured image), and meta for the quote, a star
 * rating, a role/location line, and an optional linked property.
 *
 * Testimonials can be entered manually here, or auto-promoted from approved
 * property reviews (see TestimonialRepository / Reviews).
 *
 * @package OVR\Testimonials
 * @since   1.1.0
 */

namespace OVR\Testimonials;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TestimonialPostType {

    /** @var string */
    public const POST_TYPE = 'ovr_testimonial';

    /** Meta keys (all prefixed _ovr_t_). */
    public const META_QUOTE     = '_ovr_t_quote';
    public const META_RATING    = '_ovr_t_rating';
    public const META_ROLE      = '_ovr_t_role';
    public const META_PROPERTY  = '_ovr_t_property';
    public const META_SOURCE    = '_ovr_t_source';     // 'manual' | 'review'
    public const META_REVIEW_ID = '_ovr_t_review_id';  // source review row id (dedupe)

    public function init(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    /**
     * Register the ovr_testimonial CPT.
     *
     * Not publicly queryable as its own front-end page — testimonials surface
     * only through the carousel widget — but fully manageable in wp-admin.
     */
    public function register_post_type(): void {
        $labels = [
            'name'               => _x( 'Testimonials', 'post type general name', 'ovr-core' ),
            'singular_name'      => _x( 'Testimonial', 'post type singular name', 'ovr-core' ),
            'menu_name'          => _x( 'Testimonials', 'admin menu', 'ovr-core' ),
            'add_new'            => _x( 'Add New', 'testimonial', 'ovr-core' ),
            'add_new_item'       => __( 'Add New Testimonial', 'ovr-core' ),
            'edit_item'          => __( 'Edit Testimonial', 'ovr-core' ),
            'new_item'           => __( 'New Testimonial', 'ovr-core' ),
            'view_item'          => __( 'View Testimonial', 'ovr-core' ),
            'search_items'       => __( 'Search Testimonials', 'ovr-core' ),
            'not_found'          => __( 'No testimonials found.', 'ovr-core' ),
            'not_found_in_trash' => __( 'No testimonials found in Trash.', 'ovr-core' ),
            'all_items'          => __( 'All Testimonials', 'ovr-core' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-format-quote',
            'supports'           => [ 'title', 'thumbnail' ],
        ];

        register_post_type( self::POST_TYPE, $args );
    }
}
