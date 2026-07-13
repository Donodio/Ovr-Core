<?php
/**
 * Subscription tab — unified subscription management (current plan, status,
 * usage, account credit, renewal/upgrade options).
 *
 * @package OVR
 * @var array  $subscription
 * @var string $status_label
 * @var array  $plans
 * @var string $pricing_url
 * @var string $checkout_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Subscription\UserSubscription;

$plans        = $plans ?? [];
$pricing_url  = $pricing_url ?? '';
$checkout_url = $checkout_url ?? '';
$settings     = (array) get_option( 'ovr_settings', [] );
$sym          = $settings['currency_symbol'] ?? '$';

$cur_slug    = (string) ( $subscription['plan_slug'] ?? '' );
$cur_name    = (string) ( $subscription['plan_name'] ?? '—' );
$status      = (string) ( $subscription['status'] ?? '' );
$status_lbl  = (string) ( $status_label ?? UserSubscription::status_label( $status ) );
$used        = (int) ( $subscription['listings_used'] ?? 0 );
$limit       = (int) ( $subscription['plan_limit'] ?? 0 );
$unlimited   = ( $limit <= 0 || $limit >= 9999 );
$pct         = ( ! $unlimited && $limit > 0 ) ? min( 100, (int) round( ( $used / $limit ) * 100 ) ) : 0;
$remaining   = max( 0, $limit - $used );
$expires     = (string) ( $subscription['expires'] ?? '' );
$expires_ts  = $expires ? strtotime( $expires ) : 0;
$days_left   = (int) ( $subscription['days_remaining'] ?? 0 );
$credit      = (float) ( $subscription['credit'] ?? 0 );

$is_paid     = $cur_slug ? UserSubscription::is_paid_plan( $cur_slug ) : false;
$is_active   = ( 'active' === $status );
$is_expired  = ( 'expired' === $status );
$is_pending  = ( 'pending' === $status );
$is_none     = ( 'none' === $status || '' === $status );
$is_due_soon = ( $is_active && $days_left > 0 && $days_left <= 30 );

$period_label = static function ( string $p ): string {
    return 'annually' === $p ? __( '/year', 'ovr-core' ) : __( '/month', 'ovr-core' );
};

$cur_plan  = $plans[ $cur_slug ] ?? null;
// P4: Base Subscriber is an internal expiry fallback — never displayed,
// selectable, or compared. Exclude it from every plan list on this screen.
$available = array_filter(
    $plans,
    static fn( $p, $slug ) => ! empty( $p['is_active'] ) && 'base_subscriber' !== $slug,
    ARRAY_FILTER_USE_BOTH
);
uasort( $available, static fn( $a, $b ) => ( (int) ( $a['sort_order'] ?? 0 ) ) <=> ( (int) ( $b['sort_order'] ?? 0 ) ) );

// The renewal dropdown defaults to the current plan when it is a real paid plan;
// otherwise the first available plan (covers new/expired users too).
$default_slug = ( $cur_slug && isset( $available[ $cur_slug ] ) )
    ? $cur_slug
    : (string) ( array_key_first( $available ) ?? '' );
$default_plan = $available[ $default_slug ] ?? null;
?>

<?php if ( $is_expired ) : ?>
    <div class="ld-sub-banner ld-sub-banner--err">
        <span class="material-symbols-outlined fill">error</span>
        <div>
            <p class="ld-sub-banner-t"><?php esc_html_e( 'Subscription Expired', 'ovr-core' ); ?></p>
            <p class="ld-sub-banner-d"><?php esc_html_e( 'Your subscription has expired. Renew now to reactivate your listings and restore dashboard access.', 'ovr-core' ); ?></p>
        </div>
        <?php if ( $is_paid ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'plan', $cur_slug, $checkout_url ) ); ?>" class="ld-sub-bb"><?php esc_html_e( 'Renew Now', 'ovr-core' ); ?></a>
        <?php endif; ?>
    </div>
<?php elseif ( $is_pending ) : ?>
    <div class="ld-sub-banner ld-sub-banner--warn">
        <span class="material-symbols-outlined fill">hourglass_top</span>
        <div>
            <p class="ld-sub-banner-t"><?php esc_html_e( 'Payment Pending', 'ovr-core' ); ?></p>
            <p class="ld-sub-banner-d"><?php esc_html_e( 'Your payment is being processed. Dashboard access will be granted once the payment is confirmed.', 'ovr-core' ); ?></p>
        </div>
    </div>
<?php elseif ( $is_due_soon ) : ?>
    <div class="ld-sub-banner ld-sub-banner--warn">
        <span class="material-symbols-outlined fill">schedule</span>
        <div>
            <p class="ld-sub-banner-t"><?php esc_html_e( 'Renewal Due Soon', 'ovr-core' ); ?></p>
            <p class="ld-sub-banner-d"><?php printf( esc_html__( 'Your subscription renews in %d days. Renew early to keep your listings live without interruption.', 'ovr-core' ), $days_left ); ?></p>
        </div>
        <?php if ( $is_paid ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'plan', $cur_slug, $checkout_url ) ); ?>" class="ld-sub-bb"><?php esc_html_e( 'Renew Now', 'ovr-core' ); ?></a>
        <?php endif; ?>
    </div>
<?php elseif ( $is_none ) : ?>
    <div class="ld-sub-banner">
        <span class="material-symbols-outlined fill">info</span>
        <div>
            <p class="ld-sub-banner-t"><?php esc_html_e( 'No Active Subscription', 'ovr-core' ); ?></p>
            <p class="ld-sub-banner-d"><?php esc_html_e( 'Choose a plan below to activate your landlord dashboard and start listing properties.', 'ovr-core' ); ?></p>
        </div>
    </div>
<?php endif; ?>

<header class="ld-sub-head">
    <h1 class="ld-sub-h1"><?php esc_html_e( 'My Subscription', 'ovr-core' ); ?></h1>
    <p class="ld-sub-lede"><?php esc_html_e( 'Manage your plan, view usage, renew or upgrade your subscription.', 'ovr-core' ); ?></p>
</header>

<!-- Current plan -->
<section class="ld-sub-current">
    <div class="ld-sub-current-main">
        <span class="ld-sub-tag"><?php esc_html_e( 'Current Plan', 'ovr-core' ); ?></span>
        <h2 class="ld-sub-plan-name"><?php echo esc_html( $cur_name ); ?></h2>

        <div class="ld-sub-status-row">
            <span class="ld-sub-badge ld-sub-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_lbl ); ?></span>
            <?php if ( $expires_ts ) : ?>
                <span class="ld-sub-expires-in">
                    <span class="material-symbols-outlined">event</span>
                    <?php if ( $is_active && $days_left > 0 ) : ?>
                        <?php printf( esc_html__( '%d days remaining', 'ovr-core' ), $days_left ); ?>
                        &middot;
                        <?php printf( esc_html__( 'Renews %s', 'ovr-core' ), esc_html( mysql2date( 'M j, Y', $expires ) ) ); ?>
                    <?php else : ?>
                        <?php printf( esc_html__( 'Expires: %s', 'ovr-core' ), esc_html( mysql2date( 'M j, Y', $expires ) ) ); ?>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>

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

        <?php if ( $credit > 0 ) : ?>
            <div class="ld-sub-credit">
                <span class="material-symbols-outlined">account_balance_wallet</span>
                <div>
                    <p class="ld-sub-credit-lbl"><?php esc_html_e( 'Account Credit', 'ovr-core' ); ?></p>
                    <p class="ld-sub-credit-val"><?php echo esc_html( $sym . number_format( $credit, 2 ) ); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="ld-sub-manage">
        <h3 class="ld-sub-manage-h"><?php esc_html_e( 'Actions', 'ovr-core' ); ?></h3>

        <?php if ( $is_active || $is_expired ) : ?>
            <?php if ( $is_paid && $cur_plan ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'plan', $cur_slug, $checkout_url ) ); ?>" class="ld-sub-btn ld-sub-btn--primary">
                    <?php $is_expired ? esc_html_e( 'Renew Subscription', 'ovr-core' ) : esc_html_e( 'Extend Renewal', 'ovr-core' ); ?>
                </a>
                <p class="ld-sub-manage-note"><?php esc_html_e( 'Renewing extends your expiration date and keeps your plan and listings intact.', 'ovr-core' ); ?></p>
            <?php else : ?>
                <a href="#ld-sub-renew" class="ld-sub-btn ld-sub-btn--primary"><?php esc_html_e( 'Choose a Plan', 'ovr-core' ); ?></a>
            <?php endif; ?>
        <?php elseif ( $is_pending ) : ?>
            <p class="ld-sub-manage-note"><?php esc_html_e( 'Your subscription is pending. Please wait for payment confirmation.', 'ovr-core' ); ?></p>
        <?php elseif ( $is_none ) : ?>
            <a href="<?php echo esc_url( $pricing_url ); ?>" class="ld-sub-btn ld-sub-btn--primary"><?php esc_html_e( 'Choose a Plan', 'ovr-core' ); ?></a>
        <?php endif; ?>
    </div>
</section>

<?php if ( ! empty( $available ) ) : ?>
    <!-- Renew / Extend workflow (P4): optimised for the common case — renew the
         same plan. No full plan-comparison grid; just confirm the plan and go. -->
    <section class="ld-sub-section" id="ld-sub-renew">
        <div class="ld-sub-renew">
            <div class="ld-sub-renew-intro">
                <h3 class="ld-sub-h3"><?php echo $is_expired ? esc_html__( 'Renew Subscription', 'ovr-core' ) : esc_html__( 'Renew / Extend Subscription', 'ovr-core' ); ?></h3>
                <p class="ld-sub-lede"><?php esc_html_e( 'Most owners renew the same plan each year. Confirm your plan below and continue to payment.', 'ovr-core' ); ?></p>
            </div>

            <div class="ld-sub-renew-grid">
                <div class="ld-sub-renew-pick">
                    <label class="ld-sub-renew-lbl" for="ld-sub-plan-select"><?php esc_html_e( 'Subscription Plan', 'ovr-core' ); ?></label>
                    <select id="ld-sub-plan-select" class="ld-sub-select">
                        <?php foreach ( $available as $slug => $plan ) :
                            $p_price = (float) ( $plan['price'] ?? 0 );
                            $p_per   = $period_label( $plan['period'] ?? 'monthly' );
                            $p_label = ( $plan['name'] ?? $slug )
                                . ( $p_price > 0 ? ' — ' . $sym . number_format( $p_price, 0 ) . ' ' . $p_per : ' — ' . __( 'Free', 'ovr-core' ) )
                                . ( $slug === $cur_slug ? ' (' . __( 'current', 'ovr-core' ) . ')' : '' );
                        ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"
                                    data-price="<?php echo esc_attr( number_format( $p_price, 2, '.', '' ) ); ?>"
                                    data-per="<?php echo esc_attr( $p_per ); ?>"
                                    data-name="<?php echo esc_attr( $plan['name'] ?? $slug ); ?>"
                                    <?php selected( $default_slug, $slug ); ?>>
                                <?php echo esc_html( $p_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ld-sub-renew-hint"><?php esc_html_e( 'Defaults to your current plan — only change it if you want a different one.', 'ovr-core' ); ?></p>
                </div>

                <div class="ld-sub-order">
                    <h4 class="ld-sub-order-h"><?php esc_html_e( 'Order Summary', 'ovr-core' ); ?></h4>
                    <div class="ld-sub-order-row">
                        <span><?php esc_html_e( 'Current Subscription', 'ovr-core' ); ?></span>
                        <strong><?php echo esc_html( $cur_name ); ?></strong>
                    </div>
                    <div class="ld-sub-order-row">
                        <span><?php esc_html_e( 'Current Expiration', 'ovr-core' ); ?></span>
                        <strong><?php echo $expires_ts ? esc_html( mysql2date( 'M j, Y', $expires ) ) : esc_html__( '—', 'ovr-core' ); ?></strong>
                    </div>
                    <div class="ld-sub-order-row">
                        <span><?php esc_html_e( 'Account Balance', 'ovr-core' ); ?></span>
                        <strong><?php echo esc_html( $sym . number_format( $credit, 2 ) ); ?></strong>
                    </div>
                    <div class="ld-sub-order-row ld-sub-order-sep">
                        <span id="ld-sub-order-plan"><?php echo esc_html( $default_plan['name'] ?? '' ); ?></span>
                        <strong id="ld-sub-order-price"></strong>
                    </div>
                    <div class="ld-sub-order-row ld-sub-order-total">
                        <span><?php esc_html_e( 'Total Due Today', 'ovr-core' ); ?></span>
                        <strong id="ld-sub-order-total"></strong>
                    </div>
                    <a href="<?php echo esc_url( add_query_arg( 'plan', $default_slug, $checkout_url ) ); ?>" id="ld-sub-renew-btn" class="ld-sub-btn ld-sub-btn--primary">
                        <span class="material-symbols-outlined">lock</span><?php esc_html_e( 'Continue to Payment', 'ovr-core' ); ?>
                    </a>
                    <?php if ( $credit > 0 ) : ?>
                        <p class="ld-sub-renew-hint" style="text-align:center"><?php esc_html_e( 'Your account balance is applied at checkout where available.', 'ovr-core' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script>
    (function(){
        var sel = document.getElementById('ld-sub-plan-select');
        if (!sel) { return; }
        var sym = '<?php echo esc_js( $sym ); ?>';
        var checkoutBase = '<?php echo esc_js( $checkout_url ); ?>';
        var freeLabel = '<?php echo esc_js( __( 'Free', 'ovr-core' ) ); ?>';
        var planEl = document.getElementById('ld-sub-order-plan');
        var priceEl = document.getElementById('ld-sub-order-price');
        var totalEl = document.getElementById('ld-sub-order-total');
        var btn = document.getElementById('ld-sub-renew-btn');
        function money(n){ return sym + Number(n).toLocaleString(undefined, { maximumFractionDigits: 0 }); }
        function update(){
            var o = sel.options[sel.selectedIndex];
            var price = parseFloat(o.getAttribute('data-price')) || 0;
            var per   = o.getAttribute('data-per') || '';
            var name  = o.getAttribute('data-name') || '';
            if (planEl)  { planEl.textContent  = name; }
            if (priceEl) { priceEl.textContent = price > 0 ? (money(price) + ' ' + per) : freeLabel; }
            if (totalEl) { totalEl.textContent = price > 0 ? money(price) : freeLabel; }
            if (btn)     { btn.href = checkoutBase + (checkoutBase.indexOf('?') > -1 ? '&' : '?') + 'plan=' + encodeURIComponent(sel.value); }
        }
        sel.addEventListener('change', update);
        update();
    })();
    </script>
<?php endif; ?>

<style>
    .ovr-ld .ld-sub-banner{display:flex;align-items:flex-start;gap:14px;background:var(--terc);color:#4e3d00;border:1px solid rgba(115,92,0,.25);border-radius:14px;padding:16px 20px;box-shadow:0 4px 24px rgba(0,0,0,.04)}
    .ovr-ld .ld-sub-banner .material-symbols-outlined{font-size:24px;color:var(--ter);flex-shrink:0}
    .ovr-ld .ld-sub-banner--err{background:var(--errc);color:#6b1a1a;border-color:rgba(170,46,46,.25)}
    .ovr-ld .ld-sub-banner--err .material-symbols-outlined{color:var(--err)}
    .ovr-ld .ld-sub-banner--warn{background:var(--terc);color:#4e3d00;border-color:rgba(115,92,0,.25)}
    .ovr-ld .ld-sub-banner--warn .material-symbols-outlined{color:var(--ter)}
    .ovr-ld .ld-sub-banner-t{font-size:15px;font-weight:700;margin:0}
    .ovr-ld .ld-sub-banner-d{font-size:14px;margin:2px 0 0;opacity:.92;line-height:1.5}
    .ovr-ld .ld-sub-bb{flex-shrink:0;display:inline-flex;align-items:center;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;background:var(--p);color:#fff;margin-left:auto;transition:background .15s}
    .ovr-ld .ld-sub-bb:hover{background:#003838}

    .ovr-ld .ld-sub-head{margin-top:4px}
    .ovr-ld .ld-sub-h1{font-size:32px;font-weight:700;letter-spacing:-.01em;color:var(--on);margin:0 0 6px}
    .ovr-ld .ld-sub-lede{font-size:15px;color:var(--sv);margin:0}
    .ovr-ld .ld-sub-h3{font-size:24px;font-weight:600;color:var(--on);margin:0 0 22px}

    /* Current plan */
    .ovr-ld .ld-sub-current{background:var(--surf);border:1px solid var(--ov);border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.04);display:flex;flex-direction:column;gap:0;padding:32px;overflow:hidden}
    .ovr-ld .ld-sub-current-main{flex:1}
    .ovr-ld .ld-sub-tag{display:inline-block;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;background:rgba(0,76,76,.1);color:var(--p);padding:5px 10px;border-radius:6px;margin-bottom:14px}
    .ovr-ld .ld-sub-plan-name{font-size:30px;font-weight:700;color:var(--p);margin:0 0 6px;line-height:1.1}
    .ovr-ld .ld-sub-status-row{display:flex;align-items:center;gap:16px;margin-bottom:28px;flex-wrap:wrap}
    .ovr-ld .ld-sub-badge{display:inline-block;font-size:12px;font-weight:700;padding:4px 10px;border-radius:7px;text-transform:capitalize}
    .ovr-ld .ld-sub-badge--active{background:rgba(0,108,74,.12);color:var(--sec)}
    .ovr-ld .ld-sub-badge--pending{background:rgba(115,92,0,.12);color:var(--ter)}
    .ovr-ld .ld-sub-badge--expired{background:rgba(170,46,46,.1);color:var(--err)}
    .ovr-ld .ld-sub-badge--cancelled{background:rgba(128,128,128,.1);color:var(--sv)}
    .ovr-ld .ld-sub-badge--suspended{background:rgba(170,46,46,.1);color:var(--err)}
    .ovr-ld .ld-sub-badge--none{background:rgba(128,128,128,.1);color:var(--sv)}
    .ovr-ld .ld-sub-expires-in{display:inline-flex;align-items:center;gap:6px;font-size:14px;color:var(--sv);margin:0}
    .ovr-ld .ld-sub-expires-in .material-symbols-outlined{font-size:16px;color:var(--outline)}
    .ovr-ld .ld-sub-usage{max-width:460px}
    .ovr-ld .ld-sub-usage-row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
    .ovr-ld .ld-sub-usage-lbl{font-size:14px;font-weight:600;color:var(--on)}
    .ovr-ld .ld-sub-usage-val{font-size:14px;color:var(--sv)}
    .ovr-ld .ld-sub-bar{height:12px;background:var(--surface-container-highest,#e0e3e2);border-radius:9999px;overflow:hidden}
    .ovr-ld .ld-sub-bar-fill{height:100%;background:var(--p);border-radius:9999px;transition:width .4s}
    .ovr-ld .ld-sub-usage-note{font-size:12px;color:var(--sv);margin:10px 0 0}

    .ovr-ld .ld-sub-credit{display:flex;align-items:center;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--ov)}
    .ovr-ld .ld-sub-credit .material-symbols-outlined{font-size:24px;color:var(--pfd)}
    .ovr-ld .ld-sub-credit-lbl{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--outline);margin:0 0 2px}
    .ovr-ld .ld-sub-credit-val{font-size:20px;font-weight:700;color:var(--on);margin:0}

    .ovr-ld .ld-sub-manage{display:flex;flex-direction:column;gap:12px;margin-top:28px;padding-top:28px;border-top:1px solid var(--ov)}
    .ovr-ld .ld-sub-manage-h{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);margin:0 0 2px}
    .ovr-ld .ld-sub-manage-note{font-size:12px;color:var(--sv);margin:2px 0 0;line-height:1.5}
    .ovr-ld .ld-sub-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px 20px;border-radius:10px;font-family:inherit;font-size:14px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:background .18s,color .18s,box-shadow .18s}
    .ovr-ld .ld-sub-btn--primary{background:var(--p);color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.12)}
    .ovr-ld .ld-sub-btn--primary:hover{background:#003838;color:#fff}
    .ovr-ld .ld-sub-btn--secondary{background:var(--sec);color:#fff}
    .ovr-ld .ld-sub-btn--secondary:hover{background:#00513a;color:#fff}
    .ovr-ld .ld-sub-btn--ghost{background:transparent;color:var(--sv);border-color:var(--outline)}
    .ovr-ld .ld-sub-btn--ghost:hover{background:var(--sclow);color:var(--on)}
    .ovr-ld .ld-sub-btn--outline{background:transparent;color:var(--p);border-color:var(--p)}
    .ovr-ld .ld-sub-btn--outline:hover{background:rgba(0,76,76,.06)}
    .ovr-ld .ld-sub-btn--disabled{cursor:default;opacity:.6}

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
    .ovr-ld .ld-sub-plan.is-current{border-color:var(--p);background:rgba(0,76,76,.03)}
    .ovr-ld .ld-sub-pop{position:absolute;top:0;right:0;background:var(--sec);color:#fff;font-size:11px;font-weight:700;letter-spacing:.04em;padding:5px 14px;border-radius:0 0 0 12px}
    .ovr-ld .ld-sub-current-tag{position:absolute;top:0;left:0;background:var(--p);color:#fff;font-size:11px;font-weight:700;letter-spacing:.04em;padding:5px 14px;border-radius:0 0 12px 0}
    .ovr-ld .ld-sub-plan-head h4{font-size:22px;font-weight:600;color:var(--on);margin:0 0 6px}
    .ovr-ld .ld-sub-plan-desc{font-size:14px;color:var(--sv);margin:0 0 18px;line-height:1.5}
    .ovr-ld .ld-sub-plan-price{margin-bottom:22px}
    .ovr-ld .ld-sub-amt{font-size:32px;font-weight:700;color:var(--p)}
    .ovr-ld .ld-sub-per{font-size:14px;color:var(--sv)}
    .ovr-ld .ld-sub-feats{list-style:none;margin:0 0 24px;padding:0;display:flex;flex-direction:column;gap:12px;flex:1}
    .ovr-ld .ld-sub-feats li{display:flex;align-items:flex-start;gap:12px;font-size:14px;color:var(--on)}
    .ovr-ld .ld-sub-feats .material-symbols-outlined{font-size:20px;color:var(--sec);flex-shrink:0}

    /* Renew / Extend workflow (P4) */
    .ovr-ld .ld-sub-renew{background:var(--surf);border:1px solid var(--ov);border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.04);padding:32px}
    .ovr-ld .ld-sub-renew-intro{margin-bottom:24px}
    .ovr-ld .ld-sub-renew-intro .ld-sub-h3{margin:0 0 6px}
    .ovr-ld .ld-sub-renew-grid{display:grid;grid-template-columns:1fr;gap:28px}
    .ovr-ld .ld-sub-renew-lbl{display:block;font-size:13px;font-weight:600;letter-spacing:.03em;color:var(--on);margin-bottom:8px}
    .ovr-ld .ld-sub-select{width:100%;background:#fff;border:1px solid var(--ov);border-radius:10px;padding:14px 16px;font-family:inherit;font-size:16px;color:var(--on);outline:none;cursor:pointer;transition:border-color .15s,box-shadow .15s}
    .ovr-ld .ld-sub-select:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(0,76,76,.12)}
    .ovr-ld .ld-sub-renew-hint{font-size:12px;color:var(--sv);margin:8px 0 0;line-height:1.5}
    .ovr-ld .ld-sub-order{background:var(--sclow,#f6faf9);border:1px solid var(--ov);border-radius:14px;padding:22px}
    .ovr-ld .ld-sub-order-h{font-size:13px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);margin:0 0 14px}
    .ovr-ld .ld-sub-order-row{display:flex;justify-content:space-between;align-items:baseline;gap:16px;font-size:14px;color:var(--on);padding:7px 0}
    .ovr-ld .ld-sub-order-row span{color:var(--sv)}
    .ovr-ld .ld-sub-order-row strong{font-weight:700;text-align:right}
    .ovr-ld .ld-sub-order-sep{border-top:1px solid var(--ov);margin-top:8px;padding-top:14px}
    .ovr-ld .ld-sub-order-total{font-size:17px}
    .ovr-ld .ld-sub-order-total span,.ovr-ld .ld-sub-order-total strong{color:var(--on);font-weight:800}
    .ovr-ld .ld-sub-order .ld-sub-btn{margin-top:18px}
    @media (min-width:820px){
        .ovr-ld .ld-sub-renew-grid{grid-template-columns:1.1fr 1fr;align-items:start}
    }

    @media (max-width:760px){
        .ovr-ld .ld-sub-h1{font-size:26px}
        .ovr-ld .ld-sub-plans-grid{grid-template-columns:1fr}
    }
</style>
