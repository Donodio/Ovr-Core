<?php
/**
 * Owner Verification flag (Priority 8, Section 9).
 *
 * A simple admin-controlled YES/NO "OVR Verified Owner" flag stored on the
 * USER record, so updating a user instantly re-labels every listing they own.
 * Surfaced as a trusted badge on the public listing page to combat fraudulent
 * listings.
 *
 * Canonical source of truth is the boolean `ovr_verified` user meta. Legacy
 * data (the old `ovr_verification_status` 3-state string) is still treated as
 * a positive verification for backward compatibility.
 *
 * @package OVR\Core
 * @since   1.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Verification {

    /** Legacy user meta key holding the old 3-state verification status. */
    public const META_KEY = 'ovr_verification_status';

    /** Canonical user meta key holding the YES/NO "OVR Verified" flag. */
    public const META_VERIFIED = 'ovr_verified';

    public const NOT_VERIFIED       = 'not_verified';
    public const VERIFIED           = 'verified';
    public const VERIFIED_HOMEOWNER = 'verified_homeowner';
    public const REGISTERED_PM      = 'registered_pm';

    /**
     * status key => human label. The active YES state is `verified`; the legacy
     * 3-state strings remain mapped so old data still reads sensibly.
     *
     * @return array<string,string>
     */
    public static function statuses(): array {
        return [
            self::NOT_VERIFIED       => __( 'Not Verified', 'ovr-core' ),
            self::VERIFIED           => __( 'OVR Verified Owner', 'ovr-core' ),
            self::VERIFIED_HOMEOWNER => __( 'Verified Homeowner', 'ovr-core' ),
            self::REGISTERED_PM      => __( 'Registered Property Manager', 'ovr-core' ),
        ];
    }

    /**
     * The canonical verification state for a user: `verified` (YES) or
     * `not_verified` (NO). Filterable via `ovr_verification_status`.
     */
    public static function get( int $user_id ): string {
        $status = self::is_verified_user( $user_id ) ? self::VERIFIED : self::NOT_VERIFIED;
        /** @var string $status */
        $status = (string) apply_filters( 'ovr_verification_status', $status, $user_id );
        return self::VERIFIED === $status ? self::VERIFIED : self::NOT_VERIFIED;
    }

    /** Whether a user is OVR Verified (YES), honoring legacy data. */
    public static function is_verified_user( int $user_id ): bool {
        if ( get_user_meta( $user_id, self::META_VERIFIED, true ) ) {
            return true;
        }
        $status = (string) get_user_meta( $user_id, self::META_KEY, true );
        return in_array( $status, [ self::VERIFIED_HOMEOWNER, self::REGISTERED_PM ], true );
    }

    /** Human label for a status key. */
    public static function label( string $status ): string {
        return self::statuses()[ $status ] ?? self::statuses()[ self::NOT_VERIFIED ];
    }

    /** Whether a status counts as a positive verification (badge-worthy). */
    public static function is_verified( string $status ): bool {
        return self::VERIFIED === $status
            || in_array( $status, [ self::VERIFIED_HOMEOWNER, self::REGISTERED_PM ], true );
    }

    /** Material Symbols icon name for a status. */
    public static function icon( string $status ): string {
        return self::is_verified( $status ) ? 'verified' : 'gpp_maybe';
    }
}
