<?php
/**
 * Backblaze B2 storage client (Feature E).
 *
 * Thin wrapper over the native B2 API (v3): authorize → get upload URL →
 * upload file, plus delete. Credentials live in ovr_settings (Storage tab).
 * The authorize response is cached in a transient (it is valid for ~24h) so we
 * don't re-authorize on every upload.
 *
 * Public file URLs use the account download URL:
 *   {downloadUrl}/file/{bucketName}/{key}
 * which serves files from a public bucket without signing.
 *
 * @package OVR\Storage
 * @since   2.1.0
 */

namespace OVR\Storage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BackblazeB2Client {

    private const AUTH_URL       = 'https://api.backblazeb2.com/b2api/v3/b2_authorize_account';
    private const AUTH_TRANSIENT = 'ovr_b2_auth';

    /**
     * The Storage settings block.
     *
     * @return array<string, mixed>
     */
    public static function settings(): array {
        return (array) get_option( 'ovr_settings', [] );
    }

    /**
     * Whether B2 offloading is enabled AND all credentials are present.
     */
    public static function is_configured(): bool {
        $s = self::settings();
        return ! empty( $s['b2_enabled'] )
            && ! empty( $s['b2_key_id'] )
            && ! empty( $s['b2_app_key'] )
            && ! empty( $s['b2_bucket_name'] );
    }

    /**
     * Authorize with B2, returning the session payload (cached). Returns null on
     * failure. Pass $fresh=true to bypass the cache (used by the test button).
     *
     * @return array<string, mixed>|null
     */
    public static function authorize( bool $fresh = false ): ?array {
        if ( ! $fresh ) {
            $cached = get_transient( self::AUTH_TRANSIENT );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $s   = self::settings();
        $key = trim( (string) ( $s['b2_key_id'] ?? '' ) );
        $app = trim( (string) ( $s['b2_app_key'] ?? '' ) );
        if ( '' === $key || '' === $app ) {
            return null;
        }

        $resp = wp_remote_get( self::AUTH_URL, [
            'headers' => [ 'Authorization' => 'Basic ' . base64_encode( $key . ':' . $app ) ],
            'timeout' => 20,
        ] );
        if ( is_wp_error( $resp ) ) {
            return null;
        }
        if ( 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            return null;
        }
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! is_array( $data ) ) {
            return null;
        }

        // B2 v3 nests endpoints under apiInfo.storageApi.
        $api = $data['apiInfo']['storageApi'] ?? [];
        $session = [
            'authorizationToken' => (string) ( $data['authorizationToken'] ?? '' ),
            'apiUrl'             => (string) ( $api['apiUrl'] ?? '' ),
            'downloadUrl'        => (string) ( $api['downloadUrl'] ?? '' ),
            'bucketId'           => (string) ( $api['bucketId'] ?? '' ),
            'bucketName'         => (string) ( $api['bucketName'] ?? '' ),
        ];
        if ( '' === $session['apiUrl'] || '' === $session['authorizationToken'] ) {
            return null;
        }

        // Cache for 23h (tokens last 24h).
        set_transient( self::AUTH_TRANSIENT, $session, 23 * HOUR_IN_SECONDS );
        return $session;
    }

