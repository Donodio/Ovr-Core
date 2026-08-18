<?php
/**
 * Property REST API Endpoint.
 *
 * @package OVR\REST
 * @since   1.0.0
 */

namespace OVR\REST;

use OVR\Property\PropertyQuery;
use OVR\Property\PropertyCard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PropertyEndpoint {

    private const NAMESPACE = 'ovr/v1';

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        // Collection: GET (list) + POST (create — landlord only).
        register_rest_route( self::NAMESPACE, '/properties', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_properties' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'per_page' => [ 'default' => 12, 'sanitize_callback' => 'absint' ],
                    'page'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
                    'village'  => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                    'sort'     => [ 'default' => 'newest', 'sanitize_callback' => 'sanitize_key' ],
                    'featured' => [ 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ],
                    'search'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_property' ],
                'permission_callback' => [ $this, 'can_create' ],
            ],
        ] );

        // Single resource: GET + PUT/PATCH + DELETE.
        register_rest_route( self::NAMESPACE, '/properties/(?P<id>[\d]+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_property' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ), 'sanitize_callback' => 'absint' ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_property' ],
                'permission_callback' => [ $this, 'can_edit' ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_property' ],
                'permission_callback' => [ $this, 'can_edit' ],
            ],
        ] );
    }

    /**
     * Permission: must be logged in with edit_ovr_properties capability.
     */
    public function can_create( \WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'rest_forbidden', __( 'Authentication required.', 'ovr-core' ), [ 'status' => 401 ] );
        }
        if ( ! current_user_can( 'edit_ovr_properties' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'You cannot create properties.', 'ovr-core' ), [ 'status' => 403 ] );
        }
        return true;
    }

    /**
     * Permission: must be the property author or admin.
     */
    public function can_edit( \WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'rest_forbidden', __( 'Authentication required.', 'ovr-core' ), [ 'status' => 401 ] );
        }
        $id = (int) $request->get_param( 'id' );
        if ( ! current_user_can( 'edit_post', $id ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'You cannot edit this property.', 'ovr-core' ), [ 'status' => 403 ] );
        }
        return true;
    }

    /**
     * Create a new property. Body params: title, content, base_price, bedrooms, etc.
     */
    public function create_property( \WP_REST_Request $request ): \WP_REST_Response {
        $title = sanitize_text_field( (string) $request->get_param( 'title' ) );
        if ( ! $title ) {
            return new \WP_REST_Response( [ 'message' => __( 'Title is required.', 'ovr-core' ) ], 400 );
        }

        $post_id = wp_insert_post( [
            'post_type'    => 'ovr_property',
            'post_title'   => $title,
            'post_content' => wp_kses_post( (string) $request->get_param( 'content' ) ),
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return new \WP_REST_Response( [ 'message' => $post_id->get_error_message() ], 500 );
        }

        $this->apply_meta( (int) $post_id, $request );

        return new \WP_REST_Response( PropertyCard::get_card_data( (int) $post_id ), 201 );
    }

    /**
     * Update an existing property.
     */
    public function update_property( \WP_REST_Request $request ): \WP_REST_Response {
        $id = (int) $request->get_param( 'id' );
        $update = [ 'ID' => $id ];

        if ( null !== $request->get_param( 'title' ) ) {
            $update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
        }
        if ( null !== $request->get_param( 'content' ) ) {
            $update['post_content'] = wp_kses_post( (string) $request->get_param( 'content' ) );
        }
        if ( null !== $request->get_param( 'status' ) ) {
            $update['post_status'] = sanitize_key( (string) $request->get_param( 'status' ) );
        }

        $updated = wp_update_post( $update, true );
        if ( is_wp_error( $updated ) ) {
            return new \WP_REST_Response( [ 'message' => $updated->get_error_message() ], 500 );
        }

        $this->apply_meta( $id, $request );

        return new \WP_REST_Response( PropertyCard::get_card_data( $id ), 200 );
    }

    /**
     * Delete (archive) a property.
     */
    public function delete_property( \WP_REST_Request $request ): \WP_REST_Response {
        $id    = (int) $request->get_param( 'id' );
        $force = (bool) $request->get_param( 'force' );

        // Forced delete is a hard delete; non-forced is a soft-delete (archive).
        if ( $force ) {
            $result = wp_delete_post( $id, true );
        } else {
            $result = wp_update_post( [
                'ID'          => $id,
                'post_status' => \OVR\PostTypes\PropertyPostType::STATUS_ARCHIVED,
            ] );
        }

        if ( ! $result ) {
            return new \WP_REST_Response( [ 'message' => __( 'Failed to delete property.', 'ovr-core' ) ], 500 );
        }

        // Non-forced delete = soft delete (archive). Tag the reason so the admin
        // and owner archive screens know this came from the API/landlord.
        if ( ! $force ) {
            update_post_meta( $id, '_ovr_deleted_by', 'owner' );
            update_post_meta( $id, '_ovr_deleted_at', current_time( 'mysql' ) );
            if ( class_exists( '\OVR\Core\AuditLog' ) ) {
                \OVR\Core\AuditLog::record( 'listing.deleted', 'listing', $id, [ 'deleted_by' => 'owner', 'via' => 'rest' ], get_current_user_id() );
            }
        } else {
            if ( class_exists( '\OVR\Core\AuditLog' ) ) {
                \OVR\Core\AuditLog::record( 'listing.permanent_delete', 'listing', $id, [ 'via' => 'rest' ], get_current_user_id() );
            }
        }

        return new \WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
    }

    /**
     * Apply allowed meta fields from the request body to the property post.
     */
    private function apply_meta( int $post_id, \WP_REST_Request $request ): void {
        $allowed = [
            'base_price'   => [ 'type' => 'float' ],
            'bedrooms'     => [ 'type' => 'int'   ],
            'bathrooms'    => [ 'type' => 'float' ],
            'beds'         => [ 'type' => 'int'   ],
            'max_guests'   => [ 'type' => 'int'   ],
            'sqft'         => [ 'type' => 'int'   ],
            'min_stay'     => [ 'type' => 'int'   ],
            'pets_allowed' => [ 'type' => 'bool'  ],
            'address'      => [ 'type' => 'text'  ],
            'city'         => [ 'type' => 'text'  ],
            'state'        => [ 'type' => 'text'  ],
            'zip'          => [ 'type' => 'text'  ],
            'country'      => [ 'type' => 'text'  ],
            'latitude'     => [ 'type' => 'float' ],
            'longitude'    => [ 'type' => 'float' ],
            'video_url'    => [ 'type' => 'url'   ],
            'panorama_url' => [ 'type' => 'url'   ],
            'ical_url'     => [ 'type' => 'url'   ],
        ];

        foreach ( $allowed as $key => $spec ) {
            $val = $request->get_param( $key );
            if ( null === $val ) continue;

            switch ( $spec['type'] ) {
                case 'int':   $clean = (int) $val;                            break;
                case 'float': $clean = (float) $val;                          break;
                case 'bool':  $clean = (bool) $val ? 1 : 0;                   break;
                case 'url':   $clean = esc_url_raw( (string) $val );          break;
                default:      $clean = sanitize_text_field( (string) $val );  break;
            }
            update_post_meta( $post_id, '_ovr_' . $key, $clean );
        }

        // Bust caches.
        wp_cache_delete( 'ovr_pricing_'  . $post_id, 'ovr' );
        wp_cache_delete( 'ovr_avail_'    . $post_id, 'ovr' );
        wp_cache_delete( 'ovr_price_range', 'ovr' );
    }

    public function get_properties( \WP_REST_Request $request ): \WP_REST_Response {
        $filters = [
            'per_page'      => $request->get_param( 'per_page' ),
            'paged'         => $request->get_param( 'page' ),
            'village'       => $request->get_param( 'village' ) ? [ $request->get_param( 'village' ) ] : [],
            'sort'          => $request->get_param( 'sort' ),
            'featured_only' => $request->get_param( 'featured' ),
            'keyword'       => (string) $request->get_param( 'search' ),
        ];

        $query = PropertyQuery::query( $filters );
        $items = [];

        while ( $query->have_posts() ) {
            $query->the_post();
            $items[] = PropertyCard::get_card_data( get_the_ID() );
        }
        wp_reset_postdata();

        return new \WP_REST_Response( [
            'items'     => $items,
            'total'     => $query->found_posts,
            'max_pages' => $query->max_num_pages,
        ], 200 );
    }

    public function get_property( \WP_REST_Request $request ): \WP_REST_Response {
        $id = $request->get_param( 'id' );
        $post = get_post( $id );

        if ( ! $post || 'ovr_property' !== $post->post_type || 'publish' !== $post->post_status ) {
            return new \WP_REST_Response( [ 'message' => 'Property not found.' ], 404 );
        }

        $data = PropertyCard::get_card_data( $id );
        $data['content'] = apply_filters( 'the_content', $post->post_content );

        return new \WP_REST_Response( $data, 200 );
    }
}
