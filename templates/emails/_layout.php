<?php
/**
 * Shared email layout — wraps any per-email template with the OVR header,
 * footer, and base styles. Templates pass their `$content` HTML in.
 *
 * @package OVR
 * @var string $content    Inner HTML.
 * @var string $site_name  Site name for header/footer.
 * @var string $site_url   Site URL.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name = $site_name ?? get_bloginfo( 'name' );
$site_url  = $site_url  ?? home_url( '/' );
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f1f4f3;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#181c1c;line-height:1.5">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f4f3;padding:32px 16px">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.06)">

                <tr><td style="background:linear-gradient(135deg,#006666,#004c4c);padding:24px 32px;color:#fff">
                    <a href="<?php echo esc_url( $site_url ); ?>" style="color:#fff;text-decoration:none;font-size:20px;font-weight:700;letter-spacing:-0.01em">
                        <?php echo esc_html( $site_name ); ?>
                    </a>
                </td></tr>

                <tr><td style="padding:32px">
                    <?php echo $content; ?>
                </td></tr>

                <tr><td style="background:#f7faf9;padding:20px 32px;border-top:1px solid #ebeeee;font-size:12px;color:#6f7979;text-align:center">
                    <?php
                    /* translators: 1: site name, 2: current year */
                    printf( esc_html__( '© %2$s %1$s. All rights reserved.', 'ovr-core' ), esc_html( $site_name ), esc_html( gmdate( 'Y' ) ) );
                    ?>
                    <br>
                    <a href="<?php echo esc_url( $site_url ); ?>" style="color:#006666;text-decoration:none">
                        <?php echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) ); ?>
                    </a>
                </td></tr>

            </table>
        </td></tr>
    </table>
</body>
</html>
