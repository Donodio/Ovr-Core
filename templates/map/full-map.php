<?php
/**
 * Full-screen Map Page Template.
 *
 * A clean, full-width clustered map of every published listing. The marker
 * plotting is handled by assets/js/ovr-search.js (setupMap), which binds to
 * `.ovr-map-view[data-ovr-map]`. Leaflet + the cluster plugin are enqueued for
 * this page in OVR\Core\Assets.
 *
 * @var array  $points  List of map points: [ id, title, url, thumb, price, beds, baths, lat, lng ].
 * @var string $symbol  Currency symbol for marker popups.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$points = is_array( $points ?? null ) ? $points : [];
$symbol = $symbol ?? '$';
$count  = count( $points );
?>
<div class="ovr-wrap ovr-map-page">
    <div class="ovr-container ovr-section">

        <header class="ovr-map-page-head">
            <h1 class="ovr-map-page-title"><?php esc_html_e( 'Explore Listings on the Map', 'ovr-core' ); ?></h1>
            <p class="ovr-map-page-sub">
                <?php
                if ( $count > 0 ) {
                    /* translators: %s: number of mapped listings. */
                    printf( esc_html( _n( '%s listing plotted', '%s listings plotted', $count, 'ovr-core' ) ), esc_html( number_format_i18n( $count ) ) );
                } else {
                    esc_html_e( 'No listings have map coordinates yet.', 'ovr-core' );
                }
                ?>
            </p>
        </header>

        <div class="ovr-map-view"
             data-ovr-map="<?php echo esc_attr( wp_json_encode( $points ) ); ?>"
             data-symbol="<?php echo esc_attr( $symbol ); ?>"
             role="application"
             aria-label="<?php esc_attr_e( 'Map of all listings', 'ovr-core' ); ?>">
            <?php if ( empty( $points ) ) : ?>
                <p class="ovr-map-empty">
                    <span class="material-symbols-outlined">location_off</span>
                    <?php esc_html_e( 'None of these listings have map coordinates yet. Add a latitude & longitude to your properties to plot them here.', 'ovr-core' ); ?>
                </p>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
    .ovr-map-page-head { text-align: center; margin: 0 0 24px; }
    .ovr-map-page-title { margin: 0 0 6px; font-size: clamp(26px, 4vw, 38px); line-height: 1.15; }
    .ovr-map-page-sub { margin: 0; color: var(--ovr-on-surface-variant, #3f4948); font-size: 16px; }
    .ovr-map-page .ovr-map-view { height: 78vh; min-height: 520px; }
</style>
