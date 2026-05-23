<?php
/**
 * Single Property Renderer.
 *
 * Loads property data and renders the templates/property/single.php template.
 * Used by both the [ovr_single_property] shortcode (if registered) and as a
 * fallback when called outside the template_include intercept.
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Property\PropertyMeta;
use OVR\Property\SeasonalPricing;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SingleProperty {

    public function init(): void {}

    /**
     * Render a single property page (returns HTML for shortcode use).
     */
    public static function render( int $post_id = 0 ): string {
        if ( ! $post_id ) {
            $post_id = (int) get_the_ID();
        }
        if ( ! $post_id || 'ovr_property' !== get_post_type( $post_id ) ) {
            return '';
        }

        $meta    = PropertyMeta::get_all( $post_id );
        $pricing = SeasonalPricing::get_pricing( $post_id );
        $gallery = self::get_gallery( $post_id );

        // Similar Homes intentionally omitted from the listing detail (DESIGN.md §10).
        return TemplateLoader::get_rendered( 'property/single.php', [
            'post_id' => $post_id,
            'meta'    => $meta,
            'pricing' => $pricing,
            'gallery' => $gallery,
        ] );
    }

    /**
     * Resolve a property's gallery as an array of attachment IDs.
     */
    private static function get_gallery( int $post_id ): array {
        $ids_string = (string) get_post_meta( $post_id, '_ovr_gallery_ids', true );
        if ( '' !== $ids_string ) {
            return array_values( array_filter( array_map( 'absint', explode( ',', $ids_string ) ) ) );
        }
        $thumb = get_post_thumbnail_id( $post_id );
        return $thumb ? [ (int) $thumb ] : [];
    }
}
