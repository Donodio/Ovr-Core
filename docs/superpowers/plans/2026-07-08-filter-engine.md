# Filter Engine Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a reusable filtering engine with 5 filter types (text/dropdown/date/numeric/boolean) and apply it to the Property Listings admin screen with AJAX-powered column filters.

**Architecture:** Layer-cake design: FilterTypeInterface → FilterTypes (5 implementations) → QueryBuilder (query normalization) → FilterEngine (config + render + dispatch) → FilterTable (orchestrator + AJAX + state). JS controller handles client-side debounced AJAX with History API.

**Tech Stack:** PHP 7.4+, WP_Query / WP_User_Query / custom SQL, vanilla JS, no framework dependencies.

**Spec:** `docs/superpowers/specs/2026-07-08-filter-engine-design.md`

---

## Chunk 1: FilterTypeInterface + Filter Types

**Files:**
- Create: `src/Admin/FilterTypes/FilterTypeInterface.php`
- Create: `src/Admin/FilterTypes/TextFilter.php`
- Create: `src/Admin/FilterTypes/DropdownFilter.php`
- Create: `src/Admin/FilterTypes/DateFilter.php`
- Create: `src/Admin/FilterTypes/NumericFilter.php`
- Create: `src/Admin/FilterTypes/BooleanFilter.php`

**Setup:** `mkdir -p src/Admin/FilterTypes`

### Task 1.1: Create FilterTypeInterface

- [ ] **Step 1: Write the file**

```php
<?php

namespace OVR\Admin\FilterTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface FilterTypeInterface {
    /**
     * Render the filter control HTML.
     *
     * @param string $key    Filter key (column name).
     * @param array  $config Filter configuration block.
     * @param mixed  $value  Current filter value.
     * @return string HTML output.
     */
    public function render( string $key, array $config, $value ): string;

    /**
     * Apply the filter value to a QueryBuilder.
     *
     * @param string       $key    Filter key.
     * @param mixed        $value  Filter value.
     * @param array        $config Filter configuration block.
     * @param QueryBuilder $query  The query builder to modify.
     */
    public function apply_to_query( string $key, $value, array $config, $query ): void;
}
```

- [ ] **Step 2: Create the file on disk**

Run: `mkdir -p /Users/admin/Local\ Sites/our-village-rentals/app/public/wp-content/plugins/ovr-core/src/Admin/FilterTypes`

Write the content above to `src/Admin/FilterTypes/FilterTypeInterface.php`.

### Task 1.2: Create TextFilter

- [ ] **Step 1: Write TextFilter.php**

```php
<?php

namespace OVR\Admin\FilterTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TextFilter implements FilterTypeInterface {
    public function render( string $key, array $config, $value ): string {
        $placeholder = esc_attr( $config['placeholder'] ?? sprintf(
            /* translators: %s: column label */
            __( 'Search %s…', 'ovr-core' ),
            $config['label'] ?? ''
        ) );
        $val = esc_attr( (string) $value );
        return sprintf(
            '<div class="ovr-ft-input-wrap">'
            . '<span class="ovr-ft-search-icon material-symbols-outlined">search</span>'
            . '<input type="text" class="ovr-ft-input" data-filter-key="%s" value="%s" placeholder="%s" autocomplete="off">'
            . '<button type="button" class="ovr-ft-clear" data-clear="%s" aria-label="%s">&times;</button>'
            . '</div>',
            esc_attr( $key ),
            $val,
            $placeholder,
            esc_attr( $key ),
            esc_attr__( 'Clear filter', 'ovr-core' )
        );
    }

    public function apply_to_query( string $key, $value, array $config, $query ): void {
        $term = trim( (string) $value );
        if ( '' === $term ) {
            return;
        }
        $meta_keys = $config['meta_keys'] ?? [ "_ovr_{$key}" ];
        $clauses = [];
        foreach ( $meta_keys as $mk ) {
            $clauses[] = [ 'key' => $mk, 'value' => $term, 'compare' => 'LIKE' ];
        }
        if ( ! empty( $clauses ) ) {
            $clauses['relation'] = 'OR';
            $query->add_meta_query( $clauses );
        }
    }
}
```

- [ ] **Step 2: Write the file to `src/Admin/FilterTypes/TextFilter.php`**

### Task 1.3: Create DropdownFilter

- [ ] **Step 1: Write DropdownFilter.php**

```php
<?php

namespace OVR\Admin\FilterTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DropdownFilter implements FilterTypeInterface {
    public function render( string $key, array $config, $value ): string {
        $options = $this->resolve_options( $config );
        $current = esc_attr( (string) $value );
        $placeholder = esc_attr( $config['placeholder'] ?? __( 'All', 'ovr-core' ) );
        $multiple = ! empty( $config['multiple'] ) ? ' multiple' : '';

        $html = sprintf(
            '<select class="ovr-ft-select" data-filter-key="%s"%s>',
            esc_attr( $key ),
            $multiple
        );
        $html .= sprintf( '<option value="">%s</option>', $placeholder );
        foreach ( $options as $opt_val => $opt_label ) {
            $sel = (string) $opt_val === $current ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $opt_val ),
                $sel,
                esc_html( $opt_label )
            );
        }
        $html .= '</select>';
        return $html;
    }

    private function resolve_options( array $config ): array {
        if ( isset( $config['source'] ) ) {
            return $this->resolve_source( $config['source'] );
        }
        return $config['options'] ?? [];
    }

    private function resolve_source( string $source ): array {
        if ( str_starts_with( $source, 'taxonomy:' ) ) {
            $tax = substr( $source, 9 );
            $terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
            if ( is_wp_error( $terms ) ) {
                return [];
            }
            $opts = [];
            foreach ( $terms as $t ) {
                $opts[ $t->slug ] = $t->name;
            }
            return $opts;
        }
        if ( str_starts_with( $source, 'class:' ) ) {
            $parts = explode( '::', substr( $source, 6 ), 2 );
            if ( count( $parts ) === 2 && class_exists( $parts[0] ) && method_exists( $parts[0], $parts[1] ) ) {
                $result = call_user_func( [ $parts[0], $parts[1] ] );
                return is_array( $result ) ? $result : [];
            }
            return [];
        }
        if ( str_starts_with( $source, 'table:' ) ) {
            global $wpdb;
            $parts = explode( '|', substr( $source, 6 ) );
            if ( count( $parts ) >= 2 ) {
                $table    = sanitize_key( $parts[0] );
                $id_col   = sanitize_key( $parts[1] );
                $name_col = sanitize_key( $parts[2] ?? $id_col );
                $rows     = $wpdb->get_results(
                    "SELECT DISTINCT {$id_col} AS id_val, {$name_col} AS name_val FROM {$wpdb->prefix}{$table} ORDER BY name_val ASC",
                    ARRAY_A
                );
                $opts = [];
                foreach ( $rows as $r ) {
                    $opts[ $r['id_val'] ] = $r['name_val'];
                }
                return $opts;
            }
        }
        return [];
    }

    public function apply_to_query( string $key, $value, array $config, $query ): void {
        if ( '' === $value || null === $value ) {
            return;
        }
        if ( isset( $config['source'] ) && str_starts_with( $config['source'], 'taxonomy:' ) ) {
            $tax = substr( $config['source'], 9 );
            $query->add_tax_query( [
                'taxonomy' => $tax,
                'field'    => 'slug',
                'terms'    => [ (string) $value ],
            ] );
            return;
        }
        $meta_key = $config['meta_key'] ?? "_ovr_{$key}";
        $query->add_meta_query( [ 'key' => $meta_key, 'value' => (string) $value ] );
    }
}
```

- [ ] **Step 2: Write the file to `src/Admin/FilterTypes/DropdownFilter.php`**

### Task 1.4: Create DateFilter

- [ ] **Step 1: Write DateFilter.php**

