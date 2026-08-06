<?php
/**
 * Users Management — admin page.
 *
 * @package OVR
 * @var \WP_User[] $users     User objects.
 * @var array      $plans     Subscription plans from Plans::get_plans().
 * @var array      $stats     Computed stat values (total_users, active_subs, etc.).
 * @var string     $search    Current search query.
 * @var string     $role      Current role filter.
 * @var int        $paged     Current page number.
 * @var int        $max_pages Total pages.
 * @var int        $total     Total matching users.
 * @var string     $orderby   Current sort column.
 * @var string     $order     Current sort direction.
 * @var string     $page_url  Base URL for this screen.
 * @var array|null $notice    Result notice, or null.
 * @var string     $toggle_url admin-post.php URL for status toggle.
 * @var string     $csv_url   Export CSV URL.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Subscription\UserSubscription;
?>
<div class="wrap ovr-users">

    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}
        #wpcontent{padding-left:0}

        @font-face{font-family:'OVR Atkinson';font-style:normal;font-weight:400 700;src:url(https://fonts.gstatic.com/s/atkinsonhyperlegiblenext/v8/atkinsonhyperlegiblenext.woff2) format('woff2');font-display:swap}

        .ovr-users{--navy:#000961;--navy-hover:#000748;--navy-light:#e8eaf3;--navy-glow:rgba(0,9,97,.12);--blue:#00A2E8;--blue-hover:#0090cc;--blue-light:#e5f5fe;--gold:#DEAF0C;--gold-light:#fef5d6;--gold-dark:#b8920a;--green:#2E7D32;--green-light:#e4f4e4;--red:#B3261E;--red-light:#f9e4e2;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#FFFFFF;--bg:#f0f3f7;--shadow-sm:0 1px 3px rgba(0,9,97,.06),0 1px 2px rgba(0,9,97,.04);--shadow-md:0 4px 12px rgba(0,9,97,.08),0 2px 4px rgba(0,9,97,.04);--shadow-lg:0 8px 32px rgba(0,9,97,.1),0 4px 12px rgba(0,9,97,.06);--radius-sm:6px;--radius-md:8px;--radius-lg:12px;--radius-xl:16px;font-family:'OVR Atkinson','Atkinson Hyperlegible Next',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink);-webkit-font-smoothing:antialiased}
        .ovr-users,.ovr-users *{box-sizing:border-box}
        .ovr-users .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;line-height:1;vertical-align:middle;font-size:24px}

        .ovr-u-wrap{padding:24px 40px 48px}

        .ovr-u-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:20px}
        .ovr-u-head h1{font-size:30px;font-weight:700;letter-spacing:-.01em;margin:0;padding:0;line-height:1.2;color:var(--ink)}
        .ovr-u-head p{margin:6px 0 0;font-size:16px;color:var(--muted)}
        .ovr-u-head p span{display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:600;color:var(--navy);margin-left:10px}
        .ovr-u-head p span .material-symbols-outlined{font-size:17px}

        .ovr-u-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;flex-shrink:0}

        .ovr-u-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 24px;border-radius:var(--radius-md);font-size:15px;font-weight:600;text-decoration:none;line-height:1;border:1px solid transparent;cursor:pointer;font-family:inherit;white-space:nowrap;min-height:48px;transition:all .2s ease;letter-spacing:.01em}
        .ovr-u-btn .material-symbols-outlined{font-size:21px}
        .ovr-u-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold);box-shadow:0 2px 8px rgba(222,175,12,.25)}
        .ovr-u-btn--primary:hover{background:var(--gold-dark);border-color:var(--gold-dark);color:var(--navy);box-shadow:0 4px 16px rgba(222,175,12,.35);transform:translateY(-1px)}
        .ovr-u-btn--ghost{background:transparent;color:var(--navy);border-color:var(--gray-border);box-shadow:var(--shadow-sm)}
        .ovr-u-btn--ghost:hover{border-color:var(--blue);color:var(--blue);box-shadow:0 2px 8px var(--navy-glow);transform:translateY(-1px)}
        .ovr-u-btn--subtle{background:var(--surf);color:var(--ink);border-color:var(--gray-border);box-shadow:var(--shadow-sm)}
        .ovr-u-btn--subtle:hover{border-color:var(--blue);color:var(--blue);box-shadow:0 2px 8px var(--navy-glow);transform:translateY(-1px)}

        .ovr-u-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        .ovr-u-stat{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-lg);padding:24px 24px;display:flex;align-items:center;gap:18px;box-shadow:var(--shadow-md);transition:all .25s ease;position:relative;overflow:hidden}
        .ovr-u-stat::before{content:'';position:absolute;top:0;left:0;width:100%;height:3px}
        .ovr-u-stat:hover{box-shadow:var(--shadow-lg);transform:translateY(-2px)}
        .ovr-u-stat:nth-child(1)::before{background:var(--navy)}
        .ovr-u-stat:nth-child(1) .ovr-u-stat-icon{background:var(--navy-light);color:var(--navy)}
        .ovr-u-stat:nth-child(2)::before{background:var(--green)}
        .ovr-u-stat:nth-child(2) .ovr-u-stat-icon{background:var(--green-light);color:var(--green)}
        .ovr-u-stat:nth-child(3)::before{background:var(--blue)}
        .ovr-u-stat:nth-child(3) .ovr-u-stat-icon{background:var(--blue-light);color:var(--blue)}
        .ovr-u-stat:nth-child(4)::before{background:var(--gold)}
        .ovr-u-stat:nth-child(4) .ovr-u-stat-icon{background:var(--gold-light);color:var(--gold)}
        .ovr-u-stat-icon{width:52px;height:52px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .ovr-u-stat-icon .material-symbols-outlined{font-size:28px}
        .ovr-u-stat-info{display:flex;flex-direction:column;gap:4px}
        .ovr-u-stat-value{font-size:34px;font-weight:700;line-height:1;color:var(--ink);letter-spacing:-.02em;font-variant-numeric:tabular-nums}
        .ovr-u-stat-label{font-size:15px;color:var(--muted);font-weight:500}

        .ovr-u-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-md)}

        .ovr-u-toolbar{display:flex;align-items:center;gap:12px;padding:20px 24px;border-bottom:1px solid var(--gray-border);background:var(--bg);flex-wrap:wrap}
        .ovr-u-toolbar form{display:flex;align-items:center;gap:10px;flex:1;flex-wrap:wrap}
        .ovr-u-search{position:relative;flex:1;min-width:200px;max-width:400px}
        .ovr-u-search .material-symbols-outlined{position:absolute;left:15px;top:50%;transform:translateY(-50%);font-size:21px;color:var(--gray-mid);pointer-events:none;transition:color .2s}
        .ovr-u-search:focus-within .material-symbols-outlined{color:var(--blue)}
        .ovr-u-search input[type=search]{width:100%;background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-md);padding:0 16px 0 46px;font-size:16px;font-family:inherit;color:var(--ink);outline:none;height:48px;min-height:48px;transition:border-color .2s,box-shadow .2s}
        .ovr-u-search input[type=search]:focus{border-color:var(--blue);box-shadow:0 0 0 3px var(--navy-glow)}
        .ovr-u-search input[type=search]::placeholder{color:var(--gray-mid)}
        .ovr-u-filter select{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-md);padding:0 42px 0 16px;font-size:16px;font-family:inherit;color:var(--ink);outline:none;cursor:pointer;height:48px;min-height:48px;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%235F6B7A' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 13px center;transition:border-color .2s,box-shadow .2s;min-width:160px}
        .ovr-u-filter select:focus{border-color:var(--blue);box-shadow:0 0 0 3px var(--navy-glow)}
        .ovr-u-toolbar .ovr-u-btn{height:48px;min-height:48px;padding:0 24px}
        .ovr-u-total-count{font-size:15px;color:var(--gray-mid);white-space:nowrap;font-weight:500;background:var(--surf);padding:6px 16px;border-radius:9999px;border:1px solid var(--gray-border);margin-left:auto}

        .ovr-u-table{width:100%;border-collapse:collapse}
        .ovr-u-table th{text-align:left;padding:15px 20px;font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);background:#f8f9fb;border-bottom:2px solid var(--gray-border);white-space:nowrap;user-select:none}
        .ovr-u-table th a{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:color .15s}
        .ovr-u-table th a:hover{color:var(--blue)}
        .ovr-u-table th .sort-indicator{font-size:16px;opacity:.4;transition:opacity .15s}
        .ovr-u-table th a:hover .sort-indicator{opacity:.8}
        .ovr-u-table td{padding:18px 20px;font-size:15px;color:var(--ink);border-bottom:1px solid var(--gray-border);vertical-align:middle}
        .ovr-u-table tbody tr:last-child td{border-bottom:none}
        .ovr-u-table tbody tr:hover td{background:rgba(0,162,232,.03)}
        .ovr-u-table tbody tr:nth-child(even) td{background:#fafbfc}
        .ovr-u-table tbody tr:nth-child(even):hover td{background:rgba(0,162,232,.03)}

        .ovr-u-user{display:flex;align-items:center;gap:14px}
        .ovr-u-user-avatar{width:46px;height:46px;border-radius:50%;flex-shrink:0;overflow:hidden;background:var(--gray-light);border:2px solid var(--surf);box-shadow:0 0 0 1px var(--gray-border);position:relative}
        .ovr-u-user-avatar img{width:100%;height:100%;object-fit:cover;display:block}
        .ovr-u-user-info{display:flex;flex-direction:column;gap:3px;min-width:0}
        .ovr-u-user-name{font-weight:600;font-size:16px;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .ovr-u-user-email{font-size:14px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

        .ovr-u-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:var(--radius-sm);font-size:14px;font-weight:600;line-height:1;white-space:nowrap;letter-spacing:.01em}
        .ovr-u-badge .material-symbols-outlined{font-size:16px}
        .ovr-u-badge--active{background:var(--green-light);color:var(--green)}
        .ovr-u-badge--inactive{background:var(--red-light);color:var(--red)}
        .ovr-u-badge--pending{background:var(--gold-light);color:var(--gold-dark)}
        .ovr-u-badge--editing{background:var(--blue-light);color:var(--blue)}
        .ovr-u-badge--popular{background:var(--gold-light);color:var(--gold-dark);font-size:12px;padding:2px 9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}

        .ovr-u-plan{font-size:15px;color:var(--ink)}
        .ovr-u-plan-name{font-weight:600;margin-bottom:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
        .ovr-u-plan-price{font-size:14px;color:var(--muted)}
        .ovr-u-plan-price strong{color:var(--ink);font-weight:600}

        .ovr-u-listings{font-weight:700;font-size:16px;color:var(--ink);font-variant-numeric:tabular-nums}
        .ovr-u-listings small{font-weight:400;font-size:14px;color:var(--gray-mid);margin-left:4px}
        .ovr-u-listings-bar{display:flex;gap:6px;align-items:center;margin-top:5px}
        .ovr-u-listings-track{flex:1;max-width:80px;height:5px;background:var(--gray-light);border-radius:9999px;overflow:hidden}
        .ovr-u-listings-fill{height:100%;border-radius:9999px;background:var(--blue);transition:width .4s ease}

        .ovr-u-activity{font-size:15px;color:var(--muted);display:flex;flex-direction:column;gap:2px}
        .ovr-u-activity-date{font-size:13px;color:var(--gray-mid)}

        .ovr-u-actions-cell{display:flex;gap:4px;align-items:center;opacity:.7;transition:opacity .2s}
        tr:hover .ovr-u-actions-cell{opacity:1}
        .ovr-u-action-btn{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:var(--radius-sm);border:none;cursor:pointer;background:transparent;color:var(--gray-mid);text-decoration:none;transition:all .18s ease}
        .ovr-u-action-btn:hover{background:var(--gray-light);color:var(--ink);box-shadow:var(--shadow-sm)}
        .ovr-u-action-btn .material-symbols-outlined{font-size:21px}
        .ovr-u-action-btn--listings:hover{background:var(--green-light,#e3f4ea);color:var(--green,#006c4a)}
        .ovr-u-action-btn--edit:hover{background:var(--blue-light);color:var(--blue)}
        .ovr-u-user-meta{font-size:12px;color:var(--muted);font-variant-numeric:tabular-nums}
        /* Role + Account Type pills (Area D). Each carries its own text label, so
           the distinction never depends on colour alone. */
        .ovr-u-roles{display:flex;flex-wrap:wrap;gap:4px;margin-top:2px}
        .ovr-u-role{display:inline-flex;align-items:center;padding:1px 7px;border-radius:var(--radius-sm);font-size:11px;font-weight:600;line-height:1.6;white-space:nowrap;border:1px solid transparent}
        .ovr-u-role--admin{background:var(--blue-light);color:var(--blue)}
        .ovr-u-role--user{background:var(--gray-light);color:var(--muted);border-color:var(--gray-border)}
        .ovr-u-role--landlord{background:var(--green-light);color:var(--green)}
        .ovr-u-role--subscriber{background:var(--gray-light);color:var(--muted);border-color:var(--gray-border)}
        .ovr-u-verif{display:inline-flex;align-items:center;gap:5px;font-size:13px;font-weight:600;padding:3px 10px;border-radius:999px;border:1px solid transparent;white-space:nowrap}
        .ovr-u-verif .material-symbols-outlined{font-size:17px}
        .ovr-u-verif--verified_homeowner,.ovr-u-verif--registered_pm{background:#e6f4ea;color:#1e7e34;border-color:#bfe3c8}
        .ovr-u-verif--not_verified{background:var(--gray-light);color:var(--muted);border-color:var(--gray-border)}
        .ovr-u-phone{font-size:14px;color:var(--ink);text-decoration:none;font-variant-numeric:tabular-nums;white-space:nowrap}
        .ovr-u-phone:hover{color:var(--blue);text-decoration:underline}
        .ovr-u-email{font-size:14px;color:var(--navy);text-decoration:none;word-break:break-word}
        .ovr-u-email:hover{color:var(--blue);text-decoration:underline}
        .ovr-u-muted{color:var(--muted)}
        .ovr-u-balance{font-size:14px;font-weight:600;color:var(--muted);font-variant-numeric:tabular-nums}
        .ovr-u-balance.is-positive{color:#1e7e34}
        .ovr-u-action-btn--loginas:hover{background:#fff4e5;color:#b5670a}
        .ovr-u-action-btn--suspend:hover{background:var(--gold-light);color:var(--gold-dark)}
        .ovr-u-action-btn--danger:hover{background:var(--red-light);color:var(--red)}

        .ovr-u-empty{text-align:center;padding:80px 24px;color:var(--muted)}
        .ovr-u-empty .material-symbols-outlined{font-size:60px;color:var(--gray-border);margin-bottom:16px;display:block}
        .ovr-u-empty h3{font-size:19px;font-weight:600;color:var(--ink);margin:0 0 6px}
        .ovr-u-empty p{font-size:16px;margin:0;color:var(--muted)}

        .ovr-u-pagination{display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-top:1px solid var(--gray-border);background:var(--bg);font-size:15px;color:var(--muted);flex-wrap:wrap;gap:12px}
        .ovr-u-pagination-pages{display:flex;gap:4px;align-items:center}
        .ovr-u-page{display:inline-flex;align-items:center;justify-content:center;min-width:42px;height:42px;border-radius:var(--radius-sm);font-size:15px;font-weight:500;text-decoration:none;color:var(--muted);border:1px solid var(--gray-border);background:var(--surf);padding:0 10px;cursor:pointer;transition:all .18s ease}
        .ovr-u-page:hover{background:var(--gray-light);border-color:var(--blue);color:var(--blue);z-index:1}
        .ovr-u-page--active{background:var(--navy);color:#fff;border-color:var(--navy);box-shadow:0 2px 8px var(--navy-glow)}
        .ovr-u-page--active:hover{background:var(--navy-hover);color:#fff;border-color:var(--navy-hover)}

        @media (max-width:1100px){
            .ovr-u-stats{grid-template-columns:repeat(2,1fr);gap:14px}
        }
        @media (max-width:782px){
            .ovr-u-wrap{padding:18px 14px 32px}
            .ovr-u-head h1{font-size:26px}
            .ovr-u-stats{gap:12px}
            .ovr-u-actions{width:100%}
            .ovr-u-actions .ovr-u-btn{flex:1;justify-content:center;min-height:46px}
            .ovr-u-toolbar form{display:flex;flex:1;gap:10px;flex-wrap:wrap}
            .ovr-u-search{max-width:none;flex:1 1 100%}
            .ovr-u-filter select{width:100%;min-width:auto}
            .ovr-u-toolbar .ovr-u-btn{flex:1}
            .ovr-u-total-count{width:100%;text-align:center;margin:0}
            .ovr-u-table td:nth-child(6),.ovr-u-table th:nth-child(6),
            .ovr-u-table td:nth-child(7),.ovr-u-table th:nth-child(7){display:none}
            .ovr-u-stat-value{font-size:28px}
        }
        @media (max-width:600px){
            .ovr-u-stats{grid-template-columns:1fr 1fr;gap:10px}
            .ovr-u-stat{padding:18px 18px}
            .ovr-u-stat-icon{width:44px;height:44px}
            .ovr-u-stat-icon .material-symbols-outlined{font-size:24px}
            .ovr-u-stat-value{font-size:24px}
        }

        @media (prefers-reduced-motion:reduce){
            .ovr-u-stat,.ovr-u-btn,.ovr-u-action-btn,.ovr-u-page{transition:none}
            .ovr-u-listings-fill{transition:none}
        }
    </style>

    <?php if ( $notice ) : ?>
        <div class="ovr-u-notice" style="padding:24px 40px 0">
            <div style="display:flex;align-items:center;gap:10px;padding:15px 20px;border-radius:var(--radius-md);font-size:16px;font-weight:500;background:var(--green-light);border:1px solid #b8d8b8;color:var(--green)">
                <span class="material-symbols-outlined" style="font-size:22px">check_circle</span>
                <span><?php echo esc_html( $notice['text'] ); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="ovr-u-wrap">
        <div class="ovr-u-head">
            <div>
                <h1><?php esc_html_e( 'Users Management', 'ovr-core' ); ?></h1>
                <p><?php esc_html_e( 'Manage platform users, subscriptions, and account access.', 'ovr-core' ); ?>
                    <span><span class="material-symbols-outlined">people</span> <?php echo esc_html( number_format_i18n( $stats['total_users'] ) ); ?> registered</span>
                </p>
            </div>
            <div class="ovr-u-actions">
                <a href="<?php echo esc_url( $csv_url ); ?>" class="ovr-u-btn ovr-u-btn--ghost">
                    <span class="material-symbols-outlined">download</span>
                    <?php esc_html_e( 'Export CSV', 'ovr-core' ); ?>
                </a>
                <button type="button" class="ovr-u-btn ovr-u-btn--ghost" id="ovr-u-toggle-filters">
                    <span class="material-symbols-outlined">filter_alt</span>
                    <?php esc_html_e( 'Advanced Filters', 'ovr-core' ); ?>
                </button>
                <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="ovr-u-btn ovr-u-btn--primary">
                    <span class="material-symbols-outlined">person_add</span>
                    <?php esc_html_e( 'Add New User', 'ovr-core' ); ?>
                </a>
            </div>
        </div>

        <div class="ovr-u-stats">
            <div class="ovr-u-stat">
                <div class="ovr-u-stat-icon"><span class="material-symbols-outlined">people</span></div>
                <div class="ovr-u-stat-info">
                    <span class="ovr-u-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_users'] ) ); ?></span>
                    <span class="ovr-u-stat-label"><?php esc_html_e( 'Total Users', 'ovr-core' ); ?></span>
                </div>
            </div>
            <div class="ovr-u-stat">
                <div class="ovr-u-stat-icon"><span class="material-symbols-outlined">verified</span></div>
                <div class="ovr-u-stat-info">
                    <span class="ovr-u-stat-value"><?php echo esc_html( number_format_i18n( $stats['active_subs'] ) ); ?></span>
                    <span class="ovr-u-stat-label"><?php esc_html_e( 'Active Subscriptions', 'ovr-core' ); ?></span>
                </div>
            </div>
            <div class="ovr-u-stat">
                <div class="ovr-u-stat-icon"><span class="material-symbols-outlined">badge</span></div>
                <div class="ovr-u-stat-info">
                    <span class="ovr-u-stat-value"><?php echo esc_html( number_format_i18n( $stats['property_managers'] ) ); ?></span>
                    <span class="ovr-u-stat-label"><?php esc_html_e( 'Property Managers', 'ovr-core' ); ?></span>
                </div>
            </div>
            <div class="ovr-u-stat">
                <div class="ovr-u-stat-icon"><span class="material-symbols-outlined">gpp_maybe</span></div>
                <div class="ovr-u-stat-info">
                    <span class="ovr-u-stat-value"><?php echo esc_html( number_format_i18n( $stats['not_verified'] ) ); ?></span>
                    <span class="ovr-u-stat-label"><?php esc_html_e( 'Not Yet Verified', 'ovr-core' ); ?></span>
                </div>
            </div>
        </div>

        <div class="ovr-u-card">
            <div class="ovr-u-toolbar">
                <form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
                    <input type="hidden" name="post_type" value="ovr_property">
                    <input type="hidden" name="page" value="<?php echo esc_attr( \OVR\Admin\UsersAdmin::PAGE_SLUG ); ?>">
                    <div class="ovr-u-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" name="s" placeholder="<?php esc_attr_e( 'Search by name, email...', 'ovr-core' ); ?>" value="<?php echo esc_attr( $search ); ?>">
                    </div>
                    <div class="ovr-u-filter">
                        <select name="role">
                            <option value=""><?php esc_html_e( 'All Users', 'ovr-core' ); ?></option>
                            <option value="administrator" <?php selected( $role, 'administrator' ); ?>><?php esc_html_e( 'Administrators', 'ovr-core' ); ?></option>
                            <option value="user" <?php selected( $role, 'user' ); ?>><?php esc_html_e( 'Users', 'ovr-core' ); ?></option>
                        </select>
                    </div>
                    <div class="ovr-u-filter">
                        <select name="subscription">
                            <option value=""><?php esc_html_e( 'All Subscriptions', 'ovr-core' ); ?></option>
                            <?php foreach ( (array) $plans as $plan_slug => $plan ) :
                                $plan_label = is_array( $plan ) ? ( $plan['name'] ?? $plan_slug ) : (string) $plan;
                            ?>
                                <option value="<?php echo esc_attr( $plan_slug ); ?>" <?php selected( $subscription ?? '', $plan_slug ); ?>><?php echo esc_html( $plan_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ovr-u-filter">
                        <select name="status">
                            <option value=""><?php esc_html_e( 'Any Status', 'ovr-core' ); ?></option>
                            <option value="active" <?php selected( $status ?? '', 'active' ); ?>><?php esc_html_e( 'Active', 'ovr-core' ); ?></option>
                            <option value="active_pending" <?php selected( $status ?? '', 'active_pending' ); ?>><?php esc_html_e( 'Active – Pending Renewal', 'ovr-core' ); ?></option>
                            <option value="inactive" <?php selected( $status ?? '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></option>
                            <option value="inactive_pending" <?php selected( $status ?? '', 'inactive_pending' ); ?>><?php esc_html_e( 'Inactive – Pending Renewal', 'ovr-core' ); ?></option>
                        </select>
                    </div>
                    <div class="ovr-u-filter">
                        <select name="verification">
                            <option value=""><?php esc_html_e( 'Any Verification', 'ovr-core' ); ?></option>
                            <?php foreach ( \OVR\Core\Verification::statuses() as $vkey => $vlabel ) : ?>
                                <option value="<?php echo esc_attr( $vkey ); ?>" <?php selected( $verification ?? '', $vkey ); ?>><?php echo esc_html( $vlabel ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="ovr-u-btn ovr-u-btn--subtle">
                        <span class="material-symbols-outlined">search</span>
                        <?php esc_html_e( 'Search', 'ovr-core' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $page_url ); ?>" class="ovr-u-btn ovr-u-btn--subtle" aria-label="<?php esc_attr_e( 'Reset filters', 'ovr-core' ); ?>">
                        <span class="material-symbols-outlined">filter_alt_off</span>
                        <?php esc_html_e( 'Reset', 'ovr-core' ); ?>
                    </a>
                </form>
                <span class="ovr-u-total-count">
                    <?php printf( esc_html__( '%d user(s)', 'ovr-core' ), (int) $total ); ?>
                </span>
            </div>

            <?php if ( empty( $users ) ) : ?>
                <div class="ovr-u-empty">
                    <span class="material-symbols-outlined">person_search</span>
                    <h3><?php esc_html_e( 'No users found', 'ovr-core' ); ?></h3>
                    <p><?php esc_html_e( 'Try adjusting your search or filter criteria.', 'ovr-core' ); ?></p>
                </div>
            <?php else : ?>
                <table class="ovr-u-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'User', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Phone', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Verification', 'ovr-core' ); ?></th>
                            <th>
                                <a href="<?php echo esc_url( add_query_arg( [ 'orderby' => 'subscription', 'order' => 'DESC' === $order ? 'ASC' : 'DESC' ], $page_url ) ); ?>">
                                    <?php esc_html_e( 'Subscription', 'ovr-core' ); ?>
                                    <span class="sort-indicator material-symbols-outlined">unfold_more</span>
                                </a>
                            </th>
                            <th><?php esc_html_e( 'Listings', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Balance', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $users as $user ) :
                            $plan_slug      = UserSubscription::get_plan_slug( (int) $user->ID );
                            $plan_data      = $plans[ $plan_slug ] ?? null;
                            $listing_count  = UserSubscription::get_listing_count( (int) $user->ID );
                            $acct_status    = get_user_meta( (int) $user->ID, 'ovr_account_status', true ) ?: 'active';
                            $is_active      = ( 'active' === $acct_status );
                            // "Pending renewal" = an expired subscription awaiting renewal.
                            $sub_status     = UserSubscription::get_status( (int) $user->ID );
                            $is_pending     = ( UserSubscription::STATUS_EXPIRED === $sub_status );
                            $sub_expires    = (string) get_user_meta( (int) $user->ID, UserSubscription::META_EXPIRES, true );
                            $avatar         = get_avatar( $user->ID, 46 );
                            $edit_url       = admin_url( 'user-edit.php?user_id=' . (int) $user->ID );
                            $listings_url   = admin_url( 'admin.php?page=ovr-properties&author=' . (int) $user->ID );
                            $plan_max       = $plan_data['max_listings'] ?? null;
                            $listing_pct    = $plan_max && $plan_max > 0 ? min( 100, round( ( $listing_count / $plan_max ) * 100 ) ) : null;
                            $verif_status   = \OVR\Core\Verification::get( (int) $user->ID );
                            $phone          = (string) get_user_meta( (int) $user->ID, 'ovr_phone', true );
                            // Role (Admin vs User) and Account Type (Landlord vs Subscriber) pills.
                            $user_roles     = (array) $user->roles;
                            $is_admin_role  = in_array( 'administrator', $user_roles, true );
                            $is_landlord    = in_array( 'ovr_landlord', $user_roles, true );
                            $balance        = (float) get_user_meta( (int) $user->ID, \OVR\Payment\Wallet::META_BALANCE, true );
                            $login_as_url   = wp_nonce_url(
                                add_query_arg( [ 'action' => 'ovr_login_as_user', 'user_id' => (int) $user->ID ], $toggle_url ),
                                'ovr_login_as_user'
                            );

                            // Combined status badge (Active / Inactive, plus a
                            // "Pending Renewal" state when the subscription lapsed).
                            $status_base  = $is_active ? __( 'Active', 'ovr-core' ) : __( 'Inactive', 'ovr-core' );
                            if ( $is_pending ) {
                                $status_class = 'pending';
                                $status_icon  = 'autorenew';
                                $status_text  = sprintf( /* translators: %s: Active or Inactive */ __( '%s – Pending Renewal', 'ovr-core' ), $status_base );
                            } else {
                                $status_class = $is_active ? 'active' : 'inactive';
                                $status_icon  = $is_active ? 'check_circle' : 'cancel';
                                $status_text  = $status_base;
                            }
                        ?>
                            <tr>
                                <td>
                                    <div class="ovr-u-user">
                                        <div class="ovr-u-user-avatar">
                                            <?php echo $avatar; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
                                        </div>
                                        <div class="ovr-u-user-info">
                                            <span class="ovr-u-user-name"><?php echo esc_html( $user->display_name ); ?></span>
                                            <span class="ovr-u-user-email"><?php echo esc_html( $user->user_email ); ?></span>
                                            <span class="ovr-u-user-meta">#<?php echo (int) $user->ID; ?> · <?php echo esc_html( $user->user_login ); ?></span>
                                            <span class="ovr-u-roles">
                                                <span class="ovr-u-role ovr-u-role--<?php echo $is_admin_role ? 'admin' : 'user'; ?>">
                                                    <?php echo $is_admin_role ? esc_html__( 'Admin', 'ovr-core' ) : esc_html__( 'User', 'ovr-core' ); ?>
                                                </span>
                                                <span class="ovr-u-role ovr-u-role--<?php echo $is_landlord ? 'landlord' : 'subscriber'; ?>">
                                                    <?php echo $is_landlord ? esc_html__( 'Landlord', 'ovr-core' ) : esc_html__( 'Subscriber', 'ovr-core' ); ?>
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ( $phone ) : ?>
                                        <a class="ovr-u-phone" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
                                    <?php else : ?>
                                        <span class="ovr-u-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="ovr-u-verif ovr-u-verif--<?php echo esc_attr( $verif_status ); ?>" title="<?php echo esc_attr( \OVR\Core\Verification::label( $verif_status ) ); ?>">
                                        <span class="material-symbols-outlined"><?php echo esc_html( \OVR\Core\Verification::icon( $verif_status ) ); ?></span>
                                        <?php echo esc_html( \OVR\Core\Verification::label( $verif_status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( $plan_data ) : ?>
                                        <div class="ovr-u-plan">
                                            <div class="ovr-u-plan-name">
                                                <span><?php echo esc_html( $plan_data['name'] ?? '' ); ?></span>
                                                <?php if ( ! empty( $plan_data['is_popular'] ) ) : ?>
                                                    <span class="ovr-u-badge ovr-u-badge--popular"><?php esc_html_e( 'Popular', 'ovr-core' ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="ovr-u-plan-price">
                                                <?php if ( $sub_expires ) : ?>
                                                    <?php printf(
                                                        /* translators: %s: subscription expiry date */
                                                        esc_html__( 'Expires %s', 'ovr-core' ),
                                                        '<strong>' . esc_html( mysql2date( get_option( 'date_format' ), $sub_expires ) ) . '</strong>'
                                                    ); ?>
                                                <?php else : ?>
                                                    <span class="ovr-u-muted"><?php esc_html_e( 'No expiry', 'ovr-core' ); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php else : ?>
                                        <span class="ovr-u-badge ovr-u-badge--inactive" style="background:var(--gray-light);color:var(--muted);border:1px solid var(--gray-border)"><?php echo esc_html( $plan_slug ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <span class="ovr-u-listings">
                                            <?php echo esc_html( $listing_count ); ?>
                                            <?php if ( null !== $plan_max && $plan_max > 0 ) : ?>
                                                <small>/ <?php echo esc_html( $plan_max ); ?></small>
                                            <?php elseif ( -1 === $plan_max ) : ?>
                                                <small><span class="material-symbols-outlined" style="font-size:15px;vertical-align:-2px">all_inclusive</span></small>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ( null !== $listing_pct ) : ?>
                                            <div class="ovr-u-listings-bar">
                                                <div class="ovr-u-listings-track">
                                                    <div class="ovr-u-listings-fill" style="width:<?php echo esc_attr( $listing_pct ); ?>%"></div>
                                                </div>
                                                <span style="font-size:12px;color:var(--gray-mid);font-weight:600"><?php echo esc_html( $listing_pct ); ?>%</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="ovr-u-balance<?php echo $balance > 0 ? ' is-positive' : ''; ?>"><?php echo esc_html( '$' . number_format_i18n( $balance, 2 ) ); ?></span>
                                </td>
                                <td>
                                    <span class="ovr-u-badge ovr-u-badge--<?php echo esc_attr( $status_class ); ?>">
                                        <span class="material-symbols-outlined"><?php echo esc_html( $status_icon ); ?></span>
                                        <?php echo esc_html( $status_text ); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="ovr-u-actions-cell">
                                        <a href="<?php echo esc_url( $listings_url ); ?>" class="ovr-u-action-btn ovr-u-action-btn--listings" title="<?php esc_attr_e( 'View this user\'s listings', 'ovr-core' ); ?>">
                                            <span class="material-symbols-outlined">home_work</span>
                                        </a>
                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-u-action-btn ovr-u-action-btn--edit" title="<?php esc_attr_e( 'Edit user', 'ovr-core' ); ?>">
                                            <span class="material-symbols-outlined">edit</span>
                                        </a>
                                        <?php if ( current_user_can( 'manage_options' ) && (int) $user->ID !== get_current_user_id() ) : ?>
                                            <a href="<?php echo esc_url( $login_as_url ); ?>" class="ovr-u-action-btn ovr-u-action-btn--loginas" title="<?php esc_attr_e( 'Log in as this user', 'ovr-core' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Switch to this user\'s account? You can switch back from the admin bar.', 'ovr-core' ) ); ?>');">
                                                <span class="material-symbols-outlined">login</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $max_pages > 1 ) : ?>
                    <div class="ovr-u-pagination">
                        <span>
                            <?php
                            printf(
                                esc_html__( 'Showing %1$d\u2013%2$d of %3$d', 'ovr-core' ),
                                ( ( $paged - 1 ) * \OVR\Admin\UsersAdmin::PER_PAGE ) + 1,
                                min( $paged * \OVR\Admin\UsersAdmin::PER_PAGE, $total ),
                                $total
                            );
                            ?>
                        </span>
                        <div class="ovr-u-pagination-pages">
                            <?php if ( $paged > 1 ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $page_url ) ); ?>" class="ovr-u-page" aria-label="<?php esc_attr_e( 'Previous page', 'ovr-core' ); ?>">
                                    <span class="material-symbols-outlined" style="font-size:19px">chevron_left</span>
                                </a>
                            <?php endif; ?>
                            <?php
                            $start = max( 1, $paged - 2 );
                            $end   = min( $max_pages, $start + 4 );
                            $start = max( 1, $end - 4 );
                            for ( $i = $start; $i <= $end; $i++ ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $page_url ) ); ?>"
                                   class="ovr-u-page <?php echo $i === $paged ? 'ovr-u-page--active' : ''; ?>"
                                   <?php echo $i === $paged ? 'aria-current="page"' : ''; ?>>
                                    <?php echo esc_html( $i ); ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ( $paged < $max_pages ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $page_url ) ); ?>" class="ovr-u-page" aria-label="<?php esc_attr_e( 'Next page', 'ovr-core' ); ?>">
                                    <span class="material-symbols-outlined" style="font-size:19px">chevron_right</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function () {
        var filterBtn = document.getElementById('ovr-u-toggle-filters');
        var filterSelect = document.querySelector('.ovr-u-filter select');
        if (filterBtn && filterSelect) {
            var savedDisplay = getComputedStyle(filterSelect).display;
            filterBtn.addEventListener('click', function () {
                filterSelect.style.display = (filterSelect.style.display === 'none') ? savedDisplay : 'none';
            });
        }
    })();
    </script>
</div>
