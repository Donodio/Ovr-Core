<?php
/**
 * Featured Listings Template.
 *
 * @var WP_Query $query
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Property\PropertyCard;
?>
<div class="ovr-wrap">
    <div class="ovr-container ovr-section">

        <div style="text-align:center;max-width:640px;margin:0 auto 48px">
            <p class="ovr-label-caps" style="color:var(--ovr-tertiary-container);margin-bottom:8px"><?php esc_html_e( 'HAND-PICKED', 'ovr-core' ); ?></p>
            <h1 class="ovr-h1" style="margin-bottom:16px"><?php esc_html_e( 'Featured Properties', 'ovr-core' ); ?></h1>
            <p class="ovr-body-lg" style="color:var(--ovr-on-surface-variant)">
                <?php esc_html_e( 'Our curated selection of premium rental properties across the most desirable village destinations.', 'ovr-core' ); ?>
            </p>
        </div>

        <?php if ( $query->have_posts() ) : ?>
            <div class="ovr-grid ovr-grid-3" id="ovr-featured-grid">
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <?php echo PropertyCard::render_grid( get_the_ID() ); ?>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <div style="text-align:center;padding:80px 24px">
                <span class="material-symbols-outlined" style="font-size:64px;color:var(--ovr-outline-variant)">villa</span>
                <h3 class="ovr-h3" style="margin:16px 0 8px"><?php esc_html_e( 'No Featured Properties Yet', 'ovr-core' ); ?></h3>
                <p class="ovr-body-md" style="color:var(--ovr-on-surface-variant)">
                    <?php esc_html_e( 'Check back soon — we\'re always adding new premium listings.', 'ovr-core' ); ?>
                </p>
            </div>
        <?php endif; ?>

    </div>
</div>
