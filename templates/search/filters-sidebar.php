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
        .ovr-filters-sidebar .ovr-gc-trigger{display:flex;align-items:center;justify-content:space-between;width:100%;text-align:left;cursor:pointer}
        .ovr-filters-sidebar .ovr-gc-panel{margin-top:6px}
        .ovr-filters-sidebar .ovr-gc-dd .ovr-mf-group{max-height:220px}
        /* Author display:flex on .ovr-mf-group beats the UA [hidden] rule —
           restate it so the dropdown panel actually collapses. */
        .ovr-filters-sidebar .ovr-gc-panel[hidden]{display:none}
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
        // Preserve any active village_section / village filter as hidden fields so
        // the chip-driven section filter survives a sidebar filter submission.
        foreach ( (array) $sel_sections as $ss ) : ?>
            <input type="hidden" name="village_section[]" value="<?php echo esc_attr( (string) $ss ); ?>">
        <?php endforeach;
        foreach ( (array) $sel_villages as $sv ) : ?>
            <input type="hidden" name="village[]" value="<?php echo esc_attr( (string) $sv ); ?>">
        <?php endforeach;

        $village_name_filter = '';
        if ( isset( $_GET['village_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $village_name_filter = sanitize_text_field( wp_unslash( $_GET['village_name'] ) );
        }
        ?>

        <!-- Villages Section (multi-select, browses by community area) -->
        <?php
        $render_group( 'village_section', __( 'Villages Section', 'ovr-core' ), $section_opts, $sel_sections );
        ?>

        <!-- Village Name (free-text) -->
        <div class="ovr-filter-field">
            <label for="ovr-filter-village-name"><?php esc_html_e( 'Village Name', 'ovr-core' ); ?></label>
            <input type="text"
                   id="ovr-filter-village-name"
                   name="village_name"
                   class="ovr-form-input"
                   list="ovr-village-name-datalist"
                   value="<?php echo esc_attr( $village_name_filter ); ?>"
                   placeholder="<?php esc_attr_e( 'e.g. Mallory Square', 'ovr-core' ); ?>">
            <?php if ( ! empty( $village_names ) ) : ?>
                <datalist id="ovr-village-name-datalist">
                    <?php foreach ( $village_names as $vname ) : ?>
                        <option value="<?php echo esc_attr( $vname ); ?>">
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
            <p class="ovr-filter-hint" style="margin:6px 0 0;font-size:12px;color:var(--ovr-on-surface-variant)"><?php esc_html_e( 'Start typing to see matching villages.', 'ovr-core' ); ?></p>
        </div>

        <?php
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
        // Rental Term (Long-Term / Short-Term) — sits directly under Bedrooms.
        $rental_terms = get_terms( [ 'taxonomy' => 'ovr_rental_type', 'hide_empty' => false ] );
        $rental_opts  = [];
        if ( ! is_wp_error( $rental_terms ) ) {
            foreach ( $rental_terms as $rt ) { $rental_opts[ $rt->slug ] = $rt->name; }
        }
        $sel_rental = array_map( 'strval', (array) ( $filters['rental_type'] ?? [] ) );
        $render_group( 'rental_type', __( 'Rental Term', 'ovr-core' ), $rental_opts, $sel_rental );
        ?>

        <?php
        // ── Golf Cart ──────────────────────────────────────────────────────
        // A DROPDOWN whose panel holds CHECKBOXES — one per live configured
        // golf-cart term in ovr_feature / ovr_amenity (names containing
        // "golf cart"), shown verbatim as admin-configured. Multi-select with
        // OR semantics; empty selection = Any.
        //
        // Legacy compatibility: URLs that still carry the old single
        // `golf_cart=gas` bucket key are expanded by PropertyQuery at query
        // time, and old `features[]=golf-cart-*` checkbox links pre-check the
        // matching option below.
        $golf_options = \OVR\Property\PropertyQuery::golf_cart_term_options();

        $sel_golf = \OVR\Search\SearchHandler::clean_golf_cart( $filters['golf_cart'] ?? [] );
        if ( ! $sel_golf ) {
            // Legacy features[]=golf-cart-* selections directly pre-check the
            // matching options (those slugs ARE golf-cart term slugs).
            $sel_golf = array_values( array_intersect( $sel_features, \OVR\Property\PropertyQuery::GOLF_CART_SLUGS ) );
        }

        // Non-golf features remain a hidden-preserved facet (the old single
        // "Golf Cart" checkbox used to live here); every currently-selected
        // non-golf feature survives a sidebar submission untouched.
        foreach ( $feature_opts as $fslug => $fname ) {
            if ( preg_match( '/golf\s*cart/i', (string) $fname ) ) { continue; }
            if ( in_array( $fslug, $sel_features, true ) ) {
                ?>
                <input type="hidden" name="features[]" value="<?php echo esc_attr( $fslug ); ?>">
                <?php
            }
        }
        ?>
        <?php $golf_count = count( $sel_golf ); ?>
        <div class="ovr-filter-field ovr-gc-dd" data-ovr-gc-dropdown>
            <button type="button" class="ovr-form-select ovr-gc-trigger" aria-expanded="false" aria-controls="ovr-gc-panel">
                <span><?php esc_html_e( 'Golf Cart', 'ovr-core' ); ?><?php echo $golf_count ? esc_html( ' · ' . (int) $golf_count ) : ''; ?></span>
                <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
            </button>
            <div class="ovr-mf-group ovr-gc-panel" id="ovr-gc-panel" hidden>
                <?php foreach ( $golf_options as $gslug => $gname ) : ?>
                    <label class="ovr-mf-item">
                        <input type="checkbox" class="ovr-mf-check" name="golf_cart[]" value="<?php echo esc_attr( (string) $gslug ); ?>"
                               <?php checked( in_array( (string) $gslug, $sel_golf, true ) ); ?>>
                        <span><?php echo esc_html( $gname ); ?></span>
                    </label>
                <?php endforeach; ?>
                <?php if ( empty( $golf_options ) ) : ?>
                    <span class="ovr-filter-hint"><?php esc_html_e( 'No golf cart options configured.', 'ovr-core' ); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <script>
        (function () {
        	document.addEventListener('click', function (e) {
        		var trigger = e.target.closest('[data-ovr-gc-dropdown] > .ovr-gc-trigger');
        		if (!trigger) { return; }
        		var dd = trigger.parentElement;
        		var panel = dd.querySelector('.ovr-gc-panel');
        		var open = !panel.hidden;
        		panel.hidden = open;
        		trigger.setAttribute('aria-expanded', open ? 'false' : 'true');
        	});
        	document.addEventListener('click', function (e) {
        		document.querySelectorAll('[data-ovr-gc-dropdown]').forEach(function (dd) {
        			if (!dd.contains(e.target)) {
        				var p = dd.querySelector('.ovr-gc-panel');
        				if (p && !p.hidden) { p.hidden = true; var t = dd.querySelector('.ovr-gc-trigger'); if (t) { t.setAttribute('aria-expanded', 'false'); } }
        			}
        		});
        	});
        	document.addEventListener('keydown', function (e) {
        		if (e.key !== 'Escape') { return; }
        		document.querySelectorAll('[data-ovr-gc-dropdown]').forEach(function (dd) {
        			var p = dd.querySelector('.ovr-gc-panel');
        			if (p && !p.hidden) { p.hidden = true; var t = dd.querySelector('.ovr-gc-trigger'); if (t) { t.setAttribute('aria-expanded', 'false'); } }
        		});
        	});
        	document.addEventListener('change', function (e) {
        		var cb = e.target.closest('[data-ovr-gc-dropdown] .ovr-mf-check');
        		if (!cb) { return; }
        		var dd = cb.closest('[data-ovr-gc-dropdown]');
        		var trigger = dd.querySelector('.ovr-gc-trigger');
        		var labelSpan = trigger ? trigger.querySelector('span') : null;
        		if (!labelSpan) { return; }
        		if (!labelSpan.getAttribute('data-ovr-gc-label')) {
        			labelSpan.setAttribute('data-ovr-gc-label', labelSpan.textContent.split(' · ')[0]);
        		}
        		var n = dd.querySelectorAll('.ovr-mf-check:checked').length;
        		labelSpan.textContent = n ? labelSpan.getAttribute('data-ovr-gc-label') + ' · ' + n : labelSpan.getAttribute('data-ovr-gc-label');
        	});
        })();
        </script>

        <!-- Pets — three-state policy (Chunk 1 §9-11): Yes / No / Considered.
             Never reduced to a checkbox; "none" matters to allergy sufferers. -->
        <?php $sel_pets = \OVR\Search\SearchHandler::clean_pets( $filters['pets'] ?? '' ); ?>
        <div class="ovr-filter-field">
            <label for="ovr-pets"><?php esc_html_e( 'Pets', 'ovr-core' ); ?></label>
            <select id="ovr-pets" name="pets" class="ovr-form-select">
                <option value="" <?php selected( $sel_pets, '' ); ?>><?php esc_html_e( 'Any', 'ovr-core' ); ?></option>
                <option value="allowed" <?php selected( $sel_pets, 'allowed' ); ?>><?php esc_html_e( 'Pets Allowed', 'ovr-core' ); ?></option>
                <option value="considered" <?php selected( $sel_pets, 'considered' ); ?>><?php esc_html_e( 'Pets Considered', 'ovr-core' ); ?></option>
                <option value="none" <?php selected( $sel_pets, 'none' ); ?>><?php esc_html_e( 'No Pets', 'ovr-core' ); ?></option>
            </select>
        </div>

        <?php
        $render_group( 'amenities', __( 'Amenities', 'ovr-core' ), $amenity_opts, $sel_amenities );
        $render_group( 'views', __( 'Views', 'ovr-core' ), $view_opts, $sel_views );
        ?>

        <button type="submit" class="ovr-btn ovr-btn-primary ovr-btn-full"><?php esc_html_e( 'Search Homes', 'ovr-core' ); ?></button>

        <?php
        // Clear All Filters (Chunk 1 §13-15): a state-safe reset. The link
        // points at the CLEAN search URL (no filter params survive — not in the
        // form, not in hidden inputs, not in JS state), so both the controls and
        // the underlying query are guaranteed to reset. Only the active results
        // view (grid/list/map) is preserved, which is presentation, not a filter.
        $clear_url = $form_action;
        $cur_view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( in_array( $cur_view, [ 'grid', 'list', 'map' ], true ) ) {
            $clear_url .= ( str_contains( $clear_url, '?' ) ? '&' : '?' ) . 'view=' . rawurlencode( $cur_view );
        }
        ?>
        <a href="<?php echo esc_url( $clear_url ); ?>"
           class="ovr-btn ovr-btn-outline ovr-btn-full ovr-clear-filters"
           data-ovr-clear-filters><?php esc_html_e( 'Clear All Filters', 'ovr-core' ); ?></a>
    </form>
</aside>
