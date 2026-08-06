<?php
/**
 * Bookings — admin list screen (Feature 4).
 *
 * @package OVR
 * @var array               $data          ListTable::query() result.
 * @var \OVR\Admin\ListTable $list          The list-table engine (for sort URLs).
 * @var string              $page_url      Base URL for this screen (preserves active filters).
 * @var string              $base_url      Bare screen URL (drops filters — for Reset).
 * @var array<string,string> $status_labels Status slug => label.
 * @var array               $stats         Headline stat values.
 * @var array|null          $notice        Result notice, or null.
 * @var string              $csv_url       Export CSV URL.
 * @var string              $new_url       New booking URL.
 * @var string              $wp_sync_url   WordPress-sync action URL.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Booking\BookingRepository;

$rows      = $data['rows'];
$total     = (int) $data['total'];
$paged     = (int) $data['paged'];
$per_page  = (int) $data['per_page'];
$max_pages = (int) $data['max_pages'];
$cur       = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
$cur_src   = sanitize_text_field( wp_unslash( $_GET['source'] ?? '' ) );
$symbol    = '$';
?>
<div class="wrap ovr-bk">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}
        #wpcontent{padding-left:0}
        .ovr-bk{--navy:#000961;--blue:#00A2E8;--blue-light:#e5f5fe;--navy-light:#e8eaf3;--gold:#DEAF0C;--gold-dark:#b8920a;--gold-light:#fef5d6;--green:#2E7D32;--green-light:#e4f4e4;--red:#B3261E;--red-light:#f9e4e2;--purple:#6A3FB8;--purple-light:#eee6fb;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--bg:#f0f3f7;--shadow-sm:0 1px 3px rgba(0,9,97,.06);--shadow-md:0 4px 12px rgba(0,9,97,.08);--shadow-lg:0 8px 32px rgba(0,9,97,.1);--r-sm:6px;--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink)}
        .ovr-bk,.ovr-bk *{box-sizing:border-box}
        .ovr-bk .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:22px}
        .ovr-bk-wrap{padding:24px 40px 48px}
        .ovr-bk-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:20px}
        .ovr-bk-head h1{font-size:30px;font-weight:700;margin:0;line-height:1.2}
        .ovr-bk-head p{margin:6px 0 0;font-size:16px;color:var(--muted)}
        .ovr-bk-actions{display:flex;gap:10px;flex-wrap:wrap;flex-shrink:0}
        .ovr-bk-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 22px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:46px;transition:all .2s}
        .ovr-bk-btn .material-symbols-outlined{font-size:20px}
        .ovr-bk-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold);box-shadow:0 2px 8px rgba(222,175,12,.25)}
        .ovr-bk-btn--primary:hover{background:var(--gold-dark);color:var(--navy)}
        .ovr-bk-btn--ghost{background:var(--surf);color:var(--navy);border-color:var(--gray-border);box-shadow:var(--shadow-sm)}
        .ovr-bk-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-bk-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        .ovr-bk-stat{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);padding:22px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-md);position:relative;overflow:hidden}
        .ovr-bk-stat::before{content:'';position:absolute;top:0;left:0;width:100%;height:3px}
        .ovr-bk-stat:nth-child(1)::before{background:var(--navy)}.ovr-bk-stat:nth-child(1) .ovr-bk-stat-ic{background:var(--navy-light);color:var(--navy)}
        .ovr-bk-stat:nth-child(2)::before{background:var(--blue)}.ovr-bk-stat:nth-child(2) .ovr-bk-stat-ic{background:var(--blue-light);color:var(--blue)}
        .ovr-bk-stat:nth-child(3)::before{background:var(--green)}.ovr-bk-stat:nth-child(3) .ovr-bk-stat-ic{background:var(--green-light);color:var(--green)}
        .ovr-bk-stat:nth-child(4)::before{background:var(--gold)}.ovr-bk-stat:nth-child(4) .ovr-bk-stat-ic{background:var(--gold-light);color:var(--gold-dark)}
        .ovr-bk-stat-ic{width:50px;height:50px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .ovr-bk-stat-ic .material-symbols-outlined{font-size:26px}
        .ovr-bk-stat-v{font-size:30px;font-weight:700;line-height:1;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
        .ovr-bk-stat-l{font-size:14px;color:var(--muted);margin-top:4px}
        .ovr-bk-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-md)}
        .ovr-bk-toolbar{display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid var(--gray-border);background:var(--bg);flex-wrap:wrap}
        .ovr-bk-toolbar form{display:flex;align-items:center;gap:10px;flex:1;flex-wrap:wrap}
        .ovr-bk-search{position:relative;flex:1;min-width:200px;max-width:380px}
        .ovr-bk-search .material-symbols-outlined{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:20px;color:var(--gray-mid);pointer-events:none}
        .ovr-bk-search input{width:100%;background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:0 16px 0 44px;font-size:15px;font-family:inherit;height:46px;outline:none}
        .ovr-bk-search input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.12)}
        .ovr-bk-toolbar select{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:0 38px 0 14px;font-size:15px;font-family:inherit;height:46px;cursor:pointer;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%235F6B7A' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;min-width:150px}
        .ovr-bk-toolbar select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.12)}
        .ovr-bk-count{font-size:14px;color:var(--gray-mid);font-weight:500;background:var(--surf);padding:6px 14px;border-radius:9999px;border:1px solid var(--gray-border);margin-left:auto}
        .ovr-bk-table{width:100%;border-collapse:collapse}
        .ovr-bk-table th{text-align:left;padding:14px 18px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);background:#f8f9fb;border-bottom:2px solid var(--gray-border);white-space:nowrap}
        .ovr-bk-table th a{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
        .ovr-bk-table th a:hover{color:var(--blue)}
        .ovr-bk-table th .material-symbols-outlined{font-size:15px;opacity:.4}
        .ovr-bk-table td{padding:16px 18px;font-size:15px;border-bottom:1px solid var(--gray-border);vertical-align:middle}
        .ovr-bk-table tbody tr:hover td{background:rgba(0,162,232,.03)}
        .ovr-bk-id{font-weight:700;color:var(--navy);font-variant-numeric:tabular-nums}
        .ovr-bk-guest-name{font-weight:600}
        .ovr-bk-guest-email{font-size:13px;color:var(--muted)}
        .ovr-bk-dates{font-variant-numeric:tabular-nums;white-space:nowrap}
        .ovr-bk-dates small{color:var(--gray-mid)}
        .ovr-bk-amount{font-weight:700;font-variant-numeric:tabular-nums}
        .ovr-bk-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:var(--r-sm);font-size:13px;font-weight:600;white-space:nowrap;text-transform:capitalize}
        .ovr-bk-badge--booked{background:var(--green-light);color:var(--green)}
        .ovr-bk-badge--soft_block{background:var(--blue-light);color:var(--blue)}
        .ovr-bk-badge--owner_hold{background:var(--purple-light);color:var(--purple)}
        .ovr-bk-badge--maintenance{background:var(--gold-light);color:var(--gold-dark)}
        .ovr-bk-badge--completed{background:var(--gray-light);color:var(--muted)}
        .ovr-bk-badge--cancelled{background:var(--red-light);color:var(--red)}
        .ovr-bk-src{font-size:13px;color:var(--muted);text-transform:capitalize}
        .ovr-bk-cell-actions{display:flex;gap:4px}
        .ovr-bk-act{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:var(--r-sm);border:none;cursor:pointer;background:transparent;color:var(--gray-mid);text-decoration:none;transition:all .18s}
        .ovr-bk-act:hover{background:var(--gray-light);color:var(--ink)}
        .ovr-bk-act--edit:hover{background:var(--blue-light);color:var(--blue)}
        .ovr-bk-act--danger:hover{background:var(--red-light);color:var(--red)}
        .ovr-bk-empty{text-align:center;padding:70px 24px;color:var(--muted)}
        .ovr-bk-empty .material-symbols-outlined{font-size:56px;color:var(--gray-border);margin-bottom:14px;display:block}
        .ovr-bk-empty h3{font-size:18px;font-weight:600;color:var(--ink);margin:0 0 6px}
        .ovr-bk-pag{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-top:1px solid var(--gray-border);background:var(--bg);font-size:14px;color:var(--muted);flex-wrap:wrap;gap:12px}
        .ovr-bk-pages{display:flex;gap:4px}
        .ovr-bk-page{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;border-radius:var(--r-sm);font-weight:500;text-decoration:none;color:var(--muted);border:1px solid var(--gray-border);background:var(--surf);padding:0 10px}
        .ovr-bk-page:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-bk-page--active{background:var(--navy);color:#fff;border-color:var(--navy)}
        .ovr-bk-notice{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--r-md);font-size:15px;font-weight:500;margin-bottom:18px}
        .ovr-bk-notice--success{background:var(--green-light);border:1px solid #b8d8b8;color:var(--green)}
        .ovr-bk-notice--error{background:var(--red-light);border:1px solid #e6b8b4;color:var(--red)}
        @media(max-width:1100px){.ovr-bk-stats{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:782px){.ovr-bk-wrap{padding:18px 14px 32px}.ovr-bk-actions{width:100%}.ovr-bk-actions .ovr-bk-btn{flex:1}.ovr-bk-search{max-width:none;flex:1 1 100%}.ovr-bk-table td:nth-child(2),.ovr-bk-table th:nth-child(2){display:none}}
        @media(max-width:600px){.ovr-bk-stats{grid-template-columns:1fr 1fr}.ovr-bk-table td:nth-child(6),.ovr-bk-table th:nth-child(6){display:none}}
    </style>

    <div class="ovr-bk-wrap">
        <div class="ovr-bk-head">
            <div>
                <h1><?php esc_html_e( 'Bookings', 'ovr-core' ); ?></h1>
                <p><?php esc_html_e( 'Reservations, holds and imported stays across every listing.', 'ovr-core' ); ?></p>
            </div>
            <div class="ovr-bk-actions">
                <?php if ( ! empty( $sync_url ) ) : ?>
                    <a href="<?php echo esc_url( $sync_url ); ?>" class="ovr-bk-btn ovr-bk-btn--ghost">
                        <span class="material-symbols-outlined">cloud_sync</span><?php esc_html_e( 'Sync Dashboard', 'ovr-core' ); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( $csv_url ); ?>" class="ovr-bk-btn ovr-bk-btn--ghost">
                    <span class="material-symbols-outlined">download</span><?php esc_html_e( 'Export CSV', 'ovr-core' ); ?>
                </a>
                <a href="<?php echo esc_url( $wp_sync_url ); ?>" class="ovr-bk-btn ovr-bk-btn--ghost"
                   onclick="return confirm('<?php echo esc_js( __( 'Import reservations from the configured WordPress source now?', 'ovr-core' ) ); ?>');">
                    <span class="material-symbols-outlined">sync</span><?php esc_html_e( 'New Booking (WordPress Sync)', 'ovr-core' ); ?>
                </a>
                <a href="<?php echo esc_url( $new_url ); ?>" class="ovr-bk-btn ovr-bk-btn--primary">
                    <span class="material-symbols-outlined">add</span><?php esc_html_e( 'New Booking', 'ovr-core' ); ?>
                </a>
            </div>
        </div>

        <?php if ( $notice ) : ?>
            <div class="ovr-bk-notice ovr-bk-notice--<?php echo esc_attr( $notice['type'] ); ?>">
                <span class="material-symbols-outlined"><?php echo 'error' === $notice['type'] ? 'error' : 'check_circle'; ?></span>
                <span><?php echo esc_html( $notice['text'] ); ?></span>
            </div>
        <?php endif; ?>

        <div class="ovr-bk-stats">
            <div class="ovr-bk-stat">
                <div class="ovr-bk-stat-ic"><span class="material-symbols-outlined">event_available</span></div>
                <div><div class="ovr-bk-stat-v"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></div><div class="ovr-bk-stat-l"><?php esc_html_e( 'Total Bookings', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-bk-stat">
                <div class="ovr-bk-stat-ic"><span class="material-symbols-outlined">upcoming</span></div>
                <div><div class="ovr-bk-stat-v"><?php echo esc_html( number_format_i18n( $stats['upcoming'] ) ); ?></div><div class="ovr-bk-stat-l"><?php esc_html_e( 'Upcoming Stays', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-bk-stat">
                <div class="ovr-bk-stat-ic"><span class="material-symbols-outlined">payments</span></div>
                <div><div class="ovr-bk-stat-v"><?php echo esc_html( $symbol . number_format_i18n( (float) $stats['revenue'], 0 ) ); ?></div><div class="ovr-bk-stat-l"><?php esc_html_e( 'Booked Revenue', 'ovr-core' ); ?></div></div>
            </div>
            <div class="ovr-bk-stat">
                <div class="ovr-bk-stat-ic"><span class="material-symbols-outlined">calendar_month</span></div>
                <div><div class="ovr-bk-stat-v"><?php echo esc_html( number_format_i18n( $stats['this_month'] ) ); ?></div><div class="ovr-bk-stat-l"><?php esc_html_e( 'Added This Month', 'ovr-core' ); ?></div></div>
            </div>
        </div>

        <div class="ovr-bk-card">
            <div class="ovr-bk-toolbar">
                <form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
                    <input type="hidden" name="post_type" value="ovr_property">
                    <input type="hidden" name="page" value="<?php echo esc_attr( \OVR\Admin\BookingsAdmin::PAGE_SLUG ); ?>">
                    <div class="ovr-bk-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" name="s" placeholder="<?php esc_attr_e( 'Search guest name, email, phone…', 'ovr-core' ); ?>" value="<?php echo esc_attr( $data['search'] ); ?>">
                    </div>
                    <select name="status">
                        <option value=""><?php esc_html_e( 'Any Status', 'ovr-core' ); ?></option>
                        <?php foreach ( $status_labels as $slug => $label ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cur, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="source">
                        <option value=""><?php esc_html_e( 'Any Source', 'ovr-core' ); ?></option>
                        <?php foreach ( BookingRepository::SOURCES as $src ) : ?>
                            <option value="<?php echo esc_attr( $src ); ?>" <?php selected( $cur_src, $src ); ?>><?php echo esc_html( ucfirst( $src ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="ovr-bk-btn ovr-bk-btn--ghost"><span class="material-symbols-outlined">filter_alt</span><?php esc_html_e( 'Filter', 'ovr-core' ); ?></button>
                    <a href="<?php echo esc_url( $base_url ); ?>" class="ovr-bk-btn ovr-bk-btn--ghost" title="<?php esc_attr_e( 'Clear all filters and search', 'ovr-core' ); ?>"><span class="material-symbols-outlined">filter_alt_off</span><?php esc_html_e( 'Reset', 'ovr-core' ); ?></a>
                </form>
                <span class="ovr-bk-count"><?php printf( esc_html( _n( '%d booking', '%d bookings', $total, 'ovr-core' ) ), (int) $total ); ?></span>
            </div>

            <?php if ( empty( $rows ) ) : ?>
                <div class="ovr-bk-empty">
                    <span class="material-symbols-outlined">event_busy</span>
                    <h3><?php esc_html_e( 'No bookings found', 'ovr-core' ); ?></h3>
                    <p><?php esc_html_e( 'Create a booking or import reservations to get started.', 'ovr-core' ); ?></p>
                </div>
            <?php else : ?>
                <table class="ovr-bk-table">
                    <thead>
                        <tr>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'id' ) ); ?>"><?php esc_html_e( 'Booking', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><?php esc_html_e( 'Property', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Guest', 'ovr-core' ); ?></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'checkin_date' ) ); ?>"><?php esc_html_e( 'Stay', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'amount' ) ); ?>"><?php esc_html_e( 'Amount', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><a href="<?php echo esc_url( $list->sort_url( $page_url, 'status' ) ); ?>"><?php esc_html_e( 'Status', 'ovr-core' ); ?><span class="material-symbols-outlined">unfold_more</span></a></th>
                            <th><?php esc_html_e( 'Source', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $b ) :
                            $bid       = (int) $b['id'];
                            $status    = $b['status'];
                            $edit_url  = add_query_arg( [ 'view' => 'edit', 'id' => $bid ], $page_url );
                            $del_url   = wp_nonce_url( admin_url( 'admin-post.php?action=ovr_booking_delete&id=' . $bid ), 'ovr_booking_delete_' . $bid );
                            $owner     = $b['owner_id'] ? get_the_author_meta( 'display_name', (int) $b['owner_id'] ) : '—';
                        ?>
                            <tr>
                                <td><span class="ovr-bk-id">#<?php echo esc_html( $bid ); ?></span></td>
                                <td>
                                    <?php $title = get_the_title( (int) $b['property_id'] ); ?>
                                    <div style="font-weight:600"><?php echo esc_html( $title ?: __( '(deleted listing)', 'ovr-core' ) ); ?></div>
                                    <div class="ovr-bk-guest-email"><?php echo esc_html( $owner ); ?></div>
                                </td>
                                <td>
                                    <div class="ovr-bk-guest-name"><?php echo esc_html( $b['guest_name'] ?: '—' ); ?></div>
                                    <div class="ovr-bk-guest-email"><?php echo esc_html( $b['guest_email'] ); ?></div>
                                </td>
                                <td class="ovr-bk-dates">
                                    <?php if ( $b['checkin_date'] && $b['checkout_date'] ) : ?>
                                        <?php echo esc_html( date_i18n( 'M j', strtotime( $b['checkin_date'] ) ) ); ?>
                                        <small>→</small>
                                        <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $b['checkout_date'] ) ) ); ?>
                                    <?php else : ?>—<?php endif; ?>
                                </td>
                                <td class="ovr-bk-amount"><?php echo esc_html( $symbol . number_format_i18n( (float) $b['amount'], 2 ) ); ?></td>
                                <td><span class="ovr-bk-badge ovr-bk-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_labels[ $status ] ?? ucfirst( $status ) ); ?></span></td>
                                <td><span class="ovr-bk-src"><?php echo esc_html( ucfirst( $b['source'] ) ); ?></span></td>
                                <td>
                                    <div class="ovr-bk-cell-actions">
                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-bk-act ovr-bk-act--edit" title="<?php esc_attr_e( 'Edit', 'ovr-core' ); ?>"><span class="material-symbols-outlined">edit</span></a>
                                        <a href="<?php echo esc_url( $del_url ); ?>" class="ovr-bk-act ovr-bk-act--danger" title="<?php esc_attr_e( 'Trash', 'ovr-core' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Move this booking to trash? Its calendar block will be removed.', 'ovr-core' ) ); ?>');"><span class="material-symbols-outlined">delete</span></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $max_pages > 1 ) : ?>
                    <div class="ovr-bk-pag">
                        <span><?php printf( esc_html__( 'Showing %1$d–%2$d of %3$d', 'ovr-core' ), ( ( $paged - 1 ) * $per_page ) + 1, min( $paged * $per_page, $total ), $total ); ?></span>
                        <div class="ovr-bk-pages">
                            <?php
                            $start = max( 1, $paged - 2 );
                            $end   = min( $max_pages, $start + 4 );
                            $start = max( 1, $end - 4 );
                            for ( $i = $start; $i <= $end; $i++ ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $page_url ) ); ?>" class="ovr-bk-page <?php echo $i === $paged ? 'ovr-bk-page--active' : ''; ?>"><?php echo esc_html( $i ); ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
