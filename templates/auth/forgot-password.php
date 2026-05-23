<?php
/**
 * Forgot Password Template.
 *
 * @var array  $errors
 * @var bool   $success
 * @var string $email
 * @var string $login_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ovr-wrap ovr-auth-page">
    <div class="ovr-auth-decor">
        <div class="ovr-auth-blob ovr-auth-blob-1"></div>
        <div class="ovr-auth-blob ovr-auth-blob-2"></div>
    </div>

    <div class="ovr-auth-card ovr-auth-glass">
        <div class="ovr-accent-line"></div>
        <div class="ovr-auth-card-body">

            <div class="ovr-auth-header">
                <div class="ovr-auth-brand"><?php esc_html_e( 'Our Villages Rentals', 'ovr-core' ); ?></div>
            </div>

            <?php if ( $success ) : ?>
                <div class="ovr-forgot-success">
                    <div class="ovr-forgot-success-icon">
                        <span class="material-symbols-outlined">mark_email_read</span>
                    </div>
                    <h3 class="ovr-h3" style="margin-bottom:12px"><?php esc_html_e( 'Check Your Email', 'ovr-core' ); ?></h3>
                    <p class="ovr-body-md" style="color:var(--ovr-on-surface-variant);margin-bottom:24px">
                        <?php printf( esc_html__( 'If an account exists for %s, you will receive a password reset link shortly.', 'ovr-core' ), '<strong>' . esc_html( $email ) . '</strong>' ); ?>
                    </p>
                    <a href="<?php echo esc_url( $login_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-full">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <?php esc_html_e( 'Back to Sign In', 'ovr-core' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <p class="ovr-auth-subtitle" style="text-align:center;margin-bottom:24px">
                    <?php esc_html_e( 'Enter your email and we\'ll send you a reset link.', 'ovr-core' ); ?>
                </p>

                <?php if ( ! empty( $errors ) ) : ?>
                    <div class="ovr-alert ovr-alert-error">
                        <span class="material-symbols-outlined">error</span>
                        <div><?php foreach ( $errors as $e ) : ?><p style="margin:0"><?php echo esc_html( $e ); ?></p><?php endforeach; ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" id="ovr-forgot-form">
                    <?php wp_nonce_field( 'ovr_forgot_action', 'ovr_forgot_nonce' ); ?>
                    <div class="ovr-form-group">
                        <label class="ovr-form-label" for="ovr-forgot-email"><?php esc_html_e( 'Email Address', 'ovr-core' ); ?></label>
                        <div class="ovr-input-icon-wrap">
                            <span class="ovr-input-icon material-symbols-outlined">mail</span>
                            <input type="email" id="ovr-forgot-email" name="ovr_email" class="ovr-form-input" required autocomplete="email">
                        </div>
                    </div>
                    <button type="submit" name="ovr_forgot_submit" class="ovr-btn ovr-btn-primary ovr-btn-full ovr-btn-lg">
                        <span class="material-symbols-outlined">send</span>
                        <?php esc_html_e( 'Send Reset Link', 'ovr-core' ); ?>
                    </button>
                </form>

                <div class="ovr-auth-divider">
                    <?php esc_html_e( 'Remember your password?', 'ovr-core' ); ?>
                    <a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign in', 'ovr-core' ); ?></a>
                </div>
            <?php endif; ?>

        </div>
        <div class="ovr-auth-footer-bar">
            <span class="ovr-auth-trust-icon material-symbols-outlined">lock</span>
            <span class="ovr-auth-trust-icon material-symbols-outlined">verified_user</span>
            <span class="ovr-auth-trust-icon material-symbols-outlined">shield</span>
        </div>
    </div>
</div>