```php
<?php

namespace OVR\Admin\FilterTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DateFilter implements FilterTypeInterface {
    public function render( string $key, array $config, $value ): string {
        $from = esc_attr( $value['from'] ?? '' );
        $to   = esc_attr( $value['to'] ?? '' );
        $ekey = esc_attr( $key );

        $html = sprintf(
            '<div class="ovr-ft-date-group" data-filter-key="%s">',
            $ekey
        );
        $html .= sprintf(
            '<input type="date" class="ovr-ft-date-from" name="%s[from]" value="%s" placeholder="%s">',
            $ekey, $from, esc_attr__( 'From', 'ovr-core' )
        );
        $html .= sprintf(
            '<input type="date" class="ovr-ft-date-to" name="%s[to]" value="%s" placeholder="%s">',
            $ekey, $to, esc_attr__( 'To', 'ovr-core' )
        );
        $presets = [
            'today'     => __( 'Today', 'ovr-core' ),
            '7days'     => __( '7 Days', 'ovr-core' ),
            '30days'    => __( '30 Days', 'ovr-core' ),
            'thisMonth' => __( 'This Month', 'ovr-core' ),
            'lastMonth' => __( 'Last Month', 'ovr-core' ),
            'thisYear'  => __( 'This Year', 'ovr-core' ),
        ];
        $html .= '<div class="ovr-ft-date-presets">';
        foreach ( $presets as $pkey => $plabel ) {
            $html .= sprintf(
                '<button type="button" class="ovr-ft-date-preset" data-preset="%s">%s</button>',
                $pkey, esc_html( $plabel )
            );
        }
        $html .= '</div></div>';
        return $html;
    }

    public function apply_to_query( string $key, $value, array $config, $query ): void {
        $from = $value['from'] ?? '';
        $to   = $value['to'] ?? '';
        if ( '' === $from && '' === $to ) {
            return;
        }
        $date_query = [];
        if ( '' !== $from ) {
            $date_query['after']     = $from;
            $date_query['inclusive'] = true;
        }
        if ( '' !== $to ) {
            $date_query['before']     = $to;
            $date_query['inclusive']  = true;
        }
        if ( ! empty( $date_query ) ) {
            $query->add_date_query( [ $date_query ] );
        }
    }
}
```

- [ ] **Step 2: Write the file to `src/Admin/FilterTypes/DateFilter.php`**

### Task 1.5: Create NumericFilter

- [ ] **Step 1: Write NumericFilter.php**

```php
<?php

namespace OVR\Admin\FilterTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class NumericFilter implements FilterTypeInterface {
    public function render( string $key, array $config, $value ): string {
        $op     = $value['op'] ?? '=';
        $val1   = esc_attr( $value['val'] ?? $value['val1'] ?? '' );
        $val2   = esc_attr( $value['val2'] ?? '' );
        $ekey   = esc_attr( $key );

        $operators = [
            '='  => '=',
            '>'  => '>',
            '<'  => '<',
            '>=' => '≥',
            '<=' => '≤',
            'bt' => __( 'Between', 'ovr-core' ),
        ];

        $html = sprintf( '<div class="ovr-ft-numeric" data-filter-key="%s">', $ekey );
        $html .= sprintf(
            '<select class="ovr-ft-num-op" name="%s[op]">',
            $ekey
        );
        foreach ( $operators as $ok => $ol ) {
            $sel = $ok === $op ? ' selected' : '';
            $html .= sprintf( '<option value="%s"%s>%s</option>', $ok, $sel, esc_html( $ol ) );
        }
        $html .= '</select>';
        $html .= sprintf(
            '<input type="number" class="ovr-ft-num-val" name="%s[val]" value="%s" step="any" placeholder="0">',
            $ekey, $val1
        );
        $html .= sprintf(
            '<input type="number" class="ovr-ft-num-val2" name="%s[val2]" value="%s" step="any" placeholder="0" style="%s">',
            $ekey, $val2, 'bt' === $op ? '' : 'display:none'
        );
        $html .= '</div>';
        return $html;
    }

    public function apply_to_query( string $key, $value, array $config, $query ): void {
        $op   = $value['op'] ?? '=';
        $val1 = $value['val'] ?? $value['val1'] ?? '';
        $val2 = $value['val2'] ?? '';

        if ( '' === $val1 && 'bt' !== $op ) {
            return;
        }
        if ( 'bt' === $op && ( '' === $val1 || '' === $val2 ) ) {
            return;
        }

        $meta_key = $config['meta_key'] ?? "_ovr_{$key}";

        switch ( $op ) {
            case 'bt':
                $query->add_meta_query( [
                    'key'     => $meta_key,
                    'value'   => [ (float) $val1, (float) $val2 ],
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                ] );
                break;
            default:
                $query->add_meta_query( [
                    'key'     => $meta_key,
                    'value'   => (float) $val1,
                    'compare' => $op,
                    'type'    => 'NUMERIC',
                ] );
                break;
        }
    }
}
```

- [ ] **Step 2: Write the file to `src/Admin/FilterTypes/NumericFilter.php`**

### Task 1.6: Create BooleanFilter

- [ ] **Step 1: Write BooleanFilter.php**

```php
<?php

namespace OVR\Admin\FilterTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BooleanFilter implements FilterTypeInterface {
    public function render( string $key, array $config, $value ): string {
        $current = (string) ( $value ?? '' );
        $ekey    = esc_attr( $key );
        $label   = esc_attr( $config['label'] ?? $key );

        $options = [
            ''  => __( 'Any', 'ovr-core' ),
            '1' => __( 'Yes', 'ovr-core' ),
            '0' => __( 'No', 'ovr-core' ),
        ];

        $html = sprintf( '<select class="ovr-ft-select" data-filter-key="%s">', $ekey );
        foreach ( $options as $ok => $ol ) {
            $sel = $ok === $current ? ' selected' : '';
            $html .= sprintf( '<option value="%s"%s>%s</option>', $ok, $sel, esc_html( $ol ) );
        }
        $html .= '</select>';
        return $html;
    }

    public function apply_to_query( string $key, $value, array $config, $query ): void {
        if ( '' === $value ) {
            return;
        }
        $meta_key = $config['meta_key'] ?? "_ovr_{$key}";
        if ( '1' === (string) $value ) {
            $query->add_meta_query( [ 'key' => $meta_key, 'value' => '1' ] );
        } else {
            $query->add_meta_query( [
                'relation' => 'OR',
                [ 'key' => $meta_key, 'compare' => 'NOT EXISTS' ],
                [ 'key' => $meta_key, 'value' => '1', 'compare' => '!=' ],
            ] );
        }
    }
}
```

- [ ] **Step 2: Write the file to `src/Admin/FilterTypes/BooleanFilter.php`**

### Task 1.7: Verify all Chunk 1 files

- [ ] **Step 1: Run `php -l` on all 6 files**

Run: `php -l src/Admin/FilterTypes/FilterTypeInterface.php src/Admin/FilterTypes/TextFilter.php src/Admin/FilterTypes/DropdownFilter.php src/Admin/FilterTypes/DateFilter.php src/Admin/FilterTypes/NumericFilter.php src/Admin/FilterTypes/BooleanFilter.php`

Expected: `No syntax errors detected` for each file.

---

## Chunk 2: QueryBuilder + FilterEngine

**Files:**
- Create: `src/Admin/QueryBuilder.php`
- Create: `src/Admin/FilterEngine.php`

### Task 2.1: Create QueryBuilder

- [ ] **Step 1: Write QueryBuilder.php**

