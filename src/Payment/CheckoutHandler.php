<?php
/**
 * Checkout Handler.
 *
 *  Wires the "Select Plan" buttons on /pricing/ to a payment gateway.
 *
 *  POST /wp-admin/admin-post.php?action=ovr_start_checkout
 *
 *  Starts a checkout for the active gateway (Authorize.Net / PayPal) and
 *  finalizes it server-side on the buyer's return redirect by re-confirming
 *  the payment with the provider. Never treats a redirect alone as proof of
 *  payment.
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
use OVR\Payment\PromoCode;
use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CheckoutHandler {

    /** @var PaymentGateway[] */
    private array $gateways = [];

    public function init(): void {
        // Register available gateways. Wallet/On Account removed per business
        // decision. Stripe removed per client-approved processor list.
        $this->gateways['paypal']        = new PayPalGateway();
        $this->gateways['authorize_net'] = new AuthorizeNetGateway();

        add_action( 'admin_post_ovr_start_checkout',        [ $this, 'handle_start' ] );
        add_action( 'admin_post_nopriv_ovr_start_checkout', [ $this, 'handle_start_anon' ] );
        add_action( 'wp_ajax_ovr_start_checkout',           [ $this, 'handle_ajax' ] );

        // Section 2: "Continue to Payment" from the subscription-selection step.
        // Builds the authoritative offer server-side and forwards to checkout.
        add_action( 'admin_post_ovr_continue_checkout',        [ $this, 'handle_continue' ] );
        add_action( 'admin_post_nopriv_ovr_continue_checkout', [ $this, 'handle_continue' ] );

        // Finalize a gateway redirect-back (Stripe/PayPal) before the page renders.
        add_action( 'template_redirect', [ $this, 'maybe_finalize_gateway_return' ] );

        // Close out the payment when the buyer cancels at the gateway.
        add_action( 'template_redirect', [ $this, 'maybe_mark_checkout_cancelled' ] );

        // Admin: manually mark a pending payment paid + activate the subscription.
        add_action( 'admin_post_ovr_complete_payment', [ $this, 'handle_admin_complete_payment' ] );

        add_action( 'ovr_payment_completed', [ $this, 'maybe_increment_promo_use' ], 10, 2 );

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

        // Section 2: authoritative offer supplied by the checkout page. The
        // browser submits only the opaque offer id; price + duration are loaded
        // from the persisted server-side snapshot and can never be overridden.
        $offer_id = sanitize_text_field( wp_unslash( $_POST['ovr_offer_id'] ?? '' ) );
        if ( '' !== $offer_id ) {
            $this->start_from_offer( $offer_id, $referer );
            return;
        }

        $plan_slug = sanitize_key( $_POST['plan'] ?? '' );
        $plan      = Plans::get_plan( $plan_slug );

        if ( ! $plan || empty( $plan['is_active'] ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'invalid_plan', $referer ) );
            exit;
        }

        // Needed by both the free-plan branch below and the gateway branch.
        $user_id = get_current_user_id();

        // Single canonical offer calculation. Direct checkout (pricing page)
        // passes plan + promo; we reconstruct the authoritative price and
        // duration server-side and never trust submitted amounts. A bad promo
        // degrades to the base plan instead of blocking the purchase.
        $offer = \OVR\Subscription\SubscriptionOffer::build(
            $user_id,
            $plan_slug,
            'new',
            sanitize_text_field( wp_unslash( $_POST['promo_code'] ?? '' ) )
        );
        if ( is_wp_error( $offer ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'invalid_plan', $referer ) );
            exit;
        }

        $promo_code     = (string) ( $offer['promo_code'] ?? '' );
        $price          = (float) $offer['final_price'];
        $price_for_dupe = $price;

        $offer_meta = [
            'base_price'             => (float) $offer['base_price'],
            'base_duration_days'     => (int) $offer['base_duration_days'],
            'final_price'            => (float) $offer['final_price'],
            'final_duration_days'    => (int) $offer['final_duration_days'],
            'duration_override_days' => $offer['duration_override_days'],
            'purchase_context'       => 'new',
        ];

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
            $duplicate = $this->recent_duplicate_payment( $user_id, $plan_slug, $price_for_dupe );
            if ( $duplicate ) {
                wp_safe_redirect( add_query_arg( 'ovr_checkout', 'completed', $this->success_url( $duplicate ) ) );
                exit;
            }
        }

        // Free plan: no payment, but still record a $0 "pending" payment row
        // via find_or_create_free_payment() so duplicate POSTs for the same
        // checkout intent cannot create multiple completed free payment rows
        // or fire ovr_payment_completed more than once.
        if ( 0.0 === $price ) {
            $free_meta = array_merge( [ 'plan_slug' => $plan_slug ], $offer_meta );
            if ( '' !== $promo_code ) {
                $free_meta['promo_code'] = $promo_code;
            }
            $checkout_intent = sanitize_text_field( wp_unslash( $_POST['ovr_checkout_intent'] ?? '' ) );
            if ( '' === $checkout_intent ) {
                $checkout_intent = wp_generate_uuid4();
            }
            $payment_id = $this->find_or_create_free_payment( $user_id, 'subscription', $free_meta, $checkout_intent );

            $this->complete_payment_atomically( $payment_id, [
                'payment_id'   => $payment_id,
                'plan_slug'    => $plan_slug,
                'amount'       => 0.0,
                'gateway'      => 'free',
                'promo_code'   => $promo_code,
                'duration_override_days' => $offer['duration_override_days'],
                'final_duration_days'    => (int) $offer['final_duration_days'],
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

        $checkout_meta = $offer_meta;
        if ( '' !== $promo_code ) {
            $checkout_meta['promo_code'] = $promo_code;
        }
        $result = $this->gateway( $gateway_slug )->start_checkout( [
            'user_id'    => $user_id,
            'plan_slug'  => $plan_slug,
            'amount'     => $price,
            'currency'   => $plan['currency'] ?? 'USD',
            'return_url' => Pages::get_page_url( 'ovr_page_payment_success' ),
            'cancel_url' => Pages::get_page_url( 'ovr_page_pricing' ),
            'meta'       => $checkout_meta,
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
     * Approval URLs live on the provider's own domain (secure.authorize.net,
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
     *   - PayPal / Authorize.Net → redirected to the provider, then finalized on
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

        // Free upgrade → record a $0 "pending" payment via find_or_create_free_payment()
        // and atomically complete it so duplicate POSTs cannot create two rows or
        // fire the completion event twice for the same upgrade intent.
        if ( $amount <= 0 ) {
            $checkout_intent = sanitize_text_field( wp_unslash( $_POST['ovr_checkout_intent'] ?? '' ) );
            if ( '' === $checkout_intent ) {
                $checkout_intent = wp_generate_uuid4();
            }
            $payment_id = $this->find_or_create_free_payment( $user_id, 'listing_upgrade', $meta, $checkout_intent );

            $this->complete_payment_atomically( $payment_id, [
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
     * "Continue to Payment" from /subscription-select/ (Section 2).
     *
     * Rebuilds the authoritative offer from the submitted plan + promo (client
     * values are never trusted for money/duration), persists it, and forwards
     * the buyer to the checkout screen with the opaque offer id.
     */
    public function handle_continue(): void {
        $fallback = Pages::get_page_url( 'ovr_page_subscription_select' );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( add_query_arg( 'redirect_to', urlencode( $fallback ), Pages::get_page_url( 'ovr_page_login' ) ) );
            exit;
        }

        if ( ! isset( $_POST['ovr_continue_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_continue_nonce'] ) ), 'ovr_continue_checkout' ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_offer', 'nonce_failed', $fallback ) );
            exit;
        }

        $plan    = sanitize_key( wp_unslash( $_POST['plan'] ?? '' ) );
        $context = sanitize_key( wp_unslash( $_POST['context'] ?? 'new' ) );
        $promo   = sanitize_text_field( wp_unslash( $_POST['promo_code'] ?? '' ) );
        $user_id = get_current_user_id();

        $offer = \OVR\Subscription\SubscriptionOffer::build( $user_id, $plan, $context, $promo );
        if ( is_wp_error( $offer ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_offer', 'invalid_plan', $fallback ) );
            exit;
        }

        $persisted = \OVR\Subscription\SubscriptionOffer::persist( $offer );
        if ( is_wp_error( $persisted ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_offer', 'error', $fallback ) );
            exit;
        }

        $url = add_query_arg( 'offer_id', (string) $persisted['offer_id'], Pages::get_page_url( 'ovr_page_checkout' ) );
        if ( ! empty( $persisted['promo_error'] ) ) {
            $url = add_query_arg( 'ovr_offer', 'promo_invalid', $url );
        }
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Start checkout from a persisted authoritative offer (Section 2). The
     * offer is claimed atomically so a double-click can only begin one checkout.
     */
    private function start_from_offer( string $offer_id, string $referer ): void {
        $user_id = get_current_user_id();

        $offer = \OVR\Subscription\SubscriptionOffer::resolve_for_checkout( $offer_id, $user_id );
        if ( is_wp_error( $offer ) ) {
            // Idempotent replay: a consumed offer that already produced a
            // payment should surface that payment, not an error.
            $existing = $this->payment_for_offer( $offer_id );
            if ( $existing ) {
                wp_safe_redirect( add_query_arg( 'ovr_checkout', 'completed', $this->success_url( $existing ) ) );
                exit;
            }
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'invalid_plan', $referer ) );
            exit;
        }

        // Atomic open → consumed claim. A failed claim means another request
        // already started this offer; reuse its payment instead of double-charging.
        if ( ! \OVR\Subscription\SubscriptionOffer::claim( $offer_id ) ) {
            $existing = $this->payment_for_offer( $offer_id );
            if ( $existing ) {
                wp_safe_redirect( add_query_arg( 'ovr_checkout', 'completed', $this->success_url( $existing ) ) );
                exit;
            }
            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'pending', $referer ) );
            exit;
        }

        $plan_slug = (string) $offer['plan_slug'];
        $price     = (float) $offer['final_price'];

        $meta = [
            'plan_slug'              => $plan_slug,
            'offer_id'               => $offer_id,
            'base_price'             => (float) $offer['base_price'],
            'base_duration_days'     => (int) $offer['base_duration_days'],
            'final_price'            => (float) $offer['final_price'],
            'final_duration_days'    => (int) $offer['final_duration_days'],
            'duration_override_days' => $offer['duration_override_days'],
            'purchase_context'       => (string) $offer['purchase_context'],
        ];
        if ( ! empty( $offer['promo_code'] ) ) {
            $meta['promo_code'] = (string) $offer['promo_code'];
        }

        // Free offer ($0 promo or free plan) → hardened free-payment path; the
        // offer id is the durable checkout intent.
        if ( $price <= 0 ) {
            $payment_id = $this->find_or_create_free_payment( $user_id, 'subscription', $meta, $offer_id );
            \OVR\Subscription\SubscriptionOffer::mark_status( $offer_id, \OVR\Subscription\SubscriptionOffer::STATUS_CONSUMED, $payment_id );

            $this->complete_payment_atomically( $payment_id, [
                'payment_id'   => $payment_id,
                'plan_slug'    => $plan_slug,
                'amount'       => 0.0,
                'gateway'      => 'free',
                'payment_type' => 'subscription',
                'promo_code'   => (string) ( $meta['promo_code'] ?? '' ),
                'duration_override_days' => $offer['duration_override_days'],
                'final_duration_days'    => (int) $offer['final_duration_days'],
            ] );

            wp_safe_redirect( $this->success_url( $payment_id ) );
            exit;
        }

        $gateway_slug = sanitize_key( $_POST['gateway'] ?? '' );

        if ( ! UserSubscription::is_active( $user_id ) ) {
            update_user_meta( $user_id, UserSubscription::META_STATUS, UserSubscription::STATUS_PENDING );
        }

        $result = $this->gateway( $gateway_slug )->start_checkout( [
            'user_id'    => $user_id,
            'plan_slug'  => $plan_slug,
            'amount'     => $price,
            'currency'   => (string) ( $offer['currency'] ?? 'USD' ),
            'return_url' => Pages::get_page_url( 'ovr_page_payment_success' ),
            'cancel_url' => Pages::get_page_url( 'ovr_page_subscription_select' ),
            'meta'       => $meta,
        ] );

        if ( ! empty( $result['payment_id'] ) ) {
            global $wpdb;
            // Tie the payment row to the offer id (unique) for durable idempotency
            // and so Section 3 can resolve the authoritative duration snapshot.
            $wpdb->update(
                $wpdb->prefix . 'ovr_payments',
                [ 'checkout_intent_id' => $offer_id ],
                [ 'id' => (int) $result['payment_id'] ],
                [ '%s' ],
                [ '%d' ]
            );
            \OVR\Subscription\SubscriptionOffer::mark_status( $offer_id, \OVR\Subscription\SubscriptionOffer::STATUS_CONSUMED, (int) $result['payment_id'] );
        }

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
     * Find a payment already tied to an offer id (durable checkout intent).
     */
    private function payment_for_offer( string $offer_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . $wpdb->prefix . 'ovr_payments WHERE checkout_intent_id = %s ORDER BY id DESC LIMIT 1',
            $offer_id
        ) );
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
     * Find an existing free payment for the same logical checkout intent, or
     * create one. The durable checkout_intent_id survives pending → completed,
     * so a replayed request for the same checkout intent finds the existing
     * row instead of creating a duplicate.
     *
     * The named-lock guard makes creation concurrency-safe: two simultaneous
     * POSTs for the same intent can only create one row.
     *
     * @param string|null $checkout_intent_id Durable checkout-instance identity.
     * @return int Payment ID (existing or newly inserted).
     */
    private function find_or_create_free_payment( int $user_id, string $payment_type, array $meta, ?string $checkout_intent_id = null ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';
        $meta_json = wp_json_encode( $meta );
        $lock_hash = md5( (string) $user_id . '|' . $payment_type . '|' . $meta_json . '|' . ( $checkout_intent_id ?? '' ) );
        $lock_name = 'ovr_free_intent_' . $lock_hash;

        $wpdb->query( $wpdb->prepare( "SELECT GET_LOCK(%s, 3)", $lock_name ) );

        // 1) Durable intent lookup: find any existing payment for this checkout
        //    intent, regardless of status. This closes the replay-after-completion
        //    gap because a completed payment still matches.
        if ( $checkout_intent_id ) {
            $by_intent = $wpdb->get_row( $wpdb->prepare( "
                SELECT id, status FROM {$table}
                WHERE checkout_intent_id = %s
                ORDER BY id DESC
                LIMIT 1
            ", $checkout_intent_id ), ARRAY_A );

            if ( $by_intent ) {
                $wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
                return (int) $by_intent['id'];
            }
        }

        // 2) Fallback: pending-only lookup keyed by business metadata. This
        //    preserves idempotency for in-flight duplicate submissions.
        $existing = $wpdb->get_row( $wpdb->prepare( "
            SELECT id FROM {$table}
            WHERE user_id = %d
              AND payment_type = %s
              AND amount = 0
              AND gateway = 'free'
              AND status = 'pending'
              AND meta_data = %s
            LIMIT 1
        ", $user_id, $payment_type, $meta_json ), ARRAY_A );

        if ( $existing ) {
            $wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
            return (int) $existing['id'];
        }

        // 3) No match — insert a new pending row with the durable intent ID.
        $insert_data = [
            'user_id'        => $user_id,
            'payment_type'   => $payment_type,
            'amount'         => 0.00,
            'currency'       => 'USD',
            'gateway'        => 'free',
            'transaction_id' => 'free_' . wp_generate_uuid4(),
            'status'         => 'pending',
            'meta_data'      => $meta_json,
        ];
        $insert_formats = [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ];

        if ( $checkout_intent_id ) {
            $insert_data['checkout_intent_id'] = $checkout_intent_id;
            $insert_formats[] = '%s';
        }

        $wpdb->insert( $table, $insert_data, $insert_formats );

        $payment_id = (int) $wpdb->insert_id;

        $wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );

        return $payment_id;
    }

    /**
     * Atomically transition a pending payment to completed and fire the
     * completion event exactly once.
     *
     * The conditional WHERE status='pending' makes the transition safe under
     * concurrency: only the request that actually flips the row from pending
     * to completed fires ovr_payment_completed.
     *
     * @return bool True if this request owned the transition.
     */
    public function complete_payment_atomically( int $payment_id, array $context ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payment_id ), ARRAY_A );

        if ( ! $row || 'pending' !== $row['status'] ) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            [ 'status' => 'completed' ],
            [ 'id' => $payment_id, 'status' => 'pending' ],
            [ '%s', '%s' ],
            [ '%d', '%s' ]
        );

        if ( 1 === $result ) {
            do_action( 'ovr_payment_completed', (int) $row['user_id'], $context );
            return true;
        }

        return false;
    }

    /**
     * When a gateway redirects the buyer back (Authorize.Net/PayPal), verify/capture
     * the payment, mark it completed, and fire activation. Idempotent.
     */
    public function maybe_finalize_gateway_return(): void {
        $gw = isset( $_GET['ovr_gw'] ) ? sanitize_key( wp_unslash( $_GET['ovr_gw'] ) ) : '';
        if ( ! in_array( $gw, [ 'paypal', 'authorize_net' ], true ) ) {
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

        // Require provider-correlated evidence before any state mutation.
        // An untrusted browser must not mutate another user's payment by
        // supplying only the predictable local payment_id.
        if ( 'paypal' === $gw ) {
            $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
            // Token must be present and match the stored PayPal order ID.
            if ( '' === $token || '' === (string) ( $row['transaction_id'] ?? '' ) || $token !== (string) $row['transaction_id'] ) {
                wp_safe_redirect( add_query_arg( 'ovr_checkout', 'pending', $success_url ) );
                exit;
            }
        } elseif ( 'authorize_net' === $gw ) {
            $x_trans_id = isset( $_GET['x_trans_id'] ) ? sanitize_text_field( wp_unslash( $_GET['x_trans_id'] ) ) : '';
            if ( '' === $x_trans_id ) {
                wp_safe_redirect( add_query_arg( 'ovr_checkout', 'pending', $success_url ) );
                exit;
            }
            // Do not trust x_response_code alone without a transaction ID.
            $x_response_code = isset( $_GET['x_response_code'] ) ? sanitize_text_field( wp_unslash( $_GET['x_response_code'] ) ) : '';
            if ( '' !== $x_response_code && '' === $x_trans_id ) {
                wp_safe_redirect( add_query_arg( 'ovr_checkout', 'pending', $success_url ) );
                exit;
            }
        }

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
                $wpdb->update( $table, [ 'status' => 'failed' ], [ 'id' => (int) $row['id'], 'status' => 'pending' ], [ '%s', '%s' ], [ '%d', '%s' ] );
                if ( $wpdb->rows_affected > 0 ) {
                    do_action( 'ovr_payment_failed', (int) $row['user_id'], [
                        'payment_id' => (int) $row['id'],
                        'gateway'    => $gw,
                        'code'       => (string) ( $res['code'] ?? '' ),
                    ] );
                }
                wp_safe_redirect( add_query_arg( 'ovr_checkout', 'failed', $success_url ) );
                exit;
            }

            wp_safe_redirect( add_query_arg( 'ovr_checkout', 'pending', $success_url ) );
            exit;
        }

        // Atomically transition pending → completed. If another request already
        // completed this payment, the WHERE clause matches zero rows and the
        // completion event fires exactly once.
        $meta = json_decode( (string) ( $row['meta_data'] ?? '' ), true );
        $plan_slug = is_array( $meta ) ? (string) ( $meta['plan_slug'] ?? '' ) : '';
        $promo_from_meta = is_array( $meta ) ? (string) ( $meta['promo_code'] ?? '' ) : '';

        $this->complete_payment_atomically( (int) $row['id'], [
            'payment_id'   => (int) $row['id'],
            'plan_slug'    => $plan_slug,
            'amount'       => (float) $row['amount'],
            'gateway'      => $gw,
            'payment_type' => (string) ( $row['payment_type'] ?? 'subscription' ),
            'promo_code'   => $promo_from_meta,
        ] );

        wp_safe_redirect( add_query_arg( 'ovr_checkout', 'completed', $success_url ) );
        exit;
    }

    /**
     * The buyer backed out at the gateway (PayPal/Authorize.Net send them to cancel_url
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
        // Require that the visitor is the payment owner; otherwise an
        // arbitrary user who guesses payment_id could cancel another's checkout.
        $gw = isset( $_GET['ovr_gw'] ) ? sanitize_key( wp_unslash( $_GET['ovr_gw'] ) ) : '';
        if ( 'authorize_net' === $gw ) {
            $payment_id = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : 0;
            if ( $payment_id ) {
                $owner_row = $wpdb->get_row( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE id = %d", $payment_id ), ARRAY_A );
                if ( $owner_row && (int) $owner_row['user_id'] === get_current_user_id() ) {
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

        // Atomically transition to completed. If another request already
        // completed this payment, the WHERE clause matches zero rows and the
        // completion event fires exactly once.
        $meta = json_decode( (string) ( $row['meta_data'] ?? '' ), true );
        $plan_slug = is_array( $meta ) ? (string) ( $meta['plan_slug'] ?? '' ) : '';
        $promo_from_meta2 = is_array( $meta ) ? (string) ( $meta['promo_code'] ?? '' ) : '';

        $this->complete_payment_atomically( $payment_id, [
            'payment_id'   => $payment_id,
            'plan_slug'    => $plan_slug,
            'amount'       => (float) $row['amount'],
            'gateway'      => (string) ( $row['gateway'] ?? 'admin' ),
            'payment_type' => (string) ( $row['payment_type'] ?? 'subscription' ),
            'promo_code'   => $promo_from_meta2,
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
     * Increment promo code usage when a payment completes.
     */
    public function maybe_increment_promo_use( int $user_id, array $data ): void {
        $payment_id = (int) ( $data['payment_id'] ?? 0 );
        if ( ! $payment_id ) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT meta_data FROM {$table} WHERE id = %d", $payment_id ), ARRAY_A );
        if ( ! $row ) {
            return;
        }
        $meta = json_decode( (string) ( $row['meta_data'] ?? '' ), true );
        $code = is_array( $meta ) ? (string) ( $meta['promo_code'] ?? '' ) : '';
        if ( '' === $code ) {
            $code = (string) ( $data['promo_code'] ?? '' );
        }
        if ( '' !== $code ) {
            PromoCode::increment_use( $code );
        }
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
                <?php esc_html_e( 'No payment gateway credentials are set, so card/PayPal payments are recorded as "pending" until an admin marks them paid. Add your Authorize.Net or PayPal keys under OVR → Settings → Payments to enable live checkout.', 'ovr-core' ); ?>
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
