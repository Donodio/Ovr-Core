<?php
/**
 * Stripe Gateway — Phase 1 stub.
 *
 * Records a 'pending' wp_ovr_payments row and returns a placeholder URL.
 * Phase 2 will replace start_checkout() with a real Stripe Checkout
 * Sessions API call (stripe-php). The interface stays the same.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class StripeGateway implements PaymentGateway {

    public function get_id(): string {
        return 'stripe';
    }

    public function get_label(): string {
        return __( 'Stripe', 'ovr-core' );
    }

    public function is_configured(): bool {
        $settings = get_option( 'ovr_settings', [] );
        return ! empty( $settings['stripe_publishable_key'] ) && ! empty( $settings['stripe_secret_key'] );
    }

    public function start_checkout( array $args ): array {
        $user_id   = (int) ( $args['user_id']   ?? 0 );
        $plan_slug = (string) ( $args['plan_slug'] ?? '' );
        $amount    = (float) ( $args['amount']  ?? 0 );
        $currency  = (string) ( $args['currency'] ?? 'USD' );

        if ( ! $user_id ) {
            return [ 'success' => false, 'message' => __( 'Authentication required.', 'ovr-core' ) ];
        }
        if ( ! $plan_slug || $amount <= 0 ) {
            return [ 'success' => false, 'message' => __( 'Invalid plan or amount.', 'ovr-core' ) ];
        }

        // Always create a pending row so the admin sees the intent.
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_payments';

        $inserted = $wpdb->insert( $table, [
            'user_id'        => $user_id,
            'payment_type'   => 'subscription',
            'amount'         => $amount,
            'currency'       => strtoupper( substr( $currency, 0, 3 ) ),
            'gateway'        => $this->get_id(),
            'transaction_id' => '',
            'status'         => 'pending',
            'meta_data'      => wp_json_encode( [ 'plan_slug' => $plan_slug ] ),
        ], [ '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ] );

        if ( false === $inserted ) {
            return [ 'success' => false, 'message' => __( 'Could not create payment record.', 'ovr-core' ) ];
        }

        $payment_id = (int) $wpdb->insert_id;

        // Phase 1 stub: configured? send to a "Phase 2" notice page; not
        // configured? same. Phase 2 will replace this with a real Stripe URL.
        $redirect = add_query_arg( [
            'ovr_checkout' => 'pending',
            'payment_id'   => $payment_id,
            'plan'         => $plan_slug,
        ], $args['return_url'] ?? home_url( '/' ) );

        do_action( 'ovr_checkout_started', $payment_id, $args, $this->get_id() );

        return [
            'success'      => true,
            'payment_id'   => $payment_id,
            'redirect_url' => $redirect,
            'message'      => $this->is_configured()
                ? __( 'Redirecting to Stripe…', 'ovr-core' )
                : __( 'Stripe is not configured yet — payment recorded as pending. Configure API keys in Phase 2 to enable live checkout.', 'ovr-core' ),
        ];
    }

    public function handle_webhook( array $payload ): array {
        // Phase 2: verify signature, find payment row by transaction_id,
        // flip status to 'completed', extend user's subscription_expires.
        do_action( 'ovr_stripe_webhook', $payload );
        return [ 'success' => true, 'message' => 'Stub — implemented in Phase 2.' ];
    }
}