```php
<?php

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Normalizes query modifications across WP_Query, WP_User_Query, and custom SQL.
 *
 * Filter types call add_meta_query() / add_tax_query() etc. and then the
 * admin screen calls get_wp_query_args() / get_user_query_args() or uses
 * get_sql_where() + get_sql_params() to build its query.
 */
class QueryBuilder {

    private array $meta_queries = [];
    private array $tax_queries  = [];
    private array $date_queries = [];
    private string $search      = '';
    private array $where_clauses = [];
    private array $where_params  = [];
    private string $query_type   = 'wp_query'; // wp_query | user_query | custom

    public function __construct( string $query_type = 'wp_query' ) {
        $this->query_type = $query_type;
    }

    public function add_meta_query( array $clause ): void {
        $this->meta_queries[] = $clause;
    }

    public function add_tax_query( array $clause ): void {
        $this->tax_queries[] = $clause;
    }

    public function add_date_query( array $clause ): void {
        $this->date_queries[] = $clause;
    }

    public function set_search( string $term ): void {
        $this->search = $term;
    }

    /**
     * Add a raw SQL WHERE fragment. Only used by 'custom' query type.
     *
     * @param string $clause SQL fragment with %s/%d placeholders.
     * @param mixed  ...$params Values for placeholders.
     */
    public function add_where( string $clause, ...$params ): void {
        $this->where_clauses[] = $clause;
        foreach ( $params as $p ) {
            $this->where_params[] = $p;
        }
    }

    /**
     * Merge all accumulated filters into WP_Query args.
     */
    public function get_wp_query_args( array $base_args = [] ): array {
        if ( $this->meta_queries ) {
            $base_args['meta_query'] = $this->merge_meta_queries();
        }
        if ( $this->tax_queries ) {
            $base_args['tax_query'] = $this->tax_queries;
        }
        if ( $this->date_queries ) {
            $base_args['date_query'] = $this->date_queries;
        }
        if ( '' !== $this->search ) {
            $base_args['s'] = $this->search;
        }
        return $base_args;
    }

    /**
     * Merge all accumulated filters into WP_User_Query args.
     */
    public function get_user_query_args( array $base_args = [] ): array {
        if ( $this->meta_queries ) {
            $base_args['meta_query'] = $this->merge_meta_queries();
        }
        if ( '' !== $this->search ) {
            $base_args['search']         = '*' . $this->search . '*';
            $base_args['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
        }
        return $base_args;
    }

    /**
     * Get SQL WHERE string and params for custom queries.
     *
     * @return array{0:string,1:array} [where_clause, params]
     */
    public function get_sql_parts(): array {
        $clauses = $this->where_clauses;
        $params  = $this->where_params;

        // Build meta_query equivalents as SQL if there are any.
        foreach ( $this->meta_queries as $mq ) {
            $this->sql_from_meta_query( $mq, $clauses, $params );
        }

        $sql = $clauses ? ' WHERE ' . implode( ' AND ', $clauses ) : '';
        return [ $sql, $params ];
    }

    private function merge_meta_queries(): array {
        if ( count( $this->meta_queries ) <= 1 ) {
            return $this->meta_queries[0] ?? [];
        }
        $merged = [ 'relation' => 'AND' ];
        foreach ( $this->meta_queries as $mq ) {
            // If a single clause has its own relation (OR group), add it directly.
            if ( isset( $mq['relation'] ) ) {
                $merged[] = $mq;
            } else {
                $merged[] = $mq;
            }
        }
        return $merged;
    }

    /**
     * Convert a meta_query clause to SQL WHERE fragment (simplified).
     */
    private function sql_from_meta_query( array $mq, array &$clauses, array &$params ): void {
        global $wpdb;
        if ( isset( $mq['relation'] ) ) {
            // Nested relation — recurse.
            $nested = [];
            foreach ( $mq as $k => $v ) {
                if ( is_int( $k ) && is_array( $v ) ) {
                    $this->sql_from_meta_query( $v, $nested, $params );
                }
            }
            $rel = strtoupper( $mq['relation'] );
            if ( $nested ) {
                $clauses[] = '(' . implode( " {$rel} ", $nested ) . ')';
            }
            return;
        }
        $key  = esc_sql( $mq['key'] ?? '' );
        $val  = $mq['value'] ?? '';
        $comp = strtoupper( $mq['compare'] ?? '=' );

        if ( 'EXISTS' === $comp ) {
            $clauses[] = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = {$wpdb->posts}.ID AND meta_key = '{$key}')";
            return;
        }
        if ( 'NOT EXISTS' === $comp ) {
            $clauses[] = "NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = {$wpdb->posts}.ID AND meta_key = '{$key}')";
            return;
        }
        if ( is_array( $val ) && 'BETWEEN' === $comp ) {
            $clauses[] = "pm_{$key}.meta_value BETWEEN %f AND %f";
            $params[]  = (float) $val[0];
            $params[]  = (float) $val[1];
            return;
        }
        if ( 'LIKE' === $comp ) {
            $clauses[] = "pm_{$key}.meta_value LIKE %s";
            $params[]  = '%' . $wpdb->esc_like( (string) $val ) . '%';
            return;
        }
        $clauses[] = "pm_{$key}.meta_value {$comp} %s";
        $params[]  = (string) $val;
    }
}
```

- [ ] **Step 2: Write the file to `src/Admin/QueryBuilder.php`**

### Task 2.2: Create FilterEngine

- [ ] **Step 1: Write FilterEngine.php**

```php
<?php

namespace OVR\Admin;

use OVR\Admin\FilterTypes\FilterTypeInterface;
use OVR\Admin\FilterTypes\TextFilter;
use OVR\Admin\FilterTypes\DropdownFilter;
use OVR\Admin\FilterTypes\DateFilter;
use OVR\Admin\FilterTypes\NumericFilter;
use OVR\Admin\FilterTypes\BooleanFilter;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Parses column config, renders filter HTML, and dispatches filter
 * values to the correct filter type for query modification.
 */
class FilterEngine {

    private array $config;
    private array $type_instances = [];

    private const TYPE_MAP = [
        'text'     => TextFilter::class,
        'dropdown' => DropdownFilter::class,
        'date'     => DateFilter::class,
        'numeric'  => NumericFilter::class,
        'boolean'  => BooleanFilter::class,
    ];

    public function __construct( array $config ) {
        $this->config = $config;
    }

    /**
     * Render the column filter row HTML (<tr>).
     */
    public function render_filter_row(): string {
        $html = '<tr class="ovr-ft-row">';
        $html .= '<td class="ovr-ft-cell ovr-ft-check"></td>'; // checkbox column
        foreach ( $this->config['columns'] as $key => $col ) {
            $html .= '<td class="ovr-ft-cell">';
            if ( isset( $col['filter'] ) ) {
                $type   = $col['filter']['type'];
            $value  = wp_unslash( $_GET[ $key ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
            $value  = $this->maybe_decode_json( $value );
            $html  .= $this->render_filter( $key, $type, $col['filter'], $value );
            }
            $html .= '</td>';
        }
        $html .= '</tr>';
        return $html;
    }

    /**
     * Render the global filters toolbar HTML.
     */
    public function render_global_filters(): string {
        $globals = $this->config['global_filters'] ?? [];
        if ( empty( $globals ) ) {
            return '';
        }
        $html = '<div class="ovr-ft-global-filters">';
        foreach ( $globals as $key => $gf ) {
            $type  = $gf['type'];
            $value = wp_unslash( $_GET[ $key ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
            $value = $this->maybe_decode_json( $value );
            $html .= '<label>';
            if ( ! empty( $gf['label'] ) ) {
                $html .= '<span class="ovr-ft-global-label">' . esc_html( $gf['label'] ) . '</span>';
            }
            $html .= $this->render_filter( $key, $type, $gf, $value );
            $html .= '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Apply all current filter values (from $_GET) to a QueryBuilder.
     */
    public function apply_filters_to_query( QueryBuilder $query ): void {
        foreach ( $this->config['columns'] as $key => $col ) {
            if ( ! isset( $col['filter'] ) ) {
                continue;
            }
            $value = wp_unslash( $_GET[ $key ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
            if ( '' === $value || null === $value ) {
                continue;
            }
            $value = $this->maybe_decode_json( $value );
            $instance = $this->get_filter_type( $col['filter']['type'] );
            $instance->apply_to_query( $key, $value, $col['filter'], $query );
        }
        foreach ( $this->config['global_filters'] ?? [] as $key => $gf ) {
            $value = wp_unslash( $_GET[ $key ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
            if ( '' === $value || null === $value ) {
                continue;
            }
            $value = $this->maybe_decode_json( $value );
            $instance = $this->get_filter_type( $gf['type'] );
            $instance->apply_to_query( $key, $value, $gf, $query );
        }
    }

    /**
     * Apply AJAX filter values (from POST) to a QueryBuilder.
     * JSON-encoded strings (from date/range compound filters) are decoded automatically.
     */
    public function apply_ajax_filters_to_query( array $filter_values, QueryBuilder $query ): void {
        foreach ( $this->config['columns'] as $key => $col ) {
            if ( ! isset( $col['filter'] ) ) {
                continue;
            }
            $value = $filter_values[ $key ] ?? '';
            if ( '' === $value || null === $value ) {
                continue;
            }
            $value = $this->maybe_decode_json( $value );
            $instance = $this->get_filter_type( $col['filter']['type'] );
            $instance->apply_to_query( $key, $value, $col['filter'], $query );
        }
        foreach ( $this->config['global_filters'] ?? [] as $key => $gf ) {
            $value = $filter_values[ $key ] ?? '';
            if ( '' === $value || null === $value ) {
                continue;
            }
            $value = $this->maybe_decode_json( $value );
            $instance = $this->get_filter_type( $gf['type'] );
            $instance->apply_to_query( $key, $value, $gf, $query );
        }
    }

    /**
     * Decode a JSON string if it starts with '{'. Returns original value otherwise.
     */
    private function maybe_decode_json( $value ) {
        if ( is_string( $value ) && str_starts_with( $value, '{' ) ) {
            $decoded = json_decode( $value, true );
            return is_array( $decoded ) ? $decoded : $value;
        }
        return $value;
    }

    /**
     * Get the sortable columns list (keys marked sortable).
     *
     * @return array<string, string> column_key => label
     */
    public function get_sortable_columns(): array {
        $sortable = [];
        foreach ( $this->config['columns'] as $key => $col ) {
            if ( ! empty( $col['sortable'] ) ) {
                $sortable[ $key ] = $col['label'] ?? $key;
            }
        }
        return $sortable;
    }

    public function get_per_page(): int {
        return $this->config['per_page'] ?? 20;
    }

    public function get_columns(): array {
        return $this->config['columns'] ?? [];
    }

    public function get_query_config(): array {
        return $this->config['query'] ?? [];
    }

    private function render_filter( string $key, string $type, array $filter_config, $value ): string {
        $instance = $this->get_filter_type( $type );
        return $instance->render( $key, $filter_config, $value );
    }

    private function get_filter_type( string $type ): FilterTypeInterface {
        if ( ! isset( $this->type_instances[ $type ] ) ) {
            $class = self::TYPE_MAP[ $type ] ?? null;
            if ( ! $class ) {
                // Fallback: return a no-op filter.
                return new class implements FilterTypeInterface {
                    public function render( string $key, array $config, $value ): string { return ''; }
                    public function apply_to_query( string $key, $value, array $config, $query ): void {}
                };
            }
            $this->type_instances[ $type ] = new $class();
        }
        return $this->type_instances[ $type ];
    }
}
```

