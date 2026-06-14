<?php
/**
 * Availability blocks — shared read/write for manually-managed calendar blocks.
 *
 * Wraps the `wp_ovr_availability` custom table so the landlord's frontend
 * editor, the admin meta box, the Bookings module, and search all use
 * identical logic. Only `source='manual'` rows are touched by the editor
 * helpers; iCal-sourced rows (source='ical') and booking-sourced rows are
 * left intact.
 *
 * Block semantics (Phase 2, Feature 2):
 *   - HARD block  → property unavailable, EXCLUDED from search.
 *                   Statuses: booked, maintenance, blocked.
 *   - SOFT block  → still shows available + searchable (pending reservation,
 *                   potential guest, owner hold). Statuses: soft_block,
 *                   owner_hold.
 *   - AVAILABLE OVERRIDE → blocked internally but kept searchable; any status
 *                   row with show_as_available = 1.
 *
 * The `show_as_available` flag is the single source of truth for search:
 * a row excludes the property only when show_as_available = 0.
 *
 * @package OVR\Property
 * @since   1.0.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Availability {

    /** Statuses that make the dates genuinely unavailable (hard). */
    public const HARD_STATUSES = [ 'booked', 'maintenance', 'blocked' ];

    /** Statuses that keep the listing searchable (soft). */
    public const SOFT_STATUSES = [ 'soft_block', 'owner_hold' ];

    /** Full vocabulary accepted from the editors. */
    public const ALLOWED_TYPES = [ 'booked', 'maintenance', 'blocked', 'soft_block', 'owner_hold' ];

    /** Human labels for the status dropdown. */
    public static function status_labels(): array {
        return [
            'booked'      => __( 'Booked', 'ovr-core' ),
            'soft_block'  => __( 'Soft Block', 'ovr-core' ),
            'owner_hold'  => __( 'Owner Hold', 'ovr-core' ),
            'maintenance' => __( 'Maintenance', 'ovr-core' ),
            'blocked'     => __( 'Blocked', 'ovr-core' ),
        ];
    }

    /**
     * Manually-managed blocks for a property, oldest start first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_manual_blocks( int $post_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE property_id = %d AND source = 'manual' ORDER BY start_date ASC",
                $post_id
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Replace the property's manual blocks with the supplied rows. iCal rows
     * (source='ical') and booking rows (source='booking') are never deleted.
     *
     * @param array $rows Each: [ start_date, end_date, block_type, renter_name,
     *                            notes, show_as_available ].
     */
    public static function save_manual_blocks( int $post_id, $rows ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';

        // Clear only the manually-managed rows for this property.
        $wpdb->delete( $table, [ 'property_id' => $post_id, 'source' => 'manual' ], [ '%d', '%s' ] );

        if ( ! is_array( $rows ) ) {
            return;
        }

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $start = sanitize_text_field( wp_unslash( $row['start_date'] ?? '' ) );
            $end   = sanitize_text_field( wp_unslash( $row['end_date'] ?? '' ) );
            if ( ! self::is_date( $start ) || ! self::is_date( $end ) ) {
                continue;
            }
            // Normalise reversed ranges so end is never before start.
            if ( $end < $start ) {
                [ $start, $end ] = [ $end, $start ];
            }

            // Renter Name is now a first-class column. Fall back to the legacy
            // `notes` field for editors that still post the name there.
            $renter = sanitize_text_field( wp_unslash( $row['renter_name'] ?? '' ) );
            $notes  = sanitize_textarea_field( wp_unslash( $row['notes'] ?? '' ) );
            if ( '' === $renter && '' !== $notes && self::looks_like_name( $notes ) ) {
                $renter = $notes;
            }

            // Status (block_type). A named entry with no explicit status is a
            // booking; an unnamed one just blocks.
            $type = sanitize_key( $row['block_type'] ?? '' );
            if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
                $type = '' !== $renter ? 'booked' : 'blocked';
            }

            // Searchability: soft statuses stay available unless explicitly
            // overridden; an "available override" is any row flagged available.
            $show_available = isset( $row['show_as_available'] )
                ? (int) (bool) $row['show_as_available']
                : ( in_array( $type, self::SOFT_STATUSES, true ) ? 1 : 0 );

            self::insert_block( $post_id, [
                'block_type'        => $type,
                'start_date'        => $start,
                'end_date'          => $end,
                'source'            => 'manual',
                'renter_name'       => $renter,
                'notes'             => $notes,
                'show_as_available' => $show_available,
            ] );
        }

        self::flush_cache( $post_id );
    }

    /**
     * Insert a single availability row. Shared by the editors, the Bookings
     * module, and iCal import. Returns the new row id (or 0 on failure).
     *
     * @param array<string, mixed> $data
     */
    public static function insert_block( int $post_id, array $data ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';

        $inserted = $wpdb->insert( $table, [
            'property_id'       => $post_id,
            'block_type'        => $data['block_type'] ?? 'blocked',
            'start_date'        => $data['start_date'],
            'end_date'          => $data['end_date'],
            'source'            => $data['source'] ?? 'manual',
            'ical_uid'          => $data['ical_uid'] ?? null,
            'renter_name'       => $data['renter_name'] ?? '',
            'booking_id'        => $data['booking_id'] ?? null,
            'notes'             => $data['notes'] ?? '',
            'show_as_available' => isset( $data['show_as_available'] ) ? (int) $data['show_as_available'] : 0,
        ] );

        if ( false === $inserted ) {
            return 0;
        }
        self::flush_cache( $post_id );
        return (int) $wpdb->insert_id;
    }

    /**
     * Whether a property is free for a requested stay [checkin, checkout).
     * A hard block (show_as_available = 0) overlapping the range makes it busy.
     */
    public static function is_available( int $post_id, string $checkin, string $checkout ): bool {
        if ( ! self::is_date( $checkin ) || ! self::is_date( $checkout ) ) {
            return true; // No usable range → don't filter the listing out.
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';

        $conflict = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
              WHERE property_id = %d
                AND show_as_available = 0
                AND start_date < %s
                AND end_date >= %s",
            $post_id,
            $checkout,
            $checkin
        ) );

        return 0 === $conflict;
    }

    /**
     * Property IDs that have a HARD block overlapping the requested stay, so
     * search can exclude them. Soft blocks / available overrides are ignored.
     *
     * @return int[]
     */
    public static function unavailable_property_ids( string $checkin, string $checkout ): array {
        if ( ! self::is_date( $checkin ) || ! self::is_date( $checkout ) ) {
            return [];
        }
        // Treat reversed input gracefully.
        if ( $checkout < $checkin ) {
            [ $checkin, $checkout ] = [ $checkout, $checkin ];
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT property_id FROM {$table}
              WHERE show_as_available = 0
                AND start_date < %s
                AND end_date >= %s",
            $checkout,
            $checkin
        ) );

        return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
    }

    /**
     * Bust the front-end calendar cache so blocks render immediately.
     */
    private static function flush_cache( int $post_id ): void {
        wp_cache_delete( 'ovr_avail_' . $post_id, 'ovr' );
    }

    /**
     * Heuristic: a short, comma/letter string is probably a renter name rather
     * than a long free-form note. Keeps legacy notes-as-name data working.
     */
    private static function looks_like_name( string $value ): bool {
        return strlen( $value ) <= 60 && false === strpos( $value, "\n" );
    }

    /**
     * Strict YYYY-MM-DD validation (matches the <input type="date"> value).
     */
    private static function is_date( string $value ): bool {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return false;
        }
        [ $y, $m, $d ] = array_map( 'intval', explode( '-', $value ) );
        return checkdate( $m, $d, $y );
    }
}
