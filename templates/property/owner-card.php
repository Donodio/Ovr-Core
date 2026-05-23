<?php
/**
 * Owner / Listing Summary Sidebar.
 *
 * Compact stacked cards (DESIGN.md §10):
 *   1. Title + type/village + flexible pricing + gold "Inquire" + compare/view.
 *   2. Owner / Property Manager (label + verified badge inline, small photo).
 *   3. Owner comments (full sidebar width).
 *   4. QR code + monthly-visits bar chart — visible to the listing owner only.
 *
 * @package OVR
 *
 * @var int    $post_id        Required. Property post ID.
 * @var string $title          Property title (shown at the top of the sidebar).
 * @var float  $base_price     Nightly base rate.
 * @var string $symbol         Currency symbol.
 * @var string $property_type  Property type label (e.g. "Patio Villa").
 * @var int    $bedrooms       Bedroom count.
 * @var \WP_Term|null $village  Primary village term.
 * @var bool   $has_seasonal   Whether seasonal rates exist (flexible-pricing note).
 * @var int    $views          Total page-view count.
 * @var array  $monthly_views  Map of 'Y-m' => count for the visits chart.
 * @var bool   $is_owner       Whether the current user owns this listing.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\Pages;

$post_id       = (int) ( $post_id ?? get_the_ID() );
$title         = (string) ( $title ?? get_the_title( $post_id ) );
$base_price    = (float) ( $base_price ?? 0 );
$symbol        = $symbol ?? '$';
$property_type = (string) ( $property_type ?? '' );
$bedrooms      = (int) ( $bedrooms ?? 0 );
$village       = $village ?? null;
$has_seasonal  = ! empty( $has_seasonal );
$views         = (int) ( $views ?? 0 );
$monthly_views = is_array( $monthly_views ?? null ) ? $monthly_views : [];
$is_owner      = ! empty( $is_owner );

// Sub-line under the title: "Patio Villa · 2 Bedrooms · Village of X".
$subbits = array_filter( [
    $property_type,
    $bedrooms > 0
        /* translators: %d: bedroom count */
        ? sprintf( _n( '%d Bedroom', '%d Bedrooms', $bedrooms, 'ovr-core' ), $bedrooms )
        : '',
    $village
        /* translators: %s: village name */
        ? sprintf( __( 'Village of %s', 'ovr-core' ), $village->name )
        : '',
] );

// Owner (post author).
$author_id   = (int) get_post_field( 'post_author', $post_id );
$owner_name  = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
$owner_phone = $author_id ? (string) get_user_meta( $author_id, 'ovr_phone', true ) : '';
$owner_url   = $author_id ? get_author_posts_url( $author_id ) : '';
$avatar_url  = $author_id ? get_avatar_url( $author_id, [ 'size' => 120 ] ) : '';
$listings_n  = $author_id ? (int) count_user_posts( $author_id, 'ovr_property', true ) : 0;

$is_verified = $author_id
    ? (bool) apply_filters( 'ovr_owner_is_verified', (bool) get_user_meta( $author_id, 'ovr_verified', true ), $author_id, $post_id )
    : false;

$owner_comments = trim( (string) get_post_meta( $post_id, '_ovr_owner_comments', true ) );

$compare_url = class_exists( Pages::class ) ? Pages::get_page_url( 'ovr_page_search' ) : home_url( '/' );

$permalink = get_permalink( $post_id );
$qr_src    = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=0&data=' . rawurlencode( $permalink );

