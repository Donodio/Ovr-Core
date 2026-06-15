<?php
/**
 * New support ticket form (Feature 12).
 *
 * @package OVR
 * @var string[]          $categories
 * @var string[]          $priorities
 * @var array<int,string> $users      Requester id=>label.
 * @var string            $back_url
 * @var string            $action_url
 * @var string            $nonce
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap ovr-sup">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}#wpcontent{padding-left:0}
        .ovr-sup{--navy:#000961;--blue:#00A2E8;--gold:#DEAF0C;--gold-dark:#b8920a;--gray-border:#DBDBDB;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--shadow-md:0 4px 12px rgba(0,9,97,.08);--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink)}
        .ovr-sup,.ovr-sup *{box-sizing:border-box}
        .ovr-sup .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:20px}
        .ovr-supf-wrap{padding:24px 40px 48px;max-width:760px}
        .ovr-supf-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:14px;font-weight:600;margin-bottom:14px}
        .ovr-supf-back:hover{color:var(--blue)}
        .ovr-supf-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);padding:30px}
        .ovr-supf-card h1{font-size:26px;font-weight:700;margin:0 0 22px}
        .ovr-supf-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px}
        .ovr-supf-field{display:flex;flex-direction:column;gap:6px;margin-bottom:18px}
        .ovr-supf-field.full{grid-column:1/-1}
        .ovr-supf-field label{font-size:13px;font-weight:700;color:var(--ink)}
        .ovr-supf-field input,.ovr-supf-field select,.ovr-supf-field textarea{width:100%;border:1px solid var(--gray-border);border-radius:var(--r-md);padding:11px 14px;font-size:15px;font-family:inherit;background:#fff}
        .ovr-supf-field textarea{min-height:130px;resize:vertical}
        .ovr-supf-foot{display:flex;gap:12px;margin-top:6px}
        .ovr-supf-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 26px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:48px}
        .ovr-supf-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold)}.ovr-supf-btn--primary:hover{background:var(--gold-dark)}
        .ovr-supf-btn--ghost{background:#fff;color:var(--navy);border-color:var(--gray-border)}.ovr-supf-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        @media(max-width:782px){.ovr-supf-wrap{padding:18px 14px 32px}.ovr-supf-grid{grid-template-columns:1fr}.ovr-supf-foot{flex-direction:column}.ovr-supf-btn{width:100%}}
    </style>
    <div class="ovr-supf-wrap">
        <a class="ovr-supf-back" href="<?php echo esc_url( $back_url ); ?>"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'Back to Support', 'ovr-core' ); ?></a>
        <form method="post" action="<?php echo esc_url( $action_url ); ?>">
            <input type="hidden" name="action" value="ovr_ticket_save">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
            <div class="ovr-supf-card">
                <h1><?php esc_html_e( 'New Ticket', 'ovr-core' ); ?></h1>
                <div class="ovr-supf-field full">
                    <label for="tk-subject"><?php esc_html_e( 'Subject', 'ovr-core' ); ?></label>
                    <input type="text" id="tk-subject" name="subject" required>
                </div>
                <div class="ovr-supf-grid">
                    <div class="ovr-supf-field">
                        <label for="tk-user"><?php esc_html_e( 'Requester', 'ovr-core' ); ?></label>
                        <select id="tk-user" name="user_id">
                            <option value="0"><?php esc_html_e( 'No specific user', 'ovr-core' ); ?></option>
                            <?php foreach ( $users as $uid => $label ) : ?>
                                <option value="<?php echo esc_attr( $uid ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ovr-supf-field">
                        <label for="tk-cat"><?php esc_html_e( 'Category', 'ovr-core' ); ?></label>
                        <select id="tk-cat" name="category">
                            <?php foreach ( $categories as $c ) : ?>
                                <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( ucwords( str_replace( '-', ' ', $c ) ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ovr-supf-field">
                        <label for="tk-pri"><?php esc_html_e( 'Priority', 'ovr-core' ); ?></label>
                        <select id="tk-pri" name="priority">
                            <?php foreach ( $priorities as $p ) : ?>
                                <option value="<?php echo esc_attr( $p ); ?>" <?php selected( 'normal', $p ); ?>><?php echo esc_html( ucfirst( $p ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="ovr-supf-field full">
                    <label for="tk-msg"><?php esc_html_e( 'Message', 'ovr-core' ); ?></label>
                    <textarea id="tk-msg" name="message" required></textarea>
                </div>
                <div class="ovr-supf-foot">
                    <button type="submit" class="ovr-supf-btn ovr-supf-btn--primary"><span class="material-symbols-outlined">add</span><?php esc_html_e( 'Create Ticket', 'ovr-core' ); ?></button>
                    <a href="<?php echo esc_url( $back_url ); ?>" class="ovr-supf-btn ovr-supf-btn--ghost"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></a>
                </div>
            </div>
        </form>
    </div>
</div>
