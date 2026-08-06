<?php
/**
 * Notifications.
 *
 * Listens for plugin events and dispatches transactional emails through the
 * central Mailer (which renders admin-editable templates from EmailTemplates).
 *
 * Triggered events and the templates they send:
 *
 *   ovr_user_registered        → registration_welcome
 *   ovr_inquiry_submitted      → inquiry_landlord + inquiry_guest
 *   ovr_payment_completed      → subscription_purchase (subscription)
 *                                | payment_successful (upgrade / topup / booking)
 *   ovr_payment_failed         → payment_failed
 *   ovr_subscription_activated → subscription_purchase
 *   ovr_subscription_renewed   → subscription_renewal
 *   ovr_subscription_expired   → subscription_expiry
 *   ovr_listing_saved          → listing_submitted (new pending submission)
 *   ovr_property_saved         → listing_approved | listing_rejected (admin approval)
 *   ovr_listing_deleted        → listing_deleted
 *   ovr_review_submitted       → review_submitted (admin, pending)
 *   ovr_review_status_changed  → review_approved (when approved)
 *   ovr_inquiry_replied        → support_ticket_reply-style reply notice (user)
 *   ovr_support_ticket_created → support_ticket_created (admin)
 *   ovr_support_ticket_reply   → support_ticket_reply (user)
 *   retrieve_password          → password_reset
 *
 * @package OVR\Notifications
 * @since   1.0.0
 */

namespace OVR\Notifications;

