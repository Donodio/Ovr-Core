<?php
/**
 * Listing Upgrade catalogue.
 *
 * Thin presentational facade over the admin-managed `ovr_paid_services` table
 * (see PaidService). Each active service row is one purchasable boost product.
 * Purchases are recorded as payments; UpgradeActivator turns a *completed*
 * payment into a live, time-boxed boost on the chosen listing.
 *
 * Products are keyed by the service slug. The shape is kept backward-compatible
 * with the prior hardcoded catalogue (price_14 / price_30 mirror the single
 * price) so checkout, payment-success and history screens need no rework.
 *
 * @package OVR\Subscription
 * @since   1.0.0
 */

namespace OVR\Subscription;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ListingUpgrades {

    /**
     * All purchasable upgrade products, keyed by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_products(): array {
        $products = [];

        foreach ( PaidService::active() as $row ) {
            $type  = (string) $row['service_type'];
            $meta  = PaidService::TYPES[ $type ] ?? [];
            $price = (float) $row['price'];
            $slug  = (string) $row['slug'];

            $products[ $slug ] = [
                'id'             => $slug,
                'db_id'          => (int) $row['id'],
                'service_type'   => $type,
                'icon'           => $meta['icon'] ?? 'star',
                'name'           => (string) $row['name'],
                'desc'           => (string) $row['description'],
                'description'    => (string) $row['description'],
                'price'          => $price,
                'price_14'       => $price, // BC: single price mirrored onto legacy tier keys.
                'price_30'       => $price,
                'duration_days'  => (int) $row['duration_days'],
                'badge'          => (string) $row['badge'],
                'priority_weight'=> (int) $row['priority_weight'],
                'max_simultaneous'=> (int) $row['max_simultaneous'],
                'features'       => $meta['features'] ?? [],
                'highlight'      => ( 'homepage_slider' === $type ),
            ];
        }

        /**
         * Filter the listing-upgrade catalogue.
         *
         * @param array $products Upgrade products keyed by slug.
         */
        return (array) apply_filters( 'ovr_listing_upgrades', $products );
    }

    /**
     * A single upgrade product by slug.
     *
     * Falls back to a synthetic product when the slug is unknown (e.g. an
     * in-flight legacy payment that referenced a service-type slug directly),
     * so history/receipt screens always resolve a readable name.
     *
     * @return array<string, mixed>|null
     */
    public static function get_product( string $slug ): ?array {
        $product = self::get_products()[ $slug ] ?? null;
        if ( $product ) {
            return $product;
        }

        if ( isset( PaidService::TYPES[ $slug ] ) ) {
            $meta = PaidService::TYPES[ $slug ];
            return [
                'id'            => $slug,
                'service_type'  => $slug,
                'icon'          => $meta['icon'] ?? 'star',
                'name'          => $meta['label'] ?? ucfirst( str_replace( '_', ' ', $slug ) ),
                'desc'          => '',
                'description'   => '',
                'price'         => 0.0,
                'price_14'      => 0.0,
                'price_30'      => 0.0,
                'duration_days' => 14,
                'badge'         => '',
                'features'      => $meta['features'] ?? [],
                'highlight'     => false,
            ];
        }

        return null;
    }

    /**
     * Price for a product. The catalogue is now single-price per service, so
     * the legacy $term argument is accepted but ignored when a flat price exists.
     */
    public static function price_for( array $product, int $term = 0 ): float {
        if ( isset( $product['price'] ) ) {
            return (float) $product['price'];
        }
        return (float) ( 30 === $term ? ( $product['price_30'] ?? 0 ) : ( $product['price_14'] ?? 0 ) );
    }
}
