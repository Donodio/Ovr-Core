<?php
/**
 * Checkout / Payment page.
 *
 * Order summary + payment form for a subscription plan. SECURITY: the card
 * fields are display-only (no `name`, never submitted) — only the plan, the
 * chosen gateway, and the nonce post to the existing `ovr_start_checkout`
 * handler. Card capture happens on the provider's hosted checkout (Stripe
 * Checkout / PayPal), so no card data ever reaches this site.
 *
 * @package OVR
 * @var array    $order   Normalized line item: type, eyebrow, name, sub, price, fields.
 * @var string   $symbol
 * @var float    $balance
 * @var \WP_User $user
 * @var string   $checkout_action
 * @var string   $cancel_url
 * @var string   $promo_nonce
 * @var string   $ajax_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$order     = $order ?? [];
$price     = (float) ( $order['price'] ?? 0 );
$regular   = isset( $order['regular_price'] ) ? (float) $order['regular_price'] : $price;
$eyebrow   = (string) ( $order['eyebrow'] ?? '' );
$item_name = (string) ( $order['name'] ?? '' );
$sub_lbl   = (string) ( $order['sub'] ?? '' );
$fields    = (array) ( $order['fields'] ?? [] );
$thumb     = ! empty( $order['thumb'] ) ? (string) $order['thumb'] : OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';
$fmt       = static fn( float $n ): string => $symbol . number_format( $n, 2 );
$name      = $user->display_name ?: '';
?>
<div class="ovr-wrap ovr-co">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        .ovr-co{--p:#004c4c;--pc:#006666;--sec:#006c4a;--secc:#74f7be;--err:#ba1a1a;--bg:#f7faf9;--surf:#fff;--sclow:#f1f4f3;--sv:#3f4948;--outline:#6f7979;--ov:#bec9c8;--on:#181c1c;
            font-family:'Inter',system-ui,-apple-system,sans-serif;color:var(--on);background:var(--bg)}
        .ovr-co *{box-sizing:border-box}
        .ovr-co .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle}
        .ovr-co .fill{font-variation-settings:'FILL' 1}

        .ovr-co-header{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;background:var(--surf);border-bottom:1px solid var(--ov)}
        .ovr-co-brand{display:flex;align-items:center;gap:10px;font-size:20px;font-weight:700;color:var(--p);text-decoration:none}
        .ovr-co-brand .material-symbols-outlined{font-size:26px}
        .ovr-co-cancel{display:inline-flex;align-items:center;gap:6px;color:var(--sv);font-size:14px;text-decoration:none}
        .ovr-co-cancel:hover{color:var(--p)}
        .ovr-co-cancel .material-symbols-outlined{font-size:18px}

        .ovr-co-main{max-width:1100px;margin:0 auto;padding:48px 28px 64px;display:grid;grid-template-columns:5fr 7fr;gap:56px;align-items:start}
        .ovr-co-h{font-size:30px;font-weight:700;letter-spacing:-.01em;margin:0 0 18px}

        /* Order summary */
        .ovr-co-card{background:var(--surf);border:1px solid var(--ov);border-radius:14px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,.04)}
        .ovr-co-item{display:flex;gap:20px;align-items:flex-start}
        .ovr-co-thumb{width:88px;height:88px;border-radius:10px;object-fit:cover;flex-shrink:0;border:1px solid var(--ov);background:var(--sclow)}
        .ovr-co-eyebrow{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--p);margin:0 0 4px}
        .ovr-co-item-name{font-size:21px;font-weight:600;margin:0 0 4px;line-height:1.3}
        .ovr-co-item-sub{font-size:14px;color:var(--sv);margin:0}
        .ovr-co-rule{border:none;border-top:1px solid var(--ov);margin:24px 0}
        .ovr-co-lines{display:flex;flex-direction:column;gap:14px;font-size:15px}
        .ovr-co-line{display:flex;justify-content:space-between;color:var(--sv)}
        .ovr-co-line .pos{color:var(--sec)}
        .ovr-co-total{display:flex;justify-content:space-between;align-items:flex-end}
        .ovr-co-total-lbl{font-size:12px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--sv)}
        .ovr-co-total-cur{font-size:13px;color:var(--sv)}
        .ovr-co-total-amt{font-size:30px;font-weight:700;color:var(--on)}
        .ovr-co-trust{display:flex;align-items:flex-start;gap:12px;margin-top:18px;padding:0 4px}
        .ovr-co-trust .material-symbols-outlined{color:var(--p);font-size:22px;flex-shrink:0}
        .ovr-co-trust p{font-size:12px;color:var(--sv);margin:0;line-height:1.5}

        /* Payment */
        .ovr-co-methods{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
        .ovr-co-method{background:var(--surf);border:1px solid var(--ov);border-radius:12px;padding:16px 8px;display:flex;flex-direction:column;align-items:center;gap:8px;cursor:pointer;transition:border-color .15s,background .15s;text-align:center}
        .ovr-co-method:hover{border-color:var(--outline)}
        .ovr-co-method.is-active{border:2px solid var(--p);background:rgba(0,76,76,.05)}
        .ovr-co-method .material-symbols-outlined{font-size:26px;color:var(--sv)}
        .ovr-co-method.is-active .material-symbols-outlined{color:var(--p)}
        .ovr-co-method span:last-child{font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--sv)}
        .ovr-co-method.is-active span:last-child{color:var(--p)}

        .ovr-co-field{display:flex;flex-direction:column;gap:8px;margin-bottom:18px}
        .ovr-co-label{font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--sv)}
        .ovr-co-input{width:100%;background:var(--sclow);border:1px solid var(--ov);border-radius:9px;padding:12px 14px;font-family:inherit;font-size:15px;color:var(--on);outline:none;transition:border-color .15s,box-shadow .15s}
        .ovr-co-input:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(0,76,76,.12);background:#fff}
        .ovr-co-input--icon{padding-left:44px}
        .ovr-co-inputwrap{position:relative}
        .ovr-co-inputwrap>.material-symbols-outlined{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--outline);font-size:20px}
        .ovr-co-row2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
        .ovr-co-note{font-size:13px;color:var(--sv);background:var(--sclow);border:1px solid var(--ov);border-radius:9px;padding:14px;margin-bottom:18px;display:flex;gap:10px;align-items:flex-start}
        .ovr-co-note .material-symbols-outlined{color:var(--p);font-size:20px;flex-shrink:0}

        .ovr-co-promo{display:flex;gap:12px}
        .ovr-co-promo .ovr-co-input{flex:1}
        .ovr-co-promo-btn{padding:0 22px;border:1px solid var(--ov);background:var(--surf);color:var(--sv);border-radius:9px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;transition:border-color .15s,color .15s}
        .ovr-co-promo-btn:hover{border-color:var(--p);color:var(--p)}
        .ovr-co-promo-msg{font-size:13px;margin:8px 0 0}
        .ovr-co-promo-msg.ok{color:var(--sec)}
        .ovr-co-promo-msg.err{color:var(--err)}

        .ovr-co-pay{width:100%;margin-top:24px;padding:16px;background:var(--p);color:#fff;border:none;border-radius:11px;font-family:inherit;font-size:17px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:background .18s,box-shadow .18s}
        .ovr-co-pay:hover{background:#003838;box-shadow:0 8px 20px rgba(0,76,76,.28)}
        .ovr-co-pay .material-symbols-outlined{font-size:20px}
        .ovr-co-badges{display:flex;align-items:center;justify-content:center;gap:18px;margin-top:22px;color:var(--sv);opacity:.75}
        .ovr-co-badges .b{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
        .ovr-co-badges .sep{width:1px;height:16px;background:var(--ov)}

        .ovr-co-foot{border-top:1px solid var(--ov);background:var(--surf);padding:24px 28px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}
        .ovr-co-foot p,.ovr-co-foot a{font-size:13px;color:var(--sv);margin:0;text-decoration:none}
        .ovr-co-foot a:hover{color:var(--p)}
        .ovr-co-foot-links{display:flex;gap:22px;flex-wrap:wrap}

        @media (max-width:900px){.ovr-co-main{grid-template-columns:1fr;gap:40px;padding:32px 18px 56px}}
        @media (max-width:520px){.ovr-co-methods{grid-template-columns:1fr}.ovr-co-row2{grid-template-columns:1fr}.ovr-co-h{font-size:26px}}
    </style>

    <header class="ovr-co-header">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ovr-co-brand">
            <span class="material-symbols-outlined fill">real_estate_agent</span><?php esc_html_e( 'Our Villages Rental', 'ovr-core' ); ?>
        </a>
        <a href="<?php echo esc_url( $cancel_url ); ?>" class="ovr-co-cancel">
            <span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'Cancel & return', 'ovr-core' ); ?>
        </a>
    </header>

    <main class="ovr-co-main">

        <!-- Order summary -->
        <section>
            <h1 class="ovr-co-h"><?php esc_html_e( 'Order Summary', 'ovr-core' ); ?></h1>
            <div class="ovr-co-card">
                <div class="ovr-co-item">
                    <img class="ovr-co-thumb" src="<?php echo esc_url( $thumb ); ?>" alt="">
                    <div>
                        <p class="ovr-co-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
                        <h3 class="ovr-co-item-name"><?php echo esc_html( $item_name ); ?></h3>
                        <p class="ovr-co-item-sub"><?php echo esc_html( $sub_lbl ); ?></p>
                    </div>
                </div>
                <hr class="ovr-co-rule">
                <div class="ovr-co-lines">
                    <div class="ovr-co-line"><span><?php esc_html_e( 'Subtotal (regular price)', 'ovr-core' ); ?></span><span data-co-subtotal><?php echo esc_html( $fmt( $regular ) ); ?></span></div>
                    <div class="ovr-co-line"><span><?php esc_html_e( 'Your price', 'ovr-core' ); ?></span><span class="pos"><?php echo esc_html( $fmt( $price ) ); ?></span></div>
                    <div class="ovr-co-line"><span><?php esc_html_e( 'Discount', 'ovr-core' ); ?></span><span class="pos" data-co-discount>-<?php echo esc_html( $fmt( 0 ) ); ?></span></div>
                </div>
                <hr class="ovr-co-rule">
                <div class="ovr-co-total">
                    <div>
                        <div class="ovr-co-total-lbl"><?php esc_html_e( 'Total due today', 'ovr-core' ); ?></div>
                        <div class="ovr-co-total-cur">USD</div>
                    </div>
                    <span class="ovr-co-total-amt" data-co-total><?php echo esc_html( $fmt( $price ) ); ?></span>
                </div>
            </div>
            <div class="ovr-co-trust">
                <span class="material-symbols-outlined fill">verified_user</span>
                <p><?php esc_html_e( 'Your transaction is secured with SSL encryption. We never store your full card details on our servers.', 'ovr-core' ); ?></p>
            </div>
        </section>

        <!-- Payment -->
        <section>
            <h2 class="ovr-co-h"><?php esc_html_e( 'Payment Details', 'ovr-core' ); ?></h2>

            <?php
            // Which method starts selected is decided by the server default, so
            // the pre-selected tab, the visible panel and the posted gateway can
            // never disagree with what the backend would actually charge.
            $default_gateway = isset( $default_gateway ) ? (string) $default_gateway : 'paypal';
            $methods         = [
                'stripe' => [ 'panel' => 'card',   'icon' => 'credit_card',             'label' => __( 'Credit Card', 'ovr-core' ) ],
                'paypal' => [ 'panel' => 'paypal', 'icon' => 'account_balance',         'label' => __( 'PayPal', 'ovr-core' ) ],
                'wallet' => [ 'panel' => 'wallet', 'icon' => 'account_balance_wallet',  'label' => __( 'On Account', 'ovr-core' ) ],
            ];
            if ( ! isset( $methods[ $default_gateway ] ) ) {
                $default_gateway = 'paypal';
            }
            $active_panel = $methods[ $default_gateway ]['panel'];
            ?>

            <div class="ovr-co-methods" role="tablist">
                <?php foreach ( $methods as $slug => $m ) : ?>
                    <button type="button"
                        class="ovr-co-method<?php echo $slug === $default_gateway ? ' is-active' : ''; ?>"
                        data-gateway="<?php echo esc_attr( $slug ); ?>"
                        data-panel="<?php echo esc_attr( $m['panel'] ); ?>">
                        <span class="material-symbols-outlined fill"><?php echo esc_html( $m['icon'] ); ?></span><span><?php echo esc_html( $m['label'] ); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <form method="post" action="<?php echo esc_url( $checkout_action ); ?>" id="ovr-co-form">
                <input type="hidden" name="action" value="ovr_start_checkout">
                <?php foreach ( $fields as $fk => $fv ) : ?>
                    <input type="hidden" name="<?php echo esc_attr( $fk ); ?>" value="<?php echo esc_attr( $fv ); ?>">
                <?php endforeach; ?>
                <input type="hidden" name="gateway" id="ovr-co-gateway" value="<?php echo esc_attr( $default_gateway ); ?>">
                <?php wp_nonce_field( 'ovr_checkout_action', 'ovr_checkout_nonce' ); ?>

                <div class="ovr-co-card">
                    <!-- Card panel: Stripe hosts the card form (no card data touches this site). -->
                    <div data-co-panel="card"<?php echo 'card' === $active_panel ? '' : ' hidden'; ?>>
                        <div class="ovr-co-note">
                            <span class="material-symbols-outlined">lock</span>
                            <span><?php esc_html_e( "You'll be securely redirected to Stripe to enter your card details after you place your order. Your card information is never stored on this site.", 'ovr-core' ); ?></span>
                        </div>
                    </div>

                    <!-- PayPal panel -->
                    <div data-co-panel="paypal"<?php echo 'paypal' === $active_panel ? '' : ' hidden'; ?>>
                        <div class="ovr-co-note">
                            <span class="material-symbols-outlined">open_in_new</span>
                            <span><?php esc_html_e( "You'll be securely redirected to PayPal to authorize this payment after you place your order.", 'ovr-core' ); ?></span>
                        </div>
                    </div>

                    <!-- Wallet panel -->
                    <div data-co-panel="wallet"<?php echo 'wallet' === $active_panel ? '' : ' hidden'; ?>>
                        <div class="ovr-co-note">
                            <span class="material-symbols-outlined">account_balance_wallet</span>
                            <span>
                                <?php printf( esc_html__( 'Your account balance: %s.', 'ovr-core' ), '<strong>' . esc_html( $fmt( (float) $balance ) ) . '</strong>' ); ?>
                                <?php echo (float) $balance < $price ? esc_html__( ' This is below the total — please choose another payment method.', 'ovr-core' ) : esc_html__( ' It will be charged to your account.', 'ovr-core' ); ?>
                            </span>
                        </div>
                    </div>

                    <hr class="ovr-co-rule">

                    <!-- Promo -->
                    <div class="ovr-co-field" style="margin-bottom:0">
                        <label class="ovr-co-label"><?php esc_html_e( 'Promo Code (if applicable)', 'ovr-core' ); ?></label>
                        <div class="ovr-co-promo">
                            <input class="ovr-co-input" type="text" id="ovr-co-promo" placeholder="<?php esc_attr_e( 'Enter code here', 'ovr-core' ); ?>" autocomplete="off">
                            <button type="button" class="ovr-co-promo-btn" id="ovr-co-promo-apply"><?php esc_html_e( 'Apply', 'ovr-core' ); ?></button>
                        </div>
                        <p class="ovr-co-promo-msg" id="ovr-co-promo-msg" aria-live="polite"></p>
                    </div>
                </div>

                <button type="submit" class="ovr-co-pay">
                    <span class="material-symbols-outlined">lock</span><?php esc_html_e( 'Complete Purchase', 'ovr-core' ); ?>
                </button>
            </form>

            <div class="ovr-co-badges">
                <span class="b"><span class="material-symbols-outlined">verified</span><?php esc_html_e( 'Secure Checkout', 'ovr-core' ); ?></span>
                <span class="sep"></span>
                <span class="b"><span class="material-symbols-outlined">lock</span><?php esc_html_e( '256-bit SSL', 'ovr-core' ); ?></span>
            </div>
        </section>
    </main>

    <footer class="ovr-co-foot">
        <p><?php printf( esc_html__( '© %s Our Villages Rental. All rights reserved.', 'ovr-core' ), esc_html( gmdate( 'Y' ) ) ); ?></p>
        <div class="ovr-co-foot-links">
            <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'Terms of Service', 'ovr-core' ); ?></a>
            <a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'ovr-core' ); ?></a>
        </div>
    </footer>
