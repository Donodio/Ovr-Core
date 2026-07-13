<?php

namespace OVR\Admin\FilterTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BooleanFilter implements FilterTypeInterface {
    public function render( string $key, array $config, $value ): string {
        $current = (string) ( $value ?? '' );
        $ekey    = esc_attr( $key );

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
