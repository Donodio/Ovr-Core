<?php

namespace OVR\Admin\FilterTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DateFilter implements FilterTypeInterface {
    public function render( string $key, array $config, $value ): string {
        $ekey = esc_attr( $key );

        // Single mode: one date picker that matches that exact day.
        if ( ! empty( $config['single'] ) ) {
            $on = esc_attr( $value['on'] ?? '' );
            return sprintf(
                '<div class="ovr-ft-date-group ovr-ft-date-single" data-filter-key="%s">'
                . '<input type="date" class="ovr-ft-date-on" name="%s[on]" value="%s">'
                . '</div>',
                $ekey,
                $ekey, $on
            );
        }

        $from = esc_attr( $value['from'] ?? '' );
        $to   = esc_attr( $value['to'] ?? '' );

        return sprintf(
            '<div class="ovr-ft-date-group" data-filter-key="%s">'
            . '<input type="date" class="ovr-ft-date-from" name="%s[from]" value="%s" placeholder="%s">'
            . '<input type="date" class="ovr-ft-date-to" name="%s[to]" value="%s" placeholder="%s">'
            . '</div>',
            $ekey,
            $ekey, $from, esc_attr__( 'From', 'ovr-core' ),
            $ekey, $to, esc_attr__( 'To', 'ovr-core' )
        );
    }

    public function apply_to_query( string $key, $value, array $config, $query ): void {
        $from   = $value['from'] ?? '';
        $to     = $value['to'] ?? '';
        $on     = $value['on'] ?? '';
        $column = $config['column'] ?? '';

        // Single mode: match the whole calendar day.
        if ( '' !== $on ) {
            $parts = explode( '-', $on );
            if ( 3 === count( $parts ) ) {
                $clause = [
                    'year'  => (int) $parts[0],
                    'month' => (int) $parts[1],
                    'day'   => (int) $parts[2],
                ];
                if ( '' !== $column ) {
                    $clause['column'] = $column;
                }
                $query->add_date_query( [ $clause ] );
            }
            return;
        }

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
        if ( '' !== $column ) {
            $date_query['column'] = $column;
        }
        if ( ! empty( $date_query ) ) {
            $query->add_date_query( [ $date_query ] );
        }
    }
}