<?php
/**
 * Anonymous-user dashboard placeholder.
 *
 * @package OVR
 * @var string $login_url
 * @var string $register_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ovr-wrap">
<div class="ovr-container ovr-section" style="padding-top:64px">
    <div class="ovr-card" style="max-width:520px;margin:0 auto;padding:48px 32px;text-align:center">
        <span class="material-symbols-outlined" style="font-size:64px;color:var(--ovr-primary);margin-bottom:16px">lock</span>
        <h1 class="ovr-h3" style="margin:0 0 8px"><?php esc_html_e( 'Sign in to your dashboard', 'ovr-core' ); ?></h1>
        <p class="ovr-body-md" style="color:var(--ovr-on-surface-variant);margin-bottom:24px">
            <?php esc_html_e( 'Manage your listings, inquiries, subscription, and profile here once you\'re signed in.', 'ovr-core' ); ?>
        </p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="<?php echo esc_url( $login_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-pill">
                <?php esc_html_e( 'Sign In', 'ovr-core' ); ?>
            </a>
            <a href="<?php echo esc_url( $register_url ); ?>" class="ovr-btn ovr-btn-outline ovr-btn-pill">
                <?php esc_html_e( 'Create Account', 'ovr-core' ); ?>
            </a>
        </div>
    </div>
</div>
</div>
