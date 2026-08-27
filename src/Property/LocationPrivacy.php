<?php
/**
 * Location Privacy.
 *
 * Public visitors must never receive a listing's exact coordinates. The
 * database keeps the true geocoded lat/lng (internal use only); everything
 * rendered publicly is transformed into an APPROXIMATE AREA: a circle whose
 * center is deterministically offset from the real point by a small, fixed
 * distance and whose radius comfortably contains the real location.
 *
 * Design constraints (Chunk 1 §27-§35):
 *  - The exact point itself is never exposed to the browser.
 *  - The approximation stays geographically meaningful (correct street-area,
 *    NOT a random one-mile offset).
 *  - The same listing always renders the same area (stable across page loads).
 *  - Stored coordinates are never modified.
 *
 * @package OVR\Property
 * @since   1.2.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LocationPrivacy {

	/**
	 * Radius of the public approximate-area circle, in metres. Large enough to
	 * read as "the neighbourhood" on an OSM zoomed-in map, small enough that it
	 * still points at the right few streets (~150 m ≈ one short block grid in
	 * The Villages).
	 */
	public const RADIUS_METERS = 150;

	/**
	 * Deterministic privacy-adjusted center for a listing.
	 *
	 * The center is placed at a fixed fraction of the radius away from the true
	 * location, at an angle derived from the post ID. Because the offset is
	 * strictly smaller than the radius, the true location always sits INSIDE
	 * the rendered circle; because the seed is stable, the circle never moves
	 * between loads.
	 *
	 * @param int   $post_id Listing ID (offset seed).
	 * @param float $lat     Exact internal latitude.
	 * @param float $lng     Exact internal longitude.
	 * @return array{lat:float, lng:float, radius:int}
	 */
	public static function approx_area( int $post_id, float $lat, float $lng ): array {
		if ( 0.0 === $lat && 0.0 === $lng ) {
			return [ 'lat' => 0.0, 'lng' => 0.0, 'radius' => 0 ];
		}

		// Stable per-listing angle + magnitude (deterministic, not random):
		// angle from the ID, magnitude ~40-60% of the radius so the exact home
		// stays well inside the circle while the drawn CENTER is never the
		// house itself.
		$angle = ( $post_id % 360 ) * M_PI / 180;
		$mag   = self::RADIUS_METERS * ( 0.4 + ( ( $post_id % 20 ) / 100 ) ); // 0.40–0.59 × radius.

		return [
			'lat'    => $lat + ( cos( $angle ) * $mag / 111320.0 ),
			'lng'    => $lng + ( sin( $angle ) * $mag / ( 111320.0 * max( 0.2, cos( deg2rad( $lat ) ) ) ) ),
			'radius' => self::RADIUS_METERS,
		];
	}

	/**
	 * Whether the current request may receive EXACT coordinates (wp-admin and
	 * authenticated listing editors). Public/anonymous requests always get the
	 * approximation only.
	 */
	public static function can_see_exact(): bool {
		return is_user_logged_in() && current_user_can( 'edit_ovr_properties' );
	}
}
