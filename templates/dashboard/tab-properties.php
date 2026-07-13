<?php
/**
 * My Properties tab — lean management table.
 *
 * Columns (Mark feedback P1): Property ID · Status · Address · Views ·
 * Last Updated · Actions. Listing Name, Upsell, Type and Village columns were
 * removed so the action buttons stay on-screen without horizontal scrolling.
 * Actions: View · Edit · Bump · Delete · Upgrade (Deactivate removed; visibility
 * is managed through the listing status in the editor).
 *
 * @package OVR
 * @var array  $properties  WP_Post objects
 * @var string $add_url
 * @var string $base_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$notice  = isset( $_GET['ovr_listing'] ) ? sanitize_key( wp_unslash( $_GET['ovr_listing'] ) ) : '';
$notices = [
    'created'     => __( 'Your listing has been published.', 'ovr-core' ),
    'updated'     => __( 'Your listing has been updated.', 'ovr-core' ),
    'deleted'     => __( 'Your listing has been deleted. You can restore it from Trash within the retention window.', 'ovr-core' ),
    'bumped'      => __( 'Your listing has been bumped to the top of its results.', 'ovr-core' ),
    'activated'   => __( 'Your listing is now active and visible in search.', 'ovr-core' ),
    'deactivated' => __( 'Your listing has been deactivated and hidden from search.', 'ovr-core' ),
];
$error_notice = 'bump_limit' === $notice
    ? sprintf(
        /* translators: %d: daily bump limit */
        __( 'You have reached your daily limit of %d bumps. Try again tomorrow.', 'ovr-core' ),
        \OVR\Property\Bump::daily_limit()
    )
    : '';
?>
<?php if ( isset( $notices[ $notice ] ) ) : ?>
    <div style="display:flex;align-items:center;gap:10px;background:rgba(0,108,74,.1);color:var(--ovr-secondary,#006c4a);border:1px solid rgba(0,108,74,.3);border-radius:12px;padding:14px 18px;font-size:14px;font-weight:600;margin-bottom:18px">
        <span class="material-symbols-outlined">check_circle</span>
        <span><?php echo esc_html( $notices[ $notice ] ); ?></span>
    </div>
<?php elseif ( '' !== $error_notice ) : ?>
    <div style="display:flex;align-items:center;gap:10px;background:rgba(179,38,30,.08);color:#93000a;border:1px solid rgba(179,38,30,.3);border-radius:12px;padding:14px 18px;font-size:14px;font-weight:600;margin-bottom:18px">
        <span class="material-symbols-outlined">error</span>
        <span><?php echo esc_html( $error_notice ); ?></span>
    </div>
<?php endif; ?>

<?php
// ── Stats (computed once from the owner's listings) ──
$stat_total = count( $properties );
$stat_active = 0; $stat_inactive = 0; $stat_views = 0;
foreach ( $properties as $p ) {
    $st = (string) get_post_meta( $p->ID, '_ovr_listing_status', true ) ?: 'active';
    if ( 'inactive' === $st ) { $stat_inactive++; } else { $stat_active++; }
    $stat_views += (int) get_post_meta( $p->ID, '_ovr_view_count', true );
}
?>
<section class="ovr-card ovr-mylist" style="padding:24px">

    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
        <div>
            <h2 style="font-size:20px;font-weight:600;margin:0 0 4px">
                <?php esc_html_e( 'My Properties', 'ovr-core' ); ?>
            </h2>
            <p style="margin:0;font-size:13px;color:var(--ovr-on-surface-variant)">
                <span id="ovr-mylist-count"><?php echo (int) $stat_total; ?></span>
                <?php echo esc_html( _n( 'listing', 'listings', $stat_total, 'ovr-core' ) ); ?>
            </p>
        </div>
        <a href="<?php echo esc_url( $add_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-pill">
            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">add</span>
            <?php esc_html_e( 'Add New Property', 'ovr-core' ); ?>
        </a>
    </header>

    <?php if ( ! empty( $properties ) ) : ?>
    <div class="ovr-mylist-stats">
        <div class="ovr-mylist-stat ovr-mylist-stat--total">
            <span class="ovr-mylist-stat-ic"><span class="material-symbols-outlined">home_work</span></span>
            <span class="ovr-mylist-stat-body"><span class="ovr-mylist-stat-v"><?php echo (int) $stat_total; ?></span><span class="ovr-mylist-stat-l"><?php esc_html_e( 'Total', 'ovr-core' ); ?></span></span>
        </div>
        <div class="ovr-mylist-stat ovr-mylist-stat--active">
            <span class="ovr-mylist-stat-ic"><span class="material-symbols-outlined">check_circle</span></span>
            <span class="ovr-mylist-stat-body"><span class="ovr-mylist-stat-v"><?php echo (int) $stat_active; ?></span><span class="ovr-mylist-stat-l"><?php esc_html_e( 'Active', 'ovr-core' ); ?></span></span>
        </div>
        <div class="ovr-mylist-stat ovr-mylist-stat--inactive">
            <span class="ovr-mylist-stat-ic"><span class="material-symbols-outlined">pause_circle</span></span>
            <span class="ovr-mylist-stat-body"><span class="ovr-mylist-stat-v"><?php echo (int) $stat_inactive; ?></span><span class="ovr-mylist-stat-l"><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></span></span>
        </div>
        <div class="ovr-mylist-stat ovr-mylist-stat--views">
            <span class="ovr-mylist-stat-ic"><span class="material-symbols-outlined">visibility</span></span>
            <span class="ovr-mylist-stat-body"><span class="ovr-mylist-stat-v"><?php echo esc_html( number_format_i18n( $stat_views ) ); ?></span><span class="ovr-mylist-stat-l"><?php esc_html_e( 'Total Views', 'ovr-core' ); ?></span></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( empty( $properties ) ) : ?>
        <div style="padding:48px 24px;text-align:center;background:var(--ovr-surface-container-low);border-radius:var(--ovr-radius-md)">
            <span class="material-symbols-outlined" style="font-size:56px;color:var(--ovr-outline);margin-bottom:12px">add_home</span>
            <h3 style="margin:0 0 8px"><?php esc_html_e( 'No properties yet', 'ovr-core' ); ?></h3>
            <p style="margin:0 0 20px;color:var(--ovr-on-surface-variant);font-size:14px;max-width:380px;margin-left:auto;margin-right:auto">
                <?php esc_html_e( 'Create your first listing with photos, amenities, pricing, and availability. Inquiries arrive in this dashboard the moment a guest reaches out.', 'ovr-core' ); ?>
            </p>
            <a href="<?php echo esc_url( $add_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-pill">
                <?php esc_html_e( 'List Your First Property', 'ovr-core' ); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="ovr-mylist__scroll">
            <table class="ovr-mylist__table">
                <thead>
                    <tr class="ovr-mylist__headrow">
                        <th><?php esc_html_e( 'ID', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Address', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Views', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Last Updated', 'ovr-core' ); ?></th>
                        <th class="ovr-mylist__actions-h">
                            <button type="button" class="ovr-mylist__reset" id="ovr-mylist-reset" title="<?php esc_attr_e( 'Clear filters', 'ovr-core' ); ?>">
                                <span class="material-symbols-outlined">filter_alt_off</span><?php esc_html_e( 'Reset', 'ovr-core' ); ?>
                            </button>
                        </th>
                    </tr>
                    <tr class="ovr-mylist__filters">
                        <td><input type="text" class="ovr-mylist__f" data-f="id" placeholder="<?php esc_attr_e( 'ID…', 'ovr-core' ); ?>" inputmode="numeric"></td>
                        <td>
                            <select class="ovr-mylist__f" data-f="status">
                                <option value=""><?php esc_html_e( 'All', 'ovr-core' ); ?></option>
                                <option value="active"><?php esc_html_e( 'Active', 'ovr-core' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></option>
                                <option value="draft"><?php esc_html_e( 'Draft', 'ovr-core' ); ?></option>
                            </select>
                        </td>
                        <td><input type="text" class="ovr-mylist__f" data-f="address" placeholder="<?php esc_attr_e( 'Address…', 'ovr-core' ); ?>"></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $properties as $p ) :
                    $listing_st = (string) get_post_meta( $p->ID, '_ovr_listing_status', true ) ?: 'active';
                    $address    = trim( (string) get_post_meta( $p->ID, '_ovr_address', true ) );
                    $village    = (string) get_post_meta( $p->ID, '_ovr_village_name', true );
                    $views      = (int) get_post_meta( $p->ID, '_ovr_view_count', true );
                    $modified   = get_post_datetime( $p, 'modified' );

                    // A compact human identifier now that the Name column is gone:
                    // the street address, falling back to the village or the title.
                    $identifier = $address ?: ( $village ?: (string) $p->post_title );

                    $status_color = [
                        'active'          => 'var(--ovr-secondary-container)',
                        'inactive'        => 'var(--ovr-error-container)',
                        'pending_renewal' => 'var(--ovr-tertiary-container)',
                        'draft'           => 'var(--ovr-surface-container)',
                    ][ $listing_st ] ?? 'var(--ovr-surface-container)';
                    $status_text = [
                        'active'          => __( 'Active', 'ovr-core' ),
                        'inactive'        => __( 'Inactive', 'ovr-core' ),
                        'pending_renewal' => __( 'Pending', 'ovr-core' ),
                        'draft'           => __( 'Draft', 'ovr-core' ),
                    ][ $listing_st ] ?? $listing_st;

                    $del_url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=ovr_delete_listing&post=' . $p->ID ),
                        'ovr_delete_listing_' . $p->ID
                    );
                    $bump_url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=ovr_bump_listing&post=' . $p->ID ),
                        'ovr_bump_listing_' . $p->ID
                    );
                    $edit_url    = add_query_arg( [ 'tab' => 'add-listing', 'post' => $p->ID ], $base_url );
                    $upgrade_url = add_query_arg( [ 'tab' => 'upgrades', 'post' => $p->ID ], $base_url );
                    $del_title   = $p->post_title ?: $identifier ?: __( 'this listing', 'ovr-core' );
                ?>
                    <tr class="ovr-mylist__row"
                        data-id="<?php echo (int) $p->ID; ?>"
                        data-status="<?php echo esc_attr( $listing_st ); ?>"
                        data-address="<?php echo esc_attr( strtolower( $identifier ) ); ?>">
                        <td class="ovr-mylist__id">#<?php echo (int) $p->ID; ?></td>
                        <td>
                            <span class="ovr-mylist__badge" style="background:<?php echo esc_attr( $status_color ); ?>">
                                <?php echo esc_html( $status_text ); ?>
                            </span>
                        </td>
                        <td class="ovr-mylist__addr"><?php echo $identifier ? esc_html( $identifier ) : '—'; ?></td>
                        <td><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
                        <td class="ovr-mylist__muted"><?php echo $modified ? esc_html( $modified->format( 'M j, Y' ) ) : '—'; ?></td>
                        <td class="ovr-mylist__actions">
                            <a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" target="_blank" rel="noopener" class="ovr-mylist__act" title="<?php esc_attr_e( 'View on site', 'ovr-core' ); ?>">
                                <span class="material-symbols-outlined">open_in_new</span><?php esc_html_e( 'View', 'ovr-core' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-mylist__act ovr-mylist__act--edit">
                                <span class="material-symbols-outlined">edit</span><?php esc_html_e( 'Edit', 'ovr-core' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $bump_url ); ?>" class="ovr-mylist__act" title="<?php esc_attr_e( 'Bump to top of results (free, daily limit)', 'ovr-core' ); ?>">
                                <span class="material-symbols-outlined">trending_up</span><?php esc_html_e( 'Bump', 'ovr-core' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $del_url ); ?>" class="ovr-mylist__act ovr-mylist__act--danger"
                               data-ovr-delete
                               data-title="<?php echo esc_attr( $del_title ); ?>">
                                <span class="material-symbols-outlined">delete</span><?php esc_html_e( 'Delete', 'ovr-core' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $upgrade_url ); ?>" class="ovr-mylist__act ovr-mylist__act--upgrade" title="<?php esc_attr_e( 'Purchase a promotion upgrade', 'ovr-core' ); ?>">
                                <span class="material-symbols-outlined">rocket_launch</span><?php esc_html_e( 'Upgrade', 'ovr-core' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Delete confirmation modal (P1.3) -->
<div class="ovr-mylist-modal" id="ovr-del-modal" aria-hidden="true">
    <div class="ovr-mylist-modal__backdrop" data-del-cancel></div>
    <div class="ovr-mylist-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ovr-del-title">
        <div class="ovr-mylist-modal__icon"><span class="material-symbols-outlined">delete_forever</span></div>
        <h3 class="ovr-mylist-modal__title" id="ovr-del-title"><?php esc_html_e( 'Permanently Delete Listing?', 'ovr-core' ); ?></h3>
        <p class="ovr-mylist-modal__body">
            <?php esc_html_e( 'You are about to permanently delete this property listing. This action cannot be undone.', 'ovr-core' ); ?>
        </p>
        <p class="ovr-mylist-modal__target" id="ovr-del-target"></p>
        <div class="ovr-mylist-modal__actions">
            <button type="button" class="ovr-mylist-modal__btn ovr-mylist-modal__btn--cancel" data-del-cancel><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></button>
            <a href="#" class="ovr-mylist-modal__btn ovr-mylist-modal__btn--delete" id="ovr-del-confirm"><?php esc_html_e( 'Delete Listing', 'ovr-core' ); ?></a>
        </div>
    </div>
</div>

<style>
/* ── Stats strip ── */
.ovr-mylist-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.ovr-mylist-stat{display:flex;align-items:center;gap:12px;background:var(--ovr-surface,#fff);border:1px solid var(--ovr-outline-variant,#e0e0e0);border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.ovr-mylist-stat-ic{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:11px;flex-shrink:0;background:rgba(0,108,74,.1);color:var(--ovr-primary,#006c4a)}
.ovr-mylist-stat-ic .material-symbols-outlined{font-size:24px}
.ovr-mylist-stat--active .ovr-mylist-stat-ic{background:rgba(0,138,32,.12);color:#008a20}
.ovr-mylist-stat--inactive .ovr-mylist-stat-ic{background:rgba(179,38,30,.1);color:#b3261e}
.ovr-mylist-stat--views .ovr-mylist-stat-ic{background:rgba(0,76,76,.1);color:#004c4c}
.ovr-mylist-stat-body{display:flex;flex-direction:column;min-width:0}
.ovr-mylist-stat-v{font-size:24px;font-weight:700;line-height:1.1;letter-spacing:-.02em;font-variant-numeric:tabular-nums;color:var(--ovr-on-surface,#1c2430)}
.ovr-mylist-stat-l{font-size:12.5px;color:var(--ovr-on-surface-variant,#5f6b7a);font-weight:500;margin-top:2px}

/* ── Table: no more min-width forcing a horizontal scroll on desktop ── */
.ovr-mylist__scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid var(--ovr-outline-variant,#e6e6e6);border-radius:12px}
.ovr-mylist__table{width:100%;border-collapse:separate;border-spacing:0;font-size:14px}
.ovr-mylist__table thead th{text-align:left;padding:13px 16px;font-size:11.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--ovr-on-surface-variant,#5f6b7a);background:var(--ovr-surface-container-low,#f4f7f6);border-bottom:1px solid var(--ovr-outline-variant,#e0e0e0);white-space:nowrap}
.ovr-mylist__filters td{padding:8px 12px;background:var(--ovr-surface-container-low,#f9fbfa);border-bottom:2px solid var(--ovr-outline-variant,#e0e0e0);vertical-align:middle}
.ovr-mylist__f{width:100%;min-width:80px;padding:7px 10px;font-size:13px;font-family:inherit;color:var(--ovr-on-surface,#1c2430);background:#fff;border:1px solid var(--ovr-outline,#c6d0cf);border-radius:8px;box-sizing:border-box}
.ovr-mylist__f:focus{outline:none;border-color:var(--ovr-primary,#006c4a);box-shadow:0 0 0 3px rgba(0,108,74,.14)}
.ovr-mylist__reset{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border:1px solid var(--ovr-outline,#c6d0cf);border-radius:8px;background:#fff;color:var(--ovr-on-surface,#1c2430);font-size:12px;font-weight:600;font-family:inherit;cursor:pointer;text-transform:none;letter-spacing:normal}
.ovr-mylist__reset:hover{background:var(--ovr-surface-container,#eef2f1)}
.ovr-mylist__reset .material-symbols-outlined{font-size:16px}
.ovr-mylist__table td{padding:14px 16px;border-bottom:1px solid var(--ovr-outline-variant,#eee);vertical-align:middle}
.ovr-mylist__table tbody tr:hover td{background:var(--ovr-surface-container-low,#f6faf9)}
.ovr-mylist__no-match td{text-align:center;padding:36px 16px;color:var(--ovr-on-surface-variant,#5f6b7a);font-style:italic}
.ovr-mylist__id{font-variant-numeric:tabular-nums;color:var(--ovr-on-surface-variant);white-space:nowrap;font-weight:600}
.ovr-mylist__addr{color:var(--ovr-on-surface);max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ovr-mylist__muted{color:var(--ovr-on-surface-variant);white-space:nowrap}
.ovr-mylist__badge{display:inline-block;padding:3px 11px;border-radius:9999px;font-size:11px;font-weight:600;white-space:nowrap}

/* Actions wrap within the cell so the buttons never push off-screen. */
.ovr-mylist__actions-h{text-align:right}
.ovr-mylist__actions{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end;min-width:230px}
.ovr-mylist__act{display:inline-flex;align-items:center;gap:4px;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;color:var(--ovr-on-surface,#1c2430);border:1px solid var(--ovr-outline,#cfcfcf);background:var(--ovr-surface,#fff);line-height:1;cursor:pointer;transition:background .15s,border-color .15s,color .15s,box-shadow .15s}
.ovr-mylist__act:hover{background:var(--ovr-surface-container,#f0f3f2)}
.ovr-mylist__act:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(0,108,74,.35)}
.ovr-mylist__act .material-symbols-outlined{font-size:16px}
/* Edit — high-contrast solid green (P1.4): white label on OVR green, never blue-on-blue. */
.ovr-mylist__act--edit{background:#006c4a;color:#fff;border-color:#006c4a}
.ovr-mylist__act--edit:hover{background:#00563b;border-color:#00563b;color:#fff}
.ovr-mylist__act--edit:focus-visible{box-shadow:0 0 0 3px rgba(0,108,74,.45)}
.ovr-mylist__act--edit[aria-disabled="true"],.ovr-mylist__act--edit:disabled{background:#9db8ad;border-color:#9db8ad;color:#eef4f1;cursor:not-allowed;pointer-events:none}
.ovr-mylist__act--upgrade{background:#fff6d9;color:#6b4e00;border-color:#e7cf7e}
.ovr-mylist__act--upgrade:hover{background:#ffefbf;border-color:#dcbf5e}
.ovr-mylist__act--danger{color:#93000a;border-color:#d99}
.ovr-mylist__act--danger:hover{background:rgba(147,0,10,.07);border-color:#93000a}

/* ── Delete confirmation modal ── */
.ovr-mylist-modal{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;padding:20px}
.ovr-mylist-modal.is-open{display:flex}
.ovr-mylist-modal__backdrop{position:absolute;inset:0;background:rgba(10,20,16,.55)}
.ovr-mylist-modal__dialog{position:relative;background:#fff;border-radius:16px;max-width:420px;width:100%;padding:28px 26px 22px;box-shadow:0 24px 60px rgba(0,0,0,.35);text-align:center}
.ovr-mylist-modal__icon{width:56px;height:56px;margin:0 auto 14px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(147,0,10,.1);color:#93000a}
.ovr-mylist-modal__icon .material-symbols-outlined{font-size:30px}
.ovr-mylist-modal__title{margin:0 0 10px;font-size:19px;font-weight:700;color:var(--ovr-on-surface,#1c2430)}
.ovr-mylist-modal__body{margin:0 0 6px;font-size:14px;line-height:1.55;color:var(--ovr-on-surface-variant,#5f6b7a)}
.ovr-mylist-modal__target{margin:0 0 20px;font-size:13px;font-weight:600;color:var(--ovr-on-surface,#1c2430)}
.ovr-mylist-modal__actions{display:flex;gap:10px;justify-content:center}
.ovr-mylist-modal__btn{flex:1 1 0;display:inline-flex;align-items:center;justify-content:center;padding:12px 16px;border-radius:10px;font-size:14px;font-weight:700;font-family:inherit;text-decoration:none;cursor:pointer;border:1px solid transparent}
.ovr-mylist-modal__btn--cancel{background:#fff;color:var(--ovr-on-surface,#1c2430);border-color:var(--ovr-outline,#c6d0cf)}
.ovr-mylist-modal__btn--cancel:hover{background:var(--ovr-surface-container,#f0f3f2)}
.ovr-mylist-modal__btn--delete{background:#b3261e;color:#fff}
.ovr-mylist-modal__btn--delete:hover{background:#93000a}
.ovr-mylist-modal__btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(0,108,74,.4)}

@media (max-width:768px){.ovr-mylist-stats{grid-template-columns:repeat(2,1fr)}}
</style>
<script>
(function(){
    // ── Delete confirmation modal (P1.3) ──
    var modal   = document.getElementById('ovr-del-modal');
    var confirm = document.getElementById('ovr-del-confirm');
    var target  = document.getElementById('ovr-del-target');
    var lastFocus = null;
    function openModal(url, title){
        if (!modal || !confirm) { return; }
        confirm.setAttribute('href', url);
        if (target) { target.textContent = title ? ('“' + title + '”') : ''; }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        var c = modal.querySelector('.ovr-mylist-modal__btn--cancel');
        if (c) { c.focus(); }
    }
    function closeModal(){
        if (!modal) { return; }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }
    document.querySelectorAll('[data-ovr-delete]').forEach(function(el){
        el.addEventListener('click', function(e){
            e.preventDefault();
            lastFocus = el;
            openModal(el.getAttribute('href'), el.getAttribute('data-title') || '');
        });
    });
    if (modal) {
        modal.querySelectorAll('[data-del-cancel]').forEach(function(b){
            b.addEventListener('click', function(e){ e.preventDefault(); closeModal(); });
        });
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && modal.classList.contains('is-open')) { closeModal(); }
        });
    }

    // ── Client-side column filters (the owner's list is small & already on the page) ──
    var scope = document.querySelector('.ovr-mylist');
    if (!scope) { return; }
    var filters = Array.prototype.slice.call(scope.querySelectorAll('.ovr-mylist__f'));
    var rows    = Array.prototype.slice.call(scope.querySelectorAll('tbody .ovr-mylist__row'));
    var resetBtn = document.getElementById('ovr-mylist-reset');
    var countEl  = document.getElementById('ovr-mylist-count');
    var tbody    = scope.querySelector('tbody');
    if (!filters.length || !rows.length || !tbody) { return; }

    var noMatch = document.createElement('tr');
    noMatch.className = 'ovr-mylist__no-match';
    noMatch.style.display = 'none';
    var td = document.createElement('td');
    td.setAttribute('colspan', '6');
    td.textContent = '<?php echo esc_js( __( 'No listings match your filters.', 'ovr-core' ) ); ?>';
    noMatch.appendChild(td);
    tbody.appendChild(noMatch);

    function apply(){
        var vals = {};
        filters.forEach(function(f){ vals[f.getAttribute('data-f')] = (f.value || '').trim().toLowerCase(); });
        var shown = 0;
        rows.forEach(function(row){
            var ok = true;
            for (var key in vals) {
                if (!vals[key]) { continue; }
                var data = (row.getAttribute('data-' + key) || '').toLowerCase();
                if (key === 'status') {
                    if (data !== vals[key]) { ok = false; break; }
                } else if (data.indexOf(vals[key]) === -1) {
                    ok = false; break;
                }
            }
            row.style.display = ok ? '' : 'none';
            if (ok) { shown++; }
        });
        noMatch.style.display = shown ? 'none' : '';
        if (countEl) { countEl.textContent = shown; }
    }

    filters.forEach(function(f){
        f.addEventListener('input', apply);
        f.addEventListener('change', apply);
    });
    if (resetBtn) {
        resetBtn.addEventListener('click', function(){
            filters.forEach(function(f){ f.value = ''; });
            apply();
        });
    }
})();
</script>
