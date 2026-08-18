<?php
/**
 * Sidebar Promotion Box.
 *
 * Compact view in the right rail of the editor: status pill, featured /
 * bumped toggles with expiry dates.
 *
 * @package OVR
 * @var WP_Post $post
 * @var array   $meta
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$status        = (string) ( $meta['listing_status']   ?? 'active' );
$is_featured   = ! empty( $meta['is_featured'] );
$is_bumped     = ! empty( $meta['is_bumped'] );
$in_slider     = ! empty( $meta['in_slider'] );
$is_deal       = ! empty( $meta['is_deal'] );
$feat_expires  = (string) ( $meta['featured_expires'] ?? '' );
$bump_expires  = (string) ( $meta['bump_expires']     ?? '' );
$slider_expires= (string) ( $meta['slider_expires']   ?? '' );
$deal_expires  = (string) ( $meta['deal_expires']     ?? '' );

$status_label = [
    'active'          => __( 'Active', 'ovr-core' ),
    'inactive'        => __( 'Inactive', 'ovr-core' ),
    'pending_renewal' => __( 'Pending Renewal', 'ovr-core' ),
    'draft'           => __( 'Draft', 'ovr-core' ),
];
$pill_class = [
    'active'          => 'ovr-status-pill--active',
    'inactive'        => 'ovr-status-pill--inactive',
    'pending_renewal' => 'ovr-status-pill--bumped',
    'draft'           => 'ovr-status-pill--inactive',
];
?>
<div class="ovr-promo-card">

    <!-- Status pill -->
    <div>
        <p style="margin:0 0 8px;font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--ovr-a-text-soft)">
            <?php esc_html_e( 'Listing Status', 'ovr-core' ); ?>
        </p>
        <span class="ovr-status-pill <?php echo esc_attr( $pill_class[ $status ] ?? '' ); ?>">
            <span class="material-symbols-outlined" style="font-size:14px">
                <?php echo $status === 'active' ? 'check_circle' : ( $status === 'draft' ? 'edit_note' : 'schedule' ); ?>
            </span>
            <?php echo esc_html( $status_label[ $status ] ?? $status ); ?>
        </span>
        <?php if ( $is_featured ) : ?>
            <span class="ovr-status-pill ovr-status-pill--featured" style="margin-left:6px">
                <span class="material-symbols-outlined" style="font-size:14px">star</span>
                <?php esc_html_e( 'Featured', 'ovr-core' ); ?>
            </span>
        <?php endif; ?>
        <?php if ( $is_bumped ) : ?>
            <span class="ovr-status-pill ovr-status-pill--bumped" style="margin-left:6px">
                <span class="material-symbols-outlined" style="font-size:14px">trending_up</span>
                <?php esc_html_e( 'Bumped', 'ovr-core' ); ?>
            </span>
        <?php endif; ?>
        <?php if ( $in_slider ) : ?>
            <span class="ovr-status-pill" style="margin-left:6px;background:var(--ovr-a-accent-soft);color:var(--ovr-a-accent)">
                <span class="material-symbols-outlined" style="font-size:14px">view_carousel</span>
                <?php esc_html_e( 'Slider', 'ovr-core' ); ?>
            </span>
        <?php endif; ?>
        <?php if ( $is_deal ) : ?>
            <span class="ovr-status-pill" style="margin-left:6px;background:var(--ovr-a-accent-soft);color:var(--ovr-a-accent)">
                <span class="material-symbols-outlined" style="font-size:14px">local_offer</span>
                <?php esc_html_e( 'Deal', 'ovr-core' ); ?>
            </span>
        <?php endif; ?>
        <p style="margin:8px 0 0;font-size:12px;color:var(--ovr-a-text-soft)">
            <?php esc_html_e( 'Change status in the General tab.', 'ovr-core' ); ?>
        </p>
    </div>

    <!-- Featured Listing -->
    <div class="ovr-field">
        <label class="ovr-checkbox-row" for="ovr-side-featured" style="background:transparent;border:none;padding:0">
            <input type="checkbox" id="ovr-side-featured"
                   name="ovr_meta[is_featured]"
                   value="1" <?php checked( $is_featured ); ?>>
            <span class="ovr-checkbox-row__text">
                <strong><?php esc_html_e( 'Featured Listing', 'ovr-core' ); ?></strong>
                <small><?php esc_html_e( 'Adds a gold "Featured" badge and prioritises display.', 'ovr-core' ); ?></small>
            </span>
        </label>
        <input type="date"
               name="ovr_meta[featured_expires]"
               value="<?php echo esc_attr( $feat_expires ); ?>"
               style="margin-top:10px"
               aria-label="<?php esc_attr_e( 'Featured expires on', 'ovr-core' ); ?>">
        <p class="ovr-field__hint" style="margin-top:4px"><?php esc_html_e( 'Auto-removes after this date (leave blank for no expiry).', 'ovr-core' ); ?></p>
    </div>

    <!-- Bumped -->
    <div class="ovr-field">
        <label class="ovr-checkbox-row" for="ovr-side-bumped" style="background:transparent;border:none;padding:0">
            <input type="checkbox" id="ovr-side-bumped"
                   name="ovr_meta[is_bumped]"
                   value="1" <?php checked( $is_bumped ); ?>>
            <span class="ovr-checkbox-row__text">
                <strong><?php esc_html_e( 'Bumped to Top', 'ovr-core' ); ?></strong>
                <small><?php esc_html_e( 'Pushes this listing above others in search results.', 'ovr-core' ); ?></small>
            </span>
        </label>
        <input type="date"
               name="ovr_meta[bump_expires]"
               value="<?php echo esc_attr( $bump_expires ); ?>"
               style="margin-top:10px"
               aria-label="<?php esc_attr_e( 'Bump expires on', 'ovr-core' ); ?>">
        <p class="ovr-field__hint" style="margin-top:4px"><?php esc_html_e( 'Auto-removes after this date (leave blank for no expiry).', 'ovr-core' ); ?></p>
    </div>

    <!-- Homepage Slider -->
    <div class="ovr-field">
        <label class="ovr-checkbox-row" for="ovr-side-slider" style="background:transparent;border:none;padding:0">
            <input type="checkbox" id="ovr-side-slider"
                   name="ovr_meta[in_slider]"
                   value="1" <?php checked( $in_slider ); ?>>
            <span class="ovr-checkbox-row__text">
                <strong><?php esc_html_e( 'Homepage Slider', 'ovr-core' ); ?></strong>
                <small><?php esc_html_e( 'Rotates in the homepage slideshow (does not affect search ranking).', 'ovr-core' ); ?></small>
            </span>
        </label>
        <input type="date"
               name="ovr_meta[slider_expires]"
               value="<?php echo esc_attr( $slider_expires ); ?>"
               style="margin-top:10px"
               aria-label="<?php esc_attr_e( 'Slider expires on', 'ovr-core' ); ?>">
        <p class="ovr-field__hint" style="margin-top:4px"><?php esc_html_e( 'Auto-removes after this date (leave blank for no expiry).', 'ovr-core' ); ?></p>
    </div>

    <!-- Deals & Cancellations -->
    <div class="ovr-field">
        <label class="ovr-checkbox-row" for="ovr-side-deal" style="background:transparent;border:none;padding:0">
            <input type="checkbox" id="ovr-side-deal"
                   name="ovr_meta[is_deal]"
                   value="1" <?php checked( $is_deal ); ?>>
            <span class="ovr-checkbox-row__text">
                <strong><?php esc_html_e( 'Deals & Cancellations', 'ovr-core' ); ?></strong>
                <small><?php esc_html_e( 'Surfaces on the public Deals & Cancellations page for the duration.', 'ovr-core' ); ?></small>
            </span>
        </label>
        <input type="date"
               name="ovr_meta[deal_expires]"
               value="<?php echo esc_attr( $deal_expires ); ?>"
               style="margin-top:10px"
               aria-label="<?php esc_attr_e( 'Deal expires on', 'ovr-core' ); ?>">
        <p class="ovr-field__hint" style="margin-top:4px"><?php esc_html_e( 'Auto-removes after this date (leave blank for no expiry).', 'ovr-core' ); ?></p>
    </div>

</div>
