<?php
/**
 * Availability Tab — iCal URL + manual block-out date repeater.
 *
 * @package OVR
 * @var array $meta
 * @var array $availability  Manual blocks (rows from wp_ovr_availability where source='manual').
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$availability = is_array( $availability ?? null ) ? $availability : [];
$ical_url     = (string) ( $meta['ical_url'] ?? '' );

// Last-sync info (set by IcalSync after each run).
$post_id          = isset( $post ) && $post instanceof WP_Post ? (int) $post->ID : 0;
$last_sync_time   = $post_id ? (string) get_post_meta( $post_id, '_ovr_ical_last_sync', true ) : '';
$last_sync_result = $post_id ? get_post_meta( $post_id, '_ovr_ical_last_result', true ) : null;
$last_sync_msg    = is_array( $last_sync_result ) ? (string) ( $last_sync_result['message'] ?? '' ) : '';
$last_sync_ok     = is_array( $last_sync_result ) ? ! empty( $last_sync_result['success'] )       : true;

$block_types = [
    'blocked'     => __( 'Blocked', 'ovr-core' ),
    'booked'      => __( 'Booked', 'ovr-core' ),
    'tentative'   => __( 'Tentative', 'ovr-core' ),
    'maintenance' => __( 'Maintenance', 'ovr-core' ),
];
?>
<p class="ovr-meta-tabs__panel-intro">
    <?php esc_html_e( 'Block out dates so guests cannot inquire when you are unavailable. You can sync from another platform via iCal or add blocks manually below.', 'ovr-core' ); ?>
</p>

<div class="ovr-section-head">
    <h3><span class="material-symbols-outlined">sync</span> <?php esc_html_e( 'iCal Calendar Sync', 'ovr-core' ); ?></h3>
</div>

<div class="ovr-field">
    <label class="ovr-field__label" for="ovr-meta-ical"><?php esc_html_e( 'iCal Feed URL', 'ovr-core' ); ?></label>
    <div style="display:flex;gap:8px;align-items:stretch">
        <input type="url" id="ovr-meta-ical" name="ovr_meta[ical_url]"
               value="<?php echo esc_attr( $ical_url ); ?>"
               placeholder="https://www.airbnb.com/calendar/ical/…"
               style="flex:1">
        <button type="button"
                class="ovr-btn-admin ovr-btn-admin--ghost"
                data-ovr-ical-sync
                data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
                <?php if ( ! $ical_url || ! $post_id ) echo 'disabled'; ?>>
            <span class="material-symbols-outlined">sync</span>
            <?php esc_html_e( 'Sync now', 'ovr-core' ); ?>
        </button>
    </div>
    <p class="ovr-field__hint">
        <?php esc_html_e( 'Paste an iCal URL from Airbnb, VRBO, Booking.com, or Google Calendar. Save the property first, then click "Sync now". We also pull updates automatically every hour.', 'ovr-core' ); ?>
    </p>

    <?php if ( $last_sync_time ) : ?>
        <p style="margin-top:10px;font-size:13px;display:flex;align-items:center;gap:6px;color:<?php echo $last_sync_ok ? '#00714e' : '#ba1a1a'; ?>">
            <span class="material-symbols-outlined" style="font-size:16px">
                <?php echo $last_sync_ok ? 'check_circle' : 'error'; ?>
            </span>
            <strong><?php esc_html_e( 'Last sync:', 'ovr-core' ); ?></strong>
            <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync_time ) ); ?>
            <?php if ( $last_sync_msg ) : ?>
                — <?php echo esc_html( $last_sync_msg ); ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <p data-ovr-ical-result style="margin-top:6px;font-size:13px"></p>
</div>

<div class="ovr-repeater ovr-repeater--avail" data-ovr-repeater style="margin-top:32px">

    <div class="ovr-section-head" style="margin-top:0">
        <h3><span class="material-symbols-outlined">event_busy</span> <?php esc_html_e( 'Manual Blocks', 'ovr-core' ); ?></h3>
        <button type="button" class="ovr-btn-admin ovr-btn-admin--ghost" data-ovr-repeater-add>
            <span class="material-symbols-outlined">add</span>
            <?php esc_html_e( 'Add date range', 'ovr-core' ); ?>
        </button>
    </div>

    <div class="ovr-repeater__rows" data-ovr-repeater-rows>
        <?php foreach ( $availability as $i => $row ) :
            $start = (string) ( $row['start_date']        ?? '' );
            $end   = (string) ( $row['end_date']          ?? '' );
            $type  = (string) ( $row['block_type']        ?? 'blocked' );
            $notes = (string) ( $row['notes']             ?? '' );
            $show  = ! empty( $row['show_as_available'] );
        ?>
            <div class="ovr-repeater__row" data-ovr-repeater-row>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'Start', 'ovr-core' ); ?></label>
                    <input type="date" name="ovr_meta[availability][<?php echo esc_attr( (string) $i ); ?>][start_date]"
                           value="<?php echo esc_attr( $start ); ?>">
                </div>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'End', 'ovr-core' ); ?></label>
                    <input type="date" name="ovr_meta[availability][<?php echo esc_attr( (string) $i ); ?>][end_date]"
                           value="<?php echo esc_attr( $end ); ?>">
                </div>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'Type', 'ovr-core' ); ?></label>
                    <select name="ovr_meta[availability][<?php echo esc_attr( (string) $i ); ?>][block_type]">
                        <?php foreach ( $block_types as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $type ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ovr-field">
                    <label class="ovr-field__label"><?php esc_html_e( 'Notes', 'ovr-core' ); ?></label>
                    <input type="text" name="ovr_meta[availability][<?php echo esc_attr( (string) $i ); ?>][notes]"
                           value="<?php echo esc_attr( $notes ); ?>"
                           placeholder="<?php esc_attr_e( 'Internal note (optional)', 'ovr-core' ); ?>">
                </div>
                <div class="ovr-field">
                    <label class="ovr-checkbox-row" style="background:transparent;border:none;padding:0;margin-top:18px">
                        <input type="checkbox"
                               name="ovr_meta[availability][<?php echo esc_attr( (string) $i ); ?>][show_as_available]"
                               value="1" <?php checked( $show ); ?>>
                        <span class="ovr-checkbox-row__text" style="font-size:12px">
                            <?php esc_html_e( 'Show as available', 'ovr-core' ); ?>
                        </span>
                    </label>
                </div>
                <div class="ovr-repeater__remove">
                    <button type="button" class="ovr-btn-admin ovr-btn-admin--danger" data-ovr-repeater-remove>
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ( empty( $availability ) ) : ?>
        <div class="ovr-repeater__empty" data-ovr-repeater-empty>
            <?php esc_html_e( 'No manual blocks yet. Click "Add date range" to block out unavailable dates.', 'ovr-core' ); ?>
        </div>
    <?php else : ?>
        <div class="ovr-repeater__empty" data-ovr-repeater-empty style="display:none">
            <?php esc_html_e( 'No manual blocks yet. Click "Add date range" to block out unavailable dates.', 'ovr-core' ); ?>
        </div>
    <?php endif; ?>

    <!-- Template clone source -->
    <template data-ovr-repeater-tpl>
        <div class="ovr-repeater__row" data-ovr-repeater-row>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'Start', 'ovr-core' ); ?></label>
                <input type="date" name="ovr_meta[availability][__INDEX__][start_date]">
            </div>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'End', 'ovr-core' ); ?></label>
                <input type="date" name="ovr_meta[availability][__INDEX__][end_date]">
            </div>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'Type', 'ovr-core' ); ?></label>
                <select name="ovr_meta[availability][__INDEX__][block_type]">
                    <?php foreach ( $block_types as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ovr-field">
                <label class="ovr-field__label"><?php esc_html_e( 'Notes', 'ovr-core' ); ?></label>
                <input type="text" name="ovr_meta[availability][__INDEX__][notes]"
                       placeholder="<?php esc_attr_e( 'Internal note (optional)', 'ovr-core' ); ?>">
            </div>
            <div class="ovr-field">
                <label class="ovr-checkbox-row" style="background:transparent;border:none;padding:0;margin-top:18px">
                    <input type="checkbox" name="ovr_meta[availability][__INDEX__][show_as_available]" value="1">
                    <span class="ovr-checkbox-row__text" style="font-size:12px">
                        <?php esc_html_e( 'Show as available', 'ovr-core' ); ?>
                    </span>
                </label>
            </div>
            <div class="ovr-repeater__remove">
                <button type="button" class="ovr-btn-admin ovr-btn-admin--danger" data-ovr-repeater-remove>
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
        </div>
    </template>
</div>