- [ ] **Step 2: Write the file to `src/Admin/FilterEngine.php`**

---

## Chunk 3: FilterTable + JS + CSS + Template

**Files:**
- Create: `src/Admin/FilterTable.php`
- Create: `assets/js/ovr-filter-table.js`
- Create: `assets/css/ovr-filter-table.css`
- Create: `templates/admin/filter-table.php`

### Task 3.1: Create FilterTable

- [ ] **Step 1: Write FilterTable.php**

```php
<?php

namespace OVR\Admin;

use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Orchestrates the full filter table lifecycle for an admin screen:
 * - Renders the page (header, global filters, column filters, table, pagination)
 * - Handles AJAX requests for filter changes
 * - Manages sort/pagination/search state
 * - Integrates with ListTable for custom table queries
 */
class FilterTable {

    private FilterEngine $engine;
    private string $page_slug;
    private string $capability;
    private array $table_args;

    /**
     * @param FilterEngine $engine     Configured filter engine.
     * @param string       $page_slug  Admin page slug.
     * @param string       $capability Required capability.
     * @param array        $table_args {
     *   Optional overrides:
     *     'base_url'     => admin URL for the page
     *     'list_table'   => ListTable instance (for custom table queries)
     *     'row_callback' => callable to render table row HTML
     *     'stats'        => array of stat cards
     * }
     */
    public function __construct(
        FilterEngine $engine,
        string $page_slug,
        string $capability = 'read',
        array $table_args = []
    ) {
        $this->engine     = $engine;
        $this->page_slug  = $page_slug;
        $this->capability = $capability;
        $this->table_args = $table_args;
    }

    /**
     * Register the AJAX endpoint.
     */
    public function register_ajax(): void {
        add_action( 'wp_ajax_ovr_filter_table', [ $this, 'ajax_handler' ] );
    }

    /**
     * Enqueue filter table assets.
     */
    public function enqueue_assets(): void {
        wp_enqueue_style(
            'ovr-filter-table',
            OVR_PLUGIN_URL . 'assets/css/ovr-filter-table.css',
            [],
            OVR_VERSION
        );
        wp_enqueue_script(
            'ovr-filter-table',
            OVR_PLUGIN_URL . 'assets/js/ovr-filter-table.js',
            [],
            OVR_VERSION,
            true
        );
        wp_localize_script( 'ovr-filter-table', 'ovrFilterTable', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ovr_filter_nonce' ),
            'pageSlug'=> $this->page_slug,
        ] );
    }

    /**
     * Render the full admin page.
     */
    public function render_page( array $extra_template_vars = [] ): void {
        if ( ! current_user_can( $this->capability ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ovr-core' ) );
        }

        $paged   = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $orderby = sanitize_key( wp_unslash( $_GET['orderby'] ?? '' ) );
        $order   = strtoupper( sanitize_key( wp_unslash( $_GET['order'] ?? 'DESC' ) ) );
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        // Build query via FilterEngine.
        $query_builder = new QueryBuilder( $this->get_query_source() );
        $this->engine->apply_filters_to_query( $query_builder );
        $query_args = $this->build_base_query_args( $paged, $orderby, $order );

        if ( 'wp_query' === $this->get_query_source() ) {
            $query_args = array_merge( $query_args, $query_builder->get_wp_query_args() );
            $wp_query   = new \WP_Query( $query_args );
            $rows       = $wp_query->posts;
            $total      = (int) $wp_query->found_posts;
            $max_pages  = (int) $wp_query->max_num_pages;
        } elseif ( 'user_query' === $this->get_query_source() ) {
            $query_args = array_merge( $query_args, $query_builder->get_user_query_args() );
            $user_query = new \WP_User_Query( $query_args );
            $rows       = $user_query->get_results();
            $total      = $user_query->get_total();
            $max_pages  = (int) ceil( $total / $this->engine->get_per_page() );
        } else {
            // Custom SQL — use ListTable or caller handles it.
            $rows      = [];
            $total     = 0;
            $max_pages = 1;
        }

        $vars = array_merge( $extra_template_vars, [
            'engine'      => $this->engine,
            'rows'        => $rows,
            'total'       => $total,
            'max_pages'   => $max_pages,
            'paged'       => $paged,
            'orderby'     => $orderby,
            'order'       => $order,
            'page_slug'   => $this->page_slug,
            'base_url'    => $this->table_args['base_url'] ?? $this->default_base_url(),
            'row_callback'=> $this->table_args['row_callback'] ?? null,
            'stats'       => $this->table_args['stats'] ?? [],
            'sortable'    => $this->engine->get_sortable_columns(),
            'columns'     => $this->engine->get_columns(),
        ] );

        echo '<div class="wrap ovr-ld" data-ovr-filter-table>';
        TemplateLoader::render( 'admin/filter-table', $vars );
        echo '</div>';
    }

    /**
     * AJAX handler: apply filters, return JSON with rendered table HTML.
     */
    public function ajax_handler(): void {
        if ( ! check_ajax_referer( 'ovr_filter_nonce', 'nonce', false )
             || ! current_user_can( $this->capability ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }

        parse_str( wp_unslash( $_POST['filters'] ?? '' ), $filters ); // phpcs:ignore WordPress.Security.NonceVerification

        $paged   = max( 1, absint( $filters['paged'] ?? 1 ) );
        $orderby = sanitize_key( wp_unslash( $filters['orderby'] ?? '' ) );
        $order   = strtoupper( sanitize_key( wp_unslash( $filters['order'] ?? 'DESC' ) ) );
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        $query_builder = new QueryBuilder( $this->get_query_source() );
        $this->engine->apply_ajax_filters_to_query( $filters, $query_builder );
        $query_args = $this->build_base_query_args( $paged, $orderby, $order );

        if ( 'wp_query' === $this->get_query_source() ) {
            $query_args = array_merge( $query_args, $query_builder->get_wp_query_args() );
            $wp_query   = new \WP_Query( $query_args );
            $rows       = $wp_query->posts;
            $total      = (int) $wp_query->found_posts;
            $max_pages  = (int) $wp_query->max_num_pages;
        } else {
            $rows      = [];
            $total     = 0;
            $max_pages = 1;
        }

        ob_start();
        $this->render_table_body( $rows, $filters );
        $rows_html = ob_get_clean();

        ob_start();
        $this->render_pagination( $paged, $max_pages, $total, $filters );
        $pagination_html = ob_get_clean();

        wp_send_json_success( [
            'rows_html'       => $rows_html,
            'pagination_html' => $pagination_html,
            'total'           => $total,
        ] );
    }

    /**
     * Render just the <tbody> rows for AJAX response.
     */
    public function render_table_body( array $rows, array $filters ): void {
        $callback = $this->table_args['row_callback'] ?? null;
        if ( $callback ) {
            call_user_func( $callback, $rows, $filters );
            return;
        }
        // Default: render a simple table body (override via row_callback).
        echo '<tbody id="ovr-table-tbody">';
        foreach ( $rows as $row ) {
            echo '<tr><td colspan="' . count( $this->engine->get_columns() ) . '">'
                . esc_html( is_object( $row ) ? ( $row->ID ?? $row->post_title ?? '' ) : ( $row['id'] ?? '' ) )
                . '</td></tr>';
        }
        echo '</tbody>';
    }

    /**
     * Render pagination HTML for AJAX response.
     */
    public function render_pagination( int $paged, int $max_pages, int $total, array $filters ): void {
        if ( $max_pages <= 1 ) {
            echo '<div class="ovr-ft-pagination-info">' . sprintf( esc_html__( '%d result(s)', 'ovr-core' ), $total ) . '</div>';
            return;
        }
        echo '<div class="ovr-ft-pagination">';
        echo '<span class="ovr-ft-pagination-info">' . sprintf( esc_html__( '%d result(s)', 'ovr-core' ), $total ) . '</span>';
        echo '<div class="ovr-ft-pagination-links">';
        if ( $paged > 1 ) {
            echo '<a href="#" class="ovr-ft-page" data-paged="' . ( $paged - 1 ) . '">&laquo;</a>';
        }
        $start = max( 1, $paged - 2 );
        $end   = min( $max_pages, $paged + 2 );
        for ( $i = $start; $i <= $end; $i++ ) {
            $cls = $i === $paged ? ' class="ovr-ft-page-active"' : '';
            echo '<a href="#" class="ovr-ft-page"' . $cls . ' data-paged="' . $i . '">' . $i . '</a>';
        }
        if ( $paged < $max_pages ) {
            echo '<a href="#" class="ovr-ft-page" data-paged="' . ( $paged + 1 ) . '">&raquo;</a>';
        }
        echo '</div></div>';
    }

    private function get_query_source(): string {
        $qc = $this->engine->get_query_config();
        return $qc['source'] ?? 'wp_query';
    }

    private function build_base_query_args( int $paged, string $orderby, string $order ): array {
        $qc     = $this->engine->get_query_config();
        $source = $qc['source'] ?? 'wp_query';

        if ( 'wp_query' === $source ) {
            return [
                'post_type'      => $qc['post_type'] ?? 'post',
                'post_status'    => $qc['post_status'] ?? 'any',
                'paged'          => $paged,
                'posts_per_page' => $this->engine->get_per_page(),
                'orderby'        => $orderby ?: $qc['default_orderby'] ?? 'date',
                'order'          => $order,
                'nopaging'       => false,
            ];
        }
        if ( 'user_query' === $source ) {
            return [
                'number'  => $this->engine->get_per_page(),
                'paged'   => $paged,
                'orderby' => $orderby ?: 'registered',
                'order'   => $order,
            ];
        }
        return [];
    }

    private function default_base_url(): string {
        return add_query_arg(
            [ 'post_type' => 'ovr_property', 'page' => $this->page_slug ],
            admin_url( 'edit.php' )
        );
    }
}
```

