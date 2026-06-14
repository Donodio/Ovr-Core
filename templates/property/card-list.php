<?php
/**
 * Property Card — List View Variant.
 *
 * Horizontal layout used by Search Results when view=list.
 *
 * @var int    $post_id
 * @var string $title
 * @var string $permalink
 * @var string $thumbnail
 * @var string $village
 * @var int    $bedrooms
 * @var float  $bathrooms
 * @var int    $max_guests
 * @var float  $base_price
 * @var float  $rating_avg
 * @var int    $rating_count
 * @var bool   $is_featured
 * @var bool   $is_bumped
 * @var bool   $pets_allowed
 * @var string $excerpt
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';
$excerpt  = $excerpt ?? '';
$thumb_w  = (int) ( $thumb_w ?? 0 ) ?: 1200;
$thumb_h  = (int) ( $thumb_h ?? 0 ) ?: 900;
?>
<article class="ovr-card ovr-property-card-list" id="ovr-property-list-<?php echo esc_attr( $post_id ); ?>"
         style="display:grid;grid-template-columns:280px 1fr;gap:0;overflow:hidden;align-items:stretch">

    <!-- Image: narrower column (wider rows), shown at its NATURAL aspect ratio —
         the whole photo, never cropped or truncated, no letterbox bars. The real
         width/height attrs let the browser reserve the correct space before the
         lazy image loads, so it can't collapse. Anchored to the card's top-left. -->
    <a href="<?php echo esc_url( $permalink ); ?>"
       class="ovr-card-image"
       aria-label="<?php echo esc_attr( $title ); ?>"
       style="position:relative;align-self:start;display:block;width:100%">
        <img src="<?php echo esc_url( $thumbnail ); ?>"
             alt="<?php echo esc_attr( $title ); ?>"
             width="<?php echo esc_attr( (string) $thumb_w ); ?>"
             height="<?php echo esc_attr( (string) $thumb_h ); ?>"
             loading="lazy"
             style="display:block;width:100%;height:auto">

        <?php if ( ! empty( $has_video ) ) : ?>
            <span class="ovr-card-video-flag" aria-hidden="true"><span class="material-symbols-outlined">play_circle</span></span>
        <?php endif; ?>
        <?php if ( $is_featured ) : ?>
            <span class="ovr-card-badge ovr-badge-featured">
                <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">star</span>
                <?php esc_html_e( 'Featured', 'ovr-core' ); ?>
            </span>
        <?php elseif ( $is_bumped ) : ?>
            <span class="ovr-card-badge ovr-badge-bumped"><?php esc_html_e( 'Promoted', 'ovr-core' ); ?></span>
        <?php endif; ?>
    </a>

    <!-- Body -->
    <div style="padding:24px;display:flex;flex-direction:column;justify-content:space-between;gap:16px">
        <div>
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:8px">
                <a href="<?php echo esc_url( $permalink ); ?>" style="text-decoration:none;color:var(--ovr-on-surface)">
                    <h3 class="ovr-card-title" style="font-size:20px;white-space:normal;overflow:visible">
                        <?php echo esc_html( $title ); ?>
                    </h3>
                </a>
                <?php if ( $rating_avg > 0 ) : ?>
                    <div class="ovr-card-rating" style="background:var(--ovr-secondary-container);color:var(--ovr-on-secondary-container);padding:2px 10px;border-radius:9999px;flex-shrink:0">
                        <span class="material-symbols-outlined fill" style="font-size:14px">star</span>
                        <span><?php echo esc_html( number_format( $rating_avg, 1 ) ); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( $village ) : ?>
                <p class="ovr-card-village" style="margin-bottom:12px">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">location_on</span>
                    <?php echo esc_html( $village ); ?>
                </p>
            <?php endif; ?>

            <?php if ( $excerpt ) : ?>
                <p style="font-size:14px;color:var(--ovr-on-surface-variant);margin-bottom:12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                    <?php echo esc_html( $excerpt ); ?>
                </p>
            <?php endif; ?>

            <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:14px;color:var(--ovr-on-surface-variant)">
                <span style="display:inline-flex;align-items:center;gap:4px">
                    <span class="material-symbols-outlined" style="font-size:18px">bed</span>
                    <?php
                    /* translators: %d: bedroom count */
                    printf( esc_html( _n( '%d bed', '%d beds', $bedrooms, 'ovr-core' ) ), $bedrooms ); ?>
                </span>
                <span style="display:inline-flex;align-items:center;gap:4px">
                    <span class="material-symbols-outlined" style="font-size:18px">shower</span>
                    <?php
                    /* translators: %s: bathroom count */
                    printf( esc_html__( '%s bath', 'ovr-core' ), esc_html( (string) $bathrooms ) ); ?>
                </span>
                <span style="display:inline-flex;align-items:center;gap:4px">
                    <span class="material-symbols-outlined" style="font-size:18px">group</span>
                    <?php
                    /* translators: %d: guest count */
                    printf( esc_html( _n( '%d guest', '%d guests', $max_guests, 'ovr-core' ) ), $max_guests ); ?>
                </span>
                <?php if ( $pets_allowed ) : ?>
                    <span style="display:inline-flex;align-items:center;gap:4px">
                        <span class="material-symbols-outlined" style="font-size:18px">pets</span>
                        <?php esc_html_e( 'Pets', 'ovr-core' ); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer: Price + CTA -->
        <div style="display:flex;justify-content:space-between;align-items:flex-end;border-top:1px solid var(--ovr-outline-variant);padding-top:16px">
            <div>
                <?php if ( $base_price > 0 ) : ?>
                    <span class="ovr-price-display" style="font-size:24px;color:var(--ovr-on-surface)">
                        <?php echo esc_html( $symbol . number_format( $base_price, 0 ) ); ?>
                    </span>
                    <span style="color:var(--ovr-on-surface-variant);font-size:14px">
                        / <?php esc_html_e( 'night', 'ovr-core' ); ?>
                    </span>
                <?php elseif ( ! empty( $has_pricing ) ) : ?>
                    <span class="ovr-price-display" style="font-size:18px;color:var(--ovr-on-surface)"><?php esc_html_e( 'Seasonal Rates', 'ovr-core' ); ?></span>
                <?php else : ?>
                    <span style="font-size:15px;color:var(--ovr-on-surface-variant);font-style:italic"><?php esc_html_e( 'See Description For Pricing', 'ovr-core' ); ?></span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( $permalink ); ?>" class="ovr-btn ovr-btn-secondary" style="padding:8px 20px;font-size:14px">
                <?php esc_html_e( 'View Details', 'ovr-core' ); ?>
            </a>
        </div>
    </div>
</article>

<style>
    @media (max-width: 768px) {
        .ovr-property-card-list {
            grid-template-columns: 1fr !important;
        }
    }
</style>
