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

    public function init(): void {}

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
     * Check if user can create more listings.
     */
    public static function can_create_listing( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
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

        $expiry = get_user_meta( $user_id, 'ovr_subscription_expiry', true );
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
