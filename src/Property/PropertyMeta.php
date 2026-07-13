<?php
/**
 * Property Meta Fields.
 *
 * Registers and manages all property meta fields.
 *
 * @package OVR\Property
 * @since   1.0.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PropertyMeta {

    /** @var array Meta field definitions. */
    private const FIELDS = [
        '_ovr_bedrooms'        => [ 'type' => 'integer', 'default' => 0 ],
        '_ovr_bathrooms'       => [ 'type' => 'number',  'default' => 0 ],
        '_ovr_max_guests'      => [ 'type' => 'integer', 'default' => 1 ],
        '_ovr_beds'            => [ 'type' => 'integer', 'default' => 0 ],
        '_ovr_sqft'            => [ 'type' => 'integer', 'default' => 0 ],
        '_ovr_base_price'      => [ 'type' => 'number',  'default' => 0 ],
        '_ovr_address'         => [ 'type' => 'string',  'default' => '' ],
        '_ovr_city'            => [ 'type' => 'string',  'default' => '' ],
        '_ovr_state'           => [ 'type' => 'string',  'default' => '' ],
        '_ovr_zip'             => [ 'type' => 'string',  'default' => '' ],
        '_ovr_country'         => [ 'type' => 'string',  'default' => '' ],
        '_ovr_village_name'    => [ 'type' => 'string',  'default' => '' ],
        '_ovr_latitude'        => [ 'type' => 'number',  'default' => 0 ],
        '_ovr_longitude'       => [ 'type' => 'number',  'default' => 0 ],
        '_ovr_min_stay'        => [ 'type' => 'integer', 'default' => 1 ],
        '_ovr_pets_allowed'    => [ 'type' => 'boolean', 'default' => false ],
        '_ovr_is_featured'     => [ 'type' => 'boolean', 'default' => false ],
        '_ovr_is_bumped'       => [ 'type' => 'boolean', 'default' => false ],
        '_ovr_in_slider'       => [ 'type' => 'boolean', 'default' => false ],
        '_ovr_bump_expires'    => [ 'type' => 'string',  'default' => '' ],
        '_ovr_featured_expires'=> [ 'type' => 'string',  'default' => '' ],
        '_ovr_slider_expires'  => [ 'type' => 'string',  'default' => '' ],
        '_ovr_video_url'       => [ 'type' => 'string',  'default' => '' ],
        '_ovr_video_id'        => [ 'type' => 'integer', 'default' => 0 ],
        '_ovr_panorama_url'    => [ 'type' => 'string',  'default' => '' ],
        '_ovr_panorama_id'     => [ 'type' => 'integer', 'default' => 0 ],
        '_ovr_document_ids'    => [ 'type' => 'string',  'default' => '' ],
        '_ovr_ical_url'        => [ 'type' => 'string',  'default' => '' ],
        '_ovr_rating_avg'      => [ 'type' => 'number',  'default' => 0 ],
        '_ovr_rating_count'    => [ 'type' => 'integer', 'default' => 0 ],
        '_ovr_listing_status'  => [ 'type' => 'string',  'default' => 'active' ],
        '_ovr_admin_status'    => [ 'type' => 'string',  'default' => 'approved' ],
        '_ovr_booking_mode'    => [ 'type' => 'string',  'default' => 'inquiry' ],
        '_ovr_hide_pricing'    => [ 'type' => 'boolean', 'default' => false ],
        '_ovr_nearby'          => [ 'type' => 'string',  'default' => '' ],
        '_ovr_policies'        => [ 'type' => 'string',  'default' => '' ],
        '_ovr_payment_info'    => [ 'type' => 'string',  'default' => '' ],
        '_ovr_gallery_ids'     => [ 'type' => 'string',  'default' => '' ],
        '_ovr_feature_order'   => [ 'type' => 'string',  'default' => '' ],
    ];

    public function init(): void {
        add_action( 'init', [ $this, 'register_meta' ] );
    }

    /**
     * Register meta fields for REST API access.
     */
    public function register_meta(): void {
        foreach ( self::FIELDS as $key => $config ) {
            register_post_meta( 'ovr_property', $key, [
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => $config['type'],
                'default'       => $config['default'],
                'auth_callback' => fn() => current_user_can( 'edit_ovr_properties' ),
            ] );
        }
    }

    /**
     * Get a property meta value with default.
     */
    public static function get( int $post_id, string $key, $default = null ) {
        $full_key = str_starts_with( $key, '_ovr_' ) ? $key : '_ovr_' . $key;
        $value = get_post_meta( $post_id, $full_key, true );

        if ( '' === $value && null !== $default ) {
            return $default;
        }

        return $value;
    }

    /**
     * Get all OVR meta for a property as an associative array.
     */
    public static function get_all( int $post_id ): array {
        $meta = [];
        foreach ( self::FIELDS as $key => $config ) {
            $value = get_post_meta( $post_id, $key, true );
            $clean_key = str_replace( '_ovr_', '', $key );
            $meta[ $clean_key ] = ( '' === $value ) ? $config['default'] : $value;
        }
        return $meta;
    }
}
