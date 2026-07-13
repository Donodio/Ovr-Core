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
