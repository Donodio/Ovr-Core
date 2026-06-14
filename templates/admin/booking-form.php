<?php
/**
 * Booking — New / Edit form (Feature 4).
 *
 * @package OVR
 * @var array|null           $booking       Existing row when editing, else null.
 * @var bool                 $is_edit       Whether this is an edit.
 * @var array<int,string>    $properties    id => title options.
 * @var array<string,string> $status_labels Status slug => label.
 * @var string[]             $sources       Allowed source slugs.
 * @var string               $back_url      Return-to-list URL.
 * @var string               $action_url    admin-post.php URL.
 * @var string               $nonce         Save nonce.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$b = $booking ?: [];
$v = static function ( string $key, $default = '' ) use ( $b ) {
    return isset( $b[ $key ] ) && null !== $b[ $key ] ? $b[ $key ] : $default;
};
?>
<div class="wrap ovr-bk">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}#wpcontent{padding-left:0}
        .ovr-bk{--navy:#000961;--blue:#00A2E8;--gold:#DEAF0C;--gold-dark:#b8920a;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--r-md:8px;--r-lg:12px;--shadow-md:0 4px 12px rgba(0,9,97,.08);font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;color:var(--ink)}
        .ovr-bk,.ovr-bk *{box-sizing:border-box}
        .ovr-bk .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:20px}
        .ovr-bkf-wrap{padding:24px 40px 48px;max-width:880px}
        .ovr-bkf-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:14px;font-weight:600;margin-bottom:14px}
        .ovr-bkf-back:hover{color:var(--blue)}
        .ovr-bkf-wrap h1{font-size:28px;font-weight:700;margin:0 0 22px}
        .ovr-bkf-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);padding:28px}
        .ovr-bkf-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 20px}
        .ovr-bkf-field{display:flex;flex-direction:column;gap:6px}
        .ovr-bkf-field--full{grid-column:1/-1}
        .ovr-bkf-field label{font-size:14px;font-weight:600;color:var(--ink)}
        .ovr-bkf-field label .req{color:var(--gold-dark)}
        .ovr-bkf-field input,.ovr-bkf-field select,.ovr-bkf-field textarea{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:11px 14px;font-size:15px;font-family:inherit;color:var(--ink);outline:none;width:100%}
        .ovr-bkf-field input:focus,.ovr-bkf-field select:focus,.ovr-bkf-field textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.12)}
        .ovr-bkf-field textarea{min-height:90px;resize:vertical}
        .ovr-bkf-foot{display:flex;gap:12px;margin-top:26px;padding-top:22px;border-top:1px solid var(--gray-border)}
        .ovr-bkf-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 26px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:48px}
        .ovr-bkf-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold)}
        .ovr-bkf-btn--primary:hover{background:var(--gold-dark)}
        .ovr-bkf-btn--ghost{background:var(--surf);color:var(--muted);border-color:var(--gray-border)}
        .ovr-bkf-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        @media(max-width:600px){.ovr-bkf-wrap{padding:18px 14px 32px}.ovr-bkf-grid{grid-template-columns:1fr}}
    </style>

    <div class="ovr-bkf-wrap">
        <a href="<?php echo esc_url( $back_url ); ?>" class="ovr-bkf-back"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'Back to bookings', 'ovr-core' ); ?></a>
        <h1><?php echo $is_edit ? esc_html__( 'Edit Booking', 'ovr-core' ) : esc_html__( 'New Booking', 'ovr-core' ); ?></h1>

        <form method="post" action="<?php echo esc_url( $action_url ); ?>">
            <input type="hidden" name="action" value="ovr_booking_save">
            <input type="hidden" name="booking_id" value="<?php echo esc_attr( (int) $v( 'id', 0 ) ); ?>">
            <?php wp_nonce_field( 'ovr_booking_save' ); ?>

            <div class="ovr-bkf-card">
                <div class="ovr-bkf-grid">
                    <div class="ovr-bkf-field ovr-bkf-field--full">
                        <label for="property_id"><?php esc_html_e( 'Property', 'ovr-core' ); ?> <span class="req">*</span></label>
                        <select id="property_id" name="property_id" required>
                            <option value=""><?php esc_html_e( '— Select a listing —', 'ovr-core' ); ?></option>
                            <?php foreach ( $properties as $pid => $title ) : ?>
                                <option value="<?php echo esc_attr( $pid ); ?>" <?php selected( (int) $v( 'property_id' ), $pid ); ?>><?php echo esc_html( $title ?: ( '#' . $pid ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ovr-bkf-field">
                        <label for="guest_name"><?php esc_html_e( 'Guest Name', 'ovr-core' ); ?> <span class="req">*</span></label>
                        <input type="text" id="guest_name" name="guest_name" value="<?php echo esc_attr( $v( 'guest_name' ) ); ?>" required>
                    </div>
                    <div class="ovr-bkf-field">
                        <label for="guest_email"><?php esc_html_e( 'Guest Email', 'ovr-core' ); ?></label>
                        <input type="email" id="guest_email" name="guest_email" value="<?php echo esc_attr( $v( 'guest_email' ) ); ?>">
                    </div>
                    <div class="ovr-bkf-field">
                        <label for="guest_phone"><?php esc_html_e( 'Guest Phone', 'ovr-core' ); ?></label>
                        <input type="text" id="guest_phone" name="guest_phone" value="<?php echo esc_attr( $v( 'guest_phone' ) ); ?>">
                    </div>
                    <div class="ovr-bkf-field">
                        <label for="amount"><?php esc_html_e( 'Amount', 'ovr-core' ); ?></label>
                        <input type="number" step="0.01" min="0" id="amount" name="amount" value="<?php echo esc_attr( $v( 'amount', '0.00' ) ); ?>">
                    </div>

                    <div class="ovr-bkf-field">
                        <label for="checkin_date"><?php esc_html_e( 'Check In', 'ovr-core' ); ?></label>
                        <input type="date" id="checkin_date" name="checkin_date" value="<?php echo esc_attr( $v( 'checkin_date' ) ); ?>">
                    </div>
                    <div class="ovr-bkf-field">
                        <label for="checkout_date"><?php esc_html_e( 'Check Out', 'ovr-core' ); ?></label>
                        <input type="date" id="checkout_date" name="checkout_date" value="<?php echo esc_attr( $v( 'checkout_date' ) ); ?>">
                    </div>

                    <div class="ovr-bkf-field">
                        <label for="status"><?php esc_html_e( 'Status', 'ovr-core' ); ?></label>
                        <select id="status" name="status">
                            <?php foreach ( $status_labels as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $v( 'status', 'booked' ), $slug ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ovr-bkf-field">
                        <label for="source"><?php esc_html_e( 'Source', 'ovr-core' ); ?></label>
                        <select id="source" name="source">
                            <?php foreach ( $sources as $src ) : ?>
                                <option value="<?php echo esc_attr( $src ); ?>" <?php selected( $v( 'source', 'manual' ), $src ); ?>><?php echo esc_html( ucfirst( $src ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ovr-bkf-field ovr-bkf-field--full">
                        <label for="notes"><?php esc_html_e( 'Notes', 'ovr-core' ); ?></label>
                        <textarea id="notes" name="notes"><?php echo esc_textarea( $v( 'notes' ) ); ?></textarea>
                    </div>
                </div>

                <div class="ovr-bkf-foot">
                    <button type="submit" class="ovr-bkf-btn ovr-bkf-btn--primary">
                        <span class="material-symbols-outlined">save</span>
                        <?php echo $is_edit ? esc_html__( 'Update Booking', 'ovr-core' ) : esc_html__( 'Create Booking', 'ovr-core' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $back_url ); ?>" class="ovr-bkf-btn ovr-bkf-btn--ghost"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></a>
                </div>
            </div>
        </form>
    </div>
</div>
