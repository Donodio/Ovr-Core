<?php
/**
 * Platform Overview — site-owner (administrator) dashboard.
 *
 * Adds an "Overview" submenu at the top of the OVR Properties menu giving the
 * site owner a single, minimalist view of the whole platform: properties,
 * landlords, inquiries, and revenue, plus the most recent listings.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PlatformOverview {

    public const PAGE_SLUG = 'ovr-platform-overview';

    /** User-meta key storing each admin's dashboard widget preferences. */
    public const WIDGETS_META = 'ovr_dash_widgets';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'wp_ajax_ovr_save_dash_widgets', [ $this, 'handle_save_widgets' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Platform Overview', 'ovr-core' ),
            __( 'Overview', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ],
            0 // First item, above "All Properties".
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        TemplateLoader::render( 'admin/platform-overview.php', [
            'stats'           => $this->collect_stats(),
            'recent_props'    => $this->recent_properties( 8 ),
            'activity'        => $this->recent_activity( 6 ),
            'widget_prefs'    => $this->widget_prefs(),
            'widgets_nonce'   => wp_create_nonce( 'ovr_dash_widgets' ),
            'search_url'      => add_query_arg(
                [ 'post_type' => 'ovr_property', 'page' => GlobalSearch::PAGE_SLUG ],
                admin_url( 'edit.php' )
            ),
            'settings_url'    => add_query_arg(
                [ 'post_type' => 'ovr_property', 'page' => Settings::PAGE_SLUG ],
                admin_url( 'edit.php' )
            ),
            'add_property_url' => admin_url( 'post-new.php?post_type=ovr_property' ),
            'all_props_url'    => admin_url( 'edit.php?post_type=ovr_property' ),
            'users_url'        => admin_url( 'users.php?role=ovr_landlord' ),
            'all_users_url'    => admin_url( 'users.php' ),
            'payments_url'     => admin_url( 'edit.php?post_type=ovr_property&page=' . Settings::PAGE_SLUG ),
        ] );
    }

    /**
     * Platform-wide counters.
     *
     * @return array<string, mixed>
     */
    private function collect_stats(): array {
        global $wpdb;

        $properties_total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ovr_property' AND post_status = 'publish'"
        );

        $properties_active = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_ovr_listing_status'
             WHERE p.post_type = 'ovr_property' AND p.post_status = 'publish'
               AND ( pm.meta_value IS NULL OR pm.meta_value = 'active' )"
        );

        $properties_featured = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_ovr_is_featured'
             WHERE p.post_type = 'ovr_property' AND p.post_status = 'publish' AND pm.meta_value = '1'"
        );

        $inquiries_table = $wpdb->prefix . 'ovr_inquiries';
        $inquiries_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$inquiries_table}" );
        $inquiries_new   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$inquiries_table} WHERE status = 'new'" );

        // Listings added in the last 7 days.
        $properties_new_week = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'ovr_property' AND post_status = 'publish'
               AND post_date >= ( NOW() - INTERVAL 7 DAY )"
        );

        // Inquiries logged today + average reply time (hours).
        $inquiries_today = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$inquiries_table} WHERE DATE(created_at) = CURDATE()"
        );
        $avg_reply_min = $wpdb->get_var(
            "SELECT AVG( TIMESTAMPDIFF( MINUTE, created_at, replied_at ) )
             FROM {$inquiries_table} WHERE replied_at IS NOT NULL"
        );
        $avg_reply_hours = ( null !== $avg_reply_min ) ? round( ( (float) $avg_reply_min ) / 60, 1 ) : null;

        $payments_table  = $wpdb->prefix . 'ovr_payments';
        $revenue_total   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$payments_table} WHERE status = 'completed'" );
        $payments_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$payments_table} WHERE status = 'completed'" );

        // Revenue this calendar month vs the previous month.
        $revenue_month = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount),0) FROM {$payments_table}
             WHERE status = 'completed'
               AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
        );
        $revenue_prev_month = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount),0) FROM {$payments_table}
             WHERE status = 'completed'
               AND YEAR(created_at) = YEAR( CURDATE() - INTERVAL 1 MONTH )
               AND MONTH(created_at) = MONTH( CURDATE() - INTERVAL 1 MONTH )"
        );
        $revenue_change = ( $revenue_prev_month > 0 )
            ? round( ( ( $revenue_month - $revenue_prev_month ) / $revenue_prev_month ) * 100, 1 )
            : null;

        // Revenue year-to-date (M3 F1).
        $revenue_year = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount),0) FROM {$payments_table}
             WHERE status = 'completed' AND YEAR(created_at) = YEAR(CURDATE())"
        );

        // Listing status breakdown (M3 F1): pending review + expired/lapsed.
        $properties_pending = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_ovr_admin_status' AND meta_value = 'pending_review'"
        );
        $properties_expired = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_ovr_listing_status' AND meta_value = 'pending_renewal'"
        );

        // Six-month revenue series for the Growth Trends chart.
        $revenue_series = $this->revenue_series( 6 );

        // Pending reviews awaiting moderation.
        $reviews_table   = $wpdb->prefix . 'ovr_reviews';
        $reviews_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$reviews_table} WHERE status = 'pending'" );

        // Listings parked because a subscription lapsed.
        $renewals_pending = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_ovr_listing_status' AND meta_value = 'pending_renewal'"
        );

        $users         = count_users();
        $users_total   = (int) ( $users['total_users'] ?? 0 );
        $landlords_total = (int) ( $users['avail_roles']['ovr_landlord'] ?? 0 );
        $users_new_week = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= ( NOW() - INTERVAL 7 DAY )"
        );

        $settings = (array) get_option( Settings::OPTION, [] );

        // Map engagement (M3 F10) — counters fed by the ovr_map_track beacon.
        $map_stats = get_option( 'ovr_map_stats', [] );
        $map_stats = is_array( $map_stats ) ? $map_stats : [];

        return [
            'map_engagement'      => [
                'views'         => (int) ( $map_stats['map_view'] ?? 0 ),
                'marker_clicks' => (int) ( $map_stats['marker_click'] ?? 0 ),
                'popup_views'   => (int) ( $map_stats['popup_view'] ?? 0 ),
                'total'         => (int) ( $map_stats['total'] ?? 0 ),
            ],
            'properties_total'    => $properties_total,
            'properties_active'   => $properties_active,
            'properties_featured' => $properties_featured,
            'properties_pending'  => $properties_pending,
            'properties_expired'  => $properties_expired,
            'properties_new_week' => $properties_new_week,
            'revenue_year'        => $revenue_year,
            'system_health'       => $this->system_health(),
            'landlords_total'     => $landlords_total,
            'users_total'         => $users_total,
            'users_new_week'      => $users_new_week,
            'inquiries_total'     => $inquiries_total,
            'inquiries_new'       => $inquiries_new,
            'inquiries_today'     => $inquiries_today,
            'avg_reply_hours'     => $avg_reply_hours,
            'revenue_total'       => $revenue_total,
            'revenue_month'       => $revenue_month,
            'revenue_change'      => $revenue_change,
            'revenue_series'      => $revenue_series,
            'payments_count'      => $payments_count,
            'reviews_pending'     => $reviews_pending,
            'renewals_pending'    => $renewals_pending,
            'currency_symbol'     => $settings['currency_symbol'] ?? '$',
        ];
    }

    /**
     * Lightweight system-health checks for the dashboard (M3 F1).
     *
     * @return array{ok:bool, items:array<int, array{label:string, ok:bool, note:string}>}
     */
    private function system_health(): array {
        $items = [];

        // Schema up to date.
        $db_ok   = version_compare( (string) get_option( 'ovr_db_version', '0' ), OVR_DB_VERSION, '>=' );
        $items[] = [ 'label' => __( 'Database schema', 'ovr-core' ), 'ok' => $db_ok, 'note' => $db_ok ? __( 'Current', 'ovr-core' ) : __( 'Update pending', 'ovr-core' ) ];

        // Key cron events scheduled.
        $cron_ok = (bool) wp_next_scheduled( 'ovr_hard_delete_listings' ) && (bool) wp_next_scheduled( 'ovr_audit_purge' );
        $items[] = [ 'label' => __( 'Scheduled tasks', 'ovr-core' ), 'ok' => $cron_ok, 'note' => $cron_ok ? __( 'Running', 'ovr-core' ) : __( 'Not scheduled', 'ovr-core' ) ];

        // Uploads writable.
        $uploads = wp_get_upload_dir();
        $up_ok   = empty( $uploads['error'] ) && wp_is_writable( $uploads['basedir'] );
        $items[] = [ 'label' => __( 'Media uploads', 'ovr-core' ), 'ok' => $up_ok, 'note' => $up_ok ? __( 'Writable', 'ovr-core' ) : __( 'Not writable', 'ovr-core' ) ];

        // Cloud storage (informational — not a failure when off).
        $b2 = class_exists( '\OVR\Storage\BackblazeB2Client' ) && \OVR\Storage\BackblazeB2Client::is_configured();
        $items[] = [ 'label' => __( 'Cloud storage (B2)', 'ovr-core' ), 'ok' => true, 'note' => $b2 ? __( 'Connected', 'ovr-core' ) : __( 'Local (off)', 'ovr-core' ) ];

        $all_ok = ! in_array( false, array_column( $items, 'ok' ), true );
        return [ 'ok' => $all_ok, 'items' => $items ];
    }

    /**
     * The current admin's saved widget preferences (hidden + order).
     *
     * @return array{hidden:string[], order:string[]}
     */
    private function widget_prefs(): array {
        $raw = get_user_meta( get_current_user_id(), self::WIDGETS_META, true );
        $raw = is_array( $raw ) ? $raw : [];
        return [
            'hidden' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $raw['hidden'] ?? [] ) ) ) ),
            'order'  => array_values( array_filter( array_map( 'sanitize_key', (array) ( $raw['order'] ?? [] ) ) ) ),
        ];
    }

    /**
     * AJAX: persist the current admin's widget show/hide + order preferences.
     */
    public function handle_save_widgets(): void {
        if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'ovr_dash_widgets', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }
        $hidden = isset( $_POST['hidden'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['hidden'] ) ) : [];
        $order  = isset( $_POST['order'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['order'] ) ) : [];
        update_user_meta( get_current_user_id(), self::WIDGETS_META, [
            'hidden' => array_values( $hidden ),
            'order'  => array_values( $order ),
        ] );
        wp_send_json_success();
    }

    /**
     * Completed revenue grouped by calendar month for the last N months,
     * gaps filled with zero so the chart always has a continuous series.
     *
     * @return array<int, array{label:string, total:float}>
     */
    private function revenue_series( int $months = 6 ): array {
        global $wpdb;

        $payments_table = $wpdb->prefix . 'ovr_payments';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS ym, COALESCE(SUM(amount),0) AS total
                 FROM {$payments_table}
                 WHERE status = 'completed' AND created_at >= ( CURDATE() - INTERVAL %d MONTH )
                 GROUP BY ym",
                $months - 1
            ),
            OBJECT_K
        );

        $series = [];
        for ( $i = $months - 1; $i >= 0; $i-- ) {
            $ts  = strtotime( "first day of -{$i} month" );
            $key = gmdate( 'Y-m', $ts );
            $series[] = [
                'label' => gmdate( 'M', $ts ),
                'total' => isset( $rows[ $key ] ) ? (float) $rows[ $key ]->total : 0.0,
            ];
        }
        return $series;
    }

    /**
     * Build a unified, time-ordered activity feed from the most recent
     * listings, registrations, payments, and reviews across the platform.
     *
     * @return array<int, array{icon:string, tone:string, text:string, ts:int}>
     */
    private function recent_activity( int $limit = 6 ): array {
        global $wpdb;

        $items = [];

        // Newest listings.
        $props = $wpdb->get_results(
            "SELECT post_title, post_date_gmt FROM {$wpdb->posts}
             WHERE post_type = 'ovr_property' AND post_status = 'publish'
             ORDER BY post_date_gmt DESC LIMIT 4"
        );
        foreach ( $props as $p ) {
            $items[] = [
                'icon' => 'home_work',
                'tone' => 'secondary',
                'text' => sprintf( __( 'New listing added: %s', 'ovr-core' ), '<strong>' . esc_html( $p->post_title ?: __( '(untitled)', 'ovr-core' ) ) . '</strong>' ),
                'ts'   => (int) strtotime( $p->post_date_gmt . ' UTC' ),
            ];
        }

        // New registrations.
        $regs = $wpdb->get_results(
            "SELECT display_name, user_registered FROM {$wpdb->users}
             ORDER BY user_registered DESC LIMIT 4"
        );
        foreach ( $regs as $u ) {
            $items[] = [
                'icon' => 'person_add',
                'tone' => 'neutral',
                'text' => sprintf( __( '%s registered as a new member.', 'ovr-core' ), '<strong>' . esc_html( $u->display_name ) . '</strong>' ),
                'ts'   => (int) strtotime( $u->user_registered . ' UTC' ),
            ];
        }

        // Cleared payments.
        $sym = ( (array) get_option( Settings::OPTION, [] ) )['currency_symbol'] ?? '$';
        $pays = $wpdb->get_results(
            "SELECT amount, created_at FROM {$wpdb->prefix}ovr_payments
             WHERE status = 'completed' ORDER BY created_at DESC LIMIT 4"
        );
        foreach ( $pays as $pay ) {
            $items[] = [
                'icon' => 'payments',
                'tone' => 'tertiary',
                'text' => sprintf( __( 'Payment cleared: %s', 'ovr-core' ), '<strong>' . esc_html( $sym . number_format( (float) $pay->amount, 2 ) ) . '</strong>' ),
                'ts'   => (int) strtotime( $pay->created_at . ' UTC' ),
            ];
        }

        // Reviews awaiting moderation.
        $reviews = $wpdb->get_results(
            "SELECT guest_name, created_at FROM {$wpdb->prefix}ovr_reviews
             WHERE status = 'pending' ORDER BY created_at DESC LIMIT 4"
        );
        foreach ( $reviews as $rv ) {
            $items[] = [
                'icon' => 'rate_review',
                'tone' => 'error',
                'text' => sprintf( __( 'Review submitted by %s awaits approval.', 'ovr-core' ), '<strong>' . esc_html( $rv->guest_name ?: __( 'a guest', 'ovr-core' ) ) . '</strong>' ),
                'ts'   => (int) strtotime( $rv->created_at . ' UTC' ),
            ];
        }

        usort( $items, static fn( $a, $b ) => $b['ts'] <=> $a['ts'] );

        return array_slice( $items, 0, $limit );
    }

    /**
     * Latest published listings across all landlords.
     *
     * @return \WP_Post[]
     */
    private function recent_properties( int $limit = 8 ): array {
        $q = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => [ 'publish', 'pending', 'draft' ],
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );
        return $q->posts ?: [];
    }
}
