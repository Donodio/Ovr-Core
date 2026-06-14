<?php
/**
 * Guest repository — CRM master manifest.
 *
 * Guests are deduplicated per owner by email. Bookings and inquiries upsert
 * into this table so the CRM module (Feature 5) has a single source of truth
 * for stay counts, spend, and last-stay date. Soft-deletable + audit-stamped.
 *
 * @package OVR\Crm
 * @since   2.0.0
 */

namespace OVR\Crm;

use OVR\Core\Db;
use OVR\Core\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GuestRepository {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_guests';
    }

    /**
     * Find a guest by owner + email (case-insensitive). Returns row or null.
     *
     * @return array<string, mixed>|null
     */
    public static function find_by_email( int $owner_id, string $email ): ?array {
        global $wpdb;
        $email = trim( strtolower( $email ) );
        if ( '' === $email ) {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE owner_id = %d AND email = %s AND deleted_at IS NULL LIMIT 1',
                $owner_id,
                $email
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Fetch a guest by id.
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
     * Create or update a guest by owner + email, returning the guest id.
     * Only fills blank columns on an existing record so manual CRM edits win.
     *
     * @param array{name?:string,email:string,phone?:string,address?:string,notes?:string,tags?:string,status?:string} $data
     */
    public static function upsert( int $owner_id, array $data ): int {
        global $wpdb;

        $email = trim( strtolower( $data['email'] ?? '' ) );
        $name  = sanitize_text_field( $data['name'] ?? '' );

        $existing = '' !== $email ? self::find_by_email( $owner_id, $email ) : null;

        if ( $existing ) {
            $update = [];
            if ( '' === ( $existing['name'] ?? '' ) && '' !== $name ) {
                $update['name'] = $name;
            }
            foreach ( [ 'phone', 'address', 'notes' ] as $col ) {
                if ( empty( $existing[ $col ] ) && ! empty( $data[ $col ] ) ) {
                    $update[ $col ] = sanitize_text_field( $data[ $col ] );
                }
            }
            if ( $update ) {
                $wpdb->update( self::table(), Db::stamp( $update, false ), [ 'id' => (int) $existing['id'] ] );
            }
            return (int) $existing['id'];
        }

        $row = Db::stamp( [
            'owner_id' => $owner_id,
            'name'     => $name,
            'email'    => $email,
            'phone'    => sanitize_text_field( $data['phone'] ?? '' ),
            'address'  => sanitize_textarea_field( $data['address'] ?? '' ),
            'notes'    => sanitize_textarea_field( $data['notes'] ?? '' ),
            'tags'     => sanitize_text_field( $data['tags'] ?? '' ),
            'status'   => sanitize_key( $data['status'] ?? 'active' ),
        ], true );

        $wpdb->insert( self::table(), $row );
        $id = (int) $wpdb->insert_id;
        AuditLog::record( 'guest.create', 'guest', $id, [ 'email' => $email, 'owner_id' => $owner_id ] );
        return $id;
    }

    /**
     * Recompute a guest's rollups (total stays, spend, last stay) from their
     * non-cancelled bookings. Called after any booking write.
     */
    public static function recompute_stats( int $guest_id ): void {
        global $wpdb;
        if ( ! $guest_id ) {
            return;
        }
        $bookings = $wpdb->prefix . 'ovr_bookings';

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) AS stays, COALESCE(SUM(amount),0) AS spend, MAX(checkout_date) AS last_stay
                   FROM {$bookings}
                  WHERE guest_id = %d AND deleted_at IS NULL AND status <> 'cancelled'",
                $guest_id
            ),
            ARRAY_A
        );

        $wpdb->update(
            self::table(),
            [
                'total_stays' => (int) ( $stats['stays'] ?? 0 ),
                'total_spend' => (float) ( $stats['spend'] ?? 0 ),
                'last_stay'   => $stats['last_stay'] ?: null,
                'updated_at'  => current_time( 'mysql' ),
            ],
            [ 'id' => $guest_id ]
        );
    }

    /**
     * Insert a guest from the admin Add-Guest form. Returns the new id.
     *
     * @param array<string, mixed> $data
     */
    public static function insert( array $data ): int {
        global $wpdb;
        $row = Db::stamp( [
            'owner_id' => (int) ( $data['owner_id'] ?? 0 ),
            'name'     => sanitize_text_field( $data['name'] ?? '' ),
            'email'    => sanitize_email( $data['email'] ?? '' ),
            'phone'    => sanitize_text_field( $data['phone'] ?? '' ),
            'address'  => sanitize_textarea_field( $data['address'] ?? '' ),
            'notes'    => sanitize_textarea_field( $data['notes'] ?? '' ),
            'tags'     => sanitize_text_field( $data['tags'] ?? '' ),
            'status'   => sanitize_key( $data['status'] ?? 'active' ),
        ], true );
        $wpdb->insert( self::table(), $row );
        $id = (int) $wpdb->insert_id;
        AuditLog::record( 'guest.create', 'guest', $id, [ 'email' => $row['email'], 'via' => 'admin' ] );
        return $id;
    }

    /**
     * Full update of an existing guest (admin edit).
     *
     * @param array<string, mixed> $data
     */
    public static function update( int $id, array $data ): bool {
        global $wpdb;
        $row = Db::stamp( [
            'name'    => sanitize_text_field( $data['name'] ?? '' ),
            'email'   => sanitize_email( $data['email'] ?? '' ),
            'phone'   => sanitize_text_field( $data['phone'] ?? '' ),
            'address' => sanitize_textarea_field( $data['address'] ?? '' ),
            'notes'   => sanitize_textarea_field( $data['notes'] ?? '' ),
            'tags'    => sanitize_text_field( $data['tags'] ?? '' ),
            'status'  => sanitize_key( $data['status'] ?? 'active' ),
        ], false );
        $ok = false !== $wpdb->update( self::table(), $row, [ 'id' => $id ] );
        AuditLog::record( 'guest.update', 'guest', $id );
        return $ok;
    }

    /**
     * Soft-delete a guest.
     */
    public static function delete( int $id ): bool {
        Db::soft_delete( self::table(), $id );
        AuditLog::record( 'guest.delete', 'guest', $id );
        return true;
    }

    /**
     * Dashboard segment counts.
     *
     * @return array{total:int,repeat:int,high_value:int,new30:int}
     */
    public static function dashboard_stats( float $threshold ): array {
        global $wpdb;
        $t      = self::table();
        $cutoff = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days', (int) current_time( 'timestamp' ) ) );

        return [
            'total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE deleted_at IS NULL" ),
            'repeat'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE deleted_at IS NULL AND total_stays > 1" ),
            'high_value' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE deleted_at IS NULL AND total_spend >= %f", $threshold ) ),
            'new30'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE deleted_at IS NULL AND created_at >= %s", $cutoff ) ),
        ];
    }

    /**
     * A guest's booking (stay) history, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function stay_history( int $guest_id ): array {
        global $wpdb;
        $bookings = $wpdb->prefix . 'ovr_bookings';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$bookings} WHERE guest_id = %d AND deleted_at IS NULL ORDER BY checkin_date DESC, id DESC",
                $guest_id
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * A guest's inquiry history, matched by guest_id or email, newest first.
     *
     * @param array<string, mixed> $guest
     * @return array<int, array<string, mixed>>
     */
    public static function inquiry_history( array $guest ): array {
        global $wpdb;
        $inq   = $wpdb->prefix . 'ovr_inquiries';
        $id    = (int) ( $guest['id'] ?? 0 );
        $email = (string) ( $guest['email'] ?? '' );
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$inq} WHERE guest_id = %d OR ( %s <> '' AND guest_email = %s ) ORDER BY created_at DESC",
                $id,
                $email,
                $email
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }
}
