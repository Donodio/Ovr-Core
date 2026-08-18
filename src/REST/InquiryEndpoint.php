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

    /** Monthly cleanup cron hook (cleared in Deactivator). */
    public const PURGE_HOOK = 'ovr_purge_old_inquiries';

    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );

        // Monthly retention cleanup. Self-heals the schedule so existing
        // installs pick it up without re-activation.
        add_action( self::PURGE_HOOK, [ $this, 'purge_old' ] );
        if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::PURGE_HOOK );
        }
    }

    /**
     * Retention window in days (defaults to 365 / 12 months).
     */
    private function retention_days(): int {
        $s = get_option( 'ovr_settings', [] );
        return max( 30, (int) ( $s['inquiry_retention'] ?? 365 ) );
    }

    /**
     * Delete inquiries older than the retention window. Runs daily; the
     * effective cadence is "purge anything past 12 months".
     */
    public function purge_old(): void {
        global $wpdb;
        $table  = $wpdb->prefix . 'ovr_inquiries';
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $this->retention_days() . ' days', (int) current_time( 'timestamp' ) ) );
        $deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB
        if ( $deleted > 0 ) {
            \OVR\Core\AuditLog::record( 'inquiry.purge', 'inquiry', null, [ 'deleted' => $deleted, 'cutoff' => $cutoff ] );
        }
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

        // Tie the inquiry to the CRM guest manifest under the listing's owner.
        $guest_id = \OVR\Crm\GuestRepository::upsert( (int) $post->post_author, [
            'name'  => $name,
            'email' => $email,
            'phone' => (string) $request->get_param( 'guest_phone' ),
        ] );

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_inquiries';
        $inserted = $wpdb->insert( $table, [
            'property_id'   => $property_id,
            'landlord_id'   => (int) $post->post_author,
            'guest_id'      => $guest_id ?: null,
            'guest_name'    => $name,
            'guest_email'   => $email,
            'guest_phone'   => (string) $request->get_param( 'guest_phone' ),
            'message'       => $message,
            'checkin_date'  => $checkin  ?: null,
            'checkout_date' => $checkout ?: null,
            'guests'        => (int) $request->get_param( 'guests' ) ?: null,
            'status'        => 'new',
        ], [ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ] );

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

        // Only surface the last 12 months (Feature 6); older inquiries are
        // purged by cron but we also bound the query directly.
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $this->retention_days() . ' days', (int) current_time( 'timestamp' ) ) );
        $where  = $wpdb->prepare( 'WHERE landlord_id = %d AND created_at >= %s', $user_id, $cutoff );
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
