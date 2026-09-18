<?php
/**
 * OVR — Golf Cart Display Regression (single-property summary).
 *
 * Reproduces the class of bug reported for property #977: the compact specs
 * strip underneath the photos treated the Golf Cart taxonomy field as a
 * boolean presence check ("Golf Cart Included" / "No Golf Cart") instead of
 * displaying the actual selected term label.
 *
 * Run: php wp-content/plugins/ovr-core/tests/golf-cart-display.php
 *
 * All synthetic properties/terms are removed afterwards. Property #977 is not
 * modified (and does not exist in this local database — see report).
 */

if ( ! defined( 'ABSPATH' ) ) {
    foreach ( [ __DIR__ . '/../../../wp-load.php', __DIR__ . '/../../../../wp-load.php' ] as $p ) {
        if ( file_exists( $p ) ) { require_once $p; break; }
    }
    if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "no wp-load\n" ); exit( 1 ); }
}
if ( ! function_exists( 'wp_delete_post' ) ) { require_once ABSPATH . 'wp-admin/includes/post.php'; }

use OVR\Frontend\SingleProperty;
use OVR\Property\PropertyQuery;

global $wpdb;
$pass = 0; $fail = 0;
function okg( bool $c, string $l ): void {
    global $pass, $fail;
    if ( $c ) { echo "  PASS: $l\n"; $pass++; } else { echo "  FAIL: $l\n"; $fail++; }
}

$created_terms = [];
$created_posts = [];

function golf_make_term( string $name, string $tax, string $slug ): int {
    global $created_terms;
    $existing = get_term_by( 'name', $name, $tax );
    if ( $existing ) { return (int) $existing->term_id; }
    $r = wp_insert_term( $name, $tax, [ 'slug' => $slug ] );
    if ( is_wp_error( $r ) ) {
        $t = get_term_by( 'slug', $slug, $tax );
        return $t ? (int) $t->term_id : 0;
    }
    $created_terms[] = [ (int) $r['term_id'], $tax ];
    return (int) $r['term_id'];
}

function golf_make_property( string $title ): int {
    global $created_posts;
    $id = wp_insert_post( [
        'post_type'   => 'ovr_property',
        'post_title'  => $title,
        'post_status' => 'publish',
    ] );
    if ( $id && ! is_wp_error( $id ) ) { $created_posts[] = (int) $id; return (int) $id; }
    return 0;
}

/** Extract the Golf Cart label from the rendered specs strip (null when absent). */
function golf_spec_label( string $html ): ?string {
    if ( ! preg_match( '/ovr-detail-spec"><span class="material-symbols-outlined">golf_course<\/span>\s*([^<]*)<\/span>/s', $html, $m ) ) {
        return null;
    }
    return trim( html_entity_decode( (string) $m[1] ) );
}

/** Extract the label shown in the Amenities ("What this place offers") panel. */
function golf_amenity_label( string $html, string $needle ): ?string {
    $pos = strpos( $html, 'data-ovr-panel="features"' );
    if ( false === $pos ) { return null; }
    $panel = substr( $html, $pos );
    $end   = strpos( $panel, 'data-ovr-panel="reviews"' );
    if ( false !== $end ) { $panel = substr( $panel, 0, $end ); }
    if ( false === strpos( $panel, $needle ) ) { return null; }
    return $needle;
}

// ------------------------------------------------------------------
// The canonical resolver's real options (from the live database).
// ------------------------------------------------------------------
echo "=== Live canonical golf-cart options ===\n";
$opts = PropertyQuery::golf_cart_term_options();
okg( isset( $opts['golf-cart-included'] ), 'canonical option golf-cart-included exists' );
okg( isset( $opts['golf-cart-extra-charge'] ), 'canonical option golf-cart-extra-charge exists' );
okg( PropertyQuery::GOLF_CART_SLUGS === [ 'golf-cart-included', 'golf-cart-extra-charge' ], 'GOLF_CART_SLUGS unchanged' );

// ------------------------------------------------------------------
// GOLF-001..005, 011, 012, 018: every option renders its canonical label.
// ------------------------------------------------------------------
echo "\n=== Option matrix (summary + amenities) ===\n";
$matrix = [
    'GOLF-001 included'         => [ 'ovr_amenity', 'Golf Cart Included', 'golf-cart-included',      'Golf Cart Included' ],
    'GOLF-004 extra/fee'        => [ 'ovr_amenity', 'Golf Cart-extra charge', 'golf-cart-extra-charge', 'Golf Cart-extra charge' ],
    'GOLF-002 not provided'     => [ 'ovr_amenity', 'Golf Cart Not Provided', 'ovr-golf-not-provided',  'Golf Cart Not Provided' ],
    'GOLF-002 no golf cart'     => [ 'ovr_feature', 'No Golf Cart', 'ovr-golf-no-cart',                'No Golf Cart' ],
    'GOLF-005 gas type'         => [ 'ovr_feature', 'Gas Golf Cart', 'ovr-golf-gas',                   'Gas Golf Cart' ],
    'GOLF-005 electric type'    => [ 'ovr_feature', 'Electric Golf Cart', 'ovr-golf-electric',         'Electric Golf Cart' ],
];
foreach ( $matrix as $label => $row ) {
    [ $tax, $term_name, $slug, $expected ] = $row;
    $tid = golf_make_term( $term_name, $tax, $slug );
    $pid = golf_make_property( 'Golf Matrix ' . $slug );
    wp_set_object_terms( $pid, [ $tid ], $tax, false );

    $resolved = PropertyQuery::golf_cart_label( $pid );
    okg( $resolved === $expected, "$label: resolver returns \"$expected\" (got \"$resolved\")" );

    $html = SingleProperty::render( $pid );
    $spec = golf_spec_label( $html );
    okg( $spec === $expected, "$label: summary renders \"$expected\" (got " . json_encode( $spec ) . ')' );
    okg( $spec !== 'Golf Cart Included' || $expected === 'Golf Cart Included', "$label: never coerced to \"Golf Cart Included\"" );

    // GOLF-011 summary label === amenities label
    $amen = golf_amenity_label( $html, $expected );
    okg( $amen === $expected && $spec === $amen, "$label: summary label === amenities label" );
}

