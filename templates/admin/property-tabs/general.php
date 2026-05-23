<?php
/**
 * General Tab — bedrooms, beds, baths, guests, sqft, min stay, status.
 *
 * @package OVR
 * @var array $meta
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$bedrooms     = (int)   ( $meta['bedrooms']       ?? 0 );
$bathrooms    = (float) ( $meta['bathrooms']      ?? 0 );
$beds         = (int)   ( $meta['beds']           ?? 0 );
$max_guests   = (int)   ( $meta['max_guests']     ?? 1 );
$sqft         = (int)   ( $meta['sqft']           ?? 0 );
$min_stay     = (int)   ( $meta['min_stay']       ?? 1 );
$status       = (string)( $meta['listing_status'] ?? 'active' );
$booking_mode = (string)( $meta['booking_mode']   ?? 'inquiry' );
$pets         = ! empty( $meta['pets_allowed'] );
?>
<p class="ovr-meta-tabs__panel-intro">
    <?php esc_html_e( 'Core specifications travelers see at a glance. These power the search filters and the property card.', 'ovr-core' ); ?>
</p>

<div class="ovr-section-head">
    <h3><span class="material-symbols-outlined">bed</span> <?php esc_html_e( 'Specifications', 'ovr-core' ); ?></h3>
</div>

<div class="ovr-field-grid ovr-field-grid--3">

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-bedrooms"><?php esc_html_e( 'Bedrooms', 'ovr-core' ); ?></label>
        <input type="number" id="ovr-meta-bedrooms" name="ovr_meta[bedrooms]"
               min="0" step="1" value="<?php echo esc_attr( (string) $bedrooms ); ?>">
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-beds"><?php esc_html_e( 'Beds', 'ovr-core' ); ?></label>
        <input type="number" id="ovr-meta-beds" name="ovr_meta[beds]"
               min="0" step="1" value="<?php echo esc_attr( (string) $beds ); ?>">
        <p class="ovr-field__hint"><?php esc_html_e( 'Total sleeping spots, including pull-outs.', 'ovr-core' ); ?></p>
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-bathrooms"><?php esc_html_e( 'Bathrooms', 'ovr-core' ); ?></label>
        <input type="number" id="ovr-meta-bathrooms" name="ovr_meta[bathrooms]"
               min="0" step="0.5" value="<?php echo esc_attr( (string) $bathrooms ); ?>">
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-max-guests"><?php esc_html_e( 'Max Guests', 'ovr-core' ); ?></label>
        <input type="number" id="ovr-meta-max-guests" name="ovr_meta[max_guests]"
               min="1" step="1" value="<?php echo esc_attr( (string) $max_guests ); ?>">
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-sqft"><?php esc_html_e( 'Square Feet', 'ovr-core' ); ?></label>
        <input type="number" id="ovr-meta-sqft" name="ovr_meta[sqft]"
               min="0" step="1" value="<?php echo esc_attr( (string) $sqft ); ?>">
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-min-stay"><?php esc_html_e( 'Minimum Stay (nights)', 'ovr-core' ); ?></label>
        <input type="number" id="ovr-meta-min-stay" name="ovr_meta[min_stay]"
               min="1" step="1" value="<?php echo esc_attr( (string) $min_stay ); ?>">
    </div>
</div>

<div class="ovr-section-head" style="margin-top:32px">
    <h3><span class="material-symbols-outlined">tune</span> <?php esc_html_e( 'Listing Settings', 'ovr-core' ); ?></h3>
</div>

<div class="ovr-field-grid ovr-field-grid--2">
    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-status"><?php esc_html_e( 'Listing Status', 'ovr-core' ); ?></label>
        <select id="ovr-meta-status" name="ovr_meta[listing_status]">
            <option value="active"          <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active — visible to public', 'ovr-core' ); ?></option>
            <option value="inactive"        <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive — hidden from public', 'ovr-core' ); ?></option>
            <option value="pending_renewal" <?php selected( $status, 'pending_renewal' ); ?>><?php esc_html_e( 'Active Pending Renewal — admin-only', 'ovr-core' ); ?></option>
            <option value="draft"           <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'ovr-core' ); ?></option>
        </select>
        <p class="ovr-field__hint"><?php esc_html_e( 'Independent of WordPress publish status — controls public visibility & availability.', 'ovr-core' ); ?></p>
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-booking-mode"><?php esc_html_e( 'Booking Mode', 'ovr-core' ); ?></label>
        <select id="ovr-meta-booking-mode" name="ovr_meta[booking_mode]">
            <option value="direct"  <?php selected( $booking_mode, 'direct' ); ?>><?php esc_html_e( 'Direct Booking — guest pays online', 'ovr-core' ); ?></option>
            <option value="inquiry" <?php selected( $booking_mode, 'inquiry' ); ?>><?php esc_html_e( 'Inquiry First — guest contacts owner', 'ovr-core' ); ?></option>
        </select>
        <p class="ovr-field__hint"><?php esc_html_e( 'Controls whether guests book directly with payment or submit an inquiry to the property owner.', 'ovr-core' ); ?></p>
    </div>

    <label class="ovr-checkbox-row" for="ovr-meta-pets">
        <input type="checkbox" id="ovr-meta-pets" name="ovr_meta[pets_allowed]" value="1" <?php checked( $pets ); ?>>
        <span class="ovr-checkbox-row__text">
            <strong><?php esc_html_e( 'Pets Allowed', 'ovr-core' ); ?></strong>
            <small><?php esc_html_e( 'Show the pets-friendly badge on the listing.', 'ovr-core' ); ?></small>
        </span>
    </label>
</div>
