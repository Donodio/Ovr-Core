<?php
/**
 * Shared filter chrome for the OVR admin tables.
 *
 * Every Phase 2 admin screen renders its own themed toolbar, but the two
 * controls that operate on the *request* rather than the data — "Reset
 * Filters" and "Clear Search" — behave identically everywhere and were being
 * hand-rolled per template. This class owns them once so the URL semantics
 * (what a reset drops, what a clear keeps) can never drift between screens.
 *
 * Pairs with ListTable::preserve_url(), which does the actual arg merging.
 *
 * Usage inside a template:
 *   FilterControls::render_clear_search( $base_url, 'ovr-bk-btn ovr-bk-btn--ghost' );
 *   FilterControls::render_reset( $base_url, 'ovr-bk-btn ovr-bk-btn--ghost' );
 *
 * @package OVR\Admin
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FilterControls {

    /**
     * Is a free-text search currently applied?
     */
    public static function has_search(): bool {
        return '' !== trim( (string) sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) );
    }

    /**
     * URL that drops the search term but keeps every other active filter.
     *
     * `paged` is dropped by preserve_url() already: the result set changes, so
     * staying on page 7 would land the admin on an empty page.
     */
    public static function clear_search_url( string $base_url ): string {
        return ListTable::preserve_url( $base_url, [], [ 's' ] );
    }

    /**
     * "Clear Search" — removes only the search term, keeping active filters.
     *
     * Renders nothing when there is no search to clear, so the toolbar doesn't
     * carry a dead control.
     *
     * @param string $base_url Bare screen URL (post_type + page).
     * @param string $class    Screen-specific button classes.
     */
    public static function render_clear_search( string $base_url, string $class ): void {
        if ( ! self::has_search() ) {
            return;
        }
        printf(
            '<a href="%1$s" class="%2$s" title="%3$s"><span class="material-symbols-outlined">search_off</span>%4$s</a>',
            esc_url( self::clear_search_url( $base_url ) ),
            esc_attr( $class ),
            esc_attr__( 'Clear the search term and keep the current filters', 'ovr-core' ),
            esc_html__( 'Clear Search', 'ovr-core' )
        );
    }

    /**
     * "Reset Filters" — returns to the bare screen URL, dropping search,
     * filters, sorting and pagination in one step.
     *
     * @param string $base_url Bare screen URL (post_type + page).
     * @param string $class    Screen-specific button classes.
     * @param string $label    Optional short label override (e.g. 'Reset').
     */
    public static function render_reset( string $base_url, string $class, string $label = '' ): void {
        printf(
            '<a href="%1$s" class="%2$s" title="%3$s"><span class="material-symbols-outlined">filter_alt_off</span>%4$s</a>',
            esc_url( $base_url ),
            esc_attr( $class ),
            esc_attr__( 'Clear all filters and search', 'ovr-core' ),
            esc_html( '' !== $label ? $label : __( 'Reset Filters', 'ovr-core' ) )
        );
    }
}
