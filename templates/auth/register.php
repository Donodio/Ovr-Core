<?php
/**
 * Registration Template.
 *
 * @package OVR
 * @var array  $errors
 * @var array  $old_data
 * @var string $login_url
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ovr-wrap ovr-auth-page">

    <div class="ovr-auth-decor">
        <div class="ovr-auth-blob ovr-auth-blob-1"></div>
        <div class="ovr-auth-blob ovr-auth-blob-2"></div>
    </div>

    <div class="ovr-auth-card ovr-auth-card-wide ovr-auth-glass">
        <div class="ovr-accent-line"></div>
        <div class="ovr-auth-card-body">

            <div class="ovr-auth-header">
                <div class="ovr-auth-brand"><?php esc_html_e( 'Our Villages Rentals', 'ovr-core' ); ?></div>
                <p class="ovr-auth-subtitle"><?php esc_html_e( 'Create your account and start listing', 'ovr-core' ); ?></p>
            </div>

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

            <form method="post" id="ovr-register-form">
                <?php wp_nonce_field( 'ovr_register_action', 'ovr_register_nonce' ); ?>

                <div class="ovr-form-row">
                    <div class="ovr-form-group">
                        <label class="ovr-form-label" for="ovr-first-name"><?php esc_html_e( 'First Name', 'ovr-core' ); ?></label>
                        <input type="text" id="ovr-first-name" name="ovr_first_name" class="ovr-form-input"
                               value="<?php echo esc_attr( $old_data['first_name'] ?? '' ); ?>" required>
                    </div>
                    <div class="ovr-form-group">
                        <label class="ovr-form-label" for="ovr-last-name"><?php esc_html_e( 'Last Name', 'ovr-core' ); ?></label>
                        <input type="text" id="ovr-last-name" name="ovr_last_name" class="ovr-form-input"
                               value="<?php echo esc_attr( $old_data['last_name'] ?? '' ); ?>" required>
                    </div>
                </div>

                <div class="ovr-form-row">
                    <div class="ovr-form-group">
                        <label class="ovr-form-label" for="ovr-reg-email"><?php esc_html_e( 'Email Address', 'ovr-core' ); ?></label>
                        <div class="ovr-input-icon-wrap">
                            <span class="ovr-input-icon material-symbols-outlined">mail</span>
                            <input type="email" id="ovr-reg-email" name="ovr_email" class="ovr-form-input"
                                   value="<?php echo esc_attr( $old_data['email'] ?? '' ); ?>" required autocomplete="email">
                        </div>
                    </div>
                    <div class="ovr-form-group">
                        <label class="ovr-form-label" for="ovr-phone"><?php esc_html_e( 'Phone Number', 'ovr-core' ); ?></label>
                        <div class="ovr-input-icon-wrap">
                            <span class="ovr-input-icon material-symbols-outlined">phone</span>
                            <input type="tel" id="ovr-phone" name="ovr_phone" class="ovr-form-input"
                                   value="<?php echo esc_attr( $old_data['phone'] ?? '' ); ?>">
                        </div>
                    </div>
                </div>

                <div class="ovr-form-row">
                    <div class="ovr-form-group">
                        <label class="ovr-form-label" for="ovr-reg-password"><?php esc_html_e( 'Password', 'ovr-core' ); ?></label>
                        <div class="ovr-input-icon-wrap">
                            <span class="ovr-input-icon material-symbols-outlined">lock</span>
                            <input type="password" id="ovr-reg-password" name="ovr_password" class="ovr-form-input"
                                   required minlength="8" autocomplete="new-password" style="padding-right:48px">
                            <button type="button" class="ovr-password-toggle" aria-label="<?php esc_attr_e( 'Toggle', 'ovr-core' ); ?>">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="ovr-form-group">
                        <label class="ovr-form-label" for="ovr-confirm"><?php esc_html_e( 'Confirm Password', 'ovr-core' ); ?></label>
                        <div class="ovr-input-icon-wrap">
                            <span class="ovr-input-icon material-symbols-outlined">lock</span>
                            <input type="password" id="ovr-confirm" name="ovr_confirm_password" class="ovr-form-input"
                                   required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="ovr-form-group">
                    <label class="ovr-form-checkbox-group">
                        <input type="checkbox" name="ovr_is_landlord" value="1" class="ovr-form-checkbox"
                            <?php checked( ! empty( $old_data['is_landlord'] ) ); ?>>
                        <span><?php esc_html_e( 'I want to list properties as a Landlord / Property Manager', 'ovr-core' ); ?></span>
                    </label>
                </div>

                <div class="ovr-form-group">
                    <label class="ovr-form-checkbox-group">
                        <input type="checkbox" name="ovr_terms" value="1" class="ovr-form-checkbox" required>
                        <span><?php
                            printf(
                                esc_html__( 'I agree to the %1$sTerms of Service%2$s and %3$sPrivacy Policy%4$s', 'ovr-core' ),
                                '<a href="#" target="_blank">', '</a>',
                                '<a href="#" target="_blank">', '</a>'
                            );
                        ?></span>
                    </label>
                </div>

                <button type="submit" name="ovr_register_submit" class="ovr-btn ovr-btn-primary ovr-btn-full ovr-btn-lg">
                    <span class="material-symbols-outlined">person_add</span>
                    <?php esc_html_e( 'Create Account', 'ovr-core' ); ?>
                </button>
            </form>

            <div class="ovr-auth-divider">
                <?php esc_html_e( 'Already have an account?', 'ovr-core' ); ?>
                <a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign in', 'ovr-core' ); ?></a>
            </div>

        </div>

        <div class="ovr-auth-footer-bar">
            <span class="ovr-auth-trust-icon material-symbols-outlined">lock</span>
            <span class="ovr-auth-trust-icon material-symbols-outlined">verified_user</span>
            <span class="ovr-auth-trust-icon material-symbols-outlined">shield</span>
        </div>
    </div>
</div>
