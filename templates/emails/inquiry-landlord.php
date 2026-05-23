<?php
/**
 * Inquiry-received email to the landlord.
 *
 * @package OVR
 * @var \WP_User $landlord
 * @var \WP_Post $property
 * @var string   $property_url
 * @var array    $inquiry        Row from wp_ovr_inquiries.
 * @var string   $dashboard_url
 * @var string   $site_name
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name = $site_name ?? get_bloginfo( 'name' );
$site_url  = home_url( '/' );

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#181c1c">
    <?php esc_html_e( 'You have a new inquiry 📬', 'ovr-core' ); ?>
</h1>
<p style="margin:0 0 20px;font-size:15px;color:#3f4948">
    <?php
    /* translators: 1: guest name, 2: property title */
    printf( esc_html__( '%1$s reached out about %2$s.', 'ovr-core' ),
        '<strong>' . esc_html( $inquiry['guest_name'] ) . '</strong>',
        '<a href="' . esc_url( $property_url ) . '" style="color:#006666;text-decoration:none">' . esc_html( $property->post_title ) . '</a>'
    );
    ?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border:1px solid #ebeeee;border-radius:10px">
    <tr><td style="padding:16px">
        <p style="margin:0 0 8px;font-size:12px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:#6f7979">
            <?php esc_html_e( 'Trip Dates', 'ovr-core' ); ?>
        </p>
        <p style="margin:0 0 16px;font-size:15px;color:#181c1c;font-weight:500">
            <?php
            $checkin  = ! empty( $inquiry['checkin_date'] )  ? mysql2date( get_option( 'date_format' ), $inquiry['checkin_date'] )  : '—';
            $checkout = ! empty( $inquiry['checkout_date'] ) ? mysql2date( get_option( 'date_format' ), $inquiry['checkout_date'] ) : '—';
            echo esc_html( $checkin . ' → ' . $checkout );
            ?>
            <?php if ( ! empty( $inquiry['guests'] ) ) : ?>
                · <?php
                /* translators: %d: guest count */
                printf( esc_html( _n( '%d guest', '%d guests', (int) $inquiry['guests'], 'ovr-core' ) ), (int) $inquiry['guests'] );
                ?>
            <?php endif; ?>
        </p>

        <p style="margin:0 0 8px;font-size:12px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:#6f7979">
            <?php esc_html_e( 'Message', 'ovr-core' ); ?>
        </p>
        <p style="margin:0 0 16px;font-size:14px;color:#3f4948;line-height:1.6;white-space:pre-wrap">
            <?php echo esc_html( $inquiry['message'] ); ?>
        </p>

        <p style="margin:0;font-size:13px;color:#3f4948">
            <strong><?php echo esc_html( $inquiry['guest_name'] ); ?></strong>
            · <a href="mailto:<?php echo esc_attr( $inquiry['guest_email'] ); ?>" style="color:#006666"><?php echo esc_html( $inquiry['guest_email'] ); ?></a>
            <?php if ( ! empty( $inquiry['guest_phone'] ) ) : ?>
                · <a href="tel:<?php echo esc_attr( $inquiry['guest_phone'] ); ?>" style="color:#006666"><?php echo esc_html( $inquiry['guest_phone'] ); ?></a>
            <?php endif; ?>
        </p>
    </td></tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0">
    <tr><td style="border-radius:8px;background:#006666">
        <a href="<?php echo esc_url( $dashboard_url ); ?>"
           style="display:inline-block;padding:12px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px">
            <?php esc_html_e( 'Reply from dashboard', 'ovr-core' ); ?>
        </a>
    </td></tr>
</table>

<p style="margin:24px 0 0;font-size:13px;color:#6f7979">
    <?php esc_html_e( 'Tip: replying within an hour roughly doubles your booking conversion rate.', 'ovr-core' ); ?>
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/_layout.php';
