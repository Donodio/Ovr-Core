<?php
/**
 * Custom Taxonomies for Properties.
 *
 * @package OVR\PostTypes
 * @since   1.0.0
 */

namespace OVR\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Taxonomies {

    public function init(): void {
        add_action( 'init', [ $this, 'register_taxonomies' ] );
    }

    public function register_taxonomies(): void {
        $this->register_village();
        $this->register_property_type();
        $this->register_amenity();
        $this->register_rental_type();
    }

    private function register_village(): void {
        register_taxonomy( 'ovr_village', PropertyPostType::POST_TYPE, [
            'labels' => [
                'name'          => _x( 'Villages', 'taxonomy general name', 'ovr-core' ),
                'singular_name' => _x( 'Village', 'taxonomy singular name', 'ovr-core' ),
                'search_items'  => __( 'Search Villages', 'ovr-core' ),
                'all_items'     => __( 'All Villages', 'ovr-core' ),
                'edit_item'     => __( 'Edit Village', 'ovr-core' ),
                'add_new_item'  => __( 'Add New Village', 'ovr-core' ),
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'village', 'with_front' => false ],
        ] );
    }

    private function register_property_type(): void {
        register_taxonomy( 'ovr_property_type', PropertyPostType::POST_TYPE, [
            'labels' => [
                'name'          => _x( 'Property Types', 'taxonomy general name', 'ovr-core' ),
                'singular_name' => _x( 'Property Type', 'taxonomy singular name', 'ovr-core' ),
                'search_items'  => __( 'Search Property Types', 'ovr-core' ),
                'all_items'     => __( 'All Property Types', 'ovr-core' ),
                'edit_item'     => __( 'Edit Property Type', 'ovr-core' ),
                'add_new_item'  => __( 'Add New Property Type', 'ovr-core' ),
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'property-type', 'with_front' => false ],
        ] );
    }

    private function register_amenity(): void {
        register_taxonomy( 'ovr_amenity', PropertyPostType::POST_TYPE, [
            'labels' => [
                'name'          => _x( 'Amenities', 'taxonomy general name', 'ovr-core' ),
                'singular_name' => _x( 'Amenity', 'taxonomy singular name', 'ovr-core' ),
                'search_items'  => __( 'Search Amenities', 'ovr-core' ),
                'all_items'     => __( 'All Amenities', 'ovr-core' ),
                'edit_item'     => __( 'Edit Amenity', 'ovr-core' ),
                'add_new_item'  => __( 'Add New Amenity', 'ovr-core' ),
            ],
            'hierarchical'      => false,
            'public'            => true,
            'show_in_rest'      => true,
            'show_admin_column' => false,
            'rewrite'           => [ 'slug' => 'amenity', 'with_front' => false ],
        ] );
    }

    private function register_rental_type(): void {
        register_taxonomy( 'ovr_rental_type', PropertyPostType::POST_TYPE, [
            'labels' => [
                'name'          => _x( 'Rental Types', 'taxonomy general name', 'ovr-core' ),
                'singular_name' => _x( 'Rental Type', 'taxonomy singular name', 'ovr-core' ),
                'all_items'     => __( 'All Rental Types', 'ovr-core' ),
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'rental-type', 'with_front' => false ],
        ] );
    }
}
