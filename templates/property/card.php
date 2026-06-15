<?php
/**
 * Property Card Template (Grid View).
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
 * @var int    $max_guests
 * @var int    $sqft
 * @var float  $base_price
 * @var float  $rating_avg
 * @var int    $rating_count
 * @var bool   $is_featured
 * @var bool   $is_bumped
 * @var bool   $pets_allowed
 * @var array  $options  Visibility flags (see PropertyCard::default_card_options()).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Defensive defaults so a partial data array can't emit "undefined variable" notices.
$post_id       = $post_id       ?? 0;
$title         = $title         ?? '';
$permalink     = $permalink     ?? '';
$thumbnail     = $thumbnail     ?? '';
$village       = $village       ?? '';
$property_type = $property_type ?? '';
$rental_type   = $rental_type   ?? '';
$bedrooms      = $bedrooms      ?? 0;
$bathrooms     = $bathrooms     ?? 0;
$sqft          = $sqft          ?? 0;
$is_featured   = $is_featured   ?? false;
$is_bumped     = $is_bumped     ?? false;

// Display flags (default everything on).
$options             = ( isset( $options ) && is_array( $options ) ) ? $options : [];
$show_favorite       = $options['show_favorite']       ?? true;
$show_featured_badge = $options['show_featured_badge'] ?? true;
$show_id             = $options['show_id']             ?? true;
$show_compare        = $options['show_compare']        ?? true;
$show_location       = $options['show_location']       ?? true;
$show_stats          = $options['show_stats']          ?? true;
$show_rates          = $options['show_rates']          ?? true;
$show_button         = $options['show_button']         ?? true;

$bath_display = rtrim( rtrim( number_format( (float) $bathrooms, 1 ), '0' ), '.' );
$sqft_display = $sqft ? number_format( (int) $sqft ) : '—';
$rates_note   = '' !== $rental_type ? $rental_type : __( 'Monthly rates available', 'ovr-core' );
?>
<article class="ovr-card ovr-property-card<?php echo $is_featured ? ' is-featured' : ''; ?>" id="ovr-property-<?php echo esc_attr( $post_id ); ?>">

    <!-- Image -->
    <div class="ovr-card-image">
        <a href="<?php echo esc_url( $permalink ); ?>" class="ovr-card-image-link" tabindex="-1" aria-hidden="true">
            <img src="<?php echo esc_url( $thumbnail ); ?>"
                 alt="<?php echo esc_attr( $title ); ?>"
                 loading="lazy"
                 width="400" height="300">
        </a>

        <?php if ( ! empty( $has_video ) ) : ?>
            <span class="ovr-card-video-flag" aria-hidden="true"><span class="material-symbols-outlined">play_circle</span></span>
        <?php endif; ?>
        <?php if ( $show_featured_badge && $is_featured ) : ?>
            <span class="ovr-card-badge ovr-badge-featured">
                <span class="material-symbols-outlined" style="font-size:16px">star</span>
                <?php esc_html_e( 'Featured', 'ovr-core' ); ?>
            </span>
        <?php elseif ( $show_featured_badge && $is_bumped ) : ?>
            <span class="ovr-card-badge ovr-badge-bumped"><?php esc_html_e( 'Promoted', 'ovr-core' ); ?></span>
        <?php endif; ?>

        <?php if ( $show_favorite ) : ?>
            <button class="ovr-card-favorite" aria-label="<?php esc_attr_e( 'Save to favorites', 'ovr-core' ); ?>" data-id="<?php echo esc_attr( $post_id ); ?>">
                <span class="material-symbols-outlined">favorite</span>
            </button>
        <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="ovr-card-info">

        <!-- Title + Compare -->
        <div class="ovr-card-title-row">
            <h3 class="ovr-card-title">
                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
            </h3>
            <?php if ( $show_compare ) : ?>
                <label class="ovr-card-compare">
                    <input type="checkbox" class="ovr-compare-checkbox" data-id="<?php echo esc_attr( $post_id ); ?>">
                    <?php esc_html_e( 'Compare', 'ovr-core' ); ?>
                </label>
            <?php endif; ?>
        </div>

        <!-- Location + ID -->
        <?php if ( $show_location || $show_id ) : ?>
            <div class="ovr-card-subrow">
                <?php if ( $show_location && $village ) : ?>
                    <span class="ovr-card-village">
                        <span class="material-symbols-outlined" style="font-size:18px">location_on</span>
                        <?php echo esc_html( $village ); ?>
                    </span>
                <?php else : ?>
                    <span></span>
                <?php endif; ?>

                <?php if ( $show_id ) : ?>
                    <span class="ovr-card-id">
                        <?php
                        /* translators: %s: property listing ID */
                        printf( esc_html__( 'ID: %s', 'ovr-core' ), esc_html( (string) $post_id ) );
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Beds / Baths / SqFt -->
        <?php if ( $show_stats ) : ?>
            <div class="ovr-card-stats">
                <div class="ovr-card-stat">
                    <span class="ovr-card-stat-label"><?php esc_html_e( 'Beds', 'ovr-core' ); ?></span>
                    <span class="ovr-card-stat-value"><?php echo esc_html( (string) $bedrooms ); ?></span>
                </div>
                <div class="ovr-card-stat">
                    <span class="ovr-card-stat-label"><?php esc_html_e( 'Baths', 'ovr-core' ); ?></span>
                    <span class="ovr-card-stat-value"><?php echo esc_html( $bath_display ); ?></span>
                </div>
                <div class="ovr-card-stat">
                    <span class="ovr-card-stat-label"><?php esc_html_e( 'SqFt', 'ovr-core' ); ?></span>
                    <span class="ovr-card-stat-value"><?php echo esc_html( $sqft_display ); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Rates + CTA (one line) -->
        <?php if ( $show_rates || $show_button ) : ?>
            <div class="ovr-card-foot">
                <?php if ( $show_rates ) : ?>
                    <p class="ovr-card-rates"><?php echo esc_html( $rates_note ); ?></p>
                <?php endif; ?>
                <?php if ( $show_button ) : ?>
                    <a href="<?php echo esc_url( $permalink ); ?>" class="ovr-btn ovr-card-cta <?php echo $is_featured ? 'ovr-btn-gold' : 'ovr-btn-outline'; ?>">
                        <?php echo $is_featured ? esc_html__( 'Inquire', 'ovr-core' ) : esc_html__( 'View Details', 'ovr-core' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</article>
