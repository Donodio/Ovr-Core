<?php
/**
 * No Search Results — Empty State.
 *
 * Shown when a search returns zero properties. Provides actionable suggestions
 * and a quick path back to a clean search.
 *
 * @package OVR
 *
 * @var string $keyword     Optional. Current search keyword.
 * @var array  $filters     Optional. Active filter values.
 * @var string $reset_url   Optional. URL to clear filters.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\Pages;

$keyword   = $keyword   ?? '';
$filters   = $filters   ?? [];
$reset_url = $reset_url ?? Pages::get_page_url( 'ovr_page_search' );
?>
<div class="ovr-no-results"
     style="max-width:520px;margin:48px auto;text-align:center;padding:48px 24px">

    <span class="material-symbols-outlined"
          style="font-size:72px;color:var(--ovr-outline-variant);display:block;margin-bottom:16px">
        search_off
    </span>

    <h2 class="ovr-h3" style="margin-bottom:8px">
        <?php
        if ( $keyword ) {
            /* translators: %s: search keyword */
            printf( esc_html__( 'No properties match "%s"', 'ovr-core' ), esc_html( $keyword ) );
        } else {
            esc_html_e( 'No properties match your filters', 'ovr-core' );
        }
        ?>
    </h2>

    <p class="ovr-body-md" style="color:var(--ovr-on-surface-variant);margin-bottom:24px">
        <?php esc_html_e( 'Try widening your search — adjust the price range, remove some filters, or explore a different village.', 'ovr-core' ); ?>
    </p>

    <!-- Quick suggestions -->
    <div style="display:flex;flex-direction:column;gap:8px;align-items:center;margin-bottom:32px;font-size:14px;color:var(--ovr-on-surface-variant)">
        <p style="font-weight:600;margin:0 0 4px"><?php esc_html_e( 'A few things to try:', 'ovr-core' ); ?></p>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:4px">
            <li>&middot; <?php esc_html_e( 'Remove one or more amenity requirements', 'ovr-core' ); ?></li>
            <li>&middot; <?php esc_html_e( 'Increase your price range', 'ovr-core' ); ?></li>
            <li>&middot; <?php esc_html_e( 'Reduce the minimum bedroom count', 'ovr-core' ); ?></li>
            <li>&middot; <?php esc_html_e( 'Search a nearby village', 'ovr-core' ); ?></li>
        </ul>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="<?php echo esc_url( $reset_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-pill">
            <span class="material-symbols-outlined">refresh</span>
            <?php esc_html_e( 'Clear All Filters', 'ovr-core' ); ?>
        </a>
        <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_featured' ) ); ?>" class="ovr-btn ovr-btn-outline ovr-btn-pill">
            <?php esc_html_e( 'Browse Featured', 'ovr-core' ); ?>
        </a>
    </div>
</div>
