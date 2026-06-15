<?php
/**
 * Paid Services admin (Feature 1 — Listing Upgrades System).
 *
 * Full CRUD over the `ovr_paid_services` table: create, edit, enable/disable,
 * trash and restore promotional upgrade services. Each service carries a Name,
 * Description, Price, Duration, Badge, Priority Weight, Max Simultaneous and
 * Active flag, mapped to one of three boost behaviours via service_type. Uses
 * the shared ListTable engine (search / sort / filter / paginate / CSV) and the
 * PaidService repository (actor-stamped, soft-deletable, audit-logged).
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Subscription\PaidService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PaidServicesAdmin {

    public const PAGE_SLUG = 'ovr-core-paid-services';
    public const PER_PAGE  = 20;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_init', [ PaidService::class, 'maybe_seed' ] );
        add_action( 'admin_post_ovr_paid_service_save',    [ $this, 'handle_save' ] );
        add_action( 'admin_post_ovr_paid_service_toggle',  [ $this, 'handle_toggle' ] );
        add_action( 'admin_post_ovr_paid_service_delete',  [ $this, 'handle_delete' ] );
        add_action( 'admin_post_ovr_paid_service_restore', [ $this, 'handle_restore' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Paid Services', 'ovr-core' ),
            __( 'Paid Services', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    /**
     * Shared ListTable for the services table.
     */
    private function list_table(): ListTable {
        global $wpdb;
        return new ListTable( [
            'table'       => $wpdb->prefix . 'ovr_paid_services',
            'searchable'  => [ 'name', 'description', 'badge', 'slug' ],
            'sortable'    => [ 'id', 'name', 'price', 'duration_days', 'priority_weight', 'service_type', 'is_active', 'sort_order' ],
            'default'     => [ 'orderby' => 'sort_order', 'order' => 'ASC' ],
            'per_page'    => self::PER_PAGE,
            'soft_delete' => true,
            'filters'     => [
                'service_type' => [ 'column' => 'service_type' ],
                'is_active'    => [ 'column' => 'is_active', 'cast' => 'int' ],
            ],
        ] );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage paid services.', 'ovr-core' ) );
        }

        $view = sanitize_key( wp_unslash( $_GET['view'] ?? 'list' ) );

        if ( ! empty( $_GET['export_csv'] ) ) {
            $this->export_csv();
        }

        if ( in_array( $view, [ 'new', 'edit' ], true ) ) {
            $this->render_form( $view );
            return;
        }

        $is_trash = 'trash' === sanitize_key( wp_unslash( $_GET['status_view'] ?? '' ) );

        $list = $this->list_table();
        $data = $is_trash ? $this->trashed() : $list->query();

        TemplateLoader::render( 'admin/paid-services.php', [
            'data'            => $data,
            'list'            => $list,
            'is_trash'        => $is_trash,
            'page_url'        => $this->page_url(),
            'trash_url'       => add_query_arg( 'status_view', 'trash', $this->page_url() ),
            'types'           => PaidService::TYPES,
            'stats'           => $this->get_stats(),
            'trash_count'     => $this->trash_count(),
            'currency_symbol' => $this->currency_symbol(),
            'notice'          => $this->read_notice(),
            'csv_url'         => add_query_arg( 'export_csv', '1', $this->page_url() ),
            'new_url'         => add_query_arg( 'view', 'new', $this->page_url() ),
        ] );
    }

    /**
     * Soft-deleted services (for the Trash view), shaped like ListTable::query().
     *
     * @return array<string, mixed>
     */
    private function trashed(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_paid_services';
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC", ARRAY_A );
        return [
            'rows'      => $rows ?: [],
            'total'     => count( $rows ?: [] ),
            'per_page'  => self::PER_PAGE,
            'paged'     => 1,
            'max_pages' => 1,
            'search'    => '',
            'orderby'   => 'deleted_at',
            'order'     => 'DESC',
            'filters'   => [],
        ];
    }

    private function trash_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ovr_paid_services WHERE deleted_at IS NOT NULL" );
    }

    private function render_form( string $view ): void {
        $service = null;
        if ( 'edit' === $view ) {
            $service = PaidService::get( (int) ( $_GET['id'] ?? 0 ) );
            if ( ! $service ) {
                wp_die( esc_html__( 'Service not found.', 'ovr-core' ) );
            }
        }

        TemplateLoader::render( 'admin/paid-service-form.php', [
            'service'         => $service,
            'is_edit'         => 'edit' === $view,
            'types'           => PaidService::TYPES,
            'currency_symbol' => $this->currency_symbol(),
            'back_url'        => $this->page_url(),
            'action_url'      => admin_url( 'admin-post.php' ),
            'nonce'           => wp_create_nonce( 'ovr_paid_service_save' ),
        ] );
    }

    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_paid_service_save' );

        $id   = (int) ( $_POST['service_id'] ?? 0 );
        $data = [
            'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'description'      => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'service_type'     => sanitize_key( wp_unslash( $_POST['service_type'] ?? 'featured' ) ),
            'price'            => (float) ( $_POST['price'] ?? 0 ),
            'duration_days'    => (int) ( $_POST['duration_days'] ?? 14 ),
            'badge'            => sanitize_text_field( wp_unslash( $_POST['badge'] ?? '' ) ),
            'priority_weight'  => (int) ( $_POST['priority_weight'] ?? 0 ),
            'max_simultaneous' => (int) ( $_POST['max_simultaneous'] ?? 0 ),
            'sort_order'       => (int) ( $_POST['sort_order'] ?? 0 ),
            'is_renewable'     => ! empty( $_POST['is_renewable'] ),
            'auto_renew'       => ! empty( $_POST['auto_renew'] ),
            'is_active'        => ! empty( $_POST['is_active'] ),
        ];

        if ( '' === $data['name'] ) {
            wp_safe_redirect( add_query_arg( 'msg', 'invalid', $this->page_url() ) );
            exit;
        }

        PaidService::save( $data, $id );
        wp_safe_redirect( add_query_arg( 'msg', $id ? 'updated' : 'created', $this->page_url() ) );
        exit;
    }

    public function handle_toggle(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_paid_service_toggle_' . $id );
        $service = PaidService::get( $id );
        if ( $service ) {
            PaidService::set_active( $id, empty( $service['is_active'] ) );
        }
        wp_safe_redirect( add_query_arg( 'msg', 'toggled', $this->page_url() ) );
        exit;
    }

    public function handle_delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_paid_service_delete_' . $id );
        PaidService::trash( $id );
        wp_safe_redirect( add_query_arg( 'msg', 'deleted', $this->page_url() ) );
        exit;
    }

    public function handle_restore(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_paid_service_restore_' . $id );
        PaidService::restore( $id );
        wp_safe_redirect( add_query_arg( 'msg', 'restored', $this->page_url() ) );
        exit;
    }

    private function export_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        $this->list_table()->export_csv(
            'ovr-paid-services',
            [
                __( 'ID', 'ovr-core' )          => 'id',
                __( 'Name', 'ovr-core' )        => 'name',
                __( 'Type', 'ovr-core' )        => 'service_type',
                __( 'Price', 'ovr-core' )       => 'price',
                __( 'Duration', 'ovr-core' )    => 'duration_days',
                __( 'Badge', 'ovr-core' )       => 'badge',
                __( 'Priority', 'ovr-core' )    => 'priority_weight',
                __( 'Max Slots', 'ovr-core' )   => 'max_simultaneous',
                __( 'Active', 'ovr-core' )      => 'is_active',
                __( 'Created', 'ovr-core' )     => 'created_at',
            ]
        );
    }

    /**
     * Headline stat cards.
     *
     * @return array<string, int|float>
     */
    private function get_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_paid_services';
        return [
            'total'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL" ),
            'active'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL AND is_active = 1" ),
            'avg_price'=> (float) $wpdb->get_var( "SELECT COALESCE(AVG(price),0) FROM {$table} WHERE deleted_at IS NULL AND is_active = 1" ),
            'revenue'  => (float) $wpdb->get_var(
                "SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}ovr_payments WHERE payment_type = 'listing_upgrade' AND status = 'completed'"
            ),
        ] + $this->purchase_stats();
    }

    /**
     * Live purchase counts derived from the per-listing boost-expiry meta
     * (M3 F4 reporting): active, expired, and upcoming (next 7 days).
     *
     * @return array{active_purchases:int, expired_purchases:int, upcoming_expirations:int}
     */
    private function purchase_stats(): array {
        global $wpdb;
        $keys  = "('_ovr_bump_expires','_ovr_featured_expires','_ovr_slider_expires')";
        $today = current_time( 'Y-m-d' );
        $soon  = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );

        $active = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN {$keys} AND meta_value <> '' AND meta_value >= %s", $today
        ) );
        $expired = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN {$keys} AND meta_value <> '' AND meta_value < %s", $today
        ) );
        $upcoming = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN {$keys} AND meta_value >= %s AND meta_value <= %s", $today, $soon
        ) );

        return [
            'active_purchases'     => $active,
            'expired_purchases'    => $expired,
            'upcoming_expirations' => $upcoming,
        ];
    }

    private function currency_symbol(): string {
        $settings = (array) get_option( 'ovr_settings', [] );
        return (string) ( $settings['currency_symbol'] ?? '$' );
    }

    private function read_notice(): ?array {
        if ( empty( $_GET['msg'] ) ) {
            return null;
        }
        $map = [
            'created'  => [ 'success', __( 'Service created.', 'ovr-core' ) ],
            'updated'  => [ 'success', __( 'Service updated.', 'ovr-core' ) ],
            'toggled'  => [ 'success', __( 'Service status changed.', 'ovr-core' ) ],
            'deleted'  => [ 'success', __( 'Service moved to trash.', 'ovr-core' ) ],
            'restored' => [ 'success', __( 'Service restored.', 'ovr-core' ) ],
            'invalid'  => [ 'error', __( 'A service name is required.', 'ovr-core' ) ],
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
