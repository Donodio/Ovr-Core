<?php
/**
 * CRM — Add / Edit Guest form (Feature 5).
 *
 * @package OVR
 * @var array|null $guest      Existing row when editing.
 * @var bool       $is_edit    Whether editing.
 * @var string     $back_url   List URL.
 * @var string     $action_url admin-post URL.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$g = $guest ?: [];
$v = static function ( string $k, $d = '' ) use ( $g ) {
    return isset( $g[ $k ] ) && null !== $g[ $k ] ? $g[ $k ] : $d;
};
?>
<div class="wrap ovr-crm">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}#wpcontent{padding-left:0}
        .ovr-crm{--navy:#000961;--blue:#00A2E8;--gold:#DEAF0C;--gold-dark:#b8920a;--gray-border:#DBDBDB;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--r-md:8px;--r-lg:12px;--shadow-md:0 4px 12px rgba(0,9,97,.08);font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;color:var(--ink)}
        .ovr-crm,.ovr-crm *{box-sizing:border-box}
        .ovr-crm .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:20px}
        .ovr-cf-wrap{padding:24px 40px 48px;max-width:760px}
        .ovr-cf-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:14px;font-weight:600;margin-bottom:14px}
        .ovr-cf-back:hover{color:var(--blue)}
        .ovr-cf-wrap h1{font-size:28px;font-weight:700;margin:0 0 22px}
        .ovr-cf-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);padding:28px}
        .ovr-cf-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 20px}
        .ovr-cf-field{display:flex;flex-direction:column;gap:6px}
        .ovr-cf-field--full{grid-column:1/-1}
        .ovr-cf-field label{font-size:14px;font-weight:600}
        .ovr-cf-field label .req{color:var(--gold-dark)}
        .ovr-cf-field input,.ovr-cf-field select,.ovr-cf-field textarea{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:11px 14px;font-size:15px;font-family:inherit;color:var(--ink);outline:none;width:100%}
        .ovr-cf-field input:focus,.ovr-cf-field select:focus,.ovr-cf-field textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.12)}
        .ovr-cf-field textarea{min-height:90px;resize:vertical}
        .ovr-cf-field .hint{font-size:12px;color:var(--gray-mid)}
        .ovr-cf-foot{display:flex;gap:12px;margin-top:26px;padding-top:22px;border-top:1px solid var(--gray-border)}
        .ovr-cf-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 26px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:48px}
        .ovr-cf-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold)}
        .ovr-cf-btn--primary:hover{background:var(--gold-dark)}
        .ovr-cf-btn--ghost{background:var(--surf);color:var(--muted);border-color:var(--gray-border)}
        .ovr-cf-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        @media(max-width:600px){.ovr-cf-wrap{padding:18px 14px 32px}.ovr-cf-grid{grid-template-columns:1fr}}
    </style>

    <div class="ovr-cf-wrap">
        <a href="<?php echo esc_url( $back_url ); ?>" class="ovr-cf-back"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'Back to manifest', 'ovr-core' ); ?></a>
        <h1><?php echo $is_edit ? esc_html__( 'Edit Guest', 'ovr-core' ) : esc_html__( 'Add Guest', 'ovr-core' ); ?></h1>

        <form method="post" action="<?php echo esc_url( $action_url ); ?>">
            <input type="hidden" name="action" value="ovr_guest_save">
            <input type="hidden" name="guest_id" value="<?php echo esc_attr( (int) $v( 'id', 0 ) ); ?>">
            <?php wp_nonce_field( 'ovr_guest_save' ); ?>

            <div class="ovr-cf-card">
                <div class="ovr-cf-grid">
                    <div class="ovr-cf-field">
                        <label for="name"><?php esc_html_e( 'Name', 'ovr-core' ); ?> <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="<?php echo esc_attr( $v( 'name' ) ); ?>" required>
                    </div>
                    <div class="ovr-cf-field">
                        <label for="email"><?php esc_html_e( 'Email', 'ovr-core' ); ?></label>
                        <input type="email" id="email" name="email" value="<?php echo esc_attr( $v( 'email' ) ); ?>">
                    </div>
                    <div class="ovr-cf-field">
                        <label for="phone"><?php esc_html_e( 'Phone', 'ovr-core' ); ?></label>
                        <input type="text" id="phone" name="phone" value="<?php echo esc_attr( $v( 'phone' ) ); ?>">
                    </div>
                    <div class="ovr-cf-field">
                        <label for="status"><?php esc_html_e( 'Status', 'ovr-core' ); ?></label>
                        <select id="status" name="status">
                            <option value="active" <?php selected( $v( 'status', 'active' ), 'active' ); ?>><?php esc_html_e( 'Active', 'ovr-core' ); ?></option>
                            <option value="inactive" <?php selected( $v( 'status', 'active' ), 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></option>
                        </select>
                    </div>
                    <div class="ovr-cf-field ovr-cf-field--full">
                        <label for="address"><?php esc_html_e( 'Address', 'ovr-core' ); ?></label>
                        <input type="text" id="address" name="address" value="<?php echo esc_attr( $v( 'address' ) ); ?>">
                    </div>
                    <div class="ovr-cf-field ovr-cf-field--full">
                        <label for="tags"><?php esc_html_e( 'Tags', 'ovr-core' ); ?></label>
                        <input type="text" id="tags" name="tags" value="<?php echo esc_attr( $v( 'tags' ) ); ?>" placeholder="<?php esc_attr_e( 'vip, returning, family', 'ovr-core' ); ?>">
                        <span class="hint"><?php esc_html_e( 'Comma-separated.', 'ovr-core' ); ?></span>
                    </div>
                    <div class="ovr-cf-field ovr-cf-field--full">
                        <label for="notes"><?php esc_html_e( 'Notes', 'ovr-core' ); ?></label>
                        <textarea id="notes" name="notes"><?php echo esc_textarea( $v( 'notes' ) ); ?></textarea>
                    </div>
                </div>

                <div class="ovr-cf-foot">
                    <button type="submit" class="ovr-cf-btn ovr-cf-btn--primary">
                        <span class="material-symbols-outlined">save</span>
                        <?php echo $is_edit ? esc_html__( 'Update Guest', 'ovr-core' ) : esc_html__( 'Add Guest', 'ovr-core' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $back_url ); ?>" class="ovr-cf-btn ovr-cf-btn--ghost"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></a>
                </div>
            </div>
        </form>
    </div>
</div>
