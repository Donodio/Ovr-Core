<?php
/**
 * CRM — dashboard + All Manifest list (Feature 5).
 *
 * @package OVR
 * @var array                $data       ListTable::query() result.
 * @var \OVR\Admin\ListTable $list       List engine (sort URLs).
 * @var string               $page_url   Base screen URL.
 * @var string               $segment    Active segment slug.
 * @var float                $threshold  High-value spend threshold.
 * @var array                $stats      Dashboard segment counts.
 * @var array|null           $notice     Result notice.
 * @var string               $csv_url    CSV export URL.
 * @var string               $new_url    Add-guest URL.
 * @var string               $threshold_action admin-post URL.
 * @var string               $threshold_nonce  Nonce value.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$rows      = $data['rows'];
$total     = (int) $data['total'];
$paged     = (int) $data['paged'];
$per_page  = (int) $data['per_page'];
$max_pages = (int) $data['max_pages'];
$cur_status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
$symbol     = '$';

$seg_url = static function ( string $seg ) use ( $page_url ) {
    return '' === $seg ? $page_url : add_query_arg( 'segment', $seg, $page_url );
};
$cards = [
    ''           => [ 'label' => __( 'Total Guests', 'ovr-core' ),  'icon' => 'groups',     'value' => $stats['total'] ],
    'repeat'     => [ 'label' => __( 'Repeat Guests', 'ovr-core' ), 'icon' => 'repeat',     'value' => $stats['repeat'] ],
    'high_value' => [ 'label' => __( 'High-Value', 'ovr-core' ),    'icon' => 'diamond',    'value' => $stats['high_value'] ],
    'new'        => [ 'label' => __( 'New (30 days)', 'ovr-core' ), 'icon' => 'person_add', 'value' => $stats['new30'] ],
];
?>
<div class="wrap ovr-crm">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}#wpcontent{padding-left:0}
        .ovr-crm{--navy:#000961;--blue:#00A2E8;--blue-light:#e5f5fe;--navy-light:#e8eaf3;--gold:#DEAF0C;--gold-dark:#b8920a;--gold-light:#fef5d6;--green:#2E7D32;--green-light:#e4f4e4;--red:#B3261E;--red-light:#f9e4e2;--purple:#6A3FB8;--purple-light:#eee6fb;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--bg:#f0f3f7;--shadow-sm:0 1px 3px rgba(0,9,97,.06);--shadow-md:0 4px 12px rgba(0,9,97,.08);--r-sm:6px;--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;color:var(--ink)}
        .ovr-crm,.ovr-crm *{box-sizing:border-box}
        .ovr-crm .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:22px}
        .ovr-crm-wrap{padding:24px 40px 48px}
        .ovr-crm-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:20px}
        .ovr-crm-head h1{font-size:30px;font-weight:700;margin:0;line-height:1.2}
        .ovr-crm-head p{margin:6px 0 0;font-size:16px;color:var(--muted)}
        .ovr-crm-actions{display:flex;gap:10px;flex-wrap:wrap}
        .ovr-crm-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 22px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:46px;transition:all .2s}
        .ovr-crm-btn .material-symbols-outlined{font-size:20px}
        .ovr-crm-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold)}
        .ovr-crm-btn--primary:hover{background:var(--gold-dark)}
        .ovr-crm-btn--ghost{background:var(--surf);color:var(--navy);border-color:var(--gray-border);box-shadow:var(--shadow-sm)}
        .ovr-crm-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-crm-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        .ovr-crm-stat{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);padding:22px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-md);position:relative;overflow:hidden;text-decoration:none;color:inherit;transition:all .2s}
        .ovr-crm-stat:hover{box-shadow:0 8px 24px rgba(0,9,97,.12);transform:translateY(-2px)}
        .ovr-crm-stat::before{content:'';position:absolute;top:0;left:0;width:100%;height:3px}
        .ovr-crm-stat.is-active{border-color:var(--navy);box-shadow:0 0 0 2px var(--navy-light)}
        .ovr-crm-stat:nth-child(1)::before{background:var(--navy)}.ovr-crm-stat:nth-child(1) .ovr-crm-stat-ic{background:var(--navy-light);color:var(--navy)}
        .ovr-crm-stat:nth-child(2)::before{background:var(--blue)}.ovr-crm-stat:nth-child(2) .ovr-crm-stat-ic{background:var(--blue-light);color:var(--blue)}
        .ovr-crm-stat:nth-child(3)::before{background:var(--purple)}.ovr-crm-stat:nth-child(3) .ovr-crm-stat-ic{background:var(--purple-light);color:var(--purple)}
        .ovr-crm-stat:nth-child(4)::before{background:var(--green)}.ovr-crm-stat:nth-child(4) .ovr-crm-stat-ic{background:var(--green-light);color:var(--green)}
        .ovr-crm-stat-ic{width:50px;height:50px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .ovr-crm-stat-ic .material-symbols-outlined{font-size:26px}
        .ovr-crm-stat-v{font-size:30px;font-weight:700;line-height:1;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
        .ovr-crm-stat-l{font-size:14px;color:var(--muted);margin-top:4px}
        .ovr-crm-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-md)}
        .ovr-crm-toolbar{display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid var(--gray-border);background:var(--bg);flex-wrap:wrap}
        .ovr-crm-toolbar form.search{display:flex;align-items:center;gap:10px;flex:1;flex-wrap:wrap}
        .ovr-crm-search{position:relative;flex:1;min-width:200px;max-width:380px}
        .ovr-crm-search .material-symbols-outlined{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:20px;color:var(--gray-mid);pointer-events:none}
        .ovr-crm-search input{width:100%;background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:0 16px 0 44px;font-size:15px;font-family:inherit;height:46px;outline:none}
        .ovr-crm-search input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.12)}
        .ovr-crm-toolbar select{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:0 38px 0 14px;font-size:15px;font-family:inherit;height:46px;cursor:pointer;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%235F6B7A' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;min-width:140px}
        .ovr-crm-thresh{display:flex;align-items:center;gap:8px;margin-left:auto;font-size:13px;color:var(--muted)}
        .ovr-crm-thresh input{width:110px;height:40px;border:1px solid var(--gray-border);border-radius:var(--r-sm);padding:0 10px;font-family:inherit}
        .ovr-crm-thresh button{height:40px;border:1px solid var(--gray-border);background:var(--surf);border-radius:var(--r-sm);padding:0 14px;font-weight:600;cursor:pointer;color:var(--navy)}
        .ovr-crm-table{width:100%;border-collapse:collapse}
        .ovr-crm-table th{text-align:left;padding:14px 18px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);background:#f8f9fb;border-bottom:2px solid var(--gray-border);white-space:nowrap}
        .ovr-crm-table th a{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
        .ovr-crm-table th a:hover{color:var(--blue)}
        .ovr-crm-table th .material-symbols-outlined{font-size:15px;opacity:.4}
        .ovr-crm-table td{padding:15px 18px;font-size:15px;border-bottom:1px solid var(--gray-border);vertical-align:middle}
        .ovr-crm-table tbody tr:hover td{background:rgba(0,162,232,.03)}
        .ovr-crm-name{font-weight:600}
        .ovr-crm-name a{color:var(--navy);text-decoration:none}
        .ovr-crm-name a:hover{color:var(--blue)}
        .ovr-crm-sub{font-size:13px;color:var(--muted)}
        .ovr-crm-num{font-variant-numeric:tabular-nums;font-weight:600}
        .ovr-crm-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:var(--r-sm);font-size:13px;font-weight:600;text-transform:capitalize}
        .ovr-crm-badge--active{background:var(--green-light);color:var(--green)}
        .ovr-crm-badge--inactive{background:var(--red-light);color:var(--red)}
        .ovr-crm-badge--vip{background:var(--purple-light);color:var(--purple)}
        .ovr-crm-act{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:var(--r-sm);border:none;cursor:pointer;background:transparent;color:var(--gray-mid);text-decoration:none}
        .ovr-crm-act:hover{background:var(--blue-light);color:var(--blue)}
        .ovr-crm-act--danger:hover{background:var(--red-light);color:var(--red)}
        .ovr-crm-empty{text-align:center;padding:70px 24px;color:var(--muted)}
        .ovr-crm-empty .material-symbols-outlined{font-size:56px;color:var(--gray-border);margin-bottom:14px;display:block}
        .ovr-crm-pag{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-top:1px solid var(--gray-border);background:var(--bg);font-size:14px;color:var(--muted);flex-wrap:wrap;gap:12px}
        .ovr-crm-pages{display:flex;gap:4px}
        .ovr-crm-page{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;border-radius:var(--r-sm);font-weight:500;text-decoration:none;color:var(--muted);border:1px solid var(--gray-border);background:var(--surf);padding:0 10px}
        .ovr-crm-page--active{background:var(--navy);color:#fff;border-color:var(--navy)}
        .ovr-crm-notice{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--r-md);font-size:15px;font-weight:500;margin-bottom:18px}
        .ovr-crm-notice--success{background:var(--green-light);border:1px solid #b8d8b8;color:var(--green)}
        .ovr-crm-notice--error{background:var(--red-light);border:1px solid #e6b8b4;color:var(--red)}
        @media(max-width:1100px){.ovr-crm-stats{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:782px){.ovr-crm-wrap{padding:18px 14px 32px}.ovr-crm-search{max-width:none;flex:1 1 100%}.ovr-crm-table td:nth-child(2),.ovr-crm-table th:nth-child(2){display:none}}
        @media(max-width:600px){.ovr-crm-stats{grid-template-columns:1fr 1fr}.ovr-crm-thresh{margin-left:0;width:100%}}
    </style>

    <div class="ovr-crm-wrap">
        <div class="ovr-crm-head">
            <div>
                <h1><?php esc_html_e( 'CRM', 'ovr-core' ); ?></h1>
                <p><?php esc_html_e( 'Your master guest manifest — stays, spend and lifetime value.', 'ovr-core' ); ?></p>
            </div>
            <div class="ovr-crm-actions">
                <a href="<?php echo esc_url( $csv_url ); ?>" class="ovr-crm-btn ovr-crm-btn--ghost"><span class="material-symbols-outlined">download</span><?php esc_html_e( 'Export CSV', 'ovr-core' ); ?></a>
                <a href="<?php echo esc_url( $new_url ); ?>" class="ovr-crm-btn ovr-crm-btn--primary"><span class="material-symbols-outlined">person_add</span><?php esc_html_e( 'Add Guest', 'ovr-core' ); ?></a>
            </div>
        </div>

        <?php if ( $notice ) : ?>
            <div class="ovr-crm-notice ovr-crm-notice--<?php echo esc_attr( $notice['type'] ); ?>">
                <span class="material-symbols-outlined"><?php echo 'error' === $notice['type'] ? 'error' : 'check_circle'; ?></span>
                <span><?php echo esc_html( $notice['text'] ); ?></span>
            </div>
        <?php endif; ?>

        <div class="ovr-crm-stats">
            <?php foreach ( $cards as $seg => $c ) : ?>
                <a href="<?php echo esc_url( $seg_url( $seg ) ); ?>" class="ovr-crm-stat <?php echo $segment === $seg ? 'is-active' : ''; ?>">
                    <div class="ovr-crm-stat-ic"><span class="material-symbols-outlined"><?php echo esc_attr( $c['icon'] ); ?></span></div>
                    <div>
                        <div class="ovr-crm-stat-v"><?php echo esc_html( number_format_i18n( $c['value'] ) ); ?></div>
                        <div class="ovr-crm-stat-l"><?php echo esc_html( $c['label'] ); ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="ovr-crm-card">
            <div class="ovr-crm-toolbar">
                <form class="search" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
                    <input type="hidden" name="post_type" value="ovr_property">
                    <input type="hidden" name="page" value="<?php echo esc_attr( \OVR\Admin\CrmAdmin::PAGE_SLUG ); ?>">
                    <?php if ( $segment ) : ?><input type="hidden" name="segment" value="<?php echo esc_attr( $segment ); ?>"><?php endif; ?>
                    <div class="ovr-crm-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" name="s" placeholder="<?php esc_attr_e( 'Search name, email, phone, tag…', 'ovr-core' ); ?>" value="<?php echo esc_attr( $data['search'] ); ?>">
                    </div>
                    <select name="status">
                        <option value=""><?php esc_html_e( 'Any Status', 'ovr-core' ); ?></option>
                        <option value="active" <?php selected( $cur_status, 'active' ); ?>><?php esc_html_e( 'Active', 'ovr-core' ); ?></option>
                        <option value="inactive" <?php selected( $cur_status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></option>
                    </select>
                    <button type="submit" class="ovr-crm-btn ovr-crm-btn--ghost"><span class="material-symbols-outlined">filter_alt</span><?php esc_html_e( 'Filter', 'ovr-core' ); ?></button>
                </form>
                <form class="ovr-crm-thresh" method="post" action="<?php echo esc_url( $threshold_action ); ?>">
                    <input type="hidden" name="action" value="ovr_crm_threshold">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $threshold_nonce ); ?>">
                    <label for="ovr-crm-thr"><?php esc_html_e( 'High-value ≥', 'ovr-core' ); ?></label>
                    <input id="ovr-crm-thr" type="number" min="0" step="100" name="crm_high_value_threshold" value="<?php echo esc_attr( (string) (int) $threshold ); ?>">
                    <button type="submit"><?php esc_html_e( 'Save', 'ovr-core' ); ?></button>
                </form>
            </div>

            <?php if ( empty( $rows ) ) : ?>
                <div class="ovr-crm-empty">
                    <span class="material-symbols-outlined">contacts</span>
                    <h3><?php esc_html_e( 'No guests found', 'ovr-core' ); ?></h3>
                    <p><?php esc_html_e( 'Guests are added automatically from bookings, or add one manually.', 'ovr-core' ); ?></p>
                </div>
            <?php else : ?>
                <table class="ovr-crm-table">
                    <thead>
                        <tr>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'name' ) ); ?>"><?php esc_html_e( 'Guest', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><?php esc_html_e( 'Phone', 'ovr-core' ); ?></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'total_stays' ) ); ?>"><?php esc_html_e( 'Stays', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'total_spend' ) ); ?>"><?php esc_html_e( 'Total Spend', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'last_stay' ) ); ?>"><?php esc_html_e( 'Last Stay', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $g ) :
                            $gid      = (int) $g['id'];
                            $profile  = add_query_arg( [ 'view' => 'profile', 'id' => $gid ], $page_url );
                            $edit     = add_query_arg( [ 'view' => 'edit', 'id' => $gid ], $page_url );
                            $del      = wp_nonce_url( admin_url( 'admin-post.php?action=ovr_guest_delete&id=' . $gid ), 'ovr_guest_delete_' . $gid );
                            $is_vip   = (float) $g['total_spend'] >= $threshold;
                        ?>
                            <tr>
                                <td>
                                    <div class="ovr-crm-name"><a href="<?php echo esc_url( $profile ); ?>"><?php echo esc_html( $g['name'] ?: '—' ); ?></a>
                                        <?php if ( $is_vip ) : ?><span class="ovr-crm-badge ovr-crm-badge--vip" style="margin-left:6px"><?php esc_html_e( 'VIP', 'ovr-core' ); ?></span><?php endif; ?>
                                    </div>
                                    <div class="ovr-crm-sub"><?php echo esc_html( $g['email'] ); ?></div>
                                </td>
                                <td class="ovr-crm-sub"><?php echo esc_html( $g['phone'] ?: '—' ); ?></td>
                                <td class="ovr-crm-num"><?php echo esc_html( number_format_i18n( (int) $g['total_stays'] ) ); ?></td>
                                <td class="ovr-crm-num"><?php echo esc_html( $symbol . number_format_i18n( (float) $g['total_spend'], 2 ) ); ?></td>
                                <td class="ovr-crm-sub"><?php echo $g['last_stay'] ? esc_html( date_i18n( 'M j, Y', strtotime( $g['last_stay'] ) ) ) : '—'; ?></td>
                                <td><span class="ovr-crm-badge ovr-crm-badge--<?php echo 'inactive' === $g['status'] ? 'inactive' : 'active'; ?>"><?php echo esc_html( ucfirst( $g['status'] ) ); ?></span></td>
                                <td>
                                    <a href="<?php echo esc_url( $profile ); ?>" class="ovr-crm-act" title="<?php esc_attr_e( 'View profile', 'ovr-core' ); ?>"><span class="material-symbols-outlined">visibility</span></a>
                                    <a href="<?php echo esc_url( $edit ); ?>" class="ovr-crm-act" title="<?php esc_attr_e( 'Edit', 'ovr-core' ); ?>"><span class="material-symbols-outlined">edit</span></a>
                                    <a href="<?php echo esc_url( $del ); ?>" class="ovr-crm-act ovr-crm-act--danger" title="<?php esc_attr_e( 'Remove', 'ovr-core' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this guest from the manifest?', 'ovr-core' ) ); ?>');"><span class="material-symbols-outlined">delete</span></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $max_pages > 1 ) : ?>
                    <div class="ovr-crm-pag">
                        <span><?php printf( esc_html__( 'Showing %1$d–%2$d of %3$d', 'ovr-core' ), ( ( $paged - 1 ) * $per_page ) + 1, min( $paged * $per_page, $total ), $total ); ?></span>
                        <div class="ovr-crm-pages">
                            <?php
                            $start = max( 1, $paged - 2 );
                            $end   = min( $max_pages, $start + 4 );
                            $start = max( 1, $end - 4 );
                            for ( $i = $start; $i <= $end; $i++ ) :
                                $u = add_query_arg( 'paged', $i, $segment ? add_query_arg( 'segment', $segment, $page_url ) : $page_url );
                            ?>
                                <a href="<?php echo esc_url( $u ); ?>" class="ovr-crm-page <?php echo $i === $paged ? 'ovr-crm-page--active' : ''; ?>"><?php echo esc_html( $i ); ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