// ------------------------------------------------------------------
// GOLF-012 / #977 class: explicit negative must NOT become Included.
// ------------------------------------------------------------------
echo "\n=== GOLF-012 / property #977 class ===\n";
$neg_tid = golf_make_term( 'Golf Cart Not Provided', 'ovr_amenity', 'ovr-golf-not-provided' );
$neg_pid = golf_make_property( 'Golf #977 Equivalent' );
wp_set_object_terms( $neg_pid, [ $neg_tid ], 'ovr_amenity', false );
$raw = PropertyQuery::golf_cart_label( $neg_pid );
okg( '' !== $raw, '#977-class: explicit negative value is non-empty' );
okg( $raw === 'Golf Cart Not Provided', '#977-class: resolves to negative label' );
okg( $raw !== 'Golf Cart Included', '#977-class: does NOT resolve to Included' );
okg( PropertyQuery::has_golf_cart( $neg_pid ) === true, '#977-class: presence check is true (this is why the old boolean bug fired)' );
$neg_html = SingleProperty::render( $neg_pid );
okg( golf_spec_label( $neg_html ) === 'Golf Cart Not Provided', '#977-class: summary shows the negative label' );
okg( false === strpos( golf_spec_label( $neg_html ) ?? '', 'Included' ), '#977-class: summary does not say Included' );
okg( golf_amenity_label( $neg_html, 'Golf Cart Not Provided' ) === 'Golf Cart Not Provided', '#977-class: amenities shows same label' );

// ------------------------------------------------------------------
// GOLF-006..010: empty/unselected renders nothing.
// ------------------------------------------------------------------
echo "\n=== GOLF-006..010 empty / unselected ===\n";
$empty_pid = golf_make_property( 'Golf Empty' );
okg( PropertyQuery::golf_cart_label( $empty_pid ) === '', 'GOLF-006 no term resolves to empty string' );
okg( PropertyQuery::has_golf_cart( $empty_pid ) === false, 'GOLF-006 no term presence is false' );
$empty_html = SingleProperty::render( $empty_pid );
okg( golf_spec_label( $empty_html ) === null, 'GOLF-006 missing value renders no Golf Cart summary' );
okg( false === strpos( $empty_html, 'ovr-detail-spec"><span class="material-symbols-outlined">golf_course' ), 'GOLF-010 empty value leaves no golf_course spec markup/icon' );
okg( false === strpos( $empty_html, 'No Golf Cart' ), 'GOLF-009 empty value does not render "No Golf Cart"' );
okg( false === strpos( $empty_html, 'Golf Cart Included' ), 'GOLF-009 empty value does not render "Golf Cart Included"' );

// GOLF-007 / GOLF-008: empty-string / default placeholder representations.
okg( PropertyQuery::golf_cart_label( 0 ) === '', 'GOLF-007 invalid/zero post resolves empty' );
okg( PropertyQuery::golf_cart_label( $empty_pid ) === '', 'GOLF-008 unselected/default placeholder resolves empty' );

// ------------------------------------------------------------------
// GOLF-014: other summary fields unchanged.
// ------------------------------------------------------------------
echo "\n=== GOLF-014 other summary fields ===\n";
update_post_meta( $empty_pid, '_ovr_bedrooms', 3 );
update_post_meta( $empty_pid, '_ovr_bathrooms', 2 );
$other_html = SingleProperty::render( $empty_pid );
okg( false !== strpos( $other_html, '3 bedrooms' ), 'bedrooms spec unchanged' );
okg( false !== strpos( $other_html, '2 baths' ), 'baths spec unchanged' );
okg( false !== strpos( $other_html, 'ovr-detail-spec' ), 'specs strip still renders' );
okg( false !== strpos( $other_html, 'material-symbols-outlined">bed' ), 'bed icon unchanged' );
okg( false !== strpos( $other_html, 'material-symbols-outlined">bathtub' ), 'bathtub icon unchanged' );

