<?php
/**
 * Membership dashboard — plan breakdown + Loyalty settings (Feature 10).
 *
 * @package OVR
 * @var array  $plans           Per-plan breakdown.
 * @var array  $stats           plans/members/expiring counts.
 * @var array  $loyalty         Resolved loyalty settings.
 * @var array  $loyalty_totals  points_outstanding/credit_issued/members.
 * @var string $currency_symbol
 * @var string $plans_url        Pricing Plans admin URL.
 * @var string $action_url
 * @var string $loyalty_nonce
 * @var array|null $notice
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$sym = $currency_symbol;
?>
<div class="wrap ovr-mem">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}#wpcontent{padding-left:0}
        .ovr-mem{--navy:#000961;--blue:#00A2E8;--blue-light:#e5f5fe;--navy-light:#e8eaf3;--gold:#DEAF0C;--gold-dark:#b8920a;--gold-light:#fef5d6;--green:#2E7D32;--green-light:#e4f4e4;--purple:#6A3FB8;--purple-light:#eee6fb;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--shadow-sm:0 1px 3px rgba(0,9,97,.06);--shadow-md:0 4px 12px rgba(0,9,97,.08);--r-sm:6px;--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink)}
        .ovr-mem,.ovr-mem *{box-sizing:border-box}
        .ovr-mem .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:22px}
        .ovr-mem-wrap{padding:24px 40px 48px}
        .ovr-mem-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:20px}
        .ovr-mem-head h1{font-size:30px;font-weight:700;margin:0;line-height:1.2}
        .ovr-mem-head p{margin:6px 0 0;font-size:16px;color:var(--muted)}
        .ovr-mem-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 22px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:46px}
        .ovr-mem-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold)}.ovr-mem-btn--primary:hover{background:var(--gold-dark)}
        .ovr-mem-btn--ghost{background:var(--surf);color:var(--navy);border-color:var(--gray-border);box-shadow:var(--shadow-sm)}.ovr-mem-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-mem-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        .ovr-mem-stat{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);padding:22px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-md);position:relative;overflow:hidden}
        .ovr-mem-stat::before{content:'';position:absolute;top:0;left:0;width:100%;height:3px}
        .ovr-mem-stat:nth-child(1)::before{background:var(--navy)}.ovr-mem-stat:nth-child(1) .ovr-mem-stat-ic{background:var(--navy-light);color:var(--navy)}
        .ovr-mem-stat:nth-child(2)::before{background:var(--green)}.ovr-mem-stat:nth-child(2) .ovr-mem-stat-ic{background:var(--green-light);color:var(--green)}
        .ovr-mem-stat:nth-child(3)::before{background:var(--gold)}.ovr-mem-stat:nth-child(3) .ovr-mem-stat-ic{background:var(--gold-light);color:var(--gold-dark)}
        .ovr-mem-stat:nth-child(4)::before{background:var(--purple)}.ovr-mem-stat:nth-child(4) .ovr-mem-stat-ic{background:var(--purple-light);color:var(--purple)}
        .ovr-mem-stat-ic{width:50px;height:50px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .ovr-mem-stat-ic .material-symbols-outlined{font-size:26px}
        .ovr-mem-stat-v{font-size:30px;font-weight:700;line-height:1;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
        .ovr-mem-stat-l{font-size:14px;color:var(--muted);margin-top:4px}
        .ovr-mem-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:start}
        .ovr-mem-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);overflow:hidden}
        .ovr-mem-card-h{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid var(--gray-border)}
        .ovr-mem-card-h h2{font-size:17px;font-weight:700;margin:0}
        .ovr-mem-table{width:100%;border-collapse:collapse}
        .ovr-mem-table th{text-align:left;padding:12px 22px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);background:#f8f9fb;border-bottom:2px solid var(--gray-border)}
        .ovr-mem-table td{padding:14px 22px;font-size:15px;border-bottom:1px solid var(--gray-border)}
        .ovr-mem-table tr:last-child td{border-bottom:none}
        .ovr-mem-pname{font-weight:600}
        .ovr-mem-num{font-variant-numeric:tabular-nums}
        .ovr-mem-field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
        .ovr-mem-field label{font-size:13px;font-weight:700;color:var(--ink)}
        .ovr-mem-field .hint{font-size:12px;color:var(--muted)}
        .ovr-mem-field input[type=number],.ovr-mem-field input[type=text]{width:100%;border:1px solid var(--gray-border);border-radius:var(--r-md);padding:10px 12px;font-size:15px;font-family:inherit;background:#fff}
        .ovr-mem-check{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--gray-border);border-radius:var(--r-md);background:var(--gray-light);margin-bottom:16px}
        .ovr-mem-check input{width:18px;height:18px}
        .ovr-mem-loyalty-totals{display:flex;gap:16px;padding:14px 22px;background:#f8f9fb;border-bottom:1px solid var(--gray-border);font-size:13px;color:var(--muted)}
        .ovr-mem-loyalty-totals b{display:block;font-size:18px;color:var(--ink);font-variant-numeric:tabular-nums}
        .ovr-mem-body{padding:22px}
        .ovr-mem-notice{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--r-md);font-size:15px;font-weight:500;margin-bottom:18px;background:var(--green-light);border:1px solid #b8d8b8;color:var(--green)}
        @media(max-width:1100px){.ovr-mem-stats{grid-template-columns:repeat(2,1fr)}.ovr-mem-grid{grid-template-columns:1fr}}
        @media(max-width:782px){.ovr-mem-wrap{padding:18px 14px 32px}.ovr-mem-stats{grid-template-columns:1fr 1fr}}
    </style>

    <div class="ovr-mem-wrap">
        <div class="ovr-mem-head">
            <div>
                <h1><?php esc_html_e( 'Membership', 'ovr-core' ); ?></h1>
                <p><?php esc_html_e( 'Plan adoption, renewals and the loyalty programme.', 'ovr-core' ); ?></p>
            </div>
            <a href="<?php echo esc_url( $plans_url ); ?>" class="ovr-mem-btn ovr-mem-btn--primary"><span class="material-symbols-outlined">tune</span><?php esc_html_e( 'Manage Plans', 'ovr-core' ); ?></a>
        </div>

        <?php if ( $notice ) : ?>
            <div class="ovr-mem-notice"><span class="material-symbols-outlined">check_circle</span><span><?php echo esc_html( $notice['text'] ); ?></span></div>
        <?php endif; ?>

        <div class="ovr-mem-stats">
            <div class="ovr-mem-stat">
                <div class="ovr-mem-stat-ic"><span class="material-symbols-outlined">workspace_premium</span></div>
                <div><div class="ovr-mem-stat-v"><?php echo esc_html( number_format_i18n( $stats['plans'] ) ); ?></div><div class="ovr-mem-stat-l"><?php esc_html_e( 'Active Plans', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-mem-stat">
                <div class="ovr-mem-stat-ic"><span class="material-symbols-outlined">group</span></div>
                <div><div class="ovr-mem-stat-v"><?php echo esc_html( number_format_i18n( $stats['members'] ) ); ?></div><div class="ovr-mem-stat-l"><?php esc_html_e( 'Paying Members', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-mem-stat">
                <div class="ovr-mem-stat-ic"><span class="material-symbols-outlined">event_upcoming</span></div>
                <div><div class="ovr-mem-stat-v"><?php echo esc_html( number_format_i18n( $stats['expiring'] ) ); ?></div><div class="ovr-mem-stat-l"><?php esc_html_e( 'Expiring (30 days)', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-mem-stat">
                <div class="ovr-mem-stat-ic"><span class="material-symbols-outlined">loyalty</span></div>
                <div><div class="ovr-mem-stat-v"><?php echo esc_html( number_format_i18n( $loyalty_totals['points_outstanding'] ) ); ?></div><div class="ovr-mem-stat-l"><?php esc_html_e( 'Loyalty Points Out', 'ovr-core' ); ?></div></div>
            </div>
        </div>

        <div class="ovr-mem-grid">
            <div class="ovr-mem-card">
                <div class="ovr-mem-card-h">
                    <h2><?php esc_html_e( 'Plans & Members', 'ovr-core' ); ?></h2>
                    <a href="<?php echo esc_url( $plans_url ); ?>" class="ovr-mem-btn ovr-mem-btn--ghost" style="min-height:38px;padding:0 14px;font-size:13px"><span class="material-symbols-outlined" style="font-size:18px">add</span><?php esc_html_e( 'Add / Edit', 'ovr-core' ); ?></a>
                </div>
                <table class="ovr-mem-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Plan', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Price', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Max Listings', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Members', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $plans as $p ) : ?>
                            <tr>
                                <td class="ovr-mem-pname"><?php echo esc_html( $p['name'] ); ?></td>
                                <td class="ovr-mem-num"><?php echo esc_html( $sym . number_format_i18n( (float) $p['price'], 2 ) ); ?><?php echo $p['period'] ? '<span style="color:var(--gray-mid);font-size:13px"> / ' . esc_html( $p['period'] ) . '</span>' : ''; ?></td>
                                <td class="ovr-mem-num"><?php echo (int) $p['max_listings'] < 0 ? '&infin;' : esc_html( number_format_i18n( (int) $p['max_listings'] ) ); ?></td>
                                <td class="ovr-mem-num"><?php echo esc_html( number_format_i18n( (int) $p['members'] ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="ovr-mem-card">
                <div class="ovr-mem-card-h"><h2><?php esc_html_e( 'Loyalty Settings', 'ovr-core' ); ?></h2></div>
                <div class="ovr-mem-loyalty-totals">
                    <div><b><?php echo esc_html( number_format_i18n( $loyalty_totals['points_outstanding'] ) ); ?></b><?php esc_html_e( 'points outstanding', 'ovr-core' ); ?></div>
                    <div><b><?php echo esc_html( $sym . number_format_i18n( (float) $loyalty_totals['credit_issued'], 0 ) ); ?></b><?php esc_html_e( 'credit issued', 'ovr-core' ); ?></div>
                    <div><b><?php echo esc_html( number_format_i18n( $loyalty_totals['members'] ) ); ?></b><?php esc_html_e( 'participants', 'ovr-core' ); ?></div>
                </div>
                <div class="ovr-mem-body">
                    <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                        <input type="hidden" name="action" value="ovr_save_loyalty">
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $loyalty_nonce ); ?>">
                        <label class="ovr-mem-check">
                            <input type="checkbox" name="loyalty_enabled" value="1" <?php checked( ! empty( $loyalty['loyalty_enabled'] ) ); ?>>
                            <span><?php esc_html_e( 'Enable loyalty programme', 'ovr-core' ); ?></span>
                        </label>
                        <div class="ovr-mem-field">
                            <label for="lp-ppd"><?php esc_html_e( 'Points per $1 spent', 'ovr-core' ); ?></label>
                            <input type="number" id="lp-ppd" name="points_per_dollar" min="0" step="0.1" value="<?php echo esc_attr( (string) $loyalty['points_per_dollar'] ); ?>">
                        </div>
                        <div class="ovr-mem-field">
                            <label for="lp-bonus"><?php esc_html_e( 'Renewal bonus (points)', 'ovr-core' ); ?></label>
                            <input type="number" id="lp-bonus" name="renewal_bonus_points" min="0" step="1" value="<?php echo esc_attr( (string) $loyalty['renewal_bonus_points'] ); ?>">
                            <span class="hint"><?php esc_html_e( 'Awarded each time a subscription renews.', 'ovr-core' ); ?></span>
                        </div>
                        <div class="ovr-mem-field">
                            <label for="lp-ref"><?php esc_html_e( 'Referral credit', 'ovr-core' ); ?> (<?php echo esc_html( $sym ); ?>)</label>
                            <input type="number" id="lp-ref" name="referral_credit" min="0" step="0.01" value="<?php echo esc_attr( (string) $loyalty['referral_credit'] ); ?>">
                            <span class="hint"><?php esc_html_e( 'Account credit granted for a successful referral.', 'ovr-core' ); ?></span>
                        </div>
                        <div class="ovr-mem-field">
                            <label for="lp-disc"><?php esc_html_e( 'Upgrade discount (%)', 'ovr-core' ); ?></label>
                            <input type="number" id="lp-disc" name="upgrade_discount_pct" min="0" max="100" step="1" value="<?php echo esc_attr( (string) $loyalty['upgrade_discount_pct'] ); ?>">
                        </div>
                        <button type="submit" class="ovr-mem-btn ovr-mem-btn--primary" style="width:100%"><span class="material-symbols-outlined">save</span><?php esc_html_e( 'Save Loyalty Settings', 'ovr-core' ); ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
