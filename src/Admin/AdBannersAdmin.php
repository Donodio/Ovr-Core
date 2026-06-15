<?php
/**
 * Ad Banners admin (Milestone 3 Feature 8).
 *
 * Full CRUD over wp_ovr_ad_banners: create / edit / enable-disable / delete
 * promotional banners, with impression / click / CTR analytics per banner.
 * Self-contained (list + editor rendered inline) — banners are few, so no
 * pagination engine is needed. Backed by {@see \OVR\Frontend\AdBanners}.
 *
 * @package OVR\Admin
 * @since   2.7.0
 */

namespace OVR\Admin;

use OVR\Frontend\AdBanners;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdBannersAdmin {

    public const PAGE_SLUG = 'ovr-core-ad-banners';

    private string $hook_suffix = '';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_post_ovr_ad_banner_save', [ $this, 'handle_save' ] );
        add_action( 'admin_post_ovr_ad_banner_delete', [ $this, 'handle_delete' ] );
        add_action( 'admin_post_ovr_ad_banner_toggle', [ $this, 'handle_toggle' ] );
    }

    public function register_page(): void {
        $this->hook_suffix = (string) add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Ad Banners', 'ovr-core' ),
            __( 'Ad Banners', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function enqueue( string $hook ): void {
        if ( $hook === $this->hook_suffix ) {
            wp_enqueue_media();
        }
    }

    private function page_url(): string {
        return add_query_arg(
            [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG ],
            admin_url( 'edit.php' )
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage ad banners.', 'ovr-core' ) );
        }
        $view = sanitize_key( wp_unslash( $_GET['view'] ?? 'list' ) );
        if ( in_array( $view, [ 'new', 'edit' ], true ) ) {
            $this->render_form( $view );
            return;
        }
        $this->render_list();
    }

    private function render_list(): void {
        $banners    = AdBanners::all();
        $placements = AdBanners::placements();
        $notice     = sanitize_key( wp_unslash( $_GET['msg'] ?? '' ) );

        $total_impr  = 0;
        $total_click = 0;
        foreach ( $banners as $b ) {
            $total_impr  += (int) $b['impressions'];
            $total_click += (int) $b['clicks'];
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Ad Banners', 'ovr-core' ); ?></h1>
            <a href="<?php echo esc_url( add_query_arg( 'view', 'new', $this->page_url() ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add Banner', 'ovr-core' ); ?></a>
            <hr class="wp-header-end">

            <?php $this->notice( $notice ); ?>

            <p class="description" style="max-width:720px">
                <?php esc_html_e( 'Place a banner on the front end with the shortcode below (replace the placement as needed). Clicks are tracked through a redirect, so counts stay accurate even when pages are cached.', 'ovr-core' ); ?>
                <br><code>[ovr_ad_banner placement="homepage"]</code>
            </p>

            <p style="margin:10px 0 16px">
                <strong><?php esc_html_e( 'Totals:', 'ovr-core' ); ?></strong>
                <?php echo esc_html( sprintf( __( '%1$s impressions · %2$s clicks · %3$s CTR', 'ovr-core' ), number_format_i18n( $total_impr ), number_format_i18n( $total_click ), self::ctr( $total_click, $total_impr ) ) ); ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th><?php esc_html_e( 'Banner', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Placement', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Schedule', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Impressions', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Clicks', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'CTR', 'ovr-core' ); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $banners ) ) : ?>
                    <tr><td colspan="8"><?php esc_html_e( 'No banners yet.', 'ovr-core' ); ?></td></tr>
                <?php endif; ?>
                <?php foreach ( $banners as $b ) :
                    $id        = (int) $b['id'];
                    $thumb     = $b['image_id'] ? (string) wp_get_attachment_image_url( (int) $b['image_id'], 'thumbnail' ) : '';
                    $edit_url  = add_query_arg( [ 'view' => 'edit', 'id' => $id ], $this->page_url() );
                    $toggle    = wp_nonce_url( add_query_arg( [ 'action' => 'ovr_ad_banner_toggle', 'id' => $id ], admin_url( 'admin-post.php' ) ), 'ovr_ad_banner_toggle_' . $id );
                    $delete    = wp_nonce_url( add_query_arg( [ 'action' => 'ovr_ad_banner_delete', 'id' => $id ], admin_url( 'admin-post.php' ) ), 'ovr_ad_banner_delete_' . $id );
                    $sched     = $this->schedule_label( $b['starts_at'] ?? null, $b['ends_at'] ?? null );
                ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <span style="width:56px;height:36px;background:#f0f0f1;border-radius:4px;overflow:hidden;flex:0 0 auto;display:inline-block">
                                    <?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php endif; ?>
                                </span>
                                <strong><?php echo esc_html( $b['title'] ?: __( '(untitled)', 'ovr-core' ) ); ?></strong>
                            </div>
                        </td>
                        <td><?php echo esc_html( $placements[ $b['placement'] ] ?? $b['placement'] ); ?></td>
                        <td><?php echo esc_html( $sched ); ?></td>
                        <td><?php echo (int) $b['is_enabled']
                            ? '<span style="color:#2e7d32;font-weight:600">' . esc_html__( 'Enabled', 'ovr-core' ) . '</span>'
                            : '<span style="color:#b3261e;font-weight:600">' . esc_html__( 'Disabled', 'ovr-core' ) . '</span>'; ?></td>
                        <td><?php echo esc_html( number_format_i18n( (int) $b['impressions'] ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( (int) $b['clicks'] ) ); ?></td>
                        <td><?php echo esc_html( self::ctr( (int) $b['clicks'], (int) $b['impressions'] ) ); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'ovr-core' ); ?></a>
                            <a class="button button-small" href="<?php echo esc_url( $toggle ); ?>"><?php echo (int) $b['is_enabled'] ? esc_html__( 'Disable', 'ovr-core' ) : esc_html__( 'Enable', 'ovr-core' ); ?></a>
                            <a class="button button-small button-link-delete" href="<?php echo esc_url( $delete ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this banner permanently?', 'ovr-core' ) ); ?>')"><?php esc_html_e( 'Delete', 'ovr-core' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_form( string $view ): void {
        $banner = null;
        if ( 'edit' === $view ) {
            $banner = AdBanners::get( (int) ( $_GET['id'] ?? 0 ) );
            if ( ! $banner ) {
                wp_die( esc_html__( 'Banner not found.', 'ovr-core' ) );
            }
        }
        $id        = $banner ? (int) $banner['id'] : 0;
        $image_id  = $banner ? (int) $banner['image_id'] : 0;
        $thumb     = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';
        $placements = AdBanners::placements();
        ?>
        <div class="wrap">
            <h1><?php echo $id ? esc_html__( 'Edit Banner', 'ovr-core' ) : esc_html__( 'Add Banner', 'ovr-core' ); ?></h1>
            <p><a href="<?php echo esc_url( $this->page_url() ); ?>">&larr; <?php esc_html_e( 'All banners', 'ovr-core' ); ?></a></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="ovr_ad_banner_save">
                <input type="hidden" name="banner_id" value="<?php echo esc_attr( (string) $id ); ?>">
                <input type="hidden" name="image_id" id="ovr-banner-image-id" value="<?php echo esc_attr( (string) $image_id ); ?>">
                <?php wp_nonce_field( 'ovr_ad_banner_save' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="ovr-banner-title"><?php esc_html_e( 'Title', 'ovr-core' ); ?></label></th>
                        <td><input id="ovr-banner-title" name="title" type="text" class="regular-text" value="<?php echo esc_attr( (string) ( $banner['title'] ?? '' ) ); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Image', 'ovr-core' ); ?></th>
                        <td>
                            <div id="ovr-banner-thumb" style="width:320px;max-width:100%;height:auto;background:#f0f0f1;border-radius:6px;overflow:hidden;margin-bottom:8px;<?php echo $thumb ? '' : 'display:none'; ?>">
                                <img src="<?php echo esc_url( $thumb ); ?>" alt="" style="width:100%;display:block">
                            </div>
                            <button type="button" class="button" id="ovr-banner-pick"><?php esc_html_e( 'Select Image', 'ovr-core' ); ?></button>
                            <p class="description"><?php esc_html_e( 'Wide images work best. The banner is shown at full container width.', 'ovr-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ovr-banner-link"><?php esc_html_e( 'Link URL', 'ovr-core' ); ?></label></th>
                        <td><input id="ovr-banner-link" name="link_url" type="url" class="large-text" placeholder="https://example.com" value="<?php echo esc_attr( (string) ( $banner['link_url'] ?? '' ) ); ?>">
                            <p class="description"><?php esc_html_e( 'Where the banner links to. Leave blank for a non-clickable banner.', 'ovr-core' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><label for="ovr-banner-placement"><?php esc_html_e( 'Placement', 'ovr-core' ); ?></label></th>
                        <td>
                            <select id="ovr-banner-placement" name="placement">
                                <?php foreach ( $placements as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $banner['placement'] ?? 'homepage', $slug ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Schedule', 'ovr-core' ); ?></th>
                        <td>
                            <label><?php esc_html_e( 'Start', 'ovr-core' ); ?> <input type="date" name="starts_at" value="<?php echo esc_attr( self::date_value( $banner['starts_at'] ?? '' ) ); ?>"></label>
                            &nbsp;
                            <label><?php esc_html_e( 'End', 'ovr-core' ); ?> <input type="date" name="ends_at" value="<?php echo esc_attr( self::date_value( $banner['ends_at'] ?? '' ) ); ?>"></label>
                            <p class="description"><?php esc_html_e( 'Optional. Leave blank for no start/end limit.', 'ovr-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ovr-banner-order"><?php esc_html_e( 'Sort Order', 'ovr-core' ); ?></label></th>
                        <td><input id="ovr-banner-order" name="sort_order" type="number" value="<?php echo esc_attr( (string) ( $banner['sort_order'] ?? 0 ) ); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Enabled', 'ovr-core' ); ?></th>
                        <td><label><input type="checkbox" name="is_enabled" value="1" <?php checked( $banner ? (int) $banner['is_enabled'] : 1 ); ?>> <?php esc_html_e( 'Show this banner', 'ovr-core' ); ?></label></td>
                    </tr>
                </table>

                <?php submit_button( $id ? __( 'Update Banner', 'ovr-core' ) : __( 'Create Banner', 'ovr-core' ) ); ?>
            </form>

            <script>
            ( function () {
                var pick = document.getElementById( 'ovr-banner-pick' );
                pick.addEventListener( 'click', function ( e ) {
                    e.preventDefault();
                    var frame = wp.media( {
                        title: '<?php echo esc_js( __( 'Select Banner Image', 'ovr-core' ) ); ?>',
                        library: { type: 'image' },
                        multiple: false,
                        button: { text: '<?php echo esc_js( __( 'Use this image', 'ovr-core' ) ); ?>' }
                    } );
                    frame.on( 'select', function () {
                        var att = frame.state().get( 'selection' ).first().toJSON();
                        document.getElementById( 'ovr-banner-image-id' ).value = att.id;
                        var src = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
                        var wrap = document.getElementById( 'ovr-banner-thumb' );
                        wrap.querySelector( 'img' ).src = src;
                        wrap.style.display = '';
                    } );
                    frame.open();
                } );
            } )();
            </script>
        </div>
        <?php
    }

    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        check_admin_referer( 'ovr_ad_banner_save' );

        $id = (int) ( $_POST['banner_id'] ?? 0 );
        AdBanners::save( [
            'title'      => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            'image_id'   => (int) ( $_POST['image_id'] ?? 0 ),
            'link_url'   => esc_url_raw( wp_unslash( $_POST['link_url'] ?? '' ) ),
            'placement'  => sanitize_key( wp_unslash( $_POST['placement'] ?? 'homepage' ) ),
            'starts_at'  => sanitize_text_field( wp_unslash( $_POST['starts_at'] ?? '' ) ),
            'ends_at'    => sanitize_text_field( wp_unslash( $_POST['ends_at'] ?? '' ) ),
            'sort_order' => (int) ( $_POST['sort_order'] ?? 0 ),
            'is_enabled' => ! empty( $_POST['is_enabled'] ),
        ], $id );

        wp_safe_redirect( add_query_arg( 'msg', $id ? 'updated' : 'created', $this->page_url() ) );
        exit;
    }

    public function handle_delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_ad_banner_delete_' . $id );
        AdBanners::delete( $id );
        wp_safe_redirect( add_query_arg( 'msg', 'deleted', $this->page_url() ) );
        exit;
    }

    public function handle_toggle(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_ad_banner_toggle_' . $id );
        $banner = AdBanners::get( $id );
        if ( $banner ) {
            AdBanners::set_enabled( $id, empty( $banner['is_enabled'] ) );
        }
        wp_safe_redirect( add_query_arg( 'msg', 'toggled', $this->page_url() ) );
        exit;
    }

    private function notice( string $key ): void {
        $map = [
            'created' => __( 'Banner created.', 'ovr-core' ),
            'updated' => __( 'Banner updated.', 'ovr-core' ),
            'deleted' => __( 'Banner deleted.', 'ovr-core' ),
            'toggled' => __( 'Banner status changed.', 'ovr-core' ),
        ];
        if ( isset( $map[ $key ] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $key ] ) . '</p></div>';
        }
    }

    private function schedule_label( ?string $start, ?string $end ): string {
        $start = self::date_value( (string) $start );
        $end   = self::date_value( (string) $end );
        if ( '' === $start && '' === $end ) {
            return __( 'Always', 'ovr-core' );
        }
        $fmt = static fn( string $d ): string => $d ? date_i18n( 'M j, Y', strtotime( $d ) ) : '…';
        return $fmt( $start ) . ' – ' . $fmt( $end );
    }

    /** Treat NULL / 0000-00-00 as empty for form + display. */
    private static function date_value( string $value ): string {
        return ( '' === $value || '0000-00-00' === $value ) ? '' : $value;
    }

    private static function ctr( int $clicks, int $impressions ): string {
        if ( $impressions <= 0 ) {
            return '—';
        }
        return number_format_i18n( $clicks / $impressions * 100, 1 ) . '%';
    }
}
