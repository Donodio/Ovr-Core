<?php
/**
 * Inquiry REST Endpoint.
 *
 *   POST /ovr/v1/inquiries           — submit a new inquiry (public).
 *   GET  /ovr/v1/inquiries           — list landlord's inquiries (auth required).
 *   PATCH /ovr/v1/inquiries/{id}     — update inquiry status (auth, landlord only).
 *
 * @package OVR\REST
 * @since   1.0.0
 */

namespace OVR\REST;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class InquiryEndpoint {

    private const NAMESPACE = 'ovr/v1';

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/inquiries', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'property_id'  => [ 'required' => true,  'sanitize_callback' => 'absint' ],
                    'guest_name'   => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
                    'guest_email'  => [ 'required' => true,  'sanitize_callback' => 'sanitize_email' ],
                    'message'      => [ 'required' => true,  'sanitize_callback' => 'sanitize_textarea_field' ],
                    'guest_phone'  => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    'checkin'      => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    'checkout'     => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    'guests'       => [ 'sanitize_callback' => 'absint' ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_for_landlord' ],
                'permission_callback' => [ $this, 'auth_required' ],
                'args'                => [
                    'status'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_key' ],
                    'per_page' => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
                    'page'     => [ 'default' => 1,  'sanitize_callback' => 'absint' ],
                ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/inquiries/(?P<id>[\d]+)', [
            'methods'             => \WP_REST_Server::EDITABLE,
            'callback'            => [ $this, 'update_status' ],
            'permission_callback' => [ $this, 'auth_required' ],
            'args'                => [
                'id'     => [ 'sanitize_callback' => 'absint' ],
                'status' => [ 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
            ],
        ] );
    }

    public function auth_required() {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'rest_forbidden', __( 'Authentication required.', 'ovr-core' ), [ 'status' => 401 ] );
        }
        return true;
    }

    /**
     * Submit a new inquiry — public.
     */
    public function create( \WP_REST_Request $request ): \WP_REST_Response {
        $property_id = (int) $request->get_param( 'property_id' );
        $email       = (string) $request->get_param( 'guest_email' );
        $message     = (string) $request->get_param( 'message' );
        $name        = (string) $request->get_param( 'guest_name' );

        if ( ! $property_id || ! $name || ! $email || ! $message ) {
            return new \WP_REST_Response( [ 'message' => __( 'Missing required fields.', 'ovr-core' ) ], 400 );
        }
        if ( ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid email address.', 'ovr-core' ) ], 400 );
        }

        $post = get_post( $property_id );
        if ( ! $post || 'ovr_property' !== $post->post_type ) {
            return new \WP_REST_Response( [ 'message' => __( 'Property not found.', 'ovr-core' ) ], 404 );
        }

        $checkin  = (string) $request->get_param( 'checkin' );
        $checkout = (string) $request->get_param( 'checkout' );
        if ( $checkin && $checkout && strtotime( $checkin ) >= strtotime( $checkout ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Checkout must be after check-in.', 'ovr-core' ) ], 400 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_inquiries';
        $inserted = $wpdb->insert( $table, [
            'property_id'   => $property_id,
            'landlord_id'   => (int) $post->post_author,
            'guest_name'    => $name,
            'guest_email'   => $email,
            'guest_phone'   => (string) $request->get_param( 'guest_phone' ),
            'message'       => $message,
            'checkin_date'  => $checkin  ?: null,
            'checkout_date' => $checkout ?: null,
            'guests'        => (int) $request->get_param( 'guests' ) ?: null,
            'status'        => 'new',
        ], [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ] );

        if ( false === $inserted ) {
            return new \WP_REST_Response( [ 'message' => __( 'Failed to save inquiry.', 'ovr-core' ) ], 500 );
        }

        $inquiry_id = (int) $wpdb->insert_id;
        do_action( 'ovr_inquiry_submitted', $inquiry_id, $property_id );

        return new \WP_REST_Response( [
            'id'      => $inquiry_id,
            'message' => __( 'Inquiry submitted.', 'ovr-core' ),
        ], 201 );
    }

    /**
     * List inquiries for the current landlord user.
     */
    public function list_for_landlord( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;
        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'ovr_inquiries';

        $status   = (string) $request->get_param( 'status' );
        $per_page = max( 1, (int) $request->get_param( 'per_page' ) );
        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where    = $wpdb->prepare( 'WHERE landlord_id = %d', $user_id );
        if ( $status ) {
            $where .= $wpdb->prepare( ' AND status = %s', $status );
        }

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        return new \WP_REST_Response( [
            'items' => $rows ?: [],
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil( $total / $per_page ),
        ], 200 );
    }

    /**
     * Update an inquiry's status (new → replied → archived). Landlord only.
     */
    public function update_status( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;
        $id      = (int) $request->get_param( 'id' );
        $status  = (string) $request->get_param( 'status' );
        $allowed = [ 'new', 'replied', 'archived' ];

        if ( ! in_array( $status, $allowed, true ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Invalid status.', 'ovr-core' ) ], 400 );
        }

        $table = $wpdb->prefix . 'ovr_inquiries';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT landlord_id FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        if ( ! $row ) {
            return new \WP_REST_Response( [ 'message' => __( 'Inquiry not found.', 'ovr-core' ) ], 404 );
        }
        if ( (int) $row['landlord_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return new \WP_REST_Response( [ 'message' => __( 'Forbidden.', 'ovr-core' ) ], 403 );
        }

        $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );

        return new \WP_REST_Response( [ 'id' => $id, 'status' => $status ], 200 );
    }
}
