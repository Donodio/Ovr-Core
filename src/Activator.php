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

        // 5b. Seed the canonical Village Section terms (Phase 10).
        \OVR\PostTypes\Taxonomies::seed_sections();
        update_option( 'ovr_sections_seeded', 1 );

        // 5b-ii. Seed the View / Feature facet terms (Feature 8 search filters).
        \OVR\PostTypes\Taxonomies::seed_facets();
        update_option( 'ovr_facets_seeded', 1 );

        // 5c. Seed the Paid Services catalogue (Feature 1) from legacy products.
        \OVR\Subscription\PaidService::maybe_seed();

        // 6. Flush rewrite rules to register new CPT permalinks.
        flush_rewrite_rules();

        // 7. Schedule recurring iCal sync (hourly) + address geocoding backfill.
        IcalSync::schedule_cron();
        \OVR\Property\Geocoder::schedule_cron();

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
                'watermark_text'       => 'Our Village Rentals',
                'bump_daily_limit'     => 12,
                'listing_retention_days' => 180,
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
