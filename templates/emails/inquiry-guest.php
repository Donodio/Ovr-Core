<?php
/**
 * Inquiry confirmation email to the guest.
 *
 * @package OVR
 * @var string   $guest_name
 * @var \WP_Post $property
 * @var string   $property_url
 * @var array    $inquiry
 * @var string   $site_name
 * @var string   $site_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name = $site_name ?? get_bloginfo( 'name' );
$site_url  = $site_url  ?? home_url( '/' );

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#181c1c">
    <?php
    /* translators: %s: guest name */
    printf( esc_html__( 'Thanks, %s ✓', 'ovr-core' ), esc_html( $guest_name ) );
    ?>
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#3f4948">
    <?php
    /* translators: %s: property title */
    printf( esc_html__( 'Your inquiry about %s is on its way to the host. Most landlords reply within a day — we\'ll let you know as soon as they do.', 'ovr-core' ),
        '<strong>' . esc_html( $property->post_title ) . '</strong>'
    );
    ?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border:1px solid #ebeeee;border-radius:10px;background:#f7faf9">
    <tr><td style="padding:16px">
        <p style="margin:0 0 6px;font-size:12px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:#6f7979">
            <?php esc_html_e( 'Your message', 'ovr-core' ); ?>
        </p>
        <p style="margin:0;font-size:14px;color:#3f4948;line-height:1.6;white-space:pre-wrap">
            <?php echo esc_html( $inquiry['message'] ); ?>
        </p>
    </td></tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px">
    <tr><td style="border-radius:8px;background:#006666">
        <a href="<?php echo esc_url( $property_url ); ?>"
           style="display:inline-block;padding:12px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px">
            <?php esc_html_e( 'View property again', 'ovr-core' ); ?>
        </a>
    </td></tr>
</table>

<p style="margin:0;font-size:13px;color:#6f7979">
    <?php esc_html_e( 'You don\'t need to do anything else right now. The host will reach out by email shortly.', 'ovr-core' ); ?>
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
