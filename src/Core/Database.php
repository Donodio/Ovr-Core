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
        // Also on the front end so schema upgrades (e.g. new pricing columns)
        // land even if no admin loads wp-admin first. Guarded by a version
        // compare + option write, so the dbDelta runs at most once per bump.
        add_action( 'init', [ $this, 'check_schema_version' ] );
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
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            nightly_rate decimal(10,2) NOT NULL DEFAULT 0.00,
            weekly_rate decimal(10,2) DEFAULT NULL,
            monthly_rate decimal(10,2) DEFAULT NULL,
            flat_rate decimal(10,2) DEFAULT NULL,
            rate_type varchar(20) NOT NULL DEFAULT 'custom',
            min_stay int(11) NOT NULL DEFAULT 1,
            min_stay_label varchar(50) NOT NULL DEFAULT '',
            is_override tinyint(1) NOT NULL DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY date_range (start_date, end_date)
        ) {$charset_collate};" );

        // Flexible pricing: periods need not be calendar dates, so make the
        // date columns nullable on existing installs (dbDelta won't always
        // relax NOT NULL). Suppress errors on engines that already match.
        $wpdb->query( "ALTER TABLE {$table_pricing} MODIFY start_date date NULL, MODIFY end_date date NULL" ); // phpcs:ignore WordPress.DB

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
            renter_name varchar(200) NOT NULL DEFAULT '',
            booking_id bigint(20) unsigned DEFAULT NULL,
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
            responses longtext DEFAULT NULL,
            guest_id bigint(20) unsigned DEFAULT NULL,
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
            admin_id bigint(20) unsigned DEFAULT NULL,
            action varchar(100) NOT NULL DEFAULT '',
            object_type varchar(50) NOT NULL DEFAULT '',
            object_id bigint(20) unsigned DEFAULT NULL,
            old_value longtext DEFAULT NULL,
            new_value longtext DEFAULT NULL,
            details longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY admin_id (admin_id),
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
            stay_date date DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            approved_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        // dbDelta can be unreliable adding a column on upgrade — guarantee the
        // M3 review approve-timestamp column explicitly (idempotent).
        if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$table_reviews} LIKE 'approved_at'" ) ) {
            $wpdb->query( "ALTER TABLE {$table_reviews} ADD COLUMN approved_at datetime DEFAULT NULL" ); // phpcs:ignore WordPress.DB
        }

        self::create_phase2_tables( $charset_collate );

        // Seed the email-template catalogue once the table exists (M3 F6).
        if ( class_exists( '\OVR\Email\EmailTemplates' ) ) {
            \OVR\Email\EmailTemplates::maybe_seed();
        }

        update_option( 'ovr_db_version', OVR_DB_VERSION );
    }

    /**
     * Create the Phase 2 tables (bookings, CRM, paid services, sync log,
     * support, knowledge base, review requests, loyalty). Each mutable
     * business table carries created_by/updated_by/created_at/updated_at and
     * a deleted_at column for soft delete.
     *
     * @since 2.0.0
     */
    public static function create_phase2_tables( string $charset_collate ): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Bookings — reservations, manual or platform-imported.
        $table_bookings = $wpdb->prefix . 'ovr_bookings';
        dbDelta( "CREATE TABLE {$table_bookings} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
            guest_id bigint(20) unsigned DEFAULT NULL,
            guest_name varchar(200) NOT NULL DEFAULT '',
            guest_email varchar(200) NOT NULL DEFAULT '',
            guest_phone varchar(50) DEFAULT NULL,
            checkin_date date DEFAULT NULL,
            checkout_date date DEFAULT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            status varchar(30) NOT NULL DEFAULT 'booked',
            source varchar(50) NOT NULL DEFAULT 'manual',
            external_ref varchar(255) DEFAULT NULL,
            payment_id bigint(20) unsigned DEFAULT NULL,
            availability_id bigint(20) unsigned DEFAULT NULL,
            notes text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            updated_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY property_id (property_id),
            KEY owner_id (owner_id),
            KEY guest_id (guest_id),
            KEY status (status),
            KEY source (source),
            KEY checkin_date (checkin_date),
            KEY deleted_at (deleted_at)
        ) {$charset_collate};" );

        // Guests — CRM master manifest, deduplicated by email per owner.
        $table_guests = $wpdb->prefix . 'ovr_guests';
        dbDelta( "CREATE TABLE {$table_guests} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(200) NOT NULL DEFAULT '',
            email varchar(200) NOT NULL DEFAULT '',
            phone varchar(50) DEFAULT NULL,
            address text DEFAULT NULL,
            notes text DEFAULT NULL,
            tags varchar(500) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'active',
            total_stays int(11) NOT NULL DEFAULT 0,
            total_spend decimal(12,2) NOT NULL DEFAULT 0.00,
            last_stay date DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            updated_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY owner_id (owner_id),
            KEY email (email),
            KEY status (status),
            KEY deleted_at (deleted_at)
        ) {$charset_collate};" );

        // Paid Services — admin-defined upgrade products (replaces hardcoded set).
        $table_services = $wpdb->prefix . 'ovr_paid_services';
        dbDelta( "CREATE TABLE {$table_services} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(60) NOT NULL DEFAULT '',
            name varchar(150) NOT NULL DEFAULT '',
            description text DEFAULT NULL,
            service_type varchar(40) NOT NULL DEFAULT 'featured',
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            duration_days int(11) NOT NULL DEFAULT 14,
            badge varchar(60) NOT NULL DEFAULT '',
            priority_weight int(11) NOT NULL DEFAULT 0,
            max_simultaneous int(11) NOT NULL DEFAULT 0,
            is_renewable tinyint(1) NOT NULL DEFAULT 1,
            auto_renew tinyint(1) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            updated_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY service_type (service_type),
            KEY is_active (is_active),
            KEY deleted_at (deleted_at)
        ) {$charset_collate};" );

        // Guarantee the M3 renewal columns on upgrade (dbDelta column-adds vary).
        foreach ( [ 'is_renewable' => 'tinyint(1) NOT NULL DEFAULT 1', 'auto_renew' => 'tinyint(1) NOT NULL DEFAULT 0' ] as $col => $def ) {
            if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$table_services} LIKE '{$col}'" ) ) {
                $wpdb->query( "ALTER TABLE {$table_services} ADD COLUMN {$col} {$def}" ); // phpcs:ignore WordPress.DB
            }
        }

        // Sync Log — one row per sync run (iCal / platform / WordPress import).
        $table_sync = $wpdb->prefix . 'ovr_sync_log';
        dbDelta( "CREATE TABLE {$table_sync} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            channel varchar(40) NOT NULL DEFAULT 'ical',
            property_id bigint(20) unsigned DEFAULT NULL,
            source_url varchar(500) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'success',
            imported int(11) NOT NULL DEFAULT 0,
            message text DEFAULT NULL,
            details longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY channel (channel),
            KEY property_id (property_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        // Support Tickets.
        $table_tickets = $wpdb->prefix . 'ovr_support_tickets';
        dbDelta( "CREATE TABLE {$table_tickets} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            subject varchar(255) NOT NULL DEFAULT '',
            category varchar(60) NOT NULL DEFAULT 'general',
            priority varchar(20) NOT NULL DEFAULT 'normal',
            message text NOT NULL,
            attachments longtext DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            assigned_to bigint(20) unsigned DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            updated_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY priority (priority),
            KEY deleted_at (deleted_at)
        ) {$charset_collate};" );

        // Support ticket replies (append-only thread).
        $table_replies = $wpdb->prefix . 'ovr_ticket_replies';
        dbDelta( "CREATE TABLE {$table_replies} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            is_staff tinyint(1) NOT NULL DEFAULT 0,
            message text NOT NULL,
            attachments longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        // Knowledge Base articles.
        $table_kb = $wpdb->prefix . 'ovr_kb_articles';
        dbDelta( "CREATE TABLE {$table_kb} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            slug varchar(255) NOT NULL DEFAULT '',
            category varchar(80) NOT NULL DEFAULT 'general',
            body longtext DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            sort_order int(11) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            updated_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY category (category),
            KEY status (status),
            KEY deleted_at (deleted_at)
        ) {$charset_collate};" );

        // Review Requests — tokened links a landlord sends to a past guest.
        $table_revreq = $wpdb->prefix . 'ovr_review_requests';
        dbDelta( "CREATE TABLE {$table_revreq} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
            booking_id bigint(20) unsigned DEFAULT NULL,
            guest_name varchar(200) NOT NULL DEFAULT '',
            guest_email varchar(200) NOT NULL DEFAULT '',
            token varchar(64) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            review_id bigint(20) unsigned DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY property_id (property_id),
            KEY owner_id (owner_id),
            KEY status (status)
        ) {$charset_collate};" );

        // Loyalty Ledger — points / credits earned & spent per user.
        $table_loyalty = $wpdb->prefix . 'ovr_loyalty_ledger';
        dbDelta( "CREATE TABLE {$table_loyalty} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            kind varchar(30) NOT NULL DEFAULT 'points',
            direction varchar(10) NOT NULL DEFAULT 'earn',
            points int(11) NOT NULL DEFAULT 0,
            credit_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            balance_after int(11) NOT NULL DEFAULT 0,
            reason varchar(255) NOT NULL DEFAULT '',
            related_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY kind (kind),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        // File Storage — maps WordPress attachments (and each generated size)
        // to their externally-stored copy on Backblaze B2 (Feature E). Stores the
        // public URL, the B2 object key + fileId (needed to delete), type & size.
        $table_files = $wpdb->prefix . 'ovr_file_storage';
        dbDelta( "CREATE TABLE {$table_files} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            size_name varchar(40) NOT NULL DEFAULT 'full',
            provider varchar(30) NOT NULL DEFAULT 'b2',
            storage_key varchar(500) NOT NULL DEFAULT '',
            file_id varchar(255) NOT NULL DEFAULT '',
            file_url varchar(800) NOT NULL DEFAULT '',
            file_type varchar(100) NOT NULL DEFAULT '',
            file_size bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY attachment_id (attachment_id),
            KEY size_name (size_name)
        ) {$charset_collate};" );

        // Email Templates — admin-editable transactional email content (M3 F6).
        // One row per template_key; seeded from the EmailTemplates registry.
        $table_emails = $wpdb->prefix . 'ovr_email_templates';
        dbDelta( "CREATE TABLE {$table_emails} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_key varchar(60) NOT NULL DEFAULT '',
            name varchar(150) NOT NULL DEFAULT '',
            subject varchar(255) NOT NULL DEFAULT '',
            body_html longtext DEFAULT NULL,
            body_text longtext DEFAULT NULL,
            recipient varchar(20) NOT NULL DEFAULT 'user',
            custom_email varchar(200) NOT NULL DEFAULT '',
            is_enabled tinyint(1) NOT NULL DEFAULT 1,
            updated_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY template_key (template_key)
        ) {$charset_collate};" );

        // Bump Log — one row per "Bump Listing" action (Feature F). Powers the
        // per-user daily-limit enforcement and an audit trail (who bumped what,
        // when, from which IP).
        $table_bumps = $wpdb->prefix . 'ovr_bump_log';
        dbDelta( "CREATE TABLE {$table_bumps} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            property_id bigint(20) unsigned NOT NULL DEFAULT 0,
            ip_address varchar(45) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY property_id (property_id),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        // Homepage Hero Slides (M3 F7) — an admin-managed slideshow that feeds the
        // Elementor "OVR Hero Section" widget. Each slide: a background image plus
        // optional per-slide heading/subtitle/CTA. sort_order = display order.
        $table_slides = $wpdb->prefix . 'ovr_hero_slides';
        dbDelta( "CREATE TABLE {$table_slides} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            image_id bigint(20) unsigned NOT NULL DEFAULT 0,
            heading varchar(255) NOT NULL DEFAULT '',
            subtitle text DEFAULT NULL,
            cta_text varchar(120) NOT NULL DEFAULT '',
            cta_url varchar(500) NOT NULL DEFAULT '',
            sort_order int(11) NOT NULL DEFAULT 0,
            is_enabled tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sort_order (sort_order),
            KEY is_enabled (is_enabled)
        ) {$charset_collate};" );
    }
}
