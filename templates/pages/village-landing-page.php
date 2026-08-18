<?php
/**
 * Village Landing — full page wrapper for the ovr_village taxonomy archive.
 *
 * The `/village/{slug}/` URLs are served by WordPress as taxonomy archives.
 * TemplateLoader routes them here (instead of returning the bare
 * village-landing.php partial, which would run with no $village/$query data).
 * This wrapper resolves the current term and delegates to
 * VillagePage::render() so the partial always receives its variables.
 *
 * @package OVR
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$term = get_queried_object();
$slug = $term instanceof WP_Term ? (string) $term->slug : '';

echo \OVR\Frontend\VillagePage::render( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered template.

get_footer();
