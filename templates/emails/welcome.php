<?php
/**
 * Welcome email body.
 *
 * @package OVR
 * @var \WP_User $user
 * @var bool     $is_landlord
 * @var string   $login_url
 * @var string   $dashboard_url
 * @var string   $site_name
 * @var string   $site_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#181c1c">
    <?php
    /* translators: %s: user first name */
    printf( esc_html__( 'Welcome, %s 👋', 'ovr-core' ), esc_html( $user->first_name ?: $user->display_name ) );
    ?>
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#3f4948">
    <?php
    /* translators: %s: site name */
    printf( esc_html__( 'Your account at %s is ready to go.', 'ovr-core' ), esc_html( $site_name ) );
    ?>
</p>

<?php if ( $is_landlord ) : ?>
    <p style="margin:0 0 16px;font-size:15px;color:#3f4948">
        <?php esc_html_e( 'You signed up as a landlord — you can list your first property whenever you\'re ready. Here\'s what to do next:', 'ovr-core' ); ?>
    </p>
    <ul style="margin:0 0 24px;padding-left:20px;color:#3f4948;font-size:14px;line-height:1.8">
        <li><?php esc_html_e( 'Complete your profile so guests trust you faster', 'ovr-core' ); ?></li>
        <li><?php esc_html_e( 'Choose a subscription plan that fits your portfolio', 'ovr-core' ); ?></li>
        <li><?php esc_html_e( 'List your first property with photos, pricing, and availability', 'ovr-core' ); ?></li>
    </ul>
<?php else : ?>
    <p style="margin:0 0 24px;font-size:15px;color:#3f4948">
        <?php esc_html_e( 'Browse handpicked rentals across our villages. When you find one you love, send the host an inquiry — they\'ll be in touch within 24 hours.', 'ovr-core' ); ?>
    </p>
<?php endif; ?>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px">
    <tr><td style="border-radius:8px;background:#006666">
        <a href="<?php echo esc_url( $is_landlord ? $dashboard_url : $site_url ); ?>"
           style="display:inline-block;padding:12px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px">
            <?php echo $is_landlord
                ? esc_html__( 'Go to dashboard', 'ovr-core' )
                : esc_html__( 'Browse properties', 'ovr-core' ); ?>
        </a>
    </td></tr>
</table>

<p style="margin:24px 0 0;font-size:13px;color:#6f7979">
    <?php
    /* translators: %s: login URL */
    printf( esc_html__( 'You can also sign in any time at %s.', 'ovr-core' ), '<a href="' . esc_url( $login_url ) . '" style="color:#006666">' . esc_html( $login_url ) . '</a>' );
    ?>
</p>
<?php
$content = ob_get_clean();

// phpcs:ignore — included variables consumed by layout.
include __DIR__ . '/_layout.php';
