<?php
/**
 * Bookings admin module (Feature 4).
 *
 * Adds a "Bookings" submenu under OVR Properties with a themed, sortable,
 * filterable, paginated, CSV-exportable list (via the shared ListTable
 * engine), plus New / Edit booking forms and a "New Booking (WordPress Sync)"
 * importer. All writes flow through BookingRepository so the guest manifest,
 * calendar block, and audit log stay in lockstep.
 *
 * @package OVR\Admin
 * @since   2.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Booking\BookingRepository;
use OVR\Sync\WordPressSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingsAdmin {

    public const PAGE_SLUG = 'ovr-core-bookings';
    public const PER_PAGE  = 20;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_booking_save', [ $this, 'handle_save' ] );
        add_action( 'admin_post_ovr_booking_delete', [ $this, 'handle_delete' ] );
        add_action( 'admin_post_ovr_booking_restore', [ $this, 'handle_restore' ] );
        add_action( 'admin_post_ovr_booking_wp_sync', [ $this, 'handle_wp_sync' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Bookings', 'ovr-core' ),
            __( 'Bookings', 'ovr-core' ),
            'ovr_view_bookings',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    /**
     * Build the shared ListTable for the bookings table.
     */
    private function list_table(): ListTable {
        global $wpdb;
        return new ListTable( [
            'table'       => $wpdb->prefix . 'ovr_bookings',
            'searchable'  => [ 'guest_name', 'guest_email', 'guest_phone', 'external_ref' ],
            'sortable'    => [ 'id', 'created_at', 'checkin_date', 'checkout_date', 'status', 'amount', 'source' ],
            'default'     => [ 'orderby' => 'created_at', 'order' => 'DESC' ],
            'per_page'    => self::PER_PAGE,
            'soft_delete' => true,
            'filters'     => [
                'status'   => [ 'column' => 'status' ],
                'source'   => [ 'column' => 'source' ],
                'property' => [ 'column' => 'property_id', 'cast' => 'int' ],
            ],
        ] );
    }

    public function render(): void {
        if ( ! current_user_can( 'ovr_view_bookings' ) ) {
            wp_die( esc_html__( 'You do not have permission to view bookings.', 'ovr-core' ) );
        }

        $view = sanitize_key( wp_unslash( $_GET['view'] ?? 'list' ) );

        // CSV export short-circuits before any HTML.
        if ( ! empty( $_GET['export_csv'] ) ) {
            $this->export_csv();
        }

        if ( in_array( $view, [ 'new', 'edit' ], true ) ) {
            $this->render_form( $view );
            return;
        }

        if ( 'sync' === $view ) {
            $this->render_sync();
            return;
        }

        $list = $this->list_table();
        $data = $list->query();

        TemplateLoader::render( 'admin/bookings.php', [
            'data'          => $data,
            'list'          => $list,
            'page_url'      => $this->page_url(),
            'status_labels' => BookingRepository::status_labels(),
            'stats'         => $this->get_stats(),
            'notice'        => $this->read_notice(),
            'csv_url'       => add_query_arg( 'export_csv', '1', $this->page_url() ),
            'new_url'       => add_query_arg( 'view', 'new', $this->page_url() ),
            'sync_url'      => add_query_arg( 'view', 'sync', $this->page_url() ),
            'wp_sync_url'   => wp_nonce_url( admin_url( 'admin-post.php?action=ovr_booking_wp_sync' ), 'ovr_booking_wp_sync' ),
        ] );
    }

    /**
     * Render the New / Edit booking form.
     */
    private function render_form( string $view ): void {
        $booking = null;
        if ( 'edit' === $view ) {
            $booking = BookingRepository::get( (int) ( $_GET['id'] ?? 0 ) );
            if ( ! $booking ) {
                wp_die( esc_html__( 'Booking not found.', 'ovr-core' ) );
            }
        }

        TemplateLoader::render( 'admin/booking-form.php', [
            'booking'       => $booking,
            'is_edit'       => 'edit' === $view,
            'properties'    => $this->property_options(),
            'status_labels' => BookingRepository::status_labels(),
            'sources'       => BookingRepository::SOURCES,
            'back_url'      => $this->page_url(),
            'action_url'    => admin_url( 'admin-post.php' ),
            'nonce'         => wp_create_nonce( 'ovr_booking_save' ),
        ] );
    }

    /**
     * Consolidated Calendar Sync dashboard (Feature 2): last sync + status +
     * source + imported counts + errors, across every channel (iCal + WordPress).
     */
    private function render_sync(): void {
        $settings  = (array) get_option( 'ovr_settings', [] );
        TemplateLoader::render( 'admin/sync-dashboard.php', [
            'channels'    => [
                'ical'      => [ 'label' => __( 'iCal (VRBO / Airbnb)', 'ovr-core' ), 'latest' => \OVR\Core\SyncLog::latest( 'ical' ) ],
                'wordpress' => [ 'label' => __( 'WordPress Import', 'ovr-core' ),     'latest' => \OVR\Core\SyncLog::latest( 'wordpress' ) ],
            ],
            'recent'      => \OVR\Core\SyncLog::recent( 30 ),
            'wp_enabled'  => ! empty( $settings['wp_sync_enabled'] ),
            'wp_schedule' => (string) ( $settings['wp_sync_schedule'] ?? 'manual' ),
            'wp_url'      => (string) ( $settings['wp_sync_url'] ?? '' ),
            'page_url'    => $this->page_url(),
            'settings_url'=> add_query_arg( [ 'post_type' => 'ovr_property', 'page' => 'ovr-core-settings', 'tab' => 'integration' ], admin_url( 'edit.php' ) ),
            'wp_sync_url' => wp_nonce_url( admin_url( 'admin-post.php?action=ovr_booking_wp_sync' ), 'ovr_booking_wp_sync' ),
        ] );
    }

    /**
     * Persist a new or edited booking.
     */
    public function handle_save(): void {
        if ( ! current_user_can( 'ovr_manage_bookings' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_booking_save' );

        $id   = (int) ( $_POST['booking_id'] ?? 0 );
        $data = [
            'property_id'   => (int) ( $_POST['property_id'] ?? 0 ),
            'guest_name'    => sanitize_text_field( wp_unslash( $_POST['guest_name'] ?? '' ) ),
            'guest_email'   => sanitize_email( wp_unslash( $_POST['guest_email'] ?? '' ) ),
            'guest_phone'   => sanitize_text_field( wp_unslash( $_POST['guest_phone'] ?? '' ) ),
            'checkin_date'  => sanitize_text_field( wp_unslash( $_POST['checkin_date'] ?? '' ) ),
            'checkout_date' => sanitize_text_field( wp_unslash( $_POST['checkout_date'] ?? '' ) ),
            'amount'        => (float) ( $_POST['amount'] ?? 0 ),
            'status'        => sanitize_key( wp_unslash( $_POST['status'] ?? 'booked' ) ),
            'source'        => sanitize_key( wp_unslash( $_POST['source'] ?? 'manual' ) ),
            'external_ref'  => sanitize_text_field( wp_unslash( $_POST['external_ref'] ?? '' ) ),
            'notes'         => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
        ];

        if ( ! $data['property_id'] || '' === $data['guest_name'] ) {
            wp_safe_redirect( add_query_arg( 'msg', 'invalid', $this->page_url() ) );
            exit;
        }

        if ( $id ) {
            BookingRepository::update( $id, $data );
            $msg = 'updated';
        } else {
            BookingRepository::create( $data );
            $msg = 'created';
        }

        wp_safe_redirect( add_query_arg( 'msg', $msg, $this->page_url() ) );
        exit;
    }

    public function handle_delete(): void {
        if ( ! current_user_can( 'ovr_manage_bookings' ) ) {
            wp_die( '403' );
        }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_booking_delete_' . $id );
        BookingRepository::delete( $id );
        wp_safe_redirect( add_query_arg( 'msg', 'deleted', $this->page_url() ) );
        exit;
    }

    public function handle_restore(): void {
        if ( ! current_user_can( 'ovr_manage_bookings' ) ) {
            wp_die( '403' );
        }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_booking_restore_' . $id );
        BookingRepository::restore( $id );
        wp_safe_redirect( add_query_arg( 'msg', 'restored', $this->page_url() ) );
        exit;
    }

    /**
     * Import bookings from a configured WordPress source (Feature 4 + 11).
     */
    public function handle_wp_sync(): void {
        if ( ! current_user_can( 'ovr_manage_bookings' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_booking_wp_sync' );

        $result = WordPressSync::run( 'manual' );
        $msg    = $result['success'] ? 'synced' : 'sync_failed';

        wp_safe_redirect( add_query_arg( [
            'msg'      => $msg,
            'imported' => (int) ( $result['imported'] ?? 0 ),
        ], $this->page_url() ) );
        exit;
    }

    /**
     * Stream the current (filtered) bookings as CSV.
     */
    private function export_csv(): void {
        if ( ! current_user_can( 'ovr_view_bookings' ) ) {
            wp_die( '403' );
        }
        $list = $this->list_table();
        $list->export_csv(
            'ovr-bookings',
            [
                __( 'Booking ID', 'ovr-core' ) => 'id',
                __( 'Property', 'ovr-core' )   => 'property_id',
                __( 'Owner', 'ovr-core' )      => 'owner_id',
                __( 'Guest', 'ovr-core' )      => 'guest_name',
                __( 'Email', 'ovr-core' )      => 'guest_email',
                __( 'Phone', 'ovr-core' )      => 'guest_phone',
                __( 'Check In', 'ovr-core' )   => 'checkin_date',
                __( 'Check Out', 'ovr-core' )  => 'checkout_date',
                __( 'Amount', 'ovr-core' )     => 'amount',
                __( 'Status', 'ovr-core' )     => 'status',
                __( 'Source', 'ovr-core' )     => 'source',
                __( 'Created', 'ovr-core' )    => 'created_at',
            ],
            static function ( array $row ): array {
                return [
                    $row['id'],
                    get_the_title( (int) $row['property_id'] ),
                    get_the_author_meta( 'display_name', (int) $row['owner_id'] ),
                    $row['guest_name'],
                    $row['guest_email'],
                    $row['guest_phone'],
                    $row['checkin_date'],
                    $row['checkout_date'],
                    $row['amount'],
                    $row['status'],
                    $row['source'],
                    $row['created_at'],
                ];
            }
        );
    }

    /**
     * Headline stat cards for the list screen.
     *
     * @return array<string, int|float>
     */
    private function get_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_bookings';
        $today = current_time( 'Y-m-d' );

        return [
            'total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL" ),
            'upcoming'   => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL AND checkin_date >= %s AND status NOT IN ('cancelled')",
                $today
            ) ),
            'revenue'    => (float) $wpdb->get_var(
                "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE deleted_at IS NULL AND status NOT IN ('cancelled')"
            ),
            'this_month' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL AND created_at >= %s",
                gmdate( 'Y-m-01 00:00:00' )
            ) ),
        ];
    }

    /**
     * Published properties as id => title for the form dropdown.
     *
     * @return array<int, string>
     */
    private function property_options(): array {
        $posts   = get_posts( [
            'post_type'      => 'ovr_property',
            'post_status'    => [ 'publish', 'pending', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ] );
        $options = [];
        foreach ( $posts as $pid ) {
            $options[ (int) $pid ] = get_the_title( $pid );
        }
        return $options;
    }

    private function read_notice(): ?array {
        if ( empty( $_GET['msg'] ) ) {
            return null;
        }
        $imported = (int) ( $_GET['imported'] ?? 0 );
        $map      = [
            'created'     => [ 'success', __( 'Booking created.', 'ovr-core' ) ],
            'updated'     => [ 'success', __( 'Booking updated.', 'ovr-core' ) ],
            'deleted'     => [ 'success', __( 'Booking moved to trash.', 'ovr-core' ) ],
            'restored'    => [ 'success', __( 'Booking restored.', 'ovr-core' ) ],
            'invalid'     => [ 'error', __( 'A property and guest name are required.', 'ovr-core' ) ],
            'synced'      => [ 'success', sprintf(
                /* translators: %d: number of bookings imported */
                _n( 'WordPress sync complete — %d booking imported.', 'WordPress sync complete — %d bookings imported.', $imported, 'ovr-core' ),
                $imported
            ) ],
            'sync_failed' => [ 'error', __( 'WordPress sync failed. Check the integration settings and error log.', 'ovr-core' ) ],
        ];
        $key = sanitize_key( wp_unslash( $_GET['msg'] ) );
        if ( ! isset( $map[ $key ] ) ) {
            return null;
        }
        return [ 'type' => $map[ $key ][0], 'text' => $map[ $key ][1] ];
    }

    private function page_url(): string {
        return add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
        ], admin_url( 'edit.php' ) );
    }
}
