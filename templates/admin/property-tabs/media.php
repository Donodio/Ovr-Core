<?php
/**
 * Media Tab — gallery picker, video URL, panorama URL.
 *
 * @package OVR
 * @var array $meta
 * @var array $gallery_ids
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$gallery_ids  = $gallery_ids ?? [];
$video_url    = (string) ( $meta['video_url']    ?? '' );
$panorama_url = (string) ( $meta['panorama_url'] ?? '' );
$gallery_csv  = implode( ',', array_map( 'absint', $gallery_ids ) );

// Documents (max 3 — PDF/DOC/etc).
$document_ids = array_slice(
    \OVR\Admin\PropertyMetaBoxes::parse_id_string( (string) ( $meta['document_ids'] ?? '' ) ),
    0,
    \OVR\Admin\PropertyMetaBoxes::MAX_DOCS
);
$documents_csv = implode( ',', $document_ids );
$max_docs      = \OVR\Admin\PropertyMetaBoxes::MAX_DOCS;
?>
<p class="ovr-meta-tabs__panel-intro">
    <?php esc_html_e( 'High-quality photos drive bookings. Add as many as you like — drag to reorder, the first one is the primary image used as the cover.', 'ovr-core' ); ?>
</p>

<div class="ovr-gallery-picker" data-ovr-gallery-picker>

    <div class="ovr-section-head" style="margin-top:0">
        <h3><span class="material-symbols-outlined">collections</span> <?php esc_html_e( 'Photo Gallery', 'ovr-core' ); ?></h3>
        <button type="button" class="ovr-btn-admin" data-ovr-gallery-add>
            <span class="material-symbols-outlined">add_photo_alternate</span>
            <?php esc_html_e( 'Add photos', 'ovr-core' ); ?>
        </button>
    </div>

    <input type="hidden"
           name="ovr_meta[gallery_ids]"
           value="<?php echo esc_attr( $gallery_csv ); ?>"
           data-ovr-gallery-input>

    <div class="ovr-gallery-picker__strip"
         data-ovr-gallery-strip
         data-empty-text="<?php esc_attr_e( 'No photos selected yet. Click "Add photos" to upload or pick from the media library.', 'ovr-core' ); ?>">
        <?php
        // Render existing tiles server-side so they display before JS boots.
        foreach ( $gallery_ids as $idx => $att_id ) :
            $att_id = absint( $att_id );
            if ( ! $att_id ) continue;
            $thumb = wp_get_attachment_image_url( $att_id, 'thumbnail' );
            $alt   = (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true );
            if ( ! $thumb ) continue;
            $is_primary = ( 0 === $idx );
        ?>
            <div class="ovr-gallery-tile <?php echo $is_primary ? 'is-primary' : ''; ?>"
                 data-id="<?php echo esc_attr( (string) $att_id ); ?>"
                 draggable="true">
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $alt ); ?>">
                <span class="ovr-gallery-tile__primary-badge" <?php echo $is_primary ? '' : 'hidden'; ?>>
                    <?php esc_html_e( 'Primary', 'ovr-core' ); ?>
                </span>
                <div class="ovr-gallery-tile__actions">
                    <button type="button" class="ovr-gallery-tile__btn" data-action="primary" title="<?php esc_attr_e( 'Make primary', 'ovr-core' ); ?>">
                        <span class="material-symbols-outlined">star</span>
                    </button>
                    <button type="button" class="ovr-gallery-tile__btn" data-action="remove" title="<?php esc_attr_e( 'Remove', 'ovr-core' ); ?>">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="ovr-section-head" style="margin-top:32px">
    <h3><span class="material-symbols-outlined">image</span> <?php esc_html_e( 'Featured Image', 'ovr-core' ); ?></h3>
</div>
<div style="background:var(--ovr-a-surface-low);border:1px solid var(--ovr-a-outline);border-radius:var(--ovr-a-radius-md);padding:14px;font-size:13px;color:var(--ovr-a-text-soft);line-height:1.5">
    <?php esc_html_e( 'The Featured Image (set in the right sidebar of the editor) is used as the cover when no gallery photos are present and as the social/share image. We recommend setting both — most landlords use their best gallery photo as the featured image.', 'ovr-core' ); ?>
</div>

<div class="ovr-section-head" style="margin-top:32px">
    <h3><span class="material-symbols-outlined">description</span> <?php esc_html_e( 'Documents', 'ovr-core' ); ?></h3>
    <span style="font-size:12px;color:var(--ovr-a-text-soft)">
        <?php
        /* translators: %d: max docs */
        printf( esc_html__( 'Up to %d files (PDF, DOC, DOCX)', 'ovr-core' ), (int) $max_docs );
        ?>
    </span>
