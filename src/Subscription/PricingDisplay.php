<?php
/**
 * Pricing Display.
 *
 * Renders the pricing plans page via shortcode.
 *
 * @package OVR\Subscription
 * @since   1.0.0
 */

namespace OVR\Subscription;

use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PricingDisplay {

    public function init(): void {}

    /**
     * Render the pricing-plans page.
     *
     * Accepts the following display options (passed by the shortcode):
     *
     * @param array $args {
     *   @type int    $columns         1-5 grid columns. Default 0 (auto-fit).
     *   @type string $layout          'cards' (default) | 'list' | 'compact'.
     *   @type int    $limit           Cap on plans rendered. 0 = all. Default 0.
     *   @type bool   $featured_first  Sort the popular plan first. Default false.
     *   @type bool   $show_compare    Show the comparison table below. Default true.
     *   @type bool   $show_promo      Show the promo-code field. Default true.
     *   @type string $only            CSV of plan slugs to include. Empty = all.
     *   @type string $exclude         CSV of plan slugs to hide.
     * }
     */
    public static function render( array $args = [] ): string {
        $args = wp_parse_args( $args, [
            'columns'        => 0,
            'layout'         => 'cards',
            'limit'          => 0,
            'featured_first' => false,
            'show_compare'   => true,
            'show_promo'     => true,
            'only'           => '',
            'exclude'        => '',
        ] );

        $plans = Plans::get_plans();

        // Drop inactive plans.
        $plans = array_filter( $plans, fn( $p ) => ! empty( $p['is_active'] ) || ! isset( $p['is_active'] ) );

        // Whitelist / blacklist by slug.
        $only    = array_filter( array_map( 'sanitize_key', explode( ',', (string) $args['only'] ) ) );
        $exclude = array_filter( array_map( 'sanitize_key', explode( ',', (string) $args['exclude'] ) ) );
        if ( $only ) {
            $plans = array_intersect_key( $plans, array_flip( $only ) );
        }
        if ( $exclude ) {
            $plans = array_diff_key( $plans, array_flip( $exclude ) );
        }

        // Sort.
        uasort( $plans, fn( $a, $b ) => ( $a['sort_order'] ?? 0 ) <=> ( $b['sort_order'] ?? 0 ) );
        if ( ! empty( $args['featured_first'] ) ) {
            uasort( $plans, fn( $a, $b ) => ( ! empty( $b['is_popular'] ) ? 1 : 0 ) <=> ( ! empty( $a['is_popular'] ) ? 1 : 0 ) );
        }

        // Limit.
        $limit = (int) $args['limit'];
        if ( $limit > 0 ) {
            $plans = array_slice( $plans, 0, $limit, true );
        }

        return TemplateLoader::get_rendered( 'pages/pricing-plans.php', [
            'plans'          => $plans,
            'columns'        => max( 0, min( 5, (int) $args['columns'] ) ),
            'layout'         => in_array( $args['layout'], [ 'cards', 'list', 'compact' ], true ) ? $args['layout'] : 'cards',
            'show_compare'   => (bool) $args['show_compare'],
            'show_promo'     => (bool) $args['show_promo'],
        ] );
    }
}
