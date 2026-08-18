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
 * @var string $title          Property title (already shown in the page header bar).
 * @var float  $base_price     Nightly base rate.
 * @var string $symbol         Currency symbol.
 * @var string $property_type  Property type label (e.g. "Patio Villa").
 * @var int    $bedrooms       Bedroom count.
 * @var string $rental_type    Rental type label (e.g. "Short Term").
 * @var \WP_Term|null $village  Primary village term.
 * @var array  $pricing        Rows from SeasonalPricing::get_pricing() (for the range).
 * @var bool   $has_seasonal   Whether seasonal rates exist (flexible-pricing note).
 * @var int    $views          Total page-view count.
 * @var array  $monthly_views  Map of 'Y-m' => count for the visits chart.
 * @var bool   $is_owner       Whether the current user owns this listing.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\Pages;
use OVR\Property\SeasonalPricing;

$post_id       = (int) ( $post_id ?? get_the_ID() );
$title         = (string) ( $title ?? get_the_title( $post_id ) );
$base_price    = (float) ( $base_price ?? 0 );
$symbol        = $symbol ?? '$';
$property_type = (string) ( $property_type ?? '' );
$bedrooms      = (int) ( $bedrooms ?? 0 );
$rental_type   = (string) ( $rental_type ?? '' );
$village       = $village ?? null;
$pricing       = is_array( $pricing ?? null ) ? $pricing : SeasonalPricing::get_pricing( $post_id );
$has_seasonal  = isset( $has_seasonal ) ? ! empty( $has_seasonal ) : ! empty( $pricing );
$views         = (int) ( $views ?? 0 );
$monthly_views = is_array( $monthly_views ?? null ) ? $monthly_views : [];
$is_owner      = ! empty( $is_owner );

// The actual village name is the free-text meta (Phase 21) — e.g. "Mallory
// Square", "Bonnybrook". Fall back to the Village Section term only if it's blank.
$village_name = trim( (string) get_post_meta( $post_id, '_ovr_village_name', true ) );
if ( '' === $village_name && $village ) {
    $village_name = (string) $village->name;
}

// Headline: the listing title already sits in the page header bar, so this card
// leads with the location — "Village of Bonnybrook" — and puts the key facts
// (type · bedrooms · rental term) on the line beneath it.
$headline = '' !== $village_name
    /* translators: %s: village name */
    ? sprintf( __( 'Village of %s', 'ovr-core' ), $village_name )
    : $title;

$subbits = array_filter( [
    $property_type,
    $bedrooms > 0
        /* translators: %d: bedroom count */
        ? sprintf( _n( '%d Bedroom', '%d Bedrooms', $bedrooms, 'ovr-core' ), $bedrooms )
        : '',
    $rental_type,
] );

// ── Pricing line ──────────────────────────────────────────────────────────
// Mirrors the production site: either the low–high range across every rate row
// or "See Description For Pricing". The "Check Description For Pricing" override
// only controls this display — the rows themselves are never touched.
$pricing_hidden = SeasonalPricing::is_hidden( $post_id );
$price_amounts  = [];
$price_per      = '';
foreach ( $pricing as $prow ) {
    $pd = SeasonalPricing::row_display( (array) $prow );
    if ( $pd['price'] > 0 ) {
        $price_amounts[] = (float) $pd['price'];
        if ( '' === $price_per ) {
            $price_per = (string) $pd['per'];
        } elseif ( $price_per !== (string) $pd['per'] ) {
            $price_per = '—'; // Mixed terms (per week + per month): omit the unit.
        }
    }
}
$price_text = '';
$price_note = '';
if ( ! $pricing_hidden && $price_amounts ) {
    $low  = min( $price_amounts );
    $high = max( $price_amounts );
    if ( $low === $high ) {
        $price_text = $symbol . number_format( $low, 0 );
        if ( '' !== $price_per && '—' !== $price_per ) {
            /* translators: 1: price, 2: rate term (day/week/month) */
            $price_text = sprintf( __( '%1$s / %2$s', 'ovr-core' ), $price_text, $price_per );
        }
    } else {
        /* translators: 1: lowest rate, 2: highest rate */
        $price_text = sprintf(
            __( '%1$s – %2$s', 'ovr-core' ),
            $symbol . number_format( $low, 0 ),
            $symbol . number_format( $high, 0 )
        );
    }
    $price_note = __( 'See rates below for full details.', 'ovr-core' );
} else {
    $price_text = __( 'See Description For Pricing', 'ovr-core' );
}

// Owner (post author).
$author_id   = (int) get_post_field( 'post_author', $post_id );
$owner_name  = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
$owner_phone = $author_id ? (string) get_user_meta( $author_id, 'ovr_phone', true ) : '';
// "View Listings" → the search page filtered to this owner (Phase 22), not the
// WP author archive (which renders nothing useful for landlords).
$owner_url   = '';
if ( $author_id && class_exists( Pages::class ) ) {
    $owner_url = add_query_arg( 'owner_id', $author_id, Pages::get_page_url( 'ovr_page_search' ) );
}
$avatar_url  = $author_id ? get_avatar_url( $author_id, [ 'size' => 120 ] ) : '';
$listings_n  = $author_id ? (int) count_user_posts( $author_id, 'ovr_property', true ) : 0;
// Brief owner/property-manager bio (their user profile description).
$owner_bio   = $author_id ? trim( (string) get_the_author_meta( 'description', $author_id ) ) : '';