</div>

<script>
(function(){
    var root = document.querySelector('.ovr-co');
    if (!root) return;
    var gatewayInput = root.querySelector('#ovr-co-gateway');
    var methods = root.querySelectorAll('.ovr-co-method');
    var panels  = root.querySelectorAll('[data-co-panel]');

    methods.forEach(function(m){
        m.addEventListener('click', function(){
            methods.forEach(function(x){ x.classList.remove('is-active'); });
            m.classList.add('is-active');
            gatewayInput.value = m.getAttribute('data-gateway');
            var want = m.getAttribute('data-panel');
            panels.forEach(function(p){ p.hidden = (p.getAttribute('data-co-panel') !== want); });
        });
    });

    // Promo validation via existing AJAX endpoint (updates discount + total).
    var price   = <?php echo wp_json_encode( $price ); ?>;
    var symbol  = <?php echo wp_json_encode( $symbol ); ?>;
    var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
    var nonce   = <?php echo wp_json_encode( $promo_nonce ); ?>;
    var fmt = function(n){ return symbol + Number(n).toFixed(2); };

    var applyBtn = root.querySelector('#ovr-co-promo-apply'),
        promoIn  = root.querySelector('#ovr-co-promo'),
        msg      = root.querySelector('#ovr-co-promo-msg'),
        discEl   = root.querySelector('[data-co-discount]'),
        totalEl  = root.querySelector('[data-co-total]');

    if (applyBtn) {
        applyBtn.addEventListener('click', function(){
            var code = (promoIn.value || '').trim();
            if (!code) { msg.textContent = '<?php echo esc_js( __( 'Please enter a promo code.', 'ovr-core' ) ); ?>'; msg.className = 'ovr-co-promo-msg err'; return; }
            applyBtn.disabled = true;
            var body = new URLSearchParams({ action:'ovr_apply_promo', code:code, nonce:nonce });
            fetch(ajaxUrl, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString() })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    applyBtn.disabled = false;
                    if (res && res.success) {
                        var d = res.data, disc = d.discount_type === 'percentage' ? price * (d.discount_value/100) : d.discount_value;
                        if (disc > price) disc = price;
                        discEl.textContent = '-' + fmt(disc);
                        totalEl.textContent = fmt(price - disc);
                        msg.textContent = d.message || '<?php echo esc_js( __( 'Promo code applied!', 'ovr-core' ) ); ?>';
                        msg.className = 'ovr-co-promo-msg ok';
                    } else {
                        msg.textContent = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Invalid promo code.', 'ovr-core' ) ); ?>';
                        msg.className = 'ovr-co-promo-msg err';
                    }
                })
                .catch(function(){ applyBtn.disabled = false; msg.textContent = '<?php echo esc_js( __( 'Could not apply code. Try again.', 'ovr-core' ) ); ?>'; msg.className = 'ovr-co-promo-msg err'; });
        });
    }
})();
</script>
