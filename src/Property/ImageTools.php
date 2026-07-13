<?php
/**
 * Image Tools — in-place GD edits for listing photos (rotate + watermark).
 *
 * Pure GD so it works on the typical LAMP/Local stack (GD with JPEG + FreeType).
 * Each method rewrites the source file; the caller is responsible for
 * regenerating the attachment's sized derivatives afterwards.
 *
 * @package OVR\Property
 * @since   1.0.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ImageTools {

    /**
     * Rotate an image file 90° clockwise, in place.
     */
    public static function rotate( string $file ): bool {
        return self::process( $file, static function ( $img ) {
            return self::gd_rotate( $img, 270 ); // 270° CCW = 90° CW
        } );
    }

    /**
     * Rotate an image file 90° counter-clockwise, in place.
     */
    public static function rotate_left( string $file ): bool {
        return self::process( $file, static function ( $img ) {
            return self::gd_rotate( $img, 90 ); // 90° CCW = 90° CW to the left
        } );
    }

    /**
     * Crop an image to a sub-rectangle, in place. Coordinates are in the
     * image's natural pixels; they are clamped to the image bounds.
     */
    public static function crop( string $file, int $x, int $y, int $w, int $h ): bool {
        return self::process( $file, static function ( $img ) use ( $x, $y, $w, $h ) {
            $iw = imagesx( $img );
            $ih = imagesy( $img );

            // Clamp the rectangle inside the image.
            $x = max( 0, min( $x, $iw - 1 ) );
            $y = max( 0, min( $y, $ih - 1 ) );
            $w = max( 1, min( $w, $iw - $x ) );
            $h = max( 1, min( $h, $ih - $y ) );

            // Ignore no-op / pathological crops.
            if ( $w < 10 || $h < 10 ) {
                return $img;
            }

            $out = imagecreatetruecolor( $w, $h );
            imagealphablending( $out, false );
            imagesavealpha( $out, true );
            $transparent = imagecolorallocatealpha( $out, 0, 0, 0, 127 );
            imagefilledrectangle( $out, 0, 0, $w, $h, $transparent );

            imagecopy( $out, $img, 0, 0, $x, $y, $w, $h );
            return $out;
        } );
    }

    /**
     * Bake a JPEG's EXIF orientation into its pixels and drop the tag, in place.
     *
     * Phone cameras store sideways pixels plus an Orientation tag telling the
     * viewer how to turn them. GD ignores that tag, so the same file can look
     * upright in one place and sideways in another. Normalizing on upload makes
     * every size/everywhere render identically. Returns true only when the file
     * actually had to be transformed.
     */
    public static function normalize_orientation( string $file ): bool {
        if ( ! is_readable( $file ) || ! function_exists( 'exif_read_data' ) ) {
            return false;
        }
        $info = getimagesize( $file );
        // EXIF orientation lives in JPEG (and TIFF); other types have none.
        if ( false === $info || IMAGETYPE_JPEG !== ( $info[2] ?? 0 ) ) {
            return false;
        }

        $exif        = @exif_read_data( $file );
        $orientation = (int) ( $exif['Orientation'] ?? 1 );
        if ( $orientation <= 1 ) {
            return false; // already upright (or unknown) — nothing to do.
        }

        return self::process( $file, static function ( $img ) use ( $orientation ) {
            return self::apply_orientation( $img, $orientation );
        } );
    }

    /**
     * Transform a GD image to upright per an EXIF orientation value (2–8).
     * Returns the (possibly new) GD image, or null on failure.
     */
    private static function apply_orientation( $img, int $orientation ) {
        switch ( $orientation ) {
            case 2: // mirror horizontal
                imageflip( $img, IMG_FLIP_HORIZONTAL );
                return $img;
            case 3: // 180°
                return self::gd_rotate( $img, 180 );
            case 4: // mirror vertical
                imageflip( $img, IMG_FLIP_VERTICAL );
                return $img;
            case 5: // mirror horizontal + 90° CW
                $out = self::gd_rotate( $img, 270 );
                if ( null === $out ) { return null; }
                imageflip( $out, IMG_FLIP_HORIZONTAL );
                return $out;
            case 6: // 90° CW
                return self::gd_rotate( $img, 270 );
            case 7: // mirror horizontal + 90° CCW
                $out = self::gd_rotate( $img, 90 );
                if ( null === $out ) { return null; }
                imageflip( $out, IMG_FLIP_HORIZONTAL );
                return $out;
            case 8: // 90° CCW
                return self::gd_rotate( $img, 90 );
            default:
                return $img;
        }
    }

    /**
     * imagerotate() with a transparent background + alpha preserved.
     * Angle is counter-clockwise, matching GD.
     */
    private static function gd_rotate( $img, int $ccw_degrees ) {
        $bg  = imagecolorallocatealpha( $img, 0, 0, 0, 127 ); // transparent
        $out = imagerotate( $img, $ccw_degrees, $bg );
        if ( false === $out ) {
            return null;
        }
        imagealphablending( $out, false );
        imagesavealpha( $out, true );
        return $out;
    }

    /**
     * Stamp a semi-transparent text watermark in the bottom-right corner.
     */
    public static function watermark( string $file, string $text ): bool {
        $text = trim( $text );
        if ( '' === $text ) {
            return false;
        }

        // Position + opacity are admin-configurable (M3 F5 Media settings).
        $settings = (array) get_option( 'ovr_settings', [] );
        $position = (string) ( $settings['watermark_position'] ?? 'bottom-right' );
        $opacity  = isset( $settings['watermark_opacity'] ) ? (int) $settings['watermark_opacity'] : 70;
        $opacity  = max( 0, min( 100, $opacity ) );
        // GD alpha: 0 = opaque, 127 = transparent.
        $alpha       = (int) round( ( 100 - $opacity ) / 100 * 127 );
        $shadowAlpha = min( 127, $alpha + 35 );

        return self::process( $file, static function ( $img ) use ( $text, $position, $alpha, $shadowAlpha ) {
            $w = imagesx( $img );
            $h = imagesy( $img );
            imagealphablending( $img, true );

            $margin = (int) round( $w * 0.025 ) + 6;
            $font   = self::font();

            // Resolve top-left origin (x,y) for a block of width $bw, height $bh.
            $place = static function ( int $bw, int $bh ) use ( $w, $h, $margin, $position ) {
                switch ( $position ) {
                    case 'top-left':     return [ $margin, $margin ];
                    case 'top-right':    return [ $w - $bw - $margin, $margin ];
                    case 'bottom-left':  return [ $margin, $h - $bh - $margin ];
                    case 'center':       return [ (int) round( ( $w - $bw ) / 2 ), (int) round( ( $h - $bh ) / 2 ) ];
                    case 'bottom-right':
                    default:             return [ $w - $bw - $margin, $h - $bh - $margin ];
                }
            };

            if ( $font ) {
                $size = max( 12, (int) round( $w * 0.035 ) );
                $box  = imagettfbbox( $size, 0, $font, $text );
                $tw   = abs( $box[2] - $box[0] );
                $th   = abs( $box[7] - $box[1] );
                [ $ox, $oy ] = $place( $tw, $th );
                // imagettftext y is the baseline, so add text height to the top origin.
                $bx     = (int) $ox;
                $by     = (int) $oy + $th;
                $shadow = imagecolorallocatealpha( $img, 0, 0, 0, $shadowAlpha );
                $white  = imagecolorallocatealpha( $img, 255, 255, 255, $alpha );
                imagettftext( $img, $size, 0, $bx + 2, $by + 2, $shadow, $font, $text );
                imagettftext( $img, $size, 0, $bx, $by, $white, $font, $text );
            } else {
                // Portable fallback: GD's built-in font upscaled onto a buffer.
                $gd  = 5;
                $tw0 = imagefontwidth( $gd ) * max( 1, strlen( $text ) );
                $th0 = imagefontheight( $gd );
                $buf = imagecreatetruecolor( $tw0, $th0 );
                imagesavealpha( $buf, true );
                imagefill( $buf, 0, 0, imagecolorallocatealpha( $buf, 0, 0, 0, 127 ) );
                imagestring( $buf, $gd, 0, 0, $text, imagecolorallocate( $buf, 255, 255, 255 ) );
                $scale = max( 2, (int) round( ( $w * 0.32 ) / $tw0 ) );
                $dw    = $tw0 * $scale;
                $dh    = $th0 * $scale;
                [ $dx, $dy ] = $place( $dw, $dh );
                imagecopyresampled( $img, $buf, (int) $dx, (int) $dy, 0, 0, $dw, $dh, $tw0, $th0 );
            }

            return $img;
        } );
    }

    /**
     * Load → run a GD callback → save back over the same file, preserving type.
     * The callback receives the GD image and returns the image to save (or null).
     */
    /**
     * Write a WebP copy of a JPEG/PNG source to $dest (M3 F12). Returns false
     * when GD lacks WebP support or the source can't be read; never throws.
     *
     * @param string $src     Source image path.
     * @param string $dest    Destination .webp path.
     * @param int    $quality 0–100.
     */
    public static function to_webp( string $src, string $dest, int $quality = 82 ): bool {
        if ( ! function_exists( 'imagewebp' ) || ! is_readable( $src ) ) {
            return false;
        }
        $info = @getimagesize( $src );
        if ( false === $info ) {
            return false;
        }
        switch ( $info[2] ) {
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg( $src );
                break;
            case IMAGETYPE_PNG:
                $img = function_exists( 'imagecreatefrompng' ) ? @imagecreatefrompng( $src ) : false;
                if ( $img ) {
                    imagepalettetotruecolor( $img );
                    imagealphablending( $img, true );
                    imagesavealpha( $img, true );
                }
                break;
            default:
                return false; // GIF/WebP sources skipped.
        }
        if ( false === $img ) {
            return false;
        }
        $ok = imagewebp( $img, $dest, max( 0, min( 100, $quality ) ) );
        imagedestroy( $img );
        return (bool) $ok;
    }

    private static function process( string $file, callable $cb ) {
        if ( ! is_readable( $file ) || ! function_exists( 'imagecreatefromjpeg' ) ) {
            return false;
        }
        $info = getimagesize( $file );
        if ( false === $info ) {
            return false;
        }
        $type = $info[2];

        switch ( $type ) {
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg( $file );
                break;
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng( $file );
                break;
            case IMAGETYPE_WEBP:
                $img = function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $file ) : false;
                break;
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif( $file );
                break;
            default:
                return false;
        }
        if ( false === $img ) {
            return false;
        }

        $out = $cb( $img );
        if ( null === $out || false === $out ) {
            return false;
        }

        $ok = false;
        switch ( $type ) {
            case IMAGETYPE_JPEG:
                $ok = imagejpeg( $out, $file, self::quality() );
                break;
            case IMAGETYPE_PNG:
                imagesavealpha( $out, true );
                $ok = imagepng( $out, $file );
                break;
            case IMAGETYPE_WEBP:
                $ok = function_exists( 'imagewebp' ) ? imagewebp( $out, $file ) : false;
                break;
            case IMAGETYPE_GIF:
                $ok = imagegif( $out, $file );
                break;
        }

        return (bool) $ok;
    }

    /**
     * Resolve a usable TrueType font for the watermark, or null to fall back to
     * GD's built-in font. Filterable via `ovr_watermark_font`.
     */
    private static function font(): ?string {
        $candidates = [
            defined( 'OVR_PLUGIN_DIR' ) ? OVR_PLUGIN_DIR . 'assets/fonts/watermark.ttf' : '',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/Library/Fonts/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
        ];
        if ( function_exists( 'apply_filters' ) ) {
            $candidates = (array) apply_filters( 'ovr_watermark_font', $candidates );
        }
        foreach ( $candidates as $f ) {
            if ( $f && is_readable( $f ) && function_exists( 'imagettftext' ) ) {
                return $f;
            }
        }
        return null;
    }

    private static function quality(): int {
        // Read the shared media-quality setting directly. (SettingsBehaviors::s()
        // is private; calling it from here fatals — the cause of every image
        // upload failing during watermarking.)
        $s = (array) get_option( 'ovr_settings', [] );
        return max( 10, min( 100, (int) ( $s['image_quality'] ?? 82 ) ) );
    }
}
