<?php
/**
 * Frontend Dashboard.
 *
 * Renders the [ovr_dashboard] shortcode with 5 tabs:
 *
 *   Overview        — at-a-glance stats (active listings, inquiries, revenue est.)
 *   My Properties   — list of user's properties with quick actions
 *   Inquiries       — inbox of guest inquiries with status + reply links
 *   Subscription    — current plan, listing usage, expiry, change-plan link
 *   Profile         — display name, email, phone (account settings)
 *
 * Anonymous users see a sign-in prompt instead.
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Core\Pages;
use OVR\Subscription\UserSubscription;
use OVR\Subscription\Plans;
use OVR\Payment\Wallet;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Dashboard {

    public function init(): void {}

    /**
     * Render the dashboard. Called by ShortcodeManager.
     */
    public static function render(): string {
        if ( ! is_user_logged_in() ) {
            return TemplateLoader::get_rendered( 'dashboard/login-required.php', [
                'login_url'    => Pages::get_page_url( 'ovr_page_login' ),
                'register_url' => Pages::get_page_url( 'ovr_page_register' ),
            ] );
        }

        $user = wp_get_current_user();
        $tab  = sanitize_key( $_GET['tab'] ?? 'overview' );

        $tabs = [
            'overview'     => [ 'label' => __( 'Overview',      'ovr-core' ), 'icon' => 'dashboard' ],
            'properties'   => [ 'label' => __( 'My Properties', 'ovr-core' ), 'icon' => 'home_work' ],
            'inquiries'    => [ 'label' => __( 'Inquiries',     'ovr-core' ), 'icon' => 'inbox' ],
            'subscription' => [ 'label' => __( 'Subscription',  'ovr-core' ), 'icon' => 'workspace_premium' ],
            'payments'     => [ 'label' => __( 'My Payments',   'ovr-core' ), 'icon' => 'receipt_long' ],
            'balance'      => [ 'label' => __( 'My Balance',    'ovr-core' ), 'icon' => 'account_balance_wallet' ],
            'profile'      => [ 'label' => __( 'My Information', 'ovr-core' ), 'icon' => 'person' ],
            'password'     => [ 'label' => __( 'Change Password', 'ovr-core' ), 'icon' => 'key' ],
        ];

        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'overview';
        }

        // Per-tab data.
        $data = self::collect_data( $user, $tab );

        return TemplateLoader::get_rendered( 'dashboard/wrapper.php', array_merge( $data, [
            'user'              => $user,
            'tabs'              => $tabs,
            'current_tab'       => $tab,
            'base_url'          => Pages::get_page_url( 'ovr_page_dashboard' ),
            'nav_new_inquiries' => self::count_new_inquiries( $user ),
        ] ) );
    }

    /**
     * Count of unread ("new") inquiries — used for the sidebar nav badge on
     * every tab.
     */
    private static function count_new_inquiries( \WP_User $user ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_inquiries';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE landlord_id = %d AND status = 'new'",
            $user->ID
        ) );
    }

    /**
     * Gather the per-tab data on demand. Keeps each shortcode call cheap.
     *
     * @return array<string, mixed>
     */
    private static function collect_data( \WP_User $user, string $tab ): array {
        $data = [];

        switch ( $tab ) {
            case 'overview':
                $data['stats']           = self::compute_stats( $user );
                $data['recent_inquiries'] = self::get_inquiries( $user, 5 );
                $data['properties']      = self::get_properties( $user, 4 );
                break;

            case 'properties':
                $data['properties'] = self::get_properties( $user, -1 );
                $data['add_url']    = admin_url( 'post-new.php?post_type=ovr_property' );
                break;

            case 'inquiries':
                $data['inquiries']    = self::get_inquiries( $user, -1 );
                $data['filter_status'] = sanitize_key( $_GET['status'] ?? 'all' );
                break;

            case 'subscription':
                $data['subscription']  = self::get_subscription_info( $user );
                $data['pricing_url']   = Pages::get_page_url( 'ovr_page_pricing' );
                break;

            case 'profile':
                $data['phone']  = (string) get_user_meta( $user->ID, 'ovr_phone', true );
                $data['saved']  = ! empty( $_GET['profile_saved'] );
                break;

            case 'payments':
                $data['payments'] = self::get_payments( $user );
                break;

            case 'balance':
                $data['balance']      = Wallet::get_balance( $user->ID );
                $data['transactions'] = Wallet::get_transactions( $user->ID, 25 );
                $data['topup_saved']  = ! empty( $_GET['topup_started'] );
                break;

            case 'password':
                $data['password_status'] = sanitize_key( $_GET['pw'] ?? '' );
                break;
        }

        return $data;
    }

    /**
     * Payment history for the current user.
     */
    private static function get_payments( \WP_User $user, int $limit = 50 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $user->ID,
                $limit
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Overview-tab stats.
     */
    private static function compute_stats( \WP_User $user ): array {
        global $wpdb;

        $total_props = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ovr_property' AND post_status = 'publish' AND post_author = %d",
            $user->ID
        ) );

        $active_props = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_ovr_listing_status'
             WHERE p.post_type = 'ovr_property' AND p.post_status = 'publish' AND p.post_author = %d
               AND ( pm.meta_value IS NULL OR pm.meta_value = 'active' )",
            $user->ID
        ) );

        $inquiries_table = $wpdb->prefix . 'ovr_inquiries';
        $total_inq = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$inquiries_table} WHERE landlord_id = %d",
            $user->ID
        ) );
        $new_inq = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$inquiries_table} WHERE landlord_id = %d AND status = 'new'",
            $user->ID
        ) );

        return [
            'total_properties'  => $total_props,
            'active_properties' => $active_props,
            'total_inquiries'   => $total_inq,
            'new_inquiries'     => $new_inq,
        ];
    }

    /**
     * Get properties owned by the user.
     *
     * @param int $limit -1 for all.
     * @return \WP_Post[]
     */
    private static function get_properties( \WP_User $user, int $limit = -1 ): array {
        $q = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => [ 'publish', 'draft', 'pending' ],
            'author'         => $user->ID,
            'posts_per_page' => $limit,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );
        return $q->posts ?: [];
    }

    /**
     * Get inquiries for the user (as landlord).
     *
     * @param int $limit -1 for all.
     */
    private static function get_inquiries( \WP_User $user, int $limit = -1 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_inquiries';
        $sql   = "SELECT * FROM {$table} WHERE landlord_id = %d ORDER BY created_at DESC";
        if ( $limit > 0 ) {
            $sql .= $wpdb->prepare( ' LIMIT %d', $limit );
        }
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $user->ID ), ARRAY_A );
        return $rows ?: [];
    }

    /**
     * Subscription summary for the current user.
     */
    private static function get_subscription_info( \WP_User $user ): array {
        $current       = UserSubscription::get_plan_slug( $user->ID );
        $expires       = (string) get_user_meta( $user->ID, 'ovr_subscription_expires', true );
        $listings_used = UserSubscription::get_listing_count( $user->ID );
        $plan_data     = Plans::get_plan( $current );

        return [
            'plan_slug'    => $current,
            'plan_name'    => $plan_data['name']         ?? __( 'Unknown plan', 'ovr-core' ),
            'plan_limit'   => (int) ( $plan_data['max_listings'] ?? 0 ),
            'expires'      => $expires,
            'listings_used'=> $listings_used,
        ];
    }
}
