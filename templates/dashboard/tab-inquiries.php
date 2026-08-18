<?php
/**
 * Inquiries tab — tabular inquiry history with expandable message rows.
 *
 * The list is a table so profiles and messages can be scanned at a glance.
 * Columns: Date · Listing (ID) · Address · From Name · From Email · View.
 * Clicking "View" expands a detail row beneath it with the full message,
 * dates/guests, and a confirmed delete control. Inquiries are a record, not
 * a messaging inbox — there is intentionally no reply/composer UI.
 *
 * @package OVR
 * @var array  $inquiries
 * @var string $filter_status
 * @var string $base_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$filters = [
    'all'      => __( 'All', 'ovr-core' ),
    'new'      => __( 'New', 'ovr-core' ),
    'archived' => __( 'Archived', 'ovr-core' ),
];

// Filter rows in PHP (small dataset — okay).
if ( 'all' !== $filter_status ) {
    $inquiries = array_values( array_filter( $inquiries, function ( $r ) use ( $filter_status ) {
        return ( $r['status'] ?? '' ) === $filter_status;
    } ) );
}
?>
<section class="ovr-card" style="padding:24px">

    <header style="margin-bottom:20px">
        <h2 style="font-size:20px;font-weight:600;margin:0 0 4px">
            <?php esc_html_e( 'Inquiries', 'ovr-core' ); ?>
        </h2>
        <p style="margin:0;font-size:13px;color:var(--ovr-on-surface-variant)">
            <?php esc_html_e( 'Inquiry history from guests across all your listings. Contact them directly using the details below.', 'ovr-core' ); ?>
        </p>
    </header>

    <?php
    $inq_state = isset( $_GET['ovr_inquiry'] ) ? sanitize_key( wp_unslash( $_GET['ovr_inquiry'] ) ) : '';
    if ( 'deleted' === $inq_state ) : ?>
        <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:var(--ovr-radius-md);background:var(--ovr-primary-fixed);color:var(--ovr-on-surface);margin-bottom:16px;font-size:13px">
            <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>
            <?php esc_html_e( 'Inquiry removed.', 'ovr-core' ); ?>
        </div>
    <?php elseif ( 'error' === $inq_state ) : ?>
        <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:var(--ovr-radius-md);background:#f9e4e2;color:#B3261E;margin-bottom:16px;font-size:13px">
            <span class="material-symbols-outlined" style="font-size:18px">error</span>
            <?php esc_html_e( 'That inquiry could not be removed.', 'ovr-core' ); ?>
        </div>
    <?php endif; ?>

    <!-- Filter pills -->
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;border-bottom:1px solid var(--ovr-outline-variant);padding-bottom:16px">
        <?php foreach ( $filters as $key => $label ) :
            $url    = add_query_arg( [ 'tab' => 'inquiries', 'status' => $key ], $base_url );
            $active = $key === $filter_status;
        ?>
            <a href="<?php echo esc_url( $url ); ?>"
               style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:9999px;font-size:13px;font-weight:500;text-decoration:none;color:<?php echo $active ? 'var(--ovr-on-primary)' : 'var(--ovr-on-surface-variant)'; ?>;background:<?php echo $active ? 'var(--ovr-primary)' : 'var(--ovr-surface-container-low)'; ?>;border:1px solid <?php echo $active ? 'var(--ovr-primary)' : 'var(--ovr-outline-variant)'; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ( empty( $inquiries ) ) : ?>
        <div style="padding:48px 24px;text-align:center">
            <span class="material-symbols-outlined" style="font-size:48px;color:var(--ovr-outline);margin-bottom:8px">inbox</span>
            <p style="margin:0;color:var(--ovr-on-surface-variant);font-size:14px">
                <?php esc_html_e( 'No inquiries match this filter.', 'ovr-core' ); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="ovr-inq-scroll" style="overflow-x:auto;border:1px solid var(--ovr-outline-variant);border-radius:12px">
            <table class="ovr-inq-table" style="width:100%;border-collapse:separate;border-spacing:0;font-size:13px">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Listing (ID)', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Address', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'From Name', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'From Email', 'ovr-core' ); ?></th>
                        <th style="text-align:right"><?php esc_html_e( 'View', 'ovr-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $inquiries as $inq ) :
                    $is_new   = ( $inq['status'] ?? '' ) === 'new';
                    $property = get_post( (int) $inq['property_id'] );
                    $address  = $property ? trim( (string) get_post_meta( $property->ID, '_ovr_address', true ) ) : '';
                    if ( '' === $address && $property ) {
                        $address = trim( (string) get_post_meta( $property->ID, '_ovr_village_name', true ) );
                    }
                    $fname   = (string) ( $inq['guest_name'] ?? __( 'Guest', 'ovr-core' ) );
                    $femail  = (string) ( $inq['guest_email'] ?? '' );
                ?>
                    <tr class="ovr-inq-row<?php echo $is_new ? ' is-new' : ''; ?>" data-ovr-inq-row>
                        <td class="ovr-inq-dt" style="white-space:nowrap">
                            <?php echo esc_html( mysql2date( get_option( 'date_format' ), $inq['created_at'] ) ); ?>
                        </td>
                        <td class="ovr-inq-listing">
                            <?php if ( $property ) : ?>
                                <a href="<?php echo esc_url( get_permalink( $property->ID ) ); ?>" target="_blank" rel="noopener" style="color:var(--ovr-primary);font-weight:500;text-decoration:none">
                                    <?php echo esc_html( $property->post_title ?: __( '(untitled)', 'ovr-core' ) ); ?>
                                </a>
                                <span style="color:var(--ovr-on-surface-variant);font-weight:600">#<?php echo (int) $inq['property_id']; ?></span>
                            <?php else : ?>
                                <?php
                                /* translators: %d: property ID */
                                printf( esc_html__( 'Listing #%d', 'ovr-core' ), (int) $inq['property_id'] );
                                ?>
                            <?php endif; ?>
                        </td>
                        <td class="ovr-inq-addr" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?php echo $address ? esc_html( $address ) : '—'; ?>
                        </td>
                        <td class="ovr-inq-name" style="white-space:nowrap">
                            <?php echo esc_html( $fname ); ?>
                            <?php if ( $is_new ) : ?>
                                <span style="background:var(--ovr-primary);color:var(--ovr-on-primary);padding:1px 7px;border-radius:9999px;font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-left:6px">NEW</span>
                            <?php endif; ?>
                        </td>
                        <td class="ovr-inq-email">
                            <?php if ( $femail ) : ?>
                                <a href="mailto:<?php echo esc_attr( $femail ); ?>" style="color:var(--ovr-on-surface-variant);text-decoration:none"><?php echo esc_html( $femail ); ?></a>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <button type="button" class="ovr-inq-view" data-ovr-inq-toggle aria-expanded="false">
                                <span class="material-symbols-outlined">expand_more</span>
                                <?php esc_html_e( 'View', 'ovr-core' ); ?>
                            </button>
                        </td>
                    </tr>
                    <tr class="ovr-inq-detail" data-ovr-inq-detail hidden>
                        <td colspan="6" style="padding:0;background:var(--ovr-surface-container-low)">
                            <div style="padding:20px 22px">

                                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;font-size:13px;color:var(--ovr-on-surface-variant);margin-bottom:8px">
                                    <span><span class="material-symbols-outlined" style="font-size:15px;vertical-align:-3px">call</span>
                                        <?php echo esc_html( $inq['guest_phone'] ?? '—' ); ?></span>
                                    <?php if ( ! empty( $inq['checkin_date'] ) || ! empty( $inq['checkout_date'] ) ) : ?>
                                        <span><span class="material-symbols-outlined" style="font-size:15px;vertical-align:-3px">calendar_month</span>
                                            <?php echo esc_html( ( $inq['checkin_date'] ?: '—' ) . ' → ' . ( $inq['checkout_date'] ?: '—' ) ); ?>
                                            <?php if ( ! empty( $inq['guests'] ) ) : ?>
                                                · <?php
                                                /* translators: %d: guest count */
                                                printf( esc_html( _n( '%d guest', '%d guests', (int) $inq['guests'], 'ovr-core' ) ), (int) $inq['guests'] );
                                                ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div style="background:#fff;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-sm);padding:14px 16px">
                                    <div style="font-size:12px;font-weight:600;color:var(--ovr-on-surface-variant);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px">
                                        <?php esc_html_e( 'Message', 'ovr-core' ); ?>
                                    </div>
                                    <p style="margin:0;font-size:14px;line-height:1.55;color:var(--ovr-on-surface);white-space:pre-wrap"><?php echo esc_html( $inq['message'] ); ?></p>
                                </div>

                                <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                                    <p style="margin:0;font-size:12px;color:var(--ovr-on-surface-variant);flex:1 1 100%">
                                        <?php esc_html_e( 'Contact the guest directly with the details above. Inquiries are a record, not a messaging inbox.', 'ovr-core' ); ?>
                                    </p>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                          onsubmit="return confirm('<?php echo esc_js( __( 'Delete this inquiry permanently? This cannot be undone.', 'ovr-core' ) ); ?>');">
                                        <input type="hidden" name="action" value="ovr_inquiry_delete">
                                        <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $inq['id'] ); ?>">
                                        <?php wp_nonce_field( 'ovr_inquiry_delete_' . (int) $inq['id'] ); ?>
                                        <button type="submit" class="ovr-btn" style="padding:7px 16px;font-size:13px;border:1px solid var(--ovr-outline-variant);color:#B3261E">
                                            <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                                            <?php esc_html_e( 'Delete', 'ovr-core' ); ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<style>
    .ovr-inq-table thead th{text-align:left;padding:10px 12px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--ovr-on-surface-variant);background:var(--ovr-surface-container-low);border-bottom:1px solid var(--ovr-outline-variant);white-space:nowrap}
    .ovr-inq-table td{padding:11px 12px;border-bottom:1px solid var(--ovr-outline-variant);vertical-align:middle}
    .ovr-inq-table tbody tr.ovr-inq-row:hover td{background:var(--ovr-surface-container-low)}
    .ovr-inq-table tbody tr.ovr-inq-row.is-new td{background:rgba(0,108,74,.05)}
    .ovr-inq-table tbody tr.ovr-inq-row.is-new .ovr-inq-name{font-weight:600}
    .ovr-inq-view{display:inline-flex;align-items:center;gap:4px;padding:7px 12px;border:1px solid var(--ovr-outline-variant);border-radius:8px;background:#fff;color:var(--ovr-primary);font-size:12px;font-weight:600;font-family:inherit;cursor:pointer}
    .ovr-inq-view:hover{background:var(--ovr-surface-container)}
    .ovr-inq-view .material-symbols-outlined{font-size:16px;transition:transform .2s}
    .ovr-inq-row.is-open .ovr-inq-view .material-symbols-outlined{transform:rotate(180deg)}
</style>
<script>
(function(){
    var scope = document.querySelector('.ovr-inq-table');
    if (!scope) { return; }
    scope.querySelectorAll('[data-ovr-inq-toggle]').forEach(function(btn){
        btn.addEventListener('click', function(){
            var row = btn.closest('tr');
            var detail = row.nextElementSibling;
            if (!detail || !detail.hasAttribute('data-ovr-inq-detail')) { return; }
            var open = detail.hidden;
            detail.hidden = !open;
            row.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { detail.scrollIntoView({ block:'nearest', behavior:'smooth' }); }
        });
    });
})();
</script>