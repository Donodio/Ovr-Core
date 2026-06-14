<?php
/**
 * My Properties tab — full management table (Feature H).
 *
 * Columns: Property ID · Status · Property Type · Listing Name · Address ·
 * Village · Page Views · Last Updated · Upsell Status.
 * Actions: View · Edit · Delete · Bump · Purchase Upgrade · Activate/Deactivate.
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

<section class="ovr-card ovr-mylist" style="padding:24px">

    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
        <div>
            <h2 style="font-size:20px;font-weight:600;margin:0 0 4px">
                <?php esc_html_e( 'My Properties', 'ovr-core' ); ?>
            </h2>
            <p style="margin:0;font-size:13px;color:var(--ovr-on-surface-variant)">
                <?php
                /* translators: %d: property count */
                printf( esc_html( _n( '%d listing', '%d listings', count( $properties ), 'ovr-core' ) ), count( $properties ) );
                ?>
            </p>
        </div>
        <a href="<?php echo esc_url( $add_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-pill">
            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">add</span>
            <?php esc_html_e( 'Add New Property', 'ovr-core' ); ?>
        </a>
    </header>

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
                    <tr>
                        <th><?php esc_html_e( 'ID', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Listing Name', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Address', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Village', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Views', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Last Updated', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Upsell', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $properties as $p ) :
                    $listing_st = (string) get_post_meta( $p->ID, '_ovr_listing_status', true ) ?: 'active';
                    $is_active  = 'inactive' !== $listing_st;
                    $type_terms = get_the_terms( $p->ID, 'ovr_property_type' );
                    $type_name  = ( $type_terms && ! is_wp_error( $type_terms ) ) ? $type_terms[0]->name : '—';
                    $address    = trim( (string) get_post_meta( $p->ID, '_ovr_address', true ) );
                    $village    = (string) get_post_meta( $p->ID, '_ovr_village_name', true );
                    $views      = (int) get_post_meta( $p->ID, '_ovr_view_count', true );
                    $modified   = get_post_datetime( $p, 'modified' );
                    $upsells    = \OVR\Subscription\UpgradeActivator::active_labels( $p->ID );

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
                    $toggle_url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=ovr_toggle_listing_status&post=' . $p->ID ),
                        'ovr_toggle_status_' . $p->ID
                    );
                    $edit_url    = add_query_arg( [ 'tab' => 'add-listing', 'post' => $p->ID ], $base_url );
                    $upgrade_url = add_query_arg( [ 'tab' => 'upgrades', 'post' => $p->ID ], $base_url );
                ?>
                    <tr>
                        <td class="ovr-mylist__id">#<?php echo (int) $p->ID; ?></td>
                        <td>
                            <span class="ovr-mylist__badge" style="background:<?php echo esc_attr( $status_color ); ?>">
                                <?php echo esc_html( $status_text ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $type_name ); ?></td>
                        <td class="ovr-mylist__name"><strong><?php echo esc_html( $p->post_title ?: __( '(untitled)', 'ovr-core' ) ); ?></strong></td>
                        <td class="ovr-mylist__muted"><?php echo $address ? esc_html( $address ) : '—'; ?></td>
                        <td class="ovr-mylist__muted"><?php echo $village ? esc_html( $village ) : '—'; ?></td>
                        <td><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
                        <td class="ovr-mylist__muted"><?php echo $modified ? esc_html( $modified->format( 'M j, Y' ) ) : '—'; ?></td>
                        <td>
                            <?php if ( $upsells ) : ?>
                                <span class="ovr-mylist__badge" style="background:#ffe088;color:#241a00">★ <?php echo esc_html( implode( ', ', $upsells ) ); ?></span>
                            <?php else : ?>
                                <span class="ovr-mylist__muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="ovr-mylist__actions">
                            <a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" target="_blank" rel="noopener" class="ovr-mylist__act" title="<?php esc_attr_e( 'View on site', 'ovr-core' ); ?>">
                                <span class="material-symbols-outlined">open_in_new</span><?php esc_html_e( 'View', 'ovr-core' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-mylist__act ovr-mylist__act--primary">
                                <span class="material-symbols-outlined">edit</span><?php esc_html_e( 'Edit', 'ovr-core' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $bump_url ); ?>" class="ovr-mylist__act" title="<?php esc_attr_e( 'Bump to top of results (free, daily limit)', 'ovr-core' ); ?>">
                                <span class="material-symbols-outlined">trending_up</span><?php esc_html_e( 'Bump', 'ovr-core' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $upgrade_url ); ?>" class="ovr-mylist__act" title="<?php esc_attr_e( 'Purchase a promotion upgrade', 'ovr-core' ); ?>">
                                <span class="material-symbols-outlined">rocket_launch</span><?php esc_html_e( 'Upgrade', 'ovr-core' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $toggle_url ); ?>" class="ovr-mylist__act"
                               title="<?php echo $is_active ? esc_attr__( 'Hide from search', 'ovr-core' ) : esc_attr__( 'Show in search', 'ovr-core' ); ?>">
                                <span class="material-symbols-outlined"><?php echo $is_active ? 'visibility_off' : 'visibility'; ?></span>
                                <?php echo $is_active ? esc_html__( 'Deactivate', 'ovr-core' ) : esc_html__( 'Activate', 'ovr-core' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $del_url ); ?>" class="ovr-mylist__act ovr-mylist__act--danger"
                               data-ovr-confirm="<?php echo esc_attr( sprintf( __( 'Delete “%s”? You can restore it from Trash within the retention window.', 'ovr-core' ), $p->post_title ?: __( 'this listing', 'ovr-core' ) ) ); ?>">
                                <span class="material-symbols-outlined">delete</span><?php esc_html_e( 'Delete', 'ovr-core' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<style>
.ovr-mylist__scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
.ovr-mylist__table{width:100%;border-collapse:collapse;font-size:13px;min-width:920px}
.ovr-mylist__table th{text-align:left;padding:10px 12px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--ovr-on-surface-variant);border-bottom:2px solid var(--ovr-outline-variant,#e0e0e0);white-space:nowrap}
.ovr-mylist__table td{padding:12px;border-bottom:1px solid var(--ovr-outline-variant,#eee);vertical-align:middle}
.ovr-mylist__id{font-variant-numeric:tabular-nums;color:var(--ovr-on-surface-variant);white-space:nowrap}
.ovr-mylist__name{max-width:200px}
.ovr-mylist__muted{color:var(--ovr-on-surface-variant);max-width:180px}
.ovr-mylist__badge{display:inline-block;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600;white-space:nowrap}
.ovr-mylist__actions{white-space:nowrap}
.ovr-mylist__act{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;margin:2px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;color:var(--ovr-on-surface);border:1px solid var(--ovr-outline,#cfcfcf);background:var(--ovr-surface,#fff);line-height:1}
.ovr-mylist__act:hover{background:var(--ovr-surface-container,#f3f3f3)}
.ovr-mylist__act .material-symbols-outlined{font-size:15px}
.ovr-mylist__act--primary{background:var(--ovr-primary,#006c4a);color:#fff;border-color:var(--ovr-primary,#006c4a)}
.ovr-mylist__act--primary:hover{filter:brightness(.95);background:var(--ovr-primary,#006c4a)}
.ovr-mylist__act--danger{color:#93000a;border-color:#93000a}
.ovr-mylist__act--danger:hover{background:rgba(147,0,10,.06)}
</style>
<script>
(function(){
    document.querySelectorAll('[data-ovr-confirm]').forEach(function(el){
        el.addEventListener('click', function(e){
            if (!window.confirm(el.getAttribute('data-ovr-confirm'))) { e.preventDefault(); }
        });
    });
})();
</script>
