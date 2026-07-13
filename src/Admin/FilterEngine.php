<?php

namespace OVR\Admin;

use OVR\Admin\FilterTypes\FilterTypeInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FilterEngine {
    private array $column_filters = [];
    private array $filter_types   = [];
    private string $screen_id     = '';

    public function __construct( string $screen_id ) {
        $this->screen_id = $screen_id;
        $this->register_default_types();
    }

    private function register_default_types(): void {
        $defaults = [
            'text'    => new FilterTypes\TextFilter(),
            'dropdown' => new FilterTypes\DropdownFilter(),
            'date'    => new FilterTypes\DateFilter(),
            'numeric' => new FilterTypes\NumericFilter(),
            'boolean' => new FilterTypes\BooleanFilter(),
        ];
        /**
         * Filter the available filter types for a given screen.
         *
         * @param array  $defaults Filter type instances keyed by type name.
         * @param string $screen_id Current screen ID.
         */
        $this->filter_types = apply_filters( 'ovr_filter_types', $defaults, $this->screen_id );
    }

    public function register_filter_type( string $name, FilterTypeInterface $instance ): void {
        $this->filter_types[ $name ] = $instance;
    }

    /**
     * Define a filter for a column.
     *
     * @param string $key    Column key.
     * @param array  $config {
     *     Filter configuration block.
     *
     *     @type string $type        Required. One of: text, dropdown, date, numeric, boolean.
     *     @type string $label       Column label.
     *     @type string $placeholder Placeholder text (text/dropdown).
     *     @type array  $options     Static options array (dropdown).
     *     @type string $source      Dynamic source: taxonomy:{tax} | class::{Class}::{method} | table:{table}|{id_col}|{name_col}
     *     @type string $meta_key    Custom meta key (defaults to _ovr_{$key}).
     *     @type array  $meta_keys   Multiple meta keys for OR search (text).
     *     @type bool   $multiple    Enable multiple select (dropdown).
     * }
     */
    public function add_column_filter( string $key, array $config ): void {
        $this->column_filters[ $key ] = $config;
    }

    public function get_column_filters(): array {
        return $this->column_filters;
    }

    /**
     * Render filter controls for all registered columns.
     *
     * @param array $values Current filter values keyed by column key.
     * @return string HTML.
     */
    public function render_controls( array $values = [] ): string {
        $html = '<div class="ovr-filters-row">';
        foreach ( $this->column_filters as $key => $config ) {
            $type  = $config['type'] ?? 'text';
            $label = $config['label'] ?? $key;
            $value = $values[ $key ] ?? '';

            $html .= sprintf(
                '<div class="ovr-filter-cell" data-column="%s" data-filter-type="%s">',
                esc_attr( $key ),
                esc_attr( $type )
            );
            $html .= sprintf(
                '<label class="ovr-filter-label">%s</label>',
                esc_html( $label )
            );

            if ( isset( $this->filter_types[ $type ] ) ) {
                $html .= $this->filter_types[ $type ]->render( $key, $config, $value );
            } else {
                $html .= sprintf(
                    '<span class="ovr-ft-error">%s</span>',
                    esc_html__( 'Unknown filter type', 'ovr-core' )
                );
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Process raw input data (from GET or AJAX POST) into normalized filter values.
     *
     * @param array $input Raw input (e.g., $_GET).
     * @return array Normalized filter values keyed by column key.
     */
    public function process_input( array $input ): array {
        $values = [];
        foreach ( $this->column_filters as $key => $config ) {
            if ( ! isset( $input[ $key ] ) ) {
                continue;
            }
            $type = $config['type'] ?? 'text';
            $raw  = $input[ $key ];
            switch ( $type ) {
                case 'text':
                    $values[ $key ] = sanitize_text_field( wp_unslash( $raw ) );
                    break;
                case 'dropdown':
                    $values[ $key ] = sanitize_text_field( wp_unslash( $raw ) );
                    break;
                case 'boolean':
                    $values[ $key ] = in_array( (string) $raw, [ '0', '1' ], true ) ? (string) $raw : '';
                    break;
                case 'numeric':
                    $values[ $key ] = $this->process_numeric_input( $raw );
                    break;
                case 'date':
                    $values[ $key ] = $this->process_date_input( $raw );
                    break;
                default:
                    $values[ $key ] = apply_filters( 'ovr_process_filter_input', $raw, $type, $key, $this->screen_id );
                    break;
            }
        }
        return $values;
    }

    private function process_numeric_input( $raw ): array {
        if ( ! is_array( $raw ) ) {
            $raw = [ 'op' => '=', 'val' => $raw ];
        }
        $sanitized = [
            'op'   => in_array( $raw['op'] ?? '=', [ '=', '>', '<', '>=', '<=', 'bt' ], true ) ? $raw['op'] : '=',
            'val'  => isset( $raw['val'] ) ? sanitize_text_field( wp_unslash( $raw['val'] ) ) : '',
            'val2' => isset( $raw['val2'] ) ? sanitize_text_field( wp_unslash( $raw['val2'] ) ) : '',
        ];
        return $sanitized;
    }

    private function process_date_input( $raw ): array {
        if ( ! is_array( $raw ) ) {
            $raw = [ 'from' => '', 'to' => '' ];
        }
        return [
            'from' => isset( $raw['from'] ) ? sanitize_text_field( wp_unslash( $raw['from'] ) ) : '',
            'to'   => isset( $raw['to'] ) ? sanitize_text_field( wp_unslash( $raw['to'] ) ) : '',
            'on'   => isset( $raw['on'] ) ? sanitize_text_field( wp_unslash( $raw['on'] ) ) : '',
        ];
    }

    /**
     * Render a single column filter control.
     *
     * @param string $key   Column key.
     * @param mixed  $value Current filter value.
     * @return string HTML.
     */
    public function render_single_control( string $key, $value = '' ): string {
        $config = $this->column_filters[ $key ] ?? null;
        if ( ! $config ) {
            return '';
        }
        $type = $config['type'] ?? 'text';
        if ( isset( $this->filter_types[ $type ] ) ) {
            return $this->filter_types[ $type ]->render( $key, $config, $value );
        }
        return '';
    }

    /**
     * Apply filter values to a QueryBuilder.
     *
     * @param array        $values Normalized filter values.
     * @param QueryBuilder $query  The query builder.
     */
    public function apply_filters( array $values, QueryBuilder $query ): void {
        foreach ( $this->column_filters as $key => $config ) {
            if ( ! isset( $values[ $key ] ) || '' === $values[ $key ] || [] === $values[ $key ] ) {
                continue;
            }
            $type = $config['type'] ?? 'text';
            $val  = $values[ $key ];

            if ( 'date' === $type && empty( $val['from'] ) && empty( $val['to'] ) && empty( $val['on'] ) ) {
                continue;
            }
            if ( 'numeric' === $type && '' === ( $val['val'] ?? '' ) && 'bt' !== ( $val['op'] ?? '=' ) ) {
                continue;
            }
            if ( 'numeric' === $type && 'bt' === ( $val['op'] ?? '=' ) && ( '' === ( $val['val'] ?? '' ) || '' === ( $val['val2'] ?? '' ) ) ) {
                continue;
            }

            if ( isset( $this->filter_types[ $type ] ) ) {
                $this->filter_types[ $type ]->apply_to_query( $key, $val, $config, $query );
            }
        }
    }
}
