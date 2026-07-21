<?php
/**
 * Payment Success / Thank-You page.
 *
 * @package OVR
 * @var string $status          'completed' | 'pending' | …
 * @var string $order_no
 * @var string $date
 * @var string $amount
 * @var bool   $is_upgrade
 * @var string $item_label
 * @var string $gateway_label
 * @var string $gateway_icon
 * @var string $listings_url
 * @var string $subscription_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$completed = ( 'completed' === $status );
$failed    = in_array( $status, [ 'failed', 'cancelled' ], true );
$thing     = $is_upgrade ? __( 'upgrade', 'ovr-core' ) : __( 'subscription', 'ovr-core' );
$retry_url = $is_upgrade ? $listings_url : $subscription_url;
?>
<div class="ovr-wrap ovr-ps">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        .ovr-ps{--p:#004c4c;--sec:#006c4a;--secc:#74f7be;--onsecc:#00714e;--ter:#735c00;--terc:#cca72f;--terfd:#ffe088;--onterc:#4e3d00;--bg:#f7faf9;--surf:#fff;--sv:#3f4948;--ov:#bec9c8;--on:#181c1c;
            font-family:'Inter',system-ui,-apple-system,sans-serif;color:var(--on);background:var(--bg)}
        .ovr-ps *{box-sizing:border-box}
        .ovr-ps .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle}
        .ovr-ps .fill{font-variation-settings:'FILL' 1}

        .ovr-ps-main{max-width:640px;margin:0 auto;padding:64px 24px 72px;display:flex;flex-direction:column;align-items:center;text-align:center;position:relative}
        .ovr-ps-glow{position:absolute;top:24px;left:50%;transform:translateX(-50%);width:260px;height:260px;border-radius:50%;filter:blur(70px);opacity:.35;z-index:0;background:var(--secc)}
        .ovr-ps-glow.is-pending{background:var(--terfd)}
        .ovr-ps-icon{position:relative;z-index:1;width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:30px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
        .ovr-ps-icon.ok{background:var(--secc)}
        .ovr-ps-icon.ok .material-symbols-outlined{color:var(--sec)}
        .ovr-ps-icon.pending{background:var(--terfd)}
        .ovr-ps-icon.pending .material-symbols-outlined{color:var(--ter)}
        .ovr-ps-icon .material-symbols-outlined{font-size:48px}
        .ovr-ps-h1{position:relative;z-index:1;font-size:40px;font-weight:700;letter-spacing:-.02em;margin:0 0 14px;line-height:1.15}
        .ovr-ps-lede{position:relative;z-index:1;font-size:17px;color:var(--sv);margin:0 0 44px;max-width:480px;line-height:1.6}

        .ovr-ps-card{position:relative;z-index:1;width:100%;background:var(--surf);border:1px solid var(--ov);border-radius:16px;padding:30px;box-shadow:0 4px 24px rgba(0,0,0,.04);text-align:left;margin-bottom:36px}
        .ovr-ps-card h2{font-size:18px;font-weight:600;margin:0 0 18px;padding:0 0 16px;border-bottom:1px solid var(--ov)}
        .ovr-ps-rows{display:flex;flex-direction:column;gap:14px}
        .ovr-ps-row{display:flex;justify-content:space-between;align-items:center;gap:16px}
        .ovr-ps-k{font-size:15px;color:var(--sv)}
        .ovr-ps-v{font-size:15px;font-weight:600;color:var(--on);text-align:right}
        .ovr-ps-v.amount{font-size:20px;font-weight:700}
        .ovr-ps-v .material-symbols-outlined{font-size:20px;color:var(--sv);margin-right:6px}
        .ovr-ps-status{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;padding:3px 10px;border-radius:9999px}
        .ovr-ps-status.ok{background:rgba(0,108,74,.12);color:var(--onsecc)}
        .ovr-ps-status.pending{background:rgba(115,92,0,.14);color:var(--onterc)}
        .ovr-ps-receipt{margin-top:24px;padding-top:20px;border-top:1px solid var(--ov);text-align:center}
        .ovr-ps-receipt button{display:inline-flex;align-items:center;gap:8px;background:none;border:none;cursor:pointer;font-family:inherit;font-size:15px;color:var(--p);font-weight:600}
        .ovr-ps-receipt button:hover{color:#003838}
        .ovr-ps-receipt .material-symbols-outlined{font-size:20px}

        .ovr-ps-actions{position:relative;z-index:1;display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
        .ovr-ps-btn{display:inline-flex;align-items:center;justify-content:center;padding:15px 30px;border-radius:11px;font-family:inherit;font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;transition:background .18s,box-shadow .18s,color .18s}
        .ovr-ps-btn--primary{background:var(--p);color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.12)}
        .ovr-ps-btn--primary:hover{background:#003838;color:#fff;box-shadow:0 8px 20px rgba(0,76,76,.25)}
        .ovr-ps-btn--outline{background:transparent;color:var(--p);border-color:var(--p)}
        .ovr-ps-btn--outline:hover{background:rgba(0,76,76,.06);color:var(--p)}

        @media (max-width:560px){
            .ovr-ps-main{padding:48px 18px 56px}
            .ovr-ps-h1{font-size:30px}
            .ovr-ps-actions{flex-direction:column;width:100%}
            .ovr-ps-actions .ovr-ps-btn{width:100%}
        }
        @media print{ .ovr-ps-actions,.ovr-ps-glow{display:none} }
    </style>

    <main class="ovr-ps-main">
        <span class="ovr-ps-glow<?php echo $completed ? '' : ' is-pending'; ?>" aria-hidden="true"></span>

        <div class="ovr-ps-icon <?php echo $completed ? 'ok' : 'pending'; ?>">
            <span class="material-symbols-outlined fill"><?php echo $completed ? 'check_circle' : ( $failed ? 'error' : 'schedule' ); ?></span>
        </div>

        <?php if ( $completed ) : ?>
            <h1 class="ovr-ps-h1"><?php esc_html_e( 'Payment Successful!', 'ovr-core' ); ?></h1>
            <p class="ovr-ps-lede">
                <?php printf( esc_html__( 'Thank you for your purchase. Your %s is now active — you can start listing your properties right away.', 'ovr-core' ), esc_html( $thing ) ); ?>
            </p>
        <?php elseif ( $failed ) : ?>
            <h1 class="ovr-ps-h1"><?php esc_html_e( 'Payment Not Completed', 'ovr-core' ); ?></h1>
            <p class="ovr-ps-lede">
                <?php printf( esc_html__( 'This payment was not completed, so you have not been charged and your %s has not changed. You can safely try again.', 'ovr-core' ), esc_html( $thing ) ); ?>
            </p>
            <?php if ( $retry_url ) : ?>
                <p class="ovr-ps-lede">
                    <a class="ovr-btn ovr-btn-primary" href="<?php echo esc_url( $retry_url ); ?>"><?php esc_html_e( 'Try again', 'ovr-core' ); ?></a>
                </p>
            <?php endif; ?>
        <?php else : ?>
            <h1 class="ovr-ps-h1"><?php esc_html_e( 'Order Received!', 'ovr-core' ); ?></h1>
            <p class="ovr-ps-lede">
                <?php printf( esc_html__( 'Thank you! Your payment has been recorded and is pending confirmation. We will activate your %s as soon as it is reviewed.', 'ovr-core' ), esc_html( $thing ) ); ?>
            </p>
        <?php endif; ?>

        <div class="ovr-ps-card">
            <h2><?php esc_html_e( 'Order Details', 'ovr-core' ); ?></h2>
            <div class="ovr-ps-rows">
                <div class="ovr-ps-row">
                    <span class="ovr-ps-k"><?php esc_html_e( 'Order #', 'ovr-core' ); ?></span>
                    <span class="ovr-ps-v"><?php echo esc_html( $order_no ); ?></span>
                </div>
                <div class="ovr-ps-row">
                    <span class="ovr-ps-k"><?php echo $is_upgrade ? esc_html__( 'Upgrade', 'ovr-core' ) : esc_html__( 'Plan', 'ovr-core' ); ?></span>
                    <span class="ovr-ps-v"><?php echo esc_html( $item_label ); ?></span>
                </div>
                <div class="ovr-ps-row">
                    <span class="ovr-ps-k"><?php esc_html_e( 'Date', 'ovr-core' ); ?></span>
                    <span class="ovr-ps-v"><?php echo esc_html( $date ); ?></span>
                </div>
                <div class="ovr-ps-row">
                    <span class="ovr-ps-k"><?php esc_html_e( 'Amount', 'ovr-core' ); ?></span>
                    <span class="ovr-ps-v amount"><?php echo esc_html( $amount ); ?></span>
                </div>
                <div class="ovr-ps-row">
                    <span class="ovr-ps-k"><?php esc_html_e( 'Payment Method', 'ovr-core' ); ?></span>
                    <span class="ovr-ps-v"><span class="material-symbols-outlined"><?php echo esc_html( $gateway_icon ); ?></span><?php echo esc_html( $gateway_label ); ?></span>
                </div>
                <div class="ovr-ps-row">
                    <span class="ovr-ps-k"><?php esc_html_e( 'Status', 'ovr-core' ); ?></span>
                    <span class="ovr-ps-v">
                        <span class="ovr-ps-status <?php echo $completed ? 'ok' : 'pending'; ?>">
                            <span class="material-symbols-outlined fill" style="font-size:14px"><?php echo $completed ? 'check_circle' : 'schedule'; ?></span>
                            <?php echo $completed ? esc_html__( 'Paid', 'ovr-core' ) : esc_html__( 'Pending', 'ovr-core' ); ?>
                        </span>
                    </span>
                </div>
            </div>
            <div class="ovr-ps-receipt">
                <button type="button" onclick="window.print()">
                    <span class="material-symbols-outlined">print</span><?php esc_html_e( 'Print / Save Receipt', 'ovr-core' ); ?>
                </button>
            </div>
        </div>

        <div class="ovr-ps-actions">
            <a href="<?php echo esc_url( $listings_url ); ?>" class="ovr-ps-btn ovr-ps-btn--primary"><?php esc_html_e( 'Go to My Listings', 'ovr-core' ); ?></a>
            <a href="<?php echo esc_url( $subscription_url ); ?>" class="ovr-ps-btn ovr-ps-btn--outline"><?php esc_html_e( 'Manage Subscription', 'ovr-core' ); ?></a>
        </div>
    </main>
</div>
