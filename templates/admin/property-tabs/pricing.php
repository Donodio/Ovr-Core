<?php
/**
 * Pricing Tab — base price and links to seasonal pricing tab.
 *
 * @package OVR
 * @var array $meta
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

?>
<p class="ovr-meta-tabs__panel-intro">
    <?php esc_html_e( 'This marketplace uses flexible period pricing — no Airbnb-style nightly rate. Set each way you rent (weekly, monthly, seasonal, or a fixed-term block) in the Seasonal Pricing tab.', 'ovr-core' ); ?>
</p>

<div class="ovr-section-head">
    <h3><span class="material-symbols-outlined">payments</span> <?php esc_html_e( 'Pricing', 'ovr-core' ); ?></h3>
</div>

<div class="ovr-field-grid">
    <div class="ovr-field ovr-field--full">
        <label class="ovr-field__label"><?php esc_html_e( 'Set Your Rates', 'ovr-core' ); ?></label>
        <div style="padding:14px;background:var(--ovr-a-surface-low);border:1px solid var(--ovr-a-outline);border-radius:var(--ovr-a-radius-md);font-size:13px;color:var(--ovr-a-text-soft);line-height:1.5">
            <?php esc_html_e( 'Switch to the', 'ovr-core' ); ?>
            <a href="#ovr-tab-seasonal" data-ovr-jump-tab="seasonal" style="color:var(--ovr-a-primary);font-weight:600;text-decoration:none">
                <?php esc_html_e( 'Seasonal Pricing tab', 'ovr-core' ); ?>
            </a>
            <?php esc_html_e( 'to add each period, its price, and its minimum-stay rule. If you leave it empty, the listing shows "See Description For Pricing".', 'ovr-core' ); ?>
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
