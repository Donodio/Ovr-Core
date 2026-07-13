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
