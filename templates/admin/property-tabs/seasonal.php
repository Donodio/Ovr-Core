<?php
/**
 * Seasonal Pricing Tab — repeater for season name, dates, rate, min stay.
 *
 * @package OVR
 * @var array $seasonal Existing rows from wp_ovr_seasonal_pricing.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$seasonal = is_array( $seasonal ?? null ) ? $seasonal : [];

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';
?>
<p class="ovr-meta-tabs__panel-intro">
    <?php esc_html_e( 'Charge different rates for high season, low season, holidays, etc. Each row maps a date range to a nightly rate. Rules apply in the order they appear — the first matching range wins.', 'ovr-core' ); ?>
</p>

<div class="ovr-repeater" data-ovr-repeater>

    <div class="ovr-section-head" style="margin-top:0">
        <h3><span class="material-symbols-outlined">calendar_month</span> <?php esc_html_e( 'Seasonal Rates', 'ovr-core' ); ?></h3>
        <button type="button" class="ovr-btn-admin ovr-btn-admin--ghost" data-ovr-repeater-add>
            <span class="material-symbols-outlined">add</span>
            <?php esc_html_e( 'Add season', 'ovr-core' ); ?>
        </button>
    </div>

    <div class="ovr-repeater__rows" data-ovr-repeater-rows>
        <?php foreach ( $seasonal as $i => $row ) :
            $name  = (string) ( $row['season_name']  ?? '' );
            $start = (string) ( $row['start_date']   ?? '' );
            $end   = (string) ( $row['end_date']     ?? '' );
            $rate  = (float)  ( $row['nightly_rate'] ?? 0 );
            $min   = (int)    ( $row['min_stay']     ?? 1 );
        ?>
            <div class="ovr-repeater__row" data-ovr-repeater-row>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'Season Name', 'ovr-core' ); ?></label>
                    <input type="text" name="ovr_meta[seasonal][<?php echo esc_attr( (string) $i ); ?>][season_name]"
                           value="<?php echo esc_attr( $name ); ?>"
                           placeholder="<?php esc_attr_e( 'e.g. High Season', 'ovr-core' ); ?>">
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
                    <label class="ovr-field__label"><?php echo esc_html( $symbol ); ?> / <?php esc_html_e( 'night', 'ovr-core' ); ?></label>
                    <input type="number" name="ovr_meta[seasonal][<?php echo esc_attr( (string) $i ); ?>][nightly_rate]"
                           min="0" step="0.01"
                           value="<?php echo esc_attr( number_format( $rate, 2, '.', '' ) ); ?>">
                </div>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'Min Nights', 'ovr-core' ); ?></label>
                    <input type="number" name="ovr_meta[seasonal][<?php echo esc_attr( (string) $i ); ?>][min_stay]"
                           min="1" step="1"
                           value="<?php echo esc_attr( (string) max( 1, $min ) ); ?>">
                </div>
                <div class="ovr-repeater__remove">
                    <button type="button" class="ovr-btn-admin ovr-btn-admin--danger" data-ovr-repeater-remove>
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ( empty( $seasonal ) ) : ?>
        <div class="ovr-repeater__empty" data-ovr-repeater-empty>
            <?php esc_html_e( 'No seasonal rates yet. Click "Add season" to create one.', 'ovr-core' ); ?>
        </div>
    <?php else : ?>
        <div class="ovr-repeater__empty" data-ovr-repeater-empty style="display:none">
            <?php esc_html_e( 'No seasonal rates yet. Click "Add season" to create one.', 'ovr-core' ); ?>
        </div>
    <?php endif; ?>

    <!-- Template clone source -->
    <template data-ovr-repeater-tpl>
        <div class="ovr-repeater__row" data-ovr-repeater-row>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'Season Name', 'ovr-core' ); ?></label>
                <input type="text" name="ovr_meta[seasonal][__INDEX__][season_name]"
                       placeholder="<?php esc_attr_e( 'e.g. High Season', 'ovr-core' ); ?>">
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
                <label class="ovr-field__label"><?php echo esc_html( $symbol ); ?> / <?php esc_html_e( 'night', 'ovr-core' ); ?></label>
                <input type="number" name="ovr_meta[seasonal][__INDEX__][nightly_rate]"
                       min="0" step="0.01" value="0.00">
            </div>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'Min Nights', 'ovr-core' ); ?></label>
                <input type="number" name="ovr_meta[seasonal][__INDEX__][min_stay]"
                       min="1" step="1" value="1">
            </div>
            <div class="ovr-repeater__remove">
                <button type="button" class="ovr-btn-admin ovr-btn-admin--danger" data-ovr-repeater-remove>
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
        </div>
    </template>
</div>
