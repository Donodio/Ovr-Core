<?php
namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Property\PropertyQuery;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VillagePage {
    public function init(): void {}

    public static function render( string $village_slug = '' ): string {
        if ( empty( $village_slug ) ) {
            $village_slug = get_query_var( 'ovr_village', '' );
        }

        $term = get_term_by( 'slug', $village_slug, 'ovr_village' );
        if ( ! $term || is_wp_error( $term ) ) {
            return '<p>' . esc_html__( 'Village not found.', 'ovr-core' ) . '</p>';
        }

        $query = PropertyQuery::query( [
            'village'  => [ $village_slug ],
            'per_page' => 12,
        ] );

        return TemplateLoader::get_rendered( 'pages/village-landing.php', [
            'village' => $term,
            'query'   => $query,
        ] );
    }
}
