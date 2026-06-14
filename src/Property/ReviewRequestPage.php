<?php
/**
 * Review-request front controller (Feature 7).
 *
 * Wires three flows for the tokened review-request system:
 *   - Public page: ?ovr_review_request=TOKEN renders a themed review form.
 *   - Public submit: posts the review (no-JS) and marks the request complete.
 *   - Landlord actions: generate a link, and email it to the guest.
 *
 * @package OVR\Property
 * @since   2.0.0
 */

namespace OVR\Property;

use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewRequestPage {

    public function init(): void {
        add_filter( 'query_vars', [ $this, 'register_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_render_public_page' ] );

        add_action( 'admin_post_ovr_review_public_submit', [ $this, 'handle_public_submit' ] );
        add_action( 'admin_post_nopriv_ovr_review_public_submit', [ $this, 'handle_public_submit' ] );

        add_action( 'admin_post_ovr_review_request_create', [ $this, 'handle_create' ] );
        add_action( 'admin_post_ovr_review_request_send', [ $this, 'handle_send' ] );
    }

    public function register_query_var( array $vars ): array {
        $vars[] = ReviewRequest::QUERY_VAR;
        return $vars;
    }

    /**
     * Render the standalone public review form when a valid token is present.
     */
    public function maybe_render_public_page(): void {
        $token = (string) get_query_var( ReviewRequest::QUERY_VAR );
        if ( '' === $token && isset( $_GET[ ReviewRequest::QUERY_VAR ] ) ) {
            $token = sanitize_text_field( wp_unslash( $_GET[ ReviewRequest::QUERY_VAR ] ) );
        }
        if ( '' === $token ) {
            return;
        }

        $request = ReviewRequest::get_by_token( $token );
        $done    = isset( $_GET['ovr_review'] ) && 'thanks' === sanitize_key( wp_unslash( $_GET['ovr_review'] ) );

        get_header();
        \OVR\Core\TemplateLoader::render( 'property/review-request.php', [
            'request'    => $request,
            'token'      => $token,
            'done'       => $done,
            'invalid'    => ! $request,
            'completed'  => $request && 'completed' === $request['status'],
            'action_url' => admin_url( 'admin-post.php' ),
            'property'   => $request ? get_post( (int) $request['property_id'] ) : null,
        ] );
        get_footer();
        exit;
    }

    /**
     * Public review submission (no-JS). Validates the token, stores the review,
     * closes the request, and returns to a thank-you state.
     */
    public function handle_public_submit(): void {
        $token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        $request = ReviewRequest::get_by_token( $token );

        if ( ! $request ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        check_admin_referer( 'ovr_review_public_' . $token );

        $property_id = (int) $request['property_id'];
        $back        = ReviewRequest::public_url( $property_id, $token );

        $result = Reviews::submit( [
            'property_id' => $property_id,
            'rating'      => (int) ( $_POST['rating'] ?? 0 ),
            'guest_name'  => wp_unslash( $_POST['guest_name'] ?? $request['guest_name'] ),
            'guest_email' => wp_unslash( $_POST['guest_email'] ?? $request['guest_email'] ),
            'title'       => wp_unslash( $_POST['title'] ?? '' ),
            'body'        => wp_unslash( $_POST['body'] ?? '' ),
            'stay_date'   => wp_unslash( $_POST['stay_date'] ?? '' ),
        ] );

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_review', 'error', $back ) );
            exit;
        }

        ReviewRequest::mark_completed( $token, (int) $result );
        wp_safe_redirect( add_query_arg( 'ovr_review', 'thanks', $back ) );
        exit;
    }

    /**
     * Landlord: generate a review-request link for one of their properties.
     */
    public function handle_create(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_review_request_create' );

        $property_id = (int) ( $_POST['property_id'] ?? 0 );
        $booking_id  = (int) ( $_POST['booking_id'] ?? 0 );
        $owner_id    = get_current_user_id();

        $back = add_query_arg( 'tab', 'reviews', Pages::get_page_url( 'ovr_page_dashboard' ) );

        $guest_name  = sanitize_text_field( wp_unslash( $_POST['guest_name'] ?? '' ) );
        $guest_email = sanitize_email( wp_unslash( $_POST['guest_email'] ?? '' ) );

        // When a reservation is chosen, it is the source of truth: validate
        // ownership, then derive property + guest from the booking so the
        // request is genuinely tied to that stay.
        if ( $booking_id ) {
            $booking = \OVR\Booking\BookingRepository::get( $booking_id );
            if ( ! $booking || ( (int) $booking['owner_id'] !== $owner_id && ! current_user_can( 'manage_options' ) ) ) {
                wp_safe_redirect( add_query_arg( 'ovr_rr', 'error', $back ) );
                exit;
            }
            $property_id = (int) $booking['property_id'];
            $guest_name  = $guest_name ?: (string) $booking['guest_name'];
            $guest_email = $guest_email ?: (string) $booking['guest_email'];
        }

        $post = get_post( $property_id );

        // Only allow generating links for the landlord's own listing.
        if ( ! $post || 'ovr_property' !== $post->post_type
            || ( (int) $post->post_author !== $owner_id && ! current_user_can( 'manage_options' ) ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_rr', 'error', $back ) );
            exit;
        }

        ReviewRequest::create( $property_id, $owner_id, $guest_name, $guest_email, $booking_id );

        wp_safe_redirect( add_query_arg( 'ovr_rr', 'created', $back ) );
        exit;
    }

    /**
     * Landlord: email an existing request link to its guest.
     */
    public function handle_send(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( '403' );
        }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_review_request_send_' . $id );

        $back = add_query_arg( 'tab', 'reviews', Pages::get_page_url( 'ovr_page_dashboard' ) );
        $sent = ReviewRequest::send_email( $id );

        wp_safe_redirect( add_query_arg( 'ovr_rr', $sent ? 'sent' : 'send_failed', $back ) );
        exit;
    }
}
