<?php
/**
 * Membership admin (Feature 10).
 *
 * A "Membership" dashboard that reuses the existing subscription system rather
 * than introducing a second plan model: membership *plans* are the existing
 * Pricing Plans (PlansAdmin), and member status comes from UserSubscription /
 * Lifecycle. This screen adds the platform overview (members per plan, expiring
 * soon, listings usage) plus the Loyalty programme settings.
 *
 * @package OVR\Admin
 * @since   2.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Subscription\Plans;
use OVR\Subscription\Lifecycle;
use OVR\Subscription\Loyalty;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class MembershipAdmin {

    public const PAGE_SLUG = 'ovr-core-membership';
    private const CAP       = 'ovr_manage_membership';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_save_loyalty', [ $this, 'handle_save_loyalty' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Membership', 'ovr-core' ),
            __( 'Membership', 'ovr-core' ),
            self::CAP,
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'You do not have permission to manage membership.', 'ovr-core' ) );
        }

        TemplateLoader::render( 'admin/membership.php', [
            'plans'           => $this->plan_breakdown(),
            'stats'           => $this->stats(),
            'loyalty'         => Loyalty::settings(),
            'loyalty_totals'  => Loyalty::totals(),
            'currency_symbol' => $this->currency_symbol(),
            'plans_url'       => add_query_arg( [ 'post_type' => 'ovr_property', 'page' => PlansAdmin::PAGE_SLUG ], admin_url( 'edit.php' ) ),
            'action_url'      => admin_url( 'admin-post.php' ),
            'loyalty_nonce'   => wp_create_nonce( 'ovr_save_loyalty' ),
            'notice'          => ! empty( $_GET['loyalty_saved'] ) ? [ 'type' => 'success', 'text' => __( 'Loyalty settings saved.', 'ovr-core' ) ] : null,
        ] );
    }

    /**
     * Per-plan member counts + plan facts.
     *
     * @return array<int, array<string, mixed>>
     */
    private function plan_breakdown(): array {
        global $wpdb;
        $plans = Plans::get_plans();
        uasort( $plans, static fn( $a, $b ) => (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ) );

        // One grouped query: member count per stored plan slug.
        $counts = [];
        $rows   = $wpdb->get_results( $wpdb->prepare(
            "SELECT meta_value AS slug, COUNT(*) AS n FROM {$wpdb->usermeta} WHERE meta_key = %s GROUP BY meta_value",
            \OVR\Subscription\UserSubscription::META_PLAN
        ), ARRAY_A );
        foreach ( (array) $rows as $r ) {
            $counts[ (string) $r['slug'] ] = (int) $r['n'];
        }

        $out = [];
        foreach ( $plans as $slug => $plan ) {
            $max = (int) ( $plan['max_listings'] ?? 0 );
            $out[] = [
                'slug'         => $slug,
                'name'         => (string) ( $plan['name'] ?? $slug ),
                'price'        => (float) ( $plan['price'] ?? 0 ),
                'period'       => (string) ( $plan['period'] ?? '' ),
                'max_listings' => $max,
                'members'      => $counts[ $slug ] ?? 0,
                'active'       => ! empty( $plan['active'] ) || ! isset( $plan['active'] ),
            ];
        }
        return $out;
    }

    /**
     * Headline membership stats.
     *
     * @return array<string, int>
     */
    private function stats(): array {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        $in30  = gmdate( 'Y-m-d', strtotime( '+30 days' ) );

        $paid_members = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value NOT IN ('base_subscriber','')",
            \OVR\Subscription\UserSubscription::META_PLAN
        ) );
        $expiring = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value BETWEEN %s AND %s",
            \OVR\Subscription\UserSubscription::META_EXPIRES,
            $today,
            $in30
        ) );

        return [
            'plans'    => count( array_filter( Plans::get_plans(), static fn( $p ) => ! empty( $p['active'] ) || ! isset( $p['active'] ) ) ),
            'members'  => $paid_members,
            'expiring' => $expiring,
        ];
    }

    /**
     * Persist Loyalty settings into the shared ovr_settings option.
     */
    public function handle_save_loyalty(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_save_loyalty' );

        $settings = (array) get_option( 'ovr_settings', [] );
        $settings['loyalty_enabled']      = ! empty( $_POST['loyalty_enabled'] );
        $settings['points_per_dollar']    = max( 0, (float) ( $_POST['points_per_dollar'] ?? 0 ) );
        $settings['renewal_bonus_points'] = max( 0, (int) ( $_POST['renewal_bonus_points'] ?? 0 ) );
        $settings['referral_credit']      = max( 0, (float) ( $_POST['referral_credit'] ?? 0 ) );
        $settings['upgrade_discount_pct'] = max( 0, min( 100, (float) ( $_POST['upgrade_discount_pct'] ?? 0 ) ) );
        update_option( 'ovr_settings', $settings );

        wp_safe_redirect( add_query_arg( [
            'post_type'     => 'ovr_property',
            'page'          => self::PAGE_SLUG,
            'loyalty_saved' => 1,
        ], admin_url( 'edit.php' ) ) );
        exit;
    }

    private function currency_symbol(): string {
        $s = (array) get_option( 'ovr_settings', [] );
        return (string) ( $s['currency_symbol'] ?? '$' );
    }
}
