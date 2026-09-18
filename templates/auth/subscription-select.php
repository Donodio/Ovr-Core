<?php
/**
 * Subscription Activation / Renewal / Extension screen.
 *
 * Unified plan selection, promo, summary, payment method, and purchase
 * initiation in one page. On submit the server rebuilds the authoritative
 * offer from plan + promo + context, then routes to the existing checkout
 * or completion flow.
 *
 * @package OVR
 * @var \WP_User $user
 * @var array    $plans         Paid, active plans keyed by slug.
 * @var string   $continue_url  admin-post.php (ovr_continue_checkout)
 * @var string   $checkout_url
 * @var string   $logout_url
 * @var bool     $is_expired
 * @var bool     $is_pending
 * @var string   $context       'new' | 'renewal'
 * @var string   $nonce
 * @var string   $public_nonce
 * @var string   $ajax_url
 * @var string   $symbol
 * @var string   $origin        'activation' | 'renewal' | 'dashboard'
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Subscription\SubscriptionOffer;

$plans       = $plans ?? [];
$is_expired  = ! empty( $is_expired );
$is_pending  = ! empty( $is_pending );
$context     = ( 'renewal' === ( $context ?? 'new' ) ) ? 'renewal' : 'new';
$origin      = sanitize_key( $origin ?? ( $is_expired ? 'renewal' : 'activation' ) );
$first_name  = $user->first_name ?: $user->display_name;
$cancel_url  = ! empty( $cancel_url ) ? $cancel_url : ( 'renewal' === $origin ? add_query_arg( 'tab', 'subscription', Pages::get_page_url( 'ovr_page_dashboard' ) ) : Pages::get_page_url( 'ovr_page_login' ) );

$period_days = static function ( string $p ): int {
    return SubscriptionOffer::canonical_duration_days( $p );
};
$duration_label = static function ( int $days ): string {
    /* translators: %d: number of days */
    return sprintf( _n( '%d day', '%d days', $days, 'ovr-core' ), $days );
};

