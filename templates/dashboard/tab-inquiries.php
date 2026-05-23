<?php
/**
 * Inquiries tab — inbox view with filter pills + per-row actions.
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
    'replied'  => __( 'Replied', 'ovr-core' ),
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
            <?php esc_html_e( 'Messages from guests across all your listings.', 'ovr-core' ); ?>
        </p>
    </header>

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
        <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px">
            <?php foreach ( $inquiries as $inq ) :
                $is_new   = ( $inq['status'] ?? '' ) === 'new';
                $property = get_post( (int) $inq['property_id'] );
            ?>
                <li style="padding:16px;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-md);background:<?php echo $is_new ? 'var(--ovr-primary-container)' : 'var(--ovr-surface)'; ?>;border-left:3px solid <?php echo $is_new ? 'var(--ovr-primary)' : 'transparent'; ?>">

                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:8px">
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:15px;margin-bottom:2px">
                                <?php echo esc_html( $inq['guest_name'] ); ?>
                                <?php if ( $is_new ) : ?>
                                    <span style="background:var(--ovr-primary);color:var(--ovr-on-primary);padding:1px 8px;border-radius:9999px;font-size:10px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase">NEW</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:12px;color:var(--ovr-on-surface-variant)">
                                <a href="mailto:<?php echo esc_attr( $inq['guest_email'] ); ?>" style="color:inherit">
                                    <?php echo esc_html( $inq['guest_email'] ); ?>
                                </a>
                                <?php if ( ! empty( $inq['guest_phone'] ) ) : ?>
                                    · <a href="tel:<?php echo esc_attr( $inq['guest_phone'] ); ?>" style="color:inherit">
                                        <?php echo esc_html( $inq['guest_phone'] ); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="font-size:12px;color:var(--ovr-outline);text-align:right">
                            <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $inq['created_at'] ) ); ?>
                        </div>
                    </div>

                    <?php if ( $property ) : ?>
                        <div style="margin-bottom:8px;font-size:13px">
                            <span style="color:var(--ovr-on-surface-variant)"><?php esc_html_e( 'For:', 'ovr-core' ); ?></span>
                            <a href="<?php echo esc_url( get_permalink( $property->ID ) ); ?>" target="_blank" rel="noopener" style="color:var(--ovr-primary);font-weight:500;text-decoration:none">
                                <?php echo esc_html( $property->post_title ); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $inq['checkin_date'] ) || ! empty( $inq['checkout_date'] ) ) : ?>
                        <div style="margin-bottom:8px;font-size:13px;color:var(--ovr-on-surface-variant)">
                            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">calendar_month</span>
                            <?php echo esc_html( ( $inq['checkin_date'] ?: '—' ) . ' → ' . ( $inq['checkout_date'] ?: '—' ) ); ?>
                            <?php if ( ! empty( $inq['guests'] ) ) : ?>
                                · <?php
                                /* translators: %d: guest count */
                                printf( esc_html( _n( '%d guest', '%d guests', (int) $inq['guests'], 'ovr-core' ) ), (int) $inq['guests'] );
                                ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <p style="margin:8px 0 0;font-size:14px;line-height:1.5;color:var(--ovr-on-surface);white-space:pre-wrap"><?php echo esc_html( $inq['message'] ); ?></p>

                    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                        <a href="mailto:<?php echo esc_attr( $inq['guest_email'] ); ?>?subject=Re:%20<?php echo $property ? rawurlencode( $property->post_title ) : ''; ?>"
                           class="ovr-btn ovr-btn-primary" style="padding:6px 14px;font-size:13px">
                            <span class="material-symbols-outlined" style="font-size:16px">reply</span>
                            <?php esc_html_e( 'Reply', 'ovr-core' ); ?>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