use OVR\Core\Pages;
use OVR\Email\Mailer;
use OVR\Subscription\Plans;
use OVR\Subscription\UserSubscription;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Notifications {

    public function init(): void {
        add_action( 'ovr_user_registered',         [ $this, 'on_user_registered' ], 10, 2 );
        add_action( 'ovr_inquiry_submitted',       [ $this, 'on_inquiry_submitted' ], 10, 2 );

        add_action( 'ovr_payment_completed',       [ $this, 'on_payment_completed' ], 10, 2 );
        add_action( 'ovr_payment_failed',          [ $this, 'on_payment_failed' ], 10, 2 );

        add_action( 'ovr_subscription_activated',  [ $this, 'on_subscription_activated' ], 10, 2 );
        add_action( 'ovr_subscription_renewed',    [ $this, 'on_subscription_renewed' ], 10, 2 );
        add_action( 'ovr_subscription_expired',    [ $this, 'on_subscription_expired' ], 10, 2 );

        add_action( 'ovr_listing_saved',           [ $this, 'on_listing_saved' ], 10, 3 );
        add_action( 'ovr_property_saved',          [ $this, 'on_property_saved' ], 10, 2 );
        add_action( 'ovr_listing_deleted',         [ $this, 'on_listing_deleted' ], 10, 2 );

        add_action( 'ovr_review_submitted',        [ $this, 'on_review_submitted' ], 10, 3 );
        add_action( 'ovr_review_status_changed',   [ $this, 'on_review_status_changed' ], 10, 4 );

        add_action( 'ovr_inquiry_replied',         [ $this, 'on_inquiry_replied' ], 10, 2 );

        add_action( 'ovr_support_ticket_created',  [ $this, 'on_support_ticket_created' ], 10, 2 );
        add_action( 'ovr_support_ticket_reply',    [ $this, 'on_support_ticket_reply' ], 10, 2 );

        add_action( 'retrieve_password',           [ $this, 'on_retrieve_password' ], 10, 3 );

        // Capture the title on trash so the deletion notice still has it.
        add_action( 'wp_trash_post',               [ $this, 'capture_trash_title' ], 10, 1 );
    }

    /**
     * Welcome email after a successful registration.
     */
    public function on_user_registered( int $user_id, bool $is_landlord = false ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        Mailer::send( 'registration_welcome', [
            'user_name'     => $user->display_name,
            'user_email'    => $user->user_email,
            'login_url'     => Pages::get_page_url( 'ovr_page_login' ),
            'dashboard_url' => Pages::get_page_url( 'ovr_page_dashboard' ),
        ], [ 'user_id' => $user_id ] );
    }

    /**
     * Two emails on inquiry submit: landlord notification + guest confirmation.
     */
    public function on_inquiry_submitted( int $inquiry_id, int $property_id ): void {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_inquiries WHERE id = %d", $inquiry_id ),
            ARRAY_A
        );
        if ( ! $row ) return;

        $property = get_post( $property_id );
        if ( ! $property ) return;

        $landlord     = get_userdata( (int) $row['landlord_id'] );
        $property_url = get_permalink( $property_id );

        if ( $landlord && $landlord->user_email ) {
            Mailer::send( 'inquiry_landlord', [
                'guest_name'      => (string) $row['guest_name'],
                'listing_title'   => $property->post_title,
                'property_id'     => (int) $property_id,
                'property_url'    => $property_url,
                'inquiry_message' => (string) ( $row['message'] ?? '' ),
                'dashboard_url'   => Pages::get_page_url( 'ovr_page_dashboard' ),
            ], [ 'user_email' => $landlord->user_email ] );
        }

        if ( ! empty( $row['guest_email'] ) ) {
            Mailer::send( 'inquiry_guest', [
                'guest_name'    => (string) $row['guest_name'],
                'listing_title' => $property->post_title,
                'property_url'  => $property_url,
            ], [ 'user_email' => (string) $row['guest_email'] ] );
        }
    }

    /**
     * Payment completed: subscription purchases reuse subscription_purchase,
     * everything else (listing upgrade / top-up / booking) uses payment_successful.
     */
    public function on_payment_completed( int $user_id, array $data = [] ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $payment_id = (int) ( $data['payment_id'] ?? 0 );
        $type       = 'unknown';
        $amount     = (float) ( $data['amount'] ?? 0 );
        $gateway    = (string) ( $data['gateway'] ?? '' );

        if ( $payment_id > 0 ) {
            $row = $this->payment_row( $payment_id );
            if ( $row ) {
                $type   = (string) ( $row['payment_type'] ?? $type );
                $amount = (float) ( $row['amount'] ?? $amount );
                if ( '' === $gateway ) {
                    $gateway = (string) ( $row['gateway'] ?? '' );
                }
            }
        }

        $vars = [
            'user_name'      => $user->display_name,
            'payment_amount' => wc_price_format( $amount ),
            'payment_method' => $this->gateway_label( $gateway ),
            'payment_id'     => $payment_id,
        ];

        if ( 'subscription' === $type ) {
            $plan_slug = (string) ( $data['plan_slug'] ?? '' );
            $plan      = $plan_slug ? Plans::get_plan( $plan_slug ) : null;
            $vars['membership_name'] = $plan['name'] ?? ucfirst( str_replace( '_', ' ', $plan_slug ) );
            Mailer::send( 'subscription_purchase', $vars, [ 'user_id' => $user_id ] );
            return;
        }

        Mailer::send( 'payment_successful', $vars, [ 'user_id' => $user_id ] );
    }

    /**
     * Payment failed notice to the user.
     */
    public function on_payment_failed( int $user_id, array $data = [] ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $payment_id = (int) ( $data['payment_id'] ?? 0 );
        $amount     = (float) ( $data['amount'] ?? 0 );
        $gateway    = (string) ( $data['gateway'] ?? '' );
        if ( $payment_id > 0 ) {
            $row = $this->payment_row( $payment_id );
            if ( $row ) {
                $amount = (float) ( $row['amount'] ?? $amount );
                if ( '' === $gateway ) {
                    $gateway = (string) ( $row['gateway'] ?? '' );
                }
            }
        }

        Mailer::send( 'payment_failed', [
            'user_name'      => $user->display_name,
            'payment_amount' => wc_price_format( $amount ),
            'payment_method' => $this->gateway_label( $gateway ),
        ], [ 'user_id' => $user_id ] );
    }

    /**
     * Subscription activated (new purchase) — same confirmation as a purchase.
     */
    public function on_subscription_activated( int $user_id, string $plan_slug = '' ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $plan = $plan_slug ? Plans::get_plan( $plan_slug ) : null;
        Mailer::send( 'subscription_purchase', [
            'user_name'       => $user->display_name,
            'membership_name' => $plan['name'] ?? ucfirst( str_replace( '_', ' ', $plan_slug ) ),
            'payment_amount'  => wc_price_format( (float) ( $plan['price'] ?? 0 ) ),
            'expiration_date' => $this->expiry_date( $user_id ),
            'dashboard_url'   => Pages::get_page_url( 'ovr_page_dashboard' ),
        ], [ 'user_id' => $user_id ] );
    }

    public function on_subscription_renewed( int $user_id, string $plan_slug = '' ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $plan = $plan_slug ? Plans::get_plan( $plan_slug ) : null;
        Mailer::send( 'subscription_renewal', [
            'user_name'       => $user->display_name,
            'membership_name' => $plan['name'] ?? ucfirst( str_replace( '_', ' ', $plan_slug ) ),
            'expiration_date' => $this->expiry_date( $user_id ),
            'payment_amount'  => wc_price_format( (float) ( $plan['price'] ?? 0 ) ),
        ], [ 'user_id' => $user_id ] );
    }

    public function on_subscription_expired( int $user_id, string $plan_slug = '' ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $plan = $plan_slug ? Plans::get_plan( $plan_slug ) : null;
        Mailer::send( 'subscription_expiry', [
            'user_name'       => $user->display_name,
            'membership_name' => $plan['name'] ?? ucfirst( str_replace( '_', ' ', $plan_slug ) ),
            'expiration_date' => $this->expiry_date( $user_id ),
            'dashboard_url'   => Pages::get_page_url( 'ovr_page_dashboard' ),
        ], [ 'user_id' => $user_id ] );
    }

    /**
     * New listing submitted (landlord front-end save, pending status).
     */
    public function on_listing_saved( int $post_id, int $user_id, bool $editing = false ): void {
        $post = get_post( $post_id );
        if ( ! $post || 'ovr_property' !== $post->post_type ) return;
        // Only brand-new pending submissions — edits/repubslishes don't notify.
        if ( $editing || 'pending' !== $post->post_status ) return;

        $owner = get_userdata( $user_id );
        Mailer::send( 'listing_submitted', [
            'owner_name'   => $owner ? $owner->display_name : '',
            'listing_title' => $post->post_title,
            'property_id'  => $post_id,
            'property_url' => get_edit_post_link( $post_id, 'raw' ) ?: admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
            'dashboard_url' => Pages::get_page_url( 'ovr_page_dashboard' ),
        ], [ 'user_id' => $user_id ] );
    }

    /**
     * Admin approval / rejection via the property meta-box save.
     */
    public function on_property_saved( int $post_id, $post ): void {
        if ( ! $post instanceof \WP_Post || 'ovr_property' !== $post->post_type ) return;

        $new_status = $post->post_status;
        $prev_status = get_post_meta( $post_id, '_ovr_pre_save_status', true );

        $owner = get_userdata( (int) ( $post->post_author ?? 0 ) );
        if ( ! $owner ) return;

        if ( 'publish' === $new_status && 'publish' !== $prev_status ) {
            Mailer::send( 'listing_approved', [
                'owner_name'   => $owner->display_name,
                'listing_title' => $post->post_title,
                'property_id'  => $post_id,
                'property_url' => get_permalink( $post_id ),
            ], [ 'user_id' => $owner->ID ] );
        } elseif ( 'rejected' === $new_status ) {
            Mailer::send( 'listing_rejected', [
                'owner_name'   => $owner->display_name,
                'listing_title' => $post->post_title,
                'property_id'  => $post_id,
                'property_url' => get_permalink( $post_id ),
                'reject_reason' => (string) get_post_meta( $post_id, '_ovr_reject_reason', true ),
                'dashboard_url' => Pages::get_page_url( 'ovr_page_dashboard' ),
            ], [ 'user_id' => $owner->ID ] );
        }
    }

    /**
     * Listing deleted/trashed by its owner.
     */
    public function on_listing_deleted( int $post_id, int $user_id ): void {
        $owner = get_userdata( $user_id );
        // Title is gone after trash, so keep a cached copy.
        $title = (string) get_post_meta( $post_id, '_ovr_cached_title', true );
        if ( '' === $title ) {
            $title = (string) get_post_meta( $post_id, '_ovr_title_snapshot', true );
        }
        Mailer::send( 'listing_deleted', [
            'owner_name'   => $owner ? $owner->display_name : '',
            'listing_title' => $title,
            'property_id'  => $post_id,
        ], [ 'user_id' => $user_id ] );
    }

    /**
     * Review submitted: notify admin (moderation queue) when it lands pending.
     */
    public function on_review_submitted( int $review_id, int $property_id, string $status = '' ): void {
        $property = get_post( $property_id );
        if ( ! $property ) return;

        if ( 'pending' !== $status ) return;

        $review = $this->review_row( $review_id );
        Mailer::send( 'review_submitted', [
            'review_property' => $property->post_title,
            'guest_name'      => $review['guest_name'] ?? '',
            'dashboard_url'   => Pages::get_page_url( 'ovr_page_dashboard' ),
        ] );
    }

    /**
     * Review approved: let the author know it's public.
     */
    public function on_review_status_changed( int $review_id, string $new_status, string $old_status = '', int $property_id = 0 ): void {
        if ( 'approved' !== $new_status || 'approved' === $old_status ) return;

        $property = get_post( $property_id );
        $review   = $this->review_row( $review_id );
        $email    = (string) ( $review['guest_email'] ?? '' );
        $name     = (string) ( $review['guest_name'] ?? '' );

        if ( ! $email && $property ) {
            $author = get_userdata( (int) $property->post_author );
            $email  = $author ? $author->user_email : '';
        }

        Mailer::send( 'review_approved', [
            'guest_name'     => $name,
            'review_property' => $property ? $property->post_title : '',
        ], [ 'user_email' => $email ] );
    }

    /**
     * Inquiry replied (converted to ticket-style reply notice to the guest).
     */
    public function on_inquiry_replied( int $inquiry_id, string $message = '' ): void {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_inquiries WHERE id = %d", $inquiry_id ),
            ARRAY_A
        );
        if ( ! $row || empty( $row['guest_email'] ) ) return;

        $property = get_post( (int) ( $row['property_id'] ?? 0 ) );
        Mailer::send( 'support_ticket_reply', [
            'ticket_id'      => $inquiry_id,
            'ticket_subject' => $property ? $property->post_title : '',
            'ticket_message' => $message,
            'dashboard_url'  => Pages::get_page_url( 'ovr_page_dashboard' ),
        ], [ 'user_email' => (string) $row['guest_email'] ] );
    }

    /**
     * Support ticket created in admin → notify the site owner.
     */
    public function on_support_ticket_created( int $ticket_id, array $data = [] ): void {
        Mailer::send( 'support_ticket_created', [
            'ticket_id'      => $ticket_id,
            'ticket_subject' => (string) ( $data['subject'] ?? '' ),
            'user_name'      => (string) ( $data['user_name'] ?? '' ),
            'user_email'     => (string) ( $data['user_email'] ?? '' ),
            'ticket_message' => (string) ( $data['message'] ?? '' ),
            'dashboard_url'  => Pages::get_page_url( 'ovr_page_dashboard' ),
        ] );
    }

    /**
     * Support ticket reply (staff) → notify the ticket creator.
     */
    public function on_support_ticket_reply( int $ticket_id, string $message = '' ): void {
        global $wpdb;
        $ticket = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_support_tickets WHERE id = %d", $ticket_id ),
            ARRAY_A
        );
        if ( ! $ticket ) return;

        $user = get_userdata( (int) $ticket['user_id'] );
        if ( ! $user || ! $user->user_email ) return;

        Mailer::send( 'support_ticket_reply', [
            'ticket_id'      => $ticket_id,
            'ticket_subject' => (string) ( $ticket['subject'] ?? '' ),
            'ticket_message' => $message,
            'dashboard_url'  => Pages::get_page_url( 'ovr_page_dashboard' ),
        ], [ 'user_email' => $user->user_email ] );
    }

    /**
     * Password reset key generated → send the (already templated) reset email.
     */
    public function on_retrieve_password( string $user_login, string $key, string $user_email ): void {
        $user = get_user_by( 'email', $user_email );
        if ( ! $user ) {
            $user = get_user_by( 'login', $user_login );
        }
        if ( ! $user ) return;

        $reset_url = add_query_arg(
            [ 'action' => 'rp', 'key' => $key, 'login' => rawurlencode( $user->user_login ) ],
            Pages::get_page_url( 'ovr_page_login' )
        );

        Mailer::send( 'password_reset', [
            'user_name' => $user->display_name,
            'reset_url' => $reset_url,
        ], [ 'user_email' => $user_email ] );
    }

    // ---------------------------------------------------------------------

    private function payment_row( int $payment_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    private function gateway_label( string $gateway ): string {
        $labels = [
            'stripe'        => 'Stripe',
            'paypal'        => 'PayPal',
            'authorize_net' => 'Authorize.Net',
            'wallet'        => 'Wallet',
            'free'          => 'Free',
        ];
        return $labels[ $gateway ] ?? ( $gateway ? ucfirst( str_replace( '_', ' ', $gateway ) ) : 'the site' );
    }

    private function expiry_date( int $user_id ): string {
        $info    = UserSubscription::get_info( $user_id );
        $expires = (string) ( $info['expiry_date'] ?? '' );
        return $expires ? date_i18n( get_option( 'date_format' ), strtotime( $expires ) ) : '';
    }

    /**
     * Snapshot a listing's title just before it is trashed, so the deletion
     * notification can include it (the post itself is already gone by then).
     */
    public function capture_trash_title( int $post_id ): void {
        if ( 'ovr_property' !== get_post_type( $post_id ) ) return;
        if ( '' === (string) get_post_meta( $post_id, '_ovr_title_snapshot', true ) ) {
            update_post_meta( $post_id, '_ovr_title_snapshot', get_the_title( $post_id ) );
        }
    }

    private function review_row( int $review_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_reviews WHERE id = %d", $review_id ),
            ARRAY_A
        );
        return $row ?: null;
    }
}

if ( ! function_exists( 'wc_price_format' ) ) {
    /**
     * Lightweight money formatter (avoids hard dependency on WooCommerce).
     */
    function wc_price_format( float $amount ): string {
        return number_format_i18n( $amount, 2 );
    }
}
