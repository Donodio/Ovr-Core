<?php
/**
 * Users Management — admin list & actions.
 *
 * Adds a "Users" submenu under OVR Properties with a styled table
 * showing all WordPress users, their subscription plan, listing count,
 * account status, and quick action links.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Subscription\Plans;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class UsersAdmin {

    public const PAGE_SLUG  = 'ovr-core-users';
    public const PER_PAGE   = 20;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_user_toggle_status', [ $this, 'handle_toggle_status' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Users', 'ovr-core' ),
            __( 'Users', 'ovr-core' ),
            'ovr_manage_users',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            return;
        }

        $search  = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
        $role    = sanitize_key( wp_unslash( $_GET['role'] ?? '' ) );
        $sub     = sanitize_key( wp_unslash( $_GET['subscription'] ?? '' ) );
        $status  = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
        $paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $orderby = sanitize_key( wp_unslash( $_GET['orderby'] ?? 'registered' ) );
        $order   = strtoupper( sanitize_key( wp_unslash( $_GET['order'] ?? 'DESC' ) ) );
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        $args = [
            'fields'     => 'all',
            'number'     => self::PER_PAGE,
            'paged'      => $paged,
            'orderby'    => $orderby,
            'order'      => $order,
        ];

        if ( $search ) {
            // Partial match across name + email (Phase 11).
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
        }
        if ( $role && in_array( $role, [ 'ovr_landlord', 'administrator', 'subscriber' ], true ) ) {
            $args['role'] = $role;
        }

        // Subscription type + account status filters (Phase 11), combinable.
        $meta_query = [];
        if ( $sub ) {
            $meta_query[] = [ 'key' => 'ovr_subscription_plan', 'value' => $sub ];
        }
        if ( 'inactive' === $status ) {
            $meta_query[] = [ 'key' => 'ovr_account_status', 'value' => 'inactive' ];
        } elseif ( 'active' === $status ) {
            // Active = explicitly active OR no status set yet (default).
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => 'ovr_account_status', 'value' => 'active' ],
                [ 'key' => 'ovr_account_status', 'compare' => 'NOT EXISTS' ],
            ];
        }
        if ( $meta_query ) {
            $args['meta_query'] = $meta_query;
        }

        $user_query = new \WP_User_Query( $args );
        $users      = $user_query->get_results();
        $total      = $user_query->get_total();
        $max_pages  = (int) ceil( $total / self::PER_PAGE );

        $plans = Plans::get_plans();

        TemplateLoader::render( 'admin/users.php', [
            'users'       => $users,
            'plans'       => $plans,
            'stats'       => $this->get_stats(),
            'search'      => $search,
            'role'        => $role,
            'subscription'=> $sub,
            'status'      => $status,
            'paged'       => $paged,
            'max_pages'   => $max_pages,
            'total'       => $total,
            'orderby'     => $orderby,
            'order'       => $order,
            'page_url'    => $this->page_url(),
            'notice'      => $this->read_notice(),
            'toggle_url'  => admin_url( 'admin-post.php' ),
            'csv_url'     => add_query_arg( 'export_csv', '1', $this->page_url() ),
        ] );
    }

    /**
     * Compute quick stats for the stat cards.
     *
     * @return array<string, int|string>
     */
    private function get_stats(): array {
        global $wpdb;

        $users_data = count_users();

        $active = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
                'ovr_account_status',
                'active'
            )
        );

        $managers = $users_data['avail_roles']['ovr_landlord'] ?? 0;

        $pending = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                'ovr_property',
                'pending'
            )
        );

        return [
            'total_users'    => (int) ( $users_data['total_users'] ?? 0 ),
            'active_subs'    => max( 0, (int) $active ),
            'property_managers' => (int) $managers,
            'pending_approvals' => $pending,
        ];
    }

    /**
     * Toggle a user's account status between active and inactive.
     */
    public function handle_toggle_status(): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_user_toggle_status' );

        $user_id = (int) ( $_GET['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_safe_redirect( $this->page_url() );
            exit;
        }

        $current = get_user_meta( $user_id, 'ovr_account_status', true );
        $new     = 'inactive' === $current ? 'active' : 'inactive';
        update_user_meta( $user_id, 'ovr_account_status', $new );

        wp_safe_redirect( $this->page_url() . '&msg=status_updated' );
        exit;
    }

    private function read_notice(): ?array {
        if ( empty( $_GET['msg'] ) ) {
            return null;
        }
        switch ( sanitize_key( wp_unslash( $_GET['msg'] ) ) ) {
            case 'status_updated':
                return [
                    'type' => 'success',
                    'text' => __( 'User status updated.', 'ovr-core' ),
                ];
        }
        return null;
    }

    private function page_url(): string {
        return add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
        ], admin_url( 'edit.php' ) );
    }
}
