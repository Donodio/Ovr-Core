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
use OVR\Property\SeasonalPricing;

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
            'slider_cards'        => self::slider_cards( 3 ),
            'villages'            => ! is_wp_error( $villages ) ? $villages : [],
        ] );
    }

    /**
     * Real card data for the homepage "Featured Rentals" rail, drawn from
     * listings with an active Homepage Slider boost (falling back to Featured).
     * Returns [] when nobody has a boost — the template then shows its static
     * placeholder cards so the section is never empty.
     *
     * @return array<int, array<string, string>>
     */
    private static function slider_cards( int $count = 3 ): array {
        $q     = PropertyQuery::get_slider( $count );
        $cards = [];

        foreach ( (array) $q->posts as $post ) {
            $pid     = (int) $post->ID;
            $beds    = (int) get_post_meta( $pid, '_ovr_bedrooms', true );
            $baths_r = (float) get_post_meta( $pid, '_ovr_bathrooms', true );
            $baths   = rtrim( rtrim( number_format( $baths_r, 1 ), '0' ), '.' );

            $types = wp_get_post_terms( $pid, 'ovr_property_type', [ 'fields' => 'names' ] );
            $type  = ( ! is_wp_error( $types ) && ! empty( $types ) ) ? $types[0] : '';

            $detail_bits = array_filter( [
                $beds ? sprintf( _n( '%d Bed', '%d Beds', $beds, 'ovr-core' ), $beds ) : '',
                $baths ? sprintf( __( '%s Bath', 'ovr-core' ), $baths ) : '',
                $type,
            ] );

            $village = (string) get_post_meta( $pid, '_ovr_village_name', true );

            $cards[] = [
                'image'        => get_the_post_thumbnail_url( $pid, 'large' ) ?: OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg',
                'title'        => $post->post_title ?: __( 'Village Rental', 'ovr-core' ),
                'id'           => (string) $pid,
                'details'      => implode( ' • ', $detail_bits ),
                'availability' => $village ? sprintf( __( 'Village of %s', 'ovr-core' ), $village ) : __( 'Owner-Direct Rental', 'ovr-core' ),
                'price'        => SeasonalPricing::price_summary( $pid ),
                'permalink'    => get_permalink( $pid ),
            ];
        }

        wp_reset_postdata();
        return $cards;
    }
}
