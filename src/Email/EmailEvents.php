<?php
/**
 * Email trigger engine (Milestone 3 Feature 6).
 *
 * Maps platform events to admin-editable email templates via the Mailer.
 * Covers the events that previously sent no email (subscription lifecycle,
 * review moderation, listing approval). Registration + inquiry emails are sent
 * from Notifications, and password reset from PasswordResetHandler — both now
 * route through the same Mailer/template system.
 *
 * @package OVR\Email
 * @since   2.3.0
 */

namespace OVR\Email;

use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmailEvents {

    public function init(): void {
        add_action( 'ovr_payment_completed', [ $this, 'on_payment' ], 20, 2 );
        add_action( 'ovr_subscription_renewed', [ $this, 'on_renewed' ], 20, 3 );
        add_action( 'ovr_subscription_expired', [ $this, 'on_expired' ], 20, 2 );
        add_action( 'ovr_review_submitted', [ $this, 'on_review_submitted' ], 20, 3 );
        add_action( 'ovr_review_status_changed', [ $this, 'on_review_status' ], 20, 4 );

        // Listing approval/rejection = admin changing the _ovr_admin_status meta.
        add_action( 'updated_post_meta', [ $this, 'on_admin_status_meta' ], 10, 4 );
        add_action( 'added_post_meta', [ $this, 'on_admin_status_meta' ], 10, 4 );
    }

    private function dashboard(): string {
        return Pages::get_page_url( 'ovr_page_dashboard' );
    }

    /**
     * Subscription purchase confirmation (only for plan payments, not upgrades).
     *
     * @param int   $user_id
     * @param array $meta
     */
    public function on_payment( $user_id, $meta = [] ): void {
        $meta = (array) $meta;
        if ( empty( $meta['plan_slug'] ) ) {
            return; // upgrade/bump purchase — not a membership.
        }
        $u = get_userdata( (int) $user_id );
        Mailer::send( 'subscription_purchase', [
            'user_name'       => $u ? $u->display_name : '',
            'membership_name' => (string) ( $meta['plan_name'] ?? $meta['plan_slug'] ),
            'payment_amount'  => self::money( $meta['amount'] ?? null ),
            'expiration_date' => (string) ( $meta['expires'] ?? '' ),
            'dashboard_url'   => $this->dashboard(),
        ], [ 'user_id' => (int) $user_id ] );
    }

    public function on_renewed( $user_id, $plan_slug = '', $count = 0 ): void {
        $u = get_userdata( (int) $user_id );
        Mailer::send( 'subscription_renewal', [
            'user_name'       => $u ? $u->display_name : '',
            'membership_name' => (string) $plan_slug,
            'expiration_date' => '',
        ], [ 'user_id' => (int) $user_id ] );
    }

    public function on_expired( $user_id, $previous_plan = '' ): void {
        $u = get_userdata( (int) $user_id );
        Mailer::send( 'subscription_expiry', [
            'user_name'       => $u ? $u->display_name : '',
            'membership_name' => (string) $previous_plan,
            'dashboard_url'   => $this->dashboard(),
        ], [ 'user_id' => (int) $user_id ] );
    }

    /**
     * Notify admins that a review awaits moderation (only pending submissions).
     *
     * @param int    $review_id
     * @param int    $property_id
     * @param string $status
     */
    public function on_review_submitted( $review_id, $property_id, $status = 'pending' ): void {
        if ( 'pending' !== $status ) {
            return; // auto-approved → no moderation needed.
        }
        Mailer::send( 'review_submitted', [
            'review_property' => get_the_title( (int) $property_id ),
            'dashboard_url'   => admin_url( 'edit.php?post_type=ovr_property&page=ovr-core-reviews' ),
        ] );
    }

    /**
     * Email the reviewer when their review is approved.
     */
    public function on_review_status( $review_id, $status, $old = '', $property_id = 0 ): void {
        if ( 'approved' !== $status ) {
            return;
        }
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT guest_name, guest_email FROM {$wpdb->prefix}ovr_reviews WHERE id = %d", (int) $review_id ), ARRAY_A );
        if ( ! $row || empty( $row['guest_email'] ) ) {
            return;
        }
        Mailer::send( 'review_approved', [
            'guest_name'      => (string) $row['guest_name'],
            'review_property' => get_the_title( (int) $property_id ),
        ], [ 'user_email' => (string) $row['guest_email'] ] );
    }

    /**
     * Fire listing approved/rejected when an admin flips _ovr_admin_status.
     *
     * @param int    $meta_id
     * @param int    $post_id
     * @param string $meta_key
     * @param mixed  $meta_value
     */
    public function on_admin_status_meta( $meta_id, $post_id, $meta_key, $meta_value ): void {
        if ( '_ovr_admin_status' !== $meta_key || 'ovr_property' !== get_post_type( $post_id ) ) {
            return;
        }
        $owner = (int) get_post_field( 'post_author', $post_id );
        if ( ! $owner ) {
            return;
        }
        $vars = [
            'user_name'     => get_the_author_meta( 'display_name', $owner ),
            'listing_title' => get_the_title( $post_id ),
            'property_id'   => (int) $post_id,
            'property_url'  => get_permalink( $post_id ),
            'dashboard_url' => $this->dashboard(),
        ];
        if ( 'approved' === $meta_value ) {
            Mailer::send( 'listing_approved', $vars, [ 'user_id' => $owner ] );
        } elseif ( in_array( $meta_value, [ 'hidden', 'suspended' ], true ) ) {
            $vars['reject_reason'] = __( 'Please review your listing details and resubmit.', 'ovr-core' );
            Mailer::send( 'listing_rejected', $vars, [ 'user_id' => $owner ] );
        }
    }

    private static function money( $amount ): string {
        if ( null === $amount || '' === $amount ) {
            return '';
        }
        $s = (array) get_option( 'ovr_settings', [] );
        $sym = $s['currency_symbol'] ?? '$';
        return $sym . number_format( (float) $amount, 2 );
    }
}
