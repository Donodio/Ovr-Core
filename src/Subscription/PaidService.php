<?php
/**
 * Paid Service repository (Feature 1 — Listing Upgrades System).
 *
 * The `ovr_paid_services` table is the single source of truth for the
 * promotional upgrade catalogue. Each row is one purchasable offering — a
 * Name, Description, Price, Duration, Badge, Priority Weight and Active flag —
 * mapped to one of the three underlying boost *behaviours* via `service_type`:
 *
 *   top_of_page      → priority placement in search results
 *   homepage_slider  → homepage hero rail (capped by max_simultaneous)
 *   featured         → featured badge + treatment
 *
 * Admins create/edit/disable/delete services freely; the landlord catalogue
 * (ListingUpgrades) and checkout read straight from here. All writes are
 * actor-stamped, soft-deletable and audit-logged.
 *
 * @package OVR\Subscription
 * @since   2.0.0
 */

namespace OVR\Subscription;

use OVR\Core\Db;
use OVR\Core\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PaidService {

    /** One-shot seed guard option. */
    private const SEED_OPTION = 'ovr_paid_services_seeded';

    /**
     * Service-type behaviour metadata (display + boost mapping).
     *
     * @var array<string, array<string, mixed>>
     */
    public const TYPES = [
        'top_of_page' => [
            'label'    => 'Priority Placement (Top of Search)',
            'icon'     => 'vertical_align_top',
            'features' => [ 'Ranks above standard listings in search', 'Only within the visitor’s active filters', 'First purchased ranks highest' ],
        ],
        'homepage_slider' => [
            'label'    => 'Homepage Slider (slideshow only)',
            'icon'     => 'view_carousel',
            'features' => [ 'Rotates in the homepage slideshow', 'Does NOT affect search ranking', 'Limited number of slots' ],
        ],
        'featured' => [
            'label'    => 'Featured Property',
            'icon'     => 'verified',
            'features' => [ 'Top of search results (within matching filters)', 'Placement on the Featured Listings page', "Distinctive 'Featured' badge" ],
        ],
    ];

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_paid_services';
    }

    /**
     * Valid service-type keys.
     *
     * @return string[]
     */
    public static function type_keys(): array {
        return array_keys( self::TYPES );
    }

    /**
     * A single service by id.
     *
     * @return array<string, mixed>|null
     */
    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * A single (non-deleted) service by slug.
     *
     * @return array<string, mixed>|null
     */
    public static function get_by_slug( string $slug ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE slug = %s AND ' . Db::not_deleted(),
                $slug
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Active, non-deleted services in display order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function active(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT * FROM ' . self::table()
            . ' WHERE is_active = 1 AND ' . Db::not_deleted()
            . ' ORDER BY sort_order ASC, priority_weight DESC, price ASC, id ASC',
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Active services of one type, in priority order (highest weight first).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function active_of_type( string $type ): array {
        return array_values( array_filter(
            self::active(),
            static fn( array $r ): bool => $r['service_type'] === $type
        ) );
    }

    /**
     * Create or update a service. Returns the row id.
     *
     * @param array<string, mixed> $input Raw field values.
     * @param int                  $id    0 to insert, else update.
     */
    public static function save( array $input, int $id = 0 ): int {
        global $wpdb;

        $type = in_array( $input['service_type'] ?? '', self::type_keys(), true )
            ? $input['service_type']
            : 'featured';

        $data = [
            'name'             => substr( (string) ( $input['name'] ?? '' ), 0, 150 ),
            'description'      => (string) ( $input['description'] ?? '' ),
            'service_type'     => $type,
            'price'            => round( (float) ( $input['price'] ?? 0 ), 2 ),
            'duration_days'    => max( 1, (int) ( $input['duration_days'] ?? 14 ) ),
            'badge'            => substr( (string) ( $input['badge'] ?? '' ), 0, 60 ),
            'priority_weight'  => (int) ( $input['priority_weight'] ?? 0 ),
            'max_simultaneous' => max( 0, (int) ( $input['max_simultaneous'] ?? 0 ) ),
            'is_renewable'     => empty( $input['is_renewable'] ) ? 0 : 1,
            'auto_renew'       => empty( $input['auto_renew'] ) ? 0 : 1,
            'is_active'        => empty( $input['is_active'] ) ? 0 : 1,
            'sort_order'       => (int) ( $input['sort_order'] ?? 0 ),
        ];

        if ( $id > 0 ) {
            $data = Db::stamp( $data, false );
            $wpdb->update( self::table(), $data, [ 'id' => $id ] );
            AuditLog::record( 'paid_service.update', 'paid_service', $id, [ 'name' => $data['name'], 'type' => $type ] );
            return $id;
        }

        // New row — derive a unique slug from the name (or type).
        $data['slug'] = self::unique_slug( $input['slug'] ?? ( $data['name'] ?: $type ) );
        $data         = Db::stamp( $data, true );
        $wpdb->insert( self::table(), $data );
        $new_id = (int) $wpdb->insert_id;
        AuditLog::record( 'paid_service.create', 'paid_service', $new_id, [ 'name' => $data['name'], 'type' => $type ] );
        return $new_id;
    }

    /** Toggle the active flag. */
    public static function set_active( int $id, bool $active ): void {
        global $wpdb;
        $wpdb->update(
            self::table(),
            Db::stamp( [ 'is_active' => $active ? 1 : 0 ], false ),
            [ 'id' => $id ]
        );
        AuditLog::record( $active ? 'paid_service.enable' : 'paid_service.disable', 'paid_service', $id );
    }

    /** Soft-delete (recoverable). */
    public static function trash( int $id ): void {
        Db::soft_delete( self::table(), $id );
        AuditLog::record( 'paid_service.delete', 'paid_service', $id );
    }

    /** Restore a soft-deleted service. */
    public static function restore( int $id ): void {
        Db::restore( self::table(), $id );
        AuditLog::record( 'paid_service.restore', 'paid_service', $id );
    }

    /**
     * Build a unique, non-deleted slug from arbitrary text.
     */
    private static function unique_slug( string $text ): string {
        $base = sanitize_title( $text );
        if ( '' === $base ) {
            $base = 'service';
        }
        $base = substr( $base, 0, 50 );
        $slug = $base;
        $n    = 2;
        while ( null !== self::get_by_slug( $slug ) ) {
            $slug = substr( $base, 0, 50 - strlen( (string) $n ) - 1 ) . '-' . $n;
            $n++;
        }
        return $slug;
    }

    /**
     * How many homepage-slider slots remain for a service (max_simultaneous cap).
     *
     * Counts properties currently holding a live slider boost. A cap of 0 means
     * unlimited. The property being (re)purchased is excluded so renewals never
     * count against themselves.
     *
     * @return int|null Remaining slots, or null when uncapped.
     */
    public static function remaining_slots( array $service, int $exclude_property = 0 ): ?int {
        $cap = (int) ( $service['max_simultaneous'] ?? 0 );
        if ( $cap <= 0 || 'homepage_slider' !== ( $service['service_type'] ?? '' ) ) {
            return null;
        }
        $in_use = UpgradeActivator::count_active( 'homepage_slider', $exclude_property );
        return max( 0, $cap - $in_use );
    }

    /**
     * Seed the catalogue once, from the legacy hardcoded products, so existing
     * installs keep their three upgrade types working after the table-backed
     * rebuild. Idempotent via the SEED_OPTION guard.
     */
    public static function maybe_seed(): void {
        if ( get_option( self::SEED_OPTION ) ) {
            return;
        }

        global $wpdb;
        $existing = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
        if ( $existing > 0 ) {
            update_option( self::SEED_OPTION, 1 );
            return;
        }

        // Pick up any price overrides the old option-based admin stored.
        $legacy = (array) get_option( 'ovr_paid_services', [] );
        $price  = static function ( string $type, string $key, float $default ) use ( $legacy ): float {
            $v = $legacy[ $type ][ $key ] ?? null;
            return is_numeric( $v ) ? (float) $v : $default;
        };

        $seed = [
            [ 'name' => __( 'Top of Search — 14 Days', 'ovr-core' ), 'service_type' => 'top_of_page',     'price' => $price( 'top_of_page', 'price_14', 49 ),  'duration_days' => 14, 'badge' => '',                                  'priority_weight' => 100, 'max_simultaneous' => 0, 'sort_order' => 10 ],
            [ 'name' => __( 'Top of Search — 30 Days', 'ovr-core' ), 'service_type' => 'top_of_page',     'price' => $price( 'top_of_page', 'price_30', 89 ),  'duration_days' => 30, 'badge' => '',                                  'priority_weight' => 100, 'max_simultaneous' => 0, 'sort_order' => 11 ],
            [ 'name' => __( 'Homepage Slider — 14 Days', 'ovr-core' ), 'service_type' => 'homepage_slider', 'price' => $price( 'homepage_slider', 'price_14', 119 ), 'duration_days' => 14, 'badge' => __( 'Highest Conversion', 'ovr-core' ), 'priority_weight' => 50, 'max_simultaneous' => 8, 'sort_order' => 20 ],
            [ 'name' => __( 'Homepage Slider — 30 Days', 'ovr-core' ), 'service_type' => 'homepage_slider', 'price' => $price( 'homepage_slider', 'price_30', 199 ), 'duration_days' => 30, 'badge' => __( 'Highest Conversion', 'ovr-core' ), 'priority_weight' => 50, 'max_simultaneous' => 8, 'sort_order' => 21 ],
            [ 'name' => __( 'Featured Listing — 14 Days', 'ovr-core' ), 'service_type' => 'featured',       'price' => $price( 'featured', 'price_14', 89 ),  'duration_days' => 14, 'badge' => '',                                  'priority_weight' => 10, 'max_simultaneous' => 0, 'sort_order' => 30 ],
            [ 'name' => __( 'Featured Listing — 30 Days', 'ovr-core' ), 'service_type' => 'featured',       'price' => $price( 'featured', 'price_30', 149 ), 'duration_days' => 30, 'badge' => '',                                  'priority_weight' => 10, 'max_simultaneous' => 0, 'sort_order' => 31 ],
        ];

        foreach ( $seed as $row ) {
            $row['description'] = self::TYPES[ $row['service_type'] ]['label'] ?? '';
            $row['is_active']   = 1;
            self::save( $row );
        }

        update_option( self::SEED_OPTION, 1 );
    }
}
