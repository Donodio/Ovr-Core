<?php
/**
 * Uninstall handler for OVR Core.
 *
 * Fired when the plugin is deleted through the WordPress admin.
 * Removes ALL plugin data: options, custom tables, user meta, post meta,
 * transients, and custom roles.
 *
 * @package OVR
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

/*
|--------------------------------------------------------------------------
| Remove Plugin Options
|--------------------------------------------------------------------------
*/
$options_to_delete = [
    'ovr_settings',
    'ovr_version',
    'ovr_db_version',
    'ovr_activated_at',
    'ovr_subscription_plans',
    'ovr_page_login',
    'ovr_page_register',
    'ovr_page_forgot_password',
    'ovr_page_dashboard',
    'ovr_page_pricing',
    'ovr_page_search',
    'ovr_page_featured',
    'ovr_page_onboarding',
];

foreach ( $options_to_delete as $option ) {
    delete_option( $option );
}

/*
|--------------------------------------------------------------------------
| Remove Custom Database Tables
|--------------------------------------------------------------------------
*/
$tables = [
    $wpdb->prefix . 'ovr_seasonal_pricing',
    $wpdb->prefix . 'ovr_availability',
    $wpdb->prefix . 'ovr_inquiries',
    $wpdb->prefix . 'ovr_payments',
    $wpdb->prefix . 'ovr_audit_log',
    $wpdb->prefix . 'ovr_promo_codes',
];

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

/*
|--------------------------------------------------------------------------
| Remove User Meta
|--------------------------------------------------------------------------
*/
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ovr\_%'" );

/*
|--------------------------------------------------------------------------
| Remove Post Meta
|--------------------------------------------------------------------------
*/
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ovr\_%'" );

/*
|--------------------------------------------------------------------------
| Remove Transients
|--------------------------------------------------------------------------
*/
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ovr\_%' OR option_name LIKE '_transient_timeout_ovr\_%'"
);

/*
|--------------------------------------------------------------------------
| Remove Custom Roles
|--------------------------------------------------------------------------
*/
remove_role( 'ovr_landlord' );

/*
|--------------------------------------------------------------------------
| Remove Plugin-Created Pages
|--------------------------------------------------------------------------
*/
$page_options = [
    'ovr_page_login',
    'ovr_page_register',
    'ovr_page_forgot_password',
    'ovr_page_dashboard',
    'ovr_page_pricing',
    'ovr_page_search',
    'ovr_page_featured',
    'ovr_page_onboarding',
];

foreach ( $page_options as $option ) {
    $page_id = get_option( $option );
    if ( $page_id ) {
        wp_delete_post( absint( $page_id ), true );
    }
}
