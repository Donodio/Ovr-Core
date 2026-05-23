<?php
namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Search\SearchHandler;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SearchResults {
    public function init(): void {}
    public static function render(): string {
        return SearchHandler::render();
    }
}