- [ ] **Step 2: Write the file to `src/Admin/FilterTable.php`**

### Task 3.2: Create CSS

- [ ] **Step 1: Write ovr-filter-table.css**

```css
/* ── Filter Engine Styles ── */

/* Column filter row */
.ovr-ft-row {
    background: var(--sclow, #f1f4f3);
}
.ovr-ft-cell {
    padding: 6px 8px;
    vertical-align: middle;
    border-bottom: 1px solid var(--ov, #bec9c8);
}
.ovr-ft-cell input[type="text"],
.ovr-ft-cell input[type="number"],
.ovr-ft-cell select {
    width: 100%;
    height: 30px;
    border: 1px solid var(--ov, #bec9c8);
    border-radius: 6px;
    padding: 0 8px;
    font-family: inherit;
    font-size: 12px;
    background: var(--surf, #fff);
    color: var(--on, #181c1c);
    box-sizing: border-box;
}
.ovr-ft-cell select {
    cursor: pointer;
    padding-right: 20px;
    appearance: auto;
}
.ovr-ft-cell input:focus,
.ovr-ft-cell select:focus {
    border-color: var(--p, #004c4c);
    outline: none;
    box-shadow: 0 0 0 2px var(--opc, #93e1e0);
}
.ovr-ft-check {
    width: 40px;
}

/* Text filter with search icon and clear button */
.ovr-ft-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.ovr-ft-search-icon {
    position: absolute;
    left: 6px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    color: var(--sv, #3f4948);
    pointer-events: none;
}
.ovr-ft-input {
    padding-left: 26px !important;
    padding-right: 24px !important;
}
.ovr-ft-clear {
    position: absolute;
    right: 4px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
    color: var(--sv, #3f4948);
    padding: 2px 4px;
    border-radius: 3px;
    display: none;
}
.ovr-ft-clear:hover {
    background: var(--ov, #bec9c8);
}
.ovr-ft-input:not(:placeholder-shown) ~ .ovr-ft-clear {
    display: block;
}

/* Date filter */
.ovr-ft-date-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.ovr-ft-date-group input[type="date"] {
    width: 100%;
    height: 26px;
    border: 1px solid var(--ov, #bec9c8);
    border-radius: 5px;
    padding: 0 6px;
    font-family: inherit;
    font-size: 11px;
    background: var(--surf, #fff);
    color: var(--on, #181c1c);
}
.ovr-ft-date-presets {
    display: flex;
    gap: 3px;
    flex-wrap: wrap;
}
.ovr-ft-date-preset {
    font-size: 10px;
    padding: 2px 6px;
    border: 1px solid var(--ov, #bec9c8);
    border-radius: 4px;
    background: var(--surf, #fff);
    cursor: pointer;
    color: var(--sv, #3f4948);
    font-family: inherit;
    transition: background .15s;
}
.ovr-ft-date-preset:hover {
    background: var(--sclow, #f1f4f3);
}

/* Numeric filter */
.ovr-ft-numeric {
    display: flex;
    gap: 4px;
    align-items: center;
}
.ovr-ft-num-op {
    width: 60px !important;
    flex-shrink: 0;
}
.ovr-ft-num-val,
.ovr-ft-num-val2 {
    flex: 1;
    min-width: 0;
}

/* Global filters toolbar */
.ovr-ft-global-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: end;
    margin-bottom: 16px;
    padding: 14px 16px;
    background: var(--surf, #fff);
    border: 1px solid var(--ov, #bec9c8);
    border-radius: 12px;
}
.ovr-ft-global-filters label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: var(--sv, #3f4948);
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.ovr-ft-global-filters select,
.ovr-ft-global-filters input[type="date"] {
    height: 34px;
    border: 1px solid var(--ov, #bec9c8);
    border-radius: 7px;
    padding: 0 10px;
    font-family: inherit;
    font-size: 13px;
    background: var(--surf, #fff);
    color: var(--on, #181c1c);
    min-width: 120px;
}

/* Pagination */
.ovr-ft-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 16px;
    padding: 12px 0;
}
.ovr-ft-pagination-info {
    font-size: 13px;
    color: var(--sv, #3f4948);
    margin: 0;
}
.ovr-ft-pagination-links {
    display: flex;
    gap: 4px;
    align-items: center;
}
.ovr-ft-page {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 8px;
    border: 1px solid var(--ov, #bec9c8);
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    color: var(--on, #181c1c);
    background: var(--surf, #fff);
}
.ovr-ft-page:hover {
    background: var(--sclow, #f1f4f3);
}
.ovr-ft-page-active {
    background: var(--p, #004c4c) !important;
    color: #fff !important;
    border-color: var(--p, #004c4c) !important;
}

/* Loading shimmer */
.ovr-ft-loading {
    position: relative;
    overflow: hidden;
}
.ovr-ft-loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--opc, #93e1e0), transparent);
    animation: ovr-ft-shimmer 1.2s ease-in-out infinite;
}
@keyframes ovr-ft-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Error toast */
.ovr-ft-toast {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 100000;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    box-shadow: 0 8px 32px rgba(0,0,0,.15);
    opacity: 0;
    transition: opacity .3s;
    pointer-events: none;
}
.ovr-ft-toast.is-visible {
    opacity: 1;
}
.ovr-ft-toast.is-success {
    background: #d6f3e6;
    color: #00714e;
    border: 1px solid #a6e3c8;
}
.ovr-ft-toast.is-error {
    background: #fde2e2;
    color: #93000a;
    border: 1px solid #f5b8b8;
}
```

- [ ] **Step 2: Write the file to `assets/css/ovr-filter-table.css`**

### Task 3.3: Create JS Controller

- [ ] **Step 1: Write ovr-filter-table.js**

