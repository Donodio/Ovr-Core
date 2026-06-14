<?php
/**
 * Capability registry.
 *
 * Single source of truth for which OVR capabilities each role holds. Roles
 * (and the navigation / admin menus that gate on them) read from here so the
 * permission matrix is defined in exactly one place and stays in sync.
 *
 * @package OVR\Core
 * @since   2.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capabilities {

    /** Option storing the cap-set version last synced to roles. */
    public const SYNC_OPTION = 'ovr_caps_version';

    /** Bump when the matrix below changes to force a re-sync. */
    public const SYNC_VERSION = '2';

    /**
     * Capabilities granted to the landlord role (in addition to read/upload).
     *
     * @return string[]
     */
    public static function landlord(): array {
        return [
            'edit_ovr_properties',
            'edit_published_ovr_properties',
            'publish_ovr_properties',
            'delete_ovr_properties',
            'delete_published_ovr_properties',
            'ovr_manage_listings',
            'ovr_view_dashboard',
            'ovr_manage_subscription',
            'ovr_manage_membership',
            'ovr_send_inquiries',
            'ovr_view_inquiries',
            'ovr_manage_profile',
            'ovr_manage_own_bookings',
            'ovr_view_own_crm',
            'ovr_submit_tickets',
        ];
    }

    /**
     * Capabilities for the dedicated Support agent role.
     *
     * @return string[]
     */
    public static function support(): array {
        return [
            'read',
            'ovr_view_dashboard',
            'ovr_manage_support',
            'ovr_view_all_inquiries',
            'ovr_view_bookings',
            'ovr_view_reports',
        ];
    }

    /**
     * Full administrator capability set for OVR.
     *
     * @return string[]
     */
    public static function admin(): array {
        return [
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
            'ovr_manage_membership',
            'ovr_manage_settings',
            'ovr_manage_users',
            'ovr_view_reports',
            'ovr_manage_reviews',
            'ovr_manage_payments',
            'ovr_send_inquiries',
            'ovr_view_inquiries',
            'ovr_view_all_inquiries',
            'ovr_manage_profile',
            'ovr_manage_bookings',
            'ovr_view_bookings',
            'ovr_manage_own_bookings',
            'ovr_manage_crm',
            'ovr_view_own_crm',
            'ovr_manage_support',
            'ovr_submit_tickets',
            'ovr_manage_paid_services',
            'ovr_manage_loyalty',
            'ovr_manage_integrations',
        ];
    }

    /**
     * The union of every OVR capability across all roles (deduped, ordered).
     * Excludes core WP property-CPT meta caps so the matrix lists only the
     * plugin's own `ovr_*` permissions.
     *
     * @return string[]
     */
    public static function all_caps(): array {
        $all = array_merge( self::admin(), self::landlord(), self::support() );
        $ovr = array_values( array_unique( array_filter(
            $all,
            static fn( string $cap ): bool => str_starts_with( $cap, 'ovr_' )
        ) ) );
        sort( $ovr );
        return $ovr;
    }

    /**
     * Ensure live roles carry the current capability set. Safe to call on
     * every admin load; only does work when SYNC_VERSION changes. This lets
     * existing installs pick up new Phase 2 caps without re-activation.
     */
    public static function maybe_sync(): void {
        if ( get_option( self::SYNC_OPTION ) === self::SYNC_VERSION ) {
            return;
        }
        self::sync();
        update_option( self::SYNC_OPTION, self::SYNC_VERSION );
    }

    /**
     * Apply the matrix to live roles. Creates the Support role if missing and
     * grants any newly-added caps to landlord + administrator.
     */
    public static function sync(): void {
        $landlord = get_role( 'ovr_landlord' );
        if ( $landlord ) {
            foreach ( self::landlord() as $cap ) {
                $landlord->add_cap( $cap );
            }
        }

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::admin() as $cap ) {
                $admin->add_cap( $cap );
            }
        }

        if ( ! get_role( 'ovr_support' ) ) {
            $caps = array_fill_keys( self::support(), true );
            add_role( 'ovr_support', __( 'Support Agent', 'ovr-core' ), $caps );
        } else {
            $support = get_role( 'ovr_support' );
            foreach ( self::support() as $cap ) {
                $support->add_cap( $cap );
            }
        }
    }
}
