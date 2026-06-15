<?php
/**
 * Backblaze offload manager (Feature E).
 *
 * When B2 is configured, every newly-generated attachment (the original plus
 * each generated size) is uploaded to B2 and recorded in wp_ovr_file_storage.
 * Public URLs are then rewritten to the B2 download URL so files are served
 * from B2 rather than the local server. Deleting an attachment removes its B2
 * objects too.
 *
 * Local copies are kept by default (the photo editor — rotate/crop/watermark —
 * operates on local files). Enabling "Delete local copies" in Settings → Storage
 * reclaims disk space at the cost of server-side re-editing of already-uploaded
 * photos.
 *
 * @package OVR\Storage
 * @since   2.1.0
 */

namespace OVR\Storage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StorageOffloader {

    public function init(): void {
        // Only wire offloading when B2 is configured; URL rewriting is always
        // safe (it no-ops when there's no mapping) so it can run regardless.
        add_filter( 'wp_generate_attachment_metadata', [ $this, 'offload' ], 20, 2 );
        add_filter( 'wp_get_attachment_url', [ $this, 'filter_url' ], 20, 2 );
        add_filter( 'wp_get_attachment_image_src', [ $this, 'filter_src' ], 20, 3 );
        add_action( 'delete_attachment', [ $this, 'on_delete' ] );
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_file_storage';
    }

    /* ─────────────────────── F13: monitoring + recovery ─────────────────────── */

    /**
     * Dashboard counters (M3 F13).
     *
     * @return array{rows:int,attachments:int,bytes:int,images_total:int,pending:int,local_missing:int}
     */
    public static function stats(): array {
        global $wpdb;
        $table = self::table();

        $rows        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $attachments = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT attachment_id) FROM {$table}" );
        $bytes       = (int) $wpdb->get_var( "SELECT COALESCE(SUM(file_size),0) FROM {$table}" );

