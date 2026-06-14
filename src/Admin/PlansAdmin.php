<?php
/**
 * Subscription Plans Management — admin editor.
 *
 * Adds a "Pricing Plans" submenu under OVR Properties: a table of every billing
 * tier with an edit modal for name / status / price / duration / max listings /
 * features / marketing options, plus add and delete. Persists to the same
 * `ovr_subscription_plans` option that Plans::get_plans() reads from, so changes
 * apply instantly on the front-end pricing page.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Subscription\Plans;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PlansAdmin {

    public const PAGE_SLUG  = 'ovr-core-plans';
    private const OPTION    = 'ovr_subscription_plans';
    private const PROTECTED = [ 'base_subscriber' ];

    /** Storage key => human label for the duration/period field. */
    public const PERIODS = [
        'monthly'   => 'Monthly',
        'quarterly' => 'Quarterly',
        'annually'  => 'Annual',
        'one_time'  => 'One-time',
    ];

    public function init(): void {
        add_action( 'admin_menu',                 [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_save_plans',  [ $this, 'handle_save' ] );
        add_action( 'admin_post_ovr_delete_plan', [ $this, 'handle_delete' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Membership Plans', 'ovr-core' ),
            __( 'Membership Plans', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $plans = Plans::get_plans();
        uasort( $plans, fn( $a, $b ) => (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ) );

        $settings = (array) get_option( 'ovr_settings', [] );

        TemplateLoader::render( 'admin/plans.php', [
            'plans'         => $plans,
            'periods'       => self::PERIODS,
            'protected'     => self::PROTECTED,
            'currency'      => $settings['currency_symbol'] ?? '$',
            'save_url'      => admin_url( 'admin-post.php' ),
            'page_url'      => $this->page_url(),
            'next_sort'     => $this->next_sort_order( $plans ),
            'notice'        => $this->read_notice(),
        ] );
    }

    /**
     * Translate a ?msg=… result code into a human notice.
     *
     * @return array{type:string, text:string}|null
     */
    private function read_notice(): ?array {
        if ( empty( $_GET['msg'] ) ) {
            return null;
        }
        switch ( sanitize_key( wp_unslash( $_GET['msg'] ) ) ) {
            case 'saved':
                return [ 'type' => 'success', 'text' => __( 'Plan saved. Changes are live on the pricing page.', 'ovr-core' ) ];
            case 'deleted':
                return [ 'type' => 'success', 'text' => __( 'Plan deleted. Affected subscribers were moved to Base Subscriber.', 'ovr-core' ) ];
            case 'error':
                return [ 'type' => 'error', 'text' => __( 'Could not save the plan — a name is required.', 'ovr-core' ) ];
        }
        return null;
    }

    /**
     * Save handler — single-plan upsert (edit modal or new-plan modal).
     */
    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_save_plans_action', 'ovr_plans_nonce' );

        $raw = is_array( $_POST['plan'] ?? null ) ? wp_unslash( $_POST['plan'] ) : [];

        // Existing plan keeps its immutable slug; a new plan derives one from
        // the explicit slug field, falling back to a slug built from the name.
        $existing_slug = sanitize_key( $raw['existing_slug'] ?? '' );
        if ( $existing_slug ) {
            $slug = $existing_slug;
        } else {
            $slug = sanitize_key( $raw['slug'] ?? '' );
            if ( ! $slug && ! empty( $raw['name'] ) ) {
                $slug = sanitize_key( sanitize_title( (string) $raw['name'] ) );
            }
        }

        if ( ! $slug || empty( $raw['name'] ) ) {
            wp_safe_redirect( $this->page_url() . '&msg=error' );
            exit;
        }

        $plans          = (array) get_option( self::OPTION, [] );
        $plans[ $slug ] = $this->normalize_plan( $slug, (array) $raw );
        update_option( self::OPTION, $plans );

        wp_safe_redirect( $this->page_url() . '&msg=saved' );
        exit;
    }

    public function handle_delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        $slug = sanitize_key( $_GET['plan'] ?? '' );
        if ( ! $slug || in_array( $slug, self::PROTECTED, true ) ) {
            wp_safe_redirect( $this->page_url() );
            exit;
        }
        check_admin_referer( 'ovr_delete_plan_' . $slug );

        $plans = (array) get_option( self::OPTION, [] );
        unset( $plans[ $slug ] );
        update_option( self::OPTION, $plans );

        // Move subscribers on the deleted plan back to the base tier.
        $users = get_users( [
            'meta_key'   => 'ovr_subscription_plan',
            'meta_value' => $slug,
            'fields'     => [ 'ID' ],
        ] );
        foreach ( $users as $u ) {
            update_user_meta( (int) $u->ID, 'ovr_subscription_plan', 'base_subscriber' );
        }

        wp_safe_redirect( $this->page_url() . '&msg=deleted' );
        exit;
    }

    /**
     * Normalize a submitted plan payload into the canonical stored structure.
     * Accepts features either as an array of rows (modal) or a newline blob.
     */
    private function normalize_plan( string $slug, array $row ): array {
        $features_raw = $row['features'] ?? [];
        if ( is_string( $features_raw ) ) {
            $features_raw = explode( "\n", $features_raw );
        }
        $features = array_values( array_filter( array_map(
            static fn( $f ) => sanitize_text_field( trim( (string) $f ) ),
            (array) $features_raw
        ) ) );

        $period = sanitize_key( $row['period'] ?? 'monthly' );
        if ( ! isset( self::PERIODS[ $period ] ) ) {
            $period = 'monthly';
        }

        return [
            'name'          => sanitize_text_field( $row['name'] ?? '' ),
            'slug'          => $slug,
            'price'         => round( (float) ( $row['price'] ?? 0 ), 2 ),
            'period'        => $period,
            'max_listings'  => (int) ( $row['max_listings'] ?? 1 ),
            'is_popular'    => ! empty( $row['is_popular'] ),
            'is_active'     => ! empty( $row['is_active'] ),
            'description'   => sanitize_text_field( $row['description'] ?? '' ),
            'features'      => $features,
            'sort_order'    => (int) ( $row['sort_order'] ?? 99 ),
            'currency'      => 'USD',
            'support_promo' => ! empty( $row['support_promo'] ),
            'checkout_note' => sanitize_text_field( $row['checkout_note'] ?? '' ),
        ];
    }

    /**
     * Next free sort_order so a new plan lands at the end of the list.
     */
    private function next_sort_order( array $plans ): int {
        $max = 0;
        foreach ( $plans as $p ) {
            $max = max( $max, (int) ( $p['sort_order'] ?? 0 ) );
        }
        return $max + 1;
    }

    private function page_url(): string {
        return add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
        ], admin_url( 'edit.php' ) );
    }
}
