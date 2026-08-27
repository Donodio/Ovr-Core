<?php
/**
 * Promo Codes Admin — attached to subscription plans.
 *
 * Manages wp_ovr_promo_codes. Each code stores which subscription plan slugs
 * it applies to via applicable_plans (JSON array). Codes are validated at
 * checkout via PromoCode::validate().
 *
 * @package OVR\Admin
 * @since   1.2.3
 */

namespace OVR\Admin;

use OVR\Payment\PromoCode;
use OVR\Subscription\Plans;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PromoCodesAdmin {

    public const PAGE_SLUG = 'ovr-core-promo-codes';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_save_promo',   [ $this, 'handle_save' ] );
        add_action( 'admin_post_ovr_delete_promo', [ $this, 'handle_delete' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Promo Codes', 'ovr-core' ),
            __( 'Promo Codes', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_promo_codes';
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A ) ?: [];
        $plans = Plans::get_plans();
        $plan_options = [];
        foreach ( $plans as $slug => $p ) {
            $plan_options[ $slug ] = $p['name'] ?? $slug;
        }
        $editing = null;
        if ( ! empty( $_GET['edit'] ) ) {
            $edit_id = absint( $_GET['edit'] );
            $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ), ARRAY_A );
        }
        $notice = null;
        if ( ! empty( $_GET['msg'] ) ) {
            switch ( sanitize_key( $_GET['msg'] ) ) {
                case 'saved':   $notice = [ 'type' => 'success', 'text' => __( 'Promo code saved.', 'ovr-core' ) ]; break;
                case 'deleted': $notice = [ 'type' => 'success', 'text' => __( 'Promo code deleted.', 'ovr-core' ) ]; break;
                case 'error':   $notice = [ 'type' => 'error', 'text' => __( 'Could not save promo code — code and value are required.', 'ovr-core' ) ]; break;
            }
        }
        $page_url = $this->page_url();
        include OVR_PLUGIN_DIR . 'templates/admin/promo-codes.php';
    }

    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_save_promo_action', 'ovr_promo_nonce' );

        $id    = absint( $_POST['promo_id'] ?? 0 );
        $code  = strtoupper( sanitize_text_field( $_POST['code'] ?? '' ) );
        $type  = sanitize_key( $_POST['discount_type'] ?? 'percentage' );
        if ( ! in_array( $type, [ 'percentage', 'fixed' ], true ) ) {
            $type = 'percentage';
        }
        $value = round( (float) ( $_POST['discount_value'] ?? 0 ), 2 );
        $max_uses = '' !== trim( (string) ( $_POST['max_uses'] ?? '' ) ) ? absint( $_POST['max_uses'] ) : null;
        $valid_from  = sanitize_text_field( $_POST['valid_from'] ?? '' );
        $valid_until = sanitize_text_field( $_POST['valid_until'] ?? '' );
        $is_active = ! empty( $_POST['is_active'] ) ? 1 : 0;

        $applicable = isset( $_POST['applicable_plans'] ) && is_array( $_POST['applicable_plans'] )
            ? array_map( 'sanitize_key', $_POST['applicable_plans'] )
            : [];
        $applicable_json = ! empty( $applicable ) ? wp_json_encode( array_values( $applicable ) ) : null;

        if ( '' === $code || 0.0 === $value ) {
            wp_safe_redirect( $this->page_url() . '&msg=error' );
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_promo_codes';

        $data = [
            'code'             => $code,
            'discount_type'    => $type,
            'discount_value'   => $value,
            'max_uses'         => $max_uses,
            'valid_from'       => $valid_from ?: null,
            'valid_until'      => $valid_until ?: null,
            'applicable_plans' => $applicable_json,
            'is_active'        => $is_active,
        ];
        $format = [ '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%d' ];
        if ( null === $max_uses ) {
            unset( $data['max_uses'] );
            array_splice( $format, 3, 1 );
        }

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id ], $format, [ '%d' ] );
        } else {
            $wpdb->insert( $table, $data, $format );
        }

        wp_safe_redirect( $this->page_url() . '&msg=saved' );
        exit;
    }

    public function handle_delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        $id = absint( $_GET['promo'] ?? 0 );
        check_admin_referer( 'ovr_delete_promo_' . $id );
        if ( ! $id ) {
            wp_safe_redirect( $this->page_url() );
            exit;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_promo_codes';
        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        wp_safe_redirect( $this->page_url() . '&msg=deleted' );
        exit;
    }

    private function page_url(): string {
        return add_query_arg( [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG ], admin_url( 'edit.php' ) );
    }
}
