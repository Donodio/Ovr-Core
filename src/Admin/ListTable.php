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
            } elseif ( in_array( $compare, [ '>=', '<=', '>', '<', '!=' ], true ) ) {
                // Range / inequality filters (e.g. created_at date ranges).
                $clauses[] = "`{$column}` {$compare} %s";
                $params[]  = $value;
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
        // UTF-8 BOM so Excel reads accented characters/emoji correctly.
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array_keys( $columns ) );

        foreach ( $rows as $row ) {
            if ( $mapper ) {
                fputcsv( $out, self::csv_safe_row( $mapper( $row ) ) );
                continue;
            }
            $line = [];
            foreach ( $columns as $key ) {
                $line[] = $row[ $key ] ?? '';
            }
            fputcsv( $out, self::csv_safe_row( $line ) );
        }

        fclose( $out );
        exit;
    }

    /**
     * Neutralise CSV/formula injection: a cell whose first character is a
     * spreadsheet formula trigger (= + - @, tab, CR) is prefixed with an
     * apostrophe so Excel/Sheets render it as literal text. fputcsv already
     * handles comma/quote/newline quoting. Mirrors the bespoke Users/Properties
     * exporters so every ListTable-backed export (CRM, Support, Bookings,
     * Payments, Audit Log, Paid Services) is hardened uniformly.
     *
     * @param array<int, int|string|null> $row
     * @return array<int, int|string>
     */
    private static function csv_safe_row( array $row ): array {
        return array_map( static function ( $v ) {
            $s = (string) $v;
            return ( '' !== $s && strpbrk( $s[0], "=+-@\t\r" ) !== false ) ? "'" . $s : $v;
        }, $row );
    }

    /**
     * Stream a real .xlsx (Office Open XML) download of all matching rows, then
     * exit. Self-contained (ZipArchive, no library); strings are written inline
     * so there's no shared-strings table to manage. Satisfies the universal
     * "Export Excel" requirement alongside export_csv().
     *
     * @param string                                       $filename Base filename (no extension).
     * @param array<string, string>                        $columns  header label => row key.
     * @param callable(array<string,mixed>):array<string>  $mapper   Optional row->cells override.
     */
    public function export_xlsx( string $filename, array $columns, ?callable $mapper = null ): void {
        $rows = $this->all_rows();

        // Assemble the sheet rows (header + data) as arrays of scalar cells.
        $matrix = [ array_keys( $columns ) ];
        foreach ( $rows as $row ) {
            if ( $mapper ) {
                $matrix[] = array_values( $mapper( $row ) );
                continue;
            }
            $line = [];
            foreach ( $columns as $key ) {
                $line[] = $row[ $key ] ?? '';
            }
            $matrix[] = $line;
        }

        // Fallback to CSV if ZipArchive is unavailable on the host.
        if ( ! class_exists( '\ZipArchive' ) ) {
            $this->export_csv( $filename, $columns, $mapper );
            return;
        }

        $sheet_rows = '';
        foreach ( $matrix as $r => $cells ) {
            $rownum = $r + 1;
            $sheet_rows .= '<row r="' . $rownum . '">';
            $c = 0;
            foreach ( $cells as $cell ) {
                $ref = self::xlsx_col( $c ) . $rownum;
                if ( is_numeric( $cell ) && '' !== (string) $cell ) {
                    $sheet_rows .= '<c r="' . $ref . '"><v>' . htmlspecialchars( (string) $cell, ENT_QUOTES ) . '</v></c>';
                } else {
                    $sheet_rows .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                        . htmlspecialchars( (string) $cell, ENT_QUOTES ) . '</t></is></c>';
                }
                $c++;
            }
            $sheet_rows .= '</row>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheet_rows . '</sheetData></worksheet>';

        $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';

        $root_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';

        $tmp = wp_tempnam( 'ovr-xlsx' );
        $zip = new \ZipArchive();
        $zip->open( $tmp, \ZipArchive::OVERWRITE );
        $zip->addFromString( '[Content_Types].xml', $content_types );
        $zip->addFromString( '_rels/.rels', $root_rels );
        $zip->addFromString( 'xl/workbook.xml', $workbook );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
        $zip->close();

        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '-' . current_time( 'Y-m-d' ) . '.xlsx"' );
        header( 'Content-Length: ' . filesize( $tmp ) );
        readfile( $tmp );
        @unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        exit;
    }

    /**
     * Zero-based column index → spreadsheet column letters (0→A, 26→AA).
     */
    private static function xlsx_col( int $index ): string {
        $letters = '';
        $index++;
        while ( $index > 0 ) {
            $mod     = ( $index - 1 ) % 26;
            $letters = chr( 65 + $mod ) . $letters;
            $index   = (int) ( ( $index - $mod ) / 26 );
        }
        return $letters;
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
