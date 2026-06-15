<?php
/**
 * Booking repository.
 *
 * CRUD for the wp_ovr_bookings table with three side effects kept in lockstep:
 *   1. Guest upsert into the CRM manifest (+ rollup recompute).
 *   2. A linked availability block (source='booking') so the calendar and
 *      search reflect the reservation. Hard statuses block the dates; soft
 *      statuses (soft_block / owner_hold) stay searchable.
 *   3. An audit-log entry for every create / update / delete.
 *
 * @package OVR\Booking
 * @since   2.0.0
 */

namespace OVR\Booking;

use OVR\Core\Db;
use OVR\Core\AuditLog;
use OVR\Crm\GuestRepository;
use OVR\Property\Availability;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingRepository {

    /** Booking lifecycle statuses (mirror the calendar vocabulary + lifecycle). */
    public const STATUSES = [ 'booked', 'soft_block', 'owner_hold', 'maintenance', 'completed', 'cancelled' ];

    /** Statuses that should NOT hard-block the calendar. */
    private const SOFT_STATUSES = [ 'soft_block', 'owner_hold', 'cancelled' ];

    /** Recognised booking sources. */
    public const SOURCES = [ 'manual', 'website', 'wordpress', 'airbnb', 'vrbo', 'ical', 'other' ];

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_bookings';
    }

    /**
     * Status → human label.
     *
     * @return array<string, string>
     */
    public static function status_labels(): array {
        return [
            'booked'      => __( 'Booked', 'ovr-core' ),
            'soft_block'  => __( 'Soft Block', 'ovr-core' ),
            'owner_hold'  => __( 'Owner Hold', 'ovr-core' ),
            'maintenance' => __( 'Maintenance', 'ovr-core' ),
            'completed'   => __( 'Completed', 'ovr-core' ),
            'cancelled'   => __( 'Cancelled', 'ovr-core' ),
        ];
    }

    /**
     * Fetch a single booking row.
     *
     * @return array<string, mixed>|null
     */
    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d AND deleted_at IS NULL', $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * An owner's bookings, newest check-in first (excludes soft-deleted). Used
     * by the review-request generator to tie a request to a real reservation.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for_owner( int $owner_id, int $limit = 100 ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE owner_id = %d AND deleted_at IS NULL ORDER BY checkin_date DESC, id DESC LIMIT %d',
                $owner_id,
                $limit
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Create a booking and its side effects. Returns the new booking id.
     *
     * @param array<string, mixed> $data Sanitised by the caller (admin form).
     */
    public static function create( array $data ): int {
        global $wpdb;

        $property_id = (int) ( $data['property_id'] ?? 0 );
        $owner_id    = $property_id ? (int) get_post_field( 'post_author', $property_id ) : 0;
        $status      = self::normalise_status( $data['status'] ?? 'booked' );

        // Tie to / create the CRM guest record under the listing's owner.
        $guest_id = GuestRepository::upsert( $owner_id, [
            'name'  => $data['guest_name'] ?? '',
            'email' => $data['guest_email'] ?? '',
            'phone' => $data['guest_phone'] ?? '',
        ] );

        $row = Db::stamp( [
            'property_id'  => $property_id,
            'owner_id'     => $owner_id,
            'guest_id'     => $guest_id ?: null,
            'guest_name'   => sanitize_text_field( $data['guest_name'] ?? '' ),
            'guest_email'  => sanitize_email( $data['guest_email'] ?? '' ),
            'guest_phone'  => sanitize_text_field( $data['guest_phone'] ?? '' ),
            'checkin_date' => self::date_or_null( $data['checkin_date'] ?? '' ),
            'checkout_date'=> self::date_or_null( $data['checkout_date'] ?? '' ),
            'amount'       => round( (float) ( $data['amount'] ?? 0 ), 2 ),
            'currency'     => substr( strtoupper( sanitize_text_field( $data['currency'] ?? 'USD' ) ), 0, 3 ),
            'status'       => $status,
            'source'       => self::normalise_source( $data['source'] ?? 'manual' ),
            'external_ref' => sanitize_text_field( $data['external_ref'] ?? '' ),
            'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
        ], true );

        $wpdb->insert( self::table(), $row );
        $booking_id = (int) $wpdb->insert_id;

        self::sync_calendar_block( $booking_id );
        GuestRepository::recompute_stats( $guest_id );
        AuditLog::record( 'booking.create', 'booking', $booking_id, [
            'property_id' => $property_id,
            'status'      => $status,
            'source'      => $row['source'],
        ] );

        return $booking_id;
    }

    /**
     * Update a booking and re-sync its guest + calendar block.
     */
    public static function update( int $id, array $data ): bool {
        global $wpdb;

        $existing = self::get( $id );
        if ( ! $existing ) {
            return false;
        }

        $property_id = (int) ( $data['property_id'] ?? $existing['property_id'] );
        $owner_id    = $property_id ? (int) get_post_field( 'post_author', $property_id ) : (int) $existing['owner_id'];
        $status      = self::normalise_status( $data['status'] ?? $existing['status'] );

        $guest_id = GuestRepository::upsert( $owner_id, [
            'name'  => $data['guest_name'] ?? $existing['guest_name'],
            'email' => $data['guest_email'] ?? $existing['guest_email'],
            'phone' => $data['guest_phone'] ?? $existing['guest_phone'],
        ] );

        $row = Db::stamp( [
            'property_id'  => $property_id,
            'owner_id'     => $owner_id,
            'guest_id'     => $guest_id ?: null,
            'guest_name'   => sanitize_text_field( $data['guest_name'] ?? $existing['guest_name'] ),
            'guest_email'  => sanitize_email( $data['guest_email'] ?? $existing['guest_email'] ),
            'guest_phone'  => sanitize_text_field( $data['guest_phone'] ?? $existing['guest_phone'] ),
            'checkin_date' => self::date_or_null( $data['checkin_date'] ?? $existing['checkin_date'] ),
            'checkout_date'=> self::date_or_null( $data['checkout_date'] ?? $existing['checkout_date'] ),
            'amount'       => round( (float) ( $data['amount'] ?? $existing['amount'] ), 2 ),
            'currency'     => substr( strtoupper( sanitize_text_field( $data['currency'] ?? $existing['currency'] ) ), 0, 3 ),
            'status'       => $status,
            'source'       => self::normalise_source( $data['source'] ?? $existing['source'] ),
            'external_ref' => sanitize_text_field( $data['external_ref'] ?? $existing['external_ref'] ),
            'notes'        => sanitize_textarea_field( $data['notes'] ?? $existing['notes'] ),
        ], false );

        $wpdb->update( self::table(), $row, [ 'id' => $id ] );

        self::sync_calendar_block( $id );
        // Recompute both the old and new guest (guest may have changed).
        if ( (int) $existing['guest_id'] !== $guest_id ) {
            GuestRepository::recompute_stats( (int) $existing['guest_id'] );
        }
        GuestRepository::recompute_stats( $guest_id );
        AuditLog::record( 'booking.update', 'booking', $id, [ 'status' => $status ] );

        return true;
    }

    /**
     * Soft-delete a booking and remove its calendar block.
     */
    public static function delete( int $id ): bool {
        $existing = self::get( $id );
        if ( ! $existing ) {
            return false;
        }
        self::remove_calendar_block( $id, (int) $existing['property_id'] );
        Db::soft_delete( self::table(), $id );
        GuestRepository::recompute_stats( (int) $existing['guest_id'] );
        AuditLog::record( 'booking.delete', 'booking', $id );
        return true;
    }

    /**
     * Restore a soft-deleted booking (and re-create its calendar block).
     */
    public static function restore( int $id ): bool {
        Db::restore( self::table(), $id );
        self::sync_calendar_block( $id );
        AuditLog::record( 'booking.restore', 'booking', $id );
        return true;
    }

    /**
     * Recreate the linked availability block for a booking from its current
     * row. Idempotent: drops any prior block for this booking first.
     */
    private static function sync_calendar_block( int $booking_id ): void {
        global $wpdb;
        $booking = self::get( $booking_id );
        if ( ! $booking ) {
            return;
        }
        $property_id = (int) $booking['property_id'];
        self::remove_calendar_block( $booking_id, $property_id );

        // Cancelled bookings or those without a full date range hold no dates.
        if ( 'cancelled' === $booking['status'] || empty( $booking['checkin_date'] ) || empty( $booking['checkout_date'] ) ) {
            return;
        }

        // The calendar stores the last blocked NIGHT (checkout is a departure
        // day), so block through checkout minus one day.
        $last_night = wp_date( 'Y-m-d', strtotime( $booking['checkout_date'] . ' -1 day' ) );
        if ( $last_night < $booking['checkin_date'] ) {
            $last_night = $booking['checkin_date'];
        }

        $is_soft = in_array( $booking['status'], self::SOFT_STATUSES, true );

        $avail_id = Availability::insert_block( $property_id, [
            'block_type'        => $booking['status'],
            'start_date'        => $booking['checkin_date'],
            'end_date'          => $last_night,
            'source'            => 'booking',
            'booking_id'        => $booking_id,
            'renter_name'       => $booking['guest_name'],
            'notes'             => $booking['notes'],
            'show_as_available' => $is_soft ? 1 : 0,
        ] );

        if ( $avail_id ) {
            $wpdb->update( self::table(), [ 'availability_id' => $avail_id ], [ 'id' => $booking_id ] );
        }
    }

    /**
     * Delete the availability block(s) belonging to a booking.
     */
    private static function remove_calendar_block( int $booking_id, int $property_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';
        $wpdb->delete( $table, [ 'booking_id' => $booking_id ], [ '%d' ] );
        wp_cache_delete( 'ovr_avail_' . $property_id, 'ovr' );
    }

    private static function normalise_status( string $status ): string {
        $status = sanitize_key( $status );
        return in_array( $status, self::STATUSES, true ) ? $status : 'booked';
    }

    private static function normalise_source( string $source ): string {
        $source = sanitize_key( $source );
        return in_array( $source, self::SOURCES, true ) ? $source : 'manual';
    }

    private static function date_or_null( string $value ): ?string {
        $value = sanitize_text_field( $value );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : null;
    }
}