```javascript
/**
 * OVR Filter Table — Vanilla JS controller for AJAX-powered filter tables.
 *
 * Debounced text inputs, instant dropdown/date changes, History API state
 * management, loading indicators, error handling.
 */
(function() {
    'use strict';

    var wrapper = document.querySelector('[data-ovr-filter-table]');
    if (!wrapper) return;

    var ajaxUrl  = typeof ovrFilterTable !== 'undefined' ? ovrFilterTable.ajaxUrl : ajaxurl;
    var nonce    = typeof ovrFilterTable !== 'undefined' ? ovrFilterTable.nonce : '';
    var pageSlug = typeof ovrFilterTable !== 'undefined' ? ovrFilterTable.pageSlug : '';

    var tbody       = document.getElementById('ovr-table-tbody');
    var pagination  = document.getElementById('ovr-table-footer');
    var debounceTimers = {};

    // ── Event listeners ──

    // Text inputs: debounced
    wrapper.querySelectorAll('input.ovr-ft-input').forEach(function(input) {
        var key = input.getAttribute('data-filter-key');
        input.addEventListener('input', function() {
            clearTimeout(debounceTimers[key]);
            debounceTimers[key] = setTimeout(function() {
                applyFilters();
            }, 350);
        });
    });

    // Selects and date inputs: instant
    wrapper.querySelectorAll('select[data-filter-key], input.ovr-ft-date-from, input.ovr-ft-date-to').forEach(function(el) {
        el.addEventListener('change', function() {
            applyFilters();
        });
    });

    // Date presets
    wrapper.querySelectorAll('.ovr-ft-date-preset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var preset = btn.getAttribute('data-preset');
            applyDatePreset(btn, preset);
            applyFilters();
        });
    });

    // Numeric operator change → toggle val2 visibility
    wrapper.querySelectorAll('.ovr-ft-num-op').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var container = sel.closest('.ovr-ft-numeric');
            var val2 = container.querySelector('.ovr-ft-num-val2');
            if (val2) {
                val2.style.display = sel.value === 'bt' ? '' : 'none';
            }
            applyFilters();
        });
    });
    wrapper.querySelectorAll('.ovr-ft-num-val, .ovr-ft-num-val2').forEach(function(input) {
        input.addEventListener('input', function() {
            clearTimeout(debounceTimers[input.name]);
            debounceTimers[input.name] = setTimeout(function() {
                applyFilters();
            }, 350);
        });
    });

    // Clear buttons
    wrapper.querySelectorAll('.ovr-ft-clear').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var key = btn.getAttribute('data-clear');
            var input = wrapper.querySelector('input[data-filter-key="' + key + '"]');
            if (input) {
                input.value = '';
                applyFilters();
            }
        });
    });

    // Pagination links (delegated)
    wrapper.addEventListener('click', function(e) {
        var pageLink = e.target.closest('.ovr-ft-page');
        if (pageLink && pageLink.getAttribute('data-paged')) {
            e.preventDefault();
            var paged = pageLink.getAttribute('data-paged');
            applyFilters({ paged: paged });
        }
    });

    // Sort links
    wrapper.addEventListener('click', function(e) {
        var sortLink = e.target.closest('.ovr-ft-sort-link');
        if (sortLink) {
            e.preventDefault();
            var column = sortLink.getAttribute('data-sort');
            var currentOrder = sortLink.getAttribute('data-order') || 'DESC';
            var newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
            applyFilters({ orderby: column, order: newOrder });
        }
    });

    // ── History API: back/forward ──
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.ovrFilters) {
            restoreFilters(e.state.ovrFilters);
            fetchTable(e.state.ovrFilters);
        }
    });

    // ── Core: apply filters, fetch table ──
    function applyFilters(overrides) {
        var filters = collectFilters();
        if (overrides) {
            Object.keys(overrides).forEach(function(k) {
                filters[k] = overrides[k];
            });
        }
        fetchTable(filters, filters);
    }

    function updateUrl(filters) {
        var url = new URL(window.location.href);
        Object.keys(filters).forEach(function(k) {
            if (filters[k] && filters[k] !== '') {
                url.searchParams.set(k, filters[k]);
            } else {
                url.searchParams.delete(k);
            }
        });
        url.searchParams.set('page', pageSlug);
        history.pushState({ ovrFilters: filters, time: Date.now() }, '', url.toString());
    }

    function collectFilters() {
        var filters = {};
        wrapper.querySelectorAll('[data-filter-key]').forEach(function(el) {
            var key = el.getAttribute('data-filter-key');
            var val = el.value || '';
            if (val) filters[key] = val;
        });
        // Date groups
        wrapper.querySelectorAll('.ovr-ft-date-group').forEach(function(group) {
            var key = group.getAttribute('data-filter-key');
            var from = group.querySelector('.ovr-ft-date-from').value || '';
            var to   = group.querySelector('.ovr-ft-date-to').value || '';
            if (from || to) {
                filters[key] = JSON.stringify({ from: from, to: to });
            }
        });
        return filters;
    }

    function restoreFilters(filters) {
        if (!filters) return;
        Object.keys(filters).forEach(function(key) {
            var val = filters[key];
            var el = wrapper.querySelector('[data-filter-key="' + key + '"]');
            if (!el) return;
            // Date/numeric compound groups — parse JSON, restore children.
            if (el.classList.contains('ovr-ft-date-group')) {
                try { var d = JSON.parse(val); } catch(e) { return; }
                var from = el.querySelector('.ovr-ft-date-from');
                var to   = el.querySelector('.ovr-ft-date-to');
                if (from && d.from) from.value = d.from;
                if (to && d.to) to.value = d.to;
                return;
            }
            if (el.classList.contains('ovr-ft-numeric')) {
                try { var n = JSON.parse(val); } catch(e) { return; }
                var op   = el.querySelector('.ovr-ft-num-op');
                var v1   = el.querySelector('.ovr-ft-num-val');
                var v2   = el.querySelector('.ovr-ft-num-val2');
                if (op && n.op) op.value = n.op;
                if (v1 && n.val) v1.value = n.val;
                if (v2 && n.val2) { v2.value = n.val2; v2.style.display = (n.op === 'bt' ? '' : 'none'); }
                return;
            }
            el.value = val;
        });
    }

    var retryCount = 0;

    function fetchTable(filters, urlFilters) {
        showLoading(true);

        var fd = new FormData();
        fd.set('action', 'ovr_filter_table');
        fd.set('nonce', nonce);
        fd.set('filters', buildQueryString(filters));

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            showLoading(false);
            retryCount = 0;
            if (res.success) {
                if (urlFilters) updateUrl(urlFilters);
                if (tbody && res.data.rows_html) {
                    tbody.outerHTML = res.data.rows_html;
                    tbody = document.getElementById('ovr-table-tbody');
                }
                if (pagination && res.data.pagination_html) {
                    pagination.outerHTML = res.data.pagination_html;
                    pagination = document.getElementById('ovr-table-footer');
                }
            } else {
                showToast(res.data && res.data.message ? res.data.message : 'Filter failed.', 'error');
            }
        })
        .catch(function() {
            showLoading(false);
            if (retryCount < 1) {
                retryCount++;
                showToast('Connection error. Retrying…', 'error');
                setTimeout(function() { fetchTable(filters, urlFilters); }, 2000);
            } else {
                showToast('Connection failed. Please try again.', 'error');
            }
        });
    }

    function showLoading(active) {
        wrapper.classList.toggle('ovr-ft-loading', active);
    }

    function buildQueryString(obj) {
        var parts = [];
        Object.keys(obj).forEach(function(k) {
            parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]));
        });
        return parts.join('&');
    }

    function showToast(msg, type) {
        var toast = document.createElement('div');
        toast.className = 'ovr-ft-toast is-' + (type || 'error') + ' is-visible';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(function() {
            toast.classList.remove('is-visible');
            setTimeout(function() { toast.remove(); }, 300);
        }, 3000);
    }

    // ── Date preset handler ──
    function applyDatePreset(btn, preset) {
        var group = btn.closest('.ovr-ft-date-group');
        if (!group) return;
        var fromInput = group.querySelector('.ovr-ft-date-from');
        var toInput   = group.querySelector('.ovr-ft-date-to');
        if (!fromInput || !toInput) return;

        var today = new Date();
        var fromDate, toDate;

        switch (preset) {
            case 'today':
                fromDate = toDate = today;
                break;
            case '7days':
                toDate = today;
                fromDate = new Date(today);
                fromDate.setDate(fromDate.getDate() - 7);
                break;
            case '30days':
                toDate = today;
                fromDate = new Date(today);
                fromDate.setDate(fromDate.getDate() - 30);
                break;
            case 'thisMonth':
                fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                toDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'lastMonth':
                fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'thisYear':
                fromDate = new Date(today.getFullYear(), 0, 1);
                toDate = new Date(today.getFullYear(), 11, 31);
                break;
        }

        if (fromDate) {
            fromInput.value = formatDate(fromDate);
        }
        if (toDate) {
            toInput.value = formatDate(toDate);
        }
    }

    function formatDate(d) {
        var yyyy = d.getFullYear();
        var mm   = String(d.getMonth() + 1).padStart(2, '0');
        var dd   = String(d.getDate()).padStart(2, '0');
        return yyyy + '-' + mm + '-' + dd;
    }
})();
```

- [ ] **Step 2: Write the file to `assets/js/ovr-filter-table.js`**

### Task 3.4: Create Base Template

- [ ] **Step 1: Write filter-table.php**

