<?php
/**
 * Admin Property Editor Screen.
 *
 * Renders the SAME tabbed editor landlords use (templates/dashboard/tab-add-listing.php)
 * as a normal wp-admin page — which guarantees the full admin chrome (menu,
 * admin bar, footer) stays in place. The CPT's "Add New" and "Edit" links are
 * redirected here, and the save runs through the shared `ovr_save_listing`
 * handler (admins bypass the subscription gate), returning to the list.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PropertyEditorScreen {

    private const POST_TYPE = 'ovr_property';
    private const PAGE_SLUG = 'ovr-edit-listing';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'load-post-new.php', [ $this, 'redirect_new' ] );
        add_action( 'load-post.php', [ $this, 'redirect_edit' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_notices', [ $this, 'maybe_notice' ] );
    }

    /**
     * Register the editor as a submenu page under "OVR Properties" and drop the
     * native "Add New" item so there's a single, consistent entry point.
     */
    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __( 'Add New Listing', 'ovr-core' ),
            __( 'Add New Listing', 'ovr-core' ),
            'edit_posts',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
        remove_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'post-new.php?post_type=' . self::POST_TYPE );
    }

    /** Send the CPT "Add New" screen to our editor page. */
    public function redirect_new(): void {
        if ( ( $_GET['post_type'] ?? '' ) === self::POST_TYPE ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
            exit;
        }
    }

    /** Send the CPT "Edit" screen to our editor page. */
    public function redirect_edit(): void {
        $action  = $_REQUEST['action'] ?? '';
        $post_id = absint( $_REQUEST['post'] ?? 0 );
        if ( 'edit' === $action && $post_id && self::POST_TYPE === get_post_type( $post_id ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&post=' . $post_id ) );
            exit;
        }
    }

    /** Load the Material Symbols icon font on our editor page. */
    public function enqueue( string $hook ): void {
        if ( ( $_GET['page'] ?? '' ) !== self::PAGE_SLUG ) {
            return;
        }
        wp_enqueue_style(
            'ovr-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&family=Inter:wght@400;500;600;700&display=swap',
            [],
            null
        );
    }

    /** Render the editor inside the standard admin page wrapper. */
    public function render_page(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to edit listings.', 'ovr-core' ) );
        }

        $post_id = absint( $_GET['post'] ?? 0 );
        $post    = $post_id ? get_post( $post_id ) : null;
        if ( $post && self::POST_TYPE !== $post->post_type ) {
            $post = null;
        }
        if ( $post && ! current_user_can( 'edit_post', $post->ID ) ) {
            wp_die( esc_html__( 'You are not allowed to edit this listing.', 'ovr-core' ) );
        }

        $list_url = admin_url( 'admin.php?page=ovr-properties' );

        echo '<div class="wrap">';
        echo '<div style="margin-bottom:12px;font-size:13px;color:#646970">';
        echo '<a href="' . esc_url( $list_url ) . '" style="text-decoration:none;color:#2271b1">&larr; ' . esc_html__( 'Back to All Properties', 'ovr-core' ) . '</a>';
        echo '</div>';
        echo '<h1 class="wp-heading-inline">' . ( $post ? esc_html__( 'Edit Listing', 'ovr-core' ) : esc_html__( 'Add New Listing', 'ovr-core' ) ) . '</h1>';
        echo '<hr class="wp-header-end">';

        // .ovr-ld scope + its design tokens + the icon-font utility class (which
        // is otherwise scoped to .ovr-wrap on the front end).
        echo '<style>'
            . '.ovr-ld{--p:#004c4c;--pc:#006666;--opc:#93e1e0;--pfd:#86d4d3;--sec:#006c4a;--secc:#74f7be;--ter:#735c00;--terc:#cca72f;--err:#ba1a1a;--errc:#ffdad6;--bg:#f7faf9;--surf:#fff;--sclow:#f1f4f3;--sv:#3f4948;--outline:#6f7979;--ov:#bec9c8;--on:#181c1c;font-family:Inter,system-ui,-apple-system,sans-serif}'
            . '.ovr-ld *{box-sizing:border-box}'
            . '#wpbody-content .ovr-ld{margin:16px 40px 24px 20px}'
            . '.ovr-ld .material-symbols-outlined{font-family:"Material Symbols Outlined";font-weight:normal;font-style:normal;font-size:20px;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;-webkit-font-feature-settings:"liga";font-feature-settings:"liga";-webkit-font-smoothing:antialiased}'
            . '</style>';

        echo '<div class="ovr-ld">';
        echo TemplateLoader::get_rendered( 'dashboard/tab-add-listing.php', [
            'post'             => $post,
            'can_create'       => true,
            'block_reason'     => '',
            'save_action'      => admin_url( 'admin-post.php' ),
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'listing_nonce'    => wp_create_nonce( 'ovr_listing_action' ),
            'props_url'        => $list_url,
            'subscription_url' => $list_url,
            'return_url'       => $list_url,
        ] );
        echo '</div>';
        echo '</div>';
    }

    /** Surface the save result on the property list screen. */
    public function maybe_notice(): void {
        if ( ! isset( $_GET['page'] ) || 'ovr-properties' !== $_GET['page'] ) {
            return;
        }
        $notice = isset( $_GET['ovr_listing'] ) ? sanitize_key( wp_unslash( $_GET['ovr_listing'] ) ) : '';
        $map    = [
            'created' => __( 'Listing published.', 'ovr-core' ),
            'updated' => __( 'Listing updated.', 'ovr-core' ),
            'draft'   => __( 'Listing saved as a draft.', 'ovr-core' ),
        ];
        if ( isset( $map[ $notice ] ) ) {
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $map[ $notice ] ) );
        }
    }
}
