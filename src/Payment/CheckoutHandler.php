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
use OVR\Subscription\ListingUpgrades;
use OVR\Subscription\UserSubscription;
use OVR\Subscription\SubscriptionManager;
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

        // Finalize a gateway redirect-back (Stripe/PayPal) before the page renders.
        add_action( 'template_redirect', [ $this, 'maybe_finalize_gateway_return' ] );

        // Admin: manually mark a pending payment paid + activate the subscription.
        add_action( 'admin_post_ovr_complete_payment', [ $this, 'handle_admin_complete_payment' ] );

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

        // Listing-upgrade purchase: no automated boost backend yet, so record a
        // pending payment and let an admin follow up (same model as the gateways).
        if ( ! empty( $_POST['upgrade'] ) ) {
            $this->handle_upgrade_purchase();
            return;
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

            $payment_id = (int) $wpdb->insert_id;

            do_action( 'ovr_payment_completed', $user_id, [
                'payment_id' => $payment_id,
                'plan_slug'  => $plan_slug,
                'amount'     => 0.0,
                'gateway'    => 'free',
            ] );

            wp_safe_redirect( $this->success_url( $payment_id ) );
            exit;
        }

        $gateway_slug = sanitize_key( $_POST['gateway'] ?? '' );

        // Mark subscription as pending before gateway redirect.
        $user_id = get_current_user_id();
        update_user_meta( $user_id, UserSubscription::META_STATUS, UserSubscription::STATUS_PENDING );

        $result = $this->gateway( $gateway_slug )->start_checkout( [
            'user_id'    => get_current_user_id(),
            'plan_slug'  => $plan_slug,
            'amount'     => $price,
            'currency'   => $plan['currency'] ?? 'USD',
            'return_url' => Pages::get_page_url( 'ovr_page_payment_success' ),
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

    /**
     * Buy a per-listing boost. The purchase is tied to a specific property the
     * buyer owns and runs through the SAME gateway flow as subscriptions
     * (carrying payment_type=listing_upgrade + the boost details as meta). The
     * boost activates via UpgradeActivator the moment the payment is confirmed:
     *   - Wallet / free → completed immediately (boost is live right away).
     *   - Stripe / PayPal (live) → redirected to the provider, then finalized on
     *     return, which fires ovr_payment_completed and activates the boost.
     *   - Any gateway not yet configured → recorded pending for admin completion.
     */
    private function handle_upgrade_purchase(): void {
        $upgrades_tab = add_query_arg( 'tab', 'upgrades', Pages::get_page_url( 'ovr_page_dashboard' ) );
        $listings_tab = add_query_arg( 'tab', 'properties', Pages::get_page_url( 'ovr_page_dashboard' ) );

        // The service slug identifies a catalogue row (price + duration + type).
        // `upgrade` is the canonical field; `service` is accepted as an alias.
        $id      = sanitize_title( wp_unslash( $_POST['service'] ?? $_POST['upgrade'] ?? '' ) );
        $product = ListingUpgrades::get_product( $id );

        if ( ! $product ) {
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'invalid_plan', $upgrades_tab ) );
            exit;
        }

        $service_type = (string) ( $product['service_type'] ?? '' );
        $term         = (int) ( $product['duration_days'] ?? 14 );

        // A boost must target a specific listing the buyer owns.
        $user_id     = get_current_user_id();
        $property_id = isset( $_POST['property_id'] ) ? absint( $_POST['property_id'] ) : 0;
        $property    = $property_id ? get_post( $property_id ) : null;
        if ( ! $property
            || 'ovr_property' !== $property->post_type
            || (int) $property->post_author !== (int) $user_id ) {
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'no_listing', $listings_tab ) );
            exit;
        }

        // Enforce the homepage-slider "max simultaneous listings" cap before
        // taking payment, so we never sell a slot that cannot be filled.
        $remaining = \OVR\Subscription\PaidService::remaining_slots(
            [
                'service_type'     => $service_type,
                'max_simultaneous' => (int) ( $product['max_simultaneous'] ?? 0 ),
            ],
            $property_id
        );
        if ( null !== $remaining && $remaining < 1 ) {
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'slots_full', $upgrades_tab ) );
            exit;
        }

        $amount       = ListingUpgrades::price_for( $product, $term );
        $gateway_slug = sanitize_key( $_POST['gateway'] ?? 'wallet' );
        $meta         = [
            'upgrade'      => $id,
            'service_type' => $service_type,
            'term'         => $term,
            'property_id'  => $property_id,
        ];

        // Free upgrade → record a completed $0 payment and activate immediately.
        if ( $amount <= 0 ) {
            global $wpdb;
            $wpdb->insert( $wpdb->prefix . 'ovr_payments', [
                'user_id'        => $user_id,
                'payment_type'   => 'listing_upgrade',
                'amount'         => 0.00,
                'currency'       => 'USD',
                'gateway'        => 'free',
                'transaction_id' => 'free_' . wp_generate_uuid4(),
                'status'         => 'completed',
                'meta_data'      => wp_json_encode( $meta ),
            ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );
            $payment_id = (int) $wpdb->insert_id;

            do_action( 'ovr_payment_completed', $user_id, [
                'payment_id'   => $payment_id,
                'amount'       => 0.0,
                'gateway'      => 'free',
                'payment_type' => 'listing_upgrade',
            ] );

            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'completed', $this->success_url( $payment_id ) ) );
            exit;
        }

        // Paid upgrade → run it through the chosen gateway, exactly like a plan.
        /* translators: 1: upgrade name, 2: term in days */
        $item_name = sprintf( __( '%1$s (%2$d-day boost)', 'ovr-core' ), (string) $product['name'], $term );

        $result = $this->gateway( $gateway_slug )->start_checkout( [
            'user_id'      => $user_id,
            'amount'       => $amount,
            'currency'     => 'USD',
            'payment_type' => 'listing_upgrade',
            'item_name'    => $item_name,
            'meta'         => $meta,
            'return_url'   => Pages::get_page_url( 'ovr_page_payment_success' ),
            'cancel_url'   => $upgrades_tab,
        ] );

        if ( ! empty( $result['redirect_url'] ) ) {
            wp_safe_redirect( $result['redirect_url'] );
            exit;
        }

        // Gateway refused before any redirect (e.g. wallet balance too low) —
        // return to the checkout page so they can pick another method.
        $reason = ( 'wallet' === $gateway_slug ) ? 'low_balance' : 'error';
        wp_safe_redirect( add_query_arg( [
            'upgrade'      => $id,
            'property'     => $property_id,
            'ovr_checkout' => $reason,
        ], Pages::get_page_url( 'ovr_page_checkout' ) ) );
        exit;
    }

    /**
     * URL of the payment-success page for a given payment.
     */
    private function success_url( int $payment_id ): string {
        return add_query_arg( 'payment_id', $payment_id, Pages::get_page_url( 'ovr_page_payment_success' ) );
    }

    /**
     * When a gateway redirects the buyer back (Stripe/PayPal), verify/capture
     * the payment, mark it completed, and fire activation. Idempotent.
     */
    public function maybe_finalize_gateway_return(): void {
        $gw = isset( $_GET['ovr_gw'] ) ? sanitize_key( wp_unslash( $_GET['ovr_gw'] ) ) : '';
        if ( ! in_array( $gw, [ 'stripe', 'paypal' ], true ) ) {
            return;
        }

        global $wpdb;
        $table      = $wpdb->prefix . 'ovr_payments';
        $payment_id = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : 0;

        $row = null;
        if ( $payment_id ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payment_id ), ARRAY_A );
        }
        if ( ! $row && 'paypal' === $gw && isset( $_GET['token'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
            $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE transaction_id = %s AND gateway = 'paypal'", $token ), ARRAY_A );
        }
        if ( ! $row ) {
            return;
        }

        $success_url = $this->success_url( (int) $row['id'] );

        // Already finalized → just show the receipt (idempotent on refresh).
        if ( 'completed' === $row['status'] ) {
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'completed', $success_url ) );
            exit;
        }
        if ( 'pending' !== $row['status'] ) {
            wp_safe_redirect( $success_url );
            exit;
        }

        $gateway = $this->gateway( $gw );
        if ( ! method_exists( $gateway, 'finalize' ) ) {
            return;
        }

        $res = $gateway->finalize( $row );
        if ( empty( $res['success'] ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'pending', $success_url ) );
            exit;
        }

        $wpdb->update( $table, [ 'status' => 'completed' ], [ 'id' => (int) $row['id'] ], [ '%s' ], [ '%d' ] );

        $meta      = json_decode( (string) ( $row['meta_data'] ?? '' ), true );
        $plan_slug = is_array( $meta ) ? (string) ( $meta['plan_slug'] ?? '' ) : '';

        do_action( 'ovr_payment_completed', (int) $row['user_id'], [
            'payment_id'   => (int) $row['id'],
            'plan_slug'    => $plan_slug,
            'amount'       => (float) $row['amount'],
            'gateway'      => $gw,
            'payment_type' => (string) ( $row['payment_type'] ?? 'subscription' ),
        ] );

        wp_safe_redirect( add_query_arg( 'ovr_checkout', 'completed', $success_url ) );
        exit;
    }

    /**
     * Admin action: mark a pending payment paid and activate the subscription.
     * Used for offline payments or to unblock a landlord. (Phase-1 fallback.)
     */
    public function handle_admin_complete_payment(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        $payment_id = isset( $_REQUEST['payment'] ) ? absint( $_REQUEST['payment'] ) : 0;
        $nonce      = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
        $back       = admin_url( 'edit.php?post_type=ovr_property&page=ovr-core-payments' );

        if ( ! $payment_id || ! wp_verify_nonce( $nonce, 'ovr_complete_payment_' . $payment_id ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_paid', 'error', $back ) );
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payment_id ), ARRAY_A );

        if ( ! $row || 'completed' === $row['status'] ) {
            wp_safe_redirect( add_query_arg( 'ovr_paid', $row ? 'already' : 'error', $back ) );
            exit;
        }

        $wpdb->update( $table, [ 'status' => 'completed' ], [ 'id' => $payment_id ], [ '%s' ], [ '%d' ] );

        $meta      = json_decode( (string) ( $row['meta_data'] ?? '' ), true );
        $plan_slug = is_array( $meta ) ? (string) ( $meta['plan_slug'] ?? '' ) : '';

        do_action( 'ovr_payment_completed', (int) $row['user_id'], [
            'payment_id'   => $payment_id,
            'plan_slug'    => $plan_slug,
            'amount'       => (float) $row['amount'],
            'gateway'      => (string) $row['gateway'],
            'payment_type' => (string) ( $row['payment_type'] ?? 'subscription' ),
        ] );

        wp_safe_redirect( add_query_arg( 'ovr_paid', 'done', $back ) );
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

        $user_id = get_current_user_id();
        $price = (float) ( $plan['price'] ?? 0 );
        if ( 0.0 === $price ) {
            // Free plan — no payment needed. Activate directly.
            SubscriptionManager::activate( $user_id, $plan_slug );
            wp_send_json_success( [
                'redirect_url' => Pages::get_page_url( 'ovr_page_subscription_select' ),
                'message'      => __( 'Plan selected. Choose a paid plan for full landlord access.', 'ovr-core' ),
            ] );
        }

        update_user_meta( $user_id, UserSubscription::META_STATUS, UserSubscription::STATUS_PENDING );

        $result = $this->gateway()->start_checkout( [
            'user_id'    => get_current_user_id(),
            'plan_slug'  => $plan_slug,
            'amount'     => $price,
            'currency'   => $plan['currency'] ?? 'USD',
            'return_url' => Pages::get_page_url( 'ovr_page_payment_success' ),
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
                <?php esc_html_e( 'No payment gateway credentials are set, so card/PayPal payments are recorded as "pending" until an admin marks them paid. Add your Stripe or PayPal keys under OVR → Settings → Payments to enable live checkout.', 'ovr-core' ); ?>
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

        // The payment-success page already shows full order status — don't double up.
        $success_id = (int) get_option( 'ovr_page_payment_success' );
        if ( $success_id && is_page( $success_id ) ) return;

        $messages = [
            'pending'         => __( 'Payment recorded as pending. Admin will follow up to complete activation.', 'ovr-core' ),
            'free_activated'  => __( 'Free plan activated — welcome aboard!', 'ovr-core' ),
            'nonce_failed'    => __( 'Security check failed. Please try again.', 'ovr-core' ),
            'invalid_plan'    => __( 'Plan not found.', 'ovr-core' ),
            'error'           => __( 'Checkout failed.', 'ovr-core' ),
            'no_listing'      => __( 'Pick a listing to boost: open My Listings and click “Bump” on it.', 'ovr-core' ),
            'low_balance'     => __( 'Your available credit does not cover this upgrade. Please choose another payment method.', 'ovr-core' ),
        ];

        $msg = $messages[ $status ] ?? '';
        if ( ! $msg ) return;

        $is_error = in_array( $status, [ 'nonce_failed', 'invalid_plan', 'error', 'no_listing', 'low_balance' ], true );
        ?>
        <div style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:9999;padding:14px 20px;border-radius:10px;font-family:'Inter',sans-serif;font-size:14px;background:<?php echo $is_error ? '#ffdad6' : '#74f7be'; ?>;color:<?php echo $is_error ? '#93000a' : '#00714e'; ?>;box-shadow:0 8px 24px rgba(0,0,0,0.15);max-width:420px">
            <?php echo esc_html( $msg ); ?>
        </div>
        <?php
    }
}
