<?php
/**
 * Property Media.
 *
 * Scales the media association story so a flat library of thousands of images
 * stays manageable. Three responsibilities:
 *
 *  1. Association — every attachment that belongs to a property (gallery,
 *     documents, video, panorama, featured image) gets a `_ovr_property_id`
 *     back-reference. Written when the property is saved, which also back-fills
 *     existing listings without touching their stored meta.
 *  2. Media-library filtering — a "Filter by property" dropdown in wp-admin
 *     Media → Library (and the media grid modal) so an admin can see exactly one
 *     property's media instead of every image on the site.
 *  3. Safe cleanup — on PERMANENT delete only, attachments referenced solely by
 *     the deleted property are removed. Trash/archive/soft-delete never deletes
 *     media, and any attachment still referenced by another property, a post,
 *     a featured image, the homepage, a village section, a user profile, or any
 *     other content is left untouched.
 *
 * @package OVR\Core
 * @since   1.1.2
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PropertyMedia {

    public const PROPERTY_META = '_ovr_property_id';

    /**
     * Hook everything up.
     */
    public function init(): void {
        add_action( 'save_post_ovr_property', [ $this, 'on_property_save' ], 20, 2 );
        add_action( 'before_delete_post', [ $this, 'on_before_permanent_delete' ], 10, 2 );
        add_action( 'restrict_manage_posts', [ $this, 'render_library_filter' ], 10, 1 );
        add_action( 'pre_get_posts', [ $this, 'apply_library_filter' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_library_js' ] );
        add_filter( 'ajax_query_attachments_args', [ $this, 'filter_media_grid' ] );
    }

    /**
     * After a property is saved, stamp its media with a `_ovr_property_id`
     * back-reference so every image/document is clearly associated with its
     * property. Runs on both full saves and auto-saves.
     *
     * @param int    $post_id Property ID.
     * @param object $post    Post object (ignored).
     */
    public function on_property_save( int $post_id, $post ): void {
        if ( 'ovr_property' !== get_post_type( $post_id ) ) {
            return;
        }
        if ( ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) && ! isset( $_POST['ovr_listing_action'] ) ) {
            return;
        }
        $this->stamp_associations( $post_id );
    }

    /**
     * Collect every attachment id referenced by a property and write the
     * `_ovr_property_id` meta on each. Safe to call repeatedly (idempotent).
     */
    public static function stamp_associations( int $property_id ): void {
        $ids = [];

        $gallery = (string) get_post_meta( $property_id, '_ovr_gallery_ids', true );
        foreach ( array_filter( array_map( 'absint', explode( ',', $gallery ) ) ) as $id ) {
            $ids[] = $id;
        }

        $docs = (string) get_post_meta( $property_id, '_ovr_document_ids', true );
        foreach ( array_filter( array_map( 'absint', explode( ',', $docs ) ) ) as $id ) {
            $ids[] = $id;
        }

        foreach ( [ '_ovr_video_id', '_ovr_panorama_id' ] as $key ) {
            $vid = absint( get_post_meta( $property_id, $key, true ) );
            if ( $vid ) {
                $ids[] = $vid;
            }
        }

        $thumb = (int) get_post_thumbnail_id( $property_id );
        if ( $thumb ) {
            $ids[] = $thumb;
        }

        $ids = array_unique( array_filter( $ids ) );
        foreach ( $ids as $id ) {
            if ( 'attachment' === get_post_type( $id ) ) {
                update_post_meta( (int) $id, self::PROPERTY_META, $property_id );
            }
        }
    }

    /**
     * Before a post is permanently deleted, clean up media that is safe to
     * delete. `before_delete_post` fires for BOTH trash and permanent delete,
     * so we only act when the post is already in the trash (permanent delete of
     * a trashed post) — never on the initial trash (soft delete).
     *
     * @param int $post_id Property ID.
     * @param object $post Post object.
     */
    public function on_before_permanent_delete( int $post_id, $post ): void {
        if ( ! $post || 'ovr_property' !== $post->post_type ) {
            return;
        }
        // Only a permanent delete (post already trashed) triggers cleanup.
        if ( 'trash' !== $post->post_status ) {
            return;
        }
        $this->cleanup_media( $post_id );
    }

    /**
     * Delete attachments referenced only by this property. Shared/borrowed
     * media is always preserved.
     */
    public static function cleanup_media( int $property_id ): void {
        $ids = [];

        foreach ( [ '_ovr_gallery_ids', '_ovr_document_ids' ] as $key ) {
            $csv = (string) get_post_meta( $property_id, $key, true );
            foreach ( array_filter( array_map( 'absint', explode( ',', $csv ) ) ) as $id ) {
                $ids[] = $id;
            }
        }
        foreach ( [ '_ovr_video_id', '_ovr_panorama_id' ] as $key ) {
            $vid = absint( get_post_meta( $property_id, $key, true ) );
            if ( $vid ) {
                $ids[] = $vid;
            }
        }
        $thumb = (int) get_post_thumbnail_id( $property_id );
        if ( $thumb ) {
            $ids[] = $thumb;
        }

        $ids = array_unique( array_filter( $ids ) );
        foreach ( $ids as $id ) {
            if ( self::is_safe_to_delete( (int) $id, $property_id ) ) {
                wp_delete_attachment( (int) $id, true );
            }
        }
    }

    /**
     * Whether an attachment may be permanently removed when its owner property
     * is being permanently deleted. The attachment must be referenced only by
     * the deleted property.
     *
     * @return bool
     */
    public static function is_safe_to_delete( int $attachment_id, int $deleted_property_id ): bool {
        if ( 'attachment' !== get_post_type( $attachment_id ) ) {
            return false;
        }

        // 1. Referenced by another property (via any property's media meta)?
        if ( self::referenced_by_other_property( $attachment_id, $deleted_property_id ) ) {
            return false;
        }

        // 2. Referenced by another post's featured image / content?
        $featured = get_posts( [
            'post_type'              => 'any',
            'post_status'            => 'any',
            'fields'                 => 'ids',
            'posts_per_page'         => 1,
            'meta_key'               => '_thumbnail_id',
            'meta_value'             => (string) $attachment_id,
            'exclude'                => [ $deleted_property_id ],
        ] );
        if ( ! empty( $featured ) ) {
            return false;
        }

        // 3. Referenced by a user profile / avatar? The platform stores profile
        //    photos as `ovr_avatar_id` (and occasionally `_ovr_property_id`).
        //    Check both meta keys so a shared avatar is never destroyed.
        $avatar_hits = get_users( [
            'meta_key'   => 'ovr_avatar_id',
            'meta_value' => (string) $attachment_id,
            'fields'     => 'ID',
            'number'     => 1,
        ] );
        if ( ! empty( $avatar_hits ) ) {
            return false;
        }
        $prop_hits = get_users( [
            'meta_key'   => self::PROPERTY_META,
            'meta_value' => (string) $attachment_id,
            'fields'     => 'ID',
            'number'     => 1,
        ] );
        if ( ! empty( $prop_hits ) ) {
            return false;
        }

        // 4. Referenced in post content (gallery blocks, img tags, hrefs).
        $content_hits = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => 1,
            's'              => 'wp-content/uploads/' . basename( (string) get_attached_file( $attachment_id ) ),
        ] );
        if ( ! empty( $content_hits ) ) {
            return false;
        }

        // 5. Explicit `_ovr_property_id` pointing at another property.
        $owning_property = (int) get_post_meta( $attachment_id, self::PROPERTY_META, true );
        if ( $owning_property && $owning_property !== $deleted_property_id ) {
            return false;
        }

        // 6. Option/theme references (header logo, favicon, featured-homepage, etc.)
        $options = get_option( 'ovr_settings', [] );
        foreach ( $options as $key => $val ) {
            if ( is_string( $val ) && is_numeric( $val ) && (int) $val === $attachment_id ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the attachment appears in another property's media meta.
     */
    private static function referenced_by_other_property( int $attachment_id, int $deleted_property_id ): bool {
        global $wpdb;
        $property_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
             WHERE meta_key IN ( '_ovr_gallery_ids', '_ovr_document_ids', '_ovr_video_id', '_ovr_panorama_id' )
             AND meta_value LIKE %s",
            '%' . $wpdb->esc_like( (string) $attachment_id ) . '%'
        ) );

        foreach ( $property_ids as $pid ) {
            if ( (int) $pid !== $deleted_property_id ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Dropdown in the Media Library list screen to filter by property.
     */
    public function render_library_filter( string $post_type ): void {
        if ( 'attachment' !== $post_type ) {
            return;
        }
        $current = isset( $_GET['ovr_property_id'] ) ? absint( $_GET['ovr_property_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
        $options = $this->property_options();
        ?>
        <label class="screen-reader-text" for="ovr-filter-property"><?php esc_html_e( 'Filter by property', 'ovr-core' ); ?></label>
        <select id="ovr-filter-property" name="ovr_property_id">
            <option value="0"><?php esc_html_e( 'All properties', 'ovr-core' ); ?></option>
            <?php foreach ( $options as $pid => $title ) : ?>
                <option value="<?php echo esc_attr( (string) $pid ); ?>" <?php selected( $current, $pid ); ?>>
                    <?php
                    /* translators: %d: property id */
                    printf( esc_html__( 'Property #%d — %s', 'ovr-core' ), (int) $pid, esc_html( $title ) );
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Apply the property filter to the Media Library list query.
     */
    public function apply_library_filter( \WP_Query $query ): void {
        if ( ! is_admin() || 'attachment' !== $query->get( 'post_type' ) ) {
            return;
        }
        $pid = isset( $_GET['ovr_property_id'] ) ? absint( $_GET['ovr_property_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
        if ( $pid ) {
            $query->set( 'meta_key', self::PROPERTY_META );
            $query->set( 'meta_value', (string) $pid );
        }
    }

    /**
     * Media grid modal (media-upload iframe / REST "attachments" query).
     */
    public function filter_media_grid( array $query ): array {
        $pid = isset( $_REQUEST['ovr_property_id'] ) ? absint( wp_unslash( $_REQUEST['ovr_property_id'] ) ) : 0;
        if ( $pid ) {
            $query['meta_query'][] = [
                'key'   => self::PROPERTY_META,
                'value' => (string) $pid,
            ];
        }
        return $query;
    }

    /**
     * Tiny JS: when the grid modal is open, honour the filter param from the
     * parent screen if present, and reflect it in the modal's own dropdown.
     */
    public function enqueue_library_js( string $hook ): void {
        if ( 'upload.php' !== $hook ) {
            return;
        }
        wp_enqueue_script(
            'ovr-property-media',
            OVR_PLUGIN_URL . 'assets/js/ovr-property-media.js',
            [ 'jquery' ],
            OVR_VERSION,
            true
        );
        wp_localize_script( 'ovr-property-media', 'ovrPropertyMedia', [
            'properties' => $this->property_options( 200 ),
        ] );
    }

    /**
     * Published property id → title options for the filter dropdown. Queried
     * efficiently (no per-post meta loops) and bounded to published properties.
     *
     * @return array<int,string>
     */
    private function property_options( int $limit = 500 ): array {
        $posts = get_posts( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'posts_per_page' => max( 1, $limit ),
            'orderby'        => 'ID',
            'order'          => 'DESC',
        ] );
        $opts = [];
        foreach ( $posts as $p ) {
            $opts[ (int) $p->ID ] = $p->post_title ?: (string) $p->ID;
        }
        return $opts;
    }
}
