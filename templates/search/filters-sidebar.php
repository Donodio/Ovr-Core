<?php
/**
 * Search Filters Sidebar.
 *
 * Matches the OVR search redesign: a single-column filter card with a
 * "Go to Property ID" jump, dates, village / property-type / bedroom
 * dropdowns, and a pets toggle. Posts back to the search page on submit.
 *
 * @package OVR
 *
 * @var array  $filters         Current filter values from query string.
 * @var array  $villages        WP_Term[] of available villages.
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

// Normalize selected values.
$sel_villages = (array) ( $filters['village']       ?? [] );
$sel_types    = (array) ( $filters['property_type'] ?? [] );
$sel_bedrooms = (int) ( $filters['bedrooms']        ?? 0 );
$sel_pets     = ! empty( $filters['pets'] );
$checkin      = isset( $_GET['checkin'] )  ? sanitize_text_field( wp_unslash( $_GET['checkin'] ) )  : '';
$checkout     = isset( $_GET['checkout'] ) ? sanitize_text_field( wp_unslash( $_GET['checkout'] ) ) : '';

// Base URL for the "Go to Property ID" jump (resolves a single ovr_property by ID).
$property_base = esc_js( home_url( '/?post_type=ovr_property&p=' ) );
?>
<aside class="ovr-card ovr-filters-sidebar">
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

        <!-- Village -->
        <?php if ( ! empty( $villages ) ) : ?>
            <div class="ovr-filter-field">
                <label for="ovr-village"><?php esc_html_e( 'Village Name', 'ovr-core' ); ?></label>
                <select id="ovr-village" name="village[]" class="ovr-form-select">
                    <option value=""><?php esc_html_e( 'Any Village', 'ovr-core' ); ?></option>
                    <?php foreach ( $villages as $v ) : ?>
                        <option value="<?php echo esc_attr( $v->slug ); ?>" <?php selected( in_array( $v->slug, $sel_villages, true ) ); ?>>
                            <?php echo esc_html( $v->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- Property Type -->
        <?php if ( ! empty( $property_types ) ) : ?>
            <div class="ovr-filter-field">
                <label for="ovr-type"><?php esc_html_e( 'Property Type', 'ovr-core' ); ?></label>
                <select id="ovr-type" name="property_type[]" class="ovr-form-select">
                    <option value=""><?php esc_html_e( 'Any Type', 'ovr-core' ); ?></option>
                    <?php foreach ( $property_types as $pt ) : ?>
                        <option value="<?php echo esc_attr( $pt->slug ); ?>" <?php selected( in_array( $pt->slug, $sel_types, true ) ); ?>>
                            <?php echo esc_html( $pt->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- Bedrooms -->
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

        <!-- Pets -->
        <label class="ovr-pets-row">
            <input type="checkbox" name="pets" value="1" class="ovr-form-checkbox" <?php checked( $sel_pets ); ?>>
            <span><?php esc_html_e( 'Allows Pets', 'ovr-core' ); ?></span>
        </label>

        <button type="submit" class="ovr-btn ovr-btn-primary ovr-btn-full"><?php esc_html_e( 'Apply Filters', 'ovr-core' ); ?></button>
    </form>
</aside>
