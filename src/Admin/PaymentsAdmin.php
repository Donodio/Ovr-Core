<?php
/**
 * Payments Management — admin transaction log.
 *
 * Adds a "Payments" submenu under OVR Properties: a searchable,
 * filterable table of every payment with status badges, user info,
 * and quick-action menus.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PaymentsAdmin {

    public const PAGE_SLUG = 'ovr-core-payments';
    public const PER_PAGE  = 10;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Payments', 'ovr-core' ),
            __( 'Payments', 'ovr-core' ),
            'ovr_manage_payments',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'ovr_manage_payments' ) ) {
            return;
        }

        global $wpdb;

        $search  = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
        $status  = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
        $method  = sanitize_key( wp_unslash( $_GET['method'] ?? '' ) );
        $type    = sanitize_key( wp_unslash( $_GET['type'] ?? '' ) );
        $amt_min = isset( $_GET['amt_min'] ) ? (float) $_GET['amt_min'] : 0.0;
        $amt_max = isset( $_GET['amt_max'] ) ? (float) $_GET['amt_max'] : 0.0;
        $paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $date    = sanitize_key( wp_unslash( $_GET['date'] ?? '30' ) );
        $orderby = sanitize_key( wp_unslash( $_GET['orderby'] ?? 'created_at' ) );
        $order   = strtoupper( sanitize_key( wp_unslash( $_GET['order'] ?? 'DESC' ) ) );
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        $table = $wpdb->prefix . 'ovr_payments';
        $where = [ '1=1' ];
        $args  = [];

        if ( $search ) {
            $where[] = '( transaction_id LIKE %s OR p.user_id IN ( SELECT ID FROM ' . $wpdb->users . ' WHERE display_name LIKE %s OR user_email LIKE %s ) )';
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $args[]  = $like;
            $args[]  = $like;
            $args[]  = $like;
        }

        if ( $status && in_array( $status, [ 'completed', 'pending', 'declined' ], true ) ) {
            $where[] = 'p.status = %s';
            $args[]  = $status;
        }

        if ( $method && in_array( $method, [ 'stripe', 'paypal', 'authorize_net', 'free', 'wallet' ], true ) ) {
            $where[] = 'p.gateway = %s';
            $args[]  = $method;
        }

        // Payment type (subscription / listing_upgrade / topup / booking).
        if ( $type && in_array( $type, [ 'subscription', 'listing_upgrade', 'topup', 'booking' ], true ) ) {
            $where[] = 'p.payment_type = %s';
            $args[]  = $type;
        }

        // Amount range.
        if ( $amt_min > 0 ) {
            $where[] = 'p.amount >= %f';
            $args[]  = $amt_min;
        }
        if ( $amt_max > 0 ) {
            $where[] = 'p.amount <= %f';
            $args[]  = $amt_max;
        }

        if ( '7' === $date ) {
            $where[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif ( '30' === $date ) {
            $where[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        } elseif ( 'month' === $date ) {
            $where[] = 'p.created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01\')';
        } elseif ( 'year' === $date ) {
            $where[] = 'p.created_at >= DATE_FORMAT(NOW(), \'%Y-01-01\')';
        }

        $allowed_orderby = [ 'created_at', 'amount', 'status' ];
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'created_at';
        }

        $offset = ( $paged - 1 ) * self::PER_PAGE;

        $where_sql = implode( ' AND ', $where );

        // CSV export of the current (filtered) result set (Phase 13).
        if ( ! empty( $_GET['export_csv'] ) ) {
            $this->export_csv( $table, $where_sql, $args, $orderby, $order );
            // export_csv() exits.
        }

        $count_sql = "SELECT COUNT(*) FROM {$table} p WHERE {$where_sql}";
        $total     = $args ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : (int) $wpdb->get_var( $count_sql );
        $max_pages = (int) ceil( $total / self::PER_PAGE );

        $data_sql = "SELECT p.*, u.display_name, u.user_email
                     FROM {$table} p
                     LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
                     WHERE {$where_sql}
                     ORDER BY p.{$orderby} {$order}
                     LIMIT %d OFFSET %d";
        $data_args = array_merge( $args, [ self::PER_PAGE, $offset ] );
        $payments  = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_args ), ARRAY_A );

        $stats = $this->get_stats();

        // Preserve the active filters on the CSV-export link.
        $csv_url = add_query_arg( array_merge(
            array_filter( [
                's'       => $search,
                'status'  => $status,
                'method'  => $method,
                'type'    => $type,
                'date'    => $date,
                'amt_min' => $amt_min ?: '',
                'amt_max' => $amt_max ?: '',
            ], static fn( $v ) => '' !== $v && null !== $v ),
            [ 'export_csv' => '1' ]
        ), $this->page_url() );

        TemplateLoader::render( 'admin/payments.php', [
            'payments'  => $payments ?: [],
            'stats'     => $stats,
            'search'    => $search,
            'status'    => $status,
            'method'    => $method,
            'type'      => $type,
            'amt_min'   => $amt_min,
            'amt_max'   => $amt_max,
            'date'      => $date,
            'paged'     => $paged,
            'max_pages' => $max_pages,
            'total'     => $total,
            'orderby'   => $orderby,
            'order'     => $order,
            'page_url'  => $this->page_url(),
            'csv_url'   => $csv_url,
        ] );
    }

    /**
     * Stream the filtered payments as a CSV download (Phase 13) for
     * reconciliation / tax reporting. Excel opens CSV natively.
     */
    private function export_csv( string $table, string $where_sql, array $args, string $orderby, string $order ): void {
        global $wpdb;
        $sql = "SELECT p.*, u.display_name, u.user_email
                FROM {$table} p
                LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
                WHERE {$where_sql}
                ORDER BY p.{$orderby} {$order}";
        $rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="ovr-payments-' . gmdate( 'Ymd-His' ) . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Payment ID', 'Date', 'User', 'Email', 'Type', 'Plan/Feature', 'Amount', 'Currency', 'Method', 'Transaction ID', 'Status' ] );
        foreach ( (array) $rows as $r ) {
            $meta = json_decode( (string) ( $r['meta_data'] ?? '' ), true );
            $plan = is_array( $meta ) ? (string) ( $meta['plan_slug'] ?? $meta['feature'] ?? '' ) : '';
            fputcsv( $out, [
                $r['id'] ?? '',
                $r['created_at'] ?? '',
                $r['display_name'] ?? '',
                $r['user_email'] ?? '',
                $r['payment_type'] ?? '',
                $plan,
                $r['amount'] ?? '',
                $r['currency'] ?? 'USD',
                $r['gateway'] ?? '',
                $r['transaction_id'] ?? '',
                $r['status'] ?? '',
            ] );
        }
        fclose( $out );
        exit;
    }

    private function get_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';

        $total_volume   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE status = 'completed'" );
        $completed      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" );
        $pending_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
        $this_month     = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE status = 'completed' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')" );
        // Revenue breakdowns (Phase 13).
        $this_year      = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE status = 'completed' AND created_at >= DATE_FORMAT(NOW(),'%Y-01-01')" );
        $sub_revenue    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE status = 'completed' AND payment_type = 'subscription'" );
        $listing_revenue= (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE status = 'completed' AND payment_type = 'listing_upgrade'" );

        return [
            'total_volume'    => $total_volume,
            'completed'       => $completed,
            'pending_count'   => $pending_count,
            'this_month'      => $this_month,
            'this_year'       => $this_year,
            'sub_revenue'     => $sub_revenue,
            'listing_revenue' => $listing_revenue,
        ];
    }

    private function page_url(): string {
        return add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
        ], admin_url( 'edit.php' ) );
    }
}