$default_gateway = 'authorize_net';
$methods = [
    'authorize_net' => [ 'panel' => 'card',   'icon' => 'credit_card',     'label' => __( 'Credit Card', 'ovr-core' ) ],
    'paypal'        => [ 'panel' => 'paypal', 'icon' => 'account_balance', 'label' => __( 'PayPal', 'ovr-core' ) ],
];
$active_panel = $methods[ $default_gateway ]['panel'];
?>
<div class="ovr-subsel">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        .ovr-subsel{--p:#004c4c;--pc:#006666;--sec:#006c4a;--secc:#74f7be;--ter:#735c00;--terc:#cca72f;--bg:#f7faf9;--surf:#fff;--sclow:#f1f4f3;--sv:#3f4948;--outline:#6f7979;--ov:#bec9c8;--on:#181c1c;
            font-family:'Inter',system-ui,-apple-system,sans-serif;color:var(--on);background:var(--bg);padding:48px 20px;min-height:70vh}
        .ovr-subsel *{box-sizing:border-box}
        .ovr-subsel .screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0;padding:0;margin:-1px}
        .ovr-subsel-inner{max-width:960px;margin:0 auto}
        .ovr-subsel-head{text-align:center;margin-bottom:36px}
        .ovr-subsel-head h1{font-size:34px;font-weight:700;letter-spacing:-.01em;color:var(--p);margin:0 0 10px}
        .ovr-subsel-head p{font-size:17px;color:var(--sv);margin:0;line-height:1.6}
        .ovr-subsel-banner{display:flex;align-items:center;gap:12px;max-width:680px;margin:0 auto 28px;background:var(--terc);color:#4e3d00;border:1px solid rgba(115,92,0,.3);border-radius:12px;padding:14px 18px;font-size:15px;font-weight:600}
        .ovr-subsel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:28px}
        .ovr-subsel-card{position:relative;display:flex;flex-direction:column}
        .ovr-subsel-card input[type=radio]{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
        .ovr-subsel-card label.ovr-subsel-label{background:var(--surf);border:2px solid var(--ov);border-radius:16px;padding:28px 24px;display:flex;flex-direction:column;box-shadow:0 4px 24px rgba(0,0,0,.04);cursor:pointer;height:100%;transition:border-color .15s,box-shadow .15s}
        .ovr-subsel-card.is-popular label.ovr-subsel-label{border-color:var(--sec)}
        .ovr-subsel-card input[type=radio]:checked + label.ovr-subsel-label{border-color:var(--p);box-shadow:0 0 0 3px rgba(0,76,76,.18)}
        .ovr-subsel-card input[type=radio]:focus-visible + label.ovr-subsel-label{outline:3px solid var(--pc);outline-offset:2px}
        .ovr-subsel-pop{position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--sec);color:#fff;font-size:12px;font-weight:700;letter-spacing:.03em;padding:5px 16px;border-radius:9999px;white-space:nowrap;z-index:1}
        .ovr-subsel-sel{position:absolute;top:14px;right:16px;color:var(--p);font-size:22px;opacity:0;transition:opacity .15s}
        .ovr-subsel-card input[type=radio]:checked + label.ovr-subsel-label .ovr-subsel-sel{opacity:1}
        .ovr-subsel-name{font-size:21px;font-weight:700;color:var(--on);margin:6px 0 4px}
        .ovr-subsel-desc{font-size:14px;color:var(--sv);margin:0 0 18px;line-height:1.5;min-height:42px}
        .ovr-subsel-price{margin-bottom:18px}
        .ovr-subsel-amt{font-size:38px;font-weight:700;color:var(--p)}
        .ovr-subsel-per{font-size:15px;color:var(--sv)}
        .ovr-subsel-dur{font-size:13px;color:var(--sv);margin-top:4px}
        .ovr-subsel-feats{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:11px;flex:1}
        .ovr-subsel-feats li{display:flex;align-items:flex-start;gap:10px;font-size:14.5px;color:var(--on)}
        .ovr-subsel-feats .material-symbols-outlined{font-size:20px;color:var(--sec);flex-shrink:0}
        .ovr-subsel-promo{margin:8px 0 28px;padding:22px;background:var(--surf);border:1px solid var(--ov);border-radius:14px}
        .ovr-subsel-promo h2{font-size:16px;margin:0 0 12px;color:var(--on)}
        .ovr-subsel-promo-row{display:flex;gap:10px;flex-wrap:wrap}
        .ovr-subsel-promo-row input{flex:1 1 200px;min-width:180px;border:1px solid var(--ov);border-radius:10px;padding:13px 14px;font-family:inherit;font-size:15px;text-transform:uppercase;background:var(--sclow);color:var(--on)}
        .ovr-subsel-promo-row input:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(0,76,76,.12);background:#fff}
        .ovr-subsel-btn{display:inline-block;text-align:center;padding:14px 20px;border-radius:11px;font-size:15px;font-weight:700;text-decoration:none;border:2px solid var(--p);cursor:pointer;font-family:inherit;background:var(--p);color:#fff;transition:background .18s,color .18s}
        .ovr-subsel-btn:hover{background:#003838;color:#fff}
        .ovr-subsel-btn--ghost{background:#fff;color:var(--p)}
        .ovr-subsel-btn--ghost:hover{background:rgba(0,76,76,.06);color:var(--p)}
        .ovr-subsel-btn--lg{width:100%;padding:17px 22px;font-size:17px}
        .ovr-subsel-promo-msg{margin-top:10px;font-size:14px;min-height:18px}
        .ovr-subsel-promo-msg.ok{color:var(--sec)}
        .ovr-subsel-promo-msg.err{color:#ba1a1a}
        .ovr-subsel-summary{background:var(--surf);border:2px solid var(--p);border-radius:16px;padding:26px}
        .ovr-subsel-summary h2{font-size:20px;margin:0 0 16px;color:var(--p)}
        .ovr-subsel-line{display:flex;justify-content:space-between;padding:9px 0;font-size:15px;color:var(--sv);border-bottom:1px solid var(--sclow)}
        .ovr-subsel-line:last-of-type{border-bottom:none}
        .ovr-subsel-line strong{color:var(--on)}
        .ovr-subsel-line .pos{color:var(--sec);font-weight:600}
        .ovr-subsel-total{display:flex;justify-content:space-between;align-items:center;margin:16px 0 20px;padding-top:14px;border-top:2px solid var(--p)}
        .ovr-subsel-total-lbl{font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv)}
        .ovr-subsel-total-amt{font-size:32px;font-weight:700;color:var(--on)}
        .ovr-subsel-promo-tag{display:inline-flex;align-items:center;gap:6px;background:var(--secc);color:#00714e;font-size:13px;font-weight:700;padding:4px 12px;border-radius:9999px}
        .ovr-subsel-foot{text-align:center;font-size:14px;color:var(--sv);margin-top:26px}
        .ovr-subsel-foot a{color:var(--pc);font-weight:600;text-decoration:none}
        .ovr-subsel-foot a:hover{text-decoration:underline}
        .ovr-subsel-method.is-selected{border-color:var(--p);box-shadow:0 0 0 3px rgba(0,76,76,.18)}
        @media (max-width:600px){.ovr-subsel{padding:32px 14px}.ovr-subsel-head h1{font-size:27px}.ovr-subsel-promo-row{flex-direction:column}.ovr-subsel-promo-row .ovr-subsel-btn{width:100%}}
    </style>

    <div class="ovr-subsel-inner">

        <?php if ( $is_pending ) : ?>
            <div class="ovr-subsel-banner" style="background:var(--terc)">
                <span class="material-symbols-outlined" aria-hidden="true">hourglass_top</span>
                <span><?php esc_html_e( 'You have a payment pending. Once confirmed, your subscription will activate and you can access your dashboard.', 'ovr-core' ); ?></span>
            </div>
        <?php elseif ( $is_expired ) : ?>
            <div class="ovr-subsel-banner">
                <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                <span><?php esc_html_e( 'Your subscription has expired. Subscription requires payment for activation or renewal. Choose a plan below to restore access to your dashboard and listings.', 'ovr-core' ); ?></span>
            </div>
        <?php else : ?>
            <div class="ovr-subsel-banner">
                <span class="material-symbols-outlined" aria-hidden="true">info</span>
                <span><?php esc_html_e( 'Subscription requires payment for activation or renewal. Choose a plan below to activate your landlord dashboard and start listing properties.', 'ovr-core' ); ?></span>
            </div>
        <?php endif; ?>

        <div class="ovr-subsel-head">
            <?php if ( $is_expired ) : ?>
                <h1><?php printf( esc_html__( 'Renew your subscription, %s', 'ovr-core' ), esc_html( $first_name ) ); ?></h1>
                <p><?php esc_html_e( 'Choose a plan to renew. Your new term is added after payment is confirmed.', 'ovr-core' ); ?></p>
            <?php else : ?>
                <h1><?php printf( esc_html__( 'Select your subscription, %s', 'ovr-core' ), esc_html( $first_name ) ); ?></h1>
                <p><?php esc_html_e( 'An active subscription is required to access your landlord dashboard and publish listings. Pick a plan below to continue to secure payment.', 'ovr-core' ); ?></p>
            <?php endif; ?>
        </div>

        <?php if ( empty( $plans ) ) : ?>
            <p style="text-align:center;color:var(--sv)"><?php esc_html_e( 'No subscription plans are available right now. Please contact support.', 'ovr-core' ); ?></p>
        <?php else : ?>
        <form method="post" action="<?php echo esc_url( $continue_url ); ?>" id="ovr-subsel-form">
            <input type="hidden" name="action" value="ovr_continue_checkout">
            <input type="hidden" name="context" value="<?php echo esc_attr( $context ); ?>">
            <input type="hidden" name="origin" value="<?php echo esc_attr( $origin ); ?>">
            <input type="hidden" name="gateway" id="ovr-subsel-gateway" value="<?php echo esc_attr( $default_gateway ); ?>">
            <?php wp_nonce_field( 'ovr_continue_checkout', 'ovr_continue_nonce' ); ?>

            <fieldset style="border:none;margin:0;padding:0">
                <legend class="screen-reader-text"><?php esc_html_e( 'Choose a subscription plan', 'ovr-core' ); ?></legend>
                <div class="ovr-subsel-grid">
                    <?php
                    $preselected = $preselected ?? '';
                    $first = true; foreach ( $plans as $slug => $plan ) :
                        $popular = ! empty( $plan['is_popular'] );
                        $days    = $period_days( (string) ( $plan['period'] ?? 'annually' ) );
                        $pid     = 'ovr-plan-' . sanitize_html_class( (string) $slug );
                        $is_checked = '' !== $preselected ? ( $preselected === (string) $slug ) : $first;
                    ?>
                        <div class="ovr-subsel-card<?php echo $popular ? ' is-popular' : ''; ?>">
                            <?php if ( $popular ) : ?>
                                <span class="ovr-subsel-pop"><?php esc_html_e( 'Most Popular', 'ovr-core' ); ?></span>
                            <?php endif; ?>
                            <input type="radio" name="plan" id="<?php echo esc_attr( $pid ); ?>" value="<?php echo esc_attr( (string) $slug ); ?>"
                                   data-price="<?php echo esc_attr( (string) (float) ( $plan['price'] ?? 0 ) ); ?>"
                                   data-days="<?php echo esc_attr( (string) $days ); ?>"
                                   <?php checked( $is_checked ); ?> required>
                            <label class="ovr-subsel-label" for="<?php echo esc_attr( $pid ); ?>">
                                <span class="material-symbols-outlined ovr-subsel-sel" aria-hidden="true">check_circle</span>
                                <h2 class="ovr-subsel-name"><?php echo esc_html( $plan['name'] ?? '' ); ?></h2>
                                <p class="ovr-subsel-desc"><?php echo esc_html( $plan['description'] ?? '' ); ?></p>
                                <div class="ovr-subsel-price">
                                    <span class="ovr-subsel-amt"><?php echo esc_html( $symbol . number_format( (float) ( $plan['price'] ?? 0 ), 2 ) ); ?></span>
                                    <span class="ovr-subsel-per"><?php echo 'monthly' === ( $plan['period'] ?? '' ) ? esc_html__( '/ month', 'ovr-core' ) : esc_html__( '/ year', 'ovr-core' ); ?></span>
                                    <div class="ovr-subsel-dur"><?php echo esc_html( $duration_label( $days ) ); ?></div>
                                </div>
                                <ul class="ovr-subsel-feats">
                                    <?php foreach ( (array) ( $plan['features'] ?? [] ) as $f ) : ?>
                                        <li><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span><?php echo esc_html( $f ); ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                            </label>
                        </div>
                    <?php $first = false; endforeach; ?>
                </div>
            </fieldset>

            <div class="ovr-subsel-promo">
                <h2><?php esc_html_e( 'Promo code', 'ovr-core' ); ?></h2>
                <div class="ovr-subsel-promo-row">
                    <label class="screen-reader-text" for="ovr-subsel-promo"><?php esc_html_e( 'Promo code', 'ovr-core' ); ?></label>
                    <input type="text" name="promo_code" id="ovr-subsel-promo" autocomplete="off" placeholder="<?php esc_attr_e( 'Enter promo code', 'ovr-core' ); ?>">
                    <button type="button" class="ovr-subsel-btn ovr-subsel-btn--ghost" id="ovr-subsel-promo-apply"><?php esc_html_e( 'Apply', 'ovr-core' ); ?></button>
                    <button type="button" class="ovr-subsel-btn ovr-subsel-btn--ghost" id="ovr-subsel-promo-remove" hidden><?php esc_html_e( 'Remove', 'ovr-core' ); ?></button>
                </div>
                <div class="ovr-subsel-promo-msg" id="ovr-subsel-promo-msg" role="status" aria-live="polite"></div>
            </div>

            <div class="ovr-subsel-summary" aria-live="polite">
                <h2><?php esc_html_e( 'Your subscription summary', 'ovr-core' ); ?></h2>
                <div class="ovr-subsel-line"><span><?php esc_html_e( 'Selected plan', 'ovr-core' ); ?></span><strong data-sum-plan>—</strong></div>
                <div class="ovr-subsel-line"><span><?php esc_html_e( 'Base price', 'ovr-core' ); ?></span><span data-sum-base-price>—</span></div>
                <div class="ovr-subsel-line"><span><?php esc_html_e( 'Base duration', 'ovr-core' ); ?></span><span data-sum-base-days>—</span></div>
                <div class="ovr-subsel-line" data-sum-promo-row hidden><span><?php esc_html_e( 'Promo', 'ovr-core' ); ?></span><span class="pos" data-sum-promo>—</span></div>
                <div class="ovr-subsel-total">
                    <div>
                        <div class="ovr-subsel-total-lbl"><?php esc_html_e( 'Total due today', 'ovr-core' ); ?></div>
                        <div style="font-size:12px;color:var(--sv)" data-sum-final-days>—</div>
                    </div>
                    <span class="ovr-subsel-total-amt" data-sum-final-price>—</span>
                </div>
                <div class="ovr-subsel-total">
                    <div>
                        <div class="ovr-subsel-total-lbl"><?php esc_html_e( 'Total due today', 'ovr-core' ); ?></div>
                        <div style="font-size:12px;color:var(--sv)" data-sum-final-days>—</div>
                    </div>
                    <span class="ovr-subsel-total-amt" data-sum-final-price>—</span>
                </div>
            </div>

            <fieldset style="border:none;margin:0 0 28px;padding:0" class="ovr-subsel-methods">
                <legend class="screen-reader-text"><?php esc_html_e( 'Select Method of Payment', 'ovr-core' ); ?></legend>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <?php foreach ( $methods as $slug => $m ) : ?>
                        <label style="flex:1 1 220px;display:flex;align-items:center;gap:10px;background:var(--surf);border:2px solid var(--ov);border-radius:14px;padding:16px 18px;cursor:pointer" class="ovr-subsel-method">
                            <input type="radio" name="gateway" value="<?php echo esc_attr( $slug ); ?>" data-panel="<?php echo esc_attr( $m['panel'] ); ?>" <?php checked( $default_gateway, $slug ); ?>>
                            <span class="material-symbols-outlined" aria-hidden="true"><?php echo esc_html( $m['icon'] ); ?></span>
                            <span style="font-weight:600"><?php echo esc_html( $m['label'] ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <button type="submit" class="ovr-subsel-btn ovr-subsel-btn--lg" id="ovr-subsel-continue" style="flex:1 1 280px"><?php esc_html_e( 'Enter Payment', 'ovr-core' ); ?></button>
                <a href="<?php echo esc_url( $cancel_url ); ?>" class="ovr-subsel-btn ovr-subsel-btn--ghost ovr-subsel-btn--lg" style="flex:1 1 280px;text-align:center"><?php esc_html_e( 'Cancel Purchase', 'ovr-core' ); ?></a>
            </div>
        </form>

        <script>
        (function(){
            var root = document.querySelector('.ovr-subsel');
            if (!root) return;
            var form   = document.getElementById('ovr-subsel-form');
            if (!form) return;
            var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
            var nonce   = <?php echo wp_json_encode( $public_nonce ); ?>;
            var context = <?php echo wp_json_encode( $context ); ?>;
            var gatewayField = document.getElementById('ovr-subsel-gateway');
            var methodRadios = form.querySelectorAll('input[name="gateway"]');
            var methodLabels = form.querySelectorAll('.ovr-subsel-method');
            var msg     = document.getElementById('ovr-subsel-promo-msg');
            var promo   = document.getElementById('ovr-subsel-promo');
            var applyBtn= document.getElementById('ovr-subsel-promo-apply');
            var removeBtn=document.getElementById('ovr-subsel-promo-remove');
            var radios  = form.querySelectorAll('input[name="plan"]');

            function sel(){ for (var i=0;i<radios.length;i++){ if (radios[i].checked) return radios[i]; } return null; }
            function setText(sel, txt){ var e=root.querySelector(sel); if(e){ e.textContent = txt; } }

            function syncGateway(){
                var checked = form.querySelector('input[name="gateway"]:checked');
                if (checked && gatewayField) { gatewayField.value = checked.value; }
                if (methodLabels && methodRadios) {
                    for (var i=0;i<methodRadios.length;i++){
                        var lbl = methodLabels[i];
                        if (lbl) { lbl.classList.toggle('is-selected', methodRadios[i].checked); }
                    }
                }
            }
            if (methodRadios && methodRadios.length) {
                for (var i=0;i<methodRadios.length;i++){ methodRadios[i].addEventListener('change', syncGateway); }
                syncGateway();
            }

            function render(data){
                setText('[data-sum-plan]', data.plan_name);
                setText('[data-sum-base-price]', data.base_price_display);
                setText('[data-sum-base-days]', data.base_duration_days + ' days');
                setText('[data-sum-final-price]', data.final_price_display);
                setText('[data-sum-final-days]', data.final_duration_days + ' days');
                var row = root.querySelector('[data-sum-promo-row]');
                if (data.promo_applied) {
                    row.hidden = false;
                    setText('[data-sum-promo]', data.promo_code);
                    if(removeBtn){ removeBtn.hidden = false; }
                } else {
                    row.hidden = true;
                    if(removeBtn){ removeBtn.hidden = true; }
                }
            }

            function load(showPromoMsg){
                var r = sel();
                if (!r) return;
                var body = new URLSearchParams({ action:'ovr_subscription_offer', nonce:nonce, plan:r.value, context:context, promo_code:(promo && promo.value ? promo.value.trim() : '') });
                if (applyBtn) applyBtn.disabled = true;
                fetch(ajaxUrl, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString() })
                    .then(function(resp){ return resp.json(); })
                    .then(function(res){
                        if (applyBtn) applyBtn.disabled = false;
                        if (!res || !res.success) {
                            if (showPromoMsg && msg) { msg.textContent = (res && res.data && res.data.message) ? res.data.message : <?php echo wp_json_encode( __( 'Could not load your offer. Please try again.', 'ovr-core' ) ); ?>; msg.className='ovr-subsel-promo-msg err'; }
                            return;
                        }
                        var d = res.data;
                        render(d);
                        if (msg) {
                            if (showPromoMsg) {
                                if (d.promo_applied) { msg.textContent = <?php echo wp_json_encode( __( 'Promo code applied.', 'ovr-core' ) ); ?>; msg.className='ovr-subsel-promo-msg ok'; }
                                else if (d.promo_error) { msg.textContent = d.promo_error; msg.className='ovr-subsel-promo-msg err'; }
                                else { msg.textContent = ''; msg.className='ovr-subsel-promo-msg'; }
                            } else if (!d.promo_applied) { msg.textContent=''; msg.className='ovr-subsel-promo-msg'; }
                        }
                    })
                    .catch(function(){ if (applyBtn) applyBtn.disabled = false; });
            }

            for (var i=0;i<radios.length;i++){ radios[i].addEventListener('change', function(){ load(false); }); }
            if (applyBtn) { applyBtn.addEventListener('click', function(){ load(true); }); }
            if (removeBtn) { removeBtn.addEventListener('click', function(){ if(promo){ promo.value=''; } load(false); }); }
            form.addEventListener('submit', function(e){
                if (!sel()) { e.preventDefault(); return; }
                var btn = document.getElementById('ovr-subsel-continue');
                if (btn) { btn.disabled = true; btn.textContent = <?php echo wp_json_encode( __( 'Preparing…', 'ovr-core' ) ); ?>; }
            });
            load(false);
        })();
        </script>
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
