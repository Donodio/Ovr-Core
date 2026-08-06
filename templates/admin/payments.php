<?php
/**
 * Payments Management — admin transaction log.
 *
 * @package OVR
 * @var array[]    $payments  Payment rows.
 * @var array      $stats     Computed stat values (total_volume, completed, etc.).
 * @var string     $search    Current search query.
 * @var string     $status    Current status filter.
 * @var string     $method    Current gateway filter.
 * @var string     $date      Current date-range filter.
 * @var int        $paged     Current page number.
 * @var int        $max_pages Total pages.
 * @var int        $total     Total matching payments.
 * @var string     $orderby   Current sort column.
 * @var string     $order     Current sort direction.
 * @var string     $page_url  Base URL for this screen.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap ovr-payments">

    <?php
    $paid_notice = isset( $_GET['ovr_paid'] ) ? sanitize_key( wp_unslash( $_GET['ovr_paid'] ) ) : '';
    if ( $paid_notice ) :
        $paid_messages = [
            'done'    => [ 'updated', __( 'Payment marked paid — subscription activated.', 'ovr-core' ) ],
            'already' => [ 'notice-info', __( 'That payment was already completed.', 'ovr-core' ) ],
            'error'   => [ 'error', __( 'Could not complete that payment.', 'ovr-core' ) ],
        ];
        if ( isset( $paid_messages[ $paid_notice ] ) ) :
            [ $cls, $msg ] = $paid_messages[ $paid_notice ];
            ?>
            <div class="notice notice-<?php echo esc_attr( 'updated' === $cls ? 'success' : ( 'error' === $cls ? 'error' : 'info' ) ); ?> is-dismissible" style="margin:12px 0">
                <p><?php echo esc_html( $msg ); ?></p>
            </div>
        <?php endif;
    endif;
    ?>

    <style>
        #wpcontent,#wpbody-content{background:#f3f5f8}
        #wpcontent{padding-left:0}

        @font-face{font-family:'OVR Atkinson';font-style:normal;font-weight:400 700;src:url(https://fonts.gstatic.com/s/atkinsonhyperlegiblenext/v8/atkinsonhyperlegiblenext.woff2) format('woff2');font-display:swap}

        .ovr-payments{--navy:#000961;--navy-hover:#000748;--navy-light:#e8eaf3;--navy-glow:rgba(0,9,97,.12);--blue:#00A2E8;--blue-light:#e5f5fe;--gold:#DEAF0C;--gold-light:#fef5d6;--gold-dark:#b8920a;--green:#2E7D32;--green-light:#e4f4e4;--red:#B3261E;--red-light:#f9e4e2;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#FFFFFF;--bg:#f3f5f8;--shadow-sm:0 1px 3px rgba(0,9,97,.06),0 1px 2px rgba(0,9,97,.04);--shadow-md:0 4px 12px rgba(0,9,97,.08),0 2px 4px rgba(0,9,97,.04);--shadow-lg:0 8px 32px rgba(0,9,97,.1),0 4px 12px rgba(0,9,97,.06);--radius-sm:6px;--radius-md:8px;--radius-lg:12px;--radius-xl:16px;font-family:'OVR Atkinson','Atkinson Hyperlegible Next',system-ui,sans-serif;width:100%;max-width:none;margin:0;padding:24px 28px 48px;color:var(--ink);-webkit-font-smoothing:antialiased}
        .ovr-payments,.ovr-payments *{box-sizing:border-box}
        .ovr-payments .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;line-height:1;vertical-align:middle;font-size:22px}

        .ovr-pm-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:32px}
        .ovr-pm-head-left{display:flex;flex-direction:column;gap:8px}
        .ovr-pm-head h1{font-size:32px;font-weight:700;letter-spacing:-.015em;margin:0;color:var(--ink);line-height:1.15}
        .ovr-pm-head-sub{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .ovr-pm-head-sub p{margin:0;font-size:16px;color:var(--muted)}
        .ovr-pm-head-sub .ovr-pm-live-dot{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--green);background:var(--green-light);padding:4px 12px 4px 10px;border-radius:9999px;line-height:1}
        .ovr-pm-head-sub .ovr-pm-live-dot::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--green);flex-shrink:0}
        .ovr-pm-head-sub .ovr-pm-sync-ts{font-size:13px;color:var(--gray-mid)}
        .ovr-pm-head-sub .ovr-pm-sync-sep{width:1px;height:14px;background:var(--gray-border)}

        .ovr-pm-revenue{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
        .ovr-pm-metric{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-lg);padding:28px 28px;box-shadow:var(--shadow-md);transition:all .25s ease;position:relative;overflow:hidden}
        .ovr-pm-metric::before{content:'';position:absolute;top:0;left:0;width:100%;height:4px}
        .ovr-pm-metric:hover{box-shadow:var(--shadow-lg);transform:translateY(-2px)}
        .ovr-pm-metric:nth-child(1)::before{background:var(--navy)}
        .ovr-pm-metric:nth-child(1) .ovr-pm-metric-icon{background:var(--navy-light);color:var(--navy)}
        .ovr-pm-metric:nth-child(2)::before{background:var(--blue)}
        .ovr-pm-metric:nth-child(2) .ovr-pm-metric-icon{background:var(--blue-light);color:var(--blue)}
        .ovr-pm-metric:nth-child(3)::before{background:var(--green)}
        .ovr-pm-metric:nth-child(3) .ovr-pm-metric-icon{background:var(--green-light);color:var(--green)}
        .ovr-pm-metric:nth-child(4)::before{background:var(--gold)}
        .ovr-pm-metric:nth-child(4) .ovr-pm-metric-icon{background:var(--gold-light);color:var(--gold)}
        .ovr-pm-metric-inner{display:flex;align-items:center;gap:20px}
        .ovr-pm-metric-icon{width:56px;height:56px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .ovr-pm-metric-icon .material-symbols-outlined{font-size:30px}
        .ovr-pm-metric-info{display:flex;flex-direction:column;gap:4px;min-width:0}
        .ovr-pm-metric-label{font-size:15px;font-weight:500;color:var(--muted);letter-spacing:.01em}
        .ovr-pm-metric-value{font-size:34px;font-weight:700;color:var(--ink);line-height:1;font-variant-numeric:tabular-nums;letter-spacing:-.02em}
        .ovr-pm-metric-value .ovr-pm-metric-sub{font-size:16px;font-weight:500;color:var(--gray-mid);letter-spacing:0}

        .ovr-pm-glass{border-radius:var(--radius-xl);padding:20px 24px;margin-bottom:28px;background:rgba(255,255,255,.75);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid rgba(219,219,219,.5);box-shadow:var(--shadow-sm)}
        .ovr-pm-filters{display:flex;flex-direction:row;gap:14px;align-items:flex-end;flex-wrap:wrap}
        .ovr-pm-field{display:flex;flex-direction:column;gap:5px}
        .ovr-pm-field-label{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
        .ovr-pm-field input,.ovr-pm-field select{font-family:inherit;font-size:15px;color:var(--ink);background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-md);padding:0 14px;outline:none;transition:border-color .2s,box-shadow .2s;height:42px;min-height:42px}
        .ovr-pm-field input:focus,.ovr-pm-field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px var(--navy-glow)}
        .ovr-pm-field input::placeholder{color:var(--gray-mid)}
        .ovr-pm-field select{appearance:none;-webkit-appearance:none;padding-right:36px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='%235F6B7A' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 11px center}
        .ovr-pm-field--search{flex:1;min-width:200px}
        .ovr-pm-field--search .ovr-pm-field-input{position:relative}
        .ovr-pm-field--search .ovr-pm-field-input .material-symbols-outlined{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:19px;color:var(--gray-mid);pointer-events:none;transition:color .2s}
        .ovr-pm-field--search:focus-within .ovr-pm-field-input .material-symbols-outlined{color:var(--blue)}
        .ovr-pm-field--search input{padding-left:40px;width:100%}
        .ovr-pm-field--short{min-width:140px}
        .ovr-pm-field--xs{min-width:120px}

        .ovr-pm-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:0 22px;border-radius:var(--radius-md);font-size:15px;font-weight:600;text-decoration:none;line-height:1;border:1px solid transparent;cursor:pointer;font-family:inherit;white-space:nowrap;height:42px;min-height:42px;transition:all .2s ease}
        .ovr-pm-btn .material-symbols-outlined{font-size:19px}
        .ovr-pm-btn--primary{background:var(--navy);color:#fff;box-shadow:0 2px 6px rgba(0,9,97,.15)}
        .ovr-pm-btn--primary:hover{background:var(--navy-hover);box-shadow:0 4px 14px rgba(0,9,97,.25);transform:translateY(-1px);color:#fff}
        .ovr-pm-btn--ghost{background:var(--surf);color:var(--ink);border-color:var(--gray-border)}
        .ovr-pm-btn--ghost:hover{background:var(--gray-light);color:var(--navy);transform:translateY(-1px)}

        .ovr-pm-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-xl);overflow:hidden;box-shadow:var(--shadow-sm)}
        .ovr-pm-card-header{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-bottom:1px solid var(--gray-border);background:var(--bg);gap:12px;flex-wrap:wrap}
        .ovr-pm-card-header h2{font-size:15px;font-weight:600;margin:0;color:var(--ink);display:flex;align-items:center;gap:8px}
        .ovr-pm-card-header h2 .material-symbols-outlined{font-size:18px;color:var(--navy)}
        .ovr-pm-card-header .ovr-pm-count{font-size:13px;color:var(--gray-mid);background:var(--surf);padding:2px 10px;border-radius:9999px;border:1px solid var(--gray-border)}

        .ovr-pm-table-wrap{overflow-x:auto}
        .ovr-pm-table{width:100%;border-collapse:collapse}
        .ovr-pm-table th{text-align:left;padding:14px 18px;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--gray-border);white-space:nowrap;user-select:none;background:var(--surf)}
        .ovr-pm-table td{padding:15px 18px;font-size:15px;color:var(--ink);border-bottom:1px solid rgba(219,219,219,.4);vertical-align:middle;transition:background .12s}
        .ovr-pm-table tbody tr:last-child td{border-bottom:none}
        .ovr-pm-table tbody tr:hover td{background:rgba(0,162,232,.03)}
        .ovr-pm-table tbody tr:nth-child(even) td{background:rgba(238,240,243,.35)}
        .ovr-pm-table tbody tr:nth-child(even):hover td{background:rgba(0,162,232,.03)}

        .ovr-pm-tx-id{font-family:'SF Mono','JetBrains Mono','Cascadia Code','DejaVu Sans Mono',monospace;font-size:13px;color:var(--muted);letter-spacing:-.01em;display:inline-flex;align-items:center;gap:6px}
        .ovr-pm-tx-id::before{content:'#';opacity:.4}

        .ovr-pm-user{display:flex;align-items:center;gap:11px}
        .ovr-pm-user-avatar{width:34px;height:34px;border-radius:50%;overflow:hidden;flex-shrink:0;background:var(--gray-light);border:1.5px solid var(--surf);box-shadow:0 0 0 1px var(--gray-border);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:12px;font-weight:700}
        .ovr-pm-user-avatar img{width:100%;height:100%;object-fit:cover;display:block}
        .ovr-pm-user-name{font-weight:600;font-size:15px;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

        .ovr-pm-type{display:flex;flex-direction:column;gap:2px}
        .ovr-pm-type-label{font-size:15px;font-weight:500;color:var(--ink)}
        .ovr-pm-type-sub{font-size:12px;color:var(--gray-mid);font-weight:500;letter-spacing:.01em}

        .ovr-pm-method{display:flex;align-items:center;gap:5px;color:var(--muted);font-size:14px;font-weight:500}
        .ovr-pm-method .material-symbols-outlined{font-size:16px;color:var(--gray-mid)}

        .ovr-pm-amount{font-size:19px;font-weight:700;color:var(--ink);font-variant-numeric:tabular-nums;line-height:1;letter-spacing:-.01em}
        .ovr-pm-amount--completed{color:var(--ink)}
        .ovr-pm-amount--pending{color:var(--gold)}
        .ovr-pm-amount--declined{color:var(--red)}

        .ovr-pm-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px 4px 10px;border-radius:9999px;font-size:12px;font-weight:600;line-height:1;white-space:nowrap;transition:all .15s}
        .ovr-pm-badge .material-symbols-outlined{font-size:14px}
        .ovr-pm-badge--completed{background:var(--green-light);color:var(--green)}
        .ovr-pm-badge--pending{background:var(--gold-light);color:var(--gold)}
        .ovr-pm-badge--declined{background:var(--red-light);color:var(--red)}
        .ovr-pm-badge--cancelled{background:#eceff3;color:#5a6270}

        .ovr-pm-action-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--radius-sm);border:none;cursor:pointer;background:transparent;color:var(--gray-mid);text-decoration:none;transition:all .15s ease}
        .ovr-pm-action-btn:hover{background:var(--gray-light);color:var(--navy);box-shadow:var(--shadow-sm)}
        .ovr-pm-action-btn .material-symbols-outlined{font-size:18px}

        .ovr-pm-empty{text-align:center;padding:80px 24px}
        .ovr-pm-empty .material-symbols-outlined{font-size:52px;color:var(--gray-border);margin-bottom:14px;display:block}
        .ovr-pm-empty h3{font-size:17px;font-weight:600;color:var(--ink);margin:0 0 5px}
        .ovr-pm-empty p{font-size:15px;margin:0;color:var(--muted)}

        .ovr-pm-footer{display:flex;align-items:center;justify-content:space-between;padding:14px 22px;border-top:1px solid var(--gray-border);background:var(--surf);font-size:14px;color:var(--muted);flex-wrap:wrap;gap:12px}
        .ovr-pm-pages{display:flex;gap:3px;align-items:center}
        .ovr-pm-page{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;border-radius:var(--radius-sm);font-size:14px;font-weight:500;text-decoration:none;color:var(--muted);border:1px solid var(--gray-border);background:var(--surf);padding:0 8px;cursor:pointer;transition:all .12s ease}
        .ovr-pm-page:hover{background:var(--gray-light);border-color:var(--navy);color:var(--navy);z-index:1}
        .ovr-pm-page--active{background:var(--navy);color:#fff;border-color:var(--navy);box-shadow:0 2px 6px rgba(0,9,97,.15)}
        .ovr-pm-page--active:hover{background:var(--navy-hover);color:#fff;border-color:var(--navy-hover)}

        @media (max-width:1100px){
            .ovr-pm-filters{flex-direction:column;align-items:stretch}
            .ovr-pm-field--short,.ovr-pm-field--xs{min-width:auto}
            .ovr-pm-revenue{grid-template-columns:repeat(2,1fr);gap:14px}
            .ovr-pm-metric{padding:22px 22px}
            .ovr-pm-metric-value{font-size:28px}
            .ovr-payments{padding:20px 18px 36px}
            .ovr-pm-head h1{font-size:28px}
        }
        @media (max-width:782px){
            .ovr-pm-head h1{font-size:26px}
            .ovr-pm-head{flex-direction:column;align-items:stretch}
            .ovr-pm-table th:nth-child(2),.ovr-pm-table td:nth-child(2),
            .ovr-pm-table th:nth-child(5),.ovr-pm-table td:nth-child(5){display:none}
            .ovr-pm-footer{flex-direction:column;align-items:center}
            .ovr-pm-metric-value{font-size:24px}
            .ovr-pm-metric-icon{width:48px;height:48px}
            .ovr-pm-metric-icon .material-symbols-outlined{font-size:26px}
        }
        @media (max-width:600px){
            .ovr-pm-revenue{grid-template-columns:1fr 1fr;gap:12px}
            .ovr-pm-metric{padding:18px 18px}
            .ovr-pm-metric-value{font-size:22px}
            .ovr-pm-metric-icon{width:44px;height:44px}
            .ovr-pm-metric-icon .material-symbols-outlined{font-size:24px}
        }

        @media (prefers-reduced-motion:reduce){
            .ovr-pm-btn,.ovr-pm-page,.ovr-pm-action-btn,.ovr-pm-metric{transition:none}
            .ovr-pm-metric:hover{transform:none}
        }
    </style>

    <div class="ovr-pm-head">
        <div class="ovr-pm-head-left">
            <h1><?php esc_html_e( 'Payments', 'ovr-core' ); ?></h1>
            <div class="ovr-pm-head-sub">
                <p><?php esc_html_e( 'Review and manage all financial transactions.', 'ovr-core' ); ?></p>
                <div class="ovr-pm-sync-sep"></div>
                <span class="ovr-pm-live-dot"><?php esc_html_e( 'Gateway Online', 'ovr-core' ); ?></span>
                <span class="ovr-pm-sync-ts"><?php esc_html_e( 'Synced just now', 'ovr-core' ); ?></span>
            </div>
        </div>
    </div>

    <div class="ovr-pm-revenue">
        <div class="ovr-pm-metric">
            <div class="ovr-pm-metric-inner">
                <div class="ovr-pm-metric-icon"><span class="material-symbols-outlined">payments</span></div>
                <div class="ovr-pm-metric-info">
                    <span class="ovr-pm-metric-value"><?php echo esc_html( '$' . number_format_i18n( $stats['total_volume'], 2 ) ); ?></span>
                    <span class="ovr-pm-metric-label"><?php esc_html_e( 'Total Volume', 'ovr-core' ); ?></span>
                </div>
            </div>
        </div>
        <div class="ovr-pm-metric">
            <div class="ovr-pm-metric-inner">
                <div class="ovr-pm-metric-icon"><span class="material-symbols-outlined">calendar_month</span></div>
                <div class="ovr-pm-metric-info">
                    <span class="ovr-pm-metric-value"><?php echo esc_html( '$' . number_format_i18n( $stats['this_month'], 2 ) ); ?></span>
                    <span class="ovr-pm-metric-label"><?php esc_html_e( 'This Month', 'ovr-core' ); ?></span>
                </div>
            </div>
        </div>
        <div class="ovr-pm-metric">
            <div class="ovr-pm-metric-inner">
                <div class="ovr-pm-metric-icon"><span class="material-symbols-outlined">check_circle</span></div>
                <div class="ovr-pm-metric-info">
                    <span class="ovr-pm-metric-value"><?php echo esc_html( number_format_i18n( $stats['completed'] ) ); ?> <span class="ovr-pm-metric-sub">tx</span></span>
                    <span class="ovr-pm-metric-label"><?php esc_html_e( 'Completed', 'ovr-core' ); ?></span>
                </div>
            </div>
        </div>
        <div class="ovr-pm-metric">
            <div class="ovr-pm-metric-inner">
                <div class="ovr-pm-metric-icon"><span class="material-symbols-outlined">schedule</span></div>
                <div class="ovr-pm-metric-info">
                    <span class="ovr-pm-metric-value"><?php echo esc_html( number_format_i18n( $stats['pending_count'] ) ); ?> <span class="ovr-pm-metric-sub">tx</span></span>
                    <span class="ovr-pm-metric-label"><?php esc_html_e( 'Pending', 'ovr-core' ); ?></span>
                </div>
            </div>
        </div>
        <div class="ovr-pm-metric">
            <div class="ovr-pm-metric-inner">
                <div class="ovr-pm-metric-icon"><span class="material-symbols-outlined">calendar_today</span></div>
                <div class="ovr-pm-metric-info">
                    <span class="ovr-pm-metric-value"><?php echo esc_html( '$' . number_format_i18n( (float) ( $stats['this_year'] ?? 0 ), 2 ) ); ?></span>
                    <span class="ovr-pm-metric-label"><?php esc_html_e( 'This Year', 'ovr-core' ); ?></span>
                </div>
            </div>
        </div>
        <div class="ovr-pm-metric">
            <div class="ovr-pm-metric-inner">
                <div class="ovr-pm-metric-icon"><span class="material-symbols-outlined">card_membership</span></div>
                <div class="ovr-pm-metric-info">
                    <span class="ovr-pm-metric-value"><?php echo esc_html( '$' . number_format_i18n( (float) ( $stats['sub_revenue'] ?? 0 ), 2 ) ); ?></span>
                    <span class="ovr-pm-metric-label"><?php esc_html_e( 'Subscription Revenue', 'ovr-core' ); ?></span>
                </div>
            </div>
        </div>
        <div class="ovr-pm-metric">
            <div class="ovr-pm-metric-inner">
                <div class="ovr-pm-metric-icon"><span class="material-symbols-outlined">rocket_launch</span></div>
                <div class="ovr-pm-metric-info">
                    <span class="ovr-pm-metric-value"><?php echo esc_html( '$' . number_format_i18n( (float) ( $stats['listing_revenue'] ?? 0 ), 2 ) ); ?></span>
                    <span class="ovr-pm-metric-label"><?php esc_html_e( 'Listing Revenue', 'ovr-core' ); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="ovr-pm-glass">
        <form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
            <input type="hidden" name="post_type" value="ovr_property">
            <input type="hidden" name="page" value="<?php echo esc_attr( \OVR\Admin\PaymentsAdmin::PAGE_SLUG ); ?>">
            <div class="ovr-pm-filters">
                <div class="ovr-pm-field ovr-pm-field--search">
                    <label class="ovr-pm-field-label" for="pm-search"><?php esc_html_e( 'Search Transactions', 'ovr-core' ); ?></label>
                    <div class="ovr-pm-field-input">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" id="pm-search" name="s" placeholder="<?php esc_attr_e( 'Transaction ID, user, or listing...', 'ovr-core' ); ?>" value="<?php echo esc_attr( $search ); ?>">
                    </div>
                </div>
                <div class="ovr-pm-field ovr-pm-field--short">
                    <label class="ovr-pm-field-label" for="pm-date"><?php esc_html_e( 'Date Range', 'ovr-core' ); ?></label>
                    <select id="pm-date" name="date">
                        <option value="30" <?php selected( $date, '30' ); ?>><?php esc_html_e( 'Last 30 Days', 'ovr-core' ); ?></option>
                        <option value="7" <?php selected( $date, '7' ); ?>><?php esc_html_e( 'Last 7 Days', 'ovr-core' ); ?></option>
                        <option value="month" <?php selected( $date, 'month' ); ?>><?php esc_html_e( 'This Month', 'ovr-core' ); ?></option>
                        <option value="year" <?php selected( $date, 'year' ); ?>><?php esc_html_e( 'This Year', 'ovr-core' ); ?></option>
                        <option value="all" <?php selected( $date, 'all' ); ?>><?php esc_html_e( 'All Time', 'ovr-core' ); ?></option>
                    </select>
                </div>
                <div class="ovr-pm-field ovr-pm-field--xs">
                    <label class="ovr-pm-field-label" for="pm-type"><?php esc_html_e( 'Type', 'ovr-core' ); ?></label>
                    <select id="pm-type" name="type">
                        <option value="" <?php selected( $type ?? '', '' ); ?>><?php esc_html_e( 'All Types', 'ovr-core' ); ?></option>
                        <option value="subscription" <?php selected( $type ?? '', 'subscription' ); ?>><?php esc_html_e( 'Subscription', 'ovr-core' ); ?></option>
                        <option value="listing_upgrade" <?php selected( $type ?? '', 'listing_upgrade' ); ?>><?php esc_html_e( 'Listing Upgrade', 'ovr-core' ); ?></option>
                        <option value="topup" <?php selected( $type ?? '', 'topup' ); ?>><?php esc_html_e( 'Wallet Top-up', 'ovr-core' ); ?></option>
                        <option value="booking" <?php selected( $type ?? '', 'booking' ); ?>><?php esc_html_e( 'Booking', 'ovr-core' ); ?></option>
                    </select>
                </div>
                <div class="ovr-pm-field ovr-pm-field--xs">
                    <label class="ovr-pm-field-label" for="pm-amtmin"><?php esc_html_e( 'Amount ($)', 'ovr-core' ); ?></label>
                    <div style="display:flex;gap:6px">
                        <input type="number" step="0.01" min="0" id="pm-amtmin" name="amt_min" placeholder="<?php esc_attr_e( 'Min', 'ovr-core' ); ?>" value="<?php echo esc_attr( ! empty( $amt_min ) ? (string) $amt_min : '' ); ?>" style="width:80px">
                        <input type="number" step="0.01" min="0" name="amt_max" placeholder="<?php esc_attr_e( 'Max', 'ovr-core' ); ?>" value="<?php echo esc_attr( ! empty( $amt_max ) ? (string) $amt_max : '' ); ?>" style="width:80px">
                    </div>
                </div>
                <div class="ovr-pm-field ovr-pm-field--xs">
                    <label class="ovr-pm-field-label" for="pm-status"><?php esc_html_e( 'Status', 'ovr-core' ); ?></label>
                    <select id="pm-status" name="status">
                        <option value="" <?php selected( $status, '' ); ?>><?php esc_html_e( 'All Statuses', 'ovr-core' ); ?></option>
                        <option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'ovr-core' ); ?></option>
                        <option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'ovr-core' ); ?></option>
                        <option value="declined" <?php selected( $status, 'declined' ); ?>><?php esc_html_e( 'Declined', 'ovr-core' ); ?></option>
                        <option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'ovr-core' ); ?></option>
                    </select>
                </div>
                <div class="ovr-pm-field ovr-pm-field--xs">
                    <label class="ovr-pm-field-label" for="pm-method"><?php esc_html_e( 'Method', 'ovr-core' ); ?></label>
                    <select id="pm-method" name="method">
                        <option value="" <?php selected( $method, '' ); ?>><?php esc_html_e( 'All Methods', 'ovr-core' ); ?></option>
                        <option value="stripe" <?php selected( $method, 'stripe' ); ?>><?php esc_html_e( 'Stripe', 'ovr-core' ); ?></option>
                        <option value="paypal" <?php selected( $method, 'paypal' ); ?>><?php esc_html_e( 'PayPal', 'ovr-core' ); ?></option>
                        <option value="authorize_net" <?php selected( $method, 'authorize_net' ); ?>><?php esc_html_e( 'Authorize.net', 'ovr-core' ); ?></option>
                        <option value="wallet" <?php selected( $method, 'wallet' ); ?>><?php esc_html_e( 'Wallet', 'ovr-core' ); ?></option>
                    </select>
                </div>
                <button type="submit" class="ovr-pm-btn ovr-pm-btn--primary">
                    <span class="material-symbols-outlined">filter_alt</span>
                    <?php esc_html_e( 'Apply Filters', 'ovr-core' ); ?>
                </button>
                <?php if ( ! empty( $base_url ) ) : ?>
                    <a href="<?php echo esc_url( $base_url ); ?>" class="ovr-pm-btn ovr-pm-btn--ghost"
                       title="<?php esc_attr_e( 'Clear all filters and search', 'ovr-core' ); ?>">
                        <span class="material-symbols-outlined">filter_alt_off</span>
                        <?php esc_html_e( 'Reset Filters', 'ovr-core' ); ?>
                    </a>
                <?php endif; ?>
                <?php if ( ! empty( $csv_url ) ) : ?>
                    <a href="<?php echo esc_url( $csv_url ); ?>" class="ovr-pm-btn ovr-pm-btn--primary" style="text-decoration:none">
                        <span class="material-symbols-outlined">download</span>
                        <?php esc_html_e( 'Export CSV', 'ovr-core' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="ovr-pm-card">
        <div class="ovr-pm-card-header">
            <h2><span class="material-symbols-outlined">receipt_long</span> <?php esc_html_e( 'Transaction Log', 'ovr-core' ); ?></h2>
            <span class="ovr-pm-count"><?php echo esc_html( sprintf( _n( '%d entry', '%d entries', $total, 'ovr-core' ), $total ) ); ?></span>
        </div>
        <div class="ovr-pm-table-wrap">
            <?php if ( empty( $payments ) ) : ?>
                <div class="ovr-pm-empty">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <h3><?php esc_html_e( 'No transactions found', 'ovr-core' ); ?></h3>
                    <p><?php esc_html_e( 'Try adjusting your search or filter criteria.', 'ovr-core' ); ?></p>
                </div>
            <?php else : ?>
                <table class="ovr-pm-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Transaction', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'User', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Method', 'ovr-core' ); ?></th>
                            <th style="text-align:right"><?php esc_html_e( 'Amount', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                            <th style="text-align:center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $payments as $pm ) :
                            $pm_id      = (int) ( $pm['id'] ?? 0 );
                            $tx_id      = $pm['transaction_id'] ?: 'TRX-' . str_pad( (string) $pm_id, 5, '0', STR_PAD_LEFT ) . strtoupper( wp_generate_password( 1, false ) );
                            $created    = strtotime( $pm['created_at'] ?? '' );
                            $amount     = (float) ( $pm['amount'] ?? 0 );
                            $pm_status  = $pm['status'] ?? 'pending';
                            $gateway    = $pm['gateway'] ?? '';
                            $pm_type    = $pm['payment_type'] ?? 'subscription';
                            $display    = $pm['display_name'] ?? '';
                            $user_id    = (int) ( $pm['user_id'] ?? 0 );
                            $meta       = $pm['meta_data'] ? json_decode( $pm['meta_data'], true ) : [];

                            $type_labels = [
                                'subscription'    => __( 'Subscription', 'ovr-core' ),
                                'listing_upgrade' => __( 'Listing Upgrade', 'ovr-core' ),
                                'topup'           => __( 'Account Credit', 'ovr-core' ),
                            ];
                            $type_label  = $type_labels[ $pm_type ] ?? ucwords( str_replace( '_', ' ', $pm_type ) );

                            $type_sub = '';
                            if ( 'subscription' === $pm_type && ! empty( $meta['plan_slug'] ) ) {
                                $type_sub = sprintf( __( 'Plan: %s', 'ovr-core' ), ucwords( str_replace( '_', ' ', $meta['plan_slug'] ) ) );
                            } elseif ( 'listing_upgrade' === $pm_type && ! empty( $meta['upgrade'] ) ) {
                                $upgrade_map = [ 'homepage_slider' => 'Homepage Slider', 'featured' => 'Featured', 'top_of_page' => 'Top of Page' ];
                                $type_sub    = $upgrade_map[ $meta['upgrade'] ] ?? ucwords( str_replace( '_', ' ', $meta['upgrade'] ) );
                            }

                            $method_icons = [
                                'stripe'        => 'credit_card',
                                'paypal'        => 'account_balance_wallet',
                                'authorize_net' => 'account_balance',
                                'free'          => 'redeem',
                                'wallet'        => 'account_balance_wallet',
                            ];
                            $method_labels = [
                                'stripe'        => 'Stripe',
                                'paypal'        => 'PayPal',
                                'authorize_net' => 'Authorize.net',
                                'free'          => 'Free',
                                'wallet'        => 'Wallet',
                            ];
                            $method_icon  = $method_icons[ $gateway ] ?? 'credit_card';
                            $method_label = $method_labels[ $gateway ] ?? ucwords( str_replace( '_', ' ', $gateway ) );

                            $avatar = $user_id ? get_avatar( $user_id, 34 ) : '';
                            $initials = '';
                            if ( ! $avatar && $display ) {
                                $parts   = explode( ' ', $display );
                                $initials = strtoupper( substr( $parts[0] ?? '', 0, 1 ) . ( substr( $parts[1] ?? '', 0, 1 ) ?: '' ) );
                            }
                        ?>
                            <tr>
                                <td>
                                    <span class="ovr-pm-tx-id"><?php echo esc_html( $tx_id ); ?></span>
                                </td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:2px">
                                        <span style="font-size:15px;color:var(--ink)"><?php echo $created ? esc_html( date_i18n( 'M j, Y', $created ) ) : '&mdash;'; ?></span>
                                        <span style="font-size:12px;color:var(--gray-mid)"><?php echo $created ? esc_html( date_i18n( 'g:i A', $created ) ) : ''; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="ovr-pm-user">
                                        <div class="ovr-pm-user-avatar">
                                            <?php if ( $avatar ) : ?>
                                                <?php echo $avatar; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
                                            <?php else : ?>
                                                <?php echo esc_html( $initials ?: '?' ); ?>
                                            <?php endif; ?>
                                        </div>
                                        <span class="ovr-pm-user-name"><?php echo esc_html( $display ?: __( 'Unknown', 'ovr-core' ) ); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="ovr-pm-type">
                                        <span class="ovr-pm-type-label"><?php echo esc_html( $type_label ); ?></span>
                                        <?php if ( $type_sub ) : ?>
                                            <span class="ovr-pm-type-sub"><?php echo esc_html( $type_sub ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="ovr-pm-method">
                                        <span class="material-symbols-outlined"><?php echo esc_attr( $method_icon ); ?></span>
                                        <?php echo esc_html( $method_label ); ?>
                                    </div>
                                </td>
                                <td style="text-align:right">
                                    <span class="ovr-pm-amount ovr-pm-amount--<?php echo esc_attr( $pm_status ); ?>">
                                        <?php echo esc_html( '$' . number_format_i18n( $amount, 2 ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ovr-pm-badge ovr-pm-badge--<?php echo esc_attr( $pm_status ); ?>">
                                        <span class="material-symbols-outlined">
                                            <?php echo 'completed' === $pm_status ? 'check_circle' : ( 'pending' === $pm_status ? 'schedule' : 'error' ); ?>
                                        </span>
                                        <?php echo esc_html( ucfirst( $pm_status ) ); ?>
                                    </span>
                                </td>
                                <td style="text-align:center;white-space:nowrap">
                                    <?php if ( 'completed' !== $pm_status ) :
                                        $complete_url = wp_nonce_url(
                                            admin_url( 'admin-post.php?action=ovr_complete_payment&payment=' . $pm_id ),
                                            'ovr_complete_payment_' . $pm_id
                                        );
                                    ?>
                                        <a href="<?php echo esc_url( $complete_url ); ?>" class="ovr-pm-action-btn" style="width:auto;padding:0 12px;gap:6px;font-size:12px;font-weight:600;background:#006c4a;color:#fff"
                                           title="<?php esc_attr_e( 'Mark this payment paid and activate the subscription', 'ovr-core' ); ?>"
                                           onclick="return confirm('<?php echo esc_js( __( 'Mark this payment as paid and activate the subscription?', 'ovr-core' ) ); ?>');">
                                            <span class="material-symbols-outlined">check_circle</span><?php esc_html_e( 'Mark Paid', 'ovr-core' ); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="ovr-pm-action-btn" title="<?php esc_attr_e( 'Completed', 'ovr-core' ); ?>">
                                            <span class="material-symbols-outlined">check</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $max_pages > 1 ) : ?>
                    <div class="ovr-pm-footer">
                        <span>
                            <?php
                            printf(
                                esc_html__( 'Showing %1$d\u2013%2$d of %3$s', 'ovr-core' ),
                                ( ( $paged - 1 ) * \OVR\Admin\PaymentsAdmin::PER_PAGE ) + 1,
                                min( $paged * \OVR\Admin\PaymentsAdmin::PER_PAGE, $total ),
                                number_format_i18n( $total )
                            );
                            ?>
                        </span>
                        <div class="ovr-pm-pages">
                            <?php if ( $paged > 1 ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $page_url ) ); ?>" class="ovr-pm-page" aria-label="<?php esc_attr_e( 'Previous page', 'ovr-core' ); ?>">
                                    <span class="material-symbols-outlined" style="font-size:17px">chevron_left</span>
                                </a>
                            <?php endif; ?>
                            <?php
                            $start = max( 1, $paged - 1 );
                            $end   = min( $max_pages, $start + 2 );
                            $start = max( 1, $end - 2 );
                            for ( $i = $start; $i <= $end; $i++ ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $page_url ) ); ?>"
                                   class="ovr-pm-page <?php echo $i === $paged ? 'ovr-pm-page--active' : ''; ?>"
                                   <?php echo $i === $paged ? 'aria-current="page"' : ''; ?>>
                                    <?php echo esc_html( $i ); ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ( $paged < $max_pages ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $page_url ) ); ?>" class="ovr-pm-page" aria-label="<?php esc_attr_e( 'Next page', 'ovr-core' ); ?>">
                                    <span class="material-symbols-outlined" style="font-size:17px">chevron_right</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
