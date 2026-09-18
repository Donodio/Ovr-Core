<?php
/**
 * Browse-by-Village-Section shortcut page.
 *
 * Mirrors the search-results chip strip exactly (same terms, same images)
 * but as large 2-across cards: "All Areas" first, then one card per
 * ovr_village term linking to a single-section filtered search.
 *
 * @package OVR\Frontend
 */

namespace OVR\Frontend;

use OVR\Core\Pages;
use OVR\Core\TemplateLoader;
use OVR\Search\SearchFilters;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VillageSections {

	public function init(): void {}

	/**
	 * @return array{all:array{name:string,image:string,url:string}, sections:array<int, array{name:string,image:string,url:string,count:int}>}
	 */
	public static function sections_data(): array {
		$search = Pages::get_page_url( 'ovr_page_search' );

		$all_img = SearchFilters::get_all_villages_image();

		$sections = [];
		foreach ( SearchFilters::get_villages() as $term ) {
			$slug_lc = strtolower( (string) $term->slug );
			$name_lc = strtolower( trim( (string) $term->name ) );
			if ( in_array( $slug_lc, [ 'all-villages', 'all-areas', 'the-villages' ], true )
				|| in_array( $name_lc, [ 'all villages', 'all areas', 'the villages' ], true ) ) {
				continue;
			}
			$link = add_query_arg( [ 'village_section' => [ $term->slug ] ], $search );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$sections[] = [
				'name'  => $term->name,
				'image' => SearchFilters::get_village_image( $term ),
				'url'   => $link,
				'count' => (int) $term->count,
			];
		}

		return [
			'all'       => [ 'name' => __( 'All Areas', 'ovr-core' ), 'image' => $all_img, 'url' => $search ],
			'sections'  => $sections,
		];
	}

	public static function render(): string {
		return TemplateLoader::get_rendered( 'pages/village-sections.php', self::sections_data() );
	}
}
