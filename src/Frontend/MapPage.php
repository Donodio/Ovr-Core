<?php
/**
 * Map Page.
 *
 * Renders the standalone, full-width clustered map of every published listing.
 * Powers the [ovr_map] shortcode and the /map/ page (see OVR\Core\Pages).
 *
 * Reuses the same Leaflet wiring as the search "Map" view: the front-end JS
 * (assets/js/ovr-search.js) looks for a `.ovr-map-view[data-ovr-map]` element
 * and plots a clustered marker per point. No filters sidebar — this is a clean
 * "everything on a map" destination.
 *
 * @package OVR\Frontend
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Property\PropertyQuery;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MapPage {

    public function init(): void {}

    public static function render(): string {
        $points   = PropertyQuery::get_map_points( [] );
        $settings = get_option( 'ovr_settings', [] );
        $symbol   = $settings['currency_symbol'] ?? '$';

        return TemplateLoader::get_rendered( 'map/full-map.php', [
            'points' => $points,
            'symbol' => $symbol,
        ] );
    }
}
