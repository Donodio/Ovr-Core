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

		// "All Areas" tile — stone-wall banner fallback wins over the generic
		// ovr-placeholder.jpg (get_village_image never returns ''), but a real
		// assigned term image still takes precedence.
		$all_img = OVR_PLUGIN_URL . 'assets/images/the-villages-banner.svg';
		$all_term = get_term_by( 'slug', 'the-villages', 'ovr_village' );
		if ( $all_term && ! is_wp_error( $all_term ) ) {
			$img = SearchFilters::get_village_image( $all_term );
			if ( '' !== $img && $img !== OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg' ) {
				$all_img = $img;
			}
		}

		$sections = [];
		foreach ( SearchFilters::get_villages() as $term ) {
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