</div>

<div class="ovr-doc-picker"
     data-ovr-doc-picker
     data-max="<?php echo esc_attr( (string) $max_docs ); ?>">
    <input type="hidden" name="ovr_meta[document_ids]"
           value="<?php echo esc_attr( $documents_csv ); ?>"
           data-ovr-doc-input>

    <ul class="ovr-doc-list" data-ovr-doc-list
        style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;min-height:60px;background:var(--ovr-a-surface-low);border:1.5px dashed var(--ovr-a-outline);border-radius:var(--ovr-a-radius-md);padding:12px">
        <?php
        if ( empty( $document_ids ) ) :
        ?>
            <li class="ovr-doc-empty"
                data-ovr-doc-empty
                style="color:var(--ovr-a-text-soft);font-size:13px;font-style:italic;text-align:center;padding:8px">
                <?php esc_html_e( 'No documents uploaded yet.', 'ovr-core' ); ?>
            </li>
        <?php else :
            foreach ( $document_ids as $doc_id ) :
                $att_id   = (int) $doc_id;
                if ( ! $att_id ) continue;
                $att      = get_post( $att_id );
                if ( ! $att || 'attachment' !== $att->post_type ) continue;
                $url      = wp_get_attachment_url( $att_id );
                $attached_file = get_attached_file( $att_id );
                $filename = basename( $attached_file ? $attached_file : '' );
        ?>
                <li class="ovr-doc-item" data-id="<?php echo esc_attr( (string) $att_id ); ?>"
                    style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fff;border:1px solid var(--ovr-a-outline);border-radius:var(--ovr-a-radius-md)">
                    <span class="material-symbols-outlined" style="color:var(--ovr-a-primary);flex-shrink:0">description</span>
                    <div style="min-width:0;flex:1">
                        <div style="font-size:14px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?php echo esc_html( $att->post_title ?: $filename ); ?>
                        </div>
                        <div style="font-size:12px;color:var(--ovr-a-text-soft)">
                            <?php echo esc_html( $filename ); ?>
                        </div>
                    </div>
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"
                       class="ovr-btn-admin ovr-btn-admin--ghost"
                       style="padding:5px 10px;font-size:12px">
                        <span class="material-symbols-outlined" style="font-size:14px">open_in_new</span>
                    </a>
                    <button type="button" class="ovr-btn-admin ovr-btn-admin--danger" data-action="remove">
                        <span class="material-symbols-outlined" style="font-size:14px">delete</span>
                    </button>
                </li>
        <?php
            endforeach;
        endif;
        ?>
    </ul>

    <button type="button" class="ovr-btn-admin ovr-btn-admin--ghost" data-ovr-doc-add style="margin-top:10px">
        <span class="material-symbols-outlined">upload_file</span>
        <?php esc_html_e( 'Add document', 'ovr-core' ); ?>
    </button>
</div>

<div class="ovr-section-head" style="margin-top:32px">
    <h3><span class="material-symbols-outlined">play_circle</span> <?php esc_html_e( 'Video & 360° Tour', 'ovr-core' ); ?></h3>
</div>

<div class="ovr-field-grid ovr-field-grid--2">
    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-video"><?php esc_html_e( 'Video URL', 'ovr-core' ); ?></label>
        <input type="url" id="ovr-meta-video" name="ovr_meta[video_url]"
               value="<?php echo esc_attr( $video_url ); ?>"
               placeholder="https://www.youtube.com/watch?v=…">
        <p class="ovr-field__hint"><?php esc_html_e( 'YouTube, Vimeo, or self-hosted MP4. Adds a play button overlay to the gallery hero.', 'ovr-core' ); ?></p>
    </div>

    <div class="ovr-field">
        <label class="ovr-field__label" for="ovr-meta-panorama"><?php esc_html_e( '360° Panorama URL', 'ovr-core' ); ?></label>
        <input type="url" id="ovr-meta-panorama" name="ovr_meta[panorama_url]"
               value="<?php echo esc_attr( $panorama_url ); ?>"
               placeholder="https://…/tour.html">
        <p class="ovr-field__hint"><?php esc_html_e( 'Adds a "360°" badge to the listing photo. Link to a Matterport, Kuula, or hosted panorama.', 'ovr-core' ); ?></p>
    </div>
</div>
