<?php
/**
 * Performance optimisations (Milestone 3 Feature 12).
 *
 *  - WebP: generates a .webp sibling for every uploaded image size, then swaps
 *    images to WebP at render time for browsers that send `Accept: image/webp`
 *    (and only when the sibling actually exists, so it can never break an image).
 *  - Responsive images: registers a card-sized image so WordPress's automatic
 *    srcset has a well-fitted candidate for listing cards.
 *  - Query caching: a versioned object/transient cache around the expensive
 *    map-points query, invalidated whenever a listing is saved.
 *
 * WebP serving can be disabled with the OVR_DISABLE_WEBP constant.
 *
 * @package OVR\Core
 * @since   2.8.0
 */

namespace OVR\Core;

use OVR\Property\ImageTools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Performance {

    public const MAPPOINTS_VER_OPTION = 'ovr_mappoints_cache_ver';

    public function init(): void {
        // Generate WebP siblings when attachment derivatives are (re)built.
        add_filter( 'wp_generate_attachment_metadata', [ $this, 'generate_webp' ], 20, 2 );

        // Serve WebP to capable browsers.
        if ( $this->webp_enabled() ) {
            add_filter( 'wp_get_attachment_image_src', [ $this, 'swap_image_src' ], 20 );
            add_filter( 'wp_calculate_image_srcset', [ $this, 'swap_srcset' ], 20 );
        }

        // Responsive card image size for better srcset candidates.
        add_action( 'after_setup_theme', [ $this, 'register_image_sizes' ] );

        // Invalidate the map-points cache when any listing changes.
        add_action( 'ovr_property_saved', [ __CLASS__, 'bump_mappoints_version' ] );
        add_action( 'save_post_ovr_property', [ __CLASS__, 'bump_mappoints_version' ] );
        add_action( 'deleted_post', [ __CLASS__, 'bump_mappoints_version' ] );
    }

    /* ───────────────────────── Image sizes ───────────────────────── */

    public function register_image_sizes(): void {
        // Listing-card width used across search / homepage grids.
        add_image_size( 'ovr-card', 600, 400, true );
    }

    /* ───────────────────────── WebP generation ───────────────────────── */

    /**
     * @param array<string, mixed> $metadata
     * @param int                  $attachment_id
     * @return array<string, mixed>
     */
    public function generate_webp( $metadata, $attachment_id ) {
        if ( ! is_array( $metadata ) || ! function_exists( 'imagewebp' ) ) {
            return $metadata;
        }
        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! is_readable( $file ) ) {
            return $metadata;
        }
        $mime = (string) get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, [ 'image/jpeg', 'image/png' ], true ) ) {
            return $metadata;
        }

        $quality = $this->webp_quality();
        $dir     = trailingslashit( dirname( $file ) );

        // Full-size original.
        $this->maybe_make_webp( $file, $quality );

        // Each generated size.
        if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $size ) {
                if ( empty( $size['file'] ) ) {
                    continue;
                }
                $this->maybe_make_webp( $dir . $size['file'], $quality );
            }
        }
        return $metadata;
    }

    private function maybe_make_webp( string $path, int $quality ): void {
        $webp = $this->webp_path( $path );
        if ( '' === $webp || file_exists( $webp ) ) {
            return;
        }
        ImageTools::to_webp( $path, $webp, $quality );
    }

    /* ───────────────────────── WebP serving ───────────────────────── */

    /**
     * @param array<int, mixed>|false $image [url, width, height, is_intermediate]
     * @return array<int, mixed>|false
     */
    public function swap_image_src( $image ) {
        if ( ! is_array( $image ) || empty( $image[0] ) || ! $this->browser_supports_webp() ) {
            return $image;
        }
        $webp = $this->webp_url_if_exists( (string) $image[0] );
        if ( '' !== $webp ) {
            $image[0] = $webp;
        }
        return $image;
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     * @return array<int, array<string, mixed>>
     */
    public function swap_srcset( $sources ) {
        if ( ! is_array( $sources ) || ! $this->browser_supports_webp() ) {
            return $sources;
        }
        foreach ( $sources as $k => $src ) {
            if ( empty( $src['url'] ) ) {
                continue;
            }
            $webp = $this->webp_url_if_exists( (string) $src['url'] );
            if ( '' !== $webp ) {
                $sources[ $k ]['url'] = $webp;
            }
        }
        return $sources;
    }

    /* ───────────────────────── Map-points cache ───────────────────────── */

    public static function mappoints_version(): int {
        return (int) get_option( self::MAPPOINTS_VER_OPTION, 1 );
    }

    public static function bump_mappoints_version( $post_id = 0 ): void {
        // Ignore noise from unrelated post types on save_post/deleted_post.
        if ( $post_id && 'ovr_property' !== get_post_type( (int) $post_id ) ) {
            return;
        }
        update_option( self::MAPPOINTS_VER_OPTION, self::mappoints_version() + 1, false );
    }

    /* ───────────────────────── Helpers ───────────────────────── */

    private function webp_enabled(): bool {
        if ( defined( 'OVR_DISABLE_WEBP' ) && OVR_DISABLE_WEBP ) {
            return false;
        }
        $settings = (array) get_option( 'ovr_settings', [] );
        // Default on unless explicitly disabled in the Media settings tab.
        return ! isset( $settings['enable_webp'] ) || ! empty( $settings['enable_webp'] );
    }

    private function webp_quality(): int {
        $settings = (array) get_option( 'ovr_settings', [] );
        $q        = (int) ( $settings['image_quality'] ?? 82 );
        return $q > 0 ? max( 40, min( 100, $q ) ) : 82;
    }

    private function browser_supports_webp(): bool {
        return false !== strpos( (string) ( $_SERVER['HTTP_ACCEPT'] ?? '' ), 'image/webp' );
    }

    /** Map an image file path to its .webp sibling path, or '' if not a jpg/png. */
    private function webp_path( string $path ): string {
        if ( ! preg_match( '/\.(jpe?g|png)$/i', $path ) ) {
            return '';
        }
        return preg_replace( '/\.(jpe?g|png)$/i', '.webp', $path ) ?? '';
    }

    /** Given an image URL, return its .webp URL if the file exists, else ''. */
    private function webp_url_if_exists( string $url ): string {
        if ( ! preg_match( '/\.(jpe?g|png)$/i', $url ) ) {
            return '';
        }
        $uploads = wp_get_upload_dir();
        if ( empty( $uploads['baseurl'] ) || 0 !== strpos( $url, $uploads['baseurl'] ) ) {
            return ''; // Only handle media-library images we can resolve to disk.
        }
        $path     = $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
        $path     = strtok( $path, '?' ); // Drop any query string.
        $webpPath = $this->webp_path( (string) $path );
        if ( '' === $webpPath || ! file_exists( $webpPath ) ) {
            return '';
        }
        return preg_replace( '/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $url ) ?? '';
    }
}
