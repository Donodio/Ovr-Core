<?php
/**
 * Pricing Tab — base price and links to seasonal pricing tab.
 *
 * @package OVR
 * @var array $meta
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$base_price = (float) ( $meta['base_price'] ?? 0 );

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';
?>
<p class="ovr-meta-tabs__panel-intro">
    <?php esc_html_e( 'Set a base nightly rate. Travelers see this when no seasonal rate applies. Configure season-specific rates in the Seasonal Pricing tab.', 'ovr-core' ); ?>
</p>

<div class="ovr-section-head">
    <h3><span class="material-symbols-outlined">payments</span> <?php esc_html_e( 'Base Pricing', 'ovr-core' ); ?></h3>
</div>

<div class="ovr-field-grid ovr-field-grid--2">

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-base-price">
            <?php esc_html_e( 'Base Nightly Rate', 'ovr-core' ); ?>
        </label>
        <div class="ovr-field__row">
            <span class="ovr-field__suffix"><?php echo esc_html( $symbol ); ?></span>
            <input type="number" id="ovr-meta-base-price" name="ovr_meta[base_price]"
                   min="0" step="0.01" value="<?php echo esc_attr( number_format( $base_price, 2, '.', '' ) ); ?>">
            <span class="ovr-field__suffix">/ <?php esc_html_e( 'night', 'ovr-core' ); ?></span>
        </div>
        <p class="ovr-field__hint">
            <?php esc_html_e( 'Used as the fallback rate when no seasonal price applies and as the price displayed on the property card.', 'ovr-core' ); ?>
        </p>
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label"><?php esc_html_e( 'Need Seasonal Rates?', 'ovr-core' ); ?></label>
        <div style="padding:14px;background:var(--ovr-a-surface-low);border:1px solid var(--ovr-a-outline);border-radius:var(--ovr-a-radius-md);font-size:13px;color:var(--ovr-a-text-soft);line-height:1.5">
            <?php esc_html_e( 'Switch to the', 'ovr-core' ); ?>
            <a href="#ovr-tab-seasonal" data-ovr-jump-tab="seasonal" style="color:var(--ovr-a-primary);font-weight:600;text-decoration:none">
                <?php esc_html_e( 'Seasonal Pricing tab', 'ovr-core' ); ?>
            </a>
            <?php esc_html_e( 'to add high/low season rates and minimum-stay rules.', 'ovr-core' ); ?>
        </div>
    </div>
</div>

<script>
    // Quick tab-jump from the inline link.
    document.addEventListener('click', function (e) {
        var jump = e.target.closest('[data-ovr-jump-tab]');
        if (!jump) return;
        e.preventDefault();
        var btn = document.querySelector('.ovr-meta-tabs__btn[data-tab="' + jump.dataset.ovrJumpTab + '"]');
        if (btn) btn.click();
    });
</script>
