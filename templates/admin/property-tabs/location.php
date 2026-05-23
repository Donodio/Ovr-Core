<?php
/**
 * Location Tab — address fields + lat/lng.
 *
 * @package OVR
 * @var array $meta
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$address  = (string) ( $meta['address']  ?? '' );
$city     = (string) ( $meta['city']     ?? '' );
$state    = (string) ( $meta['state']    ?? '' );
$zip      = (string) ( $meta['zip']      ?? '' );
$country  = (string) ( $meta['country']  ?? '' );
$lat      = (float)  ( $meta['latitude']  ?? 0 );
$lng      = (float)  ( $meta['longitude'] ?? 0 );
?>
<p class="ovr-meta-tabs__panel-intro">
    <?php esc_html_e( 'Where is the property? Address shows on the listing page and powers the embedded map.', 'ovr-core' ); ?>
</p>

<div class="ovr-section-head">
    <h3><span class="material-symbols-outlined">pin_drop</span> <?php esc_html_e( 'Address', 'ovr-core' ); ?></h3>
</div>

<div class="ovr-field-grid">
    <div class="ovr-field ovr-field--full">
        <label class="ovr-field__label" for="ovr-meta-address"><?php esc_html_e( 'Street Address', 'ovr-core' ); ?></label>
        <input type="text" id="ovr-meta-address" name="ovr_meta[address]"
               value="<?php echo esc_attr( $address ); ?>"
               placeholder="123 Oak Lane">
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-city"><?php esc_html_e( 'City', 'ovr-core' ); ?></label>
        <input type="text" id="ovr-meta-city" name="ovr_meta[city]"
               value="<?php echo esc_attr( $city ); ?>">
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-state"><?php esc_html_e( 'State / Region', 'ovr-core' ); ?></label>
        <input type="text" id="ovr-meta-state" name="ovr_meta[state]"
               value="<?php echo esc_attr( $state ); ?>">
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-zip"><?php esc_html_e( 'ZIP / Postal Code', 'ovr-core' ); ?></label>
        <input type="text" id="ovr-meta-zip" name="ovr_meta[zip]"
               value="<?php echo esc_attr( $zip ); ?>">
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-country"><?php esc_html_e( 'Country', 'ovr-core' ); ?></label>
        <input type="text" id="ovr-meta-country" name="ovr_meta[country]"
               value="<?php echo esc_attr( $country ); ?>">
    </div>
</div>

<div class="ovr-section-head" style="margin-top:32px">
    <h3><span class="material-symbols-outlined">my_location</span> <?php esc_html_e( 'Map Coordinates', 'ovr-core' ); ?></h3>
</div>

<p class="ovr-field__hint" style="margin-bottom:16px;font-size:13px">
    <?php
    $tip = sprintf(
        /* translators: %s: link to OpenStreetMap */
        esc_html__( 'Find coordinates by right-clicking the location on %s and selecting "Show address" → copy the lat/lng pair.', 'ovr-core' ),
        '<a href="https://www.openstreetmap.org/" target="_blank" rel="noopener" style="color:var(--ovr-a-primary);font-weight:600">OpenStreetMap</a>'
    );
    echo wp_kses( $tip, [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [], 'style' => [] ] ] );
    ?>
</p>

<div class="ovr-field-grid ovr-field-grid--2">
    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-lat"><?php esc_html_e( 'Latitude', 'ovr-core' ); ?></label>
        <input type="number" id="ovr-meta-lat" name="ovr_meta[latitude]"
               step="any" value="<?php echo esc_attr( $lat ? (string) $lat : '' ); ?>"
               placeholder="40.7128">
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-lng"><?php esc_html_e( 'Longitude', 'ovr-core' ); ?></label>
        <input type="number" id="ovr-meta-lng" name="ovr_meta[longitude]"
               step="any" value="<?php echo esc_attr( $lng ? (string) $lng : '' ); ?>"
               placeholder="-74.0060">
    </div>
</div>

<?php if ( $lat && $lng ) : ?>
    <div style="margin-top:24px;border-radius:var(--ovr-a-radius-md);overflow:hidden;border:1px solid var(--ovr-a-outline)">
        <iframe
            src="<?php echo esc_url( 'https://www.openstreetmap.org/export/embed.html?bbox=' . ( $lng - 0.01 ) . ',' . ( $lat - 0.01 ) . ',' . ( $lng + 0.01 ) . ',' . ( $lat + 0.01 ) . '&layer=mapnik&marker=' . $lat . ',' . $lng ); ?>"
            width="100%" height="280" frameborder="0"
            loading="lazy"
            style="border:0;display:block"
            title="<?php esc_attr_e( 'Property location preview', 'ovr-core' ); ?>"></iframe>
    </div>
<?php endif; ?>
