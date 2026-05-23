<?php
/**
 * Review REST Endpoint.
 *
 *   GET   /ovr/v1/reviews?property_id=X      — list approved reviews.
 *   POST  /ovr/v1/reviews                    — submit a review.
 *   PATCH /ovr/v1/reviews/{id}               — admin: approve / reject.
 *
 * @package OVR\REST
 * @since   1.0.0
 */

namespace OVR\REST;

use OVR\Property\Reviews;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ReviewEndpoint {

    private const NAMESPACE = 'ovr/v1';

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/reviews', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'property_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
                    'per_page'    => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'submit' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'property_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
                    'rating'      => [ 'required' => true, 'sanitize_callback' => 'absint' ],
                    'guest_name'  => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    'guest_email' => [ 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
                    'title'       => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    'body'        => [ 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field' ],
                ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/reviews/(?P<id>[\d]+)', [
            'methods'             => \WP_REST_Server::EDITABLE,
            'callback'            => [ $this, 'update_status' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' ) || current_user_can( 'ovr_manage_reviews' );
            },
            'args'                => [
                'id'     => [ 'sanitize_callback' => 'absint' ],
                'status' => [ 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
            ],
        ] );
    }

    public function list( \WP_REST_Request $request ): \WP_REST_Response {
        $property_id = (int) $request->get_param( 'property_id' );
        $limit       = max( 1, (int) $request->get_param( 'per_page' ) );

        $rows = Reviews::get_for_property( $property_id, $limit );
        return new \WP_REST_Response( [ 'items' => $rows, 'count' => count( $rows ) ], 200 );
    }

    public function submit( \WP_REST_Request $request ): \WP_REST_Response {
        $result = Reviews::submit( [
            'property_id' => (int) $request->get_param( 'property_id' ),
            'rating'      => (int) $request->get_param( 'rating' ),
            'guest_name'  => (string) $request->get_param( 'guest_name' ),
            'guest_email' => (string) $request->get_param( 'guest_email' ),
            'title'       => (string) $request->get_param( 'title' ),
            'body'        => (string) $request->get_param( 'body' ),
            'user_id'     => get_current_user_id(),
        ] );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        $settings = get_option( 'ovr_settings', [] );
        $auto_approve = empty( $settings['review_approval'] );

        return new \WP_REST_Response( [
            'id'      => $result,
            'status'  => $auto_approve ? 'approved' : 'pending',
            'message' => $auto_approve
                ? __( 'Thanks for your review!', 'ovr-core' )
                : __( 'Thanks! Your review is awaiting moderation.', 'ovr-core' ),
        ], 201 );
    }

    public function update_status( \WP_REST_Request $request ): \WP_REST_Response {
        $id     = (int) $request->get_param( 'id' );
        $status = (string) $request->get_param( 'status' );

        if ( ! Reviews::set_status( $id, $status ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid request.', 'ovr-core' ) ], 400 );
        }

        return new \WP_REST_Response( [ 'id' => $id, 'status' => $status ], 200 );
    }
}
