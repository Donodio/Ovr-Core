<?php
/**
 * Support Ticket repository (Feature 12 — Support Center).
 *
 * CRUD over `ovr_support_tickets` plus the append-only reply thread in
 * `ovr_ticket_replies`. Actor-stamped, soft-deletable and audit-logged.
 *
 * @package OVR\Support
 * @since   2.0.0
 */

namespace OVR\Support;

use OVR\Core\Db;
use OVR\Core\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TicketRepository {

    /** Ordered lifecycle statuses. */
    public const STATUSES = [ 'open', 'in_progress', 'waiting', 'resolved', 'closed' ];

    /** Priority levels. */
    public const PRIORITIES = [ 'low', 'normal', 'high', 'urgent' ];

    /** Default categories. */
    public const CATEGORIES = [ 'general', 'billing', 'technical', 'listing', 'account' ];

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_support_tickets';
    }

    private static function replies_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_ticket_replies';
    }

    /**
     * Human labels for statuses.
     *
     * @return array<string, string>
     */
    public static function status_labels(): array {
        return [
            'open'        => __( 'Open', 'ovr-core' ),
            'in_progress' => __( 'In Progress', 'ovr-core' ),
            'waiting'     => __( 'Waiting', 'ovr-core' ),
            'resolved'    => __( 'Resolved', 'ovr-core' ),
            'closed'      => __( 'Closed', 'ovr-core' ),
        ];
    }

    /**
     * A single ticket.
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
     * Create a ticket. Returns its id.
     *
     * @param array<string, mixed> $input
     */
    public static function create( array $input ): int {
        global $wpdb;

        $data = [
            'user_id'     => (int) ( $input['user_id'] ?? 0 ),
            'subject'     => substr( (string) ( $input['subject'] ?? '' ), 0, 255 ),
            'category'    => self::sanitize_enum( $input['category'] ?? 'general', self::CATEGORIES, 'general' ),
            'priority'    => self::sanitize_enum( $input['priority'] ?? 'normal', self::PRIORITIES, 'normal' ),
            'message'     => (string) ( $input['message'] ?? '' ),
            'attachments' => isset( $input['attachments'] ) ? wp_json_encode( (array) $input['attachments'] ) : null,
            'status'      => self::sanitize_enum( $input['status'] ?? 'open', self::STATUSES, 'open' ),
            'assigned_to' => ! empty( $input['assigned_to'] ) ? (int) $input['assigned_to'] : null,
        ];
        $data = Db::stamp( $data, true );
        $wpdb->insert( self::table(), $data );
        $id = (int) $wpdb->insert_id;
        AuditLog::record( 'ticket.create', 'ticket', $id, [ 'subject' => $data['subject'] ] );
        return $id;
    }

    /**
     * Update mutable ticket fields.
     *
     * @param array<string, mixed> $input
     */
    public static function update( int $id, array $input ): void {
        global $wpdb;

        $data = [];
        if ( isset( $input['subject'] ) ) {
            $data['subject'] = substr( (string) $input['subject'], 0, 255 );
        }
        if ( isset( $input['category'] ) ) {
            $data['category'] = self::sanitize_enum( $input['category'], self::CATEGORIES, 'general' );
        }
        if ( isset( $input['priority'] ) ) {
            $data['priority'] = self::sanitize_enum( $input['priority'], self::PRIORITIES, 'normal' );
        }
        if ( isset( $input['status'] ) ) {
            $data['status'] = self::sanitize_enum( $input['status'], self::STATUSES, 'open' );
        }
        if ( array_key_exists( 'assigned_to', $input ) ) {
            $data['assigned_to'] = $input['assigned_to'] ? (int) $input['assigned_to'] : null;
        }
        if ( ! $data ) {
            return;
        }
        $data = Db::stamp( $data, false );
        $wpdb->update( self::table(), $data, [ 'id' => $id ] );
        AuditLog::record( 'ticket.update', 'ticket', $id, $data );
    }

    public static function set_status( int $id, string $status ): void {
        self::update( $id, [ 'status' => $status ] );
    }

    public static function trash( int $id ): void {
        Db::soft_delete( self::table(), $id );
        AuditLog::record( 'ticket.delete', 'ticket', $id );
    }

    public static function restore( int $id ): void {
        Db::restore( self::table(), $id );
        AuditLog::record( 'ticket.restore', 'ticket', $id );
    }

    /**
     * Append a reply to a ticket. Optionally moves the status forward.
     */
    public static function add_reply( int $ticket_id, string $message, bool $is_staff, ?array $attachments = null ): int {
        global $wpdb;
        $wpdb->insert( self::replies_table(), [
            'ticket_id'   => $ticket_id,
            'user_id'     => get_current_user_id() ?: 0,
            'is_staff'    => $is_staff ? 1 : 0,
            'message'     => $message,
            'attachments' => $attachments ? wp_json_encode( $attachments ) : null,
            'created_at'  => current_time( 'mysql' ),
        ] );
        $reply_id = (int) $wpdb->insert_id;

        // Touch the ticket so it sorts to the top + audit the activity.
        $wpdb->update( self::table(), Db::stamp( [], false ), [ 'id' => $ticket_id ] );
        AuditLog::record( 'ticket.reply', 'ticket', $ticket_id, [ 'reply_id' => $reply_id, 'staff' => $is_staff ] );
        return $reply_id;
    }

    /**
     * Replies for a ticket, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_replies( int $ticket_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::replies_table() . ' WHERE ticket_id = %d ORDER BY created_at ASC, id ASC',
                $ticket_id
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Headline counts for the support dashboard.
     *
     * @return array<string, int>
     */
    public static function stats(): array {
        global $wpdb;
        $t = self::table();
        $count = static function ( string $where ) use ( $wpdb, $t ): int {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE deleted_at IS NULL AND {$where}" );
        };
        return [
            'open'     => $count( "status = 'open'" ),
            'pending'  => $count( "status IN ('in_progress','waiting')" ),
            'resolved' => $count( "status IN ('resolved','closed')" ),
            'total'    => $count( '1=1' ),
        ];
    }

    /**
     * @param mixed    $value
     * @param string[] $allowed
     */
    private static function sanitize_enum( $value, array $allowed, string $default ): string {
        $value = sanitize_key( (string) $value );
        return in_array( $value, $allowed, true ) ? $value : $default;
    }
}
