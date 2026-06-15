<?php
/**
 * Shared data helpers: actor stamping + soft delete.
 *
 * Phase 2 business tables carry created_by / updated_by / created_at /
 * updated_at / deleted_at columns. This helper centralises the bookkeeping
 * so every repository stamps and soft-deletes identically and audit-friendly.
 *
 * @package OVR\Core
 * @since   2.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Db {

    /**
     * SQL fragment that excludes soft-deleted rows.
     *
     * @param string $alias Optional table alias (no trailing dot).
     * @return string e.g. "t.deleted_at IS NULL"
     */
    public static function not_deleted( string $alias = '' ): string {
        $prefix = '' !== $alias ? $alias . '.' : '';
        return "{$prefix}deleted_at IS NULL";
    }

    /**
     * Stamp actor + timestamp columns onto a data array prior to insert/update.
     *
     * @param array $data      Column => value (modified in place via return).
     * @param bool  $is_insert True for INSERT (sets created_*), false for UPDATE.
     * @return array The data array with bookkeeping columns added.
     */
    public static function stamp( array $data, bool $is_insert ): array {
        $now  = current_time( 'mysql' );
        $user = get_current_user_id() ?: null;

        $data['updated_at'] = $now;
        $data['updated_by'] = $user;

        if ( $is_insert ) {
            $data['created_at'] = $data['created_at'] ?? $now;
            $data['created_by'] = $user;
        }

        return $data;
    }

    /**
     * Soft-delete a row by primary key. Returns rows affected.
     */
    public static function soft_delete( string $table, int $id, string $pk = 'id' ): int {
        global $wpdb;
        return (int) $wpdb->update(
            $table,
            [
                'deleted_at' => current_time( 'mysql' ),
                'updated_by' => get_current_user_id() ?: null,
            ],
            [ $pk => $id ],
            [ '%s', '%d' ],
            [ '%d' ]
        );
    }

    /**
     * Restore a soft-deleted row by primary key. Returns rows affected.
     */
    public static function restore( string $table, int $id, string $pk = 'id' ): int {
        global $wpdb;
        return (int) $wpdb->update(
            $table,
            [
                'deleted_at' => null,
                'updated_by' => get_current_user_id() ?: null,
            ],
            [ $pk => $id ],
            [ '%s', '%d' ],
            [ '%d' ]
        );
    }
}
