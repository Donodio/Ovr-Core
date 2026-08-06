<?php
/**
 * Email template registry + repository (Milestone 3 Feature 6).
 *
 * Defines the catalogue of transactional emails and stores their admin-editable
 * content in wp_ovr_email_templates. Each row: subject, HTML body, plain-text
 * body, recipient mode (user/admin/both/custom), enabled flag. Bodies use
 * {{variable}} tokens substituted at send time by Mailer.
 *
 * @package OVR\Email
 * @since   2.3.0
 */

namespace OVR\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmailTemplates {

    public const RECIPIENTS = [ 'user', 'admin', 'both', 'custom' ];

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_email_templates';
    }

    /**
     * The canonical catalogue. Each entry: name, subject, html (inner body),
     * recipient default, and the variables available to the editor.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array {
        $sig = "<p style=\"color:#888;font-size:12px;margin-top:24px\">{{site_name}} · <a href=\"{{site_url}}\">{{site_url}}</a></p>";
        return [
            'registration_welcome' => [
                'name'      => __( 'Registration / Welcome', 'ovr-core' ),
                'subject'   => __( 'Welcome to {{site_name}}', 'ovr-core' ),
                'html'      => "<h2>Welcome, {{user_name}}!</h2><p>Your account on {{site_name}} is ready. You can sign in any time from your dashboard.</p><p><a href=\"{{dashboard_url}}\" style=\"display:inline-block;background:#004c4c;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none\">Go to your dashboard</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'user_email', 'dashboard_url', 'login_url', 'site_name', 'site_url' ],
            ],
            'inquiry_landlord' => [
                'name'      => __( 'Inquiry Received (Landlord)', 'ovr-core' ),
                'subject'   => __( 'New inquiry from {{guest_name}} for {{listing_title}}', 'ovr-core' ),
                'html'      => "<h2>New inquiry</h2><p><strong>{{guest_name}}</strong> is interested in <a href=\"{{property_url}}\">{{listing_title}}</a> (ID {{property_id}}).</p><blockquote style=\"border-left:3px solid #ddd;padding-left:12px;color:#444\">{{inquiry_message}}</blockquote><p><a href=\"{{dashboard_url}}\">Reply from your dashboard</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'guest_name', 'listing_title', 'property_id', 'property_url', 'inquiry_message', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'inquiry_guest' => [
                'name'      => __( 'Inquiry Confirmation (Guest)', 'ovr-core' ),
                'subject'   => __( 'Your inquiry about {{listing_title}} has been received', 'ovr-core' ),
                'html'      => "<h2>Thanks, {{guest_name}}!</h2><p>Your inquiry about <a href=\"{{property_url}}\">{{listing_title}}</a> has been sent to the owner, who will be in touch soon.</p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'guest_name', 'listing_title', 'property_url', 'site_name', 'site_url' ],
            ],
            'password_reset' => [
                'name'      => __( 'Password Reset', 'ovr-core' ),
                'subject'   => __( 'Reset your {{site_name}} password', 'ovr-core' ),
                'html'      => "<h2>Password reset</h2><p>We received a request to reset your password. Click below to choose a new one. If you didn't request this, you can ignore this email.</p><p><a href=\"{{reset_url}}\" style=\"display:inline-block;background:#004c4c;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none\">Reset password</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'reset_url', 'site_name', 'site_url' ],
            ],
            'subscription_purchase' => [
                'name'      => __( 'Subscription Purchase', 'ovr-core' ),
                'subject'   => __( 'Your {{membership_name}} membership is active', 'ovr-core' ),
                'html'      => "<h2>Thank you for your purchase</h2><p>Your <strong>{{membership_name}}</strong> membership is now active. Amount paid: {{payment_amount}}.</p><p><a href=\"{{dashboard_url}}\">Manage your membership</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'membership_name', 'payment_amount', 'expiration_date', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'subscription_renewal' => [
                'name'      => __( 'Subscription Renewal', 'ovr-core' ),
                'subject'   => __( 'Your {{membership_name}} membership has renewed', 'ovr-core' ),
                'html'      => "<h2>Membership renewed</h2><p>Your <strong>{{membership_name}}</strong> membership has renewed and is valid through {{expiration_date}}.</p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'membership_name', 'expiration_date', 'payment_amount', 'site_name', 'site_url' ],
            ],
            'subscription_expiry' => [
                'name'      => __( 'Subscription Expiry', 'ovr-core' ),
                'subject'   => __( 'Your {{membership_name}} membership has expired', 'ovr-core' ),
                'html'      => "<h2>Your membership has expired</h2><p>Your <strong>{{membership_name}}</strong> membership has expired. Renew to keep your listings visible.</p><p><a href=\"{{dashboard_url}}\" style=\"display:inline-block;background:#004c4c;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none\">Renew now</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'membership_name', 'expiration_date', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'listing_approved' => [
                'name'      => __( 'Listing Approved', 'ovr-core' ),
                'subject'   => __( 'Your listing "{{listing_title}}" is live', 'ovr-core' ),
                'html'      => "<h2>Your listing is live</h2><p><a href=\"{{property_url}}\">{{listing_title}}</a> (ID {{property_id}}) has been approved and is now visible to renters.</p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'listing_title', 'property_id', 'property_url', 'site_name', 'site_url' ],
            ],
            'listing_rejected' => [
                'name'      => __( 'Listing Rejected', 'ovr-core' ),
                'subject'   => __( 'Your listing "{{listing_title}}" needs attention', 'ovr-core' ),
                'html'      => "<h2>Your listing needs attention</h2><p><a href=\"{{property_url}}\">{{listing_title}}</a> wasn't approved. {{reject_reason}}</p><p><a href=\"{{dashboard_url}}\">Edit your listing</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'listing_title', 'property_id', 'property_url', 'reject_reason', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'review_submitted' => [
                'name'      => __( 'Review Submitted (Admin)', 'ovr-core' ),
                'subject'   => __( 'A new review awaits moderation', 'ovr-core' ),
                'html'      => "<h2>New review submitted</h2><p>A new review for <strong>{{review_property}}</strong> is awaiting moderation.</p><p><a href=\"{{dashboard_url}}\">Moderate reviews</a></p>{$sig}",
                'recipient' => 'admin',
                'vars'      => [ 'review_property', 'guest_name', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'review_approved' => [
                'name'      => __( 'Review Approved', 'ovr-core' ),
                'subject'   => __( 'Your review has been published', 'ovr-core' ),
                'html'      => "<h2>Your review is live</h2><p>Thank you — your review of <strong>{{review_property}}</strong> has been approved and is now public.</p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'guest_name', 'review_property', 'site_name', 'site_url' ],
            ],
            'support_ticket_update' => [
                'name'      => __( 'Support Ticket Update', 'ovr-core' ),
                'subject'   => __( 'Update on your support ticket #{{ticket_id}}', 'ovr-core' ),
                'html'      => "<h2>Support ticket update</h2><p>There's an update on your ticket <strong>#{{ticket_id}}</strong> — {{ticket_subject}}.</p><p><a href=\"{{dashboard_url}}\">View your ticket</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'ticket_id', 'ticket_subject', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'review_request' => [
                'name'      => __( 'Review Request', 'ovr-core' ),
                'subject'   => __( 'How was your stay at {{property_title}}?', 'ovr-core' ),
                'html'      => "<h2>We'd love your review</h2><p>Hi {{guest_name}}, thank you for staying at <strong>{{property_title}}</strong>. We'd love to hear about your experience — it only takes a minute.</p><p><a href=\"{{review_url}}\" style=\"display:inline-block;background:#004c4c;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none\">Write a review</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'guest_name', 'property_title', 'review_url', 'site_name', 'site_url' ],
            ],
            'payment_successful' => [
                'name'      => __( 'Payment Successful', 'ovr-core' ),
                'subject'   => __( 'Payment received — thank you', 'ovr-core' ),
                'html'      => "<h2>Payment received</h2><p>Hi {{user_name}}, we've received your payment of <strong>{{payment_amount}}</strong> via {{payment_method}}.</p><p>Transaction reference: {{payment_id}}.</p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'payment_amount', 'payment_method', 'payment_id', 'site_name', 'site_url' ],
            ],
            'payment_failed' => [
                'name'      => __( 'Payment Failed', 'ovr-core' ),
                'subject'   => __( 'We couldn\'t process your payment', 'ovr-core' ),
                'html'      => "<h2>Payment failed</h2><p>Hi {{user_name}}, unfortunately your recent payment of {{payment_amount}} via {{payment_method}} could not be completed.</p><p>No charge was made. Please try again from your dashboard.</p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'user_name', 'payment_amount', 'payment_method', 'site_name', 'site_url' ],
            ],
            'listing_submitted' => [
                'name'      => __( 'Listing Submitted (Admin)', 'ovr-core' ),
                'subject'   => __( 'New listing awaiting approval: {{listing_title}}', 'ovr-core' ),
                'html'      => "<h2>New listing submitted</h2><p><strong>{{owner_name}}</strong> submitted a new listing: <a href=\"{{property_url}}\">{{listing_title}}</a> (ID {{property_id}}).</p><p><a href=\"{{dashboard_url}}\">Review in the admin</a></p>{$sig}",
                'recipient' => 'admin',
                'vars'      => [ 'owner_name', 'listing_title', 'property_id', 'property_url', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'listing_approved' => [
                'name'      => __( 'Listing Approved', 'ovr-core' ),
                'subject'   => __( 'Your listing "{{listing_title}}" is live', 'ovr-core' ),
                'html'      => "<h2>Your listing is live</h2><p>Good news, {{owner_name}} — your listing <a href=\"{{property_url}}\">{{listing_title}}</a> has been approved and is now visible to renters.</p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'owner_name', 'listing_title', 'property_id', 'property_url', 'site_name', 'site_url' ],
            ],
            'listing_rejected' => [
                'name'      => __( 'Listing Rejected', 'ovr-core' ),
                'subject'   => __( 'Your listing "{{listing_title}}" needs changes', 'ovr-core' ),
                'html'      => "<h2>Your listing needs changes</h2><p>Hi {{owner_name}}, your listing <a href=\"{{property_url}}\">{{listing_title}}</a> wasn't approved. {{reject_reason}}</p><p><a href=\"{{dashboard_url}}\">Edit your listing</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'owner_name', 'listing_title', 'property_id', 'property_url', 'reject_reason', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'listing_deleted' => [
                'name'      => __( 'Listing Deleted', 'ovr-core' ),
                'subject'   => __( 'Your listing "{{listing_title}}" was removed', 'ovr-core' ),
                'html'      => "<h2>Your listing was removed</h2><p>Hi {{owner_name}}, your listing <strong>{{listing_title}}</strong> (ID {{property_id}}) has been deleted. If this was a mistake, contact support.</p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'owner_name', 'listing_title', 'property_id', 'site_name', 'site_url' ],
            ],
            'support_ticket_created' => [
                'name'      => __( 'Support Ticket Created (Admin)', 'ovr-core' ),
                'subject'   => __( 'New support ticket #{{ticket_id}}: {{ticket_subject}}', 'ovr-core' ),
                'html'      => "<h2>New support ticket</h2><p><strong>#{{ticket_id}}</strong> — {{ticket_subject}}</p><p>From: {{user_name}} ({{user_email}})</p><p>{{ticket_message}}</p><p><a href=\"{{dashboard_url}}\">Open in the admin</a></p>{$sig}",
                'recipient' => 'admin',
                'vars'      => [ 'ticket_id', 'ticket_subject', 'user_name', 'user_email', 'ticket_message', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'support_ticket_reply' => [
                'name'      => __( 'Support Ticket Reply', 'ovr-core' ),
                'subject'   => __( 'Reply on your support ticket #{{ticket_id}}', 'ovr-core' ),
                'html'      => "<h2>New reply on ticket #{{ticket_id}}</h2><p>Our team replied to <strong>{{ticket_subject}}</strong>:</p><blockquote style=\"border-left:3px solid #ddd;padding-left:12px;color:#444\">{{ticket_message}}</blockquote><p><a href=\"{{dashboard_url}}\">View your ticket</a></p>{$sig}",
                'recipient' => 'user',
                'vars'      => [ 'ticket_id', 'ticket_subject', 'ticket_message', 'dashboard_url', 'site_name', 'site_url' ],
            ],
            'contact_form' => [
                'name'      => __( 'Contact Form (Admin)', 'ovr-core' ),
                'subject'   => __( 'New contact form message from {{sender_name}}', 'ovr-core' ),
                'html'      => "<h2>New contact message</h2><p>From <strong>{{sender_name}}</strong> ({{sender_email}}):</p><blockquote style=\"border-left:3px solid #ddd;padding-left:12px;color:#444\">{{contact_message}}</blockquote>{$sig}",
                'recipient' => 'admin',
                'vars'      => [ 'sender_name', 'sender_email', 'contact_message', 'site_name', 'site_url' ],
            ],
        ];
    }

    /**
     * Insert any catalogue templates not yet present (idempotent; safe to run on
     * every upgrade so newly-added templates appear without clobbering edits).
     */
    public static function maybe_seed(): void {
        global $wpdb;
        $table    = self::table();
        $existing = (array) $wpdb->get_col( "SELECT template_key FROM {$table}" );
        foreach ( self::defaults() as $key => $def ) {
            if ( in_array( $key, $existing, true ) ) {
                continue;
            }
            $wpdb->insert( $table, [
                'template_key' => $key,
                'name'         => $def['name'],
                'subject'      => $def['subject'],
                'body_html'    => $def['html'],
                'body_text'    => '',
                'recipient'    => $def['recipient'],
                'custom_email' => '',
                'is_enabled'   => 1,
                'created_at'   => current_time( 'mysql' ),
                'updated_at'   => current_time( 'mysql' ),
            ] );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get( string $key ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE template_key = %s', $key ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array {
        global $wpdb;
        return (array) $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY name ASC', ARRAY_A );
    }

    /**
     * The variables available to a template (for the editor reference).
     *
     * @return string[]
     */
    public static function variables_for( string $key ): array {
        $defs = self::defaults();
        return $defs[ $key ]['vars'] ?? [ 'site_name', 'site_url' ];
    }

    /**
     * Persist an admin edit.
     *
     * @param array<string, mixed> $data
     */
    public static function save( string $key, array $data ): bool {
        global $wpdb;
        $recipient = in_array( $data['recipient'] ?? '', self::RECIPIENTS, true ) ? $data['recipient'] : 'user';
        $updated = $wpdb->update(
            self::table(),
            [
                'subject'      => substr( (string) ( $data['subject'] ?? '' ), 0, 255 ),
                'body_html'    => (string) ( $data['body_html'] ?? '' ),
                'body_text'    => (string) ( $data['body_text'] ?? '' ),
                'recipient'    => $recipient,
                'custom_email' => substr( (string) ( $data['custom_email'] ?? '' ), 0, 200 ),
                'is_enabled'   => empty( $data['is_enabled'] ) ? 0 : 1,
                'updated_by'   => get_current_user_id() ?: null,
                'updated_at'   => current_time( 'mysql' ),
            ],
            [ 'template_key' => $key ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ],
            [ '%s' ]
        );

        if ( false !== $updated && class_exists( '\OVR\Core\AuditLog' ) ) {
            \OVR\Core\AuditLog::record( 'email_template.changed', 'email_template', null, [ 'key' => $key ] );
        }
        return false !== $updated;
    }
}
