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
     *
     * @param int    $post_id Property ID.
     * @param string $ref     Optional. Current filtered/paged results URL to
     *                        stamp onto the card links as ?ovr_ref= so the
     *                        listing's "Back To Search Results" returns here.
     */
    public static function render_list( int $post_id, string $ref = '' ): string {
        $data = self::get_card_data( $post_id );
        $data['permalink'] = self::with_ref( $data['permalink'], $ref );
        return TemplateLoader::get_rendered( 'property/card-list.php', $data );
    }

    /**
     * Stamp the current results URL onto a listing permalink so the listing's
     * back link can restore the exact filtered + paginated view.
     */
    private static function with_ref( string $permalink, string $ref ): string {
        if ( '' === $ref ) {
            return $permalink;
        }
        // add_query_arg does not URL-encode values, so pre-encode the ref URL.
        return add_query_arg( 'ovr_ref', rawurlencode( $ref ), $permalink );
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
     * @param int    $post_id          Property ID.
     * @param bool   $featured_variant Render the featured (gold) treatment.
     * @param string $ref              Optional. Current filtered/paged results
     *                                 URL stamped onto the card links as
     *                                 ?ovr_ref= for the listing's back link.
     */
    public static function render_search( int $post_id, bool $featured_variant = false, string $ref = '' ): string {
        $data                     = self::get_card_data( $post_id );
        $data['permalink']        = self::with_ref( $data['permalink'], $ref );
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

        // Prefer the specific Village Name (free text); fall back to the Village
        // Section taxonomy term for the location chip.
        $villages = wp_get_post_terms( $post_id, 'ovr_village', [ 'fields' => 'names' ] );
        $section  = ! empty( $villages ) && ! is_wp_error( $villages ) ? $villages[0] : '';
        $village  = ! empty( $meta['village_name'] ) ? (string) $meta['village_name'] : $section;

        $types         = wp_get_post_terms( $post_id, 'ovr_property_type', [ 'fields' => 'names' ] );
        $property_type = ! empty( $types ) && ! is_wp_error( $types ) ? $types[0] : '';

        $rentals     = wp_get_post_terms( $post_id, 'ovr_rental_type', [ 'fields' => 'names' ] );
        $rental_type = ! empty( $rentals ) && ! is_wp_error( $rentals ) ? $rentals[0] : '';

        // Resolve the thumbnail URL *and* its real pixel dimensions. The list
        // card sets these as the <img> width/height so the browser reserves the
        // correct aspect-ratio space before a lazy image loads (no collapse).
        $thumb_id  = get_post_thumbnail_id( $post_id );
        $thumb_src = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'large' ) : false;
        if ( $thumb_src ) {
            $thumbnail = $thumb_src[0];
            $thumb_w   = (int) $thumb_src[1];
            $thumb_h   = (int) $thumb_src[2];
        } else {
            $thumbnail = OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';
            $thumb_w   = 1200; // placeholder is 4:3
            $thumb_h   = 900;
        }

        $excerpt = get_the_excerpt( $post_id );
        if ( ! $excerpt ) {
            $excerpt = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 24, '…' );
        }

        return [
            'post_id'     => $post_id,
            'title'       => get_the_title( $post_id ),
            'permalink'   => get_permalink( $post_id ),
            'thumbnail'   => $thumbnail,
            'thumb_w'     => $thumb_w,
            'thumb_h'     => $thumb_h,
            'village'     => $village,
            'property_type' => $property_type,
            'rental_type' => $rental_type,
            'bedrooms'    => $meta['bedrooms'],
            'bathrooms'   => $meta['bathrooms'],
            'max_guests'  => $meta['max_guests'],
            'sqft'        => $meta['sqft'],
            'base_price'  => $meta['base_price'],
            'has_pricing' => ! empty( SeasonalPricing::get_pricing( $post_id ) ),
            'rating_avg'  => $meta['rating_avg'],
            'rating_count'=> $meta['rating_count'],
            'is_featured' => (bool) $meta['is_featured'],
            'is_bumped'   => (bool) $meta['is_bumped'],
            'pets_allowed'=> (bool) $meta['pets_allowed'],
            'excerpt'     => $excerpt,
            // Feature B: flag a video so cards can show a play indicator over the
            // poster (a video supersedes images as the listing's primary media).
            'has_video'   => ! empty( $meta['video_id'] ) || '' !== (string) ( $meta['video_url'] ?? '' ),
        ];
    }
}
