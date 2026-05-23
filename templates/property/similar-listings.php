<?php
/**
 * Similar Listings.
 *
 * Shown at the bottom of a single property page. Reuses PropertyCard.
 *
 * @package OVR
 *
 * @var WP_Query|null $similar  Pre-fetched similar properties query.
 * @var int           $post_id  Source property ID (excluded from results).
 * @var int           $limit    Max cards to show. Default 3.
 * @var string        $heading  Optional. Section heading override.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Property\PropertyCard;
use OVR\Property\PropertyQuery;

$post_id = $post_id ?? 0;
$limit   = max( 1, absint( $limit ?? 3 ) );
$heading = $heading ?? __( 'Similar homes you may like', 'ovr-core' );
$similar = $similar ?? PropertyQuery::get_similar( $post_id, $limit + 1 );

if ( ! $similar instanceof \WP_Query || ! $similar->have_posts() ) {
    return;
}

$rendered = 0;
?>
<section class="ovr-section" style="background:var(--ovr-surface-container-low);border-top:1px solid var(--ovr-outline-variant)">
    <div class="ovr-container">
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:32px;flex-wrap:wrap;gap:16px">
            <h2 class="ovr-h2" style="margin:0">
                <?php echo esc_html( $heading ); ?>
            </h2>
            <a href="<?php echo esc_url( \OVR\Core\Pages::get_page_url( 'ovr_page_search' ) ); ?>"
               style="font-weight:600;text-decoration:underline;color:var(--ovr-primary)">
                <?php esc_html_e( 'View more', 'ovr-core' ); ?>
            </a>
        </div>

        <div class="ovr-grid ovr-grid-3">
            <?php while ( $similar->have_posts() && $rendered < $limit ) :
                $similar->the_post();
                $current_id = get_the_ID();

                // Skip the source property if it appears.
                if ( $current_id === $post_id ) {
                    continue;
                }

                echo PropertyCard::render_grid( $current_id );
                $rendered++;
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
    </div>
</section>
