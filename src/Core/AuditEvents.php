<?php
/**
 * Audit event listeners (Milestone 3 Feature 2).
 *
 * Wires platform-wide events into AuditLog so the change history covers the
 * account, listing, payment, subscription, settings and authentication actions
 * the spec requires — on top of the per-module audit calls that already exist
 * (bookings, reviews, paid services, support, etc.).
 *
 * Booted unconditionally (logins/registrations happen on the front end too).
 *
 * @package OVR\Core
 * @since   2.2.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuditEvents {

    public function init(): void {
        // Accounts.
        add_action( 'user_register', [ $this, 'user_created' ], 10, 1 );
        add_action( 'profile_update', [ $this, 'user_updated' ], 10, 2 );
        add_action( 'delete_user', [ $this, 'user_deleted' ], 10, 1 );

        // Listings (ovr_property).
        add_action( 'save_post_ovr_property', [ $this, 'listing_saved' ], 20, 3 );
        add_action( 'wp_trash_post', [ $this, 'listing_trashed' ], 10, 1 );

        // Authentication.
        add_action( 'wp_login', [ $this, 'on_login' ], 10, 2 );
        add_action( 'wp_login_failed', [ $this, 'on_login_failed' ], 10, 1 );

        // Settings.
        add_action( 'update_option_ovr_settings', [ $this, 'settings_modified' ], 10, 2 );

        // Payments + subscription lifecycle.
        add_action( 'ovr_payment_completed', [ $this, 'payment_received' ], 10, 2 );
        add_action( 'ovr_subscription_expired', [ $this, 'subscription_expired' ], 10, 2 );
        add_action( 'ovr_subscription_renewed', [ $this, 'subscription_renewed' ], 10, 3 );
    }

    public function user_created( $user_id ): void {
        $u = get_userdata( (int) $user_id );
        AuditLog::record( 'user.created', 'user', (int) $user_id, [ 'email' => $u ? $u->user_email : '' ], (int) $user_id );
    }

    /**
     * @param int      $user_id
     * @param \WP_User $old
     */
    public function user_updated( $user_id, $old ): void {
        $new = get_userdata( (int) $user_id );
        if ( ! $new || ! $old ) {
            AuditLog::record( 'user.updated', 'user', (int) $user_id, [], (int) $user_id );
            return;
        }
        // Capture the fields most worth tracking.
        $before = [ 'email' => $old->user_email, 'role' => implode( ',', (array) $old->roles ), 'display_name' => $old->display_name ];
        $after  = [ 'email' => $new->user_email, 'role' => implode( ',', (array) $new->roles ), 'display_name' => $new->display_name ];
        if ( $before === $after ) {
            return; // nothing meaningful changed.
        }
        AuditLog::record( 'user.updated', 'user', (int) $user_id, [], (int) $user_id, [ 'old' => $before, 'new' => $after ] );
    }

    public function user_deleted( $user_id ): void {
        $u = get_userdata( (int) $user_id );
        AuditLog::record( 'user.deleted', 'user', (int) $user_id, [ 'email' => $u ? $u->user_email : '' ], (int) $user_id );
    }

    /**
     * @param int      $post_id
     * @param \WP_Post $post
     * @param bool     $update
     */
    public function listing_saved( $post_id, $post, $update ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( 'auto-draft' === ( $post->post_status ?? '' ) ) {
            return;
        }
        $action = $update ? 'listing.updated' : 'listing.created';
        AuditLog::record( $action, 'listing', (int) $post_id, [ 'title' => get_the_title( $post_id ), 'status' => $post->post_status ] );
    }

    public function listing_trashed( $post_id ): void {
        if ( 'ovr_property' !== get_post_type( $post_id ) ) {
            return;
        }
        AuditLog::record( 'listing.deleted', 'listing', (int) $post_id, [ 'title' => get_the_title( $post_id ) ] );
    }

    /**
     * @param string   $user_login
     * @param \WP_User $user
     */
    public function on_login( $user_login, $user = null ): void {
        $is_admin = $user instanceof \WP_User && user_can( $user, 'manage_options' );
        AuditLog::record(
            $is_admin ? 'admin.login' : 'user.login',
            'user',
            $user instanceof \WP_User ? (int) $user->ID : null,
            [ 'login' => (string) $user_login ],
            $user instanceof \WP_User ? (int) $user->ID : null
        );
    }

    public function on_login_failed( $username ): void {
        AuditLog::record( 'login.failed', 'user', null, [ 'login' => (string) $username ] );
    }

    /**
     * @param array $old
     * @param array $new
     */
    public function settings_modified( $old, $new ): void {
        // Only record the keys that actually changed (settings is a big array).
        $old     = (array) $old;
        $new     = (array) $new;
        $changed = [];
        foreach ( $new as $k => $v ) {
            $ov = $old[ $k ] ?? null;
            if ( $ov !== $v ) {
                $changed[ $k ] = [ 'old' => is_scalar( $ov ) ? $ov : '…', 'new' => is_scalar( $v ) ? $v : '…' ];
            }
        }
        if ( empty( $changed ) ) {
            return;
        }
        AuditLog::record( 'settings.modified', 'settings', null, [ 'keys' => array_keys( $changed ) ], null, [ 'new' => $changed ] );
    }

    /**
     * @param int   $user_id
     * @param array $meta
     */
    public function payment_received( $user_id, $meta = [] ): void {
        $meta = (array) $meta;
        AuditLog::record(
            'payment.received',
            'payment',
            null,
            [
                'amount'  => $meta['amount'] ?? null,
                'plan'    => $meta['plan_slug'] ?? ( $meta['upgrade'] ?? null ),
                'gateway' => $meta['gateway'] ?? null,
                'txn'     => $meta['transaction_id'] ?? null,
            ],
            (int) $user_id
        );
    }

    public function subscription_expired( $user_id, $previous_plan = '' ): void {
        AuditLog::record( 'subscription.changed', 'subscription', null, [ 'event' => 'expired', 'plan' => (string) $previous_plan ], (int) $user_id );
    }

    public function subscription_renewed( $user_id, $plan_slug = '', $count = 0 ): void {
        AuditLog::record( 'subscription.changed', 'subscription', null, [ 'event' => 'renewed', 'plan' => (string) $plan_slug ], (int) $user_id );
    }
}
