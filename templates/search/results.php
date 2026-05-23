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
use OVR\Admin\FeaturedCities;
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

// Helper for view / pagination URL building (preserves active filters).
$build_url = static function( array $overrides ) use ( $filters, $base_search_url ): string {
    $clean  = array_filter( $filters, static fn( $v ) => $v !== '' && $v !== 0 && $v !== [] && $v !== false );
    $merged = array_merge( $clean, $overrides );
    return $base_search_url . '?' . http_build_query( $merged );
};

// "Showing X–Y of Z" range.
$range_start = $total > 0 ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
$range_end   = min( $total, $paged * $per_page );

// Featured listings for the right-hand rail. Same equal-width grid track as
// the results, so featured cards line up row-for-row (grid view only).
$featured = ( 'list' !== $view ) ? PropertyQuery::get_featured( 4 ) : null;

// Featured Cities strip: admin-managed entries (Featured Cities portal) link to
// a keyword search; if none are configured, fall back to the village list.
$cities = FeaturedCities::get_items(); // [ ['name','image'], … ]
if ( ! empty( $cities ) ) {
    foreach ( $cities as &$city ) {
        $city['url'] = $build_url( [ 'keyword' => $city['name'], 'village' => [], 'paged' => 1 ] );
    }
    unset( $city );
} else {
    foreach ( array_slice( $villages, 0, 6 ) as $v ) {
        $cities[] = [
            'name'  => $v->name,
            'image' => SearchFilters::get_village_image( $v ),
            'url'   => $build_url( [ 'village' => [ $v->slug ], 'paged' => 1 ] ),
        ];
    }
}
?>
<div class="ovr-wrap ovr-search-stitch">

    <!-- Featured Cities strip -->
    <?php if ( ! empty( $cities ) ) : ?>
        <section class="ovr-ss-villages" aria-label="<?php esc_attr_e( 'Featured cities', 'ovr-core' ); ?>">
            <div class="ovr-ss-villages-inner">
                <?php foreach ( $cities as $city ) : ?>
                    <a class="ovr-ss-village" href="<?php echo esc_url( $city['url'] ); ?>">
                        <span class="ovr-ss-village-img">
                            <img src="<?php echo esc_url( $city['image'] ); ?>" alt="<?php echo esc_attr( $city['name'] ); ?>" loading="lazy">
                        </span>
                        <span class="ovr-ss-village-name"><?php echo esc_html( $city['name'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="ovr-container ovr-section ovr-search-page">
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

                <!-- Results header (captured once; reused across views) -->
                <?php ob_start(); ?>
                <div class="ovr-results-header">
                    <div>
                        <h2 class="ovr-results-title"><?php esc_html_e( 'Available Rentals', 'ovr-core' ); ?></h2>
                        <p class="ovr-results-count">
                            <?php if ( $total > 0 ) : ?>
                                <?php
                                /* translators: 1: first result number, 2: last result number, 3: total results. */
                                printf(
                                    esc_html__( 'Showing %1$s–%2$s of %3$s listings', 'ovr-core' ),
                                    esc_html( number_format( $range_start ) ),
                                    esc_html( number_format( $range_end ) ),
                                    esc_html( number_format( $total ) )
                                );
                                ?>
                            <?php else : ?>
                                <?php esc_html_e( 'No listings match your filters', 'ovr-core' ); ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="ovr-results-controls">
                        <!-- View toggle -->
                        <div class="ovr-view-group" role="group" aria-label="<?php esc_attr_e( 'Results view', 'ovr-core' ); ?>">
                            <?php
                            $views = [
                                'grid' => [ 'grid_view', __( 'Grid view', 'ovr-core' ) ],
                                'list' => [ 'view_list', __( 'List view', 'ovr-core' ) ],
                                'map'  => [ 'map', __( 'Map view', 'ovr-core' ) ],
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
                <?php $has_featured = ( $featured instanceof WP_Query && $featured->have_posts() ); ?>

                <!-- Results -->
                <?php if ( $query->have_posts() ) : ?>

                    <?php if ( 'list' === $view ) : ?>
                        <?php echo $results_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="ovr-search-results ovr-search-list">
                            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                                <?php echo PropertyCard::render_list( get_the_ID() ); ?>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    <?php elseif ( $has_featured ) : ?>
                        <!-- Single row, two cells: results column (navy bar + cards)
                             and the light-yellow Featured panel. Independent heights
                             so a tall featured panel never pushes the results down. -->
                        <div class="ovr-results-area">
                            <div class="ovr-results-main">
                                <?php echo $results_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <div class="ovr-search-results">
                                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                                        <?php echo PropertyCard::render_search( get_the_ID(), false ); ?>
                                    <?php endwhile; wp_reset_postdata(); ?>
                                </div>
                            </div>
                            <aside class="ovr-featured-panel" aria-label="<?php esc_attr_e( 'Featured listings', 'ovr-core' ); ?>">
                                <div class="ovr-featured-head">
                                    <span class="material-symbols-outlined">star</span>
                                    <span class="ovr-featured-head-text">
                                        <?php esc_html_e( 'Featured Listings', 'ovr-core' ); ?>
                                        <small><?php esc_html_e( 'Paid Service', 'ovr-core' ); ?></small>
                                    </span>
                                </div>
                                <div class="ovr-featured-rail">
                                    <?php while ( $featured->have_posts() ) : $featured->the_post(); ?>
                                        <?php echo PropertyCard::render_search( get_the_ID(), true ); ?>
                                    <?php endwhile; wp_reset_postdata(); ?>
                                </div>
                            </aside>
                        </div>
                    <?php else : ?>
                        <?php echo $results_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="ovr-search-results ovr-results-fullgrid">
                            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                                <?php echo PropertyCard::render_search( get_the_ID(), false ); ?>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Bottom pagination -->
                    <?php if ( $max_pages > 1 ) : ?>
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
        </div>
    </div>
</div>
