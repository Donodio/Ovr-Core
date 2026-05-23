<?php
/**
 * Pricing Plans Template.
 *
 * @var array $plans
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';

// Display options (passed from shortcode → PricingDisplay::render).
$columns       = isset( $columns ) ? (int) $columns : 0;
$layout        = isset( $layout )  ? (string) $layout : 'cards';
$show_compare  = isset( $show_compare ) ? (bool) $show_compare : true;
$show_promo    = isset( $show_promo )   ? (bool) $show_promo   : true;

$grid_class = 'ovr-pricing-grid ovr-pricing-grid--' . sanitize_html_class( $layout );

// Build grid-template-columns inline if a column count was passed.
$grid_inline = '';
if ( 'cards' === $layout && $columns >= 1 && $columns <= 5 ) {
    $grid_inline = 'grid-template-columns:repeat(' . $columns . ',1fr)';
} elseif ( 'list' === $layout ) {
    $grid_inline = 'grid-template-columns:1fr;gap:14px';
} elseif ( 'compact' === $layout ) {
    $grid_inline = 'grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px';
}
?>
<div class="ovr-wrap">
    <div class="ovr-container ovr-section">

        <!-- Header -->
        <div style="text-align:center;max-width:640px;margin:0 auto 48px">
            <p class="ovr-label-caps" style="color:var(--ovr-primary);margin-bottom:8px"><?php esc_html_e( 'PRICING', 'ovr-core' ); ?></p>
            <h1 class="ovr-h1" style="margin-bottom:16px"><?php esc_html_e( 'Choose Your Plan', 'ovr-core' ); ?></h1>
            <p class="ovr-body-lg" style="color:var(--ovr-on-surface-variant)">
                <?php esc_html_e( 'Start free and scale as your portfolio grows. All plans include core features.', 'ovr-core' ); ?>
            </p>
        </div>

        <!-- Pricing Cards -->
        <div class="<?php echo esc_attr( $grid_class ); ?>"
             style="<?php echo esc_attr( $grid_inline ); ?>">
            <?php foreach ( $plans as $plan ) :
                if ( empty( $plan['is_active'] ) ) continue;
                $is_popular = ! empty( $plan['is_popular'] );
                $is_free    = 0 == $plan['price'];
            ?>
                <div class="ovr-pricing-card<?php echo $is_popular ? ' is-popular' : ''; ?>">
                    <?php if ( $is_popular ) : ?>
                        <div class="ovr-pricing-popular-badge"><?php esc_html_e( 'Most Popular', 'ovr-core' ); ?></div>
                    <?php endif; ?>

                    <div class="ovr-pricing-name"><?php echo esc_html( $plan['name'] ); ?></div>

                    <div class="ovr-pricing-price">
                        <?php if ( $is_free ) : ?>
                            <span class="ovr-pricing-amount"><?php esc_html_e( 'Free', 'ovr-core' ); ?></span>
                        <?php else : ?>
                            <span class="ovr-pricing-amount"><?php echo esc_html( $symbol . number_format( $plan['price'], 0 ) ); ?></span>
                            <span class="ovr-pricing-period">/<?php echo esc_html( $plan['period'] ); ?></span>
                        <?php endif; ?>
                    </div>

                    <p class="ovr-pricing-desc"><?php echo esc_html( $plan['description'] ); ?></p>

                    <ul class="ovr-pricing-features">
                        <?php foreach ( $plan['features'] as $feature ) : ?>
                            <li>
                                <span class="material-symbols-outlined">check_circle</span>
                                <?php echo esc_html( $feature ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ( is_user_logged_in() ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0">
                            <input type="hidden" name="action" value="ovr_start_checkout">
                            <input type="hidden" name="plan" value="<?php echo esc_attr( $plan['slug'] ); ?>">
                            <?php wp_nonce_field( 'ovr_checkout_action', 'ovr_checkout_nonce' ); ?>

                            <?php if ( ! $is_free ) : ?>
                                <select name="gateway"
                                        class="ovr-form-select"
                                        style="width:100%;padding:8px 12px;font-size:13px;margin-bottom:8px;border-radius:var(--ovr-radius-md);border:1px solid var(--ovr-outline-variant)">
                                    <option value="stripe"><?php esc_html_e( 'Pay with Stripe', 'ovr-core' ); ?></option>
                                    <option value="paypal"><?php esc_html_e( 'Pay with PayPal', 'ovr-core' ); ?></option>
                                    <option value="authorize_net"><?php esc_html_e( 'Pay with Authorize.net', 'ovr-core' ); ?></option>
                                    <option value="wallet"><?php esc_html_e( 'Pay from Wallet', 'ovr-core' ); ?></option>
                                </select>
                            <?php endif; ?>

                            <button type="submit"
                                    class="ovr-btn <?php echo $is_popular ? 'ovr-btn-secondary' : 'ovr-btn-outline'; ?> ovr-btn-full ovr-btn-pill"
                                    data-plan="<?php echo esc_attr( $plan['slug'] ); ?>">
                                <?php echo $is_free
                                    ? esc_html__( 'Activate Free Plan', 'ovr-core' )
                                    : esc_html__( 'Select Plan', 'ovr-core' ); ?>
                            </button>
                        </form>
                    <?php else : ?>
                        <a href="<?php echo esc_url( \OVR\Core\Pages::get_page_url( 'ovr_page_register' ) ); ?>"
                           class="ovr-btn <?php echo $is_popular ? 'ovr-btn-secondary' : 'ovr-btn-outline'; ?> ovr-btn-full ovr-btn-pill">
                            <?php esc_html_e( 'Get Started', 'ovr-core' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ( $show_promo ) : ?>
        <!-- Promo Code -->
        <div style="text-align:center;margin-top:48px">
            <div style="display:inline-flex;align-items:center;gap:12px;background:var(--ovr-surface-container-lowest);border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-full);padding:4px 4px 4px 20px;box-shadow:var(--ovr-shadow-sm)">
                <span class="material-symbols-outlined" style="color:var(--ovr-tertiary-container);font-size:20px">confirmation_number</span>
                <input type="text" id="ovr-promo-input" placeholder="<?php esc_attr_e( 'Enter promo code', 'ovr-core' ); ?>"
                       style="border:none;background:transparent;font-family:var(--ovr-font);font-size:16px;outline:none;width:180px;color:var(--ovr-on-surface)">
                <button id="ovr-promo-apply" class="ovr-btn ovr-btn-primary ovr-btn-pill" style="padding:10px 20px">
                    <?php esc_html_e( 'Apply', 'ovr-core' ); ?>
                </button>
            </div>
            <div id="ovr-promo-msg" style="margin-top:12px;font-size:14px" aria-live="polite"></div>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
    .ovr-pricing-grid--list .ovr-pricing-card{display:flex;align-items:center;gap:24px;padding:20px 24px}
    .ovr-pricing-grid--list .ovr-pricing-card > *{margin:0}
    .ovr-pricing-grid--list .ovr-pricing-popular-badge{position:static;flex-shrink:0}
    .ovr-pricing-grid--compact .ovr-pricing-card{padding:18px;font-size:13px}
    .ovr-pricing-grid--compact .ovr-pricing-card h3{font-size:16px}
    .ovr-pricing-grid--compact .ovr-pricing-card .ovr-pricing-price{font-size:24px}
</style>
