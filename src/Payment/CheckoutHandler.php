<?php
/**
 * Checkout Handler.
 *
 * Wires the "Select Plan" buttons on /pricing/ to a payment gateway.
 *
 *   POST /wp-admin/admin-post.php?action=ovr_start_checkout
 *
 * Starts a checkout for the active gateway (Stripe Checkout Sessions, PayPal
 * Orders, or the internal Wallet) and finalizes it server-side on the buyer's
 * return redirect by re-confirming the payment with the provider. Never treats
 * a redirect alone as proof of payment.
 *
 * Also surfaces a one-time admin notice on plan-management screens letting
 * admins know they need to configure their payment API keys.
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
        // Wallet / On Account removed per business decision — too much
        // complexity for low usage. Historical wallet data remains readable.
        $this->gateways['stripe']        = new StripeGateway();
        $this->gateways['paypal']        = new PayPalGateway();
        $this->gateways['authorize_net'] = new AuthorizeNetGateway();

        add_action( 'admin_post_ovr_start_checkout',        [ $this, 'handle_start' ] );
        add_action( 'admin_post_nopriv_ovr_start_checkout', [ $this, 'handle_start_anon' ] );
        add_action( 'wp_ajax_ovr_start_checkout',           [ $this, 'handle_ajax' ] );

        // Finalize a gateway redirect-back (Stripe/PayPal) before the page renders.
        add_action( 'template_redirect', [ $this, 'maybe_finalize_gateway_return' ] );

        // Close out the payment when the buyer cancels at the gateway.
        add_action( 'template_redirect', [ $this, 'maybe_mark_checkout_cancelled' ] );

        // Admin: manually mark a pending payment paid + activate the subscription.
        add_action( 'admin_post_ovr_complete_payment', [ $this, 'handle_admin_complete_payment' ] );

        add_action( 'admin_notices', [ $this, 'maybe_show_config_notice' ] );

        // Surface the "checkout pending" message on the pricing page.
        add_action( 'wp_footer', [ $this, 'maybe_show_checkout_toast' ] );
    }

    /**
     * Resolve a gateway by slug. Empty string falls back to the active default.
     */
    /**
     * Slug of the gateway a buyer gets when they express no preference.
     *
     * Single source of truth: the checkout screen reads this to decide which
     * method tab starts selected and what the hidden field is seeded with, so
     * the UI can never drift from what the server would actually charge.
     * Override with the `ovr_active_gateway` filter.
     */
    public static function default_gateway(): string {
        return (string) apply_filters( 'ovr_active_gateway', 'paypal' );
    }

    public function gateway( string $slug = '' ): PaymentGateway {
        if ( ! $slug ) {
            $slug = self::default_gateway();
        }
        if ( isset( $this->gateways[ $slug ] ) ) {
            return $this->gateways[ $slug ];
        }
        // Unknown slug → fall back to the default rather than a hard-coded
        // provider, so an unconfigured gateway is never silently selected.
        return $this->gateways[ self::default_gateway() ] ?? $this->gateways['paypal'];
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

        // Needed by both the free-plan branch below and the gateway branch.
        $user_id = get_current_user_id();

        // Guard accidental double purchases — double-clicking "Complete
        // Purchase", re-submitting the form, or going back and submitting
        // again. Instant gateways (wallet/free) charge on submit, so without
        // this the buyer is debited twice for one subscription. Show the
        // receipt for the payment they just made instead of taking another.
        //
        // Only applies while the buyer is already active: that is the state a
        // successful purchase leaves them in, so it is the signal that the
        // earlier payment did its job. Someone expired or unsubscribed is
        // trying to *become* active and must never be turned away.
        if ( UserSubscription::is_active( $user_id ) ) {
            $duplicate = $this->recent_duplicate_payment( $user_id, $plan_slug, (float) ( $plan['price'] ?? 0 ) );
            if ( $duplicate ) {
                wp_safe_redirect( add_query_arg( 'ovr_checkout', 'completed', $this->success_url( $duplicate ) ) );
                exit;
            }
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

        // Mark the subscription pending before the gateway redirect — but never
        // downgrade someone who is already active. Renewing or upgrading means
        // starting a second checkout, and abandoning it (or cancelling at the
        // provider) must not strip access that is still paid for and unexpired.
        if ( ! UserSubscription::is_active( $user_id ) ) {
            update_user_meta( $user_id, UserSubscription::META_STATUS, UserSubscription::STATUS_PENDING );
        }

        $result = $this->gateway( $gateway_slug )->start_checkout( [
            'user_id'    => $user_id,
            'plan_slug'  => $plan_slug,
            'amount'     => $price,
            'currency'   => $plan['currency'] ?? 'USD',
            'return_url' => Pages::get_page_url( 'ovr_page_payment_success' ),
            'cancel_url' => Pages::get_page_url( 'ovr_page_pricing' ),
        ] );

        if ( ! empty( $result['redirect_url'] ) ) {
            $this->redirect_to_gateway( $result['redirect_url'] );
        }

        wp_safe_redirect( add_query_arg( [
            'ovr_checkout' => 'error',
            'reason'       => urlencode( $result['message'] ?? 'unknown' ),
        ], $referer ) );
        exit;
    }

    /**
     * Send the buyer to a gateway-supplied URL.
     *
     * Approval URLs live on the provider's own domain (checkout.stripe.com,
     * www.paypal.com …). wp_safe_redirect() rejects off-site hosts and silently
     * falls back to wp-admin, which strands the buyer after the order has
     * already been created at the provider. Whitelist just the host we are
     * about to send them to, so the redirect stays validated rather than open.
     */
    private function redirect_to_gateway( string $url ): void {
        $host = wp_parse_url( $url, PHP_URL_HOST );

        if ( $host ) {
            add_filter(
                'allowed_redirect_hosts',
                static function ( $hosts ) use ( $host ) {
                    $hosts[] = $host;
                    return $hosts;
                }
            );
        }

        wp_safe_redirect( $url );
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
        $gateway_slug = sanitize_key( $_POST['gateway'] ?? 'paypal' );
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
            $this->redirect_to_gateway( $result['redirect_url'] );
        }

        // Gateway refused before any redirect — return to checkout.
        $reason = 'error';
        wp_safe_redirect( add_query_arg( [
            'upgrade'      => $id,
            'property'     => $property_id,
            'ovr_checkout' => $reason,
        ], Pages::get_page_url( 'ovr_page_checkout' ) ) );
        exit;
    }

    /**
     * Seconds within which an identical completed purchase is treated as an
     * accidental re-submit rather than a deliberate second purchase.
     */
    private const DUPLICATE_WINDOW = 120;

    /**
     * Find a just-completed payment for the same user/plan/amount.
     *
     * The window is compared using the database's own clock (NOW()), because
     * `created_at` is filled by the column default in MySQL's timezone, which
     * is not necessarily the same as PHP's.
     *
     * @return int Payment id, or 0 when this is not a duplicate.
     */
    private function recent_duplicate_payment( int $user_id, string $plan_slug, float $amount ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, meta_data FROM {$table}
              WHERE user_id = %d
                AND payment_type = 'subscription'
                AND status = 'completed'
                AND amount = %f
                AND created_at >= ( NOW() - INTERVAL %d SECOND )
              ORDER BY id DESC
              LIMIT 5",
            $user_id,
            $amount,
            self::DUPLICATE_WINDOW
        ), ARRAY_A );

        foreach ( (array) $rows as $row ) {
            $meta = json_decode( (string) ( $row['meta_data'] ?? '' ), true );
            if ( is_array( $meta ) && $plan_slug === ( $meta['plan_slug'] ?? '' ) ) {
                return (int) $row['id'];
            }
        }

        return 0;
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
        if ( ! in_array( $gw, [ 'stripe', 'paypal', 'authorize_net' ], true ) ) {
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
            // The gateway gave a definitive "no" (declined, never approved,
            // expired). Record it as failed so the buyer is told the truth and
            // the row does not linger in the admin queue as if it were awaiting
            // review. Indeterminate errors (network/auth) stay pending.
            if ( ! empty( $res['failed'] ) ) {
                $wpdb->update( $table, [ 'status' => 'failed' ], [ 'id' => (int) $row['id'] ], [ '%s' ], [ '%d' ] );
                do_action( 'ovr_payment_failed', (int) $row['user_id'], [
                    'payment_id' => (int) $row['id'],
                    'gateway'    => $gw,
                    'code'       => (string) ( $res['code'] ?? '' ),
                ] );
                wp_safe_redirect( add_query_arg( 'ovr_checkout', 'failed', $success_url ) );
                exit;
            }

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
     * The buyer backed out at the gateway (PayPal/Stripe send them to cancel_url
     * with ovr_checkout=cancelled and the order id as `token`). Close the row out
     * so an abandoned checkout is not left sitting in the admin queue looking
     * like a payment that still needs to be actioned.
     *
     * Only ever touches a row that is still `pending`, so a completed payment
     * can never be walked backwards by replaying this URL.
     */
    public function maybe_mark_checkout_cancelled(): void {
        $status = isset( $_GET['ovr_checkout'] ) ? sanitize_key( wp_unslash( $_GET['ovr_checkout'] ) ) : '';
        if ( 'cancelled' !== $status ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';

        // Authorize.net returns to its cancel_url carrying ovr_gw + payment_id.
        $gw = isset( $_GET['ovr_gw'] ) ? sanitize_key( wp_unslash( $_GET['ovr_gw'] ) ) : '';
        if ( 'authorize_net' === $gw ) {
            $payment_id = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : 0;
            if ( $payment_id ) {
                $updated = $wpdb->update(
                    $table,
                    [ 'status' => 'cancelled' ],
                    [ 'id' => $payment_id, 'status' => 'pending' ],
                    [ '%s' ],
                    [ '%d', '%s' ]
                );
                if ( $updated ) {
                    do_action( 'ovr_checkout_cancelled', (string) $payment_id );
                }
            }
            return;
        }

        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        if ( '' === $token ) {
            return;
        }

        $updated = $wpdb->update(
            $table,
            [ 'status' => 'cancelled' ],
            [ 'transaction_id' => $token, 'status' => 'pending' ],
            [ '%s' ],
            [ '%s', '%s' ]
        );

        if ( $updated ) {
            do_action( 'ovr_checkout_cancelled', $token );
        }
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
            'cancelled'       => __( 'Checkout cancelled — you have not been charged.', 'ovr-core' ),
            'failed'          => __( 'Payment was not completed — you have not been charged. Please try again.', 'ovr-core' ),
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
