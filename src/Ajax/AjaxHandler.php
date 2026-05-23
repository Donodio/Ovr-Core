<?php
/**
 * AJAX Handler.
 *
 * @package OVR\Ajax
 * @since   1.0.0
 */

namespace OVR\Ajax;

use OVR\Property\PropertyQuery;
use OVR\Property\PropertyCard;
use OVR\Property\IcalSync;
use OVR\Search\SearchHandler;
use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AjaxHandler {

    public function init(): void {
        // Public AJAX (no login required).
        add_action( 'wp_ajax_ovr_search_properties', [ $this, 'search_properties' ] );
        add_action( 'wp_ajax_nopriv_ovr_search_properties', [ $this, 'search_properties' ] );

        add_action( 'wp_ajax_ovr_load_more', [ $this, 'load_more' ] );
        add_action( 'wp_ajax_nopriv_ovr_load_more', [ $this, 'load_more' ] );

        // Inquiries: AJAX path + non-JS admin-post fallback.
        add_action( 'wp_ajax_ovr_submit_inquiry', [ $this, 'submit_inquiry' ] );
        add_action( 'wp_ajax_nopriv_ovr_submit_inquiry', [ $this, 'submit_inquiry' ] );
        add_action( 'admin_post_ovr_submit_inquiry', [ $this, 'submit_inquiry_post' ] );
        add_action( 'wp_ajax_nopriv_ovr_submit_inquiry', [ $this, 'submit_inquiry_post' ] );

        // Reviews.
        add_action( 'wp_ajax_ovr_submit_review', [ $this, 'submit_review' ] );
        add_action( 'wp_ajax_nopriv_ovr_submit_review', [ $this, 'submit_review' ] );

        add_action( 'wp_ajax_ovr_apply_promo', [ $this, 'apply_promo' ] );

        // Admin: manual iCal sync trigger.
        add_action( 'wp_ajax_ovr_ical_sync', [ $this, 'ical_sync' ] );

        // Frontend: dashboard profile update.
        add_action( 'admin_post_ovr_update_profile',  [ $this, 'update_profile' ] );
        add_action( 'admin_post_ovr_change_password', [ $this, 'change_password' ] );
        add_action( 'admin_post_ovr_wallet_topup',    [ $this, 'wallet_topup' ] );
    }

    /**
     * Change password from dashboard.
     */
    public function change_password(): void {
        $referer = wp_get_referer() ?: home_url( '/' );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( $referer );
            exit;
        }

        if ( ! isset( $_POST['ovr_password_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_password_nonce'] ) ), 'ovr_password_action' ) ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'nonce_failed' ], $referer ) );
            exit;
        }

        $user    = wp_get_current_user();
        $current = (string) wp_unslash( $_POST['current_password']  ?? '' );
        $new     = (string) wp_unslash( $_POST['new_password']      ?? '' );
        $confirm = (string) wp_unslash( $_POST['confirm_password']  ?? '' );

        if ( strlen( $new ) < 8 ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'weak' ], $referer ) );
            exit;
        }
        if ( $new !== $confirm ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'mismatch' ], $referer ) );
            exit;
        }
        if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'wrong_current' ], $referer ) );
            exit;
        }

        wp_set_password( $new, $user->ID );

        // wp_set_password destroys the session — re-auth so the user stays logged in.
        wp_set_auth_cookie( $user->ID, true );
        wp_set_current_user( $user->ID );

        wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'success' ], $referer ) );
        exit;
    }

    /**
     * Wallet topup — create a pending payment row + redirect to gateway.
     */
    public function wallet_topup(): void {
        $referer = wp_get_referer() ?: home_url( '/' );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( $referer );
            exit;
        }
        if ( ! isset( $_POST['ovr_topup_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_topup_nonce'] ) ), 'ovr_topup_action' ) ) {
            wp_safe_redirect( add_query_arg( 'tab', 'balance', $referer ) );
            exit;
        }

        $amount  = (float) ( $_POST['amount'] ?? 0 );
        $gateway = sanitize_key( $_POST['gateway'] ?? 'stripe' );

        if ( $amount <= 0 ) {
            wp_safe_redirect( add_query_arg( 'tab', 'balance', $referer ) );
            exit;
        }

        // Record a pending topup row in wp_ovr_payments. The gateway webhook
        // (Phase 2) will flip status to 'completed' and Wallet listens for
        // ovr_payment_completed to credit the balance.
        global $wpdb;
        $table   = $wpdb->prefix . 'ovr_payments';
        $user_id = get_current_user_id();
        $wpdb->insert( $table, [
            'user_id'        => $user_id,
            'payment_type'   => 'topup',
            'amount'         => $amount,
            'currency'       => 'USD',
            'gateway'        => $gateway,
            'transaction_id' => '',
            'status'         => 'pending',
            'meta_data'      => wp_json_encode( [ 'kind' => 'wallet_topup' ] ),
        ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

        wp_safe_redirect( add_query_arg( [ 'tab' => 'balance', 'topup_started' => '1' ], $referer ) );
        exit;
    }

    /**
     * Profile update from the dashboard Profile tab (non-AJAX).
     */
    public function update_profile(): void {
        $referer = wp_get_referer() ?: home_url( '/' );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( $referer );
            exit;
        }
        if ( ! isset( $_POST['ovr_profile_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_profile_nonce'] ) ), 'ovr_profile_action' ) ) {
            wp_safe_redirect( $referer );
            exit;
        }

        $user_id = get_current_user_id();
        $first   = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $last    = sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) );
        $email   = sanitize_email(     wp_unslash( $_POST['email']      ?? '' ) );
        $phone   = sanitize_text_field( wp_unslash( $_POST['phone']      ?? '' ) );

        $update = [ 'ID' => $user_id ];
        if ( $first ) $update['first_name'] = $first;
        if ( $last )  $update['last_name']  = $last;
        if ( $email && is_email( $email ) ) $update['user_email'] = $email;

        wp_update_user( $update );

        if ( $phone ) {
            update_user_meta( $user_id, 'ovr_phone', $phone );
        } else {
            delete_user_meta( $user_id, 'ovr_phone' );
        }

        wp_safe_redirect( add_query_arg( [ 'tab' => 'profile', 'profile_saved' => '1' ], $referer ) );
        exit;
    }

    /**
     * Manual iCal sync trigger from the property edit screen.
     */
    public function ical_sync(): void {
        if ( ! check_ajax_referer( 'ovr_admin_nonce', 'nonce', false ) &&
             ! check_ajax_referer( 'ovr_public_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Missing property ID.', 'ovr-core' ) ], 400 );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You cannot edit this property.', 'ovr-core' ) ], 403 );
        }

        $sync   = new IcalSync();
        $result = $sync->sync_property( $post_id );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ], 200 );
        }

        wp_send_json_success( [
            'message'  => $result['message'],
            'imported' => $result['imported'],
        ] );
    }

    /**
     * AJAX property search.
     */
    public function search_properties(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        $filters = SearchHandler::get_filters_from_request();
        $query   = PropertyQuery::query( $filters );

        $html = '';
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $html .= PropertyCard::render_grid( get_the_ID() );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( [
            'html'      => $html,
            'total'     => $query->found_posts,
            'max_pages' => $query->max_num_pages,
        ] );
    }

    /**
     * Load more properties (pagination).
     */
    public function load_more(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        $paged = absint( $_POST['page'] ?? 2 );
        $filters = SearchHandler::get_filters_from_request();
        $filters['paged'] = $paged;

        $query = PropertyQuery::query( $filters );

        $html = '';
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $html .= PropertyCard::render_grid( get_the_ID() );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( [
            'html'      => $html,
            'has_more'  => $paged < $query->max_num_pages,
        ] );
    }

    /**
     * Submit property inquiry (AJAX path).
     */
    public function submit_inquiry(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        $property_id  = absint( $_POST['property_id'] ?? 0 );
        $booking_mode = get_post_meta( $property_id, '_ovr_booking_mode', true );

        $result = $this->process_inquiry( $_POST );

        if ( is_wp_error( $result ) ) {
            $code = (int) ( $result->get_error_data() ?: 400 );
            wp_send_json_error( [ 'message' => $result->get_error_message() ], $code );
        }

        if ( 'direct' === $booking_mode ) {
            $inquiry_id = $result;
            $base_price = (float) get_post_meta( $property_id, '_ovr_base_price', true );
            $checkin    = sanitize_text_field( wp_unslash( $_POST['checkin_date'] ?? '' ) );
            $checkout   = sanitize_text_field( wp_unslash( $_POST['checkout_date'] ?? '' ) );

            $nights = 1;
            if ( $checkin && $checkout ) {
                $diff = strtotime( $checkout ) - strtotime( $checkin );
                if ( $diff > 0 ) {
                    $nights = floor( $diff / DAY_IN_SECONDS );
                }
            }

            $total_amount = $base_price * $nights;
            if ( $total_amount <= 0 ) {
                $total_amount = $base_price;
            }

            $paypal = new \OVR\Payment\PayPalGateway();
            $checkout_args = [
                'user_id'      => get_current_user_id() ?: 0,
                'plan_slug'    => 'booking_' . $inquiry_id,
                'amount'       => $total_amount,
                'payment_type' => 'booking',
                'currency'     => get_option( 'ovr_settings' )['currency'] ?? 'USD',
                'return_url'   => home_url( '/' ),
                'cancel_url'   => home_url( '/' ),
            ];

            $checkout_res = $paypal->start_checkout( $checkout_args );

            if ( ! empty( $checkout_res['redirect_url'] ) && $paypal->is_configured() ) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'ovr_inquiries',
                    [ 'status' => 'pending_payment' ],
                    [ 'id' => $inquiry_id ],
                    [ '%s' ],
                    [ '%d' ]
                );

                wp_send_json_success( [
                    'redirect_url' => $checkout_res['redirect_url'],
                    'message'      => __( 'Redirecting to PayPal…', 'ovr-core' ),
                ] );
            }
        }

        wp_send_json_success( [
            'message'     => __( 'Your inquiry has been sent successfully!', 'ovr-core' ),
            'inquiry_id'  => $result,
        ] );
    }

    /**
     * Submit property review (AJAX path).
     */
    public function submit_review(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        if ( ! class_exists( '\OVR\Property\Reviews' ) ) {
            wp_send_json_error( [ 'message' => __( 'Reviews module not found.', 'ovr-core' ) ], 500 );
        }

        $result = \OVR\Property\Reviews::submit( wp_unslash( $_POST ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
        }

        wp_send_json_success( [
            'message'   => __( 'Thank you! Your review has been submitted.', 'ovr-core' ),
            'review_id' => $result,
        ] );
    }

    public function submit_inquiry_post(): void {
        $referer = wp_get_referer() ?: home_url( '/' );

        if ( ! isset( $_POST['ovr_inquiry_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_inquiry_nonce'] ) ), 'ovr_inquiry_action' ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_inquiry', 'nonce_failed', $referer ) . '#ovr-inquiry' );
            exit;
        }

        $property_id  = absint( $_POST['property_id'] ?? 0 );
        $booking_mode = get_post_meta( $property_id, '_ovr_booking_mode', true );

        $result = $this->process_inquiry( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_inquiry', 'error', $referer ) . '#ovr-inquiry' );
            exit;
        }

        if ( 'direct' === $booking_mode ) {
            $inquiry_id = $result;
            $base_price = (float) get_post_meta( $property_id, '_ovr_base_price', true );
            $checkin    = sanitize_text_field( wp_unslash( $_POST['checkin_date'] ?? '' ) );
            $checkout   = sanitize_text_field( wp_unslash( $_POST['checkout_date'] ?? '' ) );

            $nights = 1;
            if ( $checkin && $checkout ) {
                $diff = strtotime( $checkout ) - strtotime( $checkin );
                if ( $diff > 0 ) {
                    $nights = floor( $diff / DAY_IN_SECONDS );
                }
            }

            $total_amount = $base_price * $nights;
            if ( $total_amount <= 0 ) {
                $total_amount = $base_price; // Fallback
            }

            $paypal = new \OVR\Payment\PayPalGateway();
            $checkout_args = [
                'user_id'      => get_current_user_id() ?: 0,
                'plan_slug'    => 'booking_' . $inquiry_id,
                'amount'       => $total_amount,
                'payment_type' => 'booking',
                'currency'     => get_option( 'ovr_settings' )['currency'] ?? 'USD',
                'return_url'   => add_query_arg( 'ovr_inquiry', 'sent', $referer ),
                'cancel_url'   => add_query_arg( 'ovr_inquiry', 'canceled', $referer ),
            ];

            $checkout_res = $paypal->start_checkout( $checkout_args );

            if ( ! empty( $checkout_res['redirect_url'] ) && $paypal->is_configured() ) {
                // Update inquiry status to pending_payment
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'ovr_inquiries',
                    [ 'status' => 'pending_payment' ],
                    [ 'id' => $inquiry_id ],
                    [ '%s' ],
                    [ '%d' ]
                );

                wp_safe_redirect( $checkout_res['redirect_url'] );
                exit;
            }
        }

        wp_safe_redirect( add_query_arg( 'ovr_inquiry', 'sent', $referer ) . '#ovr-inquiry' );
        exit;
    }

    /**
     * Shared inquiry processing — validates input and inserts into ovr_inquiries.
     *
     * Accepts both AJAX field names and form field names:
     *   checkin / checkin_date, checkout / checkout_date.
     *
     * @param array $data The $_POST or AJAX payload.
     * @return int|\WP_Error Inserted inquiry ID, or WP_Error with HTTP status code.
     */
    private function process_inquiry( array $data ) {
        $property_id = absint( $data['property_id'] ?? 0 );
        $name        = sanitize_text_field( wp_unslash( $data['guest_name']  ?? '' ) );
        $email       = sanitize_email(     wp_unslash( $data['guest_email'] ?? '' ) );
        $phone       = sanitize_text_field( wp_unslash( $data['guest_phone'] ?? '' ) );
        $message     = sanitize_textarea_field( wp_unslash( $data['message'] ?? '' ) );

        // Accept either field name; prefer the longer (canonical) form.
        $checkin_raw  = $data['checkin_date']  ?? $data['checkin']  ?? '';
        $checkout_raw = $data['checkout_date'] ?? $data['checkout'] ?? '';
        $checkin      = sanitize_text_field( wp_unslash( $checkin_raw ) );
        $checkout     = sanitize_text_field( wp_unslash( $checkout_raw ) );
        $guests       = absint( $data['guests'] ?? 0 );

        // Validate property post.
        $post = get_post( $property_id );
        if ( ! $post || 'ovr_property' !== $post->post_type ) {
            return new \WP_Error( 'invalid_property', __( 'Invalid property.', 'ovr-core' ), 400 );
        }

        $booking_mode = get_post_meta( $property_id, '_ovr_booking_mode', true );

        // Validate required fields.
        if ( ! $property_id ) {
            return new \WP_Error( 'invalid_property', __( 'Property is required.', 'ovr-core' ), 400 );
        }
        if ( empty( $name ) || empty( $email ) ) {
            return new \WP_Error( 'missing_fields', __( 'Please fill in all required fields.', 'ovr-core' ), 400 );
        }
        if ( 'direct' !== $booking_mode && empty( $message ) ) {
            return new \WP_Error( 'missing_fields', __( 'Message is required for inquiries.', 'ovr-core' ), 400 );
        }
        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'bad_email', __( 'Please enter a valid email address.', 'ovr-core' ), 400 );
        }

        // Validate date order if both supplied.
        if ( $checkin && $checkout && strtotime( $checkin ) >= strtotime( $checkout ) ) {
            return new \WP_Error( 'bad_dates', __( 'Checkout must be after check-in.', 'ovr-core' ), 400 );
        }

        // Honeypot spam guard — silent success if filled.
        if ( ! empty( $data['ovr_hp'] ) ) {
            return 0; // Pretend success without inserting.
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_inquiries';

        $inserted = $wpdb->insert( $table, [
            'property_id'  => $property_id,
            'landlord_id'  => (int) $post->post_author,
            'guest_name'   => $name,
            'guest_email'  => $email,
            'guest_phone'  => $phone,
            'message'      => $message,
            'checkin_date' => $checkin  ?: null,
            'checkout_date'=> $checkout ?: null,
            'guests'       => $guests   ?: null,
            'status'       => 'new',
        ], [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ] );

        if ( false === $inserted ) {
            return new \WP_Error( 'db_error', __( 'Failed to submit inquiry. Please try again.', 'ovr-core' ), 500 );
        }

        $inquiry_id = (int) $wpdb->insert_id;

        do_action( 'ovr_inquiry_submitted', $inquiry_id, $property_id );

        return $inquiry_id;
    }

    /**
     * Apply promo code.
     */
    public function apply_promo(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Please log in first.', 'ovr-core' ) ], 401 );
        }

        $code = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
        if ( empty( $code ) ) {
            wp_send_json_error( [ 'message' => __( 'Please enter a promo code.', 'ovr-core' ) ], 400 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_promo_codes';

        $promo = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s AND is_active = 1", $code ),
            ARRAY_A
        );

        if ( ! $promo ) {
            wp_send_json_error( [ 'message' => __( 'Invalid or expired promo code.', 'ovr-core' ) ], 404 );
        }

        $now = current_time( 'Y-m-d' );
        if ( ( $promo['valid_from'] && $now < $promo['valid_from'] ) || ( $promo['valid_until'] && $now > $promo['valid_until'] ) ) {
            wp_send_json_error( [ 'message' => __( 'This promo code is not currently valid.', 'ovr-core' ) ], 400 );
        }

        if ( $promo['max_uses'] && $promo['current_uses'] >= $promo['max_uses'] ) {
            wp_send_json_error( [ 'message' => __( 'This promo code has reached its usage limit.', 'ovr-core' ) ], 400 );
        }

        wp_send_json_success( [
            'discount_type'  => $promo['discount_type'],
            'discount_value' => (float) $promo['discount_value'],
            'message'        => sprintf(
                __( 'Promo code applied! %s discount.', 'ovr-core' ),
                'percentage' === $promo['discount_type']
                    ? $promo['discount_value'] . '%'
                    : '$' . number_format( $promo['discount_value'], 2 )
            ),
        ] );
    }
}
