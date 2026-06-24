<?php
/**
 * Deleted Listings admin module (Feature G).
 *
 * Soft-deleted listings are WordPress-trashed (post_status=trash) by the
 * landlord's Delete action, so nothing is destroyed up front. This screen lets
 * an admin review trashed ovr_property posts and either Restore them or
 * Permanently Delete them, and shows how long each has until automatic cleanup.
 *
 * A daily cron (ovr_hard_delete_listings) permanently removes any listing that
 * has been in the trash longer than the configured retention window
 * (Settings → Listings → "Deleted Listing Retention", default 90 days).
 *
 * @package OVR\Admin
 * @since   2.1.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DeletedListingsAdmin {

    public const PAGE_SLUG = 'ovr-core-deleted-listings';
    public const CRON_HOOK = 'ovr_hard_delete_listings';

    /** Default retention window in days when the setting is unset. */
    public const DEFAULT_RETENTION_DAYS = 90;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_listing_restore', [ $this, 'handle_restore' ] );
        add_action( 'admin_post_ovr_listing_purge', [ $this, 'handle_purge' ] );
    }

    /**
     * Register the daily hard-delete cron + handler. Called unconditionally from
     * the main plugin boot (NOT admin-only): wp-cron fires outside wp-admin, so
     * the handler must be attached on every request, not just admin screens.
     */
    public static function register_cron(): void {
        add_action( self::CRON_HOOK, [ self::class, 'purge_expired' ] );
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    /**
     * The configured retention window in days (minimum 1).
     */
    public static function retention_days(): int {
        $settings = (array) get_option( 'ovr_settings', [] );
        return max( 1, (int) ( $settings['listing_retention_days'] ?? self::DEFAULT_RETENTION_DAYS ) );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Deleted Listings', 'ovr-core' ),
            __( 'Deleted Listings', 'ovr-core' ),
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

    /**
     * Render the trashed-listings recovery table.
     */
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage deleted listings.', 'ovr-core' ) );
        }

        $trashed = get_posts( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'trash',
            'posts_per_page' => 200,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );

        $retention = self::retention_days();
        $notice    = isset( $_GET['ovr_trash'] ) ? sanitize_key( wp_unslash( $_GET['ovr_trash'] ) ) : '';
        $page_url  = $this->page_url();
        $now       = time();

        $trash_count = count( $trashed );
        $due_count   = 0;
        foreach ( $trashed as $p ) {
            $tt = (int) get_post_meta( $p->ID, '_wp_trash_meta_time', true );
            if ( $tt && ( $tt + $retention * DAY_IN_SECONDS ) <= $now ) {
                $due_count++;
            }
        }
        ?>
        <div class="wrap ovr-adm">
            <style>#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}</style>
            <div class="ovr-adm-wrap">
                <div class="ovr-adm-head">
                    <div>
                        <h1><?php esc_html_e( 'Deleted Listings', 'ovr-core' ); ?></h1>
                        <p>
                            <?php
                            /* translators: %d: retention days */
                            printf( esc_html__( 'Soft-deleted listings are recoverable here. They are permanently removed automatically %d days after deletion.', 'ovr-core' ), (int) $retention );
                            ?>
                        </p>
                    </div>
                </div>

                <?php if ( 'restored' === $notice ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--success"><span class="material-symbols-outlined">check_circle</span><span><?php esc_html_e( 'Listing restored.', 'ovr-core' ); ?></span></div>
                <?php elseif ( 'purged' === $notice ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--success"><span class="material-symbols-outlined">check_circle</span><span><?php esc_html_e( 'Listing permanently deleted.', 'ovr-core' ); ?></span></div>
                <?php endif; ?>

                <div class="ovr-adm-stats ovr-adm-stats--3">
                    <div class="ovr-adm-stat">
                        <div class="ovr-adm-stat-ic"><span class="material-symbols-outlined">delete</span></div>
                        <div><div class="ovr-adm-stat-v"><?php echo esc_html( number_format_i18n( $trash_count ) ); ?></div><div class="ovr-adm-stat-l"><?php esc_html_e( 'In Trash', 'ovr-core' ); ?></div></div>
                    </div>
                    <div class="ovr-adm-stat">
                        <div class="ovr-adm-stat-ic"><span class="material-symbols-outlined">schedule</span></div>
                        <div><div class="ovr-adm-stat-v"><?php echo esc_html( number_format_i18n( $due_count ) ); ?></div><div class="ovr-adm-stat-l"><?php esc_html_e( 'Due for Cleanup', 'ovr-core' ); ?></div></div>
                    </div>
                    <div class="ovr-adm-stat">
                        <div class="ovr-adm-stat-ic"><span class="material-symbols-outlined">event_repeat</span></div>
                        <div><div class="ovr-adm-stat-v"><?php echo esc_html( number_format_i18n( $retention ) ); ?></div><div class="ovr-adm-stat-l"><?php esc_html_e( 'Retention (days)', 'ovr-core' ); ?></div></div>
                    </div>
                </div>

                <div class="ovr-adm-card">
                <?php if ( empty( $trashed ) ) : ?>
                    <div class="ovr-adm-empty">
                        <span class="material-symbols-outlined">recycling</span>
                        <h3><?php esc_html_e( 'No deleted listings', 'ovr-core' ); ?></h3>
                        <p><?php esc_html_e( 'Trashed listings will appear here for review and recovery.', 'ovr-core' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="ovr-adm-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'ID', 'ovr-core' ); ?></th>
                                <th><?php esc_html_e( 'Listing Name', 'ovr-core' ); ?></th>
                                <th><?php esc_html_e( 'Owner', 'ovr-core' ); ?></th>
                                <th><?php esc_html_e( 'Deleted', 'ovr-core' ); ?></th>
                                <th><?php esc_html_e( 'Auto-removal', 'ovr-core' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $trashed as $p ) :
                            $trash_time = (int) get_post_meta( $p->ID, '_wp_trash_meta_time', true );
                            $owner      = get_userdata( (int) $p->post_author );
                            $purge_ts   = $trash_time ? $trash_time + $retention * DAY_IN_SECONDS : 0;

                            $restore_url = wp_nonce_url(
                                admin_url( 'admin-post.php?action=ovr_listing_restore&post=' . $p->ID ),
                                'ovr_listing_restore_' . $p->ID
                            );
                            $purge_url = wp_nonce_url(
                                admin_url( 'admin-post.php?action=ovr_listing_purge&post=' . $p->ID ),
                                'ovr_listing_purge_' . $p->ID
                            );
                        ?>
                            <tr>
                                <td class="ovr-adm-mono">#<?php echo (int) $p->ID; ?></td>
                                <td><div class="ovr-adm-name"><?php echo esc_html( $p->post_title ?: __( '(untitled)', 'ovr-core' ) ); ?></div></td>
                                <td><?php echo esc_html( $owner ? $owner->display_name : '—' ); ?></td>
                                <td><?php echo $trash_time ? esc_html( date_i18n( 'M j, Y', $trash_time ) ) : '—'; ?></td>
                                <td>
                                    <?php
                                    if ( $purge_ts ) {
                                        if ( $purge_ts <= $now ) {
                                            echo '<span class="ovr-adm-status ovr-adm-status--danger">' . esc_html__( 'Due (next cleanup)', 'ovr-core' ) . '</span>';
                                        } else {
                                            echo '<span class="ovr-adm-status ovr-adm-status--warn">';
                                            /* translators: %s: human time diff */
                                            printf( esc_html__( 'in %s', 'ovr-core' ), esc_html( human_time_diff( $now, $purge_ts ) ) );
                                            echo '</span>';
                                        }
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="ovr-adm-cell-actions">
                                        <a href="<?php echo esc_url( $restore_url ); ?>" class="ovr-adm-act ovr-adm-act--ok" title="<?php esc_attr_e( 'Restore', 'ovr-core' ); ?>"><span class="material-symbols-outlined">restore_from_trash</span></a>
                                        <a href="<?php echo esc_url( $purge_url ); ?>" class="ovr-adm-act ovr-adm-act--danger" title="<?php esc_attr_e( 'Delete Permanently', 'ovr-core' ); ?>"
                                           onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this listing? This cannot be undone.', 'ovr-core' ) ); ?>');"><span class="material-symbols-outlined">delete_forever</span></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Restore (untrash) a listing.
     */
    public function handle_restore(): void {
        $post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
        $nonce   = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

        if ( ! current_user_can( 'manage_options' ) || ! $post_id || ! wp_verify_nonce( $nonce, 'ovr_listing_restore_' . $post_id ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        $post = get_post( $post_id );
        if ( $post && 'ovr_property' === $post->post_type ) {
            wp_untrash_post( $post_id );
            // wp_untrash_post restores to 'draft' on some setups; force publish
            // so the listing returns to its live state (visibility still gated
            // by _ovr_listing_status / _ovr_admin_status).
            wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
        }
        wp_safe_redirect( add_query_arg( 'ovr_trash', 'restored', $this->page_url() ) );
        exit;
    }

    /**
     * Permanently delete a single trashed listing.
     */
    public function handle_purge(): void {
        $post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
        $nonce   = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

        if ( ! current_user_can( 'manage_options' ) || ! $post_id || ! wp_verify_nonce( $nonce, 'ovr_listing_purge_' . $post_id ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        $post = get_post( $post_id );
        if ( $post && 'ovr_property' === $post->post_type ) {
            wp_delete_post( $post_id, true );
        }
        wp_safe_redirect( add_query_arg( 'ovr_trash', 'purged', $this->page_url() ) );
        exit;
    }

    /**
     * Cron: permanently delete trashed listings past the retention window.
     */
    public static function purge_expired(): void {
        $cutoff = time() - self::retention_days() * DAY_IN_SECONDS;

        $expired = get_posts( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'trash',
            'posts_per_page' => 100,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_wp_trash_meta_time',
                    'value'   => $cutoff,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ] );

        foreach ( (array) $expired as $pid ) {
            wp_delete_post( (int) $pid, true );
        }

        if ( $expired && class_exists( '\OVR\Core\AuditLog' ) ) {
            \OVR\Core\AuditLog::record( 'listing.hard_delete', 'property', 0, [ 'count' => count( $expired ) ] );
        }
    }
}
