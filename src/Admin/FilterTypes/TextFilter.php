<?php

namespace OVR\Admin\FilterTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TextFilter implements FilterTypeInterface {
    public function render( string $key, array $config, $value ): string {
        $val = esc_attr( (string) $value );
        return sprintf(
            '<input type="text" class="ovr-ft-input" data-filter-key="%s" value="%s" placeholder="%s" autocomplete="off">',
            esc_attr( $key ),
            $val,
            esc_attr__( 'Filter…', 'ovr-core' )
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