<?php
/**
 * Review Requests tab — generate / copy / send review links (Feature 7).
 *
 * @package OVR
 * @var \WP_Post[] $properties      Landlord's listings.
 * @var array      $review_requests Existing requests (rows).
 * @var array      $rr_bookings     Owner's bookings (for reservation linkage).
 * @var string     $rr_action       admin-post URL.
 * @var string     $rr_state        Result flag from query string.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$rr_bookings = is_array( $rr_bookings ?? null ) ? $rr_bookings : [];

use OVR\Property\ReviewRequest;

$notice_map = [
    'created'     => [ 'ok', __( 'Review link generated. Copy it below or email it to your guest.', 'ovr-core' ) ],
    'sent'        => [ 'ok', __( 'Review request emailed to the guest.', 'ovr-core' ) ],
    'send_failed' => [ 'err', __( 'Could not send the email — check the guest email address.', 'ovr-core' ) ],
    'error'       => [ 'err', __( 'Something went wrong generating the link.', 'ovr-core' ) ],
];
?>
<section class="ovr-card" style="padding:24px">
    <header style="margin-bottom:20px">
        <h2 style="font-size:20px;font-weight:600;margin:0 0 4px"><?php esc_html_e( 'Review Requests', 'ovr-core' ); ?></h2>
        <p style="margin:0;font-size:13px;color:var(--ovr-on-surface-variant)">
            <?php esc_html_e( 'Generate a private link inviting a past guest to review their stay. Copy it, or email it directly.', 'ovr-core' ); ?>
        </p>
    </header>

    <?php if ( $rr_state && isset( $notice_map[ $rr_state ] ) ) :
        $is_ok = 'ok' === $notice_map[ $rr_state ][0]; ?>
        <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:var(--ovr-radius-md);margin-bottom:16px;font-size:13px;background:<?php echo $is_ok ? 'var(--ovr-primary-fixed)' : '#f9e4e2'; ?>;color:<?php echo $is_ok ? 'var(--ovr-on-surface)' : '#B3261E'; ?>">
            <span class="material-symbols-outlined" style="font-size:18px"><?php echo $is_ok ? 'check_circle' : 'error'; ?></span>
            <?php echo esc_html( $notice_map[ $rr_state ][1] ); ?>
        </div>
    <?php endif; ?>

    <!-- Generate -->
    <form method="post" action="<?php echo esc_url( $rr_action ); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;padding:16px;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-md);margin-bottom:20px">
        <input type="hidden" name="action" value="ovr_review_request_create">
        <?php wp_nonce_field( 'ovr_review_request_create' ); ?>
        <label style="display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:500;flex:1 1 200px">
            <?php esc_html_e( 'Listing', 'ovr-core' ); ?>
            <select name="property_id" style="padding:9px 12px;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-sm);font-family:inherit">
                <option value=""><?php esc_html_e( '— Select —', 'ovr-core' ); ?></option>
                <?php foreach ( $properties as $p ) : ?>
                    <option value="<?php echo esc_attr( $p->ID ); ?>"><?php echo esc_html( $p->post_title ?: ( '#' . $p->ID ) ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ( $rr_bookings ) : ?>
        <label style="display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:500;flex:1 1 240px">
            <?php esc_html_e( 'Reservation (optional)', 'ovr-core' ); ?>
            <select name="booking_id" style="padding:9px 12px;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-sm);font-family:inherit">
                <option value=""><?php esc_html_e( '— None / manual —', 'ovr-core' ); ?></option>
                <?php foreach ( $rr_bookings as $bk ) :
                    $bk_label = trim( (string) ( $bk['guest_name'] ?? '' ) ) ?: __( 'Guest', 'ovr-core' );
                    $bk_prop  = get_the_title( (int) $bk['property_id'] );
                    $bk_dates = trim( (string) ( $bk['checkin_date'] ?? '' ) . ( ! empty( $bk['checkout_date'] ) ? ' → ' . $bk['checkout_date'] : '' ) );
                    $bk_text  = $bk_label . ( $bk_prop ? ' · ' . $bk_prop : '' ) . ( $bk_dates ? ' · ' . $bk_dates : '' );
                ?>
                    <option value="<?php echo esc_attr( (string) $bk['id'] ); ?>"><?php echo esc_html( $bk_text ); ?></option>
                <?php endforeach; ?>
            </select>
            <span style="font-size:11px;color:var(--ovr-on-surface-variant);font-weight:400"><?php esc_html_e( 'Ties the request to a specific stay (fills in the guest automatically).', 'ovr-core' ); ?></span>
        </label>
        <?php endif; ?>
        <label style="display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:500;flex:1 1 160px">
            <?php esc_html_e( 'Guest name (optional)', 'ovr-core' ); ?>
            <input type="text" name="guest_name" style="padding:9px 12px;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-sm);font-family:inherit">
        </label>
        <label style="display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:500;flex:1 1 200px">
            <?php esc_html_e( 'Guest email (optional)', 'ovr-core' ); ?>
            <input type="email" name="guest_email" style="padding:9px 12px;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-sm);font-family:inherit">
        </label>
        <button type="submit" class="ovr-btn ovr-btn-primary" style="padding:9px 18px;font-size:13px">
            <span class="material-symbols-outlined" style="font-size:16px">add_link</span>
            <?php esc_html_e( 'Generate link', 'ovr-core' ); ?>
        </button>
    </form>

    <?php if ( empty( $review_requests ) ) : ?>
        <div style="padding:40px 24px;text-align:center">
            <span class="material-symbols-outlined" style="font-size:48px;color:var(--ovr-outline);margin-bottom:8px">reviews</span>
            <p style="margin:0;color:var(--ovr-on-surface-variant);font-size:14px"><?php esc_html_e( 'No review requests yet.', 'ovr-core' ); ?></p>
        </div>
    <?php else : ?>
        <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px">
            <?php foreach ( $review_requests as $rr ) :
                $pid    = (int) $rr['property_id'];
                $link   = ReviewRequest::public_url( $pid, (string) $rr['token'] );
                $status = (string) $rr['status'];
                $badge  = [ 'pending' => '#8b95a5', 'sent' => 'var(--ovr-primary)', 'completed' => '#2E7D32' ][ $status ] ?? '#8b95a5';
            ?>
                <li style="padding:14px 16px;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-md)">
                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:8px">
                        <div style="font-weight:600;font-size:14px"><?php echo esc_html( get_the_title( $pid ) ?: __( '(listing removed)', 'ovr-core' ) ); ?>
                            <?php if ( $rr['guest_name'] ) : ?><span style="font-weight:400;color:var(--ovr-on-surface-variant)">· <?php echo esc_html( $rr['guest_name'] ); ?></span><?php endif; ?>
                        </div>
                        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#fff;background:<?php echo esc_attr( $badge ); ?>;padding:2px 9px;border-radius:9999px"><?php echo esc_html( ucfirst( $status ) ); ?></span>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <input type="text" readonly value="<?php echo esc_attr( $link ); ?>" onclick="this.select()" class="ovr-rr-link" style="flex:1 1 240px;min-width:0;padding:8px 12px;border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-sm);font-size:13px;font-family:monospace;background:var(--ovr-surface-container-low)">
                        <button type="button" class="ovr-btn ovr-rr-copy" data-link="<?php echo esc_attr( $link ); ?>" style="padding:8px 14px;font-size:13px;border:1px solid var(--ovr-outline-variant)">
                            <span class="material-symbols-outlined" style="font-size:16px">content_copy</span><?php esc_html_e( 'Copy', 'ovr-core' ); ?>
                        </button>
                        <button type="button" class="ovr-btn ovr-rr-share" data-link="<?php echo esc_attr( $link ); ?>"
                                data-title="<?php echo esc_attr( sprintf( __( 'Review your stay at %s', 'ovr-core' ), get_the_title( $pid ) ) ); ?>"
                                style="padding:8px 14px;font-size:13px;border:1px solid var(--ovr-outline-variant)">
                            <span class="material-symbols-outlined" style="font-size:16px">share</span><?php esc_html_e( 'Share', 'ovr-core' ); ?>
                        </button>
                        <?php if ( is_email( $rr['guest_email'] ) ) : ?>
                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'ovr_review_request_send', 'id' => (int) $rr['id'] ], admin_url( 'admin-post.php' ) ), 'ovr_review_request_send_' . (int) $rr['id'] ) ); ?>" class="ovr-btn ovr-btn-primary" style="padding:8px 14px;font-size:13px">
                                <span class="material-symbols-outlined" style="font-size:16px">send</span><?php esc_html_e( 'Email guest', 'ovr-core' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <script>
    (function(){
        document.querySelectorAll('.ovr-rr-copy').forEach(function(btn){
            btn.addEventListener('click',function(){
                var link=btn.getAttribute('data-link');
                var done=function(){var o=btn.innerHTML;btn.innerHTML='<span class="material-symbols-outlined" style="font-size:16px">check</span><?php echo esc_js( __( 'Copied', 'ovr-core' ) ); ?>';setTimeout(function(){btn.innerHTML=o;},1500);};
                if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(link).then(done,done);}
                else{var t=btn.closest('li').querySelector('.ovr-rr-link');t.select();document.execCommand('copy');done();}
            });
        });
        document.querySelectorAll('.ovr-rr-share').forEach(function(btn){
            btn.addEventListener('click',function(){
                var link=btn.getAttribute('data-link');
                var title=btn.getAttribute('data-title')||document.title;
                if(navigator.share){navigator.share({title:title,url:link}).catch(function(){});}
                else{
                    // No native share (desktop): copy + confirm.
                    var done=function(){var o=btn.innerHTML;btn.innerHTML='<span class="material-symbols-outlined" style="font-size:16px">check</span><?php echo esc_js( __( 'Link copied', 'ovr-core' ) ); ?>';setTimeout(function(){btn.innerHTML=o;},1500);};
                    if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(link).then(done,done);}
                    else{var t=btn.closest('li').querySelector('.ovr-rr-link');t.select();document.execCommand('copy');done();}
                }
            });
        });
    })();
    </script>
</section>
