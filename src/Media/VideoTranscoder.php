<?php
/**
 * Lightweight video transcoder (ffmpeg wrapper).
 *
 * Converts an uploaded video to the website-safe web target:
 *   - H.264/AVC (High profile) video
 *   - AAC audio (128 kbps) when the source has audio
 *   - yuv420p pixel format (broadest <video> support)
 *   - moov atom moved to the front (`+faststart`) so playback starts before
 *     the whole file downloads
 *   - downscaled only when wider than 1920px (never upscales, never re-encodes
 *     an already 1080p H.264 file)
 *
 * This is deliberately a tiny single-pass helper, NOT a transcoding platform.
 * When ffmpeg is unavailable the caller should fall back to a clear
 * compatibility message rather than trying to convert on the server.
 *
 * @package OVR\Media
 */

namespace OVR\Media;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VideoTranscoder {

    /**
     * Whether ffmpeg is reachable on this server.
     */
    public static function available(): bool {
        return null !== self::binary();
    }

    /**
     * Resolve the ffmpeg binary path, or null.
     */
    public static function binary(): ?string {
        static $cached = null;
        if ( null !== $cached ) {
            return $cached;
        }
        $cached = self::locate( 'ffmpeg' );
        return $cached;
    }

    /**
     * Locate a binary via PATH / common install locations when exec is enabled.
     */
    private static function locate( string $name ): ?string {
        if ( ! function_exists( 'exec' ) || ! function_exists( 'shell_exec' ) ) {
            return null;
        }
        $candidates = [];
        @exec( 'command -v ' . escapeshellarg( $name ) . ' 2>/dev/null', $candidates );
        if ( $candidates && ! empty( $candidates[0] ) && is_file( $candidates[0] ) ) {
            return $candidates[0];
        }
        $which = trim( (string) @shell_exec( 'which ' . escapeshellarg( $name ) . ' 2>/dev/null' ) );
        if ( '' !== $which && is_file( $which ) ) {
            return $which;
        }
        foreach ( [ '/usr/bin/' . $name, '/usr/local/bin/' . $name, '/opt/homebrew/bin/' . $name ] as $path ) {
            if ( is_file( $path ) ) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Transcode a source video to a web-safe H.264 MP4 in the system temp dir.
     *
     * @param string $src Absolute path to the uploaded file.
     * @return string|null Absolute path to the transcoded .mp4, or null on failure.
     */
    public static function transcode( string $src ): ?string {
        $bin = self::binary();
        if ( ! $bin || ! is_file( $src ) ) {
            return null;
        }

        // Real dimensions decide whether a downscale is needed (never upscale).
        $probe  = VideoProbe::probe( $src );
        $width  = (int) ( $probe['width'] ?? 0 );
        $vf     = '';
        if ( $width > 1920 ) {
            // Preserve aspect, cap width at 1920 (height auto). `-2` keeps even.
            $vf = '-vf scale=1920:-2';
        }

        $tmp = tempnam( sys_get_temp_dir(), 'ovr-enc-' ) . '.mp4';

        $cmd = escapeshellarg( $bin )
            . ' -y -i ' . escapeshellarg( $src )
            . ' ' . $vf
            . ' -map 0:v:0 -map 0:a?'
            . ' -c:v libx264 -preset veryfast -crf 23 -profile:v high -pix_fmt yuv420p'
            . ' -c:a aac -b:a 128k -movflags +faststart'
            . ' ' . escapeshellarg( $tmp );

        $out = [];
        $rc  = 0;
        @exec( $cmd . ' 2>&1', $out, $rc );

        if ( 0 !== $rc || ! is_file( $tmp ) || filesize( $tmp ) < 1024 ) {
            if ( is_file( $tmp ) ) {
                @unlink( $tmp );
            }
            return null;
        }
        return $tmp;
    }
}
