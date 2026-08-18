<?php
/**
 * Login Template.
 *
 * @package OVR
 * @var array  $errors
 * @var string $login_url
 * @var string $register_url
 * @var string $forgot_url
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$logged_out = isset( $_GET['logged_out'] ) && '1' === $_GET['logged_out'];
?>
<div class="ovr-wrap ovr-auth-page">

    <!-- Decorative Blobs -->
    <div class="ovr-auth-decor">
        <div class="ovr-auth-blob ovr-auth-blob-1"></div>
        <div class="ovr-auth-blob ovr-auth-blob-2"></div>
    </div>

    <div class="ovr-auth-card ovr-auth-glass">
        <div class="ovr-accent-line"></div>
        <div class="ovr-auth-card-body">

            <!-- Header -->
            <div class="ovr-auth-header">
                <span class="ovr-auth-eyebrow">
                    <span class="material-symbols-outlined">apartment</span>
                    <?php esc_html_e( 'Property Owner Login', 'ovr-core' ); ?>
                </span>
                <div class="ovr-auth-brand"><?php esc_html_e( 'Our Villages Rental', 'ovr-core' ); ?></div>
                <p class="ovr-auth-subtitle"><?php esc_html_e( 'Sign in to manage your listings and inquiries', 'ovr-core' ); ?></p>
            </div>

            <!-- Success message after logout -->
            <?php if ( $logged_out ) : ?>
                <div class="ovr-alert ovr-alert-info">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span><?php esc_html_e( 'You have been successfully logged out.', 'ovr-core' ); ?></span>
                </div>
            <?php endif; ?>

            <!-- Email verification result -->
            <?php if ( isset( $_GET['verified'] ) && '1' === $_GET['verified'] ) : ?>
                <div class="ovr-alert ovr-alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span><?php esc_html_e( 'Your email has been verified. You can now sign in.', 'ovr-core' ); ?></span>
                </div>
            <?php elseif ( isset( $_GET['verified'] ) && '0' === $_GET['verified'] ) : ?>
                <div class="ovr-alert ovr-alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <span><?php esc_html_e( 'We could not verify your email. The link may have expired — please try registering again.', 'ovr-core' ); ?></span>
                </div>
            <?php endif; ?>

            <!-- Error messages -->
            <?php if ( ! empty( $errors ) ) : ?>
                <div class="ovr-alert ovr-alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <div>
                        <?php foreach ( $errors as $error ) : ?>
                            <p style="margin:0 0 4px"><?php echo esc_html( $error ); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="post" action="<?php echo esc_url( $login_url ); ?>" id="ovr-login-form">
                <?php wp_nonce_field( 'ovr_login_action', 'ovr_login_nonce' ); ?>

                <div class="ovr-form-group">
                    <label class="ovr-form-label" for="ovr-email"><?php esc_html_e( 'Email Address', 'ovr-core' ); ?></label>
                    <div class="ovr-input-icon-wrap">
                        <span class="ovr-input-icon material-symbols-outlined">mail</span>
                        <input
                            type="email"
                            id="ovr-email"
                            name="ovr_email"
                            class="ovr-form-input"
                            placeholder="<?php esc_attr_e( 'you@example.com', 'ovr-core' ); ?>"
                            required
                            autocomplete="email"
                        >
                    </div>
                </div>

                <div class="ovr-form-group">
                    <label class="ovr-form-label" for="ovr-password"><?php esc_html_e( 'Password', 'ovr-core' ); ?></label>
                    <div class="ovr-input-icon-wrap">
                        <span class="ovr-input-icon material-symbols-outlined">lock</span>
                        <input
                            type="password"
                            id="ovr-password"
                            name="ovr_password"
                            class="ovr-form-input"
                            placeholder="<?php esc_attr_e( 'Enter your password', 'ovr-core' ); ?>"
                            required
                            autocomplete="current-password"
                            style="padding-right:48px"
                        >
                        <button type="button" class="ovr-password-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'ovr-core' ); ?>">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="ovr-auth-options">
                    <label class="ovr-auth-remember">
                        <input type="checkbox" name="ovr_remember" value="1" class="ovr-form-checkbox">
                        <?php esc_html_e( 'Remember me', 'ovr-core' ); ?>
                    </label>
                    <a href="<?php echo esc_url( $forgot_url ); ?>" class="ovr-auth-forgot">
                        <?php esc_html_e( 'Forgot password?', 'ovr-core' ); ?>
                    </a>
                </div>

                <?php if ( ! empty( $enable_2fa ) ) : ?>
                <div class="ovr-form-group">
                    <label class="ovr-form-label" for="ovr-2fa-code"><?php esc_html_e( 'One-time code (if emailed)', 'ovr-core' ); ?></label>
                    <div class="ovr-input-icon-wrap">
                        <span class="ovr-input-icon material-symbols-outlined">pin</span>
                        <input
                            type="text"
                            id="ovr-2fa-code"
                            name="ovr_2fa_code"
                            class="ovr-form-input"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            value=""
                            size="20"
                            placeholder="<?php esc_attr_e( '6-digit code', 'ovr-core' ); ?>"
                        >
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" name="ovr_login_submit" class="ovr-btn ovr-btn-primary ovr-btn-full ovr-btn-lg">
                    <span class="material-symbols-outlined">login</span>
                    <?php esc_html_e( 'Sign In', 'ovr-core' ); ?>
                </button>
            </form>

            <!-- Register Link -->
            <div class="ovr-auth-divider">
                <?php esc_html_e( "Don't have an account?", 'ovr-core' ); ?>
                <a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Create one now', 'ovr-core' ); ?></a>
            </div>

        </div><!-- .ovr-auth-card-body -->

        <!-- Trust Bar -->
        <div class="ovr-auth-footer-bar">
            <span class="ovr-auth-trust-icon material-symbols-outlined" title="<?php esc_attr_e( 'SSL Secured', 'ovr-core' ); ?>">lock</span>
            <span class="ovr-auth-trust-icon material-symbols-outlined" title="<?php esc_attr_e( 'Verified', 'ovr-core' ); ?>">verified_user</span>
            <span class="ovr-auth-trust-icon material-symbols-outlined" title="<?php esc_attr_e( 'Trusted', 'ovr-core' ); ?>">shield</span>
        </div>

    </div><!-- .ovr-auth-card -->

</div><!-- .ovr-auth-page -->
