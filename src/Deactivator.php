<?php
/**
 * Plugin Deactivator.
 *
 * Cleans up scheduled cron events on deactivation.
 * Does NOT remove data — that's handled by uninstall.php.
 *
 * @package OVR
 * @since   1.0.0
 */

namespace OVR;

use OVR\Property\IcalSync;
use OVR\Subscription\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {

    /**
     * Run deactivation routines.
     *
     * @since 1.0.0
     */
    public static function deactivate(): void {
        // Clear all scheduled cron events.
        $cron_hooks = [
            'ovr_subscription_expiry_check',
            'ovr_send_renewal_reminders',
            'ovr_purge_old_inquiries',
            'ovr_inactivity_check',
            'ovr_hard_delete_listings',
        ];

        foreach ( $cron_hooks as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }

        // Unschedule iCal sync via its dedicated helper.
        IcalSync::unschedule_cron();

        // Unschedule daily subscription check.
        Lifecycle::unschedule_cron();

        // Flush rewrite rules.
        flush_rewrite_rules();

        /**
         * Fires after the OVR plugin has been deactivated.
         *
         * @since 1.0.0
         */
        do_action( 'ovr_deactivated' );
    }
}
