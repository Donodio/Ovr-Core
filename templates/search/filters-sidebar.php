<?php
/**
 * Search Filters Sidebar.
 *
 * Phase 2.5: multi-select facets (Village Section, Village, Property Type,
 * Amenities, Views, Features) rendered as compact checkbox groups so guests
 * can combine several values — "search first, filter second", no dropdown
 * clutter. Bedrooms stays a single "+N" select; dates + pets unchanged. The
 * multi-select checkboxes are excluded from auto-submit (class
 * `ovr-mf-check`); the "Apply Filters" button submits the combined set.
 *
 * @package OVR
 *
 * @var array  $filters         Current filter values from query string.
 * @var array  $villages        WP_Term[] of village sections.
 * @var array  $property_types  WP_Term[] of property types.
 * @var array  $bedroom_opts    int => label.
 * @var string $form_action     URL to post to (search page).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Search\SearchFilters;
use OVR\Core\Pages;

$filters        = $filters        ?? [];
$villages       = $villages       ?? SearchFilters::get_villages();
$property_types = $property_types ?? SearchFilters::get_property_types();
$bedroom_opts   = $bedroom_opts   ?? SearchFilters::get_bedroom_options();
$form_action    = $form_action    ?? Pages::get_page_url( 'ovr_page_search' );

$village_names = SearchFilters::get_village_names();
$amenities     = SearchFilters::get_amenities();
$views         = SearchFilters::get_views();
$features      = SearchFilters::get_features();

// Normalize selected values.
$sel_villages  = array_map( 'strval', (array) ( $filters['village']         ?? [] ) );
$sel_sections  = array_map( 'strval', (array) ( $filters['village_section'] ?? [] ) );
$sel_types     = array_map( 'strval', (array) ( $filters['property_type']   ?? [] ) );
$sel_amenities = array_map( 'strval', (array) ( $filters['amenities']       ?? [] ) );
$sel_views     = array_map( 'strval', (array) ( $filters['views']           ?? [] ) );
$sel_features  = array_map( 'strval', (array) ( $filters['features']        ?? [] ) );
$sel_bedrooms  = (int) ( $filters['bedrooms'] ?? 0 );
$sel_pets      = ! empty( $filters['pets'] );
$checkin       = isset( $_GET['checkin'] )  ? sanitize_text_field( wp_unslash( $_GET['checkin'] ) )  : '';
$checkout      = isset( $_GET['checkout'] ) ? sanitize_text_field( wp_unslash( $_GET['checkout'] ) ) : '';

$property_base = esc_js( home_url( '/?post_type=ovr_property&p=' ) );

/**
 * Render one multi-select checkbox group.
 *
 * @param string                       $name     Field name (without []).
 * @param string                       $label    Group label.
 * @param array<string,string>         $options  value => label.
 * @param string[]                     $selected Selected values.
 */
$render_group = static function ( string $name, string $label, array $options, array $selected ): void {
    if ( ! $options ) {
        return;
    }
    $count = count( array_intersect( array_keys( $options ), $selected ) );
    ?>
    <div class="ovr-filter-field">
        <label class="ovr-mf-label">
            <?php echo esc_html( $label ); ?>
            <?php if ( $count ) : ?><span class="ovr-mf-count"><?php echo (int) $count; ?></span><?php endif; ?>
        </label>
        <div class="ovr-mf-group">
            <?php foreach ( $options as $value => $text ) : ?>
                <label class="ovr-mf-item">
                    <input type="checkbox" class="ovr-mf-check" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( (string) $value ); ?>" <?php checked( in_array( (string) $value, $selected, true ) ); ?>>
                    <span><?php echo esc_html( $text ); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
};

