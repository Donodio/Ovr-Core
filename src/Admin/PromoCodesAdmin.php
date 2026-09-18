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

        $id   = absint( $_POST['promo_id'] ?? 0 );
        $code = PromoCode::normalize_code( (string) ( $_POST['code'] ?? '' ) );

        $type = sanitize_key( $_POST['discount_type'] ?? 'percentage' );
        if ( ! in_array( $type, [ 'percentage', 'fixed' ], true ) ) {
            $type = 'percentage';
        }
        $value = round( (float) ( $_POST['discount_value'] ?? 0 ), 2 );
        if ( $value < 0 ) {
            wp_safe_redirect( $this->page_url() . '&msg=error' );
            exit;
        }

        // Explicit promotional subscription price (Mark's model). Blank = not
        // configured (NULL); 0 is a legitimate free promotional offer.
        $promo_price_raw = trim( (string) ( $_POST['promo_price'] ?? '' ) );
        $promo_price     = null;
        if ( '' !== $promo_price_raw ) {
            if ( ! is_numeric( $promo_price_raw ) || (float) $promo_price_raw < 0 ) {
                wp_safe_redirect( $this->page_url() . '&msg=error' );
                exit;
            }
            $promo_price = round( (float) $promo_price_raw, 2 );
        }

        // Promotional subscription duration in days. Positive whole number only.
        $duration_raw  = trim( (string) ( $_POST['duration_days'] ?? '' ) );
        $duration_days = null;
        if ( '' !== $duration_raw ) {
            if ( ! ctype_digit( $duration_raw ) || (int) $duration_raw <= 0 ) {
                wp_safe_redirect( $this->page_url() . '&msg=error' );
                exit;
            }
            $duration_days = (int) $duration_raw;
        }

        $max_uses = '' !== trim( (string) ( $_POST['max_uses'] ?? '' ) ) ? absint( $_POST['max_uses'] ) : null;

        $valid_from  = sanitize_text_field( $_POST['valid_from'] ?? '' );
        $valid_until = sanitize_text_field( $_POST['valid_until'] ?? '' );
        foreach ( [ $valid_from, $valid_until ] as $d ) {
            if ( '' !== $d && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
                wp_safe_redirect( $this->page_url() . '&msg=error' );
                exit;
            }
        }
        if ( '' !== $valid_from && '' !== $valid_until && $valid_until < $valid_from ) {
            wp_safe_redirect( $this->page_url() . '&msg=error' );
            exit;
        }

        // Applicable plans must reference real plans.
        $known      = array_keys( Plans::get_plans() );
        $applicable = isset( $_POST['applicable_plans'] ) && is_array( $_POST['applicable_plans'] )
            ? array_map( 'sanitize_key', $_POST['applicable_plans'] )
            : [];
        foreach ( $applicable as $slug ) {
            if ( ! in_array( $slug, $known, true ) ) {
                wp_safe_redirect( $this->page_url() . '&msg=error' );
                exit;
            }
        }
        $applicable_json = ! empty( $applicable ) ? wp_json_encode( array_values( $applicable ) ) : null;

        // A promo must change something: an explicit price, a legacy discount,
        // or a duration.
        if ( '' === $code || ( null === $promo_price && 0.0 === $value && null === $duration_days ) ) {
            wp_safe_redirect( $this->page_url() . '&msg=error' );
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_promo_codes';

        $data = [
            'code'           => $code,
            'discount_type'  => $type,
            'discount_value' => $value,
        ];
        $format = [ '%s', '%s', '%f' ];

        // Always present so the field can be cleared back to NULL.
        $data['promo_price'] = $promo_price;
        $format[]            = '%s';

        if ( null !== $duration_days ) {
            $data['duration_days'] = $duration_days;
            $format[] = '%d';
        }
        if ( null !== $max_uses ) {
            $data['max_uses'] = $max_uses;
            $format[] = '%d';
        }
        $data['valid_from'] = $valid_from ?: null;
        $data['valid_until'] = $valid_until ?: null;
        $data['applicable_plans'] = $applicable_json;
        $data['is_active'] = ! empty( $_POST['is_active'] ) ? 1 : 0;
        $format[] = '%s';
        $format[] = '%s';
        $format[] = '%s';
        $format[] = '%d';

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id ], $format, [ '%d' ] );
        } else {
            // Reject duplicate codes at the storage layer (case-insensitive).
            $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE UPPER(code) = %s", $code ) );
            if ( $exists > 0 ) {
                wp_safe_redirect( $this->page_url() . '&msg=error' );
                exit;
            }
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
