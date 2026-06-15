<?php
/**
 * Property Gallery Component.
 *
 * One large main image with a row of three thumbnails beneath it. The third
 * thumbnail carries a "+ More Images" overlay that opens the full lightbox.
 * Remaining photos are rendered as hidden tiles so the lightbox can page
 * through every image (assets/js/ovr-property.js collects all data-ovr-gallery-open tiles).
 *
 * @package OVR
 *
 * @var int    $post_id      Required. Property post ID.
 * @var array  $gallery      Optional. Array of attachment IDs.
 * @var string $title        Optional. Property title for alt text.
 * @var string $video_url    Optional. External video URL (shows play overlay).
 * @var string $video_src    Optional. Uploaded video file URL (native player).
 * @var string $video_embed  Optional. YouTube/Vimeo embed URL (iframe player).
 * @var string $panorama_url Optional. 360 panorama URL.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$post_id      = $post_id ?? 0;
$gallery      = $gallery ?? [];
$title        = $title ?? get_the_title( $post_id );
$video_url    = $video_url ?? '';
$video_src    = $video_src ?? '';
$video_embed  = $video_embed ?? '';
$panorama_url = $panorama_url ?? '';
$captions     = ( isset( $captions ) && is_array( $captions ) ) ? $captions : [];

// Feature B: a video supersedes images as the primary (hero) media.
$has_video = ( '' !== $video_src ) || ( '' !== $video_embed );

// Ensure we have at minimum the featured image.
if ( empty( $gallery ) && has_post_thumbnail( $post_id ) ) {
    $gallery = [ get_post_thumbnail_id( $post_id ) ];
}

if ( empty( $gallery ) ) {
    return; // Nothing to render.
}

$gallery     = array_values( $gallery );
$total       = count( $gallery );
$placeholder = OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';

// Helper: get image URL with fallback.
$get_img = function( $idx, $size = 'large' ) use ( $gallery, $placeholder ) {
    if ( ! isset( $gallery[ $idx ] ) ) {
        return $placeholder;
    }
    $url = wp_get_attachment_image_url( $gallery[ $idx ], $size );
    return $url ?: $placeholder;
};

// Helper: a photo's caption (empty string when none), keyed by attachment id.
$get_cap = function( $idx ) use ( $gallery, $captions ) {
    $id = isset( $gallery[ $idx ] ) ? (string) $gallery[ $idx ] : '';
    return ( '' !== $id && isset( $captions[ $id ] ) ) ? (string) $captions[ $id ] : '';
};

// Up to three thumbnails sit beneath the main image (indices 1–3).
$thumb_count   = min( 3, max( 0, $total - 1 ) );
$has_more      = $total > 4;
$last_thumb_ix = $thumb_count; // index of the 3rd thumbnail (when present).
?>
<div class="ovr-gallery" data-ovr-gallery data-post-id="<?php echo esc_attr( $post_id ); ?>">
    <div class="ovr-gallery-grid">

        <!-- Main / Hero Media — video supersedes images when present (Feature B) -->
        <?php if ( $has_video ) : ?>
            <div class="ovr-gallery-tile ovr-gallery-main ovr-gallery-video">
                <?php if ( '' !== $video_src ) : ?>
                    <video controls preload="metadata" playsinline
                           poster="<?php echo esc_url( $get_img( 0, 'large' ) ); ?>"
                           title="<?php echo esc_attr( $title ); ?>">
                        <source src="<?php echo esc_url( $video_src ); ?>">
                    </video>
                <?php else : ?>
                    <iframe src="<?php echo esc_url( $video_embed ); ?>"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen loading="lazy"
                            title="<?php esc_attr_e( 'Property video tour', 'ovr-core' ); ?>"></iframe>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <button type="button"
                    class="ovr-gallery-tile ovr-gallery-main"
                    data-ovr-gallery-open="0"
                    aria-label="<?php esc_attr_e( 'Open photo 1 in gallery', 'ovr-core' ); ?>">
                <?php $main_cap = $get_cap( 0 ); ?>
                <img src="<?php echo esc_url( $get_img( 0, 'large' ) ); ?>"
                     alt="<?php echo esc_attr( '' !== $main_cap ? $main_cap : $title ); ?>"
                     loading="eager"
                     fetchpriority="high">

                <?php if ( '' !== $main_cap ) : ?>
                    <span class="ovr-gallery-caption"><?php echo esc_html( $main_cap ); ?></span>
                <?php endif; ?>

                <?php if ( $panorama_url ) : ?>
                    <div class="ovr-gallery-panorama-badge" aria-hidden="true">
                        <span class="material-symbols-outlined" style="font-size:18px">360</span>
                        <?php esc_html_e( '360°', 'ovr-core' ); ?>
                    </div>
                <?php endif; ?>
            </button>
        <?php endif; ?>

        <!-- Three thumbnails -->
        <?php if ( $thumb_count > 0 ) : ?>
            <div class="ovr-gallery-thumbs">
                <?php for ( $i = 1; $i <= $thumb_count; $i++ ) :
                    $is_more_tile = ( $has_more && $i === $last_thumb_ix );
                ?>
                    <button type="button"
                            class="ovr-gallery-tile ovr-gallery-thumb"
                            data-ovr-gallery-open="<?php echo esc_attr( $i ); ?>"
                            aria-label="<?php
                                /* translators: %d: photo index */
                                printf( esc_attr__( 'Open photo %d in gallery', 'ovr-core' ), $i + 1 ); ?>">
                        <img src="<?php echo esc_url( $get_img( $i, 'medium_large' ) ); ?>"
                             alt="<?php
                                /* translators: 1: title, 2: index */
                                printf( esc_attr__( '%1$s — view %2$d', 'ovr-core' ), esc_attr( $title ), $i + 1 ); ?>"
                             loading="lazy">

                        <?php if ( $is_more_tile ) : ?>
                            <span class="ovr-gallery-more">
                                <span class="material-symbols-outlined" style="font-size:22px">add_photo_alternate</span>
                                <?php esc_html_e( '+ More Images', 'ovr-core' ); ?>
                            </span>
                        <?php endif; ?>
                    </button>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <!-- Remaining photos (hidden) so the lightbox can page through all -->
        <?php if ( $has_more ) : ?>
            <div class="ovr-gallery-hidden" hidden>
                <?php for ( $i = $thumb_count + 1; $i < $total; $i++ ) : ?>
                    <button type="button" tabindex="-1" data-ovr-gallery-open="<?php echo esc_attr( $i ); ?>">
                        <img src="<?php echo esc_url( $get_img( $i, 'large' ) ); ?>"
                             alt="<?php
                                /* translators: 1: title, 2: index */
                                printf( esc_attr__( '%1$s — view %2$d', 'ovr-core' ), esc_attr( $title ), $i + 1 ); ?>"
                             loading="lazy">
                    </button>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .ovr-gallery-grid {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 0;
    }
    .ovr-gallery-tile {
        position: relative;
        border: none;
        padding: 0;
        cursor: pointer;
        overflow: hidden;
        background: var(--ovr-surface-container);
        border-radius: var(--ovr-radius-lg);
    }
    .ovr-gallery-main {
        width: 100%;
        height: 520px;
    }
    .ovr-gallery-video { background: #000; cursor: default; }
    .ovr-gallery-video video,
    .ovr-gallery-video iframe {
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
        object-fit: cover;
    }
    .ovr-gallery-thumbs {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    .ovr-gallery-thumb {
        height: 180px;
    }
    .ovr-gallery-tile img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform var(--ovr-transition);
    }
    .ovr-gallery-tile:hover img {
        transform: scale(1.04);
    }
    .ovr-gallery-overlay-icon {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.25);
    }
    .ovr-gallery-panorama-badge {
        position: absolute;
        top: 16px;
        right: 16px;
        background: rgba(0,0,0,0.6);
        color: #fff;
        padding: 6px 12px;
        border-radius: var(--ovr-radius-full);
        font-size: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
        backdrop-filter: blur(4px);
    }
    .ovr-gallery-more {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        background: rgba(1, 9, 49, 0.6);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-align: center;
        transition: background var(--ovr-transition);
    }
    .ovr-gallery-thumb:hover .ovr-gallery-more {
        background: rgba(1, 9, 49, 0.72);
    }
    @media (max-width: 768px) {
        .ovr-gallery-main { height: 320px; }
        .ovr-gallery-thumb { height: 110px; }
    }
</style>
