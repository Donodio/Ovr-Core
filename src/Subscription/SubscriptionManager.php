<?php
/**
 * Subscription Manager — centralized orchestration for status checks,
 * renewal/upgrade logic, and subscription lifecycle actions.
 *
 * @package OVR\Subscription
 * @since   2.0.0
 */

namespace OVR\Subscription;

use OVR\Core\Pages;
use OVR\Subscription\Plans;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SubscriptionManager {

    /**
     * Activate a subscription after successful payment.
     *
     * Sets the plan, status, expiry, grants the ovr_landlord role,
     * and restores any pending_renewal listings.
     */
    public static function activate( int $user_id, string $plan_slug ): void {
        $plan   = Plans::get_plan( $plan_slug );
        $period = is_array( $plan ) && isset( $plan['period'] ) ? (string) $plan['period'] : 'annually';

        // Each billing period must map to its own term. Anything unrecognised
        // falls back to a year, but quarterly/monthly are named explicitly so a
        // quarterly plan cannot silently grant twelve months.
        $terms = [
            'monthly'   => '+1 month',
            'quarterly' => '+3 months',
            'annually'  => '+1 year',
            'yearly'    => '+1 year',
        ];
        $term = $terms[ $period ] ?? '+1 year';

        // Renewing before the current term ends must add to the time already
        // paid for, not restart from today — otherwise an early renewal quietly
        // forfeits the remaining days.
        $base    = time();
        $current = (string) get_user_meta( $user_id, UserSubscription::META_EXPIRES, true );
        if ( $current ) {
            $current_ts = strtotime( $current );
            if ( $current_ts && $current_ts > $base ) {
                $base = $current_ts;
            }
        }

        update_user_meta( $user_id, UserSubscription::META_STATUS, UserSubscription::STATUS_ACTIVE );
        update_user_meta( $user_id, UserSubscription::META_PLAN, $plan_slug );
        update_user_meta( $user_id, UserSubscription::META_EXPIRES, gmdate( 'Y-m-d', strtotime( $term, $base ) ) );
        update_user_meta( $user_id, UserSubscription::META_START, current_time( 'mysql' ) );
        update_user_meta( $user_id, UserSubscription::META_EDITING, true );
        delete_user_meta( $user_id, '_ovr_previous_plan' );

        // Grant landlord role if not already.
        $user = new \WP_User( $user_id );
        if ( ! in_array( 'ovr_landlord', (array) $user->roles, true ) ) {
            $user->set_role( 'ovr_landlord' );
        }
        update_user_meta( $user_id, 'ovr_is_landlord', true );

        // Restore any pending_renewal listings.
        self::restore_listings( $user_id );

        do_action( 'ovr_subscription_activated', $user_id, $plan_slug );
    }

    /**
     * Expire a subscription (called by cron or admin action).
     */
    public static function expire( int $user_id ): void {
        $previous_plan = (string) get_user_meta( $user_id, UserSubscription::META_PLAN, true );

        update_user_meta( $user_id, UserSubscription::META_STATUS, UserSubscription::STATUS_EXPIRED );
        update_user_meta( $user_id, UserSubscription::META_PLAN, '' );
        update_user_meta( $user_id, '_ovr_previous_plan', $previous_plan );

        // Mark extra listings as pending_renewal.
        $q = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'posts_per_page' => 999,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        $base_limit = 0;
        foreach ( $q->posts as $i => $post_id ) {
            if ( $i < $base_limit ) {
                continue;
            }
            update_post_meta( $post_id, '_ovr_listing_status', 'pending_renewal' );
        }

        do_action( 'ovr_subscription_expired', $user_id, $previous_plan );
    }

    /**
     * Renew: activate the same plan for another term.
     */
    public static function renew( int $user_id, string $plan_slug ): void {
        self::activate( $user_id, $plan_slug );
        do_action( 'ovr_subscription_renewed', $user_id, $plan_slug );
    }

    /**
     * Upgrade: activate a different plan, recalculate expiry.
     */
    public static function upgrade( int $user_id, string $new_plan_slug ): void {
        self::activate( $user_id, $new_plan_slug );
        do_action( 'ovr_subscription_upgraded', $user_id, $new_plan_slug );
    }

    /**
     * Where should a user be redirected based on subscription status?
     * Returns a URL string, or empty string if no redirect needed (access granted).
     */
    public static function get_redirect_by_status( int $user_id = 0 ): string {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return home_url();
        }

        $status      = UserSubscription::get_status( $user_id );
        $select_url  = Pages::get_page_url( 'ovr_page_subscription_select' );

        switch ( $status ) {
            case UserSubscription::STATUS_NONE:
                return $select_url;
            case UserSubscription::STATUS_PENDING:
                return add_query_arg( 'payment', 'pending', $select_url );
            case UserSubscription::STATUS_EXPIRED:
            case UserSubscription::STATUS_CANCELLED:
                return add_query_arg( 'renew', 'required', $select_url );
            case UserSubscription::STATUS_SUSPENDED:
                return add_query_arg( 'suspended', '1', $select_url );
            case UserSubscription::STATUS_ACTIVE:
                return '';
            default:
                return $select_url;
        }
    }

    /**
     * Restore all pending_renewal listings to active.
     */
    private static function restore_listings( int $user_id ): void {
        $q = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'posts_per_page' => 999,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [ 'key' => '_ovr_listing_status', 'value' => 'pending_renewal' ],
            ],
        ] );

        foreach ( $q->posts as $post_id ) {
            update_post_meta( $post_id, '_ovr_listing_status', 'active' );
            wp_cache_delete( 'ovr_pricing_' . $post_id, 'ovr' );
        }
    }
}
