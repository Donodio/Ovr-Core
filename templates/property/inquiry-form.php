<?php
/**
 * Property Inquiry / Booking Form.
 *
 * Sticky right-sidebar booking inquiry card on the single property page.
 * Submits via AJAX (handled by AjaxHandler) — falls back to POST.
 *
 * @package OVR
 *
 * @var int    $post_id     Required. Property post ID.
 * @var float  $base_price  Optional. Pre-fetched nightly rate.
 * @var int   $max_guests   Optional.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Property\PropertyMeta;

$post_id    = $post_id ?? 0;
$meta       = PropertyMeta::get_all( $post_id );
$base_price = $base_price ?? (float) ( $meta['base_price'] ?? 0 );
$max_guests = $max_guests ?? (int) ( $meta['max_guests'] ?? 8 );

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';

$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;

// Read inline status from the admin-post.php fallback redirect.
$inquiry_status = isset( $_GET['ovr_inquiry'] ) ? sanitize_key( wp_unslash( $_GET['ovr_inquiry'] ) ) : '';
?>
<aside class="ovr-inquiry-card ovr-card" style="padding:24px;position:sticky;top:104px">

    <!-- Price header -->
    <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:24px">
        <span class="ovr-price-display" style="font-size:32px;color:var(--ovr-primary)">
            <?php echo esc_html( $symbol . number_format( $base_price, 0 ) ); ?>
        </span>
        <span style="color:var(--ovr-on-surface-variant);font-size:16px">
            / <?php esc_html_e( 'night', 'ovr-core' ); ?>
        </span>
    </div>

    <?php if ( 'sent' === $inquiry_status ) : ?>
        <div class="ovr-alert ovr-alert-success" style="margin-bottom:20px">
            <span class="material-symbols-outlined">check_circle</span>
            <span><?php esc_html_e( 'Your inquiry has been sent! The host will be in touch soon.', 'ovr-core' ); ?></span>
        </div>
    <?php elseif ( 'error' === $inquiry_status ) : ?>
        <div class="ovr-alert ovr-alert-error" style="margin-bottom:20px">
            <span class="material-symbols-outlined">error</span>
            <span><?php esc_html_e( 'Something went wrong. Please check your inputs and try again.', 'ovr-core' ); ?></span>
        </div>
    <?php elseif ( 'nonce_failed' === $inquiry_status ) : ?>
        <div class="ovr-alert ovr-alert-error" style="margin-bottom:20px">
            <span class="material-symbols-outlined">error</span>
            <span><?php esc_html_e( 'Security check failed. Please reload the page and try again.', 'ovr-core' ); ?></span>
        </div>
    <?php endif; ?>

    <form class="ovr-inquiry-form"
          data-ovr-inquiry-form
          method="post"
          action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ovr_submit_inquiry">
        <input type="hidden" name="property_id" value="<?php echo esc_attr( $post_id ); ?>">
        <?php wp_nonce_field( 'ovr_inquiry_action', 'ovr_inquiry_nonce' ); ?>

        <!-- Honeypot — hidden from real users, bots fill it. -->
        <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
            <label>Leave this field empty
                <input type="text" name="ovr_hp" tabindex="-1" autocomplete="off">
            </label>
        </div>

        <!-- Date pair -->
        <div class="ovr-inquiry-dates" style="display:grid;grid-template-columns:1fr 1fr;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-lg);overflow:hidden;background:var(--ovr-surface-container-lowest);margin-bottom:12px">
            <div style="padding:4px 10px;border-right:1px solid var(--ovr-outline-variant)">
                <label class="ovr-form-label" style="margin-bottom:0;font-size:11px;" for="ovr-checkin-<?php echo esc_attr( $post_id ); ?>">
                    <?php esc_html_e( 'Check-in', 'ovr-core' ); ?>
                </label>
                <input type="date"
                       id="ovr-checkin-<?php echo esc_attr( $post_id ); ?>"
                       name="checkin_date"
                       style="width:100%;border:none;background:transparent;padding:0;font-size:13px;outline:none;min-height:20px;line-height:1;"
                       required>
            </div>
            <div style="padding:4px 10px">
                <label class="ovr-form-label" style="margin-bottom:0;font-size:11px;" for="ovr-checkout-<?php echo esc_attr( $post_id ); ?>">
                    <?php esc_html_e( 'Checkout', 'ovr-core' ); ?>
                </label>
                <input type="date"
                       id="ovr-checkout-<?php echo esc_attr( $post_id ); ?>"
                       name="checkout_date"
                       style="width:100%;border:none;background:transparent;padding:0;font-size:13px;outline:none;min-height:20px;line-height:1;"
                       required>
            </div>
        </div>

        <!-- Guests -->
        <div class="ovr-form-group" style="margin-bottom:16px">
            <label class="ovr-form-label" for="ovr-guests-<?php echo esc_attr( $post_id ); ?>">
                <?php esc_html_e( 'Guests', 'ovr-core' ); ?>
            </label>
            <select id="ovr-guests-<?php echo esc_attr( $post_id ); ?>"
                    name="guests"
                    class="ovr-form-select"
                    style="padding-right:40px;"
                    required>
                <?php for ( $i = 1; $i <= $max_guests; $i++ ) : ?>
                    <option value="<?php echo esc_attr( $i ); ?>">
                        <?php
                        /* translators: %d: guest count */
                        printf( esc_html( _n( '%d guest', '%d guests', $i, 'ovr-core' ) ), $i );
                        ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <?php if ( ! $is_logged_in ) : ?>
            <!-- Guest contact info (only when not logged in) -->
            <div class="ovr-form-group" style="margin-bottom:12px">
                <label class="ovr-form-label" for="ovr-guest-name-<?php echo esc_attr( $post_id ); ?>">
                    <?php esc_html_e( 'Your Name', 'ovr-core' ); ?>
                </label>
                <input type="text"
                       id="ovr-guest-name-<?php echo esc_attr( $post_id ); ?>"
                       name="guest_name"
                       class="ovr-form-input"
                       required>
            </div>
            <div class="ovr-form-group" style="margin-bottom:12px">
                <label class="ovr-form-label" for="ovr-guest-email-<?php echo esc_attr( $post_id ); ?>">
                    <?php esc_html_e( 'Email Address', 'ovr-core' ); ?>
                </label>
                <input type="email"
                       id="ovr-guest-email-<?php echo esc_attr( $post_id ); ?>"
                       name="guest_email"
                       class="ovr-form-input"
                       autocomplete="email"
                       required>
            </div>
        <?php else : ?>
            <input type="hidden" name="guest_name"  value="<?php echo esc_attr( $current_user->display_name ); ?>">
            <input type="hidden" name="guest_email" value="<?php echo esc_attr( $current_user->user_email ); ?>">
        <?php endif; ?>

        <!-- Phone — required for every inquiry (Phase 23) -->
        <div class="ovr-form-group" style="margin-bottom:12px">
            <label class="ovr-form-label" for="ovr-guest-phone-<?php echo esc_attr( $post_id ); ?>">
                <?php esc_html_e( 'Phone Number', 'ovr-core' ); ?>
            </label>
            <input type="tel"
                   id="ovr-guest-phone-<?php echo esc_attr( $post_id ); ?>"
                   name="guest_phone"
                   class="ovr-form-input"
                   autocomplete="tel"
                   <?php if ( $is_logged_in && ! empty( $current_user ) ) : $u_phone = (string) get_user_meta( $current_user->ID, 'ovr_phone', true ); ?>value="<?php echo esc_attr( $u_phone ); ?>"<?php endif; ?>
                   required>
        </div>

        <!-- Message — required for every inquiry (Phase 23) -->
        <div class="ovr-form-group" style="margin-bottom:16px">
            <label class="ovr-form-label" for="ovr-message-<?php echo esc_attr( $post_id ); ?>">
                <?php esc_html_e( 'Message to Host', 'ovr-core' ); ?>
            </label>
            <textarea id="ovr-message-<?php echo esc_attr( $post_id ); ?>"
                      name="message"
                      class="ovr-form-textarea"
                      rows="3"
                      placeholder="<?php esc_attr_e( 'Tell us about your trip…', 'ovr-core' ); ?>"
                      required></textarea>
        </div>

        <!-- Submit -->
        <button type="submit" class="ovr-btn ovr-btn-secondary ovr-btn-full ovr-btn-lg">
            <?php
                if ( isset( $meta['booking_mode'] ) && 'direct' === $meta['booking_mode'] ) {
                    esc_html_e( 'Book Now', 'ovr-core' );
                } else {
                    esc_html_e( 'Request to Book', 'ovr-core' );
                }
            ?>
        </button>

        <p style="text-align:center;margin-top:12px;font-size:13px;color:var(--ovr-on-surface-variant)">
            <?php
                if ( isset( $meta['booking_mode'] ) && 'direct' === $meta['booking_mode'] ) {
                    esc_html_e( 'You will be redirected to payment.', 'ovr-core' );
                } else {
                    esc_html_e( 'You won\'t be charged yet.', 'ovr-core' );
                }
            ?>
        </p>

        <!-- Inline response slot for AJAX -->
        <div class="ovr-inquiry-response" data-ovr-inquiry-response role="status" aria-live="polite"></div>
    </form>
</aside>
