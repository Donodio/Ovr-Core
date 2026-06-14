<?php
/**
 * Subscription tab ("Subscription Management") — current plan, usage, manage
 * actions, and available plans (inline switch via the existing checkout flow).
 * Scoped under `.ovr-ld`; the dashboard shell supplies the surrounding nav.
 *
 * @package OVR
 * @var array  $subscription
 * @var array  $plans
 * @var string $pricing_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$plans    = $plans ?? [];
$settings = (array) get_option( 'ovr_settings', [] );
$sym      = $settings['currency_symbol'] ?? '$';

$cur_slug = (string) ( $subscription['plan_slug'] ?? '' );
$cur_name = (string) ( $subscription['plan_name'] ?? '—' );
$used     = (int) ( $subscription['listings_used'] ?? 0 );
$limit    = (int) ( $subscription['plan_limit'] ?? 0 );
$unlimited= ( $limit <= 0 || $limit >= 9999 );
$pct      = ( ! $unlimited && $limit > 0 ) ? min( 100, (int) round( ( $used / $limit ) * 100 ) ) : 0;
$remaining= max( 0, $limit - $used );

$expires    = (string) ( $subscription['expires'] ?? '' );
$expires_ts = $expires ? strtotime( $expires ) : 0;
$expires_in = $expires_ts ? (int) round( ( $expires_ts - time() ) / DAY_IN_SECONDS ) : null;
$pending    = ( null !== $expires_in && $expires_in <= 30 );

$cur_plan   = $plans[ $cur_slug ] ?? null;
$cur_paid   = $cur_plan && (float) ( $cur_plan['price'] ?? 0 ) > 0;

$period_label = static function ( string $p ): string {
    return 'annually' === $p ? __( '/year', 'ovr-core' ) : __( '/month', 'ovr-core' );
};

// Available plans = active plans other than the current one, by sort order.
$available = array_filter( $plans, static fn( $p, $slug ) => ! empty( $p['is_active'] ) && $slug !== $cur_slug, ARRAY_FILTER_USE_BOTH );
uasort( $available, static fn( $a, $b ) => ( (int) ( $a['sort_order'] ?? 0 ) ) <=> ( (int) ( $b['sort_order'] ?? 0 ) ) );

$checkout_action = admin_url( 'admin-post.php' );
$checkout_url    = $checkout_url ?? '';

/**
 * Render the action for a plan. Paid plans go to the dedicated checkout page
 * (review + payment); free plans activate immediately via the existing handler.
 */
$checkout_form = static function ( array $plan, string $label, string $btn_class ) use ( $checkout_action, $checkout_url ) {
    $is_free = 0 >= (float) ( $plan['price'] ?? 0 );
    if ( $is_free ) {
        ?>
        <form method="post" action="<?php echo esc_url( $checkout_action ); ?>" class="ld-sub-form">
            <input type="hidden" name="action" value="ovr_start_checkout">
            <input type="hidden" name="plan" value="<?php echo esc_attr( $plan['slug'] ?? '' ); ?>">
            <?php wp_nonce_field( 'ovr_checkout_action', 'ovr_checkout_nonce' ); ?>
            <button type="submit" class="<?php echo esc_attr( $btn_class ); ?>"><?php echo esc_html( $label ); ?></button>
        </form>
        <?php
        return;
    }
    $url = add_query_arg( 'plan', $plan['slug'] ?? '', $checkout_url );
    ?>
    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $btn_class ); ?>"><?php echo esc_html( $label ); ?></a>
    <?php
};
?>

<?php if ( $pending ) : ?>
    <div class="ld-sub-banner">
        <span class="material-symbols-outlined fill">warning</span>
        <div>
            <p class="ld-sub-banner-t"><?php esc_html_e( 'Subscription Pending Renewal', 'ovr-core' ); ?></p>
            <p class="ld-sub-banner-d"><?php esc_html_e( 'Your plan is set to expire soon. Renew now to maintain uninterrupted access to your property listings.', 'ovr-core' ); ?></p>
        </div>
    </div>
<?php endif; ?>

<header class="ld-sub-head">
    <h1 class="ld-sub-h1"><?php esc_html_e( 'My Subscription', 'ovr-core' ); ?></h1>
    <p class="ld-sub-lede"><?php esc_html_e( 'View your current plan and renew to extend your subscription. Renewing simply pushes your expiration date forward.', 'ovr-core' ); ?></p>
</header>