    /**
     * Resolve the target bucket id for the configured bucket name. Prefers the
     * id the key is already scoped to; otherwise looks it up by name.
     */
    private static function bucket_id( array $auth ): ?string {
        $s    = self::settings();
        $name = (string) ( $s['b2_bucket_name'] ?? '' );

        if ( ! empty( $auth['bucketId'] ) && ( '' === $auth['bucketName'] || $auth['bucketName'] === $name ) ) {
            return (string) $auth['bucketId'];
        }

        // Look up by name via b2_list_buckets (requires accountId — derive from
        // the key by listing; B2 accepts bucketName filter).
        $resp = wp_remote_post( trailingslashit( $auth['apiUrl'] ) . 'b2api/v3/b2_list_buckets', [
            'headers' => [
                'Authorization' => $auth['authorizationToken'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 20,
            'body'    => wp_json_encode( [ 'accountId' => $s['b2_account_id'] ?? '', 'bucketName' => $name ] ),
        ] );
        if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            return null;
        }
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        foreach ( (array) ( $data['buckets'] ?? [] ) as $bucket ) {
            if ( ( $bucket['bucketName'] ?? '' ) === $name ) {
                return (string) $bucket['bucketId'];
            }
        }
        return null;
    }

    /**
     * Upload a local file to B2 under $key. Returns the stored-file descriptor
     * or null on failure.
     *
     * @return array{file_id:string,storage_key:string,file_url:string,file_size:int,file_type:string}|null
     */
    public static function upload( string $file, string $key, string $mime = '' ): ?array {
        if ( ! is_readable( $file ) ) {
            return null;
        }
        $auth = self::authorize();
        if ( ! $auth ) {
            return null;
        }
        $bucket_id = self::bucket_id( $auth );
        if ( ! $bucket_id ) {
            return null;
        }

        // Step 1: get an upload URL for the bucket.
        $up = wp_remote_post( trailingslashit( $auth['apiUrl'] ) . 'b2api/v3/b2_get_upload_url', [
            'headers' => [
                'Authorization' => $auth['authorizationToken'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 20,
            'body'    => wp_json_encode( [ 'bucketId' => $bucket_id ] ),
        ] );
        if ( is_wp_error( $up ) || 200 !== (int) wp_remote_retrieve_response_code( $up ) ) {
            return null;
        }
        $up_data = json_decode( wp_remote_retrieve_body( $up ), true );
        $upload_url   = (string) ( $up_data['uploadUrl'] ?? '' );
        $upload_token = (string) ( $up_data['authorizationToken'] ?? '' );
        if ( '' === $upload_url || '' === $upload_token ) {
            return null;
        }

        $contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( false === $contents ) {
            return null;
        }
        $mime = $mime ?: ( (string) ( wp_check_filetype( $file )['type'] ?? '' ) ?: 'b2/x-auto' );

        // Step 2: upload the bytes.
        $resp = wp_remote_post( $upload_url, [
            'headers' => [
                'Authorization'     => $upload_token,
                'X-Bz-File-Name'    => self::encode_name( $key ),
                'Content-Type'      => $mime,
                'X-Bz-Content-Sha1' => sha1( $contents ),
            ],
            'timeout' => 120,
            'body'    => $contents,
        ] );
        if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            return null;
        }
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! is_array( $data ) || empty( $data['fileId'] ) ) {
            return null;
        }

        $bucket_name = (string) ( self::settings()['b2_bucket_name'] ?? '' );
        $url = trailingslashit( $auth['downloadUrl'] ) . 'file/' . $bucket_name . '/' . self::encode_name( $key );

        return [
            'file_id'     => (string) $data['fileId'],
            'storage_key' => $key,
            'file_url'    => $url,
            'file_size'   => (int) ( $data['contentLength'] ?? strlen( $contents ) ),
            'file_type'   => $mime,
        ];
    }

    /**
     * Delete a file from B2 by its key + fileId. Best-effort.
     */
    public static function delete( string $storage_key, string $file_id ): bool {
        if ( '' === $file_id || '' === $storage_key ) {
            return false;
        }
        $auth = self::authorize();
        if ( ! $auth ) {
            return false;
        }
        $resp = wp_remote_post( trailingslashit( $auth['apiUrl'] ) . 'b2api/v3/b2_delete_file_version', [
            'headers' => [
                'Authorization' => $auth['authorizationToken'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 20,
            'body'    => wp_json_encode( [ 'fileName' => $storage_key, 'fileId' => $file_id ] ),
        ] );
        return ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp );
    }

    /**
     * Connectivity check for the Storage settings page.
     *
     * @return array{ok:bool, message:string}
     */
    public static function test_connection(): array {
        if ( ! self::is_configured() ) {
            return [ 'ok' => false, 'message' => __( 'Enter and enable your B2 credentials first.', 'ovr-core' ) ];
        }
        $auth = self::authorize( true );
        if ( ! $auth ) {
            return [ 'ok' => false, 'message' => __( 'Authorization failed — check the Key ID and Application Key.', 'ovr-core' ) ];
        }
        if ( ! self::bucket_id( $auth ) ) {
            return [ 'ok' => false, 'message' => __( 'Authorized, but the bucket was not found — check the Bucket Name.', 'ovr-core' ) ];
        }
        return [ 'ok' => true, 'message' => __( 'Connected to Backblaze B2 successfully.', 'ovr-core' ) ];
    }

    /**
     * Percent-encode a B2 object key per their rules (encode everything except
     * unreserved chars and the path separator).
     */
    private static function encode_name( string $key ): string {
        $parts = explode( '/', $key );
        $parts = array_map( static fn( $p ) => rawurlencode( $p ), $parts );
        return implode( '/', $parts );
    }
}