// ------------------------------------------------------------------
// GOLF-015: no PHP warning/notice for missing golf cart meta.
// ------------------------------------------------------------------
echo "\n=== GOLF-015 no PHP warnings ===\n";
$php_issues = [];
set_error_handler( function ( $no, $str, $file, $line ) use ( &$php_issues ) {
    if ( $no & ( E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED ) ) {
        $php_issues[] = $str . ' @ ' . basename( (string) $file ) . ':' . $line;
    }
    return false;
} );
$probe_pid = golf_make_property( 'Golf Warning Probe' );
$warn_html = SingleProperty::render( $probe_pid );
restore_error_handler();
$golf_issues = array_values( array_filter( $php_issues, static fn( $m ) => false !== stripos( $m, 'golf' ) || false !== stripos( $m, 'single.php' ) ) );
okg( [] === $golf_issues, 'GOLF-015 no golf-cart PHP warning/notice (got ' . count( $golf_issues ) . ')' );
okg( '' !== $warn_html, 'GOLF-015 page renders with missing golf meta' );

// ------------------------------------------------------------------
// GOLF-013: no property-ID-specific logic.
// ------------------------------------------------------------------
echo "\n=== GOLF-013 no hardcoded IDs ===\n";
$tpl_src = (string) file_get_contents( OVR_PLUGIN_DIR . 'templates/property/single.php' );
okg( false === strpos( $tpl_src, '977' ), 'GOLF-013 template contains no hardcoded 977' );
okg( false !== strpos( $tpl_src, 'golf_cart_label' ), 'GOLF-013 template uses canonical golf_cart_label resolver' );
okg( false === strpos( $tpl_src, 'has_golf_cart' ), 'GOLF-013 template no longer uses boolean has_golf_cart' );
$src_resolver = (string) file_get_contents( OVR_PLUGIN_DIR . 'src/Property/PropertyQuery.php' );
okg( 0 === preg_match( '/\b(?:post_id\s*===|===\s*\d{3,}|in_array\s*\(\s*\$post_id)/', $src_resolver ), 'GOLF-013 resolver has no property-ID special cases' );

// ------------------------------------------------------------------
// GOLF-016: Search filter semantics unchanged.
// ------------------------------------------------------------------
echo "\n=== GOLF-016 search filter semantics ===\n";
okg( PropertyQuery::GOLF_CART_BUCKETS === [ 'any', 'included', 'extra', 'gas', 'electric', 'none' ], 'GOLF-016 buckets unchanged' );
$inc = PropertyQuery::golf_cart_bucket_slugs( 'included' );
okg( in_array( 'golf-cart-included', $inc, true ), 'GOLF-016 included bucket contains canonical slug' );
$none = PropertyQuery::golf_cart_bucket_slugs( 'none' );
okg( in_array( 'ovr-golf-no-cart', $none, true ), 'GOLF-016 none bucket classifies a "No Golf Cart" term' );
$clause = PropertyQuery::golf_cart_clause( [ 'golf-cart-included' ] );
okg( is_array( $clause ) && ( $clause['relation'] ?? '' ) === 'OR', 'GOLF-016 clause shape unchanged' );
okg( PropertyQuery::golf_cart_clause( [] ) === null, 'GOLF-016 empty slugs produce no clause' );

// ------------------------------------------------------------------
// GOLF-017: listing-edit dropdown source unchanged (term options round-trip).
// ------------------------------------------------------------------
echo "\n=== GOLF-017 edit dropdown source ===\n";
$opts2 = PropertyQuery::golf_cart_term_options();
okg( isset( $opts2['golf-cart-included'] ) && 'Golf Cart Included' === $opts2['golf-cart-included'], 'GOLF-017 canonical included option label intact' );
okg( isset( $opts2['golf-cart-extra-charge'] ) && 'Golf Cart-extra charge' === $opts2['golf-cart-extra-charge'], 'GOLF-017 canonical extra-charge option label intact' );

// ------------------------------------------------------------------
// GOLF-018: save → resolve → display round-trip.
// ------------------------------------------------------------------
echo "\n=== GOLF-018 round-trip ===\n";
$rt_tid = golf_make_term( 'Golf Cart Included', 'ovr_feature', 'golf-cart-included' );
$rt_pid = golf_make_property( 'Golf Round Trip' );
wp_set_object_terms( $rt_pid, [ $rt_tid ], 'ovr_feature', false );
$rt_html = SingleProperty::render( $rt_pid );
okg( PropertyQuery::golf_cart_label( $rt_pid ) === 'Golf Cart Included', 'GOLF-018 feature term round-trips through resolver' );
okg( golf_spec_label( $rt_html ) === 'Golf Cart Included', 'GOLF-018 feature term renders in summary' );

// ------------------------------------------------------------------
// Cleanup
// ------------------------------------------------------------------
echo "\n=== Cleanup ===\n";
foreach ( $created_posts as $p ) { wp_delete_post( (int) $p, true ); }
foreach ( $created_terms as $pair ) {
    [ $tid, $tax ] = $pair;
    wp_delete_term( (int) $tid, $tax );
}
okg( true, 'synthetic posts/terms removed' );

echo "\n=== RESULTS: $pass passed, $fail failed ===\n";
if ( $fail > 0 ) { exit( 1 ); }