```php
<?php
/**
 * Reusable admin table template with column filters.
 *
 * @package OVR
 * @var FilterEngine $engine      Configured filter engine.
 * @var array        $rows        Data rows (WP_Post[] or arrays).
 * @var int          $total       Total matching records.
 * @var int          $max_pages   Total pages.
 * @var int          $paged       Current page.
 * @var string       $orderby     Current sort column.
 * @var string       $order       Current sort direction.
 * @var string       $page_slug   Admin page slug.
 * @var string       $base_url    Base admin URL for the page.
 * @var callable|null $row_callback Callable for rendering rows.
 * @var array        $stats       Stat card data.
 * @var array        $sortable    Sortable columns.
 * @var array        $columns     All column definitions.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ovr-pls-header">
    <div>
        <h1><?php echo esc_html( get_admin_page_title() ?: __( 'Properties', 'ovr-core' ) ); ?></h1>
        <?php if ( ! empty( $stats ) ) : ?>
        <div class="ovr-pls-stats">
            <?php foreach ( $stats as $stat ) : ?>
            <span class="ovr-pls-stat">
                <strong><?php echo esc_html( $stat['count'] ?? 0 ); ?></strong>
                <?php echo esc_html( $stat['label'] ?? '' ); ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php echo $engine->render_global_filters(); ?>

<div class="ovr-pls-table-wrap">
<table class="ovr-pls-table" role="grid">
<thead>
    <?php echo $engine->render_filter_row(); ?>
    <tr>
        <th class="ovr-pls-th-check"><span class="ovr-pls-th-inner"><input type="checkbox" class="ovr-pls-cb" id="ovr-pls-select-all"></span></th>
        <?php foreach ( $columns as $key => $col ) : ?>
        <th class="<?php echo esc_attr( 'ovr-pls-th-' . $key ); ?>">
            <div class="ovr-pls-th-inner">
                <?php if ( ! empty( $col['sortable'] ) ) : ?>
                <a href="#" class="ovr-ft-sort-link" data-sort="<?php echo esc_attr( $key ); ?>" data-order="<?php echo esc_attr( $order ); ?>">
                    <?php echo esc_html( $col['label'] ?? $key ); ?>
                </a>
                <?php else : ?>
                <span><?php echo esc_html( $col['label'] ?? $key ); ?></span>
                <?php endif; ?>
            </div>
        </th>
        <?php endforeach; ?>
    </tr>
</thead>
<tbody id="ovr-table-tbody">
    <?php if ( $row_callback && ! empty( $rows ) ) : ?>
        <?php call_user_func( $row_callback, $rows, get_defined_vars() ); ?>
    <?php elseif ( empty( $rows ) ) : ?>
    <tr>
        <td colspan="<?php echo count( $columns ) + 1; ?>">
            <div class="ovr-pls-empty">
                <span class="material-symbols-outlined">search_off</span>
                <p><?php esc_html_e( 'No results match your filters.', 'ovr-core' ); ?></p>
            </div>
        </td>
    </tr>
    <?php else : ?>
        <?php foreach ( $rows as $row ) : ?>
        <tr>
            <td><?php echo esc_html( is_object( $row ) ? ( $row->ID ?? '' ) : ( $row['id'] ?? '' ) ); ?></td>
            <td colspan="<?php echo count( $columns ); ?>"><?php echo esc_html( is_object( $row ) ? ( $row->post_title ?? '' ) : '' ); ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
</table>
</div>

<div id="ovr-table-footer" class="ovr-ft-pagination">
    <?php
    if ( $max_pages > 0 ) {
        if ( $max_pages > 1 ) {
            echo '<div class="ovr-ft-pagination-links">';
            if ( $paged > 1 ) {
                echo '<a href="#" class="ovr-ft-page" data-paged="' . ( $paged - 1 ) . '">&laquo;</a>';
            }
            $start = max( 1, $paged - 2 );
            $end   = min( $max_pages, $paged + 2 );
            for ( $i = $start; $i <= $end; $i++ ) {
                $cls = $i === $paged ? ' ovr-ft-page-active' : '';
                echo '<a href="#" class="ovr-ft-page' . $cls . '" data-paged="' . $i . '">' . $i . '</a>';
            }
            if ( $paged < $max_pages ) {
                echo '<a href="#" class="ovr-ft-page" data-paged="' . ( $paged + 1 ) . '">&raquo;</a>';
            }
            echo '</div>';
        }
        echo '<span class="ovr-ft-pagination-info">' . sprintf( esc_html__( '%d result(s)', 'ovr-core' ), (int) $total ) . '</span>';
    }
    ?>
</div>
```

- [ ] **Step 2: Write the file to `templates/admin/filter-table.php`**

---

## Chunk 4: PropertyListScreen Integration + Plugin Registration

**Files:**
- Modify: `src/Admin/PropertyListScreen.php`
- Modify: `templates/admin/property-list.php` (simplify to use FilterTable)
- Modify: `src/Plugin.php` (register AJAX, enqueue assets)

### Task 4.1: Refactor PropertyListScreen to use FilterTable

- [ ] **Step 1: Review current PropertyListScreen.php**
  Note its current structure: it manages its own query engine, filter parsing, rendering, and AJAX handlers. We'll replace the query/filter layer with FilterEngine + FilterTable while keeping the existing template's row rendering, service modal, and stats.

- [ ] **Step 2: Define the column configuration**

Add a static method to PropertyListScreen:

```php
public static function get_column_config(): array {
    return [
        'columns' => [
            'id' => [
                'label'    => __( 'ID', 'ovr-core' ),
                'sortable' => true,
                'filter'   => [ 'type' => 'text', 'placeholder' => 'ID #' ],
            ],
            'display_status' => [
                'label'  => __( 'Display', 'ovr-core' ),
                'filter' => [
                    'type'    => 'dropdown',
                    'options' => [
                        'approved'      => __( 'Visible', 'ovr-core' ),
                        'hidden'        => __( 'Hidden', 'ovr-core' ),
                        'suspended'     => __( 'Suspended', 'ovr-core' ),
                        'pending_review'=> __( 'Pending Review', 'ovr-core' ),
                    ],
                ],
            ],
            'owner_status' => [
                'label'  => __( 'Owner', 'ovr-core' ),
                'filter' => [
                    'type'    => 'dropdown',
                    'options' => [
                        'active'   => __( 'Active', 'ovr-core' ),
                        'inactive' => __( 'Inactive', 'ovr-core' ),
                    ],
                ],
            ],
            'title' => [
                'label'    => __( 'Name', 'ovr-core' ),
                'sortable' => true,
                'filter'   => [ 'type' => 'text', 'placeholder' => 'Search name…' ],
            ],
            'price' => [
                'label'    => __( 'Price', 'ovr-core' ),
                'sortable' => true,
                'filter'   => [ 'type' => 'numeric', 'meta_key' => '_ovr_base_price' ],
            ],
            'type' => [
                'label'  => __( 'Type', 'ovr-core' ),
                'filter' => [ 'type' => 'dropdown', 'source' => 'taxonomy:ovr_property_type' ],
            ],
            'address' => [
                'label'  => __( 'Address', 'ovr-core' ),
                'filter' => [ 'type' => 'text', 'placeholder' => 'Search address…', 'meta_keys' => [ '_ovr_address', '_ovr_city' ] ],
            ],
            'village' => [
                'label'    => __( 'Village', 'ovr-core' ),
                'sortable' => true,
                'filter'   => [ 'type' => 'dropdown', 'source' => 'taxonomy:ovr_village' ],
            ],
            'email' => [
                'label'    => __( 'Owner Email', 'ovr-core' ),
                'sortable' => true,
                'filter'   => [ 'type' => 'text', 'placeholder' => 'Search email…' ],
            ],
            'date' => [
                'label'    => __( 'Updated', 'ovr-core' ),
                'sortable' => true,
                'filter'   => [ 'type' => 'date' ],
            ],
        ],
        'global_filters' => [
            'subscription' => [
                'type'   => 'dropdown',
                'label'  => __( 'Subscription Plan', 'ovr-core' ),
                'source' => 'class:' . \OVR\Subscription\Plans::class . '::get_plans',
            ],
            'paid_service' => [
                'type'   => 'dropdown',
                'label'  => __( 'Paid Service', 'ovr-core' ),
                'source' => 'table:ovr_paid_services|id|name',
            ],
        ],
        'query' => [
            'source'    => 'wp_query',
            'post_type' => self::PT,
        ],
        'per_page' => self::PER_PAGE,
    ];
}
```

- [ ] **Step 3: Simplify PropertyListScreen::render()**

Replace the complex render() body with:

```php
public function render(): void {
    $engine = new FilterEngine( self::get_column_config() );
    $table  = new FilterTable(
        $engine,
        self::PAGE_SLUG,
        'read',
        [
            'stats'        => $this->get_stats_for_display( current_user_can( 'manage_options' ) ? 0 : get_current_user_id() ),
            'row_callback' => [ $this, 'render_table_row' ],
            'service_types'=> $this->get_service_types(),
        ]
    );
    $table->render_page();
}
```

