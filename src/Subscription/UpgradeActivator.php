<?php
/**
 * Listing Upgrade Activator.
 *
 * Turns a *paid* listing-upgrade purchase into a real, time-boxed boost on a
 * specific property, and tears it back down when it expires. This is the
 * backend the upgrade catalogue (ListingUpgrades) was missing.
 *
 * Each upgrade product maps to one boolean flag + an expiry date stored on the
 * property:
 *
 *   top_of_page      → _ovr_is_bumped   / _ovr_bump_expires     (search top rows)
 *   homepage_slider  → _ovr_in_slider   / _ovr_slider_expires   (homepage rail)
 *   featured         → _ovr_is_featured / _ovr_featured_expires (gold treatment)
 *
 * Activation is driven by `ovr_payment_completed`: the moment a listing_upgrade
 * payment is marked completed (wallet/free instantly; card/PayPal when the
 * gateway confirms or an admin marks it paid), the boost goes live. Nothing
 * activates on an unpaid/pending payment.
 *
 * @package OVR\Subscription
 * @since   1.0.0
 */

namespace OVR\Subscription;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class UpgradeActivator {

    /**
     * product id => [ flag meta key, expiry meta key ].
     *
     * @var array<string, array{flag:string, expires:string}>
     */
    private const MAP = [
        'top_of_page'     => [ 'flag' => '_ovr_is_bumped',   'expires' => '_ovr_bump_expires' ],
        'homepage_slider' => [ 'flag' => '_ovr_in_slider',   'expires' => '_ovr_slider_expires' ],
        'featured'        => [ 'flag' => '_ovr_is_featured', 'expires' => '_ovr_featured_expires' ],
    ];

    /**
     * Human labels for the boost behaviours (independent of the catalogue).
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'top_of_page'     => 'Priority Placement',
        'homepage_slider' => 'Homepage Slider',
        'featured'        => 'Featured Property',
    ];

    public function init(): void {
        // Activate the boost when its payment is confirmed (any completion path:
        // wallet, free, Stripe finalize, or admin "mark paid").
        add_action( 'ovr_payment_completed', [ $this, 'on_payment_completed' ], 10, 2 );

        // Clear expired boosts on the same daily cron the subscription
        // lifecycle already runs.
        add_action( Lifecycle::CRON_HOOK, [ $this, 'expire_due' ] );
    }

    /**
     * All known upgrade product ids.
     *
     * @return string[]
     */
    public static function product_ids(): array {
        return array_keys( self::MAP );
    }

    /**
     * Fire a boost for a property. Idempotent: re-buying extends the term from
     * the later of "today" or the current (still-active) expiry, so a renewal
     * stacks instead of shortening an existing boost.
     */
    public static function activate( int $property_id, string $upgrade_id, int $term_days ): bool {
        if ( ! isset( self::MAP[ $upgrade_id ] ) || $property_id <= 0 ) {
            return false;
        }
        $term_days = max( 1, $term_days );
        $keys      = self::MAP[ $upgrade_id ];
        $today     = current_time( 'Y-m-d' );

        // Extend from the current expiry if it's still in the future.
        $current = (string) get_post_meta( $property_id, $keys['expires'], true );
        $base    = ( $current && $current >= $today ) ? $current : $today;
        $expires = gmdate( 'Y-m-d', strtotime( $base . ' +' . $term_days . ' days' ) );

        update_post_meta( $property_id, $keys['flag'], '1' );
        update_post_meta( $property_id, $keys['expires'], $expires );

        do_action( 'ovr_upgrade_activated', $property_id, $upgrade_id, $expires );
        return true;
    }

    /**
     * Activate a boost with an explicit expiry date. Used by admin-assigned
     * complimentary services, which carry their own end date. Never shortens an
     * existing, later expiry, so a comp grant can never cut short a still-live
     * (paid or longer comp) boost of the same type.
     */
    public static function activate_until( int $property_id, string $upgrade_id, string $expires ): bool {
        if ( ! isset( self::MAP[ $upgrade_id ] ) || $property_id <= 0 || '' === $expires ) {
            return false;
        }
        $keys    = self::MAP[ $upgrade_id ];
        $current = (string) get_post_meta( $property_id, $keys['expires'], true );
        if ( '' !== $current && $current > $expires ) {
            $expires = $current; // keep the later expiry
        }
        update_post_meta( $property_id, $keys['flag'], '1' );
        update_post_meta( $property_id, $keys['expires'], $expires );
        do_action( 'ovr_upgrade_activated', $property_id, $upgrade_id, $expires );
        return true;
    }

    /**
     * Remove a boost from a property.
     */
    public static function deactivate( int $property_id, string $upgrade_id ): void {
        if ( ! isset( self::MAP[ $upgrade_id ] ) ) {
            return;
        }
        $keys = self::MAP[ $upgrade_id ];
        update_post_meta( $property_id, $keys['flag'], '0' );
        update_post_meta( $property_id, $keys['expires'], '' );
        do_action( 'ovr_upgrade_deactivated', $property_id, $upgrade_id );
    }

    /**
     * Is a given boost currently live on a property? (Flag set AND not expired.)
     */
    public static function is_active( int $property_id, string $upgrade_id ): bool {
        if ( ! isset( self::MAP[ $upgrade_id ] ) ) {
            return false;
        }
        $keys = self::MAP[ $upgrade_id ];
        if ( '1' !== (string) get_post_meta( $property_id, $keys['flag'], true ) ) {
            return false;
        }
        $expires = (string) get_post_meta( $property_id, $keys['expires'], true );
        // No expiry recorded (e.g. legacy/admin-set) counts as active.
        return '' === $expires || $expires >= current_time( 'Y-m-d' );
    }

