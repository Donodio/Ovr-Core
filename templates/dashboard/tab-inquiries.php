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

    <?php
    $reply_state = isset( $_GET['ovr_reply'] ) ? sanitize_key( wp_unslash( $_GET['ovr_reply'] ) ) : '';
    if ( 'sent' === $reply_state ) : ?>
        <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:var(--ovr-radius-md);background:var(--ovr-primary-fixed);color:var(--ovr-on-surface);margin-bottom:16px;font-size:13px">
            <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>
            <?php esc_html_e( 'Your reply was saved to the inquiry thread.', 'ovr-core' ); ?>
        </div>
    <?php elseif ( 'error' === $reply_state ) : ?>
        <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:var(--ovr-radius-md);background:#f9e4e2;color:#B3261E;margin-bottom:16px;font-size:13px">
            <span class="material-symbols-outlined" style="font-size:18px">error</span>
            <?php esc_html_e( 'Reply could not be saved.', 'ovr-core' ); ?>
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
        <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px">
            <?php foreach ( $inquiries as $inq ) :
                $is_new   = ( $inq['status'] ?? '' ) === 'new';
                $property = get_post( (int) $inq['property_id'] );
            ?>
                <li style="padding:16px;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-md);color:var(--ovr-on-surface);background:<?php echo $is_new ? 'var(--ovr-primary-fixed)' : 'var(--ovr-surface)'; ?>;border-left:3px solid <?php echo $is_new ? 'var(--ovr-primary)' : 'transparent'; ?>">

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

                    <?php
                    $history = ! empty( $inq['responses'] ) ? (array) json_decode( (string) $inq['responses'], true ) : [];
                    if ( $history ) : ?>
                        <div style="margin-top:12px;padding-top:12px;border-top:1px dashed var(--ovr-outline-variant)">
                            <div style="font-size:12px;font-weight:600;color:var(--ovr-on-surface-variant);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px">
                                <?php esc_html_e( 'Response history', 'ovr-core' ); ?>
                            </div>
                            <?php foreach ( $history as $resp ) : ?>
                                <div style="background:var(--ovr-surface-container-low);border-radius:var(--ovr-radius-sm);padding:10px 12px;margin-bottom:8px">
                                    <div style="font-size:12px;color:var(--ovr-on-surface-variant);margin-bottom:3px">
                                        <strong><?php echo esc_html( $resp['by_name'] ?? __( 'You', 'ovr-core' ) ); ?></strong>
                                        · <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $resp['at'] ?? '' ) ); ?>
                                    </div>
                                    <div style="font-size:14px;line-height:1.5;white-space:pre-wrap"><?php echo esc_html( $resp['message'] ?? '' ); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <details style="flex:1 1 100%">
                            <summary class="ovr-btn ovr-btn-primary" style="padding:6px 14px;font-size:13px;cursor:pointer;display:inline-flex;width:auto;list-style:none">
                                <span class="material-symbols-outlined" style="font-size:16px">reply</span>
                                <?php esc_html_e( 'Reply', 'ovr-core' ); ?>
                            </summary>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px">
                                <input type="hidden" name="action" value="ovr_inquiry_reply">
                                <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (int) $inq['id'] ); ?>">
                                <?php wp_nonce_field( 'ovr_inquiry_reply_' . (int) $inq['id'] ); ?>
                                <textarea name="reply_message" required rows="3" placeholder="<?php esc_attr_e( 'Write your reply…', 'ovr-core' ); ?>" style="width:100%;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-sm);padding:10px;font-family:inherit;font-size:14px;resize:vertical"></textarea>
                                <div style="margin-top:8px;display:flex;gap:8px">
                                    <button type="submit" class="ovr-btn ovr-btn-primary" style="padding:6px 14px;font-size:13px">
                                        <span class="material-symbols-outlined" style="font-size:16px">send</span>
                                        <?php esc_html_e( 'Send reply', 'ovr-core' ); ?>
                                    </button>
                                    <a href="mailto:<?php echo esc_attr( $inq['guest_email'] ); ?>?subject=Re:%20<?php echo $property ? rawurlencode( $property->post_title ) : ''; ?>" class="ovr-btn" style="padding:6px 14px;font-size:13px;border:1px solid var(--ovr-outline-variant)">
                                        <span class="material-symbols-outlined" style="font-size:16px">mail</span>
                                        <?php esc_html_e( 'Email instead', 'ovr-core' ); ?>
                                    </a>
                                </div>
                            </form>
                        </details>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
