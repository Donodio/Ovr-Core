<?php
/**
 * Video codec probe (ffprobe wrapper).
 *
 * Reads the REAL codec of an uploaded video file rather than trusting the
 * file extension. This is the guard that catches HEVC/H.265-in-.mp4 files
 * (e.g. "1959Video"): the browser cannot play those even though WordPress
 * happily accepts them as `video/mp4`.
 *
 * All probing is optional and defensive — if ffprobe is not installed (or
 * exec is disabled) `probe()` returns null and callers fall back to trusting
 * the extension/MIME so legitimate uploads are never blocked by missing tooling.
 *
 * @package OVR\Media
 */

namespace OVR\Media;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VideoProbe {

    /**
     * Whether ffprobe is reachable on this server.
     */
    public static function available(): bool {
        return null !== self::binary();
    }

    /**
     * Resolve the ffprobe binary path (first hit on PATH), or null.
     */
    public static function binary(): ?string {
        static $cached = null;
        if ( null !== $cached ) {
            return $cached;
        }
        $cached = self::locate( 'ffprobe' );
        return $cached;
    }

    /**
     * Locate a binary via `command -v` / `which` when exec is enabled.
     */
    private static function locate( string $name ): ?string {
        if ( ! function_exists( 'exec' ) || ! function_exists( 'shell_exec' ) ) {
            return null;
        }
        // A handful of hosts disable exec; never fatal — just unavailable.
        $candidates = [];
        @exec( 'command -v ' . escapeshellarg( $name ) . ' 2>/dev/null', $candidates );
        if ( $candidates && ! empty( $candidates[0] ) && is_file( $candidates[0] ) ) {
            return $candidates[0];
        }
        $which = trim( (string) @shell_exec( 'which ' . escapeshellarg( $name ) . ' 2>/dev/null' ) );
        if ( '' !== $which && is_file( $which ) ) {
            return $which;
        }
        // Last resort: common install locations.
        foreach ( [ '/usr/bin/' . $name, '/usr/local/bin/' . $name, '/opt/homebrew/bin/' . $name ] as $path ) {
            if ( is_file( $path ) ) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Probe a media file for its real video stream characteristics.
     *
     * Returns null when probing is unavailable or the file cannot be read so
     * callers never treat "unknown" as "must reject".
     *
     * @return array{codec:string,profile:string,width:int,height:int,pix_fmt:string,bit_rate:int,duration:float,audio:bool}|null
     */
    public static function probe( string $file ): ?array {
        $bin = self::binary();
        if ( ! $bin || ! is_file( $file ) ) {
            return null;
        }
        $cmd = escapeshellarg( $bin )
            . ' -v error -select_streams v:0'
            . ' -show_entries stream=codec_name,profile,width,height,pix_fmt,avg_frame_rate,bit_rate'
            . ' -show_entries format=duration,bit_rate'
            . ' -of default=noprint_wrappers=1 '
            . escapeshellarg( $file );

        $out = [];
        @exec( $cmd . ' 2>/dev/null', $out );
        if ( ! $out ) {
            return null;
        }

        $info = [];
        foreach ( $out as $line ) {
            if ( false !== strpos( $line, '=' ) ) {
                [ $k, $v ] = array_map( 'trim', explode( '=', $line, 2 ) );
                $info[ $k ] = $v;
            }
        }
        if ( empty( $info['codec_name'] ) ) {
            return null;
        }

        // Audio presence: cheap second pass.
        $audio = null;
        $aout  = [];
        $acmd  = escapeshellarg( $bin )
            . ' -v error -select_streams a:0 -show_entries stream=codec_name -of default=noprint_wrappers=1 '
            . escapeshellarg( $file );
        @exec( $acmd . ' 2>/dev/null', $aout );
        $audio = (bool) array_filter( $aout );

        return [
            'codec'    => (string) ( $info['codec_name'] ?? '' ),
            'profile'  => (string) ( $info['profile'] ?? '' ),
            'width'    => (int) ( $info['width'] ?? 0 ),
            'height'   => (int) ( $info['height'] ?? 0 ),
            'pix_fmt'  => (string) ( $info['pix_fmt'] ?? '' ),
            'bit_rate' => (int) ( $info['bit_rate'] ?? 0 ),
            'duration' => (float) ( $info['duration'] ?? 0 ),
            'audio'    => $audio,
        ];
    }

    /**
     * Is this probed stream broadly web-playable?
     *
     * The website-safe target is H.264/AVC video with a yuv420p pixel format
     * (plus AAC audio when present). HEVC/H.265, VP9-only containers and
     * 4:4:4 / 10-bit pixel formats are NOT safe for cross-browser <video>.
     *
     * @param array $info VideoProbe::probe() output.
     */
    public static function is_web_safe( array $info ): bool {
        $codec   = strtolower( (string) ( $info['codec'] ?? '' ) );
        $pix_fmt = strtolower( (string) ( $info['pix_fmt'] ?? '' ) );
        return in_array( $codec, [ 'h264', 'avc1' ], true ) && false !== strpos( $pix_fmt, 'yuv420' );
    }
}
