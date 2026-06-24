<?php
/**
 * Email Template Manager admin screen (Milestone 3 Feature 6).
 *
 * Lists every transactional template and provides an editor: subject, HTML +
 * plain-text bodies, recipient mode, enabled toggle, a variable reference,
 * inline preview, and test send.
 *
 * @package OVR\Admin
 * @since   2.3.0
 */

namespace OVR\Admin;

use OVR\Email\EmailTemplates;
use OVR\Email\Mailer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmailManagerAdmin {

    public const PAGE_SLUG = 'ovr-core-emails';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_init', [ $this, 'maybe_seed' ] );
        add_action( 'admin_post_ovr_email_save', [ $this, 'handle_save' ] );
        add_action( 'admin_post_ovr_email_test', [ $this, 'handle_test' ] );
    }

    /** Self-heal: ensure the catalogue is seeded. */
    public function maybe_seed(): void {
        EmailTemplates::maybe_seed();
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Emails', 'ovr-core' ),
            __( 'Emails', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    private function page_url(): string {
        return add_query_arg( [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG ], admin_url( 'edit.php' ) );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage emails.', 'ovr-core' ) );
        }
        $edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
        if ( '' !== $edit ) {
            $this->render_editor( $edit );
            return;
        }
        $this->render_list();
    }

    private function render_list(): void {
        $templates = EmailTemplates::all();
        $notice    = isset( $_GET['ovr_email'] ) ? sanitize_key( wp_unslash( $_GET['ovr_email'] ) ) : '';
        ?>
        <div class="wrap ovr-adm">
            <style>#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}</style>
            <div class="ovr-adm-wrap">
                <div class="ovr-adm-head">
                    <div>
                        <h1><?php esc_html_e( 'Emails', 'ovr-core' ); ?></h1>
                        <p><?php esc_html_e( 'Edit the subject, content and recipients of every automated email. Disable any you don\'t want sent.', 'ovr-core' ); ?></p>
                    </div>
                </div>

                <?php if ( 'saved' === $notice ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--success"><span class="material-symbols-outlined">check_circle</span><span><?php esc_html_e( 'Template saved.', 'ovr-core' ); ?></span></div>
                <?php elseif ( 'tested' === $notice ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--success"><span class="material-symbols-outlined">check_circle</span><span><?php esc_html_e( 'Test email sent.', 'ovr-core' ); ?></span></div>
                <?php elseif ( 'test_failed' === $notice ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--error"><span class="material-symbols-outlined">error</span><span><?php esc_html_e( 'Test email could not be sent — check the address and your mail configuration.', 'ovr-core' ); ?></span></div>
                <?php endif; ?>

                <div class="ovr-adm-card">
                    <table class="ovr-adm-table">
                        <thead><tr>
                            <th><?php esc_html_e( 'Template', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Subject', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Recipient', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $templates as $t ) :
                            $edit_url = add_query_arg( 'edit', $t['template_key'], $this->page_url() );
                        ?>
                            <tr>
                                <td>
                                    <div class="ovr-adm-name"><?php echo esc_html( $t['name'] ); ?></div>
                                    <div class="ovr-adm-mono"><?php echo esc_html( $t['template_key'] ); ?></div>
                                </td>
                                <td><?php echo esc_html( $t['subject'] ); ?></td>
                                <td><?php echo esc_html( ucfirst( $t['recipient'] ) ); ?></td>
                                <td>
                                    <?php if ( $t['is_enabled'] ) : ?>
                                        <span class="ovr-adm-status ovr-adm-status--on"><span class="material-symbols-outlined">check_circle</span><?php esc_html_e( 'Enabled', 'ovr-core' ); ?></span>
                                    <?php else : ?>
                                        <span class="ovr-adm-status ovr-adm-status--off"><span class="material-symbols-outlined">do_not_disturb_on</span><?php esc_html_e( 'Disabled', 'ovr-core' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="ovr-adm-btn ovr-adm-btn--ghost ovr-adm-btn--sm" href="<?php echo esc_url( $edit_url ); ?>"><span class="material-symbols-outlined">edit</span><?php esc_html_e( 'Edit', 'ovr-core' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_editor( string $key ): void {
        $tpl = EmailTemplates::get( $key );
        if ( ! $tpl ) {
            echo '<div class="wrap ovr-adm"><div class="ovr-adm-wrap"><div class="ovr-adm-notice ovr-adm-notice--error"><span class="material-symbols-outlined">error</span><span>' . esc_html__( 'Unknown template.', 'ovr-core' ) . '</span></div></div></div>';
            return;
        }
        $vars    = EmailTemplates::variables_for( $key );
        $preview = Mailer::render( $key, $this->sample_vars( $vars ) );
        ?>
        <div class="wrap ovr-adm">
            <style>#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}</style>
            <div class="ovr-adm-wrap">
                <div class="ovr-adm-head">
                    <div>
                        <h1><?php echo esc_html( $tpl['name'] ); ?></h1>
                        <p><?php esc_html_e( 'Edit the subject, body and delivery of this automated email.', 'ovr-core' ); ?></p>
                    </div>
                    <div class="ovr-adm-actions">
                        <a href="<?php echo esc_url( $this->page_url() ); ?>" class="ovr-adm-btn ovr-adm-btn--ghost"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'All templates', 'ovr-core' ); ?></a>
                    </div>
                </div>

                <div class="ovr-adm-cols ovr-adm-cols--wide-left">
                    <div class="ovr-adm-card">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="ovr_email_save">
                            <input type="hidden" name="template_key" value="<?php echo esc_attr( $key ); ?>">
                            <?php wp_nonce_field( 'ovr_email_save_' . $key ); ?>

                            <div class="ovr-adm-card-body">
                                <div class="ovr-adm-form-grid">
                                    <div class="ovr-adm-field ovr-adm-field--full">
                                        <label class="ovr-adm-check"><input type="checkbox" name="is_enabled" value="1" <?php checked( $tpl['is_enabled'] ); ?>> <?php esc_html_e( 'Send this email', 'ovr-core' ); ?></label>
                                    </div>
                                    <div class="ovr-adm-field ovr-adm-field--full">
                                        <label class="ovr-adm-label" for="ovr-em-subj"><?php esc_html_e( 'Subject', 'ovr-core' ); ?></label>
                                        <input id="ovr-em-subj" name="subject" type="text" class="ovr-adm-input" value="<?php echo esc_attr( $tpl['subject'] ); ?>">
                                    </div>
                                    <div class="ovr-adm-field">
                                        <label class="ovr-adm-label" for="ovr-em-rcpt"><?php esc_html_e( 'Recipient', 'ovr-core' ); ?></label>
                                        <select id="ovr-em-rcpt" name="recipient" class="ovr-adm-select">
                                            <?php foreach ( EmailTemplates::RECIPIENTS as $r ) : ?>
                                                <option value="<?php echo esc_attr( $r ); ?>" <?php selected( $tpl['recipient'], $r ); ?>><?php echo esc_html( ucfirst( $r ) ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="ovr-adm-field">
                                        <label class="ovr-adm-label" for="ovr-em-custom"><?php esc_html_e( 'Custom email', 'ovr-core' ); ?></label>
                                        <input id="ovr-em-custom" name="custom_email" type="text" class="ovr-adm-input" placeholder="<?php esc_attr_e( 'custom@example.com (for Custom)', 'ovr-core' ); ?>" value="<?php echo esc_attr( $tpl['custom_email'] ); ?>">
                                    </div>
                                    <div class="ovr-adm-field ovr-adm-field--full">
                                        <label class="ovr-adm-label" for="ovr-em-html"><?php esc_html_e( 'HTML Body', 'ovr-core' ); ?></label>
                                        <textarea id="ovr-em-html" name="body_html" rows="12" class="ovr-adm-textarea code"><?php echo esc_textarea( (string) $tpl['body_html'] ); ?></textarea>
                                    </div>
                                    <div class="ovr-adm-field ovr-adm-field--full">
                                        <label class="ovr-adm-label" for="ovr-em-text"><?php esc_html_e( 'Plain Text (optional)', 'ovr-core' ); ?></label>
                                        <textarea id="ovr-em-text" name="body_text" rows="5" class="ovr-adm-textarea code"><?php echo esc_textarea( (string) $tpl['body_text'] ); ?></textarea>
                                        <p class="ovr-adm-hint"><?php esc_html_e( 'Leave blank to auto-generate from the HTML.', 'ovr-core' ); ?></p>
                                    </div>
                                    <div class="ovr-adm-field ovr-adm-field--full">
                                        <label class="ovr-adm-label"><?php esc_html_e( 'Variables', 'ovr-core' ); ?></label>
                                        <div class="ovr-adm-card" style="box-shadow:none;padding:12px 14px">
                                            <?php foreach ( $vars as $v ) : ?>
                                                <code class="ovr-adm-mono" style="display:inline-block;margin:2px 4px 2px 0;background:var(--gray-light);padding:3px 7px;border-radius:var(--r-sm);font-size:13px">{{<?php echo esc_html( $v ); ?>}}</code>
                                            <?php endforeach; ?>
                                            <p class="ovr-adm-hint"><?php esc_html_e( 'Insert these tokens in the subject or body; they are replaced when the email sends.', 'ovr-core' ); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="ovr-adm-form-foot">
                                <button type="submit" class="ovr-adm-btn ovr-adm-btn--primary"><span class="material-symbols-outlined">save</span><?php esc_html_e( 'Save Template', 'ovr-core' ); ?></button>
                            </div>
                        </form>
                    </div>

                    <div class="ovr-adm-card">
                        <div class="ovr-adm-card-head">
                            <h2><?php esc_html_e( 'Preview', 'ovr-core' ); ?></h2>
                        </div>
                        <div class="ovr-adm-card-body">
                            <p class="ovr-adm-hint" style="margin-top:0"><?php esc_html_e( 'Rendered with sample data.', 'ovr-core' ); ?></p>
                            <div style="border:1px solid var(--gray-border);border-radius:var(--r-md);overflow:hidden;background:#fff">
                                <div style="padding:8px 12px;border-bottom:1px solid var(--gray-border);font-size:12px;color:var(--muted)"><strong><?php esc_html_e( 'Subject:', 'ovr-core' ); ?></strong> <?php echo esc_html( $preview['subject'] ?? '' ); ?></div>
                                <iframe title="preview" style="width:100%;height:360px;border:0;display:block" srcdoc="<?php echo esc_attr( $preview['html'] ?? '' ); ?>"></iframe>
                            </div>

                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;display:flex;gap:10px;align-items:flex-end">
                                <input type="hidden" name="action" value="ovr_email_test">
                                <input type="hidden" name="template_key" value="<?php echo esc_attr( $key ); ?>">
                                <?php wp_nonce_field( 'ovr_email_test_' . $key ); ?>
                                <div class="ovr-adm-field" style="flex:1;margin-bottom:0">
                                    <label class="ovr-adm-label" for="ovr-em-test"><?php esc_html_e( 'Send a test to', 'ovr-core' ); ?></label>
                                    <input id="ovr-em-test" name="test_email" type="email" class="ovr-adm-input" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required>
                                </div>
                                <button class="ovr-adm-btn ovr-adm-btn--ghost"><span class="material-symbols-outlined">send</span><?php esc_html_e( 'Send Test', 'ovr-core' ); ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_save(): void {
        $key = isset( $_POST['template_key'] ) ? sanitize_key( wp_unslash( $_POST['template_key'] ) ) : '';
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        check_admin_referer( 'ovr_email_save_' . $key );

        EmailTemplates::save( $key, [
            'subject'      => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
            'body_html'    => wp_kses_post( wp_unslash( $_POST['body_html'] ?? '' ) ),
            'body_text'    => sanitize_textarea_field( wp_unslash( $_POST['body_text'] ?? '' ) ),
            'recipient'    => sanitize_key( wp_unslash( $_POST['recipient'] ?? 'user' ) ),
            'custom_email' => sanitize_text_field( wp_unslash( $_POST['custom_email'] ?? '' ) ),
            'is_enabled'   => ! empty( $_POST['is_enabled'] ),
        ] );

        wp_safe_redirect( add_query_arg( [ 'edit' => $key, 'ovr_email' => 'saved' ], $this->page_url() ) );
        exit;
    }

    public function handle_test(): void {
        $key = isset( $_POST['template_key'] ) ? sanitize_key( wp_unslash( $_POST['template_key'] ) ) : '';
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        check_admin_referer( 'ovr_email_test_' . $key );

        $to   = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );
        $vars = $this->sample_vars( EmailTemplates::variables_for( $key ) );

        // Render + send directly to the test address (bypasses recipient mode,
        // and ignores the enabled flag so admins can test disabled drafts).
        $rendered = Mailer::render( $key, $vars );
        $ok = false;
        if ( $rendered && is_email( $to ) ) {
            $ok = wp_mail( $to, '[TEST] ' . $rendered['subject'], $rendered['html'], [ 'Content-Type: text/html; charset=UTF-8' ] );
        }

        wp_safe_redirect( add_query_arg( [ 'edit' => $key, 'ovr_email' => $ok ? 'tested' : 'test_failed' ], $this->page_url() ) );
        exit;
    }

    /**
     * Sample token values for preview / test.
     *
     * @param string[] $vars
     * @return array<string, string>
     */
    private function sample_vars( array $vars ): array {
        $samples = [
            'user_name'       => 'Jane Doe',
            'user_email'      => 'jane@example.com',
            'guest_name'      => 'Sam Rivera',
            'listing_title'   => 'Lakeside Villa',
            'review_property' => 'Lakeside Villa',
            'property_id'     => '123',
            'property_url'    => home_url( '/property/lakeside-villa/' ),
            'membership_name' => 'Homeowner 5',
            'payment_amount'  => '$149.00',
            'expiration_date' => gmdate( 'F j, Y', time() + YEAR_IN_SECONDS ),
            'inquiry_message' => 'Hi! Is this available in March for two weeks?',
            'reset_url'       => home_url( '/login/?action=rp&key=SAMPLE' ),
            'dashboard_url'   => home_url( '/dashboard/' ),
            'login_url'       => home_url( '/login/' ),
            'reject_reason'   => 'Please add at least one photo.',
            'ticket_id'       => '4521',
            'ticket_subject'  => 'Question about payouts',
        ];
        $out = [];
        foreach ( $vars as $v ) {
            $out[ $v ] = $samples[ $v ] ?? ( '{{' . $v . '}}' );
        }
        return $out;
    }
}
