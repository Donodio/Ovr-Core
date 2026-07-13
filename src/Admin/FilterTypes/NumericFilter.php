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
