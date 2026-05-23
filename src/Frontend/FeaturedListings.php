<?php
namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Property\PropertyQuery;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FeaturedListings {
    public function init(): void {}

    public static function render(): string {
        $query = PropertyQuery::get_featured( 12 );
        return TemplateLoader::get_rendered( 'pages/featured-listings.php', [
            'query' => $query,
        ] );
    }
}
