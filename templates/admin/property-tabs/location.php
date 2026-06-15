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
</div>

<div class="ovr-section-head" style="margin-top:32px">
    <h3><span class="material-symbols-outlined">my_location</span> <?php esc_html_e( 'Map', 'ovr-core' ); ?></h3>
</div>

<p class="ovr-field__hint" style="margin-bottom:16px;font-size:13px">
    <?php esc_html_e( 'The map is generated automatically from the address above — there is nothing to place by hand. Coordinates refresh when you save; the hourly backfill also fills in any listing still missing a map.', 'ovr-core' ); ?>
</p>

<?php if ( $lat && $lng ) : ?>
    <p class="ovr-field__hint" style="margin-bottom:8px;font-size:12px;color:var(--ovr-a-on-surface-variant)">
        <?php
        /* translators: 1: latitude, 2: longitude */
        printf( esc_html__( 'Detected location: %1$s, %2$s', 'ovr-core' ), esc_html( (string) $lat ), esc_html( (string) $lng ) );
        ?>
    </p>
    <div style="border-radius:var(--ovr-a-radius-md);overflow:hidden;border:1px solid var(--ovr-a-outline)">
        <iframe
            src="<?php echo esc_url( 'https://www.openstreetmap.org/export/embed.html?bbox=' . ( $lng - 0.01 ) . ',' . ( $lat - 0.01 ) . ',' . ( $lng + 0.01 ) . ',' . ( $lat + 0.01 ) . '&layer=mapnik&marker=' . $lat . ',' . $lng ); ?>"
            width="100%" height="280" frameborder="0"
            loading="lazy"
            style="border:0;display:block"
            title="<?php esc_attr_e( 'Property location preview', 'ovr-core' ); ?>"></iframe>
    </div>
<?php else : ?>
    <p class="ovr-field__hint" style="font-size:13px"><?php esc_html_e( 'No map yet — add an address and save, and the map will be generated automatically.', 'ovr-core' ); ?></p>
<?php endif; ?>
