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

// Visibility gate (Phase 8B): a listing the owner set Inactive — or an admin
// set to Hidden/Suspended/Pending Review — is not shown publicly. The owner of
// this listing and site admins can still preview it.
$gate_author   = (int) get_post_field( 'post_author', $post_id );
$gate_is_owner = is_user_logged_in() && get_current_user_id() === $gate_author;
if ( ! \OVR\Property\PropertyQuery::is_publicly_visible( $post_id ) && ! $gate_is_owner && ! current_user_can( 'manage_options' ) ) {
    status_header( 404 );
    echo '<div class="ovr-wrap ovr-single-property"><div class="ovr-detail-body"><div class="ovr-detail-card" style="text-align:center;padding:48px 24px">';
    echo '<h1 class="ovr-detail-heading">' . esc_html__( 'This listing is not available', 'ovr-core' ) . '</h1>';
    echo '<p class="ovr-tab-prose" style="margin:0">' . esc_html__( 'It may have been set to inactive or removed by the owner.', 'ovr-core' ) . '</p>';
    echo '</div></div></div>';
    get_footer();
    return;
}

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

// Uploaded media (Features B & C): native video + 360 panorama image.
$video_id   = (int) ( $meta['video_id'] ?? 0 );
$video_src  = $video_id ? ( wp_get_attachment_url( $video_id ) ?: '' ) : '';
$pano_id    = (int) ( $meta['panorama_id'] ?? 0 );
$pano_src   = $pano_id ? ( wp_get_attachment_image_url( $pano_id, 'full' ) ?: wp_get_attachment_url( $pano_id ) ) : '';

// YouTube/Vimeo → embed URL for an inline iframe player.
$video_embed = '';
if ( '' === $video_src && '' !== $video_url ) {
    $video_embed = $video_url;
    if ( preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $vm ) ) {
        $video_embed = 'https://www.youtube.com/embed/' . $vm[1];
    } elseif ( preg_match( '/vimeo\.com\/(\d+)/', $video_url, $vm ) ) {
        $video_embed = 'https://player.vimeo.com/video/' . $vm[1];
    }
}

// Feature C: a Virtual Tour exists when there's an uploaded panorama image or a tour link.
$has_tour = ( '' !== $pano_src ) || ( '' !== $pano_url );
$lat        = (float) ( $meta['latitude']  ?? 0 );
$lng        = (float) ( $meta['longitude'] ?? 0 );

$city       = (string)( $meta['city']    ?? '' );
$state      = (string)( $meta['state']   ?? '' );
$country    = (string)( $meta['country'] ?? '' );

// "City, State" (or "City, Country" if no state).
$location_short = implode( ', ', array_values( array_filter( [ $city, $state, ( $state ? '' : $country ) ] ) ) );

$villages = wp_get_post_terms( $post_id, 'ovr_village' );
$village  = ( ! is_wp_error( $villages ) && ! empty( $villages ) ) ? $villages[0] : null; // Village Section (search facet).
$village_name = (string) ( $meta['village_name'] ?? '' ); // Specific village (free text).

$ptypes        = wp_get_post_terms( $post_id, 'ovr_property_type' );
$property_type = ( ! is_wp_error( $ptypes ) && ! empty( $ptypes ) ) ? $ptypes[0]->name : '';

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';

$author_id  = (int) get_post_field( 'post_author', $post_id );
// Edit button shows to the listing owner OR an admin (Phase 15). It points at
// the front-end dashboard editor (?tab=add-listing&post=ID) — landlords have no
// wp-admin access, so get_edit_post_link() produced a dead/blocked link.
$is_owner   = is_user_logged_in() && get_current_user_id() === $author_id;
$can_edit   = $is_owner || current_user_can( 'manage_options' );
$edit_link  = '';
if ( $can_edit && class_exists( Pages::class ) ) {
    $edit_link = add_query_arg(
        [ 'tab' => 'add-listing', 'post' => $post_id ],
        Pages::get_page_url( 'ovr_page_dashboard' )
    );
}
$search_url = class_exists( Pages::class ) ? Pages::get_page_url( 'ovr_page_search' ) : home_url( '/' );

