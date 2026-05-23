<?php
/**
 * Plugin Activator.
 *
 * Handles all first-run setup: database tables, default options,
 * custom roles/capabilities, auto-created pages, and flush rewrite rules.
 *
 * @package OVR
 * @since   1.0.0
 */

namespace OVR;

use OVR\Core\Database;
use OVR\Core\Roles;
use OVR\Core\Pages;
use OVR\Property\IcalSync;
use OVR\Subscription\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    /**
     * Run activation routines.
     *
     * @since 1.0.0
     */
    public static function activate(): void {
        // 1. Create custom database tables.
        Database::create_tables();

        // 2. Register custom roles and capabilities.
        Roles::create_roles();

        // 3. Auto-create required pages (Login, Register, etc.).
        Pages::create_pages();

        // 4. Set default plugin options.
        self::set_default_options();

        // 5. Register CPTs temporarily so rewrite rules can be flushed.
        // We must register them here because the init hook hasn't fired yet.
        $cpt = new \OVR\PostTypes\PropertyPostType();
        $cpt->register_post_type();

        $tax = new \OVR\PostTypes\Taxonomies();
        $tax->register_taxonomies();

        // 6. Flush rewrite rules to register new CPT permalinks.
        flush_rewrite_rules();

        // 7. Schedule recurring iCal sync (hourly).
        IcalSync::schedule_cron();

        // 8. Schedule daily subscription expiry check.
        Lifecycle::schedule_cron();

        // 9. Store activation timestamp and version.
        update_option( 'ovr_activated_at', current_time( 'mysql' ) );
        update_option( 'ovr_version', OVR_VERSION );

        /**
         * Fires after the OVR plugin has been activated.
         *
         * @since 1.0.0
         */
        do_action( 'ovr_activated' );
    }

    /**
     * Set default plugin options on first activation.
     *
     * @since 1.0.0
     */
    private static function set_default_options(): void {
        $defaults = [
            'ovr_settings' => [
                'currency'             => 'USD',
                'currency_symbol'      => '$',
                'date_format'          => 'm/d/Y',
                'listings_per_page'    => 12,
                'enable_reviews'       => true,
                'review_approval'      => true,
                'enable_inquiries'     => true,
                'inquiry_retention'    => 365,
                'enable_watermark'     => false,
                'watermark_text'       => 'Our Villages Rentals',
                'bump_daily_limit'     => 3,
                'grace_period_days'    => 7,
                'inactivity_days'      => 180,
                'google_maps_api_key'  => '',
                'default_country'      => 'US',
                'enable_ical_sync'     => true,
            ],
        ];

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }
}
