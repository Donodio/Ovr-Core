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
