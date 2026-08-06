<?php
/**
 * Paid Services — admin list screen (Feature 1).
 *
 * @package OVR
 * @var array                $data            ListTable::query()-shaped result.
 * @var \OVR\Admin\ListTable $list            List-table engine (sort URLs).
 * @var bool                 $is_trash        Whether the Trash view is active.
 * @var string               $page_url        Base URL (preserves active filters).
 * @var string               $base_url        Bare screen URL (drops filters — for Reset).
 * @var string               $trash_url       Trash view URL.
 * @var array                $types           Service-type metadata.
 * @var array                $stats           Headline stats.
 * @var int                  $trash_count     Soft-deleted count.
 * @var string               $currency_symbol Currency symbol.
 * @var array|null           $notice          Notice or null.
 * @var string               $csv_url         Export CSV URL.
 * @var string               $new_url         New-service URL.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$rows      = $data['rows'];
$total     = (int) $data['total'];
$paged     = (int) $data['paged'];
$per_page  = (int) $data['per_page'];
$max_pages = (int) $data['max_pages'];
$sym       = $currency_symbol;
$cur_type  = sanitize_text_field( wp_unslash( $_GET['service_type'] ?? '' ) );
$cur_act   = isset( $_GET['is_active'] ) ? sanitize_text_field( wp_unslash( $_GET['is_active'] ) ) : '';

$type_label = static function ( string $t ) use ( $types ): string {
    return (string) ( $types[ $t ]['label'] ?? ucfirst( str_replace( '_', ' ', $t ) ) );
};
?>
<div class="wrap ovr-ps">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}
        #wpcontent{padding-left:0}
        .ovr-ps{--navy:#000961;--blue:#00A2E8;--blue-light:#e5f5fe;--navy-light:#e8eaf3;--gold:#DEAF0C;--gold-dark:#b8920a;--gold-light:#fef5d6;--green:#2E7D32;--green-light:#e4f4e4;--red:#B3261E;--red-light:#f9e4e2;--purple:#6A3FB8;--purple-light:#eee6fb;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--bg:#f0f3f7;--shadow-sm:0 1px 3px rgba(0,9,97,.06);--shadow-md:0 4px 12px rgba(0,9,97,.08);--r-sm:6px;--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink)}
        .ovr-ps,.ovr-ps *{box-sizing:border-box}
        .ovr-ps .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:22px}
        .ovr-ps-wrap{padding:24px 40px 48px}
        .ovr-ps-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:20px}
        .ovr-ps-head h1{font-size:30px;font-weight:700;margin:0;line-height:1.2}
        .ovr-ps-head p{margin:6px 0 0;font-size:16px;color:var(--muted)}
        .ovr-ps-actions{display:flex;gap:10px;flex-wrap:wrap;flex-shrink:0}
        .ovr-ps-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 22px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:46px;transition:all .2s}
        .ovr-ps-btn .material-symbols-outlined{font-size:20px}
        .ovr-ps-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold);box-shadow:0 2px 8px rgba(222,175,12,.25)}
        .ovr-ps-btn--primary:hover{background:var(--gold-dark);color:var(--navy)}
        .ovr-ps-btn--ghost{background:var(--surf);color:var(--navy);border-color:var(--gray-border);box-shadow:var(--shadow-sm)}
        .ovr-ps-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-ps-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        .ovr-ps-stat{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);padding:22px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-md);position:relative;overflow:hidden}
        .ovr-ps-stat::before{content:'';position:absolute;top:0;left:0;width:100%;height:3px}
        .ovr-ps-stat:nth-child(1)::before{background:var(--navy)}.ovr-ps-stat:nth-child(1) .ovr-ps-stat-ic{background:var(--navy-light);color:var(--navy)}
        .ovr-ps-stat:nth-child(2)::before{background:var(--green)}.ovr-ps-stat:nth-child(2) .ovr-ps-stat-ic{background:var(--green-light);color:var(--green)}
        .ovr-ps-stat:nth-child(3)::before{background:var(--blue)}.ovr-ps-stat:nth-child(3) .ovr-ps-stat-ic{background:var(--blue-light);color:var(--blue)}
        .ovr-ps-stat:nth-child(4)::before{background:var(--gold)}.ovr-ps-stat:nth-child(4) .ovr-ps-stat-ic{background:var(--gold-light);color:var(--gold-dark)}
        .ovr-ps-stat-ic{width:50px;height:50px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .ovr-ps-stat-ic .material-symbols-outlined{font-size:26px}
        .ovr-ps-stat-v{font-size:30px;font-weight:700;line-height:1;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
        .ovr-ps-stat-l{font-size:14px;color:var(--muted);margin-top:4px}
        .ovr-ps-tabs{display:flex;gap:6px;margin-bottom:16px}
        .ovr-ps-tab{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9999px;font-size:14px;font-weight:600;text-decoration:none;color:var(--muted);background:var(--surf);border:1px solid var(--gray-border)}
        .ovr-ps-tab.is-active{background:var(--navy);color:#fff;border-color:var(--navy)}
        .ovr-ps-tab .ovr-ps-pill{background:rgba(0,0,0,.08);border-radius:9999px;padding:1px 8px;font-size:12px}
        .ovr-ps-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-md)}
        .ovr-ps-toolbar{display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid var(--gray-border);background:var(--bg);flex-wrap:wrap}
        .ovr-ps-toolbar form{display:flex;align-items:center;gap:10px;flex:1;flex-wrap:wrap}
        .ovr-ps-search{position:relative;flex:1;min-width:200px;max-width:380px}
        .ovr-ps-search .material-symbols-outlined{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:20px;color:var(--gray-mid);pointer-events:none}
        .ovr-ps-search input{width:100%;background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:0 16px 0 44px;font-size:15px;font-family:inherit;height:46px;outline:none}
        .ovr-ps-search input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.12)}
        .ovr-ps-toolbar select{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:0 38px 0 14px;font-size:15px;font-family:inherit;height:46px;cursor:pointer;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%235F6B7A' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;min-width:150px}
        .ovr-ps-count{font-size:14px;color:var(--gray-mid);font-weight:500;background:var(--surf);padding:6px 14px;border-radius:9999px;border:1px solid var(--gray-border);margin-left:auto}
        /* Card keeps overflow:hidden for its rounded corners, so the table gets
           its own scroll region — otherwise the Actions column is clipped off
           and unreachable on narrower screens. */
        .ovr-ps-table-wrap{overflow-x:auto}
        .ovr-ps-table{width:100%;border-collapse:collapse}
        .ovr-ps-table th{text-align:left;padding:14px 18px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);background:#f8f9fb;border-bottom:2px solid var(--gray-border);white-space:nowrap}
        .ovr-ps-table th a{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
        .ovr-ps-table th a:hover{color:var(--blue)}
        .ovr-ps-table th .material-symbols-outlined{font-size:15px;opacity:.4}
        .ovr-ps-table td{padding:16px 18px;font-size:15px;border-bottom:1px solid var(--gray-border);vertical-align:middle}
        .ovr-ps-table tbody tr:hover td{background:rgba(0,162,232,.03)}
        .ovr-ps-name{font-weight:600}
        .ovr-ps-slug{font-size:12px;color:var(--gray-mid);font-family:ui-monospace,monospace}
        .ovr-ps-svcbadge{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:var(--r-sm);font-size:13px;font-weight:600;white-space:nowrap}
        .ovr-ps-svcbadge--top_of_page{background:var(--blue-light);color:var(--blue)}
        .ovr-ps-svcbadge--homepage_slider{background:var(--gold-light);color:var(--gold-dark)}
        .ovr-ps-svcbadge--featured{background:var(--purple-light);color:var(--purple)}
        .ovr-ps-promo{display:inline-block;margin-top:4px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:var(--gold-dark);background:var(--gold-light);padding:2px 8px;border-radius:var(--r-sm)}
        .ovr-ps-price{font-weight:700;font-variant-numeric:tabular-nums}
        .ovr-ps-num{font-variant-numeric:tabular-nums}
        .ovr-ps-status{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:9999px;font-size:13px;font-weight:600}
        .ovr-ps-status--on{background:var(--green-light);color:var(--green)}
        .ovr-ps-status--off{background:var(--gray-light);color:var(--muted)}
        .ovr-ps-cell-actions{display:flex;gap:4px}
        .ovr-ps-act{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:var(--r-sm);border:none;cursor:pointer;background:transparent;color:var(--gray-mid);text-decoration:none;transition:all .18s}
        .ovr-ps-act:hover{background:var(--gray-light);color:var(--ink)}
        .ovr-ps-act--edit:hover{background:var(--blue-light);color:var(--blue)}
        .ovr-ps-act--danger:hover{background:var(--red-light);color:var(--red)}
        .ovr-ps-act--ok:hover{background:var(--green-light);color:var(--green)}
        .ovr-ps-empty{text-align:center;padding:70px 24px;color:var(--muted)}
        .ovr-ps-empty .material-symbols-outlined{font-size:56px;color:var(--gray-border);margin-bottom:14px;display:block}
        .ovr-ps-empty h3{font-size:18px;font-weight:600;color:var(--ink);margin:0 0 6px}
        .ovr-ps-pag{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-top:1px solid var(--gray-border);background:var(--bg);font-size:14px;color:var(--muted);flex-wrap:wrap;gap:12px}
        .ovr-ps-pages{display:flex;gap:4px}
        .ovr-ps-page{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;border-radius:var(--r-sm);font-weight:500;text-decoration:none;color:var(--muted);border:1px solid var(--gray-border);background:var(--surf);padding:0 10px}
        .ovr-ps-page:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-ps-page--active{background:var(--navy);color:#fff;border-color:var(--navy)}
        .ovr-ps-notice{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--r-md);font-size:15px;font-weight:500;margin-bottom:18px}
        .ovr-ps-notice--success{background:var(--green-light);border:1px solid #b8d8b8;color:var(--green)}
        .ovr-ps-notice--error{background:var(--red-light);border:1px solid #e6b8b4;color:var(--red)}
        @media(max-width:1100px){.ovr-ps-stats{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:782px){.ovr-ps-wrap{padding:18px 14px 32px}.ovr-ps-actions{width:100%}.ovr-ps-actions .ovr-ps-btn{flex:1}.ovr-ps-search{max-width:none;flex:1 1 100%}.ovr-ps-table td:nth-child(5),.ovr-ps-table th:nth-child(5),.ovr-ps-table td:nth-child(6),.ovr-ps-table th:nth-child(6){display:none}}
        @media(max-width:600px){.ovr-ps-stats{grid-template-columns:1fr 1fr}}
    </style>

    <div class="ovr-ps-wrap">
        <div class="ovr-ps-head">
            <div>
                <h1><?php esc_html_e( 'Paid Services', 'ovr-core' ); ?></h1>
                <p><?php esc_html_e( 'Promotional listing upgrades owners can purchase — price, duration and placement.', 'ovr-core' ); ?></p>
            </div>
            <div class="ovr-ps-actions">
                <a href="<?php echo esc_url( $csv_url ); ?>" class="ovr-ps-btn ovr-ps-btn--ghost">
                    <span class="material-symbols-outlined">download</span><?php esc_html_e( 'Export CSV', 'ovr-core' ); ?>
                </a>
                <a href="<?php echo esc_url( $new_url ); ?>" class="ovr-ps-btn ovr-ps-btn--primary">
                    <span class="material-symbols-outlined">add</span><?php esc_html_e( 'Create Service', 'ovr-core' ); ?>
                </a>
            </div>
        </div>

        <?php if ( $notice ) : ?>
            <div class="ovr-ps-notice ovr-ps-notice--<?php echo esc_attr( $notice['type'] ); ?>">
                <span class="material-symbols-outlined"><?php echo 'error' === $notice['type'] ? 'error' : 'check_circle'; ?></span>
                <span><?php echo esc_html( $notice['text'] ); ?></span>
            </div>
        <?php endif; ?>

        <div class="ovr-ps-stats">
            <div class="ovr-ps-stat">
                <div class="ovr-ps-stat-ic"><span class="material-symbols-outlined">sell</span></div>
                <div><div class="ovr-ps-stat-v"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></div><div class="ovr-ps-stat-l"><?php esc_html_e( 'Total Services', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-ps-stat">
                <div class="ovr-ps-stat-ic"><span class="material-symbols-outlined">toggle_on</span></div>
                <div><div class="ovr-ps-stat-v"><?php echo esc_html( number_format_i18n( $stats['active'] ) ); ?></div><div class="ovr-ps-stat-l"><?php esc_html_e( 'Active Services', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-ps-stat">
                <div class="ovr-ps-stat-ic"><span class="material-symbols-outlined">sell</span></div>
                <div><div class="ovr-ps-stat-v"><?php echo esc_html( $sym . number_format_i18n( (float) $stats['avg_price'], 0 ) ); ?></div><div class="ovr-ps-stat-l"><?php esc_html_e( 'Avg. Price', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-ps-stat">
                <div class="ovr-ps-stat-ic"><span class="material-symbols-outlined">trending_up</span></div>
                <div><div class="ovr-ps-stat-v"><?php echo esc_html( $sym . number_format_i18n( (float) $stats['revenue'], 0 ) ); ?></div><div class="ovr-ps-stat-l"><?php esc_html_e( 'Upgrade Revenue', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-ps-stat">
                <div class="ovr-ps-stat-ic"><span class="material-symbols-outlined">shopping_bag</span></div>
                <div><div class="ovr-ps-stat-v"><?php echo esc_html( number_format_i18n( (int) ( $stats['active_purchases'] ?? 0 ) ) ); ?></div><div class="ovr-ps-stat-l"><?php esc_html_e( 'Active Purchases', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-ps-stat">
                <div class="ovr-ps-stat-ic"><span class="material-symbols-outlined">history</span></div>
                <div><div class="ovr-ps-stat-v"><?php echo esc_html( number_format_i18n( (int) ( $stats['expired_purchases'] ?? 0 ) ) ); ?></div><div class="ovr-ps-stat-l"><?php esc_html_e( 'Expired Purchases', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-ps-stat">
                <div class="ovr-ps-stat-ic"><span class="material-symbols-outlined">schedule</span></div>
                <div><div class="ovr-ps-stat-v"><?php echo esc_html( number_format_i18n( (int) ( $stats['upcoming_expirations'] ?? 0 ) ) ); ?></div><div class="ovr-ps-stat-l"><?php esc_html_e( 'Expiring (7 days)', 'ovr-core' ); ?></div></div>
            </div>
        </div>

        <div class="ovr-ps-tabs">
            <a href="<?php echo esc_url( $base_url ); ?>" class="ovr-ps-tab <?php echo $is_trash ? '' : 'is-active'; ?>">
                <span class="material-symbols-outlined">list</span><?php esc_html_e( 'All Services', 'ovr-core' ); ?>
            </a>
            <a href="<?php echo esc_url( $trash_url ); ?>" class="ovr-ps-tab <?php echo $is_trash ? 'is-active' : ''; ?>">
                <span class="material-symbols-outlined">delete</span><?php esc_html_e( 'Trash', 'ovr-core' ); ?>
                <?php if ( $trash_count > 0 ) : ?><span class="ovr-ps-pill"><?php echo esc_html( number_format_i18n( $trash_count ) ); ?></span><?php endif; ?>
            </a>
        </div>

        <div class="ovr-ps-card">
            <?php if ( ! $is_trash ) : ?>
                <div class="ovr-ps-toolbar">
                    <form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
                        <input type="hidden" name="post_type" value="ovr_property">
                        <input type="hidden" name="page" value="<?php echo esc_attr( \OVR\Admin\PaidServicesAdmin::PAGE_SLUG ); ?>">
                        <div class="ovr-ps-search">
                            <span class="material-symbols-outlined">search</span>
                            <input type="search" name="s" placeholder="<?php esc_attr_e( 'Search name, badge…', 'ovr-core' ); ?>" value="<?php echo esc_attr( $data['search'] ); ?>">
                        </div>
                        <select name="service_type">
                            <option value=""><?php esc_html_e( 'Any Type', 'ovr-core' ); ?></option>
                            <?php foreach ( $types as $key => $meta ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cur_type, $key ); ?>><?php echo esc_html( $meta['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="is_active">
                            <option value=""><?php esc_html_e( 'Any Status', 'ovr-core' ); ?></option>
                            <option value="1" <?php selected( $cur_act, '1' ); ?>><?php esc_html_e( 'Active', 'ovr-core' ); ?></option>
                            <option value="0" <?php selected( $cur_act, '0' ); ?>><?php esc_html_e( 'Disabled', 'ovr-core' ); ?></option>
                        </select>
                        <button type="submit" class="ovr-ps-btn ovr-ps-btn--ghost"><span class="material-symbols-outlined">filter_alt</span><?php esc_html_e( 'Filter', 'ovr-core' ); ?></button>
                        <?php \OVR\Admin\FilterControls::render_clear_search( $base_url, 'ovr-ps-btn ovr-ps-btn--ghost' ); ?>
                        <?php \OVR\Admin\FilterControls::render_reset( $base_url, 'ovr-ps-btn ovr-ps-btn--ghost', __( 'Reset', 'ovr-core' ) ); ?>
                    </form>
                    <span class="ovr-ps-count"><?php printf( esc_html( _n( '%d service', '%d services', $total, 'ovr-core' ) ), (int) $total ); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( empty( $rows ) ) : ?>
                <div class="ovr-ps-empty">
                    <span class="material-symbols-outlined"><?php echo $is_trash ? 'delete' : 'sell'; ?></span>
                    <h3><?php echo $is_trash ? esc_html__( 'Trash is empty', 'ovr-core' ) : esc_html__( 'No services yet', 'ovr-core' ); ?></h3>
                    <p><?php echo $is_trash ? esc_html__( 'Deleted services will appear here for recovery.', 'ovr-core' ) : esc_html__( 'Create your first upgrade service to start generating revenue.', 'ovr-core' ); ?></p>
                </div>
            <?php else : ?>
                <div class="ovr-ps-table-wrap">
                <table class="ovr-ps-table">
                    <thead>
                        <tr>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'name' ) ); ?>"><?php esc_html_e( 'Service', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'service_type' ) ); ?>"><?php esc_html_e( 'Type', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'price' ) ); ?>"><?php esc_html_e( 'Price', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'duration_days' ) ); ?>"><?php esc_html_e( 'Duration', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'priority_weight' ) ); ?>"><?php esc_html_e( 'Priority', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><?php esc_html_e( 'Max Slots', 'ovr-core' ); ?></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'is_active' ) ); ?>"><?php esc_html_e( 'Status', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $s ) :
                            $sid     = (int) $s['id'];
                            $type    = (string) $s['service_type'];
                            $active  = ! empty( $s['is_active'] );
                            $edit    = add_query_arg( [ 'view' => 'edit', 'id' => $sid ], $page_url );
                            $toggle  = wp_nonce_url( admin_url( 'admin-post.php?action=ovr_paid_service_toggle&id=' . $sid ), 'ovr_paid_service_toggle_' . $sid );
                            $del     = wp_nonce_url( admin_url( 'admin-post.php?action=ovr_paid_service_delete&id=' . $sid ), 'ovr_paid_service_delete_' . $sid );
                            $restore = wp_nonce_url( admin_url( 'admin-post.php?action=ovr_paid_service_restore&id=' . $sid ), 'ovr_paid_service_restore_' . $sid );
                        ?>
                            <tr>
                                <td>
                                    <div class="ovr-ps-name"><?php echo esc_html( $s['name'] ?: __( '(unnamed)', 'ovr-core' ) ); ?></div>
                                    <div class="ovr-ps-slug"><?php echo esc_html( $s['slug'] ); ?></div>
                                    <?php if ( ! empty( $s['badge'] ) ) : ?><span class="ovr-ps-promo"><?php echo esc_html( $s['badge'] ); ?></span><?php endif; ?>
                                </td>
                                <td><span class="ovr-ps-svcbadge ovr-ps-svcbadge--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type_label( $type ) ); ?></span></td>
                                <td class="ovr-ps-price"><?php echo esc_html( $sym . number_format_i18n( (float) $s['price'], 2 ) ); ?></td>
                                <td class="ovr-ps-num"><?php printf( esc_html( _n( '%d day', '%d days', (int) $s['duration_days'], 'ovr-core' ) ), (int) $s['duration_days'] ); ?></td>
                                <td class="ovr-ps-num"><?php echo esc_html( number_format_i18n( (int) $s['priority_weight'] ) ); ?></td>
                                <td class="ovr-ps-num"><?php echo (int) $s['max_simultaneous'] > 0 ? esc_html( number_format_i18n( (int) $s['max_simultaneous'] ) ) : '&infin;'; ?></td>
                                <td>
                                    <span class="ovr-ps-status ovr-ps-status--<?php echo $active ? 'on' : 'off'; ?>">
                                        <span class="material-symbols-outlined"><?php echo $active ? 'check_circle' : 'do_not_disturb_on'; ?></span><?php echo $active ? esc_html__( 'Active', 'ovr-core' ) : esc_html__( 'Disabled', 'ovr-core' ); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="ovr-ps-cell-actions">
                                        <?php if ( $is_trash ) : ?>
                                            <a href="<?php echo esc_url( $restore ); ?>" class="ovr-ps-act ovr-ps-act--ok" title="<?php esc_attr_e( 'Restore', 'ovr-core' ); ?>"><span class="material-symbols-outlined">restore_from_trash</span></a>
                                        <?php else : ?>
                                            <a href="<?php echo esc_url( $edit ); ?>" class="ovr-ps-act ovr-ps-act--edit" title="<?php esc_attr_e( 'Edit', 'ovr-core' ); ?>"><span class="material-symbols-outlined">edit</span></a>
                                            <a href="<?php echo esc_url( $toggle ); ?>" class="ovr-ps-act" title="<?php echo $active ? esc_attr__( 'Disable', 'ovr-core' ) : esc_attr__( 'Enable', 'ovr-core' ); ?>"><span class="material-symbols-outlined"><?php echo $active ? 'toggle_off' : 'toggle_on'; ?></span></a>
                                            <a href="<?php echo esc_url( $del ); ?>" class="ovr-ps-act ovr-ps-act--danger" title="<?php esc_attr_e( 'Trash', 'ovr-core' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Move this service to trash? Owners will no longer be able to buy it.', 'ovr-core' ) ); ?>');"><span class="material-symbols-outlined">delete</span></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?php if ( $max_pages > 1 ) : ?>
                    <div class="ovr-ps-pag">
                        <span><?php printf( esc_html__( 'Showing %1$d–%2$d of %3$d', 'ovr-core' ), ( ( $paged - 1 ) * $per_page ) + 1, min( $paged * $per_page, $total ), $total ); ?></span>
                        <div class="ovr-ps-pages">
                            <?php
                            $start = max( 1, $paged - 2 );
                            $end   = min( $max_pages, $start + 4 );
                            $start = max( 1, $end - 4 );
                            for ( $i = $start; $i <= $end; $i++ ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $page_url ) ); ?>" class="ovr-ps-page <?php echo $i === $paged ? 'ovr-ps-page--active' : ''; ?>"><?php echo esc_html( $i ); ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
