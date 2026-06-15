<?php
/**
 * Public review-request form (Feature 7) — opened from a tokened guest link.
 *
 * @package OVR
 * @var array|null  $request    Request row, or null when invalid.
 * @var string      $token      The token.
 * @var bool        $done        Just-submitted thank-you state.
 * @var bool        $invalid     Token not found.
 * @var bool        $completed   Already reviewed.
 * @var string      $action_url  admin-post URL.
 * @var \WP_Post|null $property  The listing.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$err = isset( $_GET['ovr_review'] ) && 'error' === sanitize_key( wp_unslash( $_GET['ovr_review'] ) );
?>
<div class="ovr-rrp" style="max-width:620px;margin:48px auto;padding:0 20px;font-family:'Inter',system-ui,sans-serif">
    <style>
        .ovr-rrp *{box-sizing:border-box}
        .ovr-rrp-card{background:#fff;border:1px solid #e3e3e3;border-radius:16px;padding:32px;box-shadow:0 8px 32px rgba(0,9,97,.08)}
        .ovr-rrp h1{font-size:24px;font-weight:700;margin:0 0 6px;color:#000961}
        .ovr-rrp .sub{color:#5F6B7A;font-size:15px;margin:0 0 22px}
        .ovr-rrp label{display:block;font-size:14px;font-weight:600;margin:0 0 6px;color:#1C2430}
        .ovr-rrp input[type=text],.ovr-rrp input[type=email],.ovr-rrp input[type=date],.ovr-rrp textarea{width:100%;padding:11px 14px;border:1px solid #DBDBDB;border-radius:8px;font-size:15px;font-family:inherit;margin-bottom:16px}
        .ovr-rrp textarea{min-height:120px;resize:vertical}
        .ovr-rrp .row{display:flex;gap:14px;flex-wrap:wrap}
        .ovr-rrp .row>div{flex:1 1 200px}
        .ovr-rrp-stars{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:4px;margin-bottom:18px}
        .ovr-rrp-stars input{display:none}
        .ovr-rrp-stars label{font-size:34px;color:#DBDBDB;cursor:pointer;margin:0;transition:color .12s}
        .ovr-rrp-stars input:checked ~ label,.ovr-rrp-stars label:hover,.ovr-rrp-stars label:hover ~ label{color:#DEAF0C}
        .ovr-rrp-btn{display:inline-flex;align-items:center;gap:8px;background:#DEAF0C;color:#000961;border:none;border-radius:8px;padding:13px 26px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit}
        .ovr-rrp-btn:hover{background:#b8920a}
        .ovr-rrp-state{text-align:center;padding:20px 0}
        .ovr-rrp-state .material-symbols-outlined{font-size:56px;display:block;margin:0 auto 12px}
        .ovr-rrp-err{background:#f9e4e2;color:#B3261E;padding:12px 16px;border-radius:8px;font-size:14px;margin-bottom:16px}
    </style>

    <div class="ovr-rrp-card">
        <?php if ( $invalid ) : ?>
            <div class="ovr-rrp-state">
                <span class="material-symbols-outlined" style="color:#B3261E">link_off</span>
                <h1><?php esc_html_e( 'This review link is no longer valid', 'ovr-core' ); ?></h1>
                <p class="sub"><?php esc_html_e( 'The link may have expired or already been used.', 'ovr-core' ); ?></p>
            </div>
        <?php elseif ( $done ) : ?>
            <div class="ovr-rrp-state">
                <span class="material-symbols-outlined" style="color:#2E7D32">verified</span>
                <h1><?php esc_html_e( 'Thank you for your review!', 'ovr-core' ); ?></h1>
                <p class="sub"><?php esc_html_e( 'Your feedback has been submitted and will appear once approved.', 'ovr-core' ); ?></p>
            </div>
        <?php elseif ( $completed ) : ?>
            <div class="ovr-rrp-state">
                <span class="material-symbols-outlined" style="color:#2E7D32">task_alt</span>
                <h1><?php esc_html_e( 'This review was already submitted', 'ovr-core' ); ?></h1>
                <p class="sub"><?php esc_html_e( 'Thanks again for sharing your experience.', 'ovr-core' ); ?></p>
            </div>
        <?php else : ?>
            <h1><?php esc_html_e( 'Share your experience', 'ovr-core' ); ?></h1>
            <p class="sub">
                <?php
                /* translators: %s: property title */
                printf( esc_html__( 'How was your stay at %s?', 'ovr-core' ), '<strong>' . esc_html( $property ? $property->post_title : '' ) . '</strong>' );
                ?>
            </p>

            <?php if ( $err ) : ?>
                <div class="ovr-rrp-err"><?php esc_html_e( 'Please add a rating and your review before submitting.', 'ovr-core' ); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                <input type="hidden" name="action" value="ovr_review_public_submit">
                <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
                <?php wp_nonce_field( 'ovr_review_public_' . $token ); ?>

                <label for="ovr-rr-property"><?php esc_html_e( 'Property', 'ovr-core' ); ?></label>
                <input type="text" id="ovr-rr-property" value="<?php echo esc_attr( $property ? $property->post_title : '' ); ?>" readonly>

                <label><?php esc_html_e( 'Your rating', 'ovr-core' ); ?></label>
                <div class="ovr-rrp-stars">
                    <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                        <input type="radio" id="ovr-star-<?php echo (int) $i; ?>" name="rating" value="<?php echo (int) $i; ?>" <?php checked( 5, $i ); ?>>
                        <label for="ovr-star-<?php echo (int) $i; ?>" title="<?php echo (int) $i; ?>">★</label>
                    <?php endfor; ?>
                </div>

                <div class="row">
                    <div>
                        <label for="ovr-rr-name"><?php esc_html_e( 'Your name', 'ovr-core' ); ?></label>
                        <input type="text" id="ovr-rr-name" name="guest_name" value="<?php echo esc_attr( $request['guest_name'] ?? '' ); ?>">
                    </div>
                    <div>
                        <label for="ovr-rr-email"><?php esc_html_e( 'Your email', 'ovr-core' ); ?></label>
                        <input type="email" id="ovr-rr-email" name="guest_email" value="<?php echo esc_attr( $request['guest_email'] ?? '' ); ?>" required>
                    </div>
                </div>

                <label for="ovr-rr-stay"><?php esc_html_e( 'When did you stay?', 'ovr-core' ); ?></label>
                <input type="date" id="ovr-rr-stay" name="stay_date">

                <label for="ovr-rr-title"><?php esc_html_e( 'Headline (optional)', 'ovr-core' ); ?></label>
                <input type="text" id="ovr-rr-title" name="title" maxlength="120">

                <label for="ovr-rr-body"><?php esc_html_e( 'Your review', 'ovr-core' ); ?></label>
                <textarea id="ovr-rr-body" name="body" required></textarea>

                <button type="submit" class="ovr-rrp-btn">
                    <span class="material-symbols-outlined">send</span>
                    <?php esc_html_e( 'Submit review', 'ovr-core' ); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
