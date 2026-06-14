<?php
/**
 * User Subscription Management.
 *
 * @package OVR\Subscription
 * @since   1.0.0
 */

namespace OVR\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserSubscription {

    public function init(): void {
        add_action( 'init', [ $this, 'maybe_grandfather_landlords' ] );
    }

    /**
     * One-time backfill: treat every EXISTING landlord as an active paid
     * subscriber through 2026-12-31 so the new subscription gate doesn't lock
     * out long-standing subscribers on go-live. Runs once, then sets a flag.
     */
    public function maybe_grandfather_landlords(): void {
        if ( get_option( 'ovr_grandfather_2026' ) ) {
            return;
        }

        $through      = '2026-12-31';
        $default_paid = 'standard_homeowner_5';

        // Every current landlord — by role and by the legacy meta flag.
        $by_role = get_users( [ 'role' => 'ovr_landlord', 'fields' => 'ID' ] );
        $by_meta = get_users( [ 'meta_key' => 'ovr_is_landlord', 'meta_value' => '1', 'fields' => 'ID' ] );
        $ids     = array_unique( array_map( 'intval', array_merge( (array) $by_role, (array) $by_meta ) ) );

        foreach ( $ids as $uid ) {
            // Ensure a paid plan so has_listing_access() passes.
            if ( ! self::is_paid_plan( (string) get_user_meta( $uid, 'ovr_subscription_plan', true ) ) ) {
                update_user_meta( $uid, 'ovr_subscription_plan', $default_paid );
            }
            // Ensure the expiry runs at least through the grandfather date.
            $exp = (string) get_user_meta( $uid, 'ovr_subscription_expires', true );
            if ( ! $exp || strtotime( $exp ) < strtotime( $through ) ) {
                update_user_meta( $uid, 'ovr_subscription_expires', $through );
            }
            update_user_meta( $uid, 'ovr_editing_enabled', true );
        }

        update_option( 'ovr_grandfather_2026', 1 );
    }

    /**
     * Get a user's current subscription plan slug.
     */
    public static function get_plan_slug( int $user_id = 0 ): string {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        return get_user_meta( $user_id, 'ovr_subscription_plan', true ) ?: 'base_subscriber';
    }

    /**
     * Get full plan data for a user.
     */
    public static function get_plan( int $user_id = 0 ): ?array {
        return Plans::get_plan( self::get_plan_slug( $user_id ) );
    }

    /**
     * Whether a plan slug represents a paid subscription (price > 0).
     */
    public static function is_paid_plan( string $slug ): bool {
        $plan = Plans::get_plan( $slug );
        return $plan && (float) ( $plan['price'] ?? 0 ) > 0;
    }

    /**
     * Whether the user may publish listings at all.
     *
     * Per client requirement: a landlord with no active, paid subscription
     * cannot list a property. The free "base_subscriber" default and any
     * expired plan therefore grant no listing access.
     */
    public static function has_listing_access( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        return self::is_active( $user_id ) && self::is_paid_plan( self::get_plan_slug( $user_id ) );
    }

    /**
     * Why a user is blocked from adding a listing: 'subscription' (no active
     * paid plan), 'limit' (plan listing cap reached), or '' (allowed).
     */
    public static function listing_block_reason( int $user_id = 0 ): string {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! self::has_listing_access( $user_id ) ) {
            return 'subscription';
        }
        return self::can_create_listing( $user_id ) ? '' : 'limit';
    }

    /**
     * Check if user can create more listings.
     */
    public static function can_create_listing( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        // No active paid subscription → no listings at all.
        if ( ! self::has_listing_access( $user_id ) ) {
            return false;
        }

        $plan = self::get_plan( $user_id );
        if ( ! $plan ) {
            return false;
        }

        // Unlimited listings.
        if ( -1 === $plan['max_listings'] ) {
            return true;
        }

        $current_count = self::get_listing_count( $user_id );
        return $current_count < $plan['max_listings'];
    }

    /**
     * Get count of user's active listings.
     */
    public static function get_listing_count( int $user_id ): int {
        $query = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        return $query->post_count;
    }

    /**
     * Check if user's subscription is active (not expired).
     */
    public static function is_active( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $status = get_user_meta( $user_id, 'ovr_account_status', true );
        if ( 'inactive' === $status ) {
            return false;
        }

        $plan_slug = self::get_plan_slug( $user_id );
        if ( 'base_subscriber' === $plan_slug ) {
            return true; // Free plan never expires.
        }

        // NB: meta key is ovr_subscription_expires (Lifecycle::META_EXPIRES);
        // an earlier ..._expiry typo here made paid plans read as never-expiring.
        $expiry = get_user_meta( $user_id, 'ovr_subscription_expires', true );
        if ( empty( $expiry ) ) {
            return true;
        }

        return strtotime( $expiry ) > time();
    }

    /**
     * Check if user's editing is enabled.
     */
    public static function is_editing_enabled( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        return (bool) get_user_meta( $user_id, 'ovr_editing_enabled', true );
    }
}
