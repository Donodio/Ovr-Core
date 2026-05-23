<?php
/**
 * Property Custom Post Type.
 *
 * @package OVR\PostTypes
 * @since   1.0.0
 */

namespace OVR\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PropertyPostType {

    /** @var string */
    public const POST_TYPE = 'ovr_property';

    public function init(): void {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    /**
     * Register the ovr_property CPT.
     */
    public function register_post_type(): void {
        $labels = [
            'name'               => _x( 'Properties', 'post type general name', 'ovr-core' ),
            'singular_name'      => _x( 'Property', 'post type singular name', 'ovr-core' ),
            'menu_name'          => _x( 'OVR Properties', 'admin menu', 'ovr-core' ),
            'add_new'            => _x( 'Add New', 'property', 'ovr-core' ),
            'add_new_item'       => __( 'Add New Property', 'ovr-core' ),
            'edit_item'          => __( 'Edit Property', 'ovr-core' ),
            'new_item'           => __( 'New Property', 'ovr-core' ),
            'view_item'          => __( 'View Property', 'ovr-core' ),
            'search_items'       => __( 'Search Properties', 'ovr-core' ),
            'not_found'          => __( 'No properties found.', 'ovr-core' ),
            'not_found_in_trash' => __( 'No properties found in Trash.', 'ovr-core' ),
            'all_items'          => __( 'All Properties', 'ovr-core' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'property', 'with_front' => false ],
            'capability_type'    => [ 'ovr_property', 'ovr_properties' ],
            'map_meta_cap'       => true,
            'has_archive'        => 'properties',
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-building',
            'supports'           => [
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields',
                'revisions',
            ],
        ];

        register_post_type( self::POST_TYPE, $args );
    }
}
