<?php
/**
 * Seasonal Pricing Tab — repeater for season name, dates, price, billing
 * period, and minimum term. Mirrors the production per-unit pricing model used
 * by the front-end landlord editor (SeasonalPricing::save_pricing), so both
 * admin and landlord write the same row shape into wp_ovr_seasonal_pricing.
 *
 * @package OVR
 * @var array $seasonal Existing rows from wp_ovr_seasonal_pricing.
 * @var array $meta     Property meta (for the hide-pricing override).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Property\SeasonalPricing;

$seasonal = is_array( $seasonal ?? null ) ? $seasonal : [];

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';
// A listing always needs *something* for pricing: with no rows the override
// defaults to on. Once rows exist the stored choice is left exactly as saved.
$hidden   = ! empty( $meta['hide_pricing'] ) || empty( $seasonal );

/** The billing-period dropdown options (Per Day / Per Week / Per Month / Flat Rate). */
$per_options = [
    'per_day'   => __( 'Per Day', 'ovr-core' ),
    'per_week'  => __( 'Per Week', 'ovr-core' ),
    'per_month' => __( 'Per Month', 'ovr-core' ),
    'flat'      => __( 'Flat Rate', 'ovr-core' ),
];

/**
 * Render the <option> list for a row's billing period, selecting the row's
 * current per-unit (resolved from its stored rate_type for legacy rows).
 */
$render_per = static function ( string $selected ) use ( $per_options ) {
    $out = '';
    foreach ( $per_options as $key => $label ) {
        $out .= sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr( $key ),
            selected( $selected, $key, false ),
            esc_html( $label )
        );
    }
    return $out;
};
?>
<p class="ovr-meta-tabs__panel-intro">
    <?php esc_html_e( 'Add a row for each pricing period — weekly, monthly, seasonal, or a fixed-term block. Set a price, choose how it bills, and (optionally) a minimum term. Add as many rows as you need; they display on the listing in the order shown here.', 'ovr-core' ); ?>
</p>

<div class="ovr-field ovr-field--full" style="margin-bottom:14px">
    <label class="ovr-field__label" style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="ovr_meta[hide_pricing]" value="1" <?php checked( $hidden ); ?>>
        <?php esc_html_e( 'See Description For Pricing', 'ovr-core' ); ?>
    </label>
    <p class="description" style="margin:4px 0 0 24px">
        <?php esc_html_e( 'Display-only: when enabled the pricing table is not shown on the listing and renters see "See Description For Pricing". The rows below are always kept.', 'ovr-core' ); ?>
    </p>
</div>

<?php // Sentinel: proves this repeater was part of the submit, so a save that
      // never rendered it can't clear the stored rows. ?>
<input type="hidden" name="ovr_meta[seasonal_present]" value="1">

<div class="ovr-repeater" data-ovr-repeater>

    <div class="ovr-section-head" style="margin-top:0">
        <h3><span class="material-symbols-outlined">calendar_month</span> <?php esc_html_e( 'Pricing Periods', 'ovr-core' ); ?></h3>
        <button type="button" class="ovr-btn-admin ovr-btn-admin--ghost" data-ovr-repeater-add>
            <span class="material-symbols-outlined">add</span>
            <?php esc_html_e( 'Add row', 'ovr-core' ); ?>
        </button>
    </div>

    <div class="ovr-repeater__rows" data-ovr-repeater-rows>
        <?php foreach ( $seasonal as $i => $row ) :
            $disp  = SeasonalPricing::row_display( (array) $row );
            $name  = $disp['period'];
            $start = $disp['from'];
            $end   = $disp['to'];
            $price = $disp['price'];
            $per   = $disp['per_key'];
            $min   = (int) ( $row['min_stay'] ?? 0 );
        ?>
            <div class="ovr-repeater__row" data-ovr-repeater-row>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'Season Name', 'ovr-core' ); ?></label>
                    <input type="text" name="ovr_meta[seasonal][<?php echo esc_attr( (string) $i ); ?>][season_name]"
                           value="<?php echo esc_attr( $name ); ?>"
                           placeholder="<?php esc_attr_e( 'e.g. Winter 2027', 'ovr-core' ); ?>">
                </div>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'Start Date', 'ovr-core' ); ?></label>
                    <input type="date" name="ovr_meta[seasonal][<?php echo esc_attr( (string) $i ); ?>][start_date]"
                           value="<?php echo esc_attr( $start ); ?>">
                </div>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'End Date', 'ovr-core' ); ?></label>
                    <input type="date" name="ovr_meta[seasonal][<?php echo esc_attr( (string) $i ); ?>][end_date]"
                           value="<?php echo esc_attr( $end ); ?>">
                </div>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php echo esc_html( $symbol ); ?> <?php esc_html_e( 'Price', 'ovr-core' ); ?></label>
                    <input type="number" name="ovr_meta[seasonal][<?php echo esc_attr( (string) $i ); ?>][price]"
                           min="0" step="0.01"
                           value="<?php echo esc_attr( $price > 0 ? number_format( $price, 2, '.', '' ) : '' ); ?>">
                </div>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'Billing Period', 'ovr-core' ); ?></label>
                    <select name="ovr_meta[seasonal][<?php echo esc_attr( (string) $i ); ?>][per]">
                        <?php echo $render_per( $per ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?>
                    </select>
                </div>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'Minimum Term', 'ovr-core' ); ?></label>
                    <input type="number" name="ovr_meta[seasonal][<?php echo esc_attr( (string) $i ); ?>][min_stay]"
                           min="0" step="1"
                           value="<?php echo esc_attr( (string) max( 0, $min ) ); ?>">
                </div>
                <div class="ovr-repeater__remove">
                    <button type="button" class="ovr-btn-admin ovr-btn-admin--danger" data-ovr-repeater-remove>
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="ovr-repeater__empty" data-ovr-repeater-empty<?php echo empty( $seasonal ) ? '' : ' style="display:none"'; ?>>
        <?php esc_html_e( 'No pricing rows yet. Click "Add row" to create one.', 'ovr-core' ); ?>
    </div>

    <!-- Template clone source -->
    <template data-ovr-repeater-tpl>
        <div class="ovr-repeater__row" data-ovr-repeater-row>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'Season Name', 'ovr-core' ); ?></label>
                <input type="text" name="ovr_meta[seasonal][__INDEX__][season_name]"
                       placeholder="<?php esc_attr_e( 'e.g. Winter 2027', 'ovr-core' ); ?>">
            </div>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'Start Date', 'ovr-core' ); ?></label>
                <input type="date" name="ovr_meta[seasonal][__INDEX__][start_date]">
            </div>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'End Date', 'ovr-core' ); ?></label>
                <input type="date" name="ovr_meta[seasonal][__INDEX__][end_date]">
            </div>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php echo esc_html( $symbol ); ?> <?php esc_html_e( 'Price', 'ovr-core' ); ?></label>
                <input type="number" name="ovr_meta[seasonal][__INDEX__][price]"
                       min="0" step="0.01">
            </div>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'Billing Period', 'ovr-core' ); ?></label>
                <select name="ovr_meta[seasonal][__INDEX__][per]">
                    <?php echo $render_per( 'per_month' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </select>
            </div>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'Minimum Term', 'ovr-core' ); ?></label>
                <input type="number" name="ovr_meta[seasonal][__INDEX__][min_stay]"
                       min="0" step="1" value="0">
            </div>
            <div class="ovr-repeater__remove">
                <button type="button" class="ovr-btn-admin ovr-btn-admin--danger" data-ovr-repeater-remove>
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
        </div>
    </template>
</div>