<!-- Current plan -->
<section class="ld-sub-current">
    <div class="ld-sub-current-main">
        <span class="ld-sub-tag"><?php esc_html_e( 'Current Plan', 'ovr-core' ); ?></span>
        <h2 class="ld-sub-plan-name"><?php echo esc_html( $cur_name ); ?></h2>
        <?php if ( $expires ) : ?>
            <p class="ld-sub-expires">
                <span class="material-symbols-outlined">event</span>
                <?php printf( esc_html__( 'Expires: %s', 'ovr-core' ), esc_html( mysql2date( 'M j, Y', $expires ) ) ); ?>
            </p>
        <?php endif; ?>

        <div class="ld-sub-usage">
            <div class="ld-sub-usage-row">
                <span class="ld-sub-usage-lbl"><?php esc_html_e( 'Listings Used', 'ovr-core' ); ?></span>
                <span class="ld-sub-usage-val"><?php echo esc_html( $unlimited ? sprintf( __( '%d / Unlimited', 'ovr-core' ), $used ) : $used . ' / ' . $limit ); ?></span>
            </div>
            <div class="ld-sub-bar"><div class="ld-sub-bar-fill" style="width:<?php echo esc_attr( (string) ( $unlimited ? 12 : $pct ) ); ?>%"></div></div>
            <p class="ld-sub-usage-note">
                <?php
                if ( $unlimited ) {
                    esc_html_e( 'Your plan includes unlimited listings.', 'ovr-core' );
                } else {
                    printf( esc_html( _n( 'You have %d listing remaining on this plan.', 'You have %d listings remaining on this plan.', $remaining, 'ovr-core' ) ), $remaining );
                }
                ?>
            </p>
        </div>
    </div>

    <div class="ld-sub-manage">
        <h3 class="ld-sub-manage-h"><?php esc_html_e( 'Renew Subscription', 'ovr-core' ); ?></h3>
        <?php if ( $cur_paid ) : ?>
            <?php $checkout_form( $cur_plan, __( 'Renew & Extend', 'ovr-core' ), 'ld-sub-btn ld-sub-btn--primary' ); ?>
            <p class="ld-sub-manage-note"><?php esc_html_e( 'Renewing your current plan extends the expiration date — your plan and listing limit stay the same.', 'ovr-core' ); ?></p>
        <?php else : ?>
            <a href="<?php echo esc_url( $pricing_url ); ?>" class="ld-sub-btn ld-sub-btn--primary"><?php esc_html_e( 'Choose a Plan', 'ovr-core' ); ?></a>
            <p class="ld-sub-manage-note"><?php esc_html_e( 'Select a subscription plan to activate your listings.', 'ovr-core' ); ?></p>
        <?php endif; ?>
    </div>
</section>

