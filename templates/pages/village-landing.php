<?php
/**
 * Village Landing Page Template.
 *
 * @var WP_Term  $village
 * @var WP_Query $query
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Property\PropertyCard;
?>
<div class="ovr-wrap">

    <!-- Village Hero -->
    <div class="ovr-hero" style="min-height:400px">
        <div class="ovr-hero-overlay" style="background:linear-gradient(to bottom,rgba(0,76,76,0.6),rgba(0,76,76,0.3))"></div>
        <div class="ovr-hero-content">
            <p class="ovr-label-caps" style="color:var(--ovr-tertiary-fixed);margin-bottom:8px"><?php esc_html_e( 'EXPLORE', 'ovr-core' ); ?></p>
            <h1 class="ovr-h1"><?php echo esc_html( $village->name ); ?></h1>
            <?php if ( $village->description ) : ?>
                <p style="max-width:600px;margin:16px auto 0"><?php echo esc_html( $village->description ); ?></p>
            <?php endif; ?>
            <p style="margin-top:16px;opacity:0.8">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">apartment</span>
                <?php printf( esc_html( _n( '%s property', '%s properties', $village->count, 'ovr-core' ) ), esc_html( $village->count ) ); ?>
            </p>
        </div>
    </div>

    <!-- Properties -->
    <div class="ovr-container ovr-section">
        <?php if ( $query->have_posts() ) : ?>
            <div class="ovr-grid ovr-grid-3">
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <?php echo PropertyCard::render_grid( get_the_ID() ); ?>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <div style="text-align:center;padding:80px 24px">
                <span class="material-symbols-outlined" style="font-size:64px;color:var(--ovr-outline-variant)">holiday_village</span>
                <h3 class="ovr-h3" style="margin:16px 0 8px"><?php esc_html_e( 'No Properties in This Village Yet', 'ovr-core' ); ?></h3>
                <p class="ovr-body-md" style="color:var(--ovr-on-surface-variant)">
                    <?php esc_html_e( 'Be the first to list your property here!', 'ovr-core' ); ?>
                </p>
                <a href="<?php echo esc_url( \OVR\Core\Pages::get_page_url( 'ovr_page_register' ) ); ?>" class="ovr-btn ovr-btn-primary" style="margin-top:16px">
                    <?php esc_html_e( 'List Your Property', 'ovr-core' ); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

</div>