- [ ] **Step 4: Add row rendering callback**

```php
public function render_table_row( array $rows, array $vars ): void {
    $service_types = $vars['service_types'] ?? [];
    $is_admin      = current_user_can( 'manage_options' );
    ?>
    <tbody id="ovr-table-tbody">
    <?php foreach ( $rows as $post ) : setup_postdata( $post );
        $pid         = (int) $post->ID;
        $title       = get_the_title( $pid );
        $address     = (string) get_post_meta( $pid, '_ovr_address', true );
        $city        = (string) get_post_meta( $pid, '_ovr_city', true );
        $addr_disp   = $address ? $address . ( $city ? ', ' . $city : '' ) : ( $city ?: '—' );
        $price       = self::format_price( get_post_meta( $pid, '_ovr_base_price', true ) );
        $admin_status = get_post_meta( $pid, '_ovr_admin_status', true ) ?: 'approved';
        $owner_status = get_post_meta( $pid, '_ovr_listing_status', true ) ?: 'active';
        $villages    = wp_get_object_terms( $pid, 'ovr_village', [ 'fields' => 'names' ] );
        $village     = ! is_wp_error( $villages ) && $villages ? $villages[0] : '—';
        $types       = wp_get_object_terms( $pid, 'ovr_property_type', [ 'fields' => 'names' ] );
        $type_label  = ! is_wp_error( $types ) && $types ? $types[0] : '—';
        $owner       = get_userdata( (int) $post->post_author );
        $owner_email = $owner ? $owner->user_email : '';
        $updated     = $post->post_modified;
        $edit_url    = admin_url( 'admin.php?page=ovr-edit-listing&post=' . $pid );

        // Fetch active services for this listing.
        $services = $this->listing_services( $pid );
    ?>
    <tr data-listing-id="<?php echo (int) $pid; ?>">
        <td><input type="checkbox" class="ovr-pls-cb ovr-pls-listing-cb" value="<?php echo (int) $pid; ?>"></td>
        <td>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-pls-pid">#<?php echo (int) $pid; ?></a>
            <button type="button" class="ovr-pls-pid-copy" data-copy="<?php echo (int) $pid; ?>">
                <span class="material-symbols-outlined">content_copy</span>
            </button>
        </td>
        <td><?php echo self::display_status_icon( $admin_status ); ?></td>
        <td><?php echo self::owner_status_icon( $owner_status ); ?></td>
        <td><a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-pls-name"><?php echo esc_html( $title ?: '(' . __( 'no title', 'ovr-core' ) . ')' ); ?></a></td>
        <td><?php echo esc_html( $price ); ?></td>
        <td><?php echo esc_html( $type_label ); ?></td>
        <td><span class="ovr-pls-addr" title="<?php echo esc_attr( $addr_disp ); ?>"><?php echo esc_html( $addr_disp ); ?></span></td>
        <td><?php echo esc_html( $village ); ?></td>
        <td>
            <?php if ( $owner_email ) : ?>
            <a href="mailto:<?php echo esc_attr( $owner_email ); ?>" class="ovr-pls-email"><?php echo esc_html( $owner_email ); ?></a>
            <?php else : ?>
            <span>—</span>
            <?php endif; ?>
        </td>
        <td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $updated ) ); ?><br><?php echo esc_html( mysql2date( get_option( 'time_format' ), $updated ) ); ?></td>
        <?php if ( $is_admin ) : ?>
        <td>
            <div class="ovr-pls-service-badges">
                <?php foreach ( $services as $svc ) : ?>
                    <span class="ovr-pls-badge ovr-pls-badge--<?php echo esc_attr( $svc['service_slug'] ?? 'default' ); ?>">
                        <?php echo esc_html( $svc['badge'] ?: $svc['service_name'] ?: 'Service' ); ?>
                    </span>
                <?php endforeach; ?>
                <button type="button" class="ovr-pls-service-add" data-listing-id="<?php echo (int) $pid; ?>">
                    <span class="material-symbols-outlined">add</span>
                </button>
            </div>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; wp_reset_postdata(); ?>
    </tbody>
    <?php
}
```

- [ ] **Step 5: Keep existing helper methods and remove custom query infrastructure**

Keep: `format_price()`, `display_status_icon()`, `owner_status_icon()`, `listing_services()`, `get_service_types()`, `listing_ids_with_service()`, AJAX methods for service management, `expire_services()` cron.

Remove: `parse_request()`, `query_listings()`, `extend_search()`, `get_stats()` (can simplify), old `enqueue()` method (replace with FilterTable enqueue).

- [ ] **Step 6: Update init() to register new AJAX endpoint**

```php
public function init(): void {
    add_action( 'admin_menu', [ $this, 'register_page' ] );
    add_action( 'admin_menu', [ $this, 'remove_native_submenu' ], 20 );
    add_action( 'admin_init', [ $this, 'redirect_native_list' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

    // Initialize the FilterTable for AJAX handling.
    $engine = new FilterEngine( self::get_column_config() );
    $table  = new FilterTable( $engine, self::PAGE_SLUG, 'read' );
    $table->register_ajax();

    // AJAX: paid service assignment from the table modal.
    add_action( 'wp_ajax_ovr_admin_add_listing_service',  [ $this, 'ajax_add_service' ] );
    add_action( 'wp_ajax_ovr_admin_remove_listing_service', [ $this, 'ajax_remove_service' ] );
    add_action( 'wp_ajax_ovr_admin_get_services',  [ $this, 'ajax_get_services' ] );
    add_action( 'wp_ajax_ovr_admin_bulk_action',    [ $this, 'ajax_bulk_action' ] );

    // Daily cron: expire overdue listing services.
    add_action( 'ovr_service_expiry', [ self::class, 'expire_services' ] );
    if ( ! wp_next_scheduled( 'ovr_service_expiry' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ovr_service_expiry' );
    }
}
```

- [ ] **Step 7: Update enqueue**

```php
public function enqueue( string $hook ): void {
    if ( ( $_GET['page'] ?? '' ) !== self::PAGE_SLUG ) {
        return;
    }
    wp_enqueue_style( 'ovr-material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&family=Inter:wght@400;500;600;700&display=swap', [], null );
    $engine = new FilterEngine( self::get_column_config() );
    $table  = new FilterTable( $engine, self::PAGE_SLUG, 'read' );
    $table->enqueue_assets();
}
```

### Task 4.2: Update Plugin.php

- [ ] **Step 1: Add the filter table AJAX action registration**

The `ovr_filter_table` AJAX action is registered by `FilterTable::register_ajax()` which is called in `PropertyListScreen::init()`. No additional changes needed in Plugin.php — the AJAX registration happens within the module's init.

- [ ] **Step 2: Verify the enqueue path constant**

Check that `OVR_PLUGIN_URL` is defined and accessible. The FilterTable enqueue uses `OVR_PLUGIN_URL . 'assets/css/ovr-filter-table.css'`.

### Task 4.3: Simplify property-list.php template

- [ ] **Step 1: Retrofit the existing template**

The existing `property-list.php` template has ~770 lines of embedded HTML, CSS, and JS. Since `FilterTable::render_page()` now uses `templates/admin/filter-table.php` as the base, the old template can be simplified to just the row rendering callback content (the per-row logic) and the service modal if needed.

The service modal (with its JS) should remain since it's independent of the filtering system. It can be kept in the template and rendered alongside the filter table output.

- [ ] **Step 2: Keep the modal and service-badge styles, remove old filter/form code**

The old template's form-based filter submission, inline JS for filter handling, and column filter HTML should be removed since FilterTable now handles all of that.

---

## Verification

After Chunks 1-4 are complete:

- [ ] **Run `php -l` on all new/modified PHP files**
- [ ] **Test page load**: Visit `admin.php?page=ovr-properties` — verify page renders with column filters above each column
- [ ] **Test text filter**: Type in the Name filter — verify debounced AJAX updates the table
- [ ] **Test dropdown filter**: Change Display Status dropdown — verify instant AJAX update
- [ ] **Test numeric filter**: Set Price ≥ 1000 — verify table updates
- [ ] **Test date filter**: Select a date range — verify table updates
- [ ] **Test history back/forward**: Apply filters, navigate away, come back — filters should restore
- [ ] **Test reset**: Clear all filters — table returns to full dataset
- [ ] **Test permissions**: Non-admin user should only see their own listings
- [ ] **Test service modal**: Add/remove paid service — verify it still works
- [ ] **Test bulk actions**: Select listings, apply bulk action — verify it works