// Verification classification (P8 §9): sourced from the OWNER's user record so
// an admin change re-labels every listing they own. A simple YES/NO "OVR
// Verified" flag (boolean `ovr_verified` meta), with legacy 3-state status
// still honored via Verification::get().
$verif_status = $author_id ? \OVR\Core\Verification::get( $author_id ) : \OVR\Core\Verification::NOT_VERIFIED;
$verif_label  = \OVR\Core\Verification::label( $verif_status );
$is_verified  = \OVR\Core\Verification::is_verified( $verif_status );
$verif_icon   = \OVR\Core\Verification::icon( $verif_status );

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

    <!-- 1. Village + key facts + pricing + inquire + compare/view -->
    <div class="ovr-owner-card">
        <div class="ovr-owner-summary">
            <h2 class="ovr-owner-village"><?php echo esc_html( $headline ); ?></h2>
            <?php if ( $subbits ) : ?>
                <p><?php echo esc_html( implode( ' · ', $subbits ) ); ?></p>
            <?php endif; ?>
        </div>

        <div class="ovr-owner-price">
            <span class="ovr-owner-price-label"><?php esc_html_e( 'Pricing', 'ovr-core' ); ?></span>
            <span class="ovr-owner-price-amount<?php echo $price_note ? '' : ' is-text'; ?>">
                <?php echo esc_html( $price_text ); ?>
            </span>
        </div>
        <?php if ( '' !== $price_note ) : ?>
            <p class="ovr-owner-price-note"><?php echo esc_html( $price_note ); ?></p>
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
                    <span class="material-symbols-outlined"><?php echo esc_html( $verif_icon ); ?></span><?php echo esc_html( $verif_label ); ?>
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
                        <?php // Phone hidden until clicked (Phase 18 anti-spam). ?>
                        <span class="ovr-owner-phone" data-ovr-phone="<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $owner_phone ) ); ?>" data-ovr-phone-display="<?php echo esc_attr( $owner_phone ); ?>">
                            <button type="button" class="ovr-phone-reveal"><span class="material-symbols-outlined">call</span><?php esc_html_e( 'Show phone', 'ovr-core' ); ?></button>
                        </span>
                    <?php endif; ?>
                </p>
                <p class="ovr-owner-line ovr-owner-listings-count">
                    <?php
                    /* translators: %d: number of active listings */
                    printf( esc_html( _n( '%d listing', '%d listings', $listings_n, 'ovr-core' ) ), $listings_n );
                    ?>
                </p>
                <?php if ( '' !== $owner_bio ) : ?>
                    <p class="ovr-owner-bio"><?php echo esc_html( $owner_bio ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( $owner_url ) : ?>
            <a href="<?php echo esc_url( $owner_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-full ovr-owner-listings-btn">
                <?php esc_html_e( 'View Listings', 'ovr-core' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- QR + visits chart (owner only) -->
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

<style>
    /* Village headline: the card leads with the location, larger and bolder
       than the facts line beneath it (the listing title is in the header bar). */
    .ovr-owner-summary h2.ovr-owner-village{font-size:24px;font-weight:800;line-height:1.2;margin:0 0 6px;letter-spacing:-.01em}
    .ovr-owner-summary p{font-size:14px}
    /* "See Description For Pricing" is a sentence, not a figure — size it down
       so it doesn't wrap awkwardly at the 24px amount size. */
    .ovr-owner-price{align-items:center}
    .ovr-owner-price-amount.is-text{font-size:16px;font-weight:600;text-align:right}
    .ovr-owner-listings-count{color:var(--ovr-on-surface-variant)}
    .ovr-owner-bio{margin:6px 0 0;font-size:13px;line-height:1.4;color:var(--ovr-on-surface-variant)}
    .ovr-phone-reveal{display:inline-flex;align-items:center;gap:5px;background:none;border:none;padding:0;margin:0;font:inherit;color:var(--ovr-secondary,#0a66c2);font-weight:600;cursor:pointer;text-decoration:underline}
    .ovr-phone-reveal:hover{opacity:.85}
    .ovr-phone-reveal .material-symbols-outlined{font-size:16px}
    .ovr-owner-phone a{font-weight:600}
    /* OVR Verified Owner banner — shown ONLY when the owner is verified (YES);
       nothing renders otherwise. Gold trust badge with a check icon. */
    .ovr-verified-banner{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:999px;font-size:13px;font-weight:700;letter-spacing:.02em;line-height:1;white-space:nowrap}
    .ovr-verified-banner.is-verified{background:var(--ovr-gold,#DEAF0C);color:#1b1b20;box-shadow:0 1px 3px rgba(222,175,12,.4)}
    .ovr-verified-banner .material-symbols-outlined{font-size:18px}
</style>
<script>
(function(){
    var card = document.currentScript ? document.currentScript.previousElementSibling : null;
    // Delegate so it works regardless of where this partial is injected.
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.ovr-phone-reveal');
        if (!btn) { return; }
        var wrap = btn.closest('.ovr-owner-phone');
        if (!wrap) { return; }
        var tel = wrap.getAttribute('data-ovr-phone') || '';
        var disp = wrap.getAttribute('data-ovr-phone-display') || tel;
        var a = document.createElement('a');
        a.href = 'tel:' + tel;
        a.textContent = disp;
        wrap.innerHTML = '';
        wrap.appendChild(a);
        // Optional analytics hook (Phase 18): fire a custom event on reveal.
        try { document.dispatchEvent(new CustomEvent('ovr:phone-revealed', { detail: { tel: tel } })); } catch(_){}
    });
})();
</script>
