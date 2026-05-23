<?php
/**
 * Property Card Renderer.
 *
 * @package OVR\Property
 * @since   1.0.0
 */

namespace OVR\Property;

use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PropertyCard {

    /**
     * Render a property card (grid view).
     */
    public static function render_grid( int $post_id, array $options = [] ): string {
        $data            = self::get_card_data( $post_id );
        $data['options'] = wp_parse_args( $options, self::default_card_options() );
        return TemplateLoader::get_rendered( 'property/card.php', $data );
    }

    /**
     * Default visibility flags for the grid card. Every element shows unless a
     * caller (e.g. the Elementor widget Style tab) turns it off.
     *
     * @return array<string,bool>
     */
    public static function default_card_options(): array {
        return [
            'show_favorite'       => true,
            'show_featured_badge' => true,
            'show_id'             => true,
            'show_compare'        => true,
            'show_location'       => true,
            'show_stats'          => true,
            'show_rates'          => true,
            'show_button'         => true,
        ];
    }

    /**
     * Render a property card (list view).
     */
    public static function render_list( int $post_id ): string {
        return TemplateLoader::get_rendered( 'property/card-list.php', self::get_card_data( $post_id ) );
    }

    /**
     * Render a property card for the Search Results "Stitch" redesign.
     *
     * Two variants share one fixed-height template so the featured rail and
     * the results grid line up row-for-row:
     *   - standard  → yellow card, navy border, single "Details" CTA.
     *   - featured  → white card, gold border + "FEATURED LISTING" banner,
     *                 rating stars, and a gold "Inquire" CTA.
     *
     * @param int  $post_id          Property ID.
     * @param bool $featured_variant Render the featured (gold) treatment.
     */
    public static function render_search( int $post_id, bool $featured_variant = false ): string {
        $data                     = self::get_card_data( $post_id );
        $data['featured_variant'] = $featured_variant;

        $settings       = get_option( 'ovr_settings', [] );
        $data['symbol'] = $settings['currency_symbol'] ?? '$';

        return TemplateLoader::get_rendered( 'property/card-search.php', $data );
    }

    /**
     * Prepare card data for a property.
     */
    public static function get_card_data( int $post_id ): array {
        $meta = PropertyMeta::get_all( $post_id );

        $villages = wp_get_post_terms( $post_id, 'ovr_village', [ 'fields' => 'names' ] );
        $village  = ! empty( $villages ) && ! is_wp_error( $villages ) ? $villages[0] : '';

        $types         = wp_get_post_terms( $post_id, 'ovr_property_type', [ 'fields' => 'names' ] );
        $property_type = ! empty( $types ) && ! is_wp_error( $types ) ? $types[0] : '';

        $rentals     = wp_get_post_terms( $post_id, 'ovr_rental_type', [ 'fields' => 'names' ] );
        $rental_type = ! empty( $rentals ) && ! is_wp_error( $rentals ) ? $rentals[0] : '';

        $thumbnail = get_the_post_thumbnail_url( $post_id, 'large' ) ?: OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';

        $excerpt = get_the_excerpt( $post_id );
        if ( ! $excerpt ) {
            $excerpt = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 24, '…' );
        }

        return [
            'post_id'     => $post_id,
            'title'       => get_the_title( $post_id ),
            'permalink'   => get_permalink( $post_id ),
            'thumbnail'   => $thumbnail,
            'village'     => $village,
            'property_type' => $property_type,
            'rental_type' => $rental_type,
            'bedrooms'    => $meta['bedrooms'],
            'bathrooms'   => $meta['bathrooms'],
            'max_guests'  => $meta['max_guests'],
            'sqft'        => $meta['sqft'],
            'base_price'  => $meta['base_price'],
            'rating_avg'  => $meta['rating_avg'],
            'rating_count'=> $meta['rating_count'],
            'is_featured' => (bool) $meta['is_featured'],
            'is_bumped'   => (bool) $meta['is_bumped'],
            'pets_allowed'=> (bool) $meta['pets_allowed'],
            'excerpt'     => $excerpt,
        ];
    }
}
