<?php
/**
 * TEMPORARY one-off data repair — stale Spotlight entitlement on post 346.
 *
 * NOT product functionality. This module exists only to let a logged-in
 * administrator deactivate a single stale Homepage Slider / Spotlight
 * entitlement through the application's own API, then it self-disables.
 *
 * REMOVE THIS FILE (and its single init line in Plugin::boot_admin()) once the
 * repair has been run and verified on staging.
 *
 * @package OVR\Admin
 */

namespace OVR\Admin;

use OVR\Subscription\UpgradeActivator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SpotlightRepair {

    /** Admin option marking the one-time repair as completed. */
    private const OPTION_DONE = 'ovr_repair_346_spotlight_done';

    /** Target property (stale Spotlight entitlement). */
    private const PROPERTY_ID = 346;

    /** Related property that must NOT change (Property Eight). */
    private const PROTECT_ID = 240;

    /** Homepage Slider / Spotlight service key in UpgradeActivator::MAP. */
    private const SERVICE = 'homepage_slider';

    private const NONCE_ACTION = 'ovr_repair_346_spotlight';
    private const MENU_SLUG    = 'ovr-spotlight-repair-346';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_repair_346_spotlight', [ $this, 'handle' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'tools.php',
            __( 'OVR Spotlight Repair (temporary)', 'ovr-core' ),
            __( 'OVR Spotlight Repair (temp)', 'ovr-core' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_page' ]
        );
    }

    private function can_run(): bool {
        return current_user_can( 'manage_options' );
    }

    private function is_done(): bool {
        return (bool) get_option( self::OPTION_DONE );
    }

    private function flag( int $id ): string {
        return (string) get_post_meta( $id, '_ovr_in_slider', true );
    }

    private function expires( int $id ): string {
        return (string) get_post_meta( $id, '_ovr_slider_expires', true );
    }

    public function render_page(): void {
        if ( ! $this->can_run() ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'OVR Spotlight Repair (temporary)', 'ovr-core' ) . '</h1>';

        if ( $this->is_done() ) {
            echo '<div class="notice notice-success"><p>' .
                esc_html__( 'Property 346 Spotlight entitlement successfully deactivated.', 'ovr-core' ) .
                '</p></div>';
            echo '<p><strong>' . esc_html__( 'This one-time repair has already been completed. No further action is needed.', 'ovr-core' ) . '</strong> ' .
                esc_html__( 'You may now remove this temporary tool.', 'ovr-core' ) . '</p>';
            echo $this->state_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_html internally.
            echo '</div>';
            return;
        }

        echo '<p>' . esc_html__( 'This tool deactivates the stale Homepage Slider / Spotlight entitlement on listing 346 using the plugin\'s own deactivation API. It runs only when you click the button below and cannot be triggered from the front end.', 'ovr-core' ) . '</p>';
        echo $this->state_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_html internally.

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( self::NONCE_ACTION );
        echo '<input type="hidden" name="action" value="ovr_repair_346_spotlight">';
        submit_button( __( 'Deactivate Property 346 Spotlight entitlement', 'ovr-core' ), 'primary', 'submit', false );
        echo '</form></div>';
    }

    /**
     * BEFORE/after state table (also used on the completed screen).
     */
    private function state_table(): string {
        $rows = [
            [ '346', '_ovr_in_slider', $this->flag( self::PROPERTY_ID ) ],
            [ '346', '_ovr_slider_expires', $this->expires( self::PROPERTY_ID ) ],
            [ '240', '_ovr_in_slider', $this->flag( self::PROTECT_ID ) ],
            [ '240', '_ovr_slider_expires', $this->expires( self::PROTECT_ID ) ],
        ];
        $out = '<table class="widefat striped" style="max-width:640px;margin:12px 0"><thead><tr><th>Property</th><th>Field</th><th>Value</th></tr></thead><tbody>';
        foreach ( $rows as $r ) {
            $out .= '<tr><td>' . esc_html( $r[0] ) . '</td><td>' . esc_html( $r[1] ) . '</td><td><code>' . esc_html( '' === $r[2] ? '(empty)' : $r[2] ) . '</code></td></tr>';
        }
        $out .= '</tbody></table>';
        return $out;
    }

    public function handle(): void {
        if ( ! $this->can_run() ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        check_admin_referer( self::NONCE_ACTION );

        $redirect = add_query_arg( 'page', self::MENU_SLUG, admin_url( 'tools.php' ) );

        // One-time guard: never run twice.
        if ( $this->is_done() ) {
            wp_safe_redirect( add_query_arg( 'ovr_repair', 'already', $redirect ) );
            exit;
        }

        // Record protected property 240 before.
        $protect_before = [ $this->flag( self::PROTECT_ID ), $this->expires( self::PROTECT_ID ) ];

        // Canonical deactivation of the stale Spotlight entitlement on 346.
        UpgradeActivator::deactivate( self::PROPERTY_ID, self::SERVICE );

        // Record protected property 240 after and refuse to claim success if it moved.
        $protect_after = [ $this->flag( self::PROTECT_ID ), $this->expires( self::PROTECT_ID ) ];
        if ( $protect_before !== $protect_after ) {
            wp_safe_redirect( add_query_arg( 'ovr_repair', 'protect_failed', $redirect ) );
            exit;
        }

        update_option( self::OPTION_DONE, 1, false );

        wp_safe_redirect( add_query_arg( 'ovr_repair', 'done', $redirect ) );
        exit;
    }
}
