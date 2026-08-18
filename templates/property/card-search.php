<?php
/**
 * Property Card — Search Results "Stitch" redesign.
 *
 * Fixed-height card used by both the results grid and the featured rail so
 * the two columns line up row-for-row (see PropertyCard::render_search()).
 *
 * @package OVR
 *
 * @var int    $post_id
 * @var string $title
 * @var string $permalink
 * @var string $thumbnail
 * @var string $village
 * @var string $property_type
 * @var string $rental_type
 * @var int    $bedrooms
 * @var float  $bathrooms
 * @var float  $base_price
 * @var float  $rating_avg
 * @var int    $rating_count
 * @var string $excerpt
 * @var string $symbol            Currency symbol.
 * @var bool   $featured_variant  Render the gold "featured" treatment.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$post_id          = (int) ( $post_id ?? 0 );
$title            = (string) ( $title ?? '' );
$permalink        = (string) ( $permalink ?? '' );
$thumbnail        = (string) ( $thumbnail ?? '' );
$village          = (string) ( $village ?? '' );
$property_type    = (string) ( $property_type ?? '' );
$rental_type      = (string) ( $rental_type ?? '' );
$bedrooms         = (int) ( $bedrooms ?? 0 );
$bathrooms        = (float) ( $bathrooms ?? 0 );
$base_price       = (float) ( $base_price ?? 0 );
$rating_avg       = (float) ( $rating_avg ?? 0 );
$rating_count     = (int) ( $rating_count ?? 0 );
$excerpt          = (string) ( $excerpt ?? '' );
$symbol           = (string) ( $symbol ?? '$' );
$featured_variant = ! empty( $featured_variant );

$bath_display = rtrim( rtrim( number_format( $bathrooms, 1 ), '0' ), '.' );
// Location-first label: the village/section name is the more useful bit of
// information in this slot (client request). The property type remains
// available in filters, detail pages and the admin editor.
$type_label   = $village ?: ( $property_type ?: __( 'Rental Home', 'ovr-core' ) );

// Cap the blurb at 200 characters (the CSS also clamps it to 3 lines).
$excerpt = trim( wp_strip_all_tags( $excerpt ) );
if ( mb_strlen( $excerpt ) > 200 ) {
    $excerpt = rtrim( mb_substr( $excerpt, 0, 199 ) ) . '…';
}

// Price line: a legacy nightly rate if one is set, else "Seasonal Rates" when
// the listing has a flexible pricing table, else "See Description For Pricing".
// No nightly-rate dependency.
$has_price   = $base_price > 0;
$has_pricing = ! empty( $has_pricing );
?>
<article class="ovr-ss-card<?php echo $featured_variant ? ' ovr-ss-card--featured' : ''; ?>" id="ovr-ss-<?php echo esc_attr( (string) $post_id ); ?>">

    <a class="ovr-ss-card-media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
        <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" width="400" height="300">
        <?php if ( ! empty( $has_video ) ) : ?>
            <span class="ovr-card-video-flag" aria-hidden="true"><span class="material-symbols-outlined">play_circle</span></span>
        <?php endif; ?>
        <?php if ( $featured_variant ) : ?>
            <span class="ovr-ss-card-flag">
                <span class="material-symbols-outlined">star</span><?php esc_html_e( 'Featured', 'ovr-core' ); ?>
            </span>
        <?php endif; ?>
        <span class="ovr-ss-card-id">
            <?php
            /* translators: %s: property listing ID */
            printf( esc_html__( 'ID: %s', 'ovr-core' ), esc_html( (string) $post_id ) );
            ?>
        </span>
    </a>

    <div class="ovr-ss-card-body">

        <div class="ovr-ss-card-toprow">
            <span class="ovr-ss-card-type"><?php echo esc_html( $type_label ); ?></span>
            <?php if ( $featured_variant && $rating_count > 0 ) : ?>
                <span class="ovr-ss-card-stars" aria-label="<?php
                    /* translators: %s: average rating */
                    echo esc_attr( sprintf( __( 'Rated %s out of 5', 'ovr-core' ), number_format( $rating_avg, 1 ) ) ); ?>">
                    <?php
                    $filled = (int) round( min( 5, max( 0, $rating_avg ) ) );
                    for ( $s = 1; $s <= 5; $s++ ) :
                        ?><span class="material-symbols-outlined<?php echo $s <= $filled ? ' is-on' : ''; ?>">star</span><?php
                    endfor;
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <h3 class="ovr-ss-card-title">
            <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
        </h3>

        <p class="ovr-ss-card-desc"><?php echo esc_html( $excerpt ); ?></p>

        <div class="ovr-ss-card-foot">
            <div class="ovr-ss-card-specs">
                <span><span class="material-symbols-outlined">bed</span><?php echo esc_html( (string) $bedrooms ); ?></span>
                <span><span class="material-symbols-outlined">shower</span><?php echo esc_html( $bath_display ); ?></span>
            </div>
            <div class="ovr-ss-card-price">
                <?php
                // Price-range label built from structured pricing (e.g. "$1,200 –
                // $1,800 / month") when the listing has a genuine range; otherwise
                // fall back to the legacy nightly rate, then "Seasonal Rates".
                $price_range = ! empty( $price_range ) ? $price_range : null;
                if ( $price_range && $price_range['max'] > $price_range['min'] ) : ?>
                    <?php echo esc_html( $symbol . number_format( $price_range['min'], 0 ) . ' – ' . $symbol . number_format( $price_range['max'], 0 ) ); ?>
                    <?php if ( '' !== $price_range['per'] ) : ?><span>/ <?php echo esc_html( $price_range['per'] ); ?></span><?php endif; ?>
                <?php elseif ( $has_price ) : ?>
                    <?php echo esc_html( $symbol . number_format( $base_price, 0 ) ); ?><span>/ <?php esc_html_e( 'night', 'ovr-core' ); ?></span>
                <?php elseif ( $has_pricing ) : ?>
                    <?php esc_html_e( 'Seasonal Rates', 'ovr-core' ); ?>
                <?php else : ?>
                    <span class="ovr-ss-price-desc"><?php esc_html_e( 'See Description For Pricing', 'ovr-core' ); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="ovr-ss-card-actions">
            <a class="ovr-ss-btn ovr-ss-btn-navy" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'Details', 'ovr-core' ); ?></a>
            <a class="ovr-ss-btn ovr-ss-btn-gold" href="<?php echo esc_url( $permalink ); ?>#ovr-inquiry"><?php esc_html_e( 'Inquire', 'ovr-core' ); ?></a>
        </div>
    </div>
</article>
