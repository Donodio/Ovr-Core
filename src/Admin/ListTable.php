<?php
/**
 * Reusable admin list-table engine.
 *
 * The OVR admin uses bespoke themed tables (rendered via TemplateLoader),
 * not WP_List_Table. This class supplies the data layer every Phase 2 admin
 * screen shares: request parsing (search / sort / filter / paginate), safe
 * SQL assembly against a custom table, and CSV export — so each module gets
 * uniform, audit-friendly table behaviour without reinventing it.
 *
 * Usage:
 *   $list = new ListTable( [
 *       'table'      => $wpdb->prefix . 'ovr_bookings',
 *       'searchable' => [ 'guest_name', 'guest_email' ],
 *       'sortable'   => [ 'id', 'created_at', 'checkin_date', 'status' ],
 *       'default'    => [ 'orderby' => 'created_at', 'order' => 'DESC' ],
 *       'per_page'   => 20,
 *       'soft_delete'=> true,
 *       'filters'    => [
 *           'status' => [ 'column' => 'status' ],
 *           'source' => [ 'column' => 'source' ],
 *           'owner'  => [ 'column' => 'owner_id', 'cast' => 'int' ],
 *       ],
 *   ] );
 *   $list->parse_request();
 *   $data = $list->query();           // rows / total / max_pages / state
 *
 * @package OVR\Admin
 * @since   2.0.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ListTable {

    /** @var array<string, mixed> Resolved config. */
    private array $config;

    /** @var array<string, mixed> Parsed request state. */
    private array $state = [];

    /**
     * @param array{
     *   table:string,
     *   searchable?:string[],
     *   sortable?:string[],
     *   default?:array{orderby?:string,order?:string},
     *   per_page?:int,
     *   soft_delete?:bool,
     *   base_where?:string[],
     *   base_params?:array<int, mixed>,
     *   filters?:array<string, array{column:string,compare?:string,cast?:string}>
     * } $config
     */
    public function __construct( array $config ) {
        $this->config = array_merge( [
            'table'       => '',
            'searchable'  => [],
            'sortable'    => [ 'id' ],
            'default'     => [ 'orderby' => 'id', 'order' => 'DESC' ],
            'per_page'    => 20,
            'soft_delete' => false,
            'base_where'  => [],
            'base_params' => [],
            'filters'     => [],
        ], $config );
    }

    /**
     * Read search / sort / pagination / filter values from the request.
     */
    public function parse_request(): void {
        $orderby = sanitize_key( wp_unslash( $_GET['orderby'] ?? $this->config['default']['orderby'] ) );
        if ( ! in_array( $orderby, $this->config['sortable'], true ) ) {
            $orderby = $this->config['default']['orderby'];
        }

        $order = strtoupper( sanitize_key( wp_unslash( $_GET['order'] ?? $this->config['default']['order'] ) ) );
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        $filters = [];
        foreach ( $this->config['filters'] as $key => $def ) {
            $raw = wp_unslash( $_GET[ $key ] ?? '' );
            if ( '' === $raw || null === $raw ) {
                continue;
            }
            $filters[ $key ] = 'int' === ( $def['cast'] ?? '' )
                ? (int) $raw
                : sanitize_text_field( $raw );
        }

        $this->state = [
            'search'  => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
            'paged'   => max( 1, (int) ( $_GET['paged'] ?? 1 ) ),
            'orderby' => $orderby,
            'order'   => $order,
            'filters' => $filters,
        ];
    }

    /**
     * Build the WHERE clause + bound params from the current state.
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private function where(): array {
        global $wpdb;

        $clauses = $this->config['base_where'];
        $params  = $this->config['base_params'];

        if ( $this->config['soft_delete'] ) {
            $clauses[] = 'deleted_at IS NULL';
        }

        // Free-text search across configured columns.
        if ( '' !== $this->state['search'] && ! empty( $this->config['searchable'] ) ) {
            $like   = '%' . $wpdb->esc_like( $this->state['search'] ) . '%';
            $ors    = [];
            foreach ( $this->config['searchable'] as $col ) {
                $ors[]    = "`{$col}` LIKE %s";
                $params[] = $like;
            }
            $clauses[] = '(' . implode( ' OR ', $ors ) . ')';
        }

        // Exact / LIKE filters.
        foreach ( $this->state['filters'] as $key => $value ) {
            $def     = $this->config['filters'][ $key ];
            $column  = preg_replace( '/[^a-zA-Z0-9_]/', '', $def['column'] );
            $compare = strtoupper( $def['compare'] ?? '=' );
            if ( 'LIKE' === $compare ) {
                $clauses[] = "`{$column}` LIKE %s";
                $params[]  = '%' . $wpdb->esc_like( (string) $value ) . '%';
            } else {
                $clauses[] = "`{$column}` = %s";
                $params[]  = $value;
            }
        }

        $sql = $clauses ? ' WHERE ' . implode( ' AND ', $clauses ) : '';
        return [ $sql, $params ];
    }

    /**
     * Execute the query and return rows + pagination metadata + state.
     *
     * @return array<string, mixed>
     */
    public function query(): array {
        global $wpdb;

        if ( empty( $this->state ) ) {
            $this->parse_request();
        }

        $table            = $this->config['table'];
        [ $where, $params ] = $this->where();
        $per_page         = (int) $this->config['per_page'];
        $offset           = ( $this->state['paged'] - 1 ) * $per_page;

        // Total (for pagination) — same WHERE, no limit.
        $count_sql = "SELECT COUNT(*) FROM {$table}{$where}";
        $total     = (int) ( $params
            ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
            : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL

        // Page of rows. orderby is whitelisted in parse_request().
        $orderby   = $this->state['orderby'];
        $order     = $this->state['order'];
        $rows_sql  = "SELECT * FROM {$table}{$where} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
        $rows_args = array_merge( $params, [ $per_page, $offset ] );
        $rows      = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

        return [
            'rows'      => $rows ?: [],
            'total'     => $total,
            'per_page'  => $per_page,
            'paged'     => $this->state['paged'],
            'max_pages' => (int) ceil( $total / max( 1, $per_page ) ),
            'search'    => $this->state['search'],
            'orderby'   => $orderby,
            'order'     => $order,
            'filters'   => $this->state['filters'],
        ];
    }

    /**
     * Fetch every matching row ignoring pagination (for CSV export).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all_rows(): array {
        global $wpdb;

        if ( empty( $this->state ) ) {
            $this->parse_request();
        }

        $table              = $this->config['table'];
        [ $where, $params ] = $this->where();
        $orderby            = $this->state['orderby'];
        $order              = $this->state['order'];

        $sql  = "SELECT * FROM {$table}{$where} ORDER BY `{$orderby}` {$order}";
        $rows = $params
            ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL
            : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

        return $rows ?: [];
    }

    /**
     * Stream a CSV download of all matching rows, then exit.
     *
     * @param string                                       $filename Base filename (no extension).
     * @param array<string, string>                        $columns  header label => row key.
     * @param callable(array<string,mixed>):array<string>  $mapper   Optional row->cells override.
     */
    public function export_csv( string $filename, array $columns, ?callable $mapper = null ): void {
        $rows = $this->all_rows();

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '-' . current_time( 'Y-m-d' ) . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array_keys( $columns ) );

        foreach ( $rows as $row ) {
            if ( $mapper ) {
                fputcsv( $out, $mapper( $row ) );
                continue;
            }
            $line = [];
            foreach ( $columns as $key ) {
                $line[] = $row[ $key ] ?? '';
            }
            fputcsv( $out, $line );
        }

        fclose( $out );
        exit;
    }

    /**
     * Build a sortable column-header link that preserves current filters.
     *
     * @return string Full URL for toggling sort on $column.
     */
    public function sort_url( string $base_url, string $column ): string {
        $order = ( $this->state['orderby'] === $column && 'ASC' === $this->state['order'] ) ? 'DESC' : 'ASC';
        return add_query_arg( [ 'orderby' => $column, 'order' => $order ], $base_url );
    }
}
