<?php
/**
 * Homepage Renderer.
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Property\PropertyQuery;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Homepage {

    public function init(): void {}

    public static function render(): string {
        $featured = PropertyQuery::get_featured( 4 );

        $villages = get_terms( [
            'taxonomy'   => 'ovr_village',
            'hide_empty' => false,
            'number'     => 6,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );

        return TemplateLoader::get_rendered( 'pages/homepage.php', [
            'featured_properties' => $featured,
            'villages'            => ! is_wp_error( $villages ) ? $villages : [],
        ] );
    }
}
