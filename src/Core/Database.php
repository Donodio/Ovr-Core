<?php
/**
 * Custom Database Tables.
 *
 * Creates and manages custom tables for data that doesn't fit WordPress
 * post meta: seasonal pricing, availability blocks, inquiries, payments,
 * audit logs, and promo codes.
 *
 * @package OVR\Core
 * @since   1.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database {

    /**
     * Initialize — check for schema updates on admin_init.
     *
     * @since 1.0.0
     */
    public function init(): void {
        add_action( 'admin_init', [ $this, 'check_schema_version' ] );
    }

    /**
     * Check if database schema needs updating.
     *
     * @since 1.0.0
     */
    public function check_schema_version(): void {
        $installed_version = get_option( 'ovr_db_version', '0' );

        if ( version_compare( $installed_version, OVR_DB_VERSION, '<' ) ) {
            self::create_tables();
        }
    }

    /**
     * Create all custom tables using dbDelta.
     *
     * @since 1.0.0
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Seasonal Pricing Table.
        $table_pricing = $wpdb->prefix . 'ovr_seasonal_pricing';
        dbDelta( "CREATE TABLE {$table_pricing} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            season_name varchar(100) NOT NULL DEFAULT '',
            start_date date NOT NULL,
            end_date date NOT NULL,
            nightly_rate decimal(10,2) NOT NULL DEFAULT 0.00,
            weekly_rate decimal(10,2) DEFAULT NULL,
            monthly_rate decimal(10,2) DEFAULT NULL,
            min_stay int(11) NOT NULL DEFAULT 1,
            is_override tinyint(1) NOT NULL DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY date_range (start_date, end_date)
        ) {$charset_collate};" );

        // Availability / Calendar Blocks Table.
        $table_avail = $wpdb->prefix . 'ovr_availability';
        dbDelta( "CREATE TABLE {$table_avail} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            block_type varchar(30) NOT NULL DEFAULT 'blocked',
            start_date date NOT NULL,
            end_date date NOT NULL,
            source varchar(50) NOT NULL DEFAULT 'manual',
            ical_uid varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            show_as_available tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY date_range (start_date, end_date),
            KEY source (source)
        ) {$charset_collate};" );

        // Inquiries Table.
        $table_inquiries = $wpdb->prefix . 'ovr_inquiries';
        dbDelta( "CREATE TABLE {$table_inquiries} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            landlord_id bigint(20) unsigned NOT NULL,
            guest_name varchar(200) NOT NULL DEFAULT '',
            guest_email varchar(200) NOT NULL DEFAULT '',
            guest_phone varchar(50) DEFAULT NULL,
            checkin_date date DEFAULT NULL,
            checkout_date date DEFAULT NULL,
            guests int(11) DEFAULT NULL,
            message text NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'new',
            replied_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY landlord_id (landlord_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        // Payments / Transaction History Table.
        $table_payments = $wpdb->prefix . 'ovr_payments';
        dbDelta( "CREATE TABLE {$table_payments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            payment_type varchar(50) NOT NULL DEFAULT 'subscription',
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            gateway varchar(50) NOT NULL DEFAULT '',
            transaction_id varchar(255) DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'pending',
            description text DEFAULT NULL,
            meta_data longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY payment_type (payment_type),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        // Audit / History Log Table.
        $table_audit = $wpdb->prefix . 'ovr_audit_log';
        dbDelta( "CREATE TABLE {$table_audit} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            action varchar(100) NOT NULL DEFAULT '',
            object_type varchar(50) NOT NULL DEFAULT '',
            object_id bigint(20) unsigned DEFAULT NULL,
            details longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type, object_id),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        // Promo Codes Table.
        $table_promos = $wpdb->prefix . 'ovr_promo_codes';
        dbDelta( "CREATE TABLE {$table_promos} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'percentage',
            discount_value decimal(10,2) NOT NULL DEFAULT 0.00,
            max_uses int(11) DEFAULT NULL,
            current_uses int(11) NOT NULL DEFAULT 0,
            valid_from date DEFAULT NULL,
            valid_until date DEFAULT NULL,
            applicable_plans text DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY is_active (is_active)
        ) {$charset_collate};" );

        // Wallet Transactions Table.
        $table_wallet = $wpdb->prefix . 'ovr_wallet_transactions';
        dbDelta( "CREATE TABLE {$table_wallet} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            kind varchar(20) NOT NULL DEFAULT 'credit',
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            balance_after decimal(10,2) NOT NULL DEFAULT 0.00,
            description varchar(255) NOT NULL DEFAULT '',
            related_payment_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        // Reviews Table.
        $table_reviews = $wpdb->prefix . 'ovr_reviews';
        dbDelta( "CREATE TABLE {$table_reviews} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            guest_name varchar(100) NOT NULL DEFAULT '',
            guest_email varchar(255) NOT NULL DEFAULT '',
            rating tinyint(1) unsigned NOT NULL DEFAULT 5,
            title varchar(255) NOT NULL DEFAULT '',
            body text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        update_option( 'ovr_db_version', OVR_DB_VERSION );
    }
}
