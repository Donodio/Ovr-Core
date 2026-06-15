<?php
/**
 * Cloud Storage dashboard (Milestone 3 Feature 13).
 *
 * Monitors the Backblaze B2 offloader: connection status, offload coverage
 * (how many images are offloaded vs pending), total bytes stored, and originals
 * whose local copy is missing. Provides recovery tools — test connection,
 * offload pending images, and restore missing originals from B2.
 *
 * @package OVR\Admin
 * @since   2.8.0
 */

namespace OVR\Admin;

use OVR\Storage\BackblazeB2Client;
use OVR\Storage\StorageOffloader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StorageAdmin {

    public const PAGE_SLUG = 'ovr-core-storage';
    private const BATCH     = 20;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_storage_test', [ $this, 'handle_test' ] );
        add_action( 'admin_post_ovr_storage_offload', [ $this, 'handle_offload' ] );
        add_action( 'admin_post_ovr_storage_restore', [ $this, 'handle_restore' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Cloud Storage', 'ovr-core' ),
            __( 'Cloud Storage', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    private function page_url(): string {
        return add_query_arg(
            [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG ],
            admin_url( 'edit.php' )
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage storage.', 'ovr-core' ) );
        }

        $configured = BackblazeB2Client::is_configured();
        $settings   = BackblazeB2Client::settings();
        $stats      = StorageOffloader::stats();
        $recent     = StorageOffloader::recent( 15 );
        $coverage   = $stats['images_total'] > 0
            ? round( ( $stats['images_total'] - $stats['pending'] ) / $stats['images_total'] * 100 )
            : 0;
        $notice     = sanitize_key( wp_unslash( $_GET['msg'] ?? '' ) );
        $detail     = isset( $_GET['detail'] ) ? sanitize_text_field( wp_unslash( $_GET['detail'] ) ) : '';
        $action_url = admin_url( 'admin-post.php' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Cloud Storage', 'ovr-core' ); ?></h1>
            <p class="description" style="max-width:760px">
                <?php esc_html_e( 'Backblaze B2 offloads media to low-cost cloud storage and serves it from there. Monitor coverage and recover files below. Credentials live under Settings → Storage.', 'ovr-core' ); ?>
            </p>

            <?php $this->notice( $notice, $detail ); ?>

            <!-- Connection status -->
            <div class="ovr-storage-card" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin:14px 0;max-width:760px">
                <h2 style="margin-top:0"><?php esc_html_e( 'Connection', 'ovr-core' ); ?></h2>
                <?php if ( $configured ) : ?>
                    <p style="color:#2e7d32;font-weight:600"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'B2 offloading is enabled.', 'ovr-core' ); ?></p>
                    <p><?php printf( esc_html__( 'Bucket: %s', 'ovr-core' ), '<code>' . esc_html( (string) ( $settings['b2_bucket_name'] ?? '' ) ) . '</code>' ); ?>
                       <?php echo ! empty( $settings['b2_delete_local'] ) ? esc_html__( '· Local copies of sized images are removed after upload.', 'ovr-core' ) : esc_html__( '· Local copies are kept.', 'ovr-core' ); ?>
                    </p>
                    <form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin-top:8px">
                        <input type="hidden" name="action" value="ovr_storage_test">
                        <?php wp_nonce_field( 'ovr_storage_test' ); ?>
                        <button class="button"><?php esc_html_e( 'Test Connection', 'ovr-core' ); ?></button>
                    </form>
                <?php else : ?>
                    <p style="color:#b3261e;font-weight:600"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'B2 offloading is not configured.', 'ovr-core' ); ?></p>
                    <p><a class="button" href="<?php echo esc_url( add_query_arg( [ 'post_type' => 'ovr_property', 'page' => Settings::PAGE_SLUG ], admin_url( 'edit.php' ) ) ); ?>"><?php esc_html_e( 'Configure Storage', 'ovr-core' ); ?></a></p>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;max-width:760px">
                <?php
                $this->stat_card( __( 'Offload Coverage', 'ovr-core' ), $coverage . '%', sprintf( __( '%1$s of %2$s images', 'ovr-core' ), number_format_i18n( $stats['images_total'] - $stats['pending'] ), number_format_i18n( $stats['images_total'] ) ) );
                $this->stat_card( __( 'Files in B2', 'ovr-core' ), number_format_i18n( $stats['rows'] ), sprintf( __( '%s attachments', 'ovr-core' ), number_format_i18n( $stats['attachments'] ) ) );
                $this->stat_card( __( 'Stored', 'ovr-core' ), size_format( $stats['bytes'] ?: 0 ), __( 'total in cloud', 'ovr-core' ) );
                $this->stat_card( __( 'Pending Offload', 'ovr-core' ), number_format_i18n( $stats['pending'] ), __( 'images not yet uploaded', 'ovr-core' ), $stats['pending'] > 0 ? 'warn' : '' );
                $this->stat_card( __( 'Local Missing', 'ovr-core' ), number_format_i18n( $stats['local_missing'] ), __( 'originals only in B2', 'ovr-core' ), $stats['local_missing'] > 0 ? 'alert' : '' );
                ?>
            </div>

            <!-- Recovery tools -->
            <div style="margin:18px 0;max-width:760px;display:flex;gap:10px;flex-wrap:wrap">
                <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                    <input type="hidden" name="action" value="ovr_storage_offload">
                    <?php wp_nonce_field( 'ovr_storage_offload' ); ?>
                    <button class="button button-primary" <?php disabled( ! $configured || $stats['pending'] === 0 ); ?>>
                        <?php printf( esc_html__( 'Offload Pending (up to %d)', 'ovr-core' ), self::BATCH ); ?>
                    </button>
                </form>
                <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                    <input type="hidden" name="action" value="ovr_storage_restore">
                    <?php wp_nonce_field( 'ovr_storage_restore' ); ?>
                    <button class="button" <?php disabled( $stats['local_missing'] === 0 ); ?>>
                        <?php printf( esc_html__( 'Restore Missing Originals (up to %d)', 'ovr-core' ), self::BATCH ); ?>
                    </button>
                </form>
            </div>

            <!-- Recent files -->
            <h2><?php esc_html_e( 'Recently Offloaded', 'ovr-core' ); ?></h2>
            <table class="wp-list-table widefat fixed striped" style="max-width:980px">
                <thead><tr>
                    <th><?php esc_html_e( 'Attachment', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Size', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Bytes', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Key', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'When', 'ovr-core' ); ?></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $recent ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'Nothing offloaded yet.', 'ovr-core' ); ?></td></tr>
                <?php endif; ?>
                <?php foreach ( $recent as $r ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( get_edit_post_link( (int) $r['attachment_id'] ) ?: '#' ); ?>">#<?php echo (int) $r['attachment_id']; ?></a></td>
                        <td><?php echo esc_html( (string) $r['size_name'] ); ?></td>
                        <td><?php echo esc_html( size_format( (int) $r['file_size'] ?: 0 ) ); ?></td>
                        <td><code style="font-size:11px"><?php echo esc_html( (string) $r['storage_key'] ); ?></code></td>
                        <td><?php echo esc_html( (string) $r['created_at'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function stat_card( string $label, string $value, string $sub, string $tone = '' ): void {
        $color = 'warn' === $tone ? '#b26a00' : ( 'alert' === $tone ? '#b3261e' : '#1d2327' );
        ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px">
            <div style="font-size:12px;color:#646970;text-transform:uppercase;letter-spacing:.04em"><?php echo esc_html( $label ); ?></div>
            <div style="font-size:26px;font-weight:700;color:<?php echo esc_attr( $color ); ?>;margin:4px 0"><?php echo esc_html( $value ); ?></div>
            <div style="font-size:12px;color:#646970"><?php echo esc_html( $sub ); ?></div>
        </div>
        <?php
    }

    public function handle_test(): void {
        $this->guard( 'ovr_storage_test' );
        $result = BackblazeB2Client::test_connection();
        wp_safe_redirect( add_query_arg( [
            'msg'    => $result['ok'] ? 'test_ok' : 'test_fail',
            'detail' => rawurlencode( $result['message'] ),
        ], $this->page_url() ) );
        exit;
    }

    public function handle_offload(): void {
        $this->guard( 'ovr_storage_offload' );
        $done = ( new StorageOffloader() )->offload_pending( self::BATCH );
        wp_safe_redirect( add_query_arg( [ 'msg' => 'offloaded', 'detail' => (string) $done ], $this->page_url() ) );
        exit;
    }

    public function handle_restore(): void {
        $this->guard( 'ovr_storage_restore' );
        $done = ( new StorageOffloader() )->restore_missing( self::BATCH );
        wp_safe_redirect( add_query_arg( [ 'msg' => 'restored', 'detail' => (string) $done ], $this->page_url() ) );
        exit;
    }

    private function guard( string $nonce ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        check_admin_referer( $nonce );
    }

    private function notice( string $key, string $detail ): void {
        if ( '' === $key ) {
            return;
        }
        $map = [
            'test_ok'   => [ 'success', __( 'Connection OK. %s', 'ovr-core' ) ],
            'test_fail' => [ 'error', __( 'Connection failed. %s', 'ovr-core' ) ],
            'offloaded' => [ 'success', __( 'Offloaded %s pending image(s).', 'ovr-core' ) ],
            'restored'  => [ 'success', __( 'Restored %s missing original(s) from B2.', 'ovr-core' ) ],
        ];
        if ( ! isset( $map[ $key ] ) ) {
            return;
        }
        [ $type, $tmpl ] = $map[ $key ];
        $text = sprintf( $tmpl, esc_html( $detail ) );
        printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), wp_kses_post( $text ) );
    }
}
