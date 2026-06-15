<?php
/**
 * Custom Roles and Capabilities.
 *
 * Registers the OVR Landlord role with specific capabilities for
 * managing their own properties and viewing dashboard features.
 *
 * @package OVR\Core
 * @since   1.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roles {

    /**
     * Initialize — keep live roles in sync with the capability registry.
     *
     * create_roles() only runs on activation, so existing installs would
     * never receive caps added in later releases. maybe_sync() reconciles
     * them once per cap-set version bump.
     *
     * @since 1.0.0
     */
    public function init(): void {
        add_action( 'admin_init', [ Capabilities::class, 'maybe_sync' ] );
    }

    /**
     * Create custom roles and add capabilities.
     *
     * Called during plugin activation. Capability sets live in the central
     * Capabilities registry so the permission matrix is defined once.
     *
     * @since 1.0.0
     */
    public static function create_roles(): void {
        // OVR Landlord — manages their own properties + Phase 2 tooling.
        $landlord_caps = array_fill_keys( Capabilities::landlord(), true );
        $landlord_caps['read']         = true;
        $landlord_caps['upload_files'] = true;
        add_role( 'ovr_landlord', __( 'Landlord', 'ovr-core' ), $landlord_caps );

        // Support Agent role + administrator caps are applied by the registry,
        // which also stamps the synced cap-set version so init()'s maybe_sync()
        // stays a no-op until the matrix changes again.
        Capabilities::sync();
        update_option( Capabilities::SYNC_OPTION, Capabilities::SYNC_VERSION );
    }

    /**
     * Remove custom roles. Used during uninstall.
     *
     * @since 1.0.0
     */
    public static function remove_roles(): void {
        remove_role( 'ovr_landlord' );

        // Remove custom capabilities from admin.
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $caps = array_filter(
                array_keys( $admin->capabilities ),
                fn( $cap ) => str_starts_with( $cap, 'ovr_' ) || str_contains( $cap, 'ovr_properties' )
            );
            foreach ( $caps as $cap ) {
                $admin->remove_cap( $cap );
            }
        }
    }
}
