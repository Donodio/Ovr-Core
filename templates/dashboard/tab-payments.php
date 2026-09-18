<?php
/**
 * Payments tab ("Transaction History") — table of the user's ovr_payments rows
 * with client-side filters (date/type/status), CSV export, and pagination.
 * Scoped under `.ovr-ld`; the dashboard shell supplies the surrounding nav.
 *
 * @package OVR
 * @var array  $payments
 * @var string $receipt_url   Payment-success page URL (receipt view).
 * @var string $checkout_url  Checkout page URL (retry).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Subscription\Plans;
use OVR\Subscription\ListingUpgrades;

$payments     = $payments ?? [];
$receipt_url  = $receipt_url ?? '';
$checkout_url = $checkout_url ?? '';
$settings     = (array) get_option( 'ovr_settings', [] );
$sym          = $settings['currency_symbol'] ?? '$';

$gateways = [
    'stripe'        => [ 'credit_card', __( 'Card (Stripe)', 'ovr-core' ) ],
    'authorize_net' => [ 'credit_card', __( 'Card', 'ovr-core' ) ],
    'paypal'        => [ 'account_balance', 'PayPal' ],
    'wallet'        => [ 'account_balance_wallet', __( 'Wallet', 'ovr-core' ) ],
    'free'          => [ 'redeem', __( 'Free', 'ovr-core' ) ],
];

// Status → [ pill class, label, filter value ].
$status_map = [
    'completed' => [ 'ok',      __( 'Completed', 'ovr-core' ), 'completed' ],
    'pending'   => [ 'pending', __( 'Pending', 'ovr-core' ),   'pending' ],
    'failed'    => [ 'declined',__( 'Declined', 'ovr-core' ),  'declined' ],
    'refunded'  => [ 'refund',  __( 'Refunded', 'ovr-core' ),  'refunded' ],
];
?>
<div class="ld-pay">

    <header class="ld-pay-head">
        <div>
            <h1 class="ld-pay-h1"><?php esc_html_e( 'Transaction History', 'ovr-core' ); ?></h1>
            <p class="ld-pay-lede"><?php esc_html_e( 'View and manage your recent payments, subscriptions, and upgrades.', 'ovr-core' ); ?></p>
        </div>
        <button type="button" class="ld-pay-export" id="ld-pay-export">
            <span class="material-symbols-outlined">download</span><?php esc_html_e( 'Export CSV', 'ovr-core' ); ?>
        </button>
    </header>

    <!-- Filters -->
    <div class="ld-pay-filters">
        <div class="ld-pay-field">
            <label class="ld-pay-label"><?php esc_html_e( 'Date Range', 'ovr-core' ); ?></label>
            <div class="ld-pay-selectwrap">
                <span class="material-symbols-outlined">calendar_today</span>
                <select id="ld-pay-date" class="ld-pay-select">
                    <option value="all"><?php esc_html_e( 'All Time', 'ovr-core' ); ?></option>
                    <option value="30"><?php esc_html_e( 'Last 30 Days', 'ovr-core' ); ?></option>
                    <option value="90"><?php esc_html_e( 'Last 3 Months', 'ovr-core' ); ?></option>
                    <option value="ytd"><?php esc_html_e( 'Year to Date', 'ovr-core' ); ?></option>
                </select>
            </div>
        </div>
        <div class="ld-pay-field">
            <label class="ld-pay-label"><?php esc_html_e( 'Transaction Type', 'ovr-core' ); ?></label>
            <div class="ld-pay-selectwrap">
                <span class="material-symbols-outlined">filter_list</span>
                <select id="ld-pay-type" class="ld-pay-select">
                    <option value="all"><?php esc_html_e( 'All Transactions', 'ovr-core' ); ?></option>
                    <option value="subscription"><?php esc_html_e( 'Subscriptions', 'ovr-core' ); ?></option>
                    <option value="upgrade"><?php esc_html_e( 'Upgrades', 'ovr-core' ); ?></option>
                    <option value="refund"><?php esc_html_e( 'Refunds', 'ovr-core' ); ?></option>
                </select>
            </div>
        </div>
        <div class="ld-pay-field">
            <label class="ld-pay-label"><?php esc_html_e( 'Status', 'ovr-core' ); ?></label>
            <select id="ld-pay-status" class="ld-pay-select ld-pay-select--plain">
                <option value="all"><?php esc_html_e( 'Any Status', 'ovr-core' ); ?></option>
                <option value="completed"><?php esc_html_e( 'Completed', 'ovr-core' ); ?></option>
                <option value="pending"><?php esc_html_e( 'Pending', 'ovr-core' ); ?></option>
                <option value="declined"><?php esc_html_e( 'Declined', 'ovr-core' ); ?></option>
                <option value="refunded"><?php esc_html_e( 'Refunded', 'ovr-core' ); ?></option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="ld-pay-card">
        <?php if ( empty( $payments ) ) : ?>
            <div class="ld-pay-empty">
                <span class="material-symbols-outlined">receipt_long</span>
                <p><?php esc_html_e( 'No payments yet. Your subscription and upgrade charges will appear here.', 'ovr-core' ); ?></p>
            </div>
        <?php else : ?>
            <div class="ld-pay-scroll">
                <table class="ld-pay-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Description', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Amount', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Method', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                            <th class="ld-pay-r"><?php esc_html_e( 'Action', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ld-pay-body">
                        <?php foreach ( $payments as $p ) :
                            $status   = (string) ( $p['status'] ?? 'pending' );
                            $smeta    = $status_map[ $status ] ?? [ 'refund', ucfirst( $status ), $status ];
                            $gw       = (string) ( $p['gateway'] ?? '' );
                            $gmeta    = $gateways[ $gw ] ?? [ 'payments', ucwords( str_replace( '_', ' ', $gw ) ) ];
                            $meta     = json_decode( (string) ( $p['meta_data'] ?? '' ), true ) ?: [];
                            $ts       = strtotime( (string) ( $p['created_at'] ?? '' ) );

                            // Item label + sub-line + filter type + retry target.
                            $type      = 'subscription';
                            $retry_url = '';
                            if ( ! empty( $meta['upgrade'] ) ) {
                                $type    = 'upgrade';
                                $product = ListingUpgrades::get_product( (string) $meta['upgrade'] );
                                $term    = (int) ( $meta['term'] ?? 14 );
                                $label   = $product ? sprintf( __( '%1$s (%2$d-day)', 'ovr-core' ), $product['name'], $term ) : __( 'Listing Upgrade', 'ovr-core' );
                                $retry_url = add_query_arg( [ 'upgrade' => (string) $meta['upgrade'], 'term' => $term ], $checkout_url );
                            } elseif ( ! empty( $meta['plan_slug'] ) ) {
                                $plan      = Plans::get_plan( (string) $meta['plan_slug'] );
                                $label     = $plan['name'] ?? __( 'Subscription', 'ovr-core' );
                                $retry_url = add_query_arg( 'plan', (string) $meta['plan_slug'], $checkout_url );
                            } else {
                                $label = ucwords( str_replace( '_', ' ', (string) ( $p['payment_type'] ?? 'payment' ) ) );
                            }
                            if ( 'refunded' === $status ) { $type = 'refund'; }

                            $order_no = ! empty( $p['transaction_id'] )
                                ? (string) $p['transaction_id']
                                : 'OVR-' . str_pad( (string) ( $p['id'] ?? 0 ), 6, '0', STR_PAD_LEFT );
                            $sub      = ! empty( $p['description'] ) ? (string) $p['description'] : $order_no;
                            $declined = ( 'failed' === $status );
                            $receipt  = add_query_arg( 'payment_id', (int) ( $p['id'] ?? 0 ), $receipt_url );
                        ?>
                            <tr class="ld-pay-row" data-type="<?php echo esc_attr( $type ); ?>" data-status="<?php echo esc_attr( $smeta[2] ); ?>" data-ts="<?php echo esc_attr( (string) ( $ts ?: 0 ) ); ?>">
                                <td>
                                    <div class="ld-pay-date"><?php echo esc_html( $ts ? gmdate( 'M j, Y', $ts ) : '—' ); ?></div>
                                    <div class="ld-pay-sub"><?php echo esc_html( $ts ? gmdate( 'g:i A', $ts ) : '' ); ?></div>
                                </td>
                                <td>
                                    <div class="ld-pay-desc"><?php echo esc_html( $label ); ?></div>
                                    <div class="ld-pay-sub<?php echo $declined ? ' is-err' : ''; ?>"><?php echo esc_html( $sub ); ?></div>
                                </td>
                                <td><div class="ld-pay-amt<?php echo $declined ? ' is-dim' : ''; ?>"><?php echo esc_html( $sym . number_format( (float) ( $p['amount'] ?? 0 ), 2 ) ); ?></div></td>
                                <td>
                                    <div class="ld-pay-method<?php echo $declined ? ' is-dim' : ''; ?>">
                                        <span class="material-symbols-outlined"><?php echo esc_html( $gmeta[0] ); ?></span><?php echo esc_html( $gmeta[1] ); ?>
                                    </div>
                                </td>
                                <td><span class="ld-pay-pill <?php echo esc_attr( $smeta[0] ); ?>"><?php echo esc_html( $smeta[1] ); ?></span></td>
                                <td class="ld-pay-r">
                                    <?php if ( $declined && $retry_url ) : ?>
                                        <a href="<?php echo esc_url( $retry_url ); ?>" class="ld-pay-retry"><?php esc_html_e( 'Retry', 'ovr-core' ); ?></a>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( $receipt ); ?>" class="ld-pay-receipt" title="<?php esc_attr_e( 'View receipt', 'ovr-core' ); ?>">
                                            <span class="material-symbols-outlined">receipt_long</span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="ld-pay-noresult" hidden><?php esc_html_e( 'No transactions match these filters.', 'ovr-core' ); ?></div>
            </div>

            <div class="ld-pay-foot">
                <span class="ld-pay-count" id="ld-pay-count"></span>
                <div class="ld-pay-pager">
                    <button type="button" class="ld-pay-pbtn" id="ld-pay-prev"><?php esc_html_e( 'Previous', 'ovr-core' ); ?></button>
                    <button type="button" class="ld-pay-pbtn" id="ld-pay-next"><?php esc_html_e( 'Next', 'ovr-core' ); ?></button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .ovr-ld .ld-pay{display:flex;flex-direction:column;gap:24px}
    .ovr-ld .ld-pay-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
    .ovr-ld .ld-pay-h1{font-size:32px;font-weight:700;letter-spacing:-.01em;color:var(--on);margin:0 0 6px}
    .ovr-ld .ld-pay-lede{font-size:15px;color:var(--sv);margin:0}
    .ovr-ld .ld-pay-export{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:1px solid var(--ov);background:var(--surf);border-radius:10px;font-family:inherit;font-size:14px;font-weight:600;color:var(--p);cursor:pointer;transition:background .15s,border-color .15s}
    .ovr-ld .ld-pay-export:hover{background:var(--sclow);border-color:var(--p)}
    .ovr-ld .ld-pay-export .material-symbols-outlined{font-size:19px}

    .ovr-ld .ld-pay-filters{background:var(--surf);border:1px solid var(--ov);border-radius:14px;padding:20px;display:flex;gap:16px;flex-wrap:wrap;box-shadow:0 4px 24px rgba(0,0,0,.04)}
    .ovr-ld .ld-pay-field{flex:1;min-width:180px;display:flex;flex-direction:column;gap:7px}
    .ovr-ld .ld-pay-label{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--sv)}
    .ovr-ld .ld-pay-selectwrap{position:relative}
    .ovr-ld .ld-pay-selectwrap>.material-symbols-outlined{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--sv);font-size:19px;pointer-events:none}
    .ovr-ld .ld-pay-select{width:100%;padding:11px 14px;border:1px solid var(--ov);border-radius:9px;font-family:inherit;font-size:14px;color:var(--on);background:var(--surf);outline:none;cursor:pointer;appearance:none}
    .ovr-ld .ld-pay-selectwrap .ld-pay-select{padding-left:38px}
    .ovr-ld .ld-pay-select:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(0,76,76,.12)}

    .ovr-ld .ld-pay-card{background:var(--surf);border:1px solid var(--ov);border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.04)}
    .ovr-ld .ld-pay-scroll{overflow-x:auto}
    .ovr-ld .ld-pay-table{width:100%;border-collapse:collapse;font-size:14px}
    .ovr-ld .ld-pay-table thead tr{background:var(--sclow);border-bottom:1px solid var(--ov)}
    .ovr-ld .ld-pay-table th{padding:14px 22px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);white-space:nowrap}
    .ovr-ld .ld-pay-table th.ld-pay-r,.ovr-ld .ld-pay-table td.ld-pay-r{text-align:right}
    .ovr-ld .ld-pay-row{border-bottom:1px solid rgba(190,201,200,.3)}
    .ovr-ld .ld-pay-row:last-child{border-bottom:none}
    .ovr-ld .ld-pay-row:hover{background:var(--sclow)}
    .ovr-ld .ld-pay-table td{padding:18px 22px;vertical-align:middle}
    .ovr-ld .ld-pay-date{font-weight:600;color:var(--on);white-space:nowrap}
    .ovr-ld .ld-pay-desc{font-weight:600;color:var(--on)}
    .ovr-ld .ld-pay-sub{font-size:13px;color:var(--sv);margin-top:3px}
    .ovr-ld .ld-pay-sub.is-err{color:var(--err)}
    .ovr-ld .ld-pay-amt{font-size:18px;font-weight:700;color:var(--on);white-space:nowrap;font-variant-numeric:tabular-nums}
    .ovr-ld .ld-pay-amt.is-dim,.ovr-ld .ld-pay-method.is-dim{opacity:.55}
    .ovr-ld .ld-pay-method{display:flex;align-items:center;gap:8px;color:var(--on);white-space:nowrap}
    .ovr-ld .ld-pay-method .material-symbols-outlined{font-size:20px;color:var(--p)}
    .ovr-ld .ld-pay-pill{display:inline-flex;align-items:center;padding:4px 12px;border-radius:9999px;font-size:11px;font-weight:700;letter-spacing:.03em}
    .ovr-ld .ld-pay-pill.ok{background:var(--secc);color:var(--onsecc,#00714e)}
    .ovr-ld .ld-pay-pill.pending{background:#ffe088;color:#4e3d00}
    .ovr-ld .ld-pay-pill.declined{background:var(--errc);color:#93000a}
    .ovr-ld .ld-pay-pill.refund{background:var(--surface-container-highest,#e0e3e2);color:var(--sv)}
    .ovr-ld .ld-pay-receipt{display:inline-flex;padding:8px;border-radius:8px;color:var(--p);text-decoration:none;transition:background .15s}
    .ovr-ld .ld-pay-receipt:hover{background:var(--sclow)}
    .ovr-ld .ld-pay-retry{display:inline-block;padding:6px 14px;border:1px solid var(--p);border-radius:8px;color:var(--p);font-weight:600;font-size:13px;text-decoration:none}
    .ovr-ld .ld-pay-retry:hover{background:rgba(0,76,76,.06)}

    .ovr-ld .ld-pay-empty{padding:56px 24px;text-align:center;color:var(--sv)}
    .ovr-ld .ld-pay-empty .material-symbols-outlined{font-size:40px;color:var(--ov);display:block;margin:0 auto 10px}
    .ovr-ld .ld-pay-noresult{padding:40px;text-align:center;color:var(--sv);font-size:14px}

    .ovr-ld .ld-pay-foot{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 22px;border-top:1px solid var(--ov);background:var(--sclow);flex-wrap:wrap}
    .ovr-ld .ld-pay-count{font-size:14px;color:var(--sv)}
    .ovr-ld .ld-pay-pager{display:flex;gap:8px}
    .ovr-ld .ld-pay-pbtn{padding:7px 16px;border:1px solid var(--ov);border-radius:8px;background:var(--surf);font-family:inherit;font-size:13px;font-weight:600;color:var(--on);cursor:pointer;transition:background .15s,color .15s}
    .ovr-ld .ld-pay-pbtn:hover:not(:disabled){background:var(--surf);color:var(--p);border-color:var(--p)}
    .ovr-ld .ld-pay-pbtn:disabled{opacity:.45;cursor:default}

    @media (max-width:760px){.ovr-ld .ld-pay-h1{font-size:26px}}
</style>

<script>
(function(){
    var root = document.querySelector('.ovr-ld .ld-pay');
    if (!root) return;
    var body = root.querySelector('#ld-pay-body');
    if (!body) return;

    var rows    = Array.prototype.slice.call(body.querySelectorAll('.ld-pay-row'));
    var perPage = 8, page = 1, filtered = rows.slice();
    var dateSel = root.querySelector('#ld-pay-date'),
        typeSel = root.querySelector('#ld-pay-type'),
        statSel = root.querySelector('#ld-pay-status'),
        countEl = root.querySelector('#ld-pay-count'),
        prevBtn = root.querySelector('#ld-pay-prev'),
        nextBtn = root.querySelector('#ld-pay-next'),
        noRes   = root.querySelector('.ld-pay-noresult');

    var now = Date.now() / 1000;
    function minTs(range){
        if (range === '30')  return now - 30*86400;
        if (range === '90')  return now - 90*86400;
        if (range === 'ytd') return new Date(new Date().getFullYear(), 0, 1).getTime() / 1000;
        return 0;
    }

    function applyFilters(){
        var dr = dateSel ? dateSel.value : 'all',
            ty = typeSel ? typeSel.value : 'all',
            st = statSel ? statSel.value : 'all',
            min = minTs(dr);
        filtered = rows.filter(function(r){
            if (ty !== 'all' && r.getAttribute('data-type') !== ty) return false;
            if (st !== 'all' && r.getAttribute('data-status') !== st) return false;
            if (min && (parseInt(r.getAttribute('data-ts'), 10) || 0) < min) return false;
            return true;
        });
        page = 1;
        render();
    }

    function render(){
        var total = filtered.length, pages = Math.max(1, Math.ceil(total / perPage));
        if (page > pages) page = pages;
        rows.forEach(function(r){ r.style.display = 'none'; });
        var start = (page - 1) * perPage, end = Math.min(start + perPage, total);
        for (var i = start; i < end; i++) filtered[i].style.display = '';
        if (noRes) noRes.hidden = total !== 0;
        if (countEl) {
            countEl.textContent = total === 0
                ? '0 ' + 'entries'
                : 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entries';
        }
        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = page >= pages;
    }

    [dateSel, typeSel, statSel].forEach(function(s){ if (s) s.addEventListener('change', applyFilters); });
    if (prevBtn) prevBtn.addEventListener('click', function(){ if (page > 1) { page--; render(); } });
    if (nextBtn) nextBtn.addEventListener('click', function(){ page++; render(); });

    // CSV export of the currently-filtered rows.
    var exportBtn = root.querySelector('#ld-pay-export');
    if (exportBtn) exportBtn.addEventListener('click', function(){
        var head = ['Date','Description','Reference','Amount','Method','Status'];
        var lines = [head.join(',')];
        filtered.forEach(function(r){
            var c = r.children;
            var cell = function(el, sel){ var n = el.querySelector(sel); return n ? n.textContent.trim() : ''; };
            var vals = [
                cell(c[0], '.ld-pay-date'),
                cell(c[1], '.ld-pay-desc'),
                cell(c[1], '.ld-pay-sub'),
                cell(c[2], '.ld-pay-amt'),
                cell(c[3], '.ld-pay-method'),
                cell(c[4], '.ld-pay-pill')
            ].map(function(v){ return '"' + v.replace(/"/g, '""') + '"'; });
            lines.push(vals.join(','));
        });
        var blob = new Blob([lines.join('\n')], { type: 'text/csv' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'ovr-payments.csv';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });

    render();
})();
</script>
