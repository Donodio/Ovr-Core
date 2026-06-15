<?php
/**
 * Gateway payload helpers.
 *
 * Lets every gateway accept a *generic* purchase instead of assuming a
 * subscription plan. A checkout request may carry:
 *
 *   payment_type  string  e.g. 'subscription' (default) or 'listing_upgrade'
 *   meta          array   stored verbatim as the payment row's meta_data JSON
 *   item_name     string  human label for the line item / receipt
 *   plan_slug     string  legacy subscription shortcut (folded into meta + name)
 *
 * @package OVR\Payment
 * @since   1.0.0
 */

namespace OVR\Payment;

use OVR\Subscription\Plans;

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait GatewayPayload {

    /** Resolve the payment_type for the row (defaults to subscription). */
    protected function payload_type( array $args ): string {
        $type = (string) ( $args['payment_type'] ?? 'subscription' );
        return '' !== $type ? $type : 'subscription';
    }

    /**
     * Resolve the meta_data array for the row. Starts from an explicit `meta`
     * payload and folds in `plan_slug` for backward-compatible subscriptions.
     *
     * @return array<string, mixed>
     */
    protected function payload_meta( array $args ): array {
        $meta = ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) ? $args['meta'] : [];
        if ( ! empty( $args['plan_slug'] ) && empty( $meta['plan_slug'] ) ) {
            $meta['plan_slug'] = (string) $args['plan_slug'];
        }
        return $meta;
    }

    /** A human label for the line item / receipt. */
    protected function payload_item_name( array $args ): string {
        $name = (string) ( $args['item_name'] ?? '' );
        if ( '' !== $name ) {
            return $name;
        }
        if ( ! empty( $args['plan_slug'] ) ) {
            $plan = Plans::get_plan( (string) $args['plan_slug'] );
            if ( $plan && ! empty( $plan['name'] ) ) {
                return (string) $plan['name'];
            }
        }
        return __( 'OVR Purchase', 'ovr-core' );
    }

    /** Minimal validity: a logged-in buyer and a positive amount. */
    protected function payload_valid( array $args ): bool {
        return (int) ( $args['user_id'] ?? 0 ) > 0 && (float) ( $args['amount'] ?? 0 ) > 0;
    }
}
