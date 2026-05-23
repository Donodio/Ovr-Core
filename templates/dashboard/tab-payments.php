<?php
/**
 * Payments tab — history of all wp_ovr_payments rows for this user.
 *
 * @package OVR
 * @var array $payments
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$status_color = [
    'completed'  => [ 'bg' => 'var(--ovr-secondary-container)', 'fg' => 'var(--ovr-on-secondary-container)' ],
    'pending'    => [ 'bg' => 'var(--ovr-tertiary-container)',  'fg' => 'var(--ovr-on-tertiary-container)' ],
    'failed'     => [ 'bg' => 'var(--ovr-error-container)',     'fg' => 'var(--ovr-on-error-container)' ],
    'refunded'   => [ 'bg' => 'var(--ovr-surface-container)',   'fg' => 'var(--ovr-on-surface-variant)' ],
];

$gateway_label = [
    'stripe'        => 'Stripe',
    'paypal'        => 'PayPal',
    'authorize_net' => 'Authorize.net',
    'wallet'        => __( 'Wallet', 'ovr-core' ),
];
?>
<section class="ovr-card" style="padding:24px">

    <header style="margin-bottom:20px">
        <h2 style="font-size:20px;font-weight:600;margin:0 0 4px">
            <?php esc_html_e( 'My Payments', 'ovr-core' ); ?>
        </h2>
        <p style="margin:0;font-size:13px;color:var(--ovr-on-surface-variant)">
            <?php esc_html_e( 'Subscription charges, wallet topups, and other transactions.', 'ovr-core' ); ?>
        </p>
    </header>

    <?php if ( empty( $payments ) ) : ?>
        <div style="padding:48px;text-align:center;background:var(--ovr-surface-container-low);border-radius:var(--ovr-radius-md)">
            <span class="material-symbols-outlined" style="font-size:48px;color:var(--ovr-outline);margin-bottom:8px">receipt_long</span>
            <p style="margin:0;font-size:14px;color:var(--ovr-on-surface-variant)"><?php esc_html_e( 'No payments yet.', 'ovr-core' ); ?></p>
        </div>
    <?php else : ?>
        <div style="overflow-x:auto;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-md)">
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <thead>
                    <tr style="background:var(--ovr-surface-container-low);text-align:left">
                        <th style="padding:12px 16px;font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--ovr-on-surface-variant)"><?php esc_html_e( 'Date', 'ovr-core' ); ?></th>
                        <th style="padding:12px 16px;font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--ovr-on-surface-variant)"><?php esc_html_e( 'Type', 'ovr-core' ); ?></th>
                        <th style="padding:12px 16px;font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--ovr-on-surface-variant)"><?php esc_html_e( 'Gateway', 'ovr-core' ); ?></th>
                        <th style="padding:12px 16px;font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--ovr-on-surface-variant);text-align:right"><?php esc_html_e( 'Amount', 'ovr-core' ); ?></th>
                        <th style="padding:12px 16px;font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--ovr-on-surface-variant)"><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $payments as $p ) :
                        $status   = (string) $p['status'];
                        $colors   = $status_color[ $status ] ?? [ 'bg' => 'var(--ovr-surface-container)', 'fg' => 'var(--ovr-on-surface)' ];
                        $gateway  = $gateway_label[ (string) $p['gateway'] ] ?? $p['gateway'];
                    ?>
                        <tr style="border-top:1px solid var(--ovr-outline-variant)">
                            <td style="padding:12px 16px;color:var(--ovr-on-surface-variant)">
                                <?php echo esc_html( mysql2date( get_option( 'date_format' ), $p['created_at'] ) ); ?>
                            </td>
                            <td style="padding:12px 16px;text-transform:capitalize">
                                <?php echo esc_html( str_replace( '_', ' ', $p['payment_type'] ) ); ?>
                            </td>
                            <td style="padding:12px 16px"><?php echo esc_html( $gateway ); ?></td>
                            <td style="padding:12px 16px;text-align:right;font-weight:600;font-variant-numeric:tabular-nums">
                                <?php echo esc_html( $p['currency'] . ' ' . number_format( (float) $p['amount'], 2 ) ); ?>
                            </td>
                            <td style="padding:12px 16px">
                                <span style="display:inline-block;padding:3px 10px;border-radius:9999px;font-size:11px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;background:<?php echo esc_attr( $colors['bg'] ); ?>;color:<?php echo esc_attr( $colors['fg'] ); ?>">
                                    <?php echo esc_html( $status ); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
