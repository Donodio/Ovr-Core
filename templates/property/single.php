<?php
/**
 * Single Property Template.
 *
 * Owner-direct listing detail (DESIGN.md §10): sub-header bar, gallery + owner
 * summary card, availability, rates table, tabbed description/features/reviews,
 * policies, map/video/panorama, inquiry, and disclaimer. No "Similar Homes".
 *
 * @package OVR
 *
 * @var int      $post_id
 * @var array    $meta     PropertyMeta::get_all() output.
 * @var array    $pricing  SeasonalPricing::get_pricing() rows.
 * @var array    $gallery  Attachment IDs.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\Pages;
use OVR\Core\TemplateLoader;
use OVR\Property\PropertyMeta;
use OVR\Property\SeasonalPricing;

// Pre-populated when called via SingleProperty::render(); otherwise auto-load.
$post_id  = $post_id ?? get_the_ID();
$meta     = ! empty( $meta )    ? $meta    : PropertyMeta::get_all( $post_id );
$pricing  = ! empty( $pricing ) ? $pricing : SeasonalPricing::get_pricing( $post_id );

if ( empty( $gallery ) ) {
    $ids_string = (string) ( $meta['gallery_ids'] ?? '' );
    if ( $ids_string ) {
        $gallery = array_filter( array_map( 'absint', explode( ',', $ids_string ) ) );
    } elseif ( has_post_thumbnail( $post_id ) ) {
        $gallery = [ get_post_thumbnail_id( $post_id ) ];
    } else {
        $gallery = [];
    }
}

get_header();

$title    = get_the_title( $post_id );
$content  = apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );

$bedrooms   = (int)   ( $meta['bedrooms']   ?? 0 );
$bathrooms  = (float) ( $meta['bathrooms']  ?? 0 );
$beds       = (int)   ( $meta['beds']       ?? 0 );
$sqft       = (int)   ( $meta['sqft']       ?? 0 );
$max_guests = (int)   ( $meta['max_guests'] ?? 1 );
$base_price = (float) ( $meta['base_price'] ?? 0 );
$pets       = ! empty( $meta['pets_allowed'] );
$is_feat    = ! empty( $meta['is_featured'] );
$rating_avg = (float) ( $meta['rating_avg']   ?? 0 );
$rating_n   = (int)   ( $meta['rating_count'] ?? 0 );
$min_stay   = (int)   ( $meta['min_stay']   ?? 1 );
$video_url  = (string)( $meta['video_url']    ?? '' );
$pano_url   = (string)( $meta['panorama_url'] ?? '' );
$lat        = (float) ( $meta['latitude']  ?? 0 );
$lng        = (float) ( $meta['longitude'] ?? 0 );

$city       = (string)( $meta['city']    ?? '' );
$state      = (string)( $meta['state']   ?? '' );
$country    = (string)( $meta['country'] ?? '' );

// "City, State" (or "City, Country" if no state).
$location_short = implode( ', ', array_values( array_filter( [ $city, $state, ( $state ? '' : $country ) ] ) ) );

$villages = wp_get_post_terms( $post_id, 'ovr_village' );
$village  = ( ! is_wp_error( $villages ) && ! empty( $villages ) ) ? $villages[0] : null;

$ptypes        = wp_get_post_terms( $post_id, 'ovr_property_type' );
$property_type = ( ! is_wp_error( $ptypes ) && ! empty( $ptypes ) ) ? $ptypes[0]->name : '';

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';

$author_id  = (int) get_post_field( 'post_author', $post_id );
// Edit button shows ONLY to the owner of this specific listing.
$is_owner   = is_user_logged_in() && get_current_user_id() === $author_id;
$edit_link  = $is_owner ? get_edit_post_link( $post_id ) : '';
$search_url = class_exists( Pages::class ) ? Pages::get_page_url( 'ovr_page_search' ) : home_url( '/' );

// Lightweight page-view counter (total + per-month) — skip owner and admin screens.
$views   = (int) get_post_meta( $post_id, '_ovr_view_count', true );
$monthly = get_post_meta( $post_id, '_ovr_monthly_views', true );
$monthly = is_array( $monthly ) ? $monthly : [];
if ( ! is_admin() && ! $is_owner ) {
    $views++;
    update_post_meta( $post_id, '_ovr_view_count', $views );

    $mkey             = wp_date( 'Y-m' );
    $monthly[ $mkey ] = (int) ( $monthly[ $mkey ] ?? 0 ) + 1;
    if ( count( $monthly ) > 12 ) {
        ksort( $monthly );
        $monthly = array_slice( $monthly, -12, null, true );
    }
    update_post_meta( $post_id, '_ovr_monthly_views', $monthly );
}

// Schema.org Lodging structured data.
$schema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'LodgingBusiness',
    'name'        => $title,
    'description' => wp_strip_all_tags( get_post_field( 'post_excerpt', $post_id ) ?: get_post_field( 'post_content', $post_id ) ),
    'url'         => get_permalink( $post_id ),
    'image'       => has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'large' ) : '',
    'priceRange'  => $symbol . number_format( $base_price, 0 ),
];
if ( $rating_avg > 0 && $rating_n > 0 ) {
    $schema['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => $rating_avg,
        'reviewCount' => $rating_n,
    ];
}
if ( $lat && $lng ) {
    $schema['geo'] = [ '@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng ];
}

// Pre-render the embedded tab partials so empty ones get a graceful fallback.
$amenities_html = TemplateLoader::get_rendered( 'property/amenities.php', [ 'post_id' => $post_id ] );
$reviews_html   = TemplateLoader::get_rendered( 'property/reviews-section.php', [ 'post_id' => $post_id ] );
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ); ?></script>

<div class="ovr-wrap ovr-single-property">

    <!-- Sub-header: back / ID + title / tools -->
    <div class="ovr-detail-subbar">
        <div class="ovr-detail-subbar-inner">
            <div class="ovr-detail-subbar-main">
                <a href="<?php echo esc_url( $search_url ); ?>" class="ovr-detail-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <?php esc_html_e( 'Back to Search', 'ovr-core' ); ?>
                </a>
                <div class="ovr-detail-titlewrap">
                    <span class="ovr-detail-id">
                        <?php
                        /* translators: %d: listing ID */
                        printf( esc_html__( 'ID %d', 'ovr-core' ), $post_id );
                        ?>
                    </span>
                    <h1 class="ovr-detail-title"><?php echo esc_html( $title ); ?></h1>
                </div>
            </div>
            <div class="ovr-detail-tools">
                <?php if ( $edit_link ) : ?>
                    <a class="ovr-detail-tool" href="<?php echo esc_url( $edit_link ); ?>" aria-label="<?php esc_attr_e( 'Edit listing', 'ovr-core' ); ?>">
                        <span class="material-symbols-outlined">edit</span>
                    </a>
                <?php endif; ?>
                <button type="button" class="ovr-detail-tool" onclick="window.print()" aria-label="<?php esc_attr_e( 'Print listing', 'ovr-core' ); ?>">
                    <span class="material-symbols-outlined">print</span>
                </button>
            </div>
        </div>
    </div>

    <div class="ovr-detail-body">

        <!-- Top: gallery + owner summary card -->
        <div class="ovr-detail-top">

            <div class="ovr-detail-gallery-col">
                <?php
                echo TemplateLoader::get_rendered( 'property/gallery.php', [
                    'post_id'      => $post_id,
                    'gallery'      => $gallery,
                    'title'        => $title,
                    'video_url'    => $video_url,
                    'panorama_url' => $pano_url,
                ] );
                ?>

                <!-- Specs strip -->
                <div class="ovr-detail-specs">
                    <span class="ovr-detail-spec"><span class="material-symbols-outlined">group</span>
                        <?php
                        /* translators: %d: guest count */
                        printf( esc_html( _n( '%d guest', '%d guests', $max_guests, 'ovr-core' ) ), $max_guests );
                        ?>
                    </span>
                    <span class="ovr-detail-spec"><span class="material-symbols-outlined">bed</span>
                        <?php
                        /* translators: %d: bedroom count */
                        printf( esc_html( _n( '%d bedroom', '%d bedrooms', $bedrooms, 'ovr-core' ) ), $bedrooms );
                        ?>
                    </span>
                    <span class="ovr-detail-spec"><span class="material-symbols-outlined">bathtub</span>
                        <?php
                        /* translators: %s: bathroom count */
                        printf( esc_html__( '%s baths', 'ovr-core' ), esc_html( (string) $bathrooms ) );
                        ?>
                    </span>
                    <?php if ( $sqft > 0 ) : ?>
                        <span class="ovr-detail-spec"><span class="material-symbols-outlined">straighten</span>
                            <?php
                            /* translators: %s: square footage */
                            printf( esc_html__( '%s sq ft', 'ovr-core' ), esc_html( number_format_i18n( $sqft ) ) );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Status chips -->
                <?php if ( $is_feat || $pets || $rating_avg > 0 ) : ?>
                    <div class="ovr-detail-chips">
                        <?php if ( $is_feat ) : ?>
                            <span class="ovr-detail-chip is-featured"><span class="material-symbols-outlined">star</span><?php esc_html_e( 'Featured', 'ovr-core' ); ?></span>
                        <?php endif; ?>
                        <?php if ( $rating_avg > 0 ) : ?>
                            <span class="ovr-detail-chip is-rating"><span class="material-symbols-outlined">star</span>
                                <?php echo esc_html( number_format( $rating_avg, 1 ) ); ?>
                                <?php
                                /* translators: %d: review count */
                                printf( esc_html( _n( '(%d review)', '(%d reviews)', $rating_n, 'ovr-core' ) ), $rating_n );
                                ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( $pets ) : ?>
                            <span class="ovr-detail-chip"><span class="material-symbols-outlined">pets</span><?php esc_html_e( 'Pet Friendly', 'ovr-core' ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Owner / summary card -->
            <div class="ovr-detail-aside">
                <?php
                echo TemplateLoader::get_rendered( 'property/owner-card.php', [
                    'post_id'       => $post_id,
                    'base_price'    => $base_price,
                    'symbol'        => $symbol,
                    'property_type' => $property_type,
                    'bedrooms'      => $bedrooms,
                    'village'       => $village,
                    'has_seasonal'  => ! empty( $pricing ),
                    'views'         => $views,
                    'monthly_views' => $monthly,
                    'is_owner'      => $is_owner,
                    'title'         => $title,
                ] );
                ?>
            </div>
        </div>

        <!-- Availability -->
        <?php
        echo TemplateLoader::get_rendered( 'property/calendar.php', [
            'post_id'      => $post_id,
            'months_ahead' => 6,
            'min_stay'     => $min_stay,
        ] );
        ?>

        <!-- Rates / Pricing -->
        <?php
        echo TemplateLoader::get_rendered( 'property/seasonal-pricing.php', [
            'post_id' => $post_id,
            'pricing' => $pricing,
        ] );
        ?>

        <!-- Tabs: Description / Features / Reviews -->
        <section class="ovr-detail-section" data-purpose="tabs-content">
            <div class="ovr-tabs" data-ovr-tabs>
                <div class="ovr-tabs-nav" role="tablist">
                    <button type="button" class="ovr-tab is-active" role="tab" aria-selected="true" data-ovr-tab="desc"><?php esc_html_e( 'General Description', 'ovr-core' ); ?></button>
                    <button type="button" class="ovr-tab" role="tab" aria-selected="false" data-ovr-tab="features"><?php esc_html_e( 'Features', 'ovr-core' ); ?></button>
                    <button type="button" class="ovr-tab" role="tab" aria-selected="false" data-ovr-tab="reviews"><?php esc_html_e( 'Reviews', 'ovr-core' ); ?></button>
                </div>
                <div class="ovr-tab-panels">

                    <div class="ovr-tab-panel is-active" role="tabpanel" data-ovr-panel="desc">
                        <h3 class="ovr-tab-subhead"><?php esc_html_e( 'About this home', 'ovr-core' ); ?></h3>
                        <div class="ovr-tab-prose">
                            <?php
                            if ( $content ) {
                                echo wp_kses_post( $content );
                            } else {
                                echo '<p>' . esc_html__( 'No description has been added for this listing yet.', 'ovr-core' ) . '</p>';
                            }
                            ?>
                        </div>
                        <?php if ( $village || $location_short ) : ?>
                            <div class="ovr-tab-near">
                                <h3 class="ovr-tab-subhead"><?php esc_html_e( "What's nearby", 'ovr-core' ); ?></h3>
                                <p class="ovr-tab-prose" style="margin:0">
                                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--ovr-secondary);vertical-align:-3px">location_on</span>
                                    <?php
                                    if ( $village ) {
                                        printf(
                                            /* translators: %s: village name */
                                            esc_html__( 'Located in the Village of %s', 'ovr-core' ),
                                            '<a href="' . esc_url( get_term_link( $village ) ) . '">' . esc_html( $village->name ) . '</a>'
                                        );
                                        echo $location_short ? ' &middot; ' . esc_html( $location_short ) : '';
                                    } else {
                                        echo esc_html( $location_short );
                                    }
                                    ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="ovr-tab-panel" role="tabpanel" data-ovr-panel="features">
                        <?php
                        if ( trim( (string) $amenities_html ) !== '' ) {
                            echo $amenities_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        } else {
                            echo '<p class="ovr-tab-prose" style="margin:0">' . esc_html__( 'No features have been listed for this property yet.', 'ovr-core' ) . '</p>';
                        }
                        ?>
                    </div>

                    <div class="ovr-tab-panel" role="tabpanel" data-ovr-panel="reviews">
                        <?php echo $reviews_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>

                </div>
            </div>
        </section>

        <!-- Policies & Payment -->
        <?php
        echo TemplateLoader::get_rendered( 'property/policies.php', [
            'post_id' => $post_id,
            'meta'    => $meta,
        ] );
        ?>

        <!-- Documents & Resources -->
        <?php
        $doc_csv = (string) get_post_meta( $post_id, '_ovr_document_ids', true );
        $doc_ids = array_filter( array_map( 'absint', explode( ',', $doc_csv ) ) );
        if ( ! empty( $doc_ids ) ) :
        ?>
            <section class="ovr-detail-section" data-purpose="documents">
                <div class="ovr-detail-card">
                    <h2 class="ovr-detail-heading"><?php esc_html_e( 'Documents & Resources', 'ovr-core' ); ?></h2>
                    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:12px">
                        <?php foreach ( $doc_ids as $doc_id ) :
                            $att = get_post( (int) $doc_id );
                            if ( ! $att ) { continue; }
                            $url      = wp_get_attachment_url( $doc_id );
                            $filename = basename( (string) get_attached_file( $doc_id ) );
                        ?>
                            <li>
                                <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"
                                   style="display:inline-flex;align-items:center;gap:10px;padding:12px 18px;background:var(--ovr-surface-container-low);border:1px solid var(--ovr-border-gray);border-radius:var(--ovr-radius-md);text-decoration:none;color:var(--ovr-on-surface);font-weight:600;font-size:14px">
                                    <span class="material-symbols-outlined" style="color:var(--ovr-secondary)">description</span>
                                    <?php echo esc_html( $att->post_title ?: $filename ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <!-- Map & Location (+ optional video / panorama) -->
        <?php if ( ( $lat && $lng ) || $video_url || $pano_url ) :
            $embed_url = '';
            if ( $video_url ) {
                $embed_url = $video_url;
                if ( preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $m ) ) {
                    $embed_url = 'https://www.youtube.com/embed/' . $m[1];
                } elseif ( preg_match( '/vimeo\.com\/(\d+)/', $video_url, $m ) ) {
                    $embed_url = 'https://player.vimeo.com/video/' . $m[1];
                }
            }
            // Caption that grounds the map in The Villages (DESIGN.md: avoid city-wide busy maps).
            $map_caption = '';
            if ( $village ) {
                /* translators: %s: village name */
                $map_caption = sprintf( __( 'Village of %s', 'ovr-core' ), $village->name );
            }
            if ( $location_short ) {
                $map_caption = $map_caption ? $map_caption . ' · ' . $location_short : $location_short;
            }
        ?>
            <section class="ovr-detail-section" data-purpose="media-sections">
                <div class="ovr-detail-card">
                    <h2 class="ovr-detail-heading"><?php esc_html_e( 'Map & Location', 'ovr-core' ); ?></h2>

                    <?php if ( $lat && $lng ) : ?>
                        <div class="ovr-media-map">
                            <iframe
                                src="https://www.openstreetmap.org/export/embed.html?bbox=<?php echo esc_attr( ( $lng - 0.008 ) . ',' . ( $lat - 0.006 ) . ',' . ( $lng + 0.008 ) . ',' . ( $lat + 0.006 ) ); ?>&amp;layer=mapnik&amp;marker=<?php echo esc_attr( $lat . ',' . $lng ); ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                title="<?php esc_attr_e( 'Property location map', 'ovr-core' ); ?>"></iframe>
                            <?php if ( $map_caption ) : ?>
                                <div class="ovr-media-map-caption">
                                    <span class="material-symbols-outlined">location_on</span>
                                    <?php echo esc_html( $map_caption ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <p class="ovr-tab-prose" style="margin:0"><?php esc_html_e( 'A precise map location has not been provided for this listing.', 'ovr-core' ); ?></p>
                    <?php endif; ?>

                    <?php if ( $video_url || $pano_url ) : ?>
                        <div class="ovr-media-extra">
                            <?php if ( $video_url ) : ?>
                                <div class="ovr-media-tile">
                                    <iframe src="<?php echo esc_url( $embed_url ); ?>" allowfullscreen loading="lazy" title="<?php esc_attr_e( 'Property video tour', 'ovr-core' ); ?>"></iframe>
                                </div>
                            <?php endif; ?>
                            <?php if ( $pano_url ) : ?>
                                <a class="ovr-media-tile ovr-media-cta ovr-media-cta--panorama" href="<?php echo esc_url( $pano_url ); ?>" target="_blank" rel="noopener">
                                    <span class="material-symbols-outlined">360</span>
                                    <span><?php esc_html_e( 'View 360° Tour', 'ovr-core' ); ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Inquiry -->
        <section class="ovr-detail-section" id="ovr-inquiry">
            <div class="ovr-detail-card">
                <h2 class="ovr-detail-heading"><?php esc_html_e( 'Inquire – Contact the Owner', 'ovr-core' ); ?></h2>
                <div class="ovr-inquire-section">
                    <?php
                    echo TemplateLoader::get_rendered( 'property/inquiry-form.php', [
                        'post_id'    => $post_id,
                        'base_price' => $base_price,
                        'max_guests' => $max_guests,
                    ] );
                    ?>
                </div>
            </div>
        </section>

        <!-- Disclaimer -->
        <?php
        $disclaimer = trim( (string) ( $settings['listing_disclaimer'] ?? '' ) );
        if ( $disclaimer ) :
        ?>
            <div class="ovr-detail-disclaimer">
                <h3><?php esc_html_e( 'Page Disclaimer', 'ovr-core' ); ?></h3>
                <p><?php echo esc_html( $disclaimer ); ?></p>
            </div>
        <?php endif; ?>

    </div>
</div>
<?php
get_footer();
