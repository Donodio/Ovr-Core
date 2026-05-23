<?php
/**
 * Change Password tab.
 *
 * @package OVR
 * @var string $password_status One of '', 'success', 'mismatch', 'weak', 'wrong_current', 'nonce_failed'
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$password_status = $password_status ?? '';
?>
<section class="ovr-card" style="padding:24px;max-width:560px">

    <header style="margin-bottom:20px">
        <h2 style="font-size:20px;font-weight:600;margin:0 0 4px">
            <?php esc_html_e( 'Change Password', 'ovr-core' ); ?>
        </h2>
        <p style="margin:0;font-size:13px;color:var(--ovr-on-surface-variant)">
            <?php esc_html_e( 'Use a strong password — at least 8 characters with letters and numbers.', 'ovr-core' ); ?>
        </p>
    </header>

    <?php
    $alerts = [
        'success'        => [ 'class' => 'ovr-alert-success', 'icon' => 'check_circle', 'text' => __( 'Password updated.', 'ovr-core' ) ],
        'mismatch'       => [ 'class' => 'ovr-alert-error',   'icon' => 'error',        'text' => __( 'New passwords don\'t match.', 'ovr-core' ) ],
        'weak'           => [ 'class' => 'ovr-alert-error',   'icon' => 'error',        'text' => __( 'Password must be at least 8 characters.', 'ovr-core' ) ],
        'wrong_current'  => [ 'class' => 'ovr-alert-error',   'icon' => 'error',        'text' => __( 'Your current password is incorrect.', 'ovr-core' ) ],
        'nonce_failed'   => [ 'class' => 'ovr-alert-error',   'icon' => 'error',        'text' => __( 'Security check failed. Please try again.', 'ovr-core' ) ],
    ];
    if ( ! empty( $alerts[ $password_status ] ) ) :
        $a = $alerts[ $password_status ];
    ?>
        <div class="ovr-alert <?php echo esc_attr( $a['class'] ); ?>" style="margin-bottom:20px">
            <span class="material-symbols-outlined"><?php echo esc_html( $a['icon'] ); ?></span>
            <span><?php echo esc_html( $a['text'] ); ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ovr_change_password">
        <?php wp_nonce_field( 'ovr_password_action', 'ovr_password_nonce' ); ?>

        <div style="display:flex;flex-direction:column;gap:16px;margin-bottom:20px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                    <?php esc_html_e( 'Current Password', 'ovr-core' ); ?>
                </label>
                <input type="password" name="current_password" autocomplete="current-password" class="ovr-form-input" required>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                    <?php esc_html_e( 'New Password', 'ovr-core' ); ?>
                </label>
                <input type="password" name="new_password" autocomplete="new-password" minlength="8" class="ovr-form-input" required>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                    <?php esc_html_e( 'Confirm New Password', 'ovr-core' ); ?>
                </label>
                <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" class="ovr-form-input" required>
            </div>
        </div>

        <button type="submit" class="ovr-btn ovr-btn-primary ovr-btn-pill">
            <?php esc_html_e( 'Update Password', 'ovr-core' ); ?>
        </button>
    </form>
</section>
