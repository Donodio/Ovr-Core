<?php
/**
 * CRM admin module (Feature 5).
 *
 * "CRM" submenu under OVR Properties: a dashboard (total / repeat / high-value
 * / new guests), the master "All Manifest" list (search + segment filters +
 * CSV via the shared ListTable engine), an Add/Edit Guest form, and a Guest
 * Profile showing stay history, inquiry history, payments, notes, tags and
 * lifetime value.
 *
 * Guests are populated automatically from bookings (BookingRepository) and can
 * also be added manually here.
 *
 * @package OVR\Admin
 * @since   2.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Crm\GuestRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CrmAdmin {

    public const PAGE_SLUG = 'ovr-core-crm';
    public const PER_PAGE  = 20;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_guest_save', [ $this, 'handle_save' ] );
        add_action( 'admin_post_ovr_guest_delete', [ $this, 'handle_delete' ] );
        add_action( 'admin_post_ovr_crm_threshold', [ $this, 'handle_threshold' ] );
        // Run the export before admin-header.php emits HTML, or the download
        // headers fail "headers already sent" and the CSV is appended to the page.
        add_action( 'admin_init', [ $this, 'maybe_export' ] );
    }

    /** Stream the CSV export early (admin_init) so it downloads as a file. */
    public function maybe_export(): void {
        if ( ( $_GET['page'] ?? '' ) === self::PAGE_SLUG && ! empty( $_GET['export_csv'] ) ) {
            $this->export_csv();
        }
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'CRM', 'ovr-core' ),
            __( 'CRM', 'ovr-core' ),
            'ovr_manage_crm',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    /**
     * The configurable high-value spend threshold.
     */
    public static function high_value_threshold(): float {
        $s = get_option( 'ovr_settings', [] );
        return (float) ( $s['crm_high_value_threshold'] ?? 5000 );
    }

    /**
     * Build the manifest ListTable, applying the active segment as base WHERE.
     */
    private function list_table( string $segment ): ListTable {
        global $wpdb;

        $base_where  = [];
        $base_params = [];

        switch ( $segment ) {
            case 'repeat':
                $base_where[] = 'total_stays > 1';
                break;
            case 'high_value':
                $base_where[]  = 'total_spend >= %f';
                $base_params[] = self::high_value_threshold();
                break;
            case 'new':
                $base_where[]  = 'created_at >= %s';
                $base_params[] = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days', (int) current_time( 'timestamp' ) ) );
                break;
        }

        return new ListTable( [
            'table'       => $wpdb->prefix . 'ovr_guests',
            'searchable'  => [ 'name', 'email', 'phone', 'tags' ],
            'sortable'    => [ 'id', 'name', 'total_stays', 'total_spend', 'last_stay', 'created_at' ],
            'default'     => [ 'orderby' => 'total_spend', 'order' => 'DESC' ],
            'per_page'    => self::PER_PAGE,
            'soft_delete' => true,
            'base_where'  => $base_where,
            'base_params' => $base_params,
            'filters'     => [
                'status' => [ 'column' => 'status' ],
            ],
        ] );
    }

    public function render(): void {
        if ( ! current_user_can( 'ovr_manage_crm' ) ) {
            wp_die( esc_html__( 'You do not have permission to view the CRM.', 'ovr-core' ) );
        }

        $view = sanitize_key( wp_unslash( $_GET['view'] ?? 'list' ) );

        if ( ! empty( $_GET['export_csv'] ) ) {
            $this->export_csv();
        }

        if ( 'profile' === $view ) {
            $this->render_profile();
            return;
        }
        if ( in_array( $view, [ 'new', 'edit' ], true ) ) {
            $this->render_form( $view );
            return;
        }

        $segment = sanitize_key( wp_unslash( $_GET['segment'] ?? '' ) );
        $list    = $this->list_table( $segment );
        $data    = $list->query();

        TemplateLoader::render( 'admin/crm.php', [
            'data'      => $data,
            'list'      => $list,
            'page_url'  => $this->page_url(),
            'segment'   => $segment,
            'threshold' => self::high_value_threshold(),
            'stats'     => GuestRepository::dashboard_stats( self::high_value_threshold() ),
            'notice'    => $this->read_notice(),
            'csv_url'   => add_query_arg( [ 'export_csv' => '1', 'segment' => $segment ], $this->page_url() ),
            'new_url'   => add_query_arg( 'view', 'new', $this->page_url() ),
            'threshold_action' => admin_url( 'admin-post.php' ),
            'threshold_nonce'  => wp_create_nonce( 'ovr_crm_threshold' ),
        ] );
    }

    private function render_profile(): void {
        $guest = GuestRepository::get( (int) ( $_GET['id'] ?? 0 ) );
        if ( ! $guest || ! empty( $guest['deleted_at'] ) ) {
            wp_die( esc_html__( 'Guest not found.', 'ovr-core' ) );
        }

        $stays = GuestRepository::stay_history( (int) $guest['id'] );

        TemplateLoader::render( 'admin/crm-guest.php', [
            'guest'     => $guest,
            'stays'     => $stays,
            'inquiries' => GuestRepository::inquiry_history( $guest ),
            'back_url'  => $this->page_url(),
            'edit_url'  => add_query_arg( [ 'view' => 'edit', 'id' => (int) $guest['id'] ], $this->page_url() ),
        ] );
    }

    private function render_form( string $view ): void {
        $guest = null;
        if ( 'edit' === $view ) {
            $guest = GuestRepository::get( (int) ( $_GET['id'] ?? 0 ) );
            if ( ! $guest ) {
                wp_die( esc_html__( 'Guest not found.', 'ovr-core' ) );
            }
        }

        TemplateLoader::render( 'admin/crm-guest-form.php', [
            'guest'      => $guest,
            'is_edit'    => 'edit' === $view,
            'back_url'   => $this->page_url(),
            'action_url' => admin_url( 'admin-post.php' ),
        ] );
    }

    public function handle_save(): void {
        if ( ! current_user_can( 'ovr_manage_crm' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_guest_save' );

        $id   = (int) ( $_POST['guest_id'] ?? 0 );
        $data = [
            'name'    => wp_unslash( $_POST['name'] ?? '' ),
            'email'   => wp_unslash( $_POST['email'] ?? '' ),
            'phone'   => wp_unslash( $_POST['phone'] ?? '' ),
            'address' => wp_unslash( $_POST['address'] ?? '' ),
            'notes'   => wp_unslash( $_POST['notes'] ?? '' ),
            'tags'    => wp_unslash( $_POST['tags'] ?? '' ),
            'status'  => wp_unslash( $_POST['status'] ?? 'active' ),
        ];

        if ( '' === trim( (string) $data['name'] ) ) {
            wp_safe_redirect( add_query_arg( 'msg', 'invalid', $this->page_url() ) );
            exit;
        }

        if ( $id ) {
            GuestRepository::update( $id, $data );
            $msg = 'updated';
        } else {
            GuestRepository::insert( $data );
            $msg = 'created';
        }

        wp_safe_redirect( add_query_arg( 'msg', $msg, $this->page_url() ) );
        exit;
    }

    public function handle_delete(): void {
        if ( ! current_user_can( 'ovr_manage_crm' ) ) {
            wp_die( '403' );
        }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_guest_delete_' . $id );
        GuestRepository::delete( $id );
        wp_safe_redirect( add_query_arg( 'msg', 'deleted', $this->page_url() ) );
        exit;
    }

    /**
     * Persist the configurable high-value threshold into ovr_settings.
     */
    public function handle_threshold(): void {
        if ( ! current_user_can( 'ovr_manage_crm' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_crm_threshold' );

        $value    = max( 0, (float) ( $_POST['crm_high_value_threshold'] ?? 5000 ) );
        $settings = (array) get_option( 'ovr_settings', [] );
        $settings['crm_high_value_threshold'] = $value;
        update_option( 'ovr_settings', $settings );

        wp_safe_redirect( add_query_arg( 'msg', 'threshold', $this->page_url() ) );
        exit;
    }

    private function export_csv(): void {
        if ( ! current_user_can( 'ovr_manage_crm' ) ) {
            wp_die( '403' );
        }
        $segment = sanitize_key( wp_unslash( $_GET['segment'] ?? '' ) );
        $this->list_table( $segment )->export_csv(
            'ovr-guests',
            [
                __( 'Guest', 'ovr-core' )       => 'name',
                __( 'Email', 'ovr-core' )       => 'email',
                __( 'Phone', 'ovr-core' )       => 'phone',
                __( 'Total Stays', 'ovr-core' ) => 'total_stays',
                __( 'Total Spend', 'ovr-core' ) => 'total_spend',
                __( 'Last Stay', 'ovr-core' )   => 'last_stay',
                __( 'Status', 'ovr-core' )      => 'status',
                __( 'Tags', 'ovr-core' )        => 'tags',
            ]
        );
    }

    private function read_notice(): ?array {
        if ( empty( $_GET['msg'] ) ) {
            return null;
        }
        $map = [
            'created'   => [ 'success', __( 'Guest added.', 'ovr-core' ) ],
            'updated'   => [ 'success', __( 'Guest updated.', 'ovr-core' ) ],
            'deleted'   => [ 'success', __( 'Guest removed.', 'ovr-core' ) ],
            'threshold' => [ 'success', __( 'High-value threshold saved.', 'ovr-core' ) ],
            'invalid'   => [ 'error', __( 'A guest name is required.', 'ovr-core' ) ],
        ];
        $key = sanitize_key( wp_unslash( $_GET['msg'] ) );
        return isset( $map[ $key ] ) ? [ 'type' => $map[ $key ][0], 'text' => $map[ $key ][1] ] : null;
    }

    private function page_url(): string {
        return add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
        ], admin_url( 'edit.php' ) );
    }
}
