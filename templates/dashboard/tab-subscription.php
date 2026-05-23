<?php
/**
 * Subscription tab — current plan, usage, expiry, change link.
 *
 * @package OVR
 * @var array  $subscription
 * @var string $pricing_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$used   = (int) ( $subscription['listings_used'] ?? 0 );
$limit  = (int) ( $subscription['plan_limit']    ?? 0 );
$pct    = $limit > 0 ? min( 100, round( ( $used / $limit ) * 100 ) ) : 0;
$is_unlimited = ( $limit === 0 || $limit >= 9999 );

$expires    = (string) ( $subscription['expires'] ?? '' );
$expires_ts = $expires ? strtotime( $expires ) : 0;
$expires_in = $expires_ts ? max( 0, (int) round( ( $expires_ts - time() ) / DAY_IN_SECONDS ) ) : null;
?>
<section style="display:grid;grid-template-columns:1fr;gap:20px">

    <!-- Current plan card -->
    <div class="ovr-card" style="padding:28px;background:linear-gradient(135deg,var(--ovr-primary-container),var(--ovr-surface));overflow:hidden;position:relative">
        <span style="position:absolute;top:24px;right:24px;background:var(--ovr-primary);color:var(--ovr-on-primary);padding:5px 12px;border-radius:9999px;font-size:11px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase">
            <?php esc_html_e( 'Current Plan', 'ovr-core' ); ?>
        </span>
        <div style="font-size:13px;color:var(--ovr-on-surface-variant);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-bottom:6px">
            <?php esc_html_e( 'Subscription', 'ovr-core' ); ?>
        </div>
        <h2 style="font-size:32px;font-weight:700;margin:0 0 6px">
            <?php echo esc_html( $subscription['plan_name'] ?? '—' ); ?>
        </h2>
        <?php if ( $expires ) : ?>
            <p style="margin:0;color:var(--ovr-on-surface-variant);font-size:14px">
                <?php
                if ( null !== $expires_in && $expires_in <= 30 ) {
                    /* translators: %d: days until renewal */
                    printf( esc_html( _n( 'Renews in %d day', 'Renews in %d days', $expires_in, 'ovr-core' ) ), (int) $expires_in );
                } else {
                    /* translators: %s: expiry date */
                    printf( esc_html__( 'Renews on %s', 'ovr-core' ), esc_html( mysql2date( get_option( 'date_format' ), $expires ) ) );
                }
                ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Listing usage -->
    <div class="ovr-card" style="padding:24px">
        <h3 style="font-size:16px;font-weight:600;margin:0 0 16px"><?php esc_html_e( 'Listing Usage', 'ovr-core' ); ?></h3>

        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px">
            <span style="font-size:32px;font-weight:700">
                <?php echo esc_html( (string) $used ); ?>
                <?php if ( ! $is_unlimited ) : ?>
                    <span style="font-size:18px;color:var(--ovr-on-surface-variant);font-weight:500">/ <?php echo esc_html( (string) $limit ); ?></span>
                <?php endif; ?>
            </span>
            <span style="font-size:13px;color:var(--ovr-on-surface-variant)">
                <?php $is_unlimited ? esc_html_e( 'Unlimited', 'ovr-core' ) : printf( '%d%%', $pct ); ?>
            </span>
        </div>

        <?php if ( ! $is_unlimited ) : ?>
            <div style="background:var(--ovr-surface-container-low);height:8px;border-radius:9999px;overflow:hidden">
                <div style="background:var(--ovr-primary);height:100%;width:<?php echo esc_attr( (string) $pct ); ?>%;transition:width 300ms"></div>
            </div>

            <?php if ( $used >= $limit ) : ?>
                <p style="margin:14px 0 0;padding:12px;background:var(--ovr-error-container);color:var(--ovr-on-error-container);border-radius:var(--ovr-radius-md);font-size:13px;display:flex;gap:8px;align-items:flex-start">
                    <span class="material-symbols-outlined" style="flex-shrink:0">warning</span>
                    <span>
                        <strong><?php esc_html_e( 'Listing limit reached.', 'ovr-core' ); ?></strong><br>
                        <?php esc_html_e( 'Upgrade your plan to add more properties.', 'ovr-core' ); ?>
                    </span>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Change plan CTA -->
    <div class="ovr-card" style="padding:24px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
        <div>
            <h3 style="font-size:16px;font-weight:600;margin:0 0 4px"><?php esc_html_e( 'Need more capacity?', 'ovr-core' ); ?></h3>
            <p style="margin:0;font-size:13px;color:var(--ovr-on-surface-variant)">
                <?php esc_html_e( 'Compare plans and upgrade in seconds. You can downgrade or cancel any time.', 'ovr-core' ); ?>
            </p>
        </div>
        <a href="<?php echo esc_url( $pricing_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-pill">
            <?php esc_html_e( 'Compare Plans', 'ovr-core' ); ?>
        </a>
    </div>

</section>
