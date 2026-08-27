<?php
/**
 * Search Results Template.
 *
 * @var WP_Query $query
 * @var array    $filters
 * @var int      $total
 * @var int      $max_pages
 * @var int      $paged
 * @var string   $view
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Property\PropertyCard;
use OVR\Property\PropertyQuery;
use OVR\Search\SearchFilters;
use OVR\Core\TemplateLoader;
use OVR\Core\Pages;

$view      = $view ?? 'grid';
$paged     = max( 1, (int) ( $paged ?? 1 ) );
$max_pages = max( 1, (int) ( $max_pages ?? 1 ) );
$total     = (int) ( $total ?? 0 );
$per_page  = (int) ( $filters['per_page'] ?? 12 );

$villages       = SearchFilters::get_villages();
$property_types = SearchFilters::get_property_types();
$bedroom_opts   = SearchFilters::get_bedroom_options();

$base_search_url = Pages::get_page_url( 'ovr_page_search' );

// When the visitor arrived via a landlord's "View Listings" button the results
// are scoped to a single owner. In that mode we drop the cross-owner chrome
// (Featured rail + Featured Cities strip) and label the page with the owner's
// name, so it reads as "this landlord's listings" rather than the general search.
$owner_id     = (int) ( $filters['owner_id'] ?? 0 );
$owner_active = $owner_id > 0;
$owner_name   = $owner_active ? get_the_author_meta( 'display_name', $owner_id ) : '';

// Helper for view / pagination URL building (preserves active filters + view).
$build_url = static function( array $overrides ) use ( $filters, $base_search_url, $view ): string {
    $clean  = array_filter( $filters, static fn( $v ) => $v !== '' && $v !== 0 && $v !== [] && $v !== false );
    // Always carry the active results view so paging/filtering keeps grid/list/map.
    $merged = array_merge( $clean, [ 'view' => $view ], $overrides );
    return $base_search_url . '?' . http_build_query( $merged );
};

// Exact current filtered + paginated results URL. Stamped onto every listing
// link (?ovr_ref=) so a listing's "Back To Search Results" returns the visitor
// to this precise view — same filters, same page — instead of the homepage.
$results_ref = $build_url( [ 'paged' => $paged ] );

// "Showing X–Y of Z" range.
$range_start = $total > 0 ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
$range_end   = min( $total, $paged * $per_page );

// Check if any search filter is actively set. When no filters are applied we
// prepend featured listings at the top of the grid (page 1 only); when filters
// are active the results are purely query-driven — featured status is still
// shown as a badge but does not affect ordering.
$has_active_filters = (
    '' !== ( $filters['keyword'] ?? '' ) ||
    ! empty( $filters['village'] ) ||
    '' !== ( $filters['village_name'] ?? '' ) ||
    ! empty( $filters['village_section'] ) ||
    ! empty( $filters['property_type'] ) ||
    ! empty( $filters['rental_type'] ) ||
    ! empty( $filters['amenities'] ) ||
    ! empty( $filters['views'] ) ||
    ! empty( $filters['features'] ) ||
    (int) ( $filters['bedrooms'] ?? 0 ) > 0 ||
    (float) ( $filters['bathrooms'] ?? 0 ) > 0 ||
    (float) ( $filters['price_min'] ?? 0 ) > 0 ||
    (float) ( $filters['price_max'] ?? 0 ) > 0 ||
    (int) ( $filters['guests'] ?? 0 ) > 0 ||
    ! empty( $filters['pets'] ) ||
    ! empty( array_filter( (array) ( $filters['golf_cart'] ?? [] ) ) ) ||
    ! empty( $filters['deals_only'] ) ||
    ! empty( $filters['featured_only'] ) ||
    '' !== ( $filters['checkin'] ?? '' ) ||
    '' !== ( $filters['checkout'] ?? '' ) ||
    (int) ( $filters['owner_id'] ?? 0 ) > 0 ||
    'newest' !== ( $filters['sort'] ?? 'newest' )
);

// Village Section strip (Chunk 1 §16-21): the top row of cards are DIRECT
// SHORTCUTS, not a second copy of the detailed sidebar facet. Each chip link
// is built CLEAN from the bare search URL — no other filter state is
// inherited, so a stale "Eastport" village selection or an unrelated
// property-type filter can never survive a shortcut click. Clicking a chip
// executes the search immediately (AJAX region swap with fallback to normal
// navigation); after landing, detailed filters can deliberately narrow it.
//
// Highlight rule (§20): at most ONE chip may be active. A chip is highlighted
// only when EXACTLY one village_section is selected and it matches — so
// multi-selecting sections in the detailed sidebar never lights up several
// shortcuts simultaneously.
//
// The first chip is always "All Villages" — the neutral state that clears the
// section restriction and restores every eligible listing without a reload.
// Skipped on single-owner views ("Listings by [owner]"), where it has no place.
$section_chips = [];
if ( ! $owner_active ) {
    $sel_sections    = array_map( 'strval', (array) ( $filters['village_section'] ?? [] ) );
    $single_section  = ( 1 === count( $sel_sections ) ) ? (string) $sel_sections[0] : '';

    // Clean shortcut URL builder: bare search page + ONLY the given params
    // (+ the active results view). Nothing else from the current query string
    // survives — this is what makes the chips conflict-free by construction.
    $clean_url = static function( array $params ) use ( $base_search_url, $view ): string {
        $params['view'] = in_array( $view, [ 'grid', 'list', 'map' ], true ) ? $view : 'grid';
        return $base_search_url . '?' . http_build_query( $params );
    };

    // "All Villages" — the default/unfiltered state; first in the strip. Uses
    // the "The Villages" section's image (the community-wide artwork) when one
    // is assigned; otherwise falls back to the bundled stone-wall "The Villages"
    // banner so it reads as a real community tile, not a plain icon box.
    $all_img = OVR_PLUGIN_URL . 'assets/images/the-villages-banner.svg';
    $all_term = get_term_by( 'slug', 'the-villages', 'ovr_village' );
    if ( $all_term && ! is_wp_error( $all_term ) ) {
        $term_img = SearchFilters::get_village_image( $all_term );
        if ( '' !== $term_img ) {
            $all_img = $term_img;
        }
    }
    $section_chips[] = [
        'name'   => __( 'All Villages', 'ovr-core' ),
        'image'  => $all_img,
        'active' => empty( $sel_sections ),
        'url'    => $clean_url( [] ),
        'all'    => true,
    ];

    foreach ( $villages as $v ) {
        $active = ( '' !== $single_section && $single_section === (string) $v->slug );
        $section_chips[] = [
            'name'   => $v->name,
            'image'  => SearchFilters::get_village_image( $v ),
            'active' => $active,
            // A clean one-section shortcut — every time, even when re-clicking
            // the currently-active chip (idempotent reset of that section).
            'url'    => $clean_url( [ 'village_section' => [ $v->slug ] ] ),
            'all'    => false,
        ];
    }
}
?>
<div class="ovr-wrap ovr-search-stitch">

    <!-- Village Section filter strip — hidden on full-page map (Option 2: map has no filters) -->
    <?php if ( ! empty( $section_chips ) && 'map' !== $view ) : ?>
        <style>
            .ovr-ss-village.is-active{position:relative}
            .ovr-ss-village.is-active .ovr-ss-village-img{outline:3px solid var(--ovr-gold,#DEAF0C);outline-offset:2px;border-radius:8px}
            .ovr-ss-village.is-active .ovr-ss-village-name{color:var(--ovr-gold,#DEAF0C);font-weight:700}
        </style>
        <section class="ovr-ss-villages" aria-label="<?php esc_attr_e( 'Filter by village section', 'ovr-core' ); ?>">
            <div class="ovr-ss-villages-inner">
                <?php foreach ( $section_chips as $chip ) : ?>
                    <?php if ( ! empty( $chip['all'] ) ) : ?>
                        <a class="ovr-ss-village ovr-ss-village--all<?php echo $chip['active'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $chip['url'] ); ?>"<?php echo $chip['active'] ? ' aria-current="true"' : ''; ?>>
                            <span class="ovr-ss-village-img ovr-ss-village-img--all">
                                <?php if ( ! empty( $chip['image'] ) ) : ?>
                                    <img src="<?php echo esc_url( $chip['image'] ); ?>" alt="<?php echo esc_attr( $chip['name'] ); ?>" loading="lazy">
                                <?php else : ?>
                                    <span class="material-symbols-outlined" aria-hidden="true">grid_view</span>
                                <?php endif; ?>
                            </span>
                            <span class="ovr-ss-village-name"><?php echo esc_html( $chip['name'] ); ?></span>
                        </a>
                    <?php else : ?>
                        <a class="ovr-ss-village<?php echo $chip['active'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $chip['url'] ); ?>"<?php echo $chip['active'] ? ' aria-current="true"' : ''; ?>>
                            <span class="ovr-ss-village-img">
                                <img src="<?php echo esc_url( $chip['image'] ); ?>" alt="<?php echo esc_attr( $chip['name'] ); ?>" loading="lazy">
                            </span>
                            <span class="ovr-ss-village-name"><?php echo esc_html( $chip['name'] ); ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="ovr-container ovr-section ovr-search-page <?php echo 'map' === $view ? 'ovr-search-page--map' : ''; ?>">
        <?php if ( 'map' === $view ) : ?>
            <!-- Full-page map (Option 2): no left filters — header with view toggle + map canvas -->
            <div class="ovr-search-main ovr-search-main--map-full">
        <?php else : ?>
        <div class="ovr-search-layout">

            <!-- Filters sidebar -->
            <?php
            echo TemplateLoader::get_rendered( 'search/filters-sidebar.php', [
                'filters'        => $filters,
                'villages'       => $villages,
                'property_types' => $property_types,
                'bedroom_opts'   => $bedroom_opts,
                'form_action'    => $base_search_url,
            ] );
            ?>

            <!-- Results column -->
            <div class="ovr-search-main">
        <?php endif; ?>

                <!-- Results header (captured once; reused across views) -->
                <?php ob_start(); ?>
                <div class="ovr-results-header">
                    <div>
                        <h2 class="ovr-results-title">
                            <?php
                            if ( ! empty( $filters['deals_only'] ) ) {
                                esc_html_e( 'Deals & Cancellations', 'ovr-core' );
                            } elseif ( $owner_active && '' !== $owner_name ) {
                                /* translators: %s: landlord / owner display name. */
                                printf( esc_html__( 'Listings by %s', 'ovr-core' ), esc_html( $owner_name ) );
                            } else {
                                esc_html_e( 'Available Rentals', 'ovr-core' );
                            }
                            ?>
                        </h2>
                        <p class="ovr-results-count">
                            <?php if ( $total > 0 && $has_active_filters ) : ?>
                                <?php
                                /* translators: 1: first result number, 2: last result number, 3: total results. */
                                printf(
                                    esc_html__( 'Showing %1$s–%2$s of %3$s listings', 'ovr-core' ),
                                    esc_html( number_format( $range_start ) ),
                                    esc_html( number_format( $range_end ) ),
                                    esc_html( number_format( $total ) )
                                );
                                ?>
                            <?php elseif ( 0 === $total ) : ?>
                                <?php esc_html_e( 'No listings match your filters', 'ovr-core' ); ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="ovr-results-controls">
                        <!-- View toggle (Grid / List are primary) -->
                        <div class="ovr-view-group" role="group" aria-label="<?php esc_attr_e( 'Results view', 'ovr-core' ); ?>">
                            <?php
                            $views = [
                                'grid' => [ 'grid_view', __( 'Grid view', 'ovr-core' ) ],
                                'list' => [ 'view_list', __( 'List view', 'ovr-core' ) ],
                            ];
                            foreach ( $views as $key => $v ) :
                            ?>
                                <a href="<?php echo esc_url( $build_url( [ 'view' => $key ] ) ); ?>"
                                   class="ovr-view-toggle <?php echo $key === $view ? 'is-active' : ''; ?>"
                                   aria-label="<?php echo esc_attr( $v[1] ); ?>"
                                   <?php echo $key === $view ? 'aria-current="true"' : ''; ?>>
                                    <span class="material-symbols-outlined"><?php echo esc_html( $v[0] ); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- Map is a quiet, secondary option, tucked beside the main views. -->
                        <a href="<?php echo esc_url( $build_url( [ 'view' => 'map' ] ) ); ?>"
                           class="ovr-map-toggle <?php echo 'map' === $view ? 'is-active' : ''; ?>"
                           aria-label="<?php esc_attr_e( 'Map view', 'ovr-core' ); ?>"
                           <?php echo 'map' === $view ? 'aria-current="true"' : ''; ?>>
                            <span class="material-symbols-outlined">place</span>
                            <span class="ovr-map-toggle-label"><?php esc_html_e( 'Map', 'ovr-core' ); ?></span>
                        </a>

                        <!-- Compact top pager -->
                        <?php if ( $max_pages > 1 ) : ?>
                            <div class="ovr-toppager">
                                <?php if ( $paged > 1 ) : ?>
                                    <a class="ovr-toppager-btn" href="<?php echo esc_url( $build_url( [ 'paged' => $paged - 1 ] ) ); ?>" aria-label="<?php esc_attr_e( 'Previous page', 'ovr-core' ); ?>">
                                        <span class="material-symbols-outlined">chevron_left</span>
                                    </a>
                                <?php else : ?>
                                    <span class="ovr-toppager-btn is-disabled"><span class="material-symbols-outlined">chevron_left</span></span>
                                <?php endif; ?>
                                <span class="ovr-toppager-label">
                                    <?php
                                    /* translators: %s: current page number. */
                                    printf( esc_html__( 'Page %s', 'ovr-core' ), esc_html( (string) $paged ) );
                                    ?>
                                </span>
                                <?php if ( $paged < $max_pages ) : ?>
                                    <a class="ovr-toppager-btn" href="<?php echo esc_url( $build_url( [ 'paged' => $paged + 1 ] ) ); ?>" aria-label="<?php esc_attr_e( 'Next page', 'ovr-core' ); ?>">
                                        <span class="material-symbols-outlined">chevron_right</span>
                                    </a>
                                <?php else : ?>
                                    <span class="ovr-toppager-btn is-disabled"><span class="material-symbols-outlined">chevron_right</span></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $results_header = ob_get_clean(); ?>

                <!-- Results -->
                <?php if ( $query->have_posts() || 'map' === $view ) : // map always renders (may be empty) ?>
                    <?php
                    // "Search In A Villages Section" heading when exactly one village
                    // section is filtered (P8 §9): a clear section title above the
                    // results with a little breathing room beneath the photos strip.
                    $sel_sections = array_map( 'strval', (array) ( $filters['village_section'] ?? [] ) );
                    if ( 1 === count( $sel_sections ) ) {
                        $sec_term = get_term_by( 'slug', $sel_sections[0], 'ovr_village' );
                        if ( $sec_term && ! is_wp_error( $sec_term ) ) :
                        ?>
                            <h3 class="ovr-section-results-heading" style="font-size:20px;font-weight:600;color:var(--ovr-on-surface,#1c2430);margin:8px 0 18px"><?php printf( esc_html__( 'Search In %s', 'ovr-core' ), esc_html( $sec_term->name ) ); ?></h3>
                        <?php endif;
                    } elseif ( ! empty( $filters['featured_only'] ) ) {
                        // Featured subset view reuses the standard results format
                        // (P8 §9): a clear heading above the results grid.
                        ?>
                            <h3 class="ovr-section-results-heading" style="font-size:20px;font-weight:600;color:var(--ovr-on-surface,#1c2430);margin:8px 0 18px"><?php esc_html_e( 'Featured Properties', 'ovr-core' ); ?></h3>
                        <?php
                    }
                    ?>

                    <?php if ( 'map' === $view ) : ?>
                        <?php echo $results_header; // header with Grid/List/Map toggle — filter UI lives on Grid/Table (Option 2) ?>
                        <?php
                        // Full-width map (Option 2): no left filters — full-bleed
                        // canvas showing EVERY matching listing for active filters
                        // (mega-menu Map Search has no filters → ALL HOMES).
                        $map_points   = PropertyQuery::get_map_points( $filters );
                        $map_settings = get_option( 'ovr_settings', [] );
                        $map_symbol   = $map_settings['currency_symbol'] ?? '$';
                        ?>
                        <div class="ovr-map-full">

                            <!-- Full-bleed map canvas -->
                            <div class="ovr-map-view"
                                 data-ovr-map="<?php echo esc_attr( wp_json_encode( $map_points ) ); ?>"
                                 data-symbol="<?php echo esc_attr( $map_symbol ); ?>"
                                 role="application"
                                 aria-label="<?php esc_attr_e( 'Map of search results', 'ovr-core' ); ?>">
                                <?php if ( empty( $map_points ) ) : ?>
                                    <p class="ovr-map-empty">
                                        <span class="material-symbols-outlined">location_off</span>
                                        <?php esc_html_e( 'None of these listings have map coordinates yet. Add a latitude & longitude to your properties to plot them here.', 'ovr-core' ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php elseif ( 'list' === $view ) : ?>
                        <?php echo $results_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="ovr-search-results ovr-search-list">
                            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                                <?php echo PropertyCard::render_list( get_the_ID(), $results_ref ); ?>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    <?php else : ?>
                        <?php echo $results_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="ovr-search-results ovr-results-fullgrid">
                            <?php
                            // Featured listings float to the top of the grid via
                            // the query's boost ordering (PropertyQuery::
                            // boost_order_clauses) — but ONLY when they match the
                            // active search filters, since ordering acts on the
                            // already-filtered set. We no longer inject featured
                            // listings from a separate, filter-blind query, so an
                            // unrelated featured listing is never shown (Mark P2).
                            while ( $query->have_posts() ) {
                                $query->the_post();
                                $cid = (int) get_the_ID();
                                echo PropertyCard::render_search( $cid, \OVR\Subscription\UpgradeActivator::is_active( $cid, 'featured' ), $results_ref ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            wp_reset_postdata();
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Bottom pagination (not shown in map view) -->
                    <?php if ( 'map' !== $view && $max_pages > 1 ) : ?>
                        <nav class="ovr-pagination" aria-label="<?php esc_attr_e( 'Search results pages', 'ovr-core' ); ?>">

                            <?php if ( $paged > 1 ) : ?>
                                <a href="<?php echo esc_url( $build_url( [ 'paged' => $paged - 1 ] ) ); ?>" class="ovr-page-step"><?php esc_html_e( 'Previous', 'ovr-core' ); ?></a>
                            <?php else : ?>
                                <span class="ovr-page-step is-disabled"><?php esc_html_e( 'Previous', 'ovr-core' ); ?></span>
                            <?php endif; ?>

                            <div class="ovr-page-numbers">
                                <?php
                                $window = 2;
                                $start  = max( 1, $paged - $window );
                                $end    = min( $max_pages, $paged + $window );

                                if ( $start > 1 ) : ?>
                                    <a href="<?php echo esc_url( $build_url( [ 'paged' => 1 ] ) ); ?>" class="ovr-page-link">1</a>
                                    <?php if ( $start > 2 ) : ?><span class="ovr-page-ellipsis">…</span><?php endif;
                                endif;

                                for ( $i = $start; $i <= $end; $i++ ) : ?>
                                    <a href="<?php echo esc_url( $build_url( [ 'paged' => $i ] ) ); ?>"
                                       class="ovr-page-link <?php echo $i === $paged ? 'is-current' : ''; ?>"
                                       <?php echo $i === $paged ? 'aria-current="page"' : ''; ?>>
                                        <?php echo esc_html( (string) $i ); ?>
                                    </a>
                                <?php endfor;

                                if ( $end < $max_pages ) :
                                    if ( $end < $max_pages - 1 ) : ?><span class="ovr-page-ellipsis">…</span><?php endif; ?>
                                    <a href="<?php echo esc_url( $build_url( [ 'paged' => $max_pages ] ) ); ?>" class="ovr-page-link"><?php echo esc_html( (string) $max_pages ); ?></a>
                                <?php endif; ?>
                            </div>

                            <?php if ( $paged < $max_pages ) : ?>
                                <a href="<?php echo esc_url( $build_url( [ 'paged' => $paged + 1 ] ) ); ?>" class="ovr-page-step is-next"><?php esc_html_e( 'Next', 'ovr-core' ); ?></a>
                            <?php else : ?>
                                <span class="ovr-page-step is-disabled"><?php esc_html_e( 'Next', 'ovr-core' ); ?></span>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>

                <?php else : ?>
                    <?php echo $results_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php
                    echo TemplateLoader::get_rendered( 'search/no-results.php', [
                        'keyword'   => (string) ( $filters['keyword'] ?? '' ),
                        'filters'   => $filters,
                        'reset_url' => $base_search_url,
                    ] );
                    ?>
                <?php endif; ?>
            </div>
        <?php if ( 'map' !== $view ) : ?>
        </div>
        <?php endif; ?>
    </div>
</div>