    /**
     * Product ids currently live on a property.
     *
     * @return string[]
     */
    public static function active_products( int $property_id ): array {
        $out = [];
        foreach ( array_keys( self::MAP ) as $id ) {
            if ( self::is_active( $property_id, $id ) ) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Human-readable names of the boosts live on a property.
     *
     * @return string[]
     */
    public static function active_labels( int $property_id ): array {
        $labels = [];
        foreach ( self::active_products( $property_id ) as $id ) {
            $labels[] = self::TYPE_LABELS[ $id ] ?? ucfirst( str_replace( '_', ' ', $id ) );
        }
        return $labels;
    }

    /**
     * Count properties currently holding a live boost of a given type.
     *
     * Used to enforce a service's `max_simultaneous` cap (e.g. homepage slider
     * slots). $exclude_property is skipped so a renewal never counts itself.
     */
    public static function count_active( string $type, int $exclude_property = 0 ): int {
        if ( ! isset( self::MAP[ $type ] ) ) {
            return 0;
        }
        $keys  = self::MAP[ $type ];
        $today = current_time( 'Y-m-d' );

        $args = [
            'post_type'      => 'ovr_property',
            'post_status'    => 'any',
            'posts_per_page' => 999,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => $keys['flag'], 'value' => '1' ],
                [
                    'relation' => 'OR',
                    [ 'key' => $keys['expires'], 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
                    [ 'key' => $keys['expires'], 'value' => '', 'compare' => '=' ],
                ],
            ],
        ];
        if ( $exclude_property > 0 ) {
            $args['post__not_in'] = [ $exclude_property ];
        }

        $q = new \WP_Query( $args );
        return (int) $q->post_count;
    }

    /**
     * Expiry date (Y-m-d) of a boost, or '' if not set.
     */
    public static function expires_for( int $property_id, string $upgrade_id ): string {
        if ( ! isset( self::MAP[ $upgrade_id ] ) ) {
            return '';
        }
        return (string) get_post_meta( $property_id, self::MAP[ $upgrade_id ]['expires'], true );
    }

    /**
     * Activate the purchased boost once its payment is confirmed.
     *
     * Reads the canonical purchase details from the payment row's meta_data so
     * it works for every completion path (wallet/free fire it directly; Stripe
     * finalize and the admin "mark paid" action re-fire it with the row's
     * payment_type).
     *
     * @param int   $user_id Buyer.
     * @param array $context { payment_id, payment_type?, ... }.
     */
    public function on_payment_completed( int $user_id, array $context = [] ): void {
        $payment_id = (int) ( $context['payment_id'] ?? 0 );
        if ( ! $payment_id ) {
            return;
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ovr_payments WHERE id = %d", $payment_id ),
            ARRAY_A
        );
        if ( ! $row || 'listing_upgrade' !== (string) ( $row['payment_type'] ?? '' ) ) {
            return;
        }

        $meta        = json_decode( (string) ( $row['meta_data'] ?? '' ), true );
        $meta        = is_array( $meta ) ? $meta : [];
        $slug        = (string) ( $meta['upgrade'] ?? '' );
        $type        = (string) ( $meta['service_type'] ?? '' );
        $term        = (int) ( $meta['term'] ?? 0 );
        $property_id = (int) ( $meta['property_id'] ?? 0 );

        // Resolve the boost behaviour + duration from the catalogue when the
        // payment meta only carried a service slug (the normal path). Falls
        // back to a raw service-type slug for any legacy in-flight payment.
        if ( '' === $type || $term <= 0 ) {
            $product = ListingUpgrades::get_product( $slug );
            if ( $product ) {
                $type = $type ?: (string) ( $product['service_type'] ?? '' );
                $term = $term > 0 ? $term : (int) ( $product['duration_days'] ?? 14 );
            } elseif ( isset( self::MAP[ $slug ] ) ) {
                $type = $slug;
                $term = $term > 0 ? $term : 14;
            }
        }

        if ( '' === $type || ! $property_id ) {
            return;
        }

        // Ownership guard: the boosted property must belong to the payer.
        $property = get_post( $property_id );
        if ( ! $property
            || 'ovr_property' !== $property->post_type
            || (int) $property->post_author !== (int) $row['user_id'] ) {
            return;
        }

        self::activate( $property_id, $type, $term );
    }

    /**
     * Daily sweep: clear any boost whose expiry date has passed.
     */
    public function expire_due(): void {
        $today = current_time( 'Y-m-d' );

        foreach ( self::MAP as $keys ) {
            $q = new \WP_Query( [
                'post_type'      => 'ovr_property',
                'post_status'    => 'any',
                'posts_per_page' => 999,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    'relation' => 'AND',
                    [ 'key' => $keys['flag'], 'value' => '1' ],
                    [
                        'key'     => $keys['expires'],
                        'value'   => $today,
                        'compare' => '<',
                        'type'    => 'DATE',
                    ],
                ],
            ] );

            foreach ( $q->posts as $pid ) {
                update_post_meta( (int) $pid, $keys['flag'], '0' );
                update_post_meta( (int) $pid, $keys['expires'], '' );
            }
        }
    }
}