// Build the last 6 months for the visits bar chart.
$now    = current_time( 'timestamp' );
$chart  = [];
for ( $i = 5; $i >= 0; $i-- ) {
    $ts   = strtotime( "first day of -{$i} month", $now );
    $key  = wp_date( 'Y-m', $ts );
    $chart[] = [ 'label' => wp_date( 'M', $ts ), 'count' => (int) ( $monthly_views[ $key ] ?? 0 ) ];
}
$chart_max     = max( 1, max( array_column( $chart, 'count' ) ) );
$last_updated  = get_the_modified_date( get_option( 'date_format' ) ?: 'M j, Y', $post_id );
?>
<div class="ovr-owner-stack" data-purpose="owner-card">

    <!-- 1. Title + pricing + inquire + compare/view -->
    <div class="ovr-owner-card">
        <div class="ovr-owner-summary">
            <h2><?php echo esc_html( $title ); ?></h2>
            <?php if ( $subbits ) : ?>
                <p><?php echo esc_html( implode( ' · ', $subbits ) ); ?></p>
            <?php endif; ?>
        </div>

        <?php if ( $base_price > 0 ) : ?>
            <div class="ovr-owner-price">
                <span class="ovr-owner-price-label"><?php esc_html_e( 'Pricing', 'ovr-core' ); ?></span>
                <span class="ovr-owner-price-amount">
                    <?php echo esc_html( $symbol . number_format( $base_price, 0 ) ); ?><span>/ <?php esc_html_e( 'night', 'ovr-core' ); ?></span>
                </span>
            </div>
            <?php if ( $has_seasonal ) : ?>
                <p class="ovr-owner-price-note"><?php esc_html_e( 'Seasonal & weekly/monthly rates available below.', 'ovr-core' ); ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <a href="#ovr-inquiry" class="ovr-btn ovr-btn-full ovr-inquire-cta">
            <span class="material-symbols-outlined">mail</span>
            <?php esc_html_e( 'Inquire – Email Owner', 'ovr-core' ); ?>
        </a>

        <div class="ovr-owner-actions">
            <button type="button" class="ovr-owner-action" data-ovr-compare="<?php echo esc_attr( $post_id ); ?>">
                <span class="material-symbols-outlined">playlist_add</span>
                <?php esc_html_e( 'Add to Compare List', 'ovr-core' ); ?>
            </button>
            <a class="ovr-owner-view" href="<?php echo esc_url( $compare_url ); ?>"><?php esc_html_e( 'View', 'ovr-core' ); ?></a>
        </div>
    </div>

    <!-- 2. Owner / Property Manager -->
    <div class="ovr-owner-card ovr-owner-pm">
        <div class="ovr-owner-pm-head">
            <p class="ovr-owner-block-label"><?php esc_html_e( 'Owner / Property Manager', 'ovr-core' ); ?></p>
            <?php if ( $is_verified ) : ?>
                <span class="ovr-verified-banner is-verified">
                    <span class="material-symbols-outlined">verified</span><?php esc_html_e( 'Verified', 'ovr-core' ); ?>
                </span>
            <?php else : ?>
                <span class="ovr-verified-banner is-unverified">
                    <span class="material-symbols-outlined">gpp_maybe</span><?php esc_html_e( 'Not Verified', 'ovr-core' ); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="ovr-owner-person">
            <?php if ( $avatar_url ) : ?>
                <img class="ovr-owner-photo" src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $owner_name ); ?>" loading="lazy">
            <?php endif; ?>
            <div class="ovr-owner-info">
                <?php if ( $owner_name ) : ?>
                    <h5 class="ovr-owner-name"><?php echo esc_html( $owner_name ); ?></h5>
                <?php endif; ?>
                <p class="ovr-owner-line">
                    <a href="#ovr-inquiry"><?php esc_html_e( 'Email Owner', 'ovr-core' ); ?></a>
                    <?php if ( $owner_phone ) : ?>
                        <span aria-hidden="true">·</span>
                        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $owner_phone ) ); ?>"><?php echo esc_html( $owner_phone ); ?></a>
                    <?php endif; ?>
                </p>
                <p class="ovr-owner-line ovr-owner-listings-count">
                    <?php
                    /* translators: %d: number of active listings */
                    printf( esc_html( _n( '%d listing', '%d listings', $listings_n, 'ovr-core' ) ), $listings_n );
                    ?>
                </p>
            </div>
        </div>

        <?php if ( $owner_url ) : ?>
            <a href="<?php echo esc_url( $owner_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-full ovr-owner-listings-btn">
                <?php esc_html_e( 'View Listings', 'ovr-core' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- 3. Owner comments -->
    <div class="ovr-owner-card ovr-owner-comments-card">
        <p class="ovr-owner-block-label"><?php esc_html_e( 'Owner Comments', 'ovr-core' ); ?></p>
        <?php if ( $owner_comments ) : ?>
            <p class="ovr-owner-comments-text">&ldquo;<?php echo esc_html( $owner_comments ); ?>&rdquo;</p>
        <?php else : ?>
            <p class="ovr-owner-comments-text is-empty"><?php esc_html_e( 'No comments from the owner yet.', 'ovr-core' ); ?></p>
        <?php endif; ?>
    </div>

    <!-- 4. QR + visits chart (owner only) -->
    <?php if ( $is_owner ) : ?>
        <div class="ovr-owner-card ovr-owner-meta-card">
            <div class="ovr-owner-meta-head">
                <p class="ovr-owner-block-label"><?php esc_html_e( 'Listing Performance', 'ovr-core' ); ?></p>
                <span class="ovr-owner-meta-total">
                    <?php
                    /* translators: %s: total view count */
                    printf( esc_html__( '%s total views', 'ovr-core' ), esc_html( number_format_i18n( $views ) ) );
                    ?>
                </span>
            </div>
            <div class="ovr-owner-meta-body">
                <figure class="ovr-visits-chart" aria-label="<?php esc_attr_e( 'Monthly visits, last 6 months', 'ovr-core' ); ?>">
                    <div class="ovr-visits-bars">
                        <?php foreach ( $chart as $bar ) :
                            $pct = (int) round( ( $bar['count'] / $chart_max ) * 100 );
                        ?>
                            <div class="ovr-visits-col" title="<?php
                                /* translators: 1: month, 2: visit count */
                                echo esc_attr( sprintf( __( '%1$s: %2$d visits', 'ovr-core' ), $bar['label'], $bar['count'] ) ); ?>">
                                <span class="ovr-visits-track"><span class="ovr-visits-bar" style="--ovr-bar:<?php echo esc_attr( max( 4, $pct ) ); ?>%"></span></span>
                                <span class="ovr-visits-label"><?php echo esc_html( $bar['label'] ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <figcaption class="ovr-visits-cap"><?php esc_html_e( 'Monthly visits', 'ovr-core' ); ?></figcaption>
                </figure>
                <div class="ovr-owner-qr">
                    <img src="<?php echo esc_url( $qr_src ); ?>" alt="<?php esc_attr_e( 'Scan to open this listing', 'ovr-core' ); ?>" loading="lazy">
                    <span><?php esc_html_e( 'QR code', 'ovr-core' ); ?></span>
                </div>
            </div>
            <p class="ovr-owner-meta-foot">
                <?php
                /* translators: 1: listing ID, 2: last updated date */
                printf( esc_html__( 'ID %1$s · Updated %2$s', 'ovr-core' ), esc_html( (string) $post_id ), esc_html( $last_updated ) );
                ?>
            </p>
        </div>
    <?php endif; ?>
</div>
