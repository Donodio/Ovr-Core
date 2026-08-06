<?php
/**
 * Support Center — dashboard + Tickets / Knowledge Base lists (Feature 12).
 *
 * @package OVR
 * @var string               $tab           'tickets' | 'kb'.
 * @var array                $data          ListTable::query() result.
 * @var \OVR\Admin\ListTable $list          List-table engine.
 * @var string               $page_url      Base URL (Tickets tab; preserves filters).
 * @var string               $base_url      Bare URL for active tab (drops filters — for Reset).
 * @var string               $new_url       New ticket / new article URL.
 * @var array                $stats         open/pending/resolved/kb counts.
 * @var array|null           $notice
 * Tickets tab extras: $kb_url, $csv_url, $status_labels, $priorities, $categories.
 * KB tab extras:      $tickets_url, $kb_statuses, $categories.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$rows      = $data['rows'];
$total     = (int) $data['total'];
$paged     = (int) $data['paged'];
$per_page  = (int) $data['per_page'];
$max_pages = (int) $data['max_pages'];
$is_kb     = ( 'kb' === $tab );
$tickets_tab_url = $page_url;
$kb_tab_url      = add_query_arg( 'tab', 'kb', $page_url );
$cur_status   = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
$cur_priority = sanitize_text_field( wp_unslash( $_GET['priority'] ?? '' ) );
$cur_cat      = sanitize_text_field( wp_unslash( $_GET['category'] ?? '' ) );
?>
<div class="wrap ovr-sup">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}
        #wpcontent{padding-left:0}
        .ovr-sup{--navy:#000961;--blue:#00A2E8;--blue-light:#e5f5fe;--navy-light:#e8eaf3;--gold:#DEAF0C;--gold-dark:#b8920a;--gold-light:#fef5d6;--green:#2E7D32;--green-light:#e4f4e4;--red:#B3261E;--red-light:#f9e4e2;--orange:#C7681C;--orange-light:#fdeede;--purple:#6A3FB8;--purple-light:#eee6fb;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--bg:#f0f3f7;--shadow-sm:0 1px 3px rgba(0,9,97,.06);--shadow-md:0 4px 12px rgba(0,9,97,.08);--r-sm:6px;--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink)}
        .ovr-sup,.ovr-sup *{box-sizing:border-box}
        .ovr-sup .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:22px}
        .ovr-sup-wrap{padding:24px 40px 48px}
        .ovr-sup-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:20px}
        .ovr-sup-head h1{font-size:30px;font-weight:700;margin:0;line-height:1.2}
        .ovr-sup-head p{margin:6px 0 0;font-size:16px;color:var(--muted)}
        .ovr-sup-actions{display:flex;gap:10px;flex-wrap:wrap;flex-shrink:0}
        .ovr-sup-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 22px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:46px;transition:all .2s}
        .ovr-sup-btn .material-symbols-outlined{font-size:20px}
        .ovr-sup-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold);box-shadow:0 2px 8px rgba(222,175,12,.25)}
        .ovr-sup-btn--primary:hover{background:var(--gold-dark);color:var(--navy)}
        .ovr-sup-btn--ghost{background:var(--surf);color:var(--navy);border-color:var(--gray-border);box-shadow:var(--shadow-sm)}
        .ovr-sup-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-sup-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        .ovr-sup-stat{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);padding:22px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-md);position:relative;overflow:hidden}
        .ovr-sup-stat::before{content:'';position:absolute;top:0;left:0;width:100%;height:3px}
        .ovr-sup-stat:nth-child(1)::before{background:var(--blue)}.ovr-sup-stat:nth-child(1) .ovr-sup-stat-ic{background:var(--blue-light);color:var(--blue)}
        .ovr-sup-stat:nth-child(2)::before{background:var(--orange)}.ovr-sup-stat:nth-child(2) .ovr-sup-stat-ic{background:var(--orange-light);color:var(--orange)}
        .ovr-sup-stat:nth-child(3)::before{background:var(--green)}.ovr-sup-stat:nth-child(3) .ovr-sup-stat-ic{background:var(--green-light);color:var(--green)}
        .ovr-sup-stat:nth-child(4)::before{background:var(--purple)}.ovr-sup-stat:nth-child(4) .ovr-sup-stat-ic{background:var(--purple-light);color:var(--purple)}
        .ovr-sup-stat-ic{width:50px;height:50px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .ovr-sup-stat-ic .material-symbols-outlined{font-size:26px}
        .ovr-sup-stat-v{font-size:30px;font-weight:700;line-height:1;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
        .ovr-sup-stat-l{font-size:14px;color:var(--muted);margin-top:4px}
        .ovr-sup-tabs{display:flex;gap:6px;margin-bottom:16px}
        .ovr-sup-tab{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9999px;font-size:14px;font-weight:600;text-decoration:none;color:var(--muted);background:var(--surf);border:1px solid var(--gray-border)}
        .ovr-sup-tab.is-active{background:var(--navy);color:#fff;border-color:var(--navy)}
        .ovr-sup-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-md)}
        .ovr-sup-toolbar{display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid var(--gray-border);background:var(--bg);flex-wrap:wrap}
        .ovr-sup-toolbar form{display:flex;align-items:center;gap:10px;flex:1;flex-wrap:wrap}
        .ovr-sup-search{position:relative;flex:1;min-width:200px;max-width:360px}
        .ovr-sup-search .material-symbols-outlined{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:20px;color:var(--gray-mid);pointer-events:none}
        .ovr-sup-search input{width:100%;background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:0 16px 0 44px;font-size:15px;font-family:inherit;height:46px;outline:none}
        .ovr-sup-search input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.12)}
        .ovr-sup-toolbar select{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:0 38px 0 14px;font-size:15px;font-family:inherit;height:46px;cursor:pointer;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%235F6B7A' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;min-width:140px}
        .ovr-sup-count{font-size:14px;color:var(--gray-mid);font-weight:500;background:var(--surf);padding:6px 14px;border-radius:9999px;border:1px solid var(--gray-border);margin-left:auto}
        .ovr-sup-table{width:100%;border-collapse:collapse}
        .ovr-sup-table th{text-align:left;padding:14px 18px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);background:#f8f9fb;border-bottom:2px solid var(--gray-border);white-space:nowrap}
        .ovr-sup-table th a{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
        .ovr-sup-table th a:hover{color:var(--blue)}
        .ovr-sup-table th .material-symbols-outlined{font-size:15px;opacity:.4}
        .ovr-sup-table td{padding:15px 18px;font-size:15px;border-bottom:1px solid var(--gray-border);vertical-align:middle}
        .ovr-sup-table tbody tr:hover td{background:rgba(0,162,232,.03)}
        .ovr-sup-subj{font-weight:600}
        .ovr-sup-subj a{color:var(--navy);text-decoration:none}
        .ovr-sup-subj a:hover{color:var(--blue)}
        .ovr-sup-sub{font-size:13px;color:var(--muted)}
        .ovr-sup-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:var(--r-sm);font-size:13px;font-weight:600;white-space:nowrap;text-transform:capitalize}
        .ovr-sup-badge--open,.ovr-sup-badge--published{background:var(--blue-light);color:var(--blue)}
        .ovr-sup-badge--in_progress,.ovr-sup-badge--draft{background:var(--orange-light);color:var(--orange)}
        .ovr-sup-badge--waiting{background:var(--gold-light);color:var(--gold-dark)}
        .ovr-sup-badge--resolved,.ovr-sup-badge--closed{background:var(--green-light);color:var(--green)}
        .ovr-sup-badge--archived{background:var(--gray-light);color:var(--muted)}
        .ovr-sup-pri{display:inline-flex;align-items:center;gap:5px;font-size:13px;font-weight:600;text-transform:capitalize}
        .ovr-sup-pri--low{color:var(--gray-mid)}.ovr-sup-pri--normal{color:var(--blue)}.ovr-sup-pri--high{color:var(--orange)}.ovr-sup-pri--urgent{color:var(--red)}
        .ovr-sup-cell-actions{display:flex;gap:4px}
        .ovr-sup-act{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:var(--r-sm);border:none;cursor:pointer;background:transparent;color:var(--gray-mid);text-decoration:none;transition:all .18s}
        .ovr-sup-act:hover{background:var(--gray-light);color:var(--ink)}
        .ovr-sup-act--edit:hover{background:var(--blue-light);color:var(--blue)}
        .ovr-sup-act--danger:hover{background:var(--red-light);color:var(--red)}
        .ovr-sup-empty{text-align:center;padding:70px 24px;color:var(--muted)}
        .ovr-sup-empty .material-symbols-outlined{font-size:56px;color:var(--gray-border);margin-bottom:14px;display:block}
        .ovr-sup-empty h3{font-size:18px;font-weight:600;color:var(--ink);margin:0 0 6px}
        .ovr-sup-pag{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-top:1px solid var(--gray-border);background:var(--bg);font-size:14px;color:var(--muted);flex-wrap:wrap;gap:12px}
        .ovr-sup-pages{display:flex;gap:4px}
        .ovr-sup-page{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;border-radius:var(--r-sm);font-weight:500;text-decoration:none;color:var(--muted);border:1px solid var(--gray-border);background:var(--surf);padding:0 10px}
        .ovr-sup-page:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-sup-page--active{background:var(--navy);color:#fff;border-color:var(--navy)}
        .ovr-sup-notice{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--r-md);font-size:15px;font-weight:500;margin-bottom:18px}
        .ovr-sup-notice--success{background:var(--green-light);border:1px solid #b8d8b8;color:var(--green)}
        .ovr-sup-notice--error{background:var(--red-light);border:1px solid #e6b8b4;color:var(--red)}
        @media(max-width:1100px){.ovr-sup-stats{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:782px){.ovr-sup-wrap{padding:18px 14px 32px}.ovr-sup-actions{width:100%}.ovr-sup-actions .ovr-sup-btn{flex:1}.ovr-sup-search{max-width:none;flex:1 1 100%}.ovr-sup-table td:nth-child(3),.ovr-sup-table th:nth-child(3){display:none}}
        @media(max-width:600px){.ovr-sup-stats{grid-template-columns:1fr 1fr}}
    </style>

    <div class="ovr-sup-wrap">
        <div class="ovr-sup-head">
            <div>
                <h1><?php esc_html_e( 'Support Center', 'ovr-core' ); ?></h1>
                <p><?php esc_html_e( 'Manage support tickets and the knowledge base.', 'ovr-core' ); ?></p>
            </div>
            <div class="ovr-sup-actions">
                <?php if ( ! $is_kb && ! empty( $csv_url ) ) : ?>
                    <a href="<?php echo esc_url( $csv_url ); ?>" class="ovr-sup-btn ovr-sup-btn--ghost"><span class="material-symbols-outlined">download</span><?php esc_html_e( 'Export CSV', 'ovr-core' ); ?></a>
                <?php endif; ?>
                <a href="<?php echo esc_url( $new_url ); ?>" class="ovr-sup-btn ovr-sup-btn--primary">
                    <span class="material-symbols-outlined">add</span><?php echo $is_kb ? esc_html__( 'Create Article', 'ovr-core' ) : esc_html__( 'New Ticket', 'ovr-core' ); ?>
                </a>
            </div>
        </div>

        <?php if ( $notice ) : ?>
            <div class="ovr-sup-notice ovr-sup-notice--<?php echo esc_attr( $notice['type'] ); ?>">
                <span class="material-symbols-outlined"><?php echo 'error' === $notice['type'] ? 'error' : 'check_circle'; ?></span>
                <span><?php echo esc_html( $notice['text'] ); ?></span>
            </div>
        <?php endif; ?>

        <div class="ovr-sup-stats">
            <div class="ovr-sup-stat">
                <div class="ovr-sup-stat-ic"><span class="material-symbols-outlined">confirmation_number</span></div>
                <div><div class="ovr-sup-stat-v"><?php echo esc_html( number_format_i18n( $stats['open'] ) ); ?></div><div class="ovr-sup-stat-l"><?php esc_html_e( 'Open Tickets', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-sup-stat">
                <div class="ovr-sup-stat-ic"><span class="material-symbols-outlined">pending</span></div>
                <div><div class="ovr-sup-stat-v"><?php echo esc_html( number_format_i18n( $stats['pending'] ) ); ?></div><div class="ovr-sup-stat-l"><?php esc_html_e( 'Pending Tickets', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-sup-stat">
                <div class="ovr-sup-stat-ic"><span class="material-symbols-outlined">task_alt</span></div>
                <div><div class="ovr-sup-stat-v"><?php echo esc_html( number_format_i18n( $stats['resolved'] ) ); ?></div><div class="ovr-sup-stat-l"><?php esc_html_e( 'Resolved Tickets', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-sup-stat">
                <div class="ovr-sup-stat-ic"><span class="material-symbols-outlined">menu_book</span></div>
                <div><div class="ovr-sup-stat-v"><?php echo esc_html( number_format_i18n( $stats['kb'] ) ); ?></div><div class="ovr-sup-stat-l"><?php esc_html_e( 'KB Articles', 'ovr-core' ); ?></div></div>
            </div>
        </div>

        <div class="ovr-sup-tabs">
            <a href="<?php echo esc_url( $tickets_tab_url ); ?>" class="ovr-sup-tab <?php echo $is_kb ? '' : 'is-active'; ?>"><span class="material-symbols-outlined">support_agent</span><?php esc_html_e( 'Tickets', 'ovr-core' ); ?></a>
            <a href="<?php echo esc_url( $kb_tab_url ); ?>" class="ovr-sup-tab <?php echo $is_kb ? 'is-active' : ''; ?>"><span class="material-symbols-outlined">menu_book</span><?php esc_html_e( 'Knowledge Base', 'ovr-core' ); ?></a>
        </div>

        <div class="ovr-sup-card">
            <div class="ovr-sup-toolbar">
                <form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
                    <input type="hidden" name="post_type" value="ovr_property">
                    <input type="hidden" name="page" value="<?php echo esc_attr( \OVR\Admin\SupportAdmin::PAGE_SLUG ); ?>">
                    <?php if ( $is_kb ) : ?><input type="hidden" name="tab" value="kb"><?php endif; ?>
                    <div class="ovr-sup-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" name="s" placeholder="<?php echo $is_kb ? esc_attr__( 'Search articles…', 'ovr-core' ) : esc_attr__( 'Search tickets…', 'ovr-core' ); ?>" value="<?php echo esc_attr( $data['search'] ); ?>">
                    </div>
                    <select name="status">
                        <option value=""><?php esc_html_e( 'Any Status', 'ovr-core' ); ?></option>
                        <?php foreach ( ( $is_kb ? $kb_statuses : $status_labels ) as $slug => $label ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cur_status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( ! $is_kb ) : ?>
                        <select name="priority">
                            <option value=""><?php esc_html_e( 'Any Priority', 'ovr-core' ); ?></option>
                            <?php foreach ( $priorities as $p ) : ?>
                                <option value="<?php echo esc_attr( $p ); ?>" <?php selected( $cur_priority, $p ); ?>><?php echo esc_html( ucfirst( $p ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <select name="category">
                        <option value=""><?php esc_html_e( 'Any Category', 'ovr-core' ); ?></option>
                        <?php foreach ( $categories as $c ) : ?>
                            <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $cur_cat, $c ); ?>><?php echo esc_html( ucwords( str_replace( '-', ' ', $c ) ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="ovr-sup-btn ovr-sup-btn--ghost"><span class="material-symbols-outlined">filter_alt</span><?php esc_html_e( 'Filter', 'ovr-core' ); ?></button>
                    <a href="<?php echo esc_url( $base_url ); ?>" class="ovr-sup-btn ovr-sup-btn--ghost" title="<?php esc_attr_e( 'Clear all filters and search', 'ovr-core' ); ?>"><span class="material-symbols-outlined">filter_alt_off</span><?php esc_html_e( 'Reset', 'ovr-core' ); ?></a>
                </form>
                <span class="ovr-sup-count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
            </div>

            <?php if ( empty( $rows ) ) : ?>
                <div class="ovr-sup-empty">
                    <span class="material-symbols-outlined"><?php echo $is_kb ? 'menu_book' : 'support_agent'; ?></span>
                    <h3><?php echo $is_kb ? esc_html__( 'No articles yet', 'ovr-core' ) : esc_html__( 'No tickets found', 'ovr-core' ); ?></h3>
                    <p><?php echo $is_kb ? esc_html__( 'Create your first knowledge base article.', 'ovr-core' ) : esc_html__( 'Tickets raised by users will appear here.', 'ovr-core' ); ?></p>
                </div>
            <?php elseif ( $is_kb ) : ?>
                <table class="ovr-sup-table">
                    <thead>
                        <tr>
                            <th><a href="<?php echo esc_url( $list->sort_url( $kb_tab_url, 'title' ) ); ?>"><?php esc_html_e( 'Title', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $kb_tab_url, 'category' ) ); ?>"><?php esc_html_e( 'Category', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $kb_tab_url, 'status' ) ); ?>"><?php esc_html_e( 'Status', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $kb_tab_url, 'sort_order' ) ); ?>"><?php esc_html_e( 'Order', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $a ) :
                            $aid    = (int) $a['id'];
                            $status = (string) $a['status'];
                            $edit   = add_query_arg( [ 'view' => 'kb-edit', 'id' => $aid ], $page_url );
                            $del    = wp_nonce_url( admin_url( 'admin-post.php?action=ovr_kb_delete&id=' . $aid ), 'ovr_kb_delete_' . $aid );
                            $next   = 'published' === $status ? 'archived' : 'published';
                            $toggle = wp_nonce_url( admin_url( 'admin-post.php?action=ovr_kb_status&id=' . $aid . '&status=' . $next ), 'ovr_kb_status_' . $aid );
                        ?>
                            <tr>
                                <td><div class="ovr-sup-subj"><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $a['title'] ?: __( '(untitled)', 'ovr-core' ) ); ?></a></div></td>
                                <td><?php echo esc_html( ucwords( str_replace( '-', ' ', (string) $a['category'] ) ) ); ?></td>
                                <td><span class="ovr-sup-badge ovr-sup-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $kb_statuses[ $status ] ?? ucfirst( $status ) ); ?></span></td>
                                <td class="ovr-sup-sub"><?php echo esc_html( number_format_i18n( (int) $a['sort_order'] ) ); ?></td>
                                <td>
                                    <div class="ovr-sup-cell-actions">
                                        <a href="<?php echo esc_url( $edit ); ?>" class="ovr-sup-act ovr-sup-act--edit" title="<?php esc_attr_e( 'Edit', 'ovr-core' ); ?>"><span class="material-symbols-outlined">edit</span></a>
                                        <a href="<?php echo esc_url( $toggle ); ?>" class="ovr-sup-act" title="<?php echo 'published' === $status ? esc_attr__( 'Archive', 'ovr-core' ) : esc_attr__( 'Publish', 'ovr-core' ); ?>"><span class="material-symbols-outlined"><?php echo 'published' === $status ? 'archive' : 'publish'; ?></span></a>
                                        <a href="<?php echo esc_url( $del ); ?>" class="ovr-sup-act ovr-sup-act--danger" title="<?php esc_attr_e( 'Trash', 'ovr-core' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Move this article to trash?', 'ovr-core' ) ); ?>');"><span class="material-symbols-outlined">delete</span></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <table class="ovr-sup-table">
                    <thead>
                        <tr>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'id' ) ); ?>"><?php esc_html_e( 'Ticket', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><?php esc_html_e( 'Requester', 'ovr-core' ); ?></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'priority' ) ); ?>"><?php esc_html_e( 'Priority', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'status' ) ); ?>"><?php esc_html_e( 'Status', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'updated_at' ) ); ?>"><?php esc_html_e( 'Updated', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $t ) :
                            $tid    = (int) $t['id'];
                            $status = (string) $t['status'];
                            $pri    = (string) $t['priority'];
                            $open   = add_query_arg( [ 'view' => 'ticket', 'id' => $tid ], $page_url );
                            $del    = wp_nonce_url( admin_url( 'admin-post.php?action=ovr_ticket_delete&id=' . $tid ), 'ovr_ticket_delete_' . $tid );
                            $req    = $t['user_id'] ? get_the_author_meta( 'display_name', (int) $t['user_id'] ) : __( 'Guest', 'ovr-core' );
                        ?>
                            <tr>
                                <td>
                                    <div class="ovr-sup-subj"><a href="<?php echo esc_url( $open ); ?>">#<?php echo esc_html( $tid ); ?> · <?php echo esc_html( $t['subject'] ?: __( '(no subject)', 'ovr-core' ) ); ?></a></div>
                                    <div class="ovr-sup-sub"><?php echo esc_html( ucwords( str_replace( '-', ' ', (string) $t['category'] ) ) ); ?></div>
                                </td>
                                <td><?php echo esc_html( $req ); ?></td>
                                <td><span class="ovr-sup-pri ovr-sup-pri--<?php echo esc_attr( $pri ); ?>"><span class="material-symbols-outlined">flag</span><?php echo esc_html( ucfirst( $pri ) ); ?></span></td>
                                <td><span class="ovr-sup-badge ovr-sup-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_labels[ $status ] ?? ucfirst( $status ) ); ?></span></td>
                                <td class="ovr-sup-sub"><?php echo esc_html( $t['updated_at'] ? date_i18n( 'M j, Y', strtotime( $t['updated_at'] ) ) : '—' ); ?></td>
                                <td>
                                    <div class="ovr-sup-cell-actions">
                                        <a href="<?php echo esc_url( $open ); ?>" class="ovr-sup-act ovr-sup-act--edit" title="<?php esc_attr_e( 'Open', 'ovr-core' ); ?>"><span class="material-symbols-outlined">open_in_new</span></a>
                                        <a href="<?php echo esc_url( $del ); ?>" class="ovr-sup-act ovr-sup-act--danger" title="<?php esc_attr_e( 'Trash', 'ovr-core' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Move this ticket to trash?', 'ovr-core' ) ); ?>');"><span class="material-symbols-outlined">delete</span></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ( $max_pages > 1 ) :
                $pag_base = $is_kb ? $kb_tab_url : $page_url; ?>
                <div class="ovr-sup-pag">
                    <span><?php printf( esc_html__( 'Showing %1$d–%2$d of %3$d', 'ovr-core' ), ( ( $paged - 1 ) * $per_page ) + 1, min( $paged * $per_page, $total ), $total ); ?></span>
                    <div class="ovr-sup-pages">
                        <?php
                        $start = max( 1, $paged - 2 );
                        $end   = min( $max_pages, $start + 4 );
                        $start = max( 1, $end - 4 );
                        for ( $i = $start; $i <= $end; $i++ ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $pag_base ) ); ?>" class="ovr-sup-page <?php echo $i === $paged ? 'ovr-sup-page--active' : ''; ?>"><?php echo esc_html( $i ); ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
