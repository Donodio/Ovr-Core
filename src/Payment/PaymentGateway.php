<?php
/**
 * Payment Gateway Interface.
 *
 * Phase 1 ships a Stripe stub. Phase 2 (or any third party) implements
 * this interface to add a real provider.
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface PaymentGateway {

    /** Unique slug — used as gateway in wp_ovr_payments. */
    public function get_id(): string;

    /** Human label for the dashboard. */
    public function get_label(): string;

    /** Whether the gateway is fully configured (API keys present). */
    public function is_configured(): bool;

    /**
     * Start a checkout. Returns a redirect URL or an error.
     *
     * Generic purchase payload (see the GatewayPayload trait):
     *   user_id, amount, currency, return_url, cancel_url  — always.
     *   payment_type  string  'subscription' (default) or e.g. 'listing_upgrade'.
     *   meta          array   stored verbatim as the payment row's meta_data.
     *   item_name     string  human label for the line item / receipt.
     *   plan_slug     string  legacy subscription shortcut (folded into meta).
     *
     * @param array $args
     * @return array{success:bool, redirect_url?:string, payment_id?:int, message?:string}
     */
    public function start_checkout( array $args ): array;

    /**
     * Handle a webhook from the provider. Phase 1 stub returns true.
     *
     * @return array{success:bool, message?:string}
     */
    public function handle_webhook( array $payload ): array;
}