// Build value=>label maps for each facet.
$section_opts  = [];
foreach ( $villages as $s ) { $section_opts[ $s->slug ] = $s->name; }
$village_opts  = [];
foreach ( $village_names as $vname ) { $village_opts[ $vname ] = $vname; }
$type_opts     = [];
foreach ( $property_types as $pt ) { $type_opts[ $pt->slug ] = $pt->name; }
$amenity_opts  = [];
foreach ( $amenities as $a ) { $amenity_opts[ $a->slug ] = $a->name; }
$view_opts     = [];
foreach ( $views as $v ) { $view_opts[ $v->slug ] = $v->name; }
$feature_opts  = [];
foreach ( $features as $f ) { $feature_opts[ $f->slug ] = $f->name; }
?>
<aside class="ovr-card ovr-filters-sidebar">
    <style>
        .ovr-filters-sidebar .ovr-mf-label{display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:8px}
        .ovr-filters-sidebar .ovr-mf-count{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 6px;border-radius:9999px;background:var(--ovr-primary,#000961);color:#fff;font-size:11px;font-weight:700}
        .ovr-filters-sidebar .ovr-mf-group{display:flex;flex-direction:column;gap:2px;max-height:180px;overflow-y:auto;border:1px solid var(--ovr-outline-variant,#e3e3e3);border-radius:8px;padding:6px}
        .ovr-filters-sidebar .ovr-mf-item{display:flex;align-items:center;gap:9px;padding:6px 8px;border-radius:6px;cursor:pointer;font-size:14px;line-height:1.3}
        .ovr-filters-sidebar .ovr-mf-item:hover{background:var(--ovr-surface-container-low,#f2f4f7)}
        .ovr-filters-sidebar .ovr-mf-item input{flex-shrink:0;width:16px;height:16px;margin:0;accent-color:var(--ovr-primary,#000961)}
        .ovr-filters-sidebar .ovr-mf-group::-webkit-scrollbar{width:8px}
        .ovr-filters-sidebar .ovr-mf-group::-webkit-scrollbar-thumb{background:var(--ovr-outline-variant,#cfd6df);border-radius:8px}
    </style>

    <h2 class="ovr-filters-title"><?php esc_html_e( 'Search Filters', 'ovr-core' ); ?></h2>

    <form method="get" action="<?php echo esc_url( $form_action ); ?>" id="ovr-filters-form">

        <?php if ( ! empty( $filters['keyword'] ) ) : ?>
            <input type="hidden" name="keyword" value="<?php echo esc_attr( $filters['keyword'] ); ?>">
        <?php endif; ?>

        <!-- Go to Property ID -->
        <div class="ovr-filter-field">
            <label for="ovr-prop-id"><?php esc_html_e( 'Go to Property ID', 'ovr-core' ); ?></label>
            <div class="ovr-id-jump">
                <input type="text" id="ovr-prop-id" class="ovr-form-input" placeholder="<?php esc_attr_e( 'e.g. 1234', 'ovr-core' ); ?>" inputmode="numeric">
                <button type="button" class="ovr-btn ovr-btn-outline"
                        onclick="var v=document.getElementById('ovr-prop-id').value.trim();if(v){window.location.href='<?php echo $property_base; // phpcs:ignore WordPress.Security.EscapeOutput ?>'+encodeURIComponent(v);}">
                    <?php esc_html_e( 'Go', 'ovr-core' ); ?>
                </button>
            </div>
        </div>

        <hr>

        <!-- Dates -->
        <div class="ovr-filter-field">
            <label><?php esc_html_e( 'Dates', 'ovr-core' ); ?></label>
            <input type="date" name="checkin" class="ovr-form-input" aria-label="<?php esc_attr_e( 'Check in', 'ovr-core' ); ?>" value="<?php echo esc_attr( $checkin ); ?>">
            <input type="date" name="checkout" class="ovr-form-input" aria-label="<?php esc_attr_e( 'Check out', 'ovr-core' ); ?>" value="<?php echo esc_attr( $checkout ); ?>">
        </div>

        <?php
        $render_group( 'village_section', __( 'Village Section', 'ovr-core' ), $section_opts, $sel_sections );
        $render_group( 'village', __( 'Village', 'ovr-core' ), $village_opts, $sel_villages );
        $render_group( 'property_type', __( 'Property Type', 'ovr-core' ), $type_opts, $sel_types );
        ?>

        <!-- Bedrooms (single "+N") -->
        <div class="ovr-filter-field">
            <label for="ovr-beds"><?php esc_html_e( 'Bedrooms', 'ovr-core' ); ?></label>
            <select id="ovr-beds" name="bedrooms" class="ovr-form-select">
                <option value=""><?php esc_html_e( 'Any', 'ovr-core' ); ?></option>
                <?php foreach ( $bedroom_opts as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( (string) $val ); ?>" <?php selected( (int) $val, $sel_bedrooms ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php
        $render_group( 'amenities', __( 'Amenities', 'ovr-core' ), $amenity_opts, $sel_amenities );
        $render_group( 'views', __( 'Views', 'ovr-core' ), $view_opts, $sel_views );
        $render_group( 'features', __( 'Features', 'ovr-core' ), $feature_opts, $sel_features );
        ?>

        <!-- Pets -->
        <label class="ovr-pets-row">
            <input type="checkbox" name="pets" value="1" class="ovr-form-checkbox" <?php checked( $sel_pets ); ?>>
            <span><?php esc_html_e( 'Allows Pets', 'ovr-core' ); ?></span>
        </label>

        <button type="submit" class="ovr-btn ovr-btn-primary ovr-btn-full"><?php esc_html_e( 'Apply Filters', 'ovr-core' ); ?></button>
    </form>
</aside>