// Return the visitor to the exact (filtered + paginated) results they came from
// so they can keep browsing the same shortlist. Priority:
//   1. ?ovr_ref= — the precise results URL stamped onto every listing link on
//      the search page (survives refresh / new tab / stripped referer).
//   2. HTTP referer, when it points back at the search page.
//   3. The plain search page — never the homepage.
// Both candidate URLs must start with the search page URL (guards against an
// open redirect via a forged ?ovr_ref).
$referer  = wp_get_referer();
$ref      = isset( $_GET['ovr_ref'] ) ? esc_url_raw( wp_unslash( $_GET['ovr_ref'] ) ) : '';
$back_url = $search_url;
if ( $ref && 0 === strpos( $ref, $search_url ) ) {
    $back_url = $ref;
} elseif ( $referer && false !== strpos( $referer, $search_url ) ) {
    $back_url = $referer;
}

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

    <!-- Sub-header (Phase 14): back · prominent Property ID + Section · large title -->
    <div class="ovr-detail-subbar">
        <div class="ovr-detail-subbar-inner">
            <div class="ovr-detail-subbar-main">
                <a href="<?php echo esc_url( $back_url ); ?>" class="ovr-detail-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <?php esc_html_e( 'Back To Search Results', 'ovr-core' ); ?>
                </a>
                <div class="ovr-detail-titlewrap">
                    <div class="ovr-detail-idrow">
                        <span class="ovr-detail-id">
                            <?php
                            /* translators: %d: listing/property ID */
                            printf( esc_html__( 'Property ID #%d', 'ovr-core' ), $post_id );
                            ?>
                        </span>
                        <?php if ( $village ) : ?>
                            <span class="ovr-detail-sectionchip"><?php echo esc_html( $village->name ); ?></span>
                        <?php endif; ?>
                    </div>
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
                    'video_src'    => $video_src,
                    'video_embed'  => $video_embed,
                    'panorama_url' => $pano_url,
                    'captions'     => (array) get_post_meta( $post_id, '_ovr_gallery_captions', true ),
                ] );
                ?>

                <?php if ( $has_tour ) : ?>
                    <button type="button" class="ovr-virtual-tour-btn" data-ovr-tour-open>
                        <span class="material-symbols-outlined">360</span>
                        <?php esc_html_e( 'Virtual Tour', 'ovr-core' ); ?>
                    </button>
                <?php endif; ?>

                <!-- Specs strip: Bedrooms · Baths · Pets (guests removed — most
                     Villages rentals are for 2 with guests welcome during a stay). -->
                <div class="ovr-detail-specs">
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
                    <span class="ovr-detail-spec">
                        <span class="material-symbols-outlined"><?php echo $pets ? 'pets' : 'block'; ?></span>
                        <?php echo $pets ? esc_html__( 'Pets allowed', 'ovr-core' ) : esc_html__( 'No pets', 'ovr-core' ); ?>
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
                        <?php if ( $village_name || $village || $location_short ) : ?>
                            <div class="ovr-tab-near">
                                <h3 class="ovr-tab-subhead"><?php esc_html_e( "What's nearby", 'ovr-core' ); ?></h3>
                                <p class="ovr-tab-prose" style="margin:0">
                                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--ovr-secondary);vertical-align:-3px">location_on</span>
                                    <?php
                                    // Lead with the specific Village Name (free text); the Village
                                    // Section term links to its search/landing page when present.
                                    $bits = [];
                                    if ( '' !== $village_name ) {
                                        $bits[] = sprintf(
                                            /* translators: %s: specific village name */
                                            esc_html__( 'Located in %s', 'ovr-core' ),
                                            esc_html( $village_name )
                                        );
                                    }
                                    if ( $village ) {
                                        $link = '<a href="' . esc_url( get_term_link( $village ) ) . '">' . esc_html( $village->name ) . '</a>';
                                        $bits[] = '' !== $village_name
                                            /* translators: %s: village section name */
                                            ? sprintf( esc_html__( '%s section', 'ovr-core' ), $link )
                                            : sprintf( esc_html__( 'Located in the %s section', 'ovr-core' ), $link );
                                    }
                                    if ( $location_short ) {
                                        $bits[] = esc_html( $location_short );
                                    }
                                    echo implode( ' &middot; ', $bits ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

        <!-- Owner-authored sections (Phase 20): What's Nearby / Policies / Payment Information -->
        <?php
        $extra_sections = [
            [ 'icon' => 'explore',     'title' => __( "What's Nearby", 'ovr-core' ),        'body' => (string) get_post_meta( $post_id, '_ovr_nearby', true ) ],
            [ 'icon' => 'policy',      'title' => __( 'Policies', 'ovr-core' ),             'body' => (string) get_post_meta( $post_id, '_ovr_policies', true ) ],
            [ 'icon' => 'payments',    'title' => __( 'Payment Information', 'ovr-core' ),   'body' => (string) get_post_meta( $post_id, '_ovr_payment_info', true ) ],
        ];
        foreach ( $extra_sections as $sec ) :
            $body = trim( (string) $sec['body'] );
            if ( '' === $body ) { continue; }
            ?>
            <section class="ovr-detail-section" data-purpose="owner-section">
                <div class="ovr-detail-card">
                    <h2 class="ovr-detail-heading">
                        <span class="material-symbols-outlined" style="vertical-align:-4px;margin-right:6px;color:var(--ovr-secondary)"><?php echo esc_html( $sec['icon'] ); ?></span>
                        <?php echo esc_html( $sec['title'] ); ?>
                    </h2>
                    <div class="ovr-tab-prose"><?php echo wp_kses_post( wpautop( $body ) ); ?></div>
                </div>
            </section>
        <?php endforeach; ?>

        <!-- Policies & Payment -->
        <?php
        echo TemplateLoader::get_rendered( 'property/policies.php', [
            'post_id' => $post_id,
            'meta'    => $meta,
        ] );
        ?>

        <!-- Documents & Resources (Feature D: ordered, titled downloads) -->
        <?php
        $docs = \OVR\Frontend\ListingForm::get_documents( $post_id );
        if ( ! empty( $docs ) ) :
        ?>
            <section class="ovr-detail-section" data-purpose="documents">
                <div class="ovr-detail-card">
                    <h2 class="ovr-detail-heading"><?php esc_html_e( 'Documents & Resources', 'ovr-core' ); ?></h2>
                    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:12px">
                        <?php foreach ( $docs as $doc ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener"
                                   style="display:inline-flex;align-items:center;gap:10px;padding:12px 18px;background:var(--ovr-surface-container-low);border:1px solid var(--ovr-border-gray);border-radius:var(--ovr-radius-md);text-decoration:none;color:var(--ovr-on-surface);font-weight:600;font-size:14px">
                                    <span class="material-symbols-outlined" style="color:var(--ovr-secondary)">description</span>
                                    <?php echo esc_html( $doc['title'] ?: $doc['filename'] ); ?>
                                    <?php if ( $doc['ext'] ) : ?>
                                        <span style="font-size:11px;font-weight:700;color:var(--ovr-on-surface-variant)">· <?php echo esc_html( $doc['ext'] ); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <!-- Map & Location (video lives in the hero; panorama opens via the Virtual Tour button) -->
        <?php if ( $lat && $lng ) :
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

<?php if ( $has_tour ) : ?>
<!-- Virtual Tour modal (Feature C): tour links open in an iframe; uploaded 360
     images open in a Pannellum equirectangular viewer (loaded on demand). -->
<div class="ovr-tour-modal" id="ovr-tour-modal" hidden
     data-tour-type="<?php echo $pano_url ? 'embed' : 'pano'; ?>"
     data-tour-src="<?php echo esc_url( $pano_url ?: $pano_src ); ?>">
    <div class="ovr-tour-backdrop" data-ovr-tour-close></div>
    <div class="ovr-tour-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Virtual Tour', 'ovr-core' ); ?>">
        <button type="button" class="ovr-tour-close" data-ovr-tour-close aria-label="<?php esc_attr_e( 'Close', 'ovr-core' ); ?>">
            <span class="material-symbols-outlined">close</span>
        </button>
        <div class="ovr-tour-stage" id="ovr-tour-stage"></div>
    </div>
</div>
<style>
    .ovr-virtual-tour-btn{display:inline-flex;align-items:center;gap:8px;margin-top:14px;padding:11px 20px;border-radius:9999px;border:1px solid var(--ovr-primary,#006c4a);background:var(--ovr-primary,#006c4a);color:#fff;font-weight:700;font-size:14px;cursor:pointer}
    .ovr-virtual-tour-btn:hover{filter:brightness(.95)}
    .ovr-tour-modal{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center}
    .ovr-tour-modal[hidden]{display:none}
    .ovr-tour-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.82)}
    .ovr-tour-dialog{position:relative;width:min(1100px,94vw);height:min(680px,86vh);background:#000;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.5)}
    .ovr-tour-close{position:absolute;top:12px;right:12px;z-index:2;width:42px;height:42px;border-radius:9999px;border:none;background:rgba(0,0,0,.55);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center}
    .ovr-tour-close:hover{background:rgba(0,0,0,.8)}
    .ovr-tour-stage{width:100%;height:100%}
    .ovr-tour-stage iframe{width:100%;height:100%;border:0;display:block}
    .ovr-tour-stage img{width:100%;height:100%;object-fit:contain;background:#000}
</style>
<script>
(function(){
    var modal = document.getElementById('ovr-tour-modal');
    var openBtn = document.querySelector('[data-ovr-tour-open]');
    if (!modal || !openBtn) { return; }
    var stage = document.getElementById('ovr-tour-stage');
    var type = modal.getAttribute('data-tour-type');
    var src  = modal.getAttribute('data-tour-src');
    var built = false, panoViewer = null;

    function loadPannellum(cb){
        if (window.pannellum) { cb(); return; }
        var css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css';
        document.head.appendChild(css);
        var js = document.createElement('script');
        js.src = 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js';
        js.onload = cb;
        js.onerror = function(){ // Fallback: show the panorama as a plain scrollable image.
            stage.innerHTML = '<img src="' + src + '" alt="">';
        };
        document.head.appendChild(js);
    }
    function build(){
        if (built) { return; }
        built = true;
        if (type === 'embed') {
            stage.innerHTML = '<iframe src="' + src + '" allow="fullscreen; xr-spatial-tracking; gyroscope; accelerometer" allowfullscreen></iframe>';
        } else {
            loadPannellum(function(){
                if (window.pannellum) {
                    panoViewer = window.pannellum.viewer('ovr-tour-stage', { type:'equirectangular', panorama: src, autoLoad:true, showControls:true });
                } else {
                    stage.innerHTML = '<img src="' + src + '" alt="">';
                }
            });
        }
    }
    function open(){ build(); modal.hidden = false; document.body.style.overflow = 'hidden'; }
    function close(){ modal.hidden = true; document.body.style.overflow = ''; }
    openBtn.addEventListener('click', open);
    modal.querySelectorAll('[data-ovr-tour-close]').forEach(function(el){ el.addEventListener('click', close); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && !modal.hidden) { close(); } });
})();
</script>
<?php endif; ?>
<?php
get_footer();
