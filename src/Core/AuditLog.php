<?php
/**
 * Audit Log writer.
 *
 * A single, reusable service every module calls to record who did what to
 * which record. Writes to the existing wp_ovr_audit_log table and captures
 * the acting user + request IP automatically.
 *
 * Usage:
 *   AuditLog::record( 'booking.create', 'booking', $booking_id, [ 'amount' => 250 ] );
 *
 * @package OVR\Core
 * @since   2.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuditLog {

    /**
     * Record an audit entry.
     *
     * @param string   $action      Dot-namespaced action, e.g. "booking.update".
     * @param string   $object_type Object class, e.g. "booking", "guest", "ticket".
     * @param int|null $object_id   Affected record id, if any.
     * @param array    $details     Arbitrary context, stored as JSON.
     * @param int|null $user_id     Override acting user (defaults to current user).
     * @return int|false Insert id, or false on failure.
     */
    public static function record( string $action, string $object_type = '', ?int $object_id = null, array $details = [], ?int $user_id = null ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ovr_audit_log';

        $uid = null === $user_id ? get_current_user_id() : $user_id;

        $inserted = $wpdb->insert(
            $table,
            [
                'user_id'     => $uid ?: null,
                'action'      => substr( $action, 0, 100 ),
                'object_type' => substr( $object_type, 0, 50 ),
                'object_id'   => $object_id ?: null,
                'details'     => $details ? wp_json_encode( $details ) : null,
                'ip_address'  => self::client_ip(),
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        return false === $inserted ? false : (int) $wpdb->insert_id;
    }

    /**
     * Full history for a single object, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for_object( string $object_type, int $object_id, int $limit = 50 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_audit_log';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE object_type = %s AND object_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
                $object_type,
                $object_id,
                $limit
            ),
            ARRAY_A
        );

        return self::decode( $rows );
    }

    /**
     * Recent entries, optionally filtered by action prefix or user.
     *
     * @param array{action?:string,user_id?:int,object_type?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function recent( int $limit = 100, array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_audit_log';

        $where  = [ '1=1' ];
        $params = [];

        if ( ! empty( $filters['action'] ) ) {
            $where[]  = 'action LIKE %s';
            $params[] = $wpdb->esc_like( $filters['action'] ) . '%';
        }
        if ( ! empty( $filters['user_id'] ) ) {
            $where[]  = 'user_id = %d';
            $params[] = (int) $filters['user_id'];
        }
        if ( ! empty( $filters['object_type'] ) ) {
            $where[]  = 'object_type = %s';
            $params[] = $filters['object_type'];
        }

        $params[] = $limit;

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where )
            . ' ORDER BY created_at DESC, id DESC LIMIT %d';

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

        return self::decode( $rows );
    }

    /**
     * Decode the JSON details column for a result set.
     *
     * @param array<int, array<string, mixed>>|null $rows
     * @return array<int, array<string, mixed>>
     */
    private static function decode( ?array $rows ): array {
        if ( ! $rows ) {
            return [];
        }
        foreach ( $rows as &$row ) {
            $row['details'] = ! empty( $row['details'] )
                ? (array) json_decode( (string) $row['details'], true )
                : [];
        }
        return $rows;
    }

    /**
     * Best-effort client IP for the current request.
     */
    private static function client_ip(): ?string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : '';
        if ( '' === $ip ) {
            return null;
        }
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? substr( $ip, 0, 45 ) : null;
    }
}
