<?php
/**
 * Profile tab — display name, email, phone, password change link.
 *
 * @package OVR
 * @var \WP_User $user
 * @var string   $phone
 * @var bool     $saved
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<section class="ovr-card" style="padding:24px">

    <header style="margin-bottom:24px">
        <h2 style="font-size:20px;font-weight:600;margin:0 0 4px">
            <?php esc_html_e( 'Profile', 'ovr-core' ); ?>
        </h2>
        <p style="margin:0;font-size:13px;color:var(--ovr-on-surface-variant)">
            <?php esc_html_e( 'Update your account details. Email is used for login and notifications.', 'ovr-core' ); ?>
        </p>
    </header>

    <?php if ( $saved ) : ?>
        <div class="ovr-alert ovr-alert-success" style="margin-bottom:20px">
            <span class="material-symbols-outlined">check_circle</span>
            <span><?php esc_html_e( 'Profile updated.', 'ovr-core' ); ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ovr_update_profile">
        <?php wp_nonce_field( 'ovr_profile_action', 'ovr_profile_nonce' ); ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-bottom:24px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                    <?php esc_html_e( 'First Name', 'ovr-core' ); ?>
                </label>
                <input type="text" name="first_name" class="ovr-form-input" value="<?php echo esc_attr( $user->first_name ); ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                    <?php esc_html_e( 'Last Name', 'ovr-core' ); ?>
                </label>
                <input type="text" name="last_name" class="ovr-form-input" value="<?php echo esc_attr( $user->last_name ); ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                    <?php esc_html_e( 'Email', 'ovr-core' ); ?>
                </label>
                <input type="email" name="email" class="ovr-form-input" value="<?php echo esc_attr( $user->user_email ); ?>" required>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                    <?php esc_html_e( 'Phone', 'ovr-core' ); ?>
                </label>
                <input type="tel" name="phone" class="ovr-form-input" value="<?php echo esc_attr( $phone ); ?>">
            </div>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <button type="submit" class="ovr-btn ovr-btn-primary ovr-btn-pill">
                <?php esc_html_e( 'Save Profile', 'ovr-core' ); ?>
            </button>
            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" style="font-size:13px;font-weight:500;color:var(--ovr-primary);text-decoration:none">
                <?php esc_html_e( 'Change password →', 'ovr-core' ); ?>
            </a>
        </div>
    </form>
</section>
