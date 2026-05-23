<?php
/**
 * Checkout Handler.
 *
 * Wires the "Select Plan" buttons on /pricing/ to a payment gateway.
 *
 *   POST /wp-admin/admin-post.php?action=ovr_start_checkout
 *
 * Phase 1: Stripe stub records a pending payment row and redirects back
 * with a query string. Phase 2 will swap in real Checkout Session URLs.
 *
 * Also surfaces a one-time admin notice on plan-management screens letting
 * admins know they need to configure Stripe API keys.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

use OVR\Subscription\Plans;
use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CheckoutHandler {

    /** @var PaymentGateway[] */
    private array $gateways = [];

    public function init(): void {
        // Register all available gateways.
        $this->gateways['stripe']        = new StripeGateway();
        $this->gateways['paypal']        = new PayPalGateway();
        $this->gateways['authorize_net'] = new AuthorizeNetGateway();
        $this->gateways['wallet']        = new WalletGateway();

        add_action( 'admin_post_ovr_start_checkout',        [ $this, 'handle_start' ] );
        add_action( 'admin_post_nopriv_ovr_start_checkout', [ $this, 'handle_start_anon' ] );
        add_action( 'wp_ajax_ovr_start_checkout',           [ $this, 'handle_ajax' ] );

        add_action( 'admin_notices', [ $this, 'maybe_show_config_notice' ] );

        // Surface the "checkout pending" message on the pricing page.
        add_action( 'wp_footer', [ $this, 'maybe_show_checkout_toast' ] );
    }

    /**
     * Resolve a gateway by slug. Empty string falls back to the active default.
     */
    public function gateway( string $slug = '' ): PaymentGateway {
        if ( ! $slug ) {
            $slug = (string) apply_filters( 'ovr_active_gateway', 'stripe' );
        }
        return $this->gateways[ $slug ] ?? $this->gateways['stripe'];
    }

    /**
     * All registered gateways (slug => label).
     *
     * @return array<string,string>
     */
    public function get_gateway_choices(): array {
        $out = [];
        foreach ( $this->gateways as $slug => $g ) {
            $out[ $slug ] = $g->get_label();
        }
        return $out;
    }

    /**
     * Redirect anonymous users to login with a return URL.
     */
    public function handle_start_anon(): void {
        $login = Pages::get_page_url( 'ovr_page_login' );
        $back  = wp_get_referer() ?: Pages::get_page_url( 'ovr_page_pricing' );
        wp_safe_redirect( add_query_arg( 'redirect_to', urlencode( $back ), $login ) );
        exit;
    }

    /**
     * Form-post entry point.
     */
    public function handle_start(): void {
        if ( ! is_user_logged_in() ) {
            $this->handle_start_anon();
            return;
        }

        $referer = wp_get_referer() ?: Pages::get_page_url( 'ovr_page_pricing' );

        if ( ! isset( $_POST['ovr_checkout_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_checkout_nonce'] ) ), 'ovr_checkout_action' ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'nonce_failed', $referer ) );
            exit;
        }

        $plan_slug = sanitize_key( $_POST['plan'] ?? '' );
        $plan      = Plans::get_plan( $plan_slug );

        if ( ! $plan ) {
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'invalid_plan', $referer ) );
            exit;
        }

        // Free plan: no payment, but still record a $0 "completed" payment row
        // and fire ovr_payment_completed so Lifecycle restores listings + sets
        // editing_enabled. Brief: "Secondary status controlling editing
        // permissions, activated upon successful subscription payment".
        $price = (float) ( $plan['price'] ?? 0 );
        if ( 0.0 === $price ) {
            $user_id = get_current_user_id();

            global $wpdb;
            $wpdb->insert( $wpdb->prefix . 'ovr_payments', [
                'user_id'        => $user_id,
                'payment_type'   => 'subscription',
                'amount'         => 0.00,
                'currency'       => $plan['currency'] ?? 'USD',
                'gateway'        => 'free',
                'transaction_id' => 'free_' . wp_generate_uuid4(),
                'status'         => 'completed',
                'meta_data'      => wp_json_encode( [ 'plan_slug' => $plan_slug ] ),
            ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

            do_action( 'ovr_payment_completed', $user_id, [
                'payment_id' => (int) $wpdb->insert_id,
                'plan_slug'  => $plan_slug,
                'amount'     => 0.0,
                'gateway'    => 'free',
            ] );

            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'free_activated', Pages::get_page_url( 'ovr_page_dashboard' ) ) );
            exit;
        }

        $gateway_slug = sanitize_key( $_POST['gateway'] ?? '' );

        $result = $this->gateway( $gateway_slug )->start_checkout( [
            'user_id'    => get_current_user_id(),
            'plan_slug'  => $plan_slug,
            'amount'     => $price,
            'currency'   => $plan['currency'] ?? 'USD',
            'return_url' => Pages::get_page_url( 'ovr_page_dashboard' ),
            'cancel_url' => Pages::get_page_url( 'ovr_page_pricing' ),
        ] );

        if ( ! empty( $result['redirect_url'] ) ) {
            wp_safe_redirect( $result['redirect_url'] );
            exit;
        }

        wp_safe_redirect( add_query_arg( [
            'ovr_checkout' => 'error',
            'reason'       => urlencode( $result['message'] ?? 'unknown' ),
        ], $referer ) );
        exit;
    }

    public function handle_ajax(): void {
        if ( ! check_ajax_referer( 'ovr_public_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [
                'message'      => __( 'Please log in to continue.', 'ovr-core' ),
                'redirect_url' => Pages::get_page_url( 'ovr_page_login' ),
            ], 401 );
        }

        $plan_slug = sanitize_key( $_POST['plan'] ?? '' );
        $plan      = Plans::get_plan( $plan_slug );
        if ( ! $plan ) {
            wp_send_json_error( [ 'message' => __( 'Invalid plan.', 'ovr-core' ) ], 400 );
        }

        $price = (float) ( $plan['price'] ?? 0 );
        if ( 0.0 === $price ) {
            update_user_meta( get_current_user_id(), 'ovr_subscription_plan', $plan_slug );
            wp_send_json_success( [
                'redirect_url' => Pages::get_page_url( 'ovr_page_dashboard' ),
                'message'      => __( 'Free plan activated.', 'ovr-core' ),
            ] );
        }

        $result = $this->gateway()->start_checkout( [
            'user_id'    => get_current_user_id(),
            'plan_slug'  => $plan_slug,
            'amount'     => $price,
            'currency'   => $plan['currency'] ?? 'USD',
            'return_url' => Pages::get_page_url( 'ovr_page_dashboard' ),
            'cancel_url' => Pages::get_page_url( 'ovr_page_pricing' ),
        ] );

        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result );
        }
        wp_send_json_error( $result, 400 );
    }

    /**
     * Admin notice shown to administrators on subscription/properties screens
     * if the gateway is not yet configured. Lets them know it's a Phase 2 step.
     */
    public function maybe_show_config_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( get_user_meta( get_current_user_id(), '_ovr_dismissed_payment_notice', true ) ) return;
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'ovr_property' !== $screen->post_type ) return;

        if ( $this->gateway()->is_configured() ) return;
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php esc_html_e( 'OVR — Payment gateway not configured.', 'ovr-core' ); ?></strong>
                <?php esc_html_e( 'Stripe API keys are pending. Subscriptions are accepted but payments are recorded as "pending". Configure your live keys in Phase 2 to enable real checkout.', 'ovr-core' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * After checkout redirects back, show a non-modal toast on the page.
     */
    public function maybe_show_checkout_toast(): void {
        $status = isset( $_GET['ovr_checkout'] ) ? sanitize_key( wp_unslash( $_GET['ovr_checkout'] ) ) : '';
        if ( ! $status ) return;

        $messages = [
            'pending'         => __( 'Payment recorded as pending. Admin will follow up to complete activation.', 'ovr-core' ),
            'free_activated'  => __( 'Free plan activated — welcome aboard!', 'ovr-core' ),
            'nonce_failed'    => __( 'Security check failed. Please try again.', 'ovr-core' ),
            'invalid_plan'    => __( 'Plan not found.', 'ovr-core' ),
            'error'           => __( 'Checkout failed.', 'ovr-core' ),
        ];

        $msg = $messages[ $status ] ?? '';
        if ( ! $msg ) return;

        $is_error = in_array( $status, [ 'nonce_failed', 'invalid_plan', 'error' ], true );
        ?>
        <div style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:9999;padding:14px 20px;border-radius:10px;font-family:'Inter',sans-serif;font-size:14px;background:<?php echo $is_error ? '#ffdad6' : '#74f7be'; ?>;color:<?php echo $is_error ? '#93000a' : '#00714e'; ?>;box-shadow:0 8px 24px rgba(0,0,0,0.15);max-width:420px">
            <?php echo esc_html( $msg ); ?>
        </div>
        <?php
    }
}
