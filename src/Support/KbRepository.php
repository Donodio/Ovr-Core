<?php
/**
 * Knowledge Base repository (Feature 12 — Support Center).
 *
 * CRUD over `ovr_kb_articles`: create, edit, categorize, publish/draft/archive
 * and soft-delete help articles. Actor-stamped + audit-logged.
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

class KbRepository {

    /** Publication states. */
    public const STATUSES = [ 'draft', 'published', 'archived' ];

    /** Default categories. */
    public const CATEGORIES = [ 'general', 'getting-started', 'listings', 'billing', 'account', 'policies' ];

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_kb_articles';
    }

    /**
     * @return array<string, string>
     */
    public static function status_labels(): array {
        return [
            'draft'     => __( 'Draft', 'ovr-core' ),
            'published' => __( 'Published', 'ovr-core' ),
            'archived'  => __( 'Archived', 'ovr-core' ),
        ];
    }

    /**
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
     * Published articles, optionally filtered by category.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function published( string $category = '' ): array {
        global $wpdb;
        $sql    = 'SELECT * FROM ' . self::table() . " WHERE status = 'published' AND " . Db::not_deleted();
        $params = [];
        if ( '' !== $category ) {
            $sql     .= ' AND category = %s';
            $params[] = $category;
        }
        $sql .= ' ORDER BY sort_order ASC, title ASC';
        $rows = $params
            ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL
            : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
        return $rows ?: [];
    }

    /**
     * Create or update an article. Returns its id.
     *
     * @param array<string, mixed> $input
     */
    public static function save( array $input, int $id = 0 ): int {
        global $wpdb;

        $status   = sanitize_key( (string) ( $input['status'] ?? 'draft' ) );
        $status   = in_array( $status, self::STATUSES, true ) ? $status : 'draft';
        $category = sanitize_title( (string) ( $input['category'] ?? 'general' ) ) ?: 'general';

        $data = [
            'title'      => substr( (string) ( $input['title'] ?? '' ), 0, 255 ),
            'category'   => substr( $category, 0, 80 ),
            'body'       => (string) ( $input['body'] ?? '' ),
            'status'     => $status,
            'sort_order' => (int) ( $input['sort_order'] ?? 0 ),
        ];

        if ( $id > 0 ) {
            $data = Db::stamp( $data, false );
            $wpdb->update( self::table(), $data, [ 'id' => $id ] );
            AuditLog::record( 'kb.update', 'kb_article', $id, [ 'title' => $data['title'] ] );
            return $id;
        }

        $data['slug'] = self::unique_slug( $input['slug'] ?? ( $data['title'] ?: 'article' ) );
        $data         = Db::stamp( $data, true );
        $wpdb->insert( self::table(), $data );
        $new_id = (int) $wpdb->insert_id;
        AuditLog::record( 'kb.create', 'kb_article', $new_id, [ 'title' => $data['title'] ] );
        return $new_id;
    }

    public static function set_status( int $id, string $status ): void {
        global $wpdb;
        $status = in_array( $status, self::STATUSES, true ) ? $status : 'draft';
        $wpdb->update( self::table(), Db::stamp( [ 'status' => $status ], false ), [ 'id' => $id ] );
        AuditLog::record( 'kb.status', 'kb_article', $id, [ 'status' => $status ] );
    }

    public static function trash( int $id ): void {
        Db::soft_delete( self::table(), $id );
        AuditLog::record( 'kb.delete', 'kb_article', $id );
    }

    public static function restore( int $id ): void {
        Db::restore( self::table(), $id );
        AuditLog::record( 'kb.restore', 'kb_article', $id );
    }

    /**
     * @return array<string, int>
     */
    public static function stats(): array {
        global $wpdb;
        $t = self::table();
        return [
            'published' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE deleted_at IS NULL AND status = 'published'" ),
            'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE deleted_at IS NULL" ),
        ];
    }

    private static function unique_slug( string $text ): string {
        $base = sanitize_title( $text );
        if ( '' === $base ) {
            $base = 'article';
        }
        $base = substr( $base, 0, 200 );
        $slug = $base;
        $n    = 2;
        while ( null !== self::get_by_slug( $slug ) ) {
            $slug = $base . '-' . $n;
            $n++;
        }
        return $slug;
    }
}
