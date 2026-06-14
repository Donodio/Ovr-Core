<?php
/**
 * Support ticket detail — thread + reply + status controls (Feature 12).
 *
 * @package OVR
 * @var array      $ticket        Ticket row.
 * @var array      $replies       Reply rows (oldest first).
 * @var \WP_User|false|null $requester
 * @var array      $status_labels
 * @var string[]   $priorities
 * @var array<int,string> $agents Assignable agents id=>name.
 * @var string     $back_url
 * @var string     $action_url
 * @var string     $reply_nonce
 * @var string     $status_nonce
 * @var array|null $notice
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$tid     = (int) $ticket['id'];
$status  = (string) $ticket['status'];
$pri     = (string) $ticket['priority'];
$assigned= (int) ( $ticket['assigned_to'] ?? 0 );
?>
<div class="wrap ovr-sup">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}#wpcontent{padding-left:0}
        .ovr-sup{--navy:#000961;--blue:#00A2E8;--blue-light:#e5f5fe;--gold:#DEAF0C;--gold-dark:#b8920a;--green:#2E7D32;--green-light:#e4f4e4;--red:#B3261E;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--shadow-md:0 4px 12px rgba(0,9,97,.08);--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink)}
        .ovr-sup,.ovr-sup *{box-sizing:border-box}
        .ovr-sup .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:20px}
        .ovr-supt-wrap{padding:24px 40px 48px;display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start}
        .ovr-supt-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:14px;font-weight:600;margin-bottom:14px;grid-column:1/-1}
        .ovr-supt-back:hover{color:var(--blue)}
        .ovr-supt-main,.ovr-supt-side{display:flex;flex-direction:column;gap:18px}
        .ovr-supt-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);padding:24px}
        .ovr-supt-h{font-size:22px;font-weight:700;margin:0 0 4px}
        .ovr-supt-meta{font-size:13px;color:var(--muted);margin:0 0 18px}
        .ovr-supt-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:6px;font-size:12px;font-weight:600;text-transform:capitalize}
        .ovr-supt-badge--open{background:var(--blue-light);color:var(--blue)}
        .ovr-supt-badge--in_progress{background:#fdeede;color:#C7681C}
        .ovr-supt-badge--waiting{background:#fef5d6;color:var(--gold-dark)}
        .ovr-supt-badge--resolved,.ovr-supt-badge--closed{background:var(--green-light);color:var(--green)}
        .ovr-supt-msg{font-size:15px;line-height:1.65;white-space:pre-wrap;color:var(--ink)}
        .ovr-supt-thread{display:flex;flex-direction:column;gap:14px}
        .ovr-supt-reply{border:1px solid var(--gray-border);border-radius:var(--r-md);padding:14px 16px}
        .ovr-supt-reply.is-staff{background:var(--blue-light);border-color:#bfe6fb}
        .ovr-supt-reply-head{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:6px;font-weight:600}
        .ovr-supt-reply-body{font-size:14px;line-height:1.6;white-space:pre-wrap}
        .ovr-supt-field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
        .ovr-supt-field label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--muted)}
        .ovr-supt-field select,.ovr-supt-field textarea{width:100%;border:1px solid var(--gray-border);border-radius:var(--r-md);padding:10px 12px;font-size:14px;font-family:inherit;background:#fff}
        .ovr-supt-field textarea{min-height:120px;resize:vertical}
        .ovr-supt-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:0 18px;border-radius:var(--r-md);font-size:14px;font-weight:600;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:44px}
        .ovr-supt-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold)}
        .ovr-supt-btn--primary:hover{background:var(--gold-dark)}
        .ovr-sup-notice{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--r-md);font-size:15px;font-weight:500;margin-bottom:18px;grid-column:1/-1}
        .ovr-sup-notice--success{background:var(--green-light);border:1px solid #b8d8b8;color:var(--green)}
        .ovr-sup-notice--error{background:#f9e4e2;border:1px solid #e6b8b4;color:var(--red)}
        .ovr-supt-info{font-size:13px;line-height:1.8}.ovr-supt-info dt{color:var(--muted);font-weight:600}.ovr-supt-info dd{margin:0 0 8px;font-weight:600}
        @media(max-width:1000px){.ovr-supt-wrap{grid-template-columns:1fr}}
        @media(max-width:782px){.ovr-supt-wrap{padding:18px 14px 32px}}
    </style>

    <div class="ovr-supt-wrap">
        <a class="ovr-supt-back" href="<?php echo esc_url( $back_url ); ?>"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'Back to Support', 'ovr-core' ); ?></a>

        <?php if ( $notice ) : ?>
            <div class="ovr-sup-notice ovr-sup-notice--<?php echo esc_attr( $notice['type'] ); ?>">
                <span class="material-symbols-outlined"><?php echo 'error' === $notice['type'] ? 'error' : 'check_circle'; ?></span>
                <span><?php echo esc_html( $notice['text'] ); ?></span>
            </div>
        <?php endif; ?>

        <div class="ovr-supt-main">
            <div class="ovr-supt-card">
                <h1 class="ovr-supt-h">#<?php echo esc_html( $tid ); ?> · <?php echo esc_html( $ticket['subject'] ?: __( '(no subject)', 'ovr-core' ) ); ?></h1>
                <p class="ovr-supt-meta">
                    <span class="ovr-supt-badge ovr-supt-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_labels[ $status ] ?? ucfirst( $status ) ); ?></span>
                    &nbsp;·&nbsp;<?php echo esc_html( ucwords( str_replace( '-', ' ', (string) $ticket['category'] ) ) ); ?>
                    &nbsp;·&nbsp;<?php echo esc_html( date_i18n( 'M j, Y g:ia', strtotime( (string) $ticket['created_at'] ) ) ); ?>
                </p>
                <div class="ovr-supt-msg"><?php echo esc_html( (string) $ticket['message'] ); ?></div>
            </div>

            <div class="ovr-supt-card">
                <h2 class="ovr-supt-h" style="font-size:16px"><?php esc_html_e( 'Conversation', 'ovr-core' ); ?></h2>
                <?php if ( empty( $replies ) ) : ?>
                    <p class="ovr-supt-meta" style="margin:8px 0 0"><?php esc_html_e( 'No replies yet.', 'ovr-core' ); ?></p>
                <?php else : ?>
                    <div class="ovr-supt-thread" style="margin-top:12px">
                        <?php foreach ( $replies as $r ) :
                            $author = $r['user_id'] ? get_the_author_meta( 'display_name', (int) $r['user_id'] ) : __( 'User', 'ovr-core' );
                        ?>
                            <div class="ovr-supt-reply <?php echo ! empty( $r['is_staff'] ) ? 'is-staff' : ''; ?>">
                                <div class="ovr-supt-reply-head">
                                    <span><?php echo esc_html( $author ); ?><?php echo ! empty( $r['is_staff'] ) ? ' · ' . esc_html__( 'Staff', 'ovr-core' ) : ''; ?></span>
                                    <span><?php echo esc_html( date_i18n( 'M j, g:ia', strtotime( (string) $r['created_at'] ) ) ); ?></span>
                                </div>
                                <div class="ovr-supt-reply-body"><?php echo esc_html( (string) $r['message'] ); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ovr-supt-card">
                <h2 class="ovr-supt-h" style="font-size:16px"><?php esc_html_e( 'Add Reply', 'ovr-core' ); ?></h2>
                <form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin-top:12px">
                    <input type="hidden" name="action" value="ovr_ticket_reply">
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $tid ); ?>">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $reply_nonce ); ?>">
                    <div class="ovr-supt-field">
                        <label for="reply-msg"><?php esc_html_e( 'Message', 'ovr-core' ); ?></label>
                        <textarea id="reply-msg" name="message" required placeholder="<?php esc_attr_e( 'Type your reply to the requester…', 'ovr-core' ); ?>"></textarea>
                    </div>
                    <button type="submit" class="ovr-supt-btn ovr-supt-btn--primary"><span class="material-symbols-outlined">send</span><?php esc_html_e( 'Send Reply', 'ovr-core' ); ?></button>
                </form>
            </div>
        </div>

        <div class="ovr-supt-side">
            <div class="ovr-supt-card">
                <h2 class="ovr-supt-h" style="font-size:16px;margin-bottom:14px"><?php esc_html_e( 'Manage', 'ovr-core' ); ?></h2>
                <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                    <input type="hidden" name="action" value="ovr_ticket_status">
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr( $tid ); ?>">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $status_nonce ); ?>">
                    <div class="ovr-supt-field">
                        <label for="t-status"><?php esc_html_e( 'Status', 'ovr-core' ); ?></label>
                        <select id="t-status" name="status">
                            <?php foreach ( $status_labels as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ovr-supt-field">
                        <label for="t-pri"><?php esc_html_e( 'Priority', 'ovr-core' ); ?></label>
                        <select id="t-pri" name="priority">
                            <?php foreach ( $priorities as $p ) : ?>
                                <option value="<?php echo esc_attr( $p ); ?>" <?php selected( $pri, $p ); ?>><?php echo esc_html( ucfirst( $p ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ovr-supt-field">
                        <label for="t-assign"><?php esc_html_e( 'Assigned To', 'ovr-core' ); ?></label>
                        <select id="t-assign" name="assigned_to">
                            <option value="0"><?php esc_html_e( 'Unassigned', 'ovr-core' ); ?></option>
                            <?php foreach ( $agents as $aid => $name ) : ?>
                                <option value="<?php echo esc_attr( $aid ); ?>" <?php selected( $assigned, $aid ); ?>><?php echo esc_html( $name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="ovr-supt-btn ovr-supt-btn--primary"><span class="material-symbols-outlined">save</span><?php esc_html_e( 'Update Ticket', 'ovr-core' ); ?></button>
                </form>
            </div>

            <div class="ovr-supt-card">
                <h2 class="ovr-supt-h" style="font-size:16px;margin-bottom:12px"><?php esc_html_e( 'Requester', 'ovr-core' ); ?></h2>
                <dl class="ovr-supt-info">
                    <dt><?php esc_html_e( 'Name', 'ovr-core' ); ?></dt>
                    <dd><?php echo esc_html( $requester ? $requester->display_name : __( 'Guest', 'ovr-core' ) ); ?></dd>
                    <?php if ( $requester ) : ?>
                        <dt><?php esc_html_e( 'Email', 'ovr-core' ); ?></dt>
                        <dd><a href="mailto:<?php echo esc_attr( $requester->user_email ); ?>"><?php echo esc_html( $requester->user_email ); ?></a></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>
