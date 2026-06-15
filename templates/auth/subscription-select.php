<?php
/**
 * Subscription Select template — forced plan choice for unpaid landlords.
 *
 * Senior-friendly: one screen, large plan cards, one obvious button per plan.
 * Scoped under `.ovr-subsel` (teal mockup palette + Inter).
 *
 * @package OVR
 * @var \WP_User $user
 * @var array    $plans         Paid, active plans keyed by slug.
 * @var string   $checkout_url
 * @var string   $logout_url
 * @var bool     $is_expired
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$plans      = $plans ?? [];
$is_expired = ! empty( $is_expired );
$settings   = (array) get_option( 'ovr_settings', [] );
$sym        = $settings['currency_symbol'] ?? '$';
$first_name = $user->first_name ?: $user->display_name;

$period_label = static function ( string $p ): string {
    return 'annually' === $p ? __( '/ year', 'ovr-core' ) : __( '/ month', 'ovr-core' );
};
?>
<div class="ovr-subsel">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        .ovr-subsel{--p:#004c4c;--pc:#006666;--sec:#006c4a;--secc:#74f7be;--ter:#735c00;--terc:#cca72f;--bg:#f7faf9;--surf:#fff;--sclow:#f1f4f3;--sv:#3f4948;--outline:#6f7979;--ov:#bec9c8;--on:#181c1c;
            font-family:'Inter',system-ui,-apple-system,sans-serif;color:var(--on);background:var(--bg);padding:48px 20px;min-height:70vh}
        .ovr-subsel *{box-sizing:border-box}
        .ovr-subsel-inner{max-width:960px;margin:0 auto}
        .ovr-subsel-head{text-align:center;margin-bottom:36px}
        .ovr-subsel-head h1{font-size:34px;font-weight:700;letter-spacing:-.01em;color:var(--p);margin:0 0 10px}
        .ovr-subsel-head p{font-size:17px;color:var(--sv);margin:0;line-height:1.6}
        .ovr-subsel-banner{display:flex;align-items:center;gap:12px;max-width:680px;margin:0 auto 28px;background:var(--terc);color:#4e3d00;border:1px solid rgba(115,92,0,.3);border-radius:12px;padding:14px 18px;font-size:15px;font-weight:600}
        .ovr-subsel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:28px}
        .ovr-subsel-card{background:var(--surf);border:2px solid var(--ov);border-radius:16px;padding:28px 24px;display:flex;flex-direction:column;box-shadow:0 4px 24px rgba(0,0,0,.04);position:relative}
        .ovr-subsel-card.is-popular{border-color:var(--sec)}
        .ovr-subsel-pop{position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--sec);color:#fff;font-size:12px;font-weight:700;letter-spacing:.03em;padding:5px 16px;border-radius:9999px;white-space:nowrap}
        .ovr-subsel-name{font-size:21px;font-weight:700;color:var(--on);margin:6px 0 4px}
        .ovr-subsel-desc{font-size:14px;color:var(--sv);margin:0 0 18px;line-height:1.5;min-height:42px}
        .ovr-subsel-price{margin-bottom:18px}
        .ovr-subsel-amt{font-size:38px;font-weight:700;color:var(--p)}
        .ovr-subsel-per{font-size:15px;color:var(--sv)}
        .ovr-subsel-feats{list-style:none;margin:0 0 24px;padding:0;display:flex;flex-direction:column;gap:11px;flex:1}
        .ovr-subsel-feats li{display:flex;align-items:flex-start;gap:10px;font-size:14.5px;color:var(--on)}
        .ovr-subsel-feats .material-symbols-outlined{font-size:20px;color:var(--sec);flex-shrink:0}
        .ovr-subsel-btn{display:block;width:100%;text-align:center;padding:15px 20px;border-radius:11px;font-size:16px;font-weight:700;text-decoration:none;border:2px solid var(--p);transition:background .18s,color .18s}
        .ovr-subsel-btn--primary{background:var(--p);color:#fff}
        .ovr-subsel-btn--primary:hover{background:#003838;color:#fff}
        .ovr-subsel-btn--outline{background:#fff;color:var(--p)}
        .ovr-subsel-btn--outline:hover{background:rgba(0,76,76,.06);color:var(--p)}
        .ovr-subsel-foot{text-align:center;font-size:14px;color:var(--sv)}
        .ovr-subsel-foot a{color:var(--pc);font-weight:600;text-decoration:none}
        .ovr-subsel-foot a:hover{text-decoration:underline}
        @media (max-width:600px){.ovr-subsel{padding:32px 14px}.ovr-subsel-head h1{font-size:27px}}
    </style>

    <div class="ovr-subsel-inner">

        <?php if ( $is_expired ) : ?>
            <div class="ovr-subsel-banner">
                <span class="material-symbols-outlined">warning</span>
                <span><?php esc_html_e( 'Your subscription has expired. Renew below to restore access to your dashboard and listings.', 'ovr-core' ); ?></span>
            </div>
        <?php endif; ?>

        <div class="ovr-subsel-head">
            <h1><?php printf( esc_html__( 'Choose your subscription, %s', 'ovr-core' ), esc_html( $first_name ) ); ?></h1>
            <p><?php esc_html_e( 'An active subscription is required to access your landlord dashboard and publish listings. Pick a plan below to continue to secure payment.', 'ovr-core' ); ?></p>
        </div>

        <?php if ( empty( $plans ) ) : ?>
            <p style="text-align:center;color:var(--sv)"><?php esc_html_e( 'No subscription plans are available right now. Please contact support.', 'ovr-core' ); ?></p>
        <?php else : ?>
            <div class="ovr-subsel-grid">
                <?php foreach ( $plans as $slug => $plan ) :
                    $popular = ! empty( $plan['is_popular'] );
                    $url     = add_query_arg( 'plan', (string) $slug, $checkout_url );
                ?>
                    <div class="ovr-subsel-card<?php echo $popular ? ' is-popular' : ''; ?>">
                        <?php if ( $popular ) : ?>
                            <span class="ovr-subsel-pop"><?php esc_html_e( 'Most Popular', 'ovr-core' ); ?></span>
                        <?php endif; ?>
                        <h2 class="ovr-subsel-name"><?php echo esc_html( $plan['name'] ?? '' ); ?></h2>
                        <p class="ovr-subsel-desc"><?php echo esc_html( $plan['description'] ?? '' ); ?></p>
                        <div class="ovr-subsel-price">
                            <span class="ovr-subsel-amt"><?php echo esc_html( $sym . number_format( (float) $plan['price'], 0 ) ); ?></span>
                            <span class="ovr-subsel-per"><?php echo esc_html( $period_label( (string) ( $plan['period'] ?? 'annually' ) ) ); ?></span>
                        </div>
                        <ul class="ovr-subsel-feats">
                            <?php foreach ( (array) ( $plan['features'] ?? [] ) as $f ) : ?>
                                <li><span class="material-symbols-outlined">check_circle</span><span><?php echo esc_html( $f ); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?php echo esc_url( $url ); ?>" class="ovr-subsel-btn ovr-subsel-btn--<?php echo $popular ? 'primary' : 'outline'; ?>">
                            <?php printf( esc_html__( 'Choose %s', 'ovr-core' ), esc_html( $plan['name'] ?? __( 'Plan', 'ovr-core' ) ) ); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="ovr-subsel-foot">
            <?php
            printf(
                /* translators: %s: sign-out link */
                esc_html__( 'Not ready yet? You can %s and come back later.', 'ovr-core' ),
                '<a href="' . esc_url( $logout_url ) . '">' . esc_html__( 'sign out', 'ovr-core' ) . '</a>'
            );
            ?>
        </p>
    </div>
</div>
