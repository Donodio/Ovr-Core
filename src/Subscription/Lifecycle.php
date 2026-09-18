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
     * Context: ['plan_slug' => 'standard_homeowner_5', 'payment_id' => 123, 'promo_code' => 'FREE180'].
     */
    public function on_payment_completed( int $user_id, array $context = [] ): void {
        $plan_slug = (string) ( $context['plan_slug'] ?? '' );
        if ( ! $plan_slug ) return;

        $payment_type = (string) ( $context['payment_type'] ?? 'subscription' );

        // Listing upgrades are handled by UpgradeActivator — not subscription.
        if ( 'listing_upgrade' === $payment_type ) {
            return;
        }

        // Promo duration override (e.g. 180-day $0 promo) — look up the code's
        // duration_days and pass it to activate() so expiry is base + N days.
        $duration_days = null;
        $promo_code = (string) ( $context['promo_code'] ?? '' );
        $payment_id = (int) ( $context['payment_id'] ?? 0 );

        // Snapshot first (Section 2): once an offer/payment has authorized a
        // term, later promo edits must not change what the buyer paid for.
        // duration_override_days is the promo-authorized term; null means the
        // plan's own period applies (existing behaviour).
        $payment_meta = [];
        if ( $payment_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ovr_payments';
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT meta_data FROM {$table} WHERE id = %d", $payment_id ), ARRAY_A );
            if ( $row && ! empty( $row['meta_data'] ) ) {
                $decoded = json_decode( (string) $row['meta_data'], true );
                if ( is_array( $decoded ) ) {
                    $payment_meta = $decoded;
                }
            }
        }
        if ( '' === $promo_code && ! empty( $payment_meta['promo_code'] ) ) {
            $promo_code = (string) $payment_meta['promo_code'];
        }

        $snapshot_days = $context['duration_override_days'] ?? ( $payment_meta['duration_override_days'] ?? null );
        if ( null !== $snapshot_days && '' !== $snapshot_days && (int) $snapshot_days > 0 ) {
            $duration_days = (int) $snapshot_days;
        } elseif ( '' !== $promo_code ) {
            $promo_row = \OVR\Payment\PromoCode::get_by_code( $promo_code );
            if ( $promo_row && ! empty( $promo_row['duration_days'] ) ) {
                $duration_days = (int) $promo_row['duration_days'];
            }
        }

        // Use SubscriptionManager to activate (sets status, plan, role, restores listings).
        SubscriptionManager::activate( $user_id, $plan_slug, $duration_days );
    }
}
