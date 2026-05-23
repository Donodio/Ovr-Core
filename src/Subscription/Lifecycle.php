<?php
/**
 * Subscription Lifecycle.
 *
 * Daily cron monitors `ovr_subscription_expires` user meta. When expired:
 *   - Plan reverts to base_subscriber (free, 1 listing)
 *   - editing_enabled remains TRUE (Base plan still allows editing the 1 listing)
 *   - All landlord listings beyond the Base limit are marked
 *     `_ovr_listing_status = pending_renewal` (still queryable but
 *     hidden from public search)
 *   - User stays active and can log in
 *
 * On a successful renewal payment:
 *   - Plan restored, expires bumped +1 year
 *   - All `pending_renewal` listings flip back to `active`
 *
 * @package OVR\Subscription
 * @since   1.0.0
 */

namespace OVR\Subscription;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Lifecycle {

    public const CRON_HOOK = 'ovr_subscription_expiry_check';
    public const CRON_RECURRENCE = 'daily';
    public const META_PLAN     = 'ovr_subscription_plan';
    public const META_EXPIRES  = 'ovr_subscription_expires';
    public const META_EDITING  = 'ovr_editing_enabled';

    public function init(): void {
        add_action( self::CRON_HOOK, [ $this, 'check_all' ] );

        // When a payment completes, restore listings + extend expiry.
        add_action( 'ovr_payment_completed', [ $this, 'on_payment_completed' ], 10, 2 );
    }

    public static function schedule_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( strtotime( 'tomorrow 03:00' ), self::CRON_RECURRENCE, self::CRON_HOOK );
        }
    }

    public static function unschedule_cron(): void {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) wp_unschedule_event( $ts, self::CRON_HOOK );
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Daily check — iterate users whose ovr_subscription_expires has passed.
     */
    public function check_all(): void {
        $today = current_time( 'Y-m-d' );

        $users = get_users( [
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'     => self::META_EXPIRES,
                    'value'   => $today,
                    'compare' => '<',
                    'type'    => 'DATE',
                ],
                [
                    'key'     => self::META_PLAN,
                    'value'   => 'base_subscriber',
                    'compare' => '!=',
                ],
            ],
            'fields' => [ 'ID' ],
        ] );

        foreach ( $users as $u ) {
            $this->expire_user( (int) $u->ID );
        }

        do_action( 'ovr_subscription_check_complete', count( $users ) );
    }

    /**
     * Expire a single user — revert plan, mark listings.
     */
    public function expire_user( int $user_id ): void {
        $previous_plan = (string) get_user_meta( $user_id, self::META_PLAN, true );

        update_user_meta( $user_id, self::META_PLAN, 'base_subscriber' );
        update_user_meta( $user_id, '_ovr_previous_plan', $previous_plan );

        // Mark all but the most recent listing as pending_renewal.
        $base_limit = (int) ( Plans::get_plan( 'base_subscriber' )['max_listings'] ?? 1 );

        $q = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        $kept = 0;
        foreach ( $q->posts as $post_id ) {
            if ( $kept < $base_limit ) {
                // Keep this one active.
                $kept++;
                continue;
            }
            update_post_meta( $post_id, '_ovr_listing_status', 'pending_renewal' );
        }

        do_action( 'ovr_subscription_expired', $user_id, $previous_plan );
    }

    /**
     * Restore a user's listings after a successful renewal payment.
     *
     * Fires off the `ovr_payment_completed` action with ($user_id, $plan_slug).
     * Hook signature: do_action('ovr_payment_completed', $user_id, ['plan_slug' => 'standard_homeowner_5']).
     */
    public function on_payment_completed( int $user_id, array $context = [] ): void {
        $plan_slug = (string) ( $context['plan_slug'] ?? '' );
        if ( ! $plan_slug ) return;

        // Set / refresh plan + expiry.
        update_user_meta( $user_id, self::META_PLAN, $plan_slug );
        update_user_meta( $user_id, self::META_EXPIRES, gmdate( 'Y-m-d', strtotime( '+1 year' ) ) );
        update_user_meta( $user_id, self::META_EDITING, true );
        delete_user_meta( $user_id, '_ovr_previous_plan' );

        // Restore any pending_renewal listings.
        $q = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'posts_per_page' => -1,
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

        do_action( 'ovr_subscription_renewed', $user_id, $plan_slug, count( $q->posts ) );
    }
}
