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
        <div class="wrap">
            <h1><?php esc_html_e( 'Email Templates', 'ovr-core' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Edit the subject, content and recipients of every automated email. Disable any you don\'t want sent.', 'ovr-core' ); ?></p>
            <?php if ( 'saved' === $notice ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template saved.', 'ovr-core' ); ?></p></div>
            <?php elseif ( 'tested' === $notice ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test email sent.', 'ovr-core' ); ?></p></div>
            <?php elseif ( 'test_failed' === $notice ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Test email could not be sent — check the address and your mail configuration.', 'ovr-core' ); ?></p></div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped" style="margin-top:14px">
                <thead><tr>
                    <th><?php esc_html_e( 'Template', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Subject', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Recipient', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ( $templates as $t ) :
                    $edit_url = add_query_arg( 'edit', $t['template_key'], $this->page_url() );
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $t['name'] ); ?></strong><br><code style="font-size:11px"><?php echo esc_html( $t['template_key'] ); ?></code></td>
                        <td><?php echo esc_html( $t['subject'] ); ?></td>
                        <td><?php echo esc_html( ucfirst( $t['recipient'] ) ); ?></td>
                        <td><?php echo $t['is_enabled']
                            ? '<span style="color:#2e7d32;font-weight:600">' . esc_html__( 'Enabled', 'ovr-core' ) . '</span>'
                            : '<span style="color:#b3261e;font-weight:600">' . esc_html__( 'Disabled', 'ovr-core' ) . '</span>'; ?></td>
                        <td><a class="button" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'ovr-core' ); ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_editor( string $key ): void {
        $tpl = EmailTemplates::get( $key );
        if ( ! $tpl ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Unknown template.', 'ovr-core' ) . '</p></div>';
            return;
        }
        $vars    = EmailTemplates::variables_for( $key );
        $preview = Mailer::render( $key, $this->sample_vars( $vars ) );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $tpl['name'] ); ?></h1>
            <p><a href="<?php echo esc_url( $this->page_url() ); ?>">&larr; <?php esc_html_e( 'All templates', 'ovr-core' ); ?></a></p>

            <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="flex:1 1 480px;min-width:320px">
                    <input type="hidden" name="action" value="ovr_email_save">
                    <input type="hidden" name="template_key" value="<?php echo esc_attr( $key ); ?>">
                    <?php wp_nonce_field( 'ovr_email_save_' . $key ); ?>

                    <table class="form-table">
                        <tr><th><label><?php esc_html_e( 'Enabled', 'ovr-core' ); ?></label></th>
                            <td><label><input type="checkbox" name="is_enabled" value="1" <?php checked( $tpl['is_enabled'] ); ?>> <?php esc_html_e( 'Send this email', 'ovr-core' ); ?></label></td></tr>
                        <tr><th><label for="ovr-em-subj"><?php esc_html_e( 'Subject', 'ovr-core' ); ?></label></th>
                            <td><input id="ovr-em-subj" name="subject" type="text" class="large-text" value="<?php echo esc_attr( $tpl['subject'] ); ?>"></td></tr>
                        <tr><th><label for="ovr-em-rcpt"><?php esc_html_e( 'Recipient', 'ovr-core' ); ?></label></th>
                            <td>
                                <select id="ovr-em-rcpt" name="recipient">
                                    <?php foreach ( EmailTemplates::RECIPIENTS as $r ) : ?>
                                        <option value="<?php echo esc_attr( $r ); ?>" <?php selected( $tpl['recipient'], $r ); ?>><?php echo esc_html( ucfirst( $r ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input name="custom_email" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'custom@example.com (for Custom)', 'ovr-core' ); ?>" value="<?php echo esc_attr( $tpl['custom_email'] ); ?>">
                            </td></tr>
                        <tr><th><label for="ovr-em-html"><?php esc_html_e( 'HTML Body', 'ovr-core' ); ?></label></th>
                            <td><textarea id="ovr-em-html" name="body_html" rows="12" class="large-text code"><?php echo esc_textarea( (string) $tpl['body_html'] ); ?></textarea></td></tr>
                        <tr><th><label for="ovr-em-text"><?php esc_html_e( 'Plain Text (optional)', 'ovr-core' ); ?></label></th>
                            <td><textarea id="ovr-em-text" name="body_text" rows="5" class="large-text code"><?php echo esc_textarea( (string) $tpl['body_text'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Leave blank to auto-generate from the HTML.', 'ovr-core' ); ?></p></td></tr>
                        <tr><th><?php esc_html_e( 'Variables', 'ovr-core' ); ?></th>
                            <td>
                                <?php foreach ( $vars as $v ) : ?>
                                    <code style="display:inline-block;margin:2px 4px 2px 0;background:#f0f0f1;padding:2px 6px;border-radius:4px">{{<?php echo esc_html( $v ); ?>}}</code>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e( 'Insert these tokens in the subject or body; they are replaced when the email sends.', 'ovr-core' ); ?></p>
                            </td></tr>
                    </table>
                    <?php submit_button( __( 'Save Template', 'ovr-core' ) ); ?>
                </form>

                <div style="flex:1 1 360px;min-width:300px">
                    <h2><?php esc_html_e( 'Preview', 'ovr-core' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Rendered with sample data.', 'ovr-core' ); ?></p>
                    <div style="border:1px solid #dcdcde;border-radius:8px;overflow:hidden;background:#fff">
                        <div style="padding:8px 12px;border-bottom:1px solid #eee;font-size:12px;color:#555"><strong><?php esc_html_e( 'Subject:', 'ovr-core' ); ?></strong> <?php echo esc_html( $preview['subject'] ?? '' ); ?></div>
                        <iframe title="preview" style="width:100%;height:360px;border:0" srcdoc="<?php echo esc_attr( $preview['html'] ?? '' ); ?>"></iframe>
                    </div>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px;display:flex;gap:8px;align-items:flex-end">
                        <input type="hidden" name="action" value="ovr_email_test">
                        <input type="hidden" name="template_key" value="<?php echo esc_attr( $key ); ?>">
                        <?php wp_nonce_field( 'ovr_email_test_' . $key ); ?>
                        <label style="display:flex;flex-direction:column;font-size:12px;font-weight:600;gap:3px;flex:1">
                            <?php esc_html_e( 'Send a test to', 'ovr-core' ); ?>
                            <input name="test_email" type="email" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required>
                        </label>
                        <button class="button"><?php esc_html_e( 'Send Test', 'ovr-core' ); ?></button>
                    </form>
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
