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
     * Initialize — nothing needed at runtime for roles.
     *
     * @since 1.0.0
     */
    public function init(): void {
        // Roles are created on activation. Nothing needed on every load.
    }

    /**
     * Create custom roles and add capabilities.
     *
     * Called during plugin activation.
     *
     * @since 1.0.0
     */
    public static function create_roles(): void {
        // OVR Landlord — can manage their own properties.
        add_role( 'ovr_landlord', __( 'Landlord', 'ovr-core' ), [
            // WordPress defaults.
            'read'                    => true,
            'upload_files'            => true,

            // OVR Property capabilities (mapped to custom CPT).
            'edit_ovr_properties'          => true,
            'edit_published_ovr_properties'=> true,
            'publish_ovr_properties'       => true,
            'delete_ovr_properties'        => true,
            'delete_published_ovr_properties' => true,

            // OVR-specific capabilities.
            'ovr_manage_listings'     => true,
            'ovr_view_dashboard'      => true,
            'ovr_manage_subscription' => true,
            'ovr_send_inquiries'      => true,
            'ovr_view_inquiries'      => true,
            'ovr_manage_profile'      => true,
        ] );

        // Add OVR capabilities to Administrator role.
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin_caps = [
                'edit_ovr_properties',
                'edit_others_ovr_properties',
                'edit_published_ovr_properties',
                'publish_ovr_properties',
                'delete_ovr_properties',
                'delete_others_ovr_properties',
                'delete_published_ovr_properties',
                'read_private_ovr_properties',
                'ovr_manage_listings',
                'ovr_manage_all_listings',
                'ovr_view_dashboard',
                'ovr_manage_subscription',
                'ovr_manage_all_subscriptions',
                'ovr_manage_settings',
                'ovr_manage_users',
                'ovr_view_reports',
                'ovr_manage_reviews',
                'ovr_manage_payments',
                'ovr_send_inquiries',
                'ovr_view_inquiries',
                'ovr_view_all_inquiries',
                'ovr_manage_profile',
            ];

            foreach ( $admin_caps as $cap ) {
                $admin->add_cap( $cap );
            }
        }
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