        $images_total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'"
        );

        // Image attachments with no 'full' offload row yet.
        $pending = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%'
               AND NOT EXISTS ( SELECT 1 FROM {$table} f WHERE f.attachment_id = p.ID AND f.size_name = 'full' )"
        );

        // Offloaded originals whose local copy is gone (recovery candidates).
        $local_missing = 0;
        $full_rows     = (array) $wpdb->get_col( "SELECT attachment_id FROM {$table} WHERE size_name = 'full'" );
        foreach ( $full_rows as $aid ) {
            $path = get_attached_file( (int) $aid );
            if ( $path && ! file_exists( $path ) ) {
                $local_missing++;
            }
        }

        return [
            'rows'          => $rows,
            'attachments'   => $attachments,
            'bytes'         => $bytes,
            'images_total'  => $images_total,
            'pending'       => $pending,
            'local_missing' => $local_missing,
        ];
    }

    /** Recent offload rows for the dashboard table. @return array<int,array<string,mixed>> */
    public static function recent( int $limit = 15 ): array {
        global $wpdb;
        return (array) $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit ),
            ARRAY_A
        );
    }

    /**
     * Offload image attachments that haven't been offloaded yet (M3 F13 recovery
     * / catch-up tool). Processes up to $limit attachments. Returns the count
     * successfully offloaded.
     */
    public function offload_pending( int $limit = 20 ): int {
        if ( ! BackblazeB2Client::is_configured() ) {
            return 0;
        }
        global $wpdb;
        $table = self::table();
        $ids   = (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%'
               AND NOT EXISTS ( SELECT 1 FROM {$table} f WHERE f.attachment_id = p.ID AND f.size_name = 'full' )
             ORDER BY p.ID DESC LIMIT %d",
            $limit
        ) );

        $done = 0;
        foreach ( $ids as $aid ) {
            $aid  = (int) $aid;
            $meta = wp_get_attachment_metadata( $aid );
            $this->offload( is_array( $meta ) ? $meta : [], $aid );
            if ( self::get_row( $aid, 'full' ) ) {
                $done++;
            }
        }
        return $done;
    }

    /**
     * Restore originals from B2 whose local file is missing (M3 F13 recovery).
     * Downloads the B2 copy back to the local path and regenerates sized
     * derivatives. Processes up to $limit attachments; returns the restored count.
     */
    public function restore_missing( int $limit = 20 ): int {
        global $wpdb;
        $table = self::table();
        $rows  = (array) $wpdb->get_results(
            "SELECT attachment_id, file_url FROM {$table} WHERE size_name = 'full' ORDER BY id DESC",
            ARRAY_A
        );

        $restored = 0;
        foreach ( $rows as $row ) {
            if ( $restored >= $limit ) {
                break;
            }
            $aid  = (int) $row['attachment_id'];
            $path = get_attached_file( $aid );
            if ( ! $path || file_exists( $path ) ) {
                continue; // local copy present — nothing to restore.
            }
            $url = (string) $row['file_url'];
            if ( '' === $url ) {
                continue;
            }
            $resp = wp_remote_get( $url, [ 'timeout' => 30 ] );
            if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
                continue;
            }
            $body = wp_remote_retrieve_body( $resp );
            if ( '' === $body ) {
                continue;
            }
            wp_mkdir_p( dirname( $path ) );
            if ( false === file_put_contents( $path, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
                continue;
            }
            // Rebuild sized derivatives from the restored original.
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $meta = wp_generate_attachment_metadata( $aid, $path );
            if ( is_array( $meta ) ) {
                wp_update_attachment_metadata( $aid, $meta );
            }
            $restored++;
        }
        return $restored;
    }

    /**
     * Offload the original + each generated size to B2 after metadata is built.
     *
     * @param array $metadata
     * @param int   $attachment_id
     * @return array Unmodified metadata (we only side-effect).
     */
    public function offload( $metadata, $attachment_id ) {
        if ( ! BackblazeB2Client::is_configured() ) {
            return $metadata;
        }

        $original = get_attached_file( (int) $attachment_id );
        if ( ! $original || ! file_exists( $original ) ) {
            return $metadata;
        }

        $rel = (string) get_post_meta( (int) $attachment_id, '_wp_attached_file', true );
        if ( '' === $rel ) {
            return $metadata;
        }
        $dir_rel = ltrim( dirname( $rel ), '/.' );
        $dir_rel = '' === $dir_rel ? '' : trailingslashit( $dir_rel );
        $base    = trailingslashit( dirname( $original ) );

        $delete_local = ! empty( BackblazeB2Client::settings()['b2_delete_local'] );

        // Original ("full").
        $this->offload_one( (int) $attachment_id, 'full', $original, $rel, $delete_local, $metadata );

        // Generated sizes.
        if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $size_name => $size ) {
                if ( empty( $size['file'] ) ) {
                    continue;
                }
                $path = $base . $size['file'];
                $key  = $dir_rel . $size['file'];
                $this->offload_one( (int) $attachment_id, (string) $size_name, $path, $key, $delete_local, $metadata );
            }
        }

        return $metadata;
    }

    /**
     * Offload a single file + record the mapping (idempotent per attachment+size).
     */
    private function offload_one( int $attachment_id, string $size_name, string $path, string $key, bool $delete_local, array $metadata ): void {
        if ( ! file_exists( $path ) ) {
            return;
        }
        if ( self::get_row( $attachment_id, $size_name ) ) {
            return; // already offloaded.
        }

        $result = BackblazeB2Client::upload( $path, $key );
        if ( ! $result ) {
            return;
        }

        global $wpdb;
        $wpdb->insert( self::table(), [
            'attachment_id' => $attachment_id,
            'size_name'     => $size_name,
            'provider'      => 'b2',
            'storage_key'   => $result['storage_key'],
            'file_id'       => $result['file_id'],
            'file_url'      => $result['file_url'],
            'file_type'     => $result['file_type'],
            'file_size'     => $result['file_size'],
            'created_at'    => current_time( 'mysql' ),
        ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ] );

        // Keep the original locally so the photo editor can still rewrite it;
        // only intermediate sizes are safe to drop when local deletion is on.
        if ( $delete_local && 'full' !== $size_name ) {
            @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
        }
    }

    /**
     * Replace the full-size URL with its B2 URL when mapped.
     *
     * @param string $url
     * @param int    $attachment_id
     * @return string
     */
    public function filter_url( $url, $attachment_id ) {
        $row = self::get_row( (int) $attachment_id, 'full' );
        return $row ? (string) $row['file_url'] : $url;
    }

    /**
     * Replace a sized image URL with its B2 URL when mapped.
     *
     * @param array|false  $image [url, width, height, is_intermediate]
     * @param int          $attachment_id
     * @param string|int[] $size
     * @return array|false
     */
    public function filter_src( $image, $attachment_id, $size ) {
        if ( ! is_array( $image ) || ! is_string( $size ) ) {
            return $image;
        }
        $row = self::get_row( (int) $attachment_id, $size );
        if ( ! $row ) {
            $row = self::get_row( (int) $attachment_id, 'full' );
        }
        if ( $row ) {
            $image[0] = (string) $row['file_url'];
        }
        return $image;
    }

    /**
     * Delete all B2 objects for an attachment when it is deleted.
     */
    public function on_delete( $attachment_id ): void {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE attachment_id = %d', (int) $attachment_id ),
            ARRAY_A
        );
        foreach ( (array) $rows as $row ) {
            BackblazeB2Client::delete( (string) $row['storage_key'], (string) $row['file_id'] );
        }
        $wpdb->delete( self::table(), [ 'attachment_id' => (int) $attachment_id ], [ '%d' ] );
    }

    /**
     * Fetch a single storage row for (attachment, size).
     *
     * @return array<string,mixed>|null
     */
    private static function get_row( int $attachment_id, string $size_name ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE attachment_id = %d AND size_name = %s LIMIT 1',
                $attachment_id,
                $size_name
            ),
            ARRAY_A
        );
        return $row ?: null;
    }
}