<style>
    .ovr-ld .ld-sub-banner{display:flex;align-items:flex-start;gap:14px;background:var(--terc);color:#4e3d00;border:1px solid rgba(115,92,0,.25);border-radius:14px;padding:16px 20px;box-shadow:0 4px 24px rgba(0,0,0,.04)}
    .ovr-ld .ld-sub-banner .material-symbols-outlined{font-size:24px;color:var(--ter);flex-shrink:0}
    .ovr-ld .ld-sub-banner-t{font-size:15px;font-weight:700;margin:0}
    .ovr-ld .ld-sub-banner-d{font-size:14px;margin:2px 0 0;opacity:.92;line-height:1.5}

    .ovr-ld .ld-sub-head{margin-top:4px}
    .ovr-ld .ld-sub-h1{font-size:32px;font-weight:700;letter-spacing:-.01em;color:var(--on);margin:0 0 6px}
    .ovr-ld .ld-sub-lede{font-size:15px;color:var(--sv);margin:0}
    .ovr-ld .ld-sub-h3{font-size:24px;font-weight:600;color:var(--on);margin:0 0 22px}

    /* Current plan */
    .ovr-ld .ld-sub-current{background:var(--surf);border:1px solid var(--ov);border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.04);display:flex;flex-direction:column;gap:0;padding:32px;overflow:hidden}
    .ovr-ld .ld-sub-current-main{flex:1}
    .ovr-ld .ld-sub-tag{display:inline-block;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;background:rgba(0,76,76,.1);color:var(--p);padding:5px 10px;border-radius:6px;margin-bottom:14px}
    .ovr-ld .ld-sub-plan-name{font-size:30px;font-weight:700;color:var(--p);margin:0 0 6px;line-height:1.1}
    .ovr-ld .ld-sub-expires{display:flex;align-items:center;gap:8px;font-size:14px;color:var(--sv);margin:0 0 28px}
    .ovr-ld .ld-sub-expires .material-symbols-outlined{font-size:18px;color:var(--outline)}
    .ovr-ld .ld-sub-usage{max-width:460px}
    .ovr-ld .ld-sub-usage-row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
    .ovr-ld .ld-sub-usage-lbl{font-size:14px;font-weight:600;color:var(--on)}
    .ovr-ld .ld-sub-usage-val{font-size:14px;color:var(--sv)}
    .ovr-ld .ld-sub-bar{height:12px;background:var(--surface-container-highest,#e0e3e2);border-radius:9999px;overflow:hidden}
    .ovr-ld .ld-sub-bar-fill{height:100%;background:var(--p);border-radius:9999px;transition:width .4s}
    .ovr-ld .ld-sub-usage-note{font-size:12px;color:var(--sv);margin:10px 0 0}

    .ovr-ld .ld-sub-manage{display:flex;flex-direction:column;gap:12px;margin-top:28px;padding-top:28px;border-top:1px solid var(--ov)}
    .ovr-ld .ld-sub-manage-h{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);margin:0 0 2px}
    .ovr-ld .ld-sub-manage-note{font-size:12px;color:var(--sv);margin:2px 0 0;line-height:1.5}
    .ovr-ld .ld-sub-form{margin:0;display:flex;flex-direction:column;gap:8px}
    .ovr-ld .ld-sub-gateway{width:100%;padding:9px 12px;border:1px solid var(--ov);border-radius:9px;font-family:inherit;font-size:13px;color:var(--on);background:#fff}
    .ovr-ld .ld-sub-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px 20px;border-radius:10px;font-family:inherit;font-size:14px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:background .18s,color .18s,box-shadow .18s}
    .ovr-ld .ld-sub-btn .material-symbols-outlined{font-size:18px}
    .ovr-ld .ld-sub-btn--primary{background:var(--p);color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.12)}
    .ovr-ld .ld-sub-btn--primary:hover{background:#003838;color:#fff}
    .ovr-ld .ld-sub-btn--secondary{background:var(--sec);color:#fff}
    .ovr-ld .ld-sub-btn--secondary:hover{background:#00513a;color:#fff}
    .ovr-ld .ld-sub-btn--ghost{background:transparent;color:var(--sv);border-color:var(--outline)}
    .ovr-ld .ld-sub-btn--ghost:hover{background:var(--sclow);color:var(--on)}
    .ovr-ld .ld-sub-btn--outline{background:transparent;color:var(--p);border-color:var(--p)}
    .ovr-ld .ld-sub-btn--outline:hover{background:rgba(0,76,76,.06)}

    @media (min-width:1000px){
        .ovr-ld .ld-sub-current{flex-direction:row;gap:36px;align-items:stretch}
        .ovr-ld .ld-sub-manage{margin-top:0;padding-top:0;border-top:none;border-left:1px solid var(--ov);padding-left:36px;min-width:240px;justify-content:center}
    }

    /* Available plans */
    .ovr-ld .ld-sub-section{margin-top:8px}
    .ovr-ld .ld-sub-plans-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:24px}
    .ovr-ld .ld-sub-plan{background:var(--surf);border:1px solid var(--ov);border-radius:14px;padding:28px;display:flex;flex-direction:column;box-shadow:0 4px 24px rgba(0,0,0,.04);position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s}
    .ovr-ld .ld-sub-plan:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(0,0,0,.08)}
    .ovr-ld .ld-sub-plan.is-popular{border-color:var(--sec)}
    .ovr-ld .ld-sub-pop{position:absolute;top:0;right:0;background:var(--sec);color:#fff;font-size:11px;font-weight:700;letter-spacing:.04em;padding:5px 14px;border-radius:0 0 0 12px}
    .ovr-ld .ld-sub-plan-head h4{font-size:22px;font-weight:600;color:var(--on);margin:0 0 6px}
    .ovr-ld .ld-sub-plan-desc{font-size:14px;color:var(--sv);margin:0 0 18px;line-height:1.5}
    .ovr-ld .ld-sub-plan-price{margin-bottom:22px}
    .ovr-ld .ld-sub-amt{font-size:32px;font-weight:700;color:var(--p)}
    .ovr-ld .ld-sub-per{font-size:14px;color:var(--sv)}
    .ovr-ld .ld-sub-feats{list-style:none;margin:0 0 24px;padding:0;display:flex;flex-direction:column;gap:12px;flex:1}
    .ovr-ld .ld-sub-feats li{display:flex;align-items:flex-start;gap:12px;font-size:14px;color:var(--on)}
    .ovr-ld .ld-sub-feats .material-symbols-outlined{font-size:20px;color:var(--sec);flex-shrink:0}

    @media (max-width:760px){
        .ovr-ld .ld-sub-h1{font-size:26px}
        .ovr-ld .ld-sub-plans-grid{grid-template-columns:1fr}
    }
</style>
