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
use OVR\Subscription\ListingUpgrades;
use OVR\Subscription\UpgradeActivator;
use OVR\Payment\Wallet;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Dashboard {

    public function init(): void {
        add_action( 'admin_post_ovr_inquiry_reply', [ $this, 'handle_inquiry_reply' ] );
    }

    /**
     * Record an in-app reply to an inquiry from the dashboard (server-rendered,
     * no-JS). Appends to the inquiry's response history and marks it replied.
     */
    public function handle_inquiry_reply(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( '403' );
        }
        $id = (int) ( $_POST['inquiry_id'] ?? 0 );
        check_admin_referer( 'ovr_inquiry_reply_' . $id );

        global $wpdb;
        $table   = $wpdb->prefix . 'ovr_inquiries';
        $message = sanitize_textarea_field( wp_unslash( $_POST['reply_message'] ?? '' ) );
        $row     = $wpdb->get_row( $wpdb->prepare( "SELECT landlord_id, responses FROM {$table} WHERE id = %d", $id ), ARRAY_A );

        $back = add_query_arg( [ 'tab' => 'inquiries' ], Pages::get_page_url( 'ovr_page_dashboard' ) );

        if ( ! $row || ( (int) $row['landlord_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) || '' === $message ) {
            wp_safe_redirect( add_query_arg( 'ovr_reply', 'error', $back ) );
            exit;
        }

        $history   = $row['responses'] ? (array) json_decode( (string) $row['responses'], true ) : [];
        $history[] = [
            'at'      => current_time( 'mysql' ),
            'by'      => get_current_user_id(),
            'by_name' => wp_get_current_user()->display_name,
            'message' => $message,
        ];

        $wpdb->update(
            $table,
            [ 'responses' => wp_json_encode( $history ), 'status' => 'replied', 'replied_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ]
        );
        \OVR\Core\AuditLog::record( 'inquiry.reply', 'inquiry', $id );

        wp_safe_redirect( add_query_arg( 'ovr_reply', 'sent', $back ) );
        exit;
    }

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

        // Gate (defense-in-depth; the SubscriptionGate also redirects the page):
        // landlord tools require an active paid subscription.
        if ( ! current_user_can( 'manage_options' ) && ! UserSubscription::has_listing_access( $user->ID ) ) {
            $select = Pages::get_page_url( 'ovr_page_subscription_select' );
            return '<div style="max-width:560px;margin:48px auto;padding:36px 28px;text-align:center;font-family:Inter,system-ui,sans-serif;background:#fff;border:1px solid #bec9c8;border-radius:16px">'
                . '<h2 style="color:#004c4c;margin:0 0 10px;font-size:24px">' . esc_html__( 'Subscription required', 'ovr-core' ) . '</h2>'
                . '<p style="color:#3f4948;margin:0 0 22px;font-size:16px;line-height:1.6">' . esc_html__( 'You need an active subscription to access your landlord dashboard and listings.', 'ovr-core' ) . '</p>'
                . '<a href="' . esc_url( $select ) . '" style="display:inline-block;background:#004c4c;color:#fff;padding:14px 30px;border-radius:10px;text-decoration:none;font-weight:700;font-size:16px">' . esc_html__( 'Choose a Plan', 'ovr-core' ) . '</a>'
                . '</div>';
        }

        $tab  = sanitize_key( $_GET['tab'] ?? 'overview' );

        $tabs = [
            'overview'     => [ 'label' => __( 'Overview',      'ovr-core' ), 'icon' => 'dashboard' ],
            'properties'   => [ 'label' => __( 'My Properties', 'ovr-core' ), 'icon' => 'home_work' ],
            'add-listing'  => [ 'label' => __( 'Add Listing',   'ovr-core' ), 'icon' => 'add_home' ],
            'upgrades'     => [ 'label' => __( 'Listing Upgrades', 'ovr-core' ), 'icon' => 'trending_up' ],
            'inquiries'    => [ 'label' => __( 'Inquiries',     'ovr-core' ), 'icon' => 'inbox' ],
            'reviews'      => [ 'label' => __( 'Review Requests', 'ovr-core' ), 'icon' => 'reviews' ],
            'subscription' => [ 'label' => __( 'Subscription',  'ovr-core' ), 'icon' => 'workspace_premium' ],
            'payments'     => [ 'label' => __( 'My Payments',   'ovr-core' ), 'icon' => 'receipt_long' ],
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
                $data['stats']            = self::compute_stats( $user );
                $data['recent_inquiries'] = self::get_inquiries( $user, 5 );
                $data['properties']       = self::get_properties( $user, 4 );
                $data['balance']          = Wallet::get_balance( $user->ID );
                $data['subscription']     = self::get_subscription_info( $user );
                $data['add_url']          = add_query_arg( 'tab', 'add-listing', Pages::get_page_url( 'ovr_page_dashboard' ) );
                $data['pricing_url']      = Pages::get_page_url( 'ovr_page_pricing' );
                break;

            case 'properties':
                $data['properties'] = self::get_properties( $user, -1 );
                $data['add_url']    = add_query_arg( 'tab', 'add-listing', Pages::get_page_url( 'ovr_page_dashboard' ) );
                break;

            case 'add-listing':
                $pid  = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
                $post = $pid ? get_post( $pid ) : null;
                // Only the owner may edit, and only an OVR property.
                if ( $post && ( 'ovr_property' !== $post->post_type || (int) $post->post_author !== $user->ID ) ) {
                    $post = null;
                }
                $data['post']          = $post;
                $data['can_create']    = $post ? true : UserSubscription::can_create_listing( $user->ID );
                $data['block_reason']  = $post ? '' : UserSubscription::listing_block_reason( $user->ID );
                $data['save_action']   = admin_url( 'admin-post.php' );
                $data['ajax_url']      = admin_url( 'admin-ajax.php' );
                $data['listing_nonce'] = wp_create_nonce( 'ovr_listing_action' );
                $data['props_url']     = add_query_arg( 'tab', 'properties', Pages::get_page_url( 'ovr_page_dashboard' ) );
                $data['subscription_url'] = add_query_arg( 'tab', 'subscription', Pages::get_page_url( 'ovr_page_dashboard' ) );
                break;

            case 'upgrades':
                // A specific listing arrives via its "Bump" button (?post=ID).
                $bid   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
                $bpost = $bid ? get_post( $bid ) : null;
                if ( $bpost && ( 'ovr_property' !== $bpost->post_type || (int) $bpost->post_author !== $user->ID ) ) {
                    $bpost = null;
                }
                $data['boost_post']   = $bpost;
                $data['boosted']      = self::get_boosted_properties( $user );
                $data['properties']   = self::get_properties( $user, -1 );
                $data['upgrades']     = ListingUpgrades::get_products();
                $data['checkout_url'] = Pages::get_page_url( 'ovr_page_checkout' );
                $data['props_url']    = add_query_arg( 'tab', 'properties', Pages::get_page_url( 'ovr_page_dashboard' ) );
                $data['pricing_url']  = Pages::get_page_url( 'ovr_page_pricing' );
                break;

            case 'inquiries':
                $data['inquiries']    = self::get_inquiries( $user, -1 );
                $data['filter_status'] = sanitize_key( $_GET['status'] ?? 'all' );
                break;

            case 'reviews':
                $data['properties']      = self::get_properties( $user, -1 );
                $data['review_requests'] = \OVR\Property\ReviewRequest::for_owner( $user->ID, 50 );
                $data['rr_bookings']     = \OVR\Booking\BookingRepository::for_owner( $user->ID, 100 );
                $data['rr_action']       = admin_url( 'admin-post.php' );
                $data['rr_state']        = isset( $_GET['ovr_rr'] ) ? sanitize_key( wp_unslash( $_GET['ovr_rr'] ) ) : '';
                break;

            case 'subscription':
                $data['subscription']  = self::get_subscription_info( $user );
                $data['plans']         = Plans::get_plans();
                $data['pricing_url']   = Pages::get_page_url( 'ovr_page_pricing' );
                $data['checkout_url']  = Pages::get_page_url( 'ovr_page_checkout' );
                break;

            case 'profile':
                $data['phone']   = (string) get_user_meta( $user->ID, 'ovr_phone', true );
                $data['address'] = (string) get_user_meta( $user->ID, 'ovr_address', true );
                $data['saved']   = ! empty( $_GET['profile_saved'] );
                break;

            case 'payments':
                $data['payments']     = self::get_payments( $user );
                $data['receipt_url']  = Pages::get_page_url( 'ovr_page_payment_success' );
                $data['checkout_url'] = Pages::get_page_url( 'ovr_page_checkout' );
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

        // Inquiries received in the trailing 12 months. (Client preference: a
        // 30-day window too often shows "0" and reads as a broken site.)
        $inq_12mo = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$inquiries_table} WHERE landlord_id = %d AND created_at >= ( NOW() - INTERVAL 12 MONTH )",
            $user->ID
        ) );

        // Listings published this calendar month (powers the "+N this month" pill).
        $new_this_month = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'ovr_property' AND post_status = 'publish' AND post_author = %d
               AND post_date >= %s",
            $user->ID,
            gmdate( 'Y-m-01 00:00:00' )
        ) );

        return [
            'total_properties'  => $total_props,
            'active_properties' => $active_props,
            'total_inquiries'   => $total_inq,
            'new_inquiries'     => $new_inq,
            'inquiries_12mo'    => $inq_12mo,
            'new_this_month'    => $new_this_month,
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
     * Properties owned by the user that currently carry ANY active boost
     * (Top of Page, Homepage Slider, or Featured) — the real "active upgrades".
     *
     * @return \WP_Post[]
     */
    private static function get_boosted_properties( \WP_User $user ): array {
        $q = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => [ 'publish', 'draft', 'pending' ],
            'author'         => $user->ID,
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => '_ovr_is_featured', 'value' => '1' ],
                [ 'key' => '_ovr_is_bumped',   'value' => '1' ],
                [ 'key' => '_ovr_in_slider',   'value' => '1' ],
            ],
        ] );

        // Keep only those with a non-expired boost still live.
        return array_values( array_filter(
            $q->posts ?: [],
            static fn( $p ) => ! empty( UpgradeActivator::active_products( $p->ID ) )
        ) );
    }


    /**
     * Get inquiries for the user (as landlord).
     *
     * @param int $limit -1 for all.
     */
    private static function get_inquiries( \WP_User $user, int $limit = -1 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_inquiries';
        // Only the last 12 months are shown (Feature 6).
        $sql   = "SELECT * FROM {$table} WHERE landlord_id = %d AND created_at >= ( NOW() - INTERVAL 12 MONTH ) ORDER BY created_at DESC";
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
