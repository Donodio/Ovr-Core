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

    /**
     * Canonical Village Section terms (Phase 10). These mirror the production
     * site's Section dropdown so migrated listings map 1:1. The Section dropdown
     * in the listing editor is populated from these ovr_village terms.
     *
     * @var string[]
     */
    public const SECTIONS = [
        'North of CR466 (Spanish Springs / Historic Area)',
        'North of CR466A (Sumter Landing)',
        'South of CR466A (Brownwood)',
        '(South of 44 / East of Tpke) Sawgrass Grove',
        'South of CR44 (Eastport / Middleton)',
    ];

    /** Canonical View terms (seeded so the search facet renders). */
    public const VIEWS = [
        'Lake View', 'Golf Course View', 'Garden View', 'Preserve View', 'Water View', 'Pool View',
    ];

    /** Canonical Feature terms (seeded so the search facet renders). */
    public const FEATURES = [
        'Pool', 'Hot Tub', 'Golf Cart Included', 'Garage', 'Lanai', 'Furnished', 'Pet Friendly', 'WiFi', 'Outdoor Kitchen',
    ];

    public function init(): void {
        add_action( 'init', [ $this, 'register_taxonomies' ], 9 );
        // Seed the Section terms once they (and the taxonomy) are registered.
        add_action( 'init', [ self::class, 'maybe_seed_sections' ], 11 );
        // Seed View / Feature facet terms once (so the search filters appear).
        add_action( 'init', [ self::class, 'maybe_seed_facets' ], 11 );
    }

    public function register_taxonomies(): void {
        $this->register_village();
        $this->register_property_type();
        $this->register_amenity();
        $this->register_rental_type();
        $this->register_view();
        $this->register_feature();
    }

    /**
     * Insert the canonical Village Section terms if they are missing. Runs once
     * per install (guarded by an option) so existing sites pick the sections up
     * on the next page load without re-activating. Idempotent.
     */
    public static function maybe_seed_sections(): void {
        if ( get_option( 'ovr_sections_seeded' ) ) {
            return;
        }
        self::seed_sections();
        update_option( 'ovr_sections_seeded', 1 );
    }

    /**
     * Create any missing canonical Section terms in the ovr_village taxonomy.
     */
    public static function seed_sections(): void {
        if ( ! taxonomy_exists( 'ovr_village' ) ) {
            return;
        }
        foreach ( self::SECTIONS as $name ) {
            if ( ! term_exists( $name, 'ovr_village' ) ) {
                wp_insert_term( $name, 'ovr_village' );
            }
        }
    }

    /**
     * Seed the canonical View + Feature terms once per install (option-guarded),
     * so the multi-select search facets have terms to display. Idempotent.
     */
    public static function maybe_seed_facets(): void {
        if ( get_option( 'ovr_facets_seeded' ) ) {
            return;
        }
        self::seed_facets();
        update_option( 'ovr_facets_seeded', 1 );
    }

    /**
     * Create any missing canonical View / Feature terms.
     */
    public static function seed_facets(): void {
        if ( taxonomy_exists( 'ovr_view' ) ) {
            foreach ( self::VIEWS as $name ) {
                if ( ! term_exists( $name, 'ovr_view' ) ) {
                    wp_insert_term( $name, 'ovr_view' );
                }
            }
        }
        if ( taxonomy_exists( 'ovr_feature' ) ) {
            foreach ( self::FEATURES as $name ) {
                if ( ! term_exists( $name, 'ovr_feature' ) ) {
                    wp_insert_term( $name, 'ovr_feature' );
                }
            }
        }
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
            // Mark feedback P6.1: restore lookup management under OVR Properties.
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'amenity', 'with_front' => false ],
        ] );
    }

    private function register_view(): void {
        register_taxonomy( 'ovr_view', PropertyPostType::POST_TYPE, [
            'labels' => [
                'name'          => _x( 'Views', 'taxonomy general name', 'ovr-core' ),
                'singular_name' => _x( 'View', 'taxonomy singular name', 'ovr-core' ),
                'all_items'     => __( 'All Views', 'ovr-core' ),
                'add_new_item'  => __( 'Add New View', 'ovr-core' ),
                'edit_item'     => __( 'Edit View', 'ovr-core' ),
            ],
            'hierarchical'      => false,
            'public'            => true,
            // Mark feedback P6.1: restore lookup management under OVR Properties.
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'view', 'with_front' => false ],
        ] );
    }

    private function register_feature(): void {
        register_taxonomy( 'ovr_feature', PropertyPostType::POST_TYPE, [
            'labels' => [
                'name'          => _x( 'Features', 'taxonomy general name', 'ovr-core' ),
                'singular_name' => _x( 'Feature', 'taxonomy singular name', 'ovr-core' ),
                'all_items'     => __( 'All Features', 'ovr-core' ),
                'add_new_item'  => __( 'Add New Feature', 'ovr-core' ),
                'edit_item'     => __( 'Edit Feature', 'ovr-core' ),
            ],
            'hierarchical'      => false,
            'public'            => true,
            // Mark feedback P6.1: restore lookup management under OVR Properties.
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'feature', 'with_front' => false ],
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
