<?php
/**
 * Subscription Lifecycle.
 *
 * Daily cron monitors `ovr_subscription_expires` user meta. When expired:
 *   - Status set to expired
 *   - All landlord listings marked `_ovr_listing_status = pending_renewal`
 *   - User kept as subscriber (no role removal)
 *
 * On a successful renewal/upgrade payment:
 *   - Status set to active, plan + expiry restored
 *   - All pending_renewal listings flip back to active
 *
 * @package OVR\Subscription
 * @since   1.0.0
 */

namespace OVR\Subscription;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Lifecycle {

    public const CRON_HOOK      = 'ovr_subscription_expiry_check';
    public const CRON_RECURRENCE = 'daily';

    public function init(): void {
        add_action( self::CRON_HOOK, [ $this, 'check_all' ] );
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
     * Daily check — expire users whose subscription has passed the expiry date.
     */
    public function check_all(): void {
        $today = current_time( 'Y-m-d' );

        $users = get_users( [
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'     => UserSubscription::META_STATUS,
                    'value'   => UserSubscription::STATUS_ACTIVE,
                ],
                [
                    'key'     => UserSubscription::META_EXPIRES,
                    'value'   => '',
                    'compare' => '!=',
                ],
                [
                    'key'     => UserSubscription::META_EXPIRES,
                    'value'   => $today,
                    'compare' => '<',
                    'type'    => 'DATE',
                ],
            ],
            'fields' => [ 'ID' ],
        ] );

        foreach ( $users as $u ) {
            SubscriptionManager::expire( (int) $u->ID );
        }

        do_action( 'ovr_subscription_check_complete', count( $users ) );
    }

    /**
     * Restore a user's listings after a successful payment.
     *
     * Fires on `ovr_payment_completed` with ($user_id, $context).
     * Context: ['plan_slug' => 'standard_homeowner_5'].
     */
    public function on_payment_completed( int $user_id, array $context = [] ): void {
        $plan_slug = (string) ( $context['plan_slug'] ?? '' );
        if ( ! $plan_slug ) return;

        $payment_type = (string) ( $context['payment_type'] ?? 'subscription' );

        // Listing upgrades are handled by UpgradeActivator — not subscription.
        if ( 'listing_upgrade' === $payment_type ) {
            return;
        }

        // Use SubscriptionManager to activate (sets status, plan, role, restores listings).
        SubscriptionManager::activate( $user_id, $plan_slug );
    }
}
