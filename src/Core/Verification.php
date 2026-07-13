<?php
/**
 * Owner Verification classification (Priority 8, Section 9).
 *
 * An admin-controlled trust level stored on the USER record, so updating a
 * user's status instantly re-labels every listing they own. Surfaced as a badge
 * on the public listing page to combat fraudulent listings.
 *
 * @package OVR\Core
 * @since   1.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Verification {

    /** User meta key holding the verification status. */
    public const META_KEY = 'ovr_verification_status';

    public const NOT_VERIFIED       = 'not_verified';
    public const VERIFIED_HOMEOWNER = 'verified_homeowner';
    public const REGISTERED_PM      = 'registered_pm';

    /**
     * status key => human label. First entry is the default.
     *
     * @return array<string,string>
     */
    public static function statuses(): array {
        return [
            self::NOT_VERIFIED       => __( 'Not Yet Verified', 'ovr-core' ),
            self::VERIFIED_HOMEOWNER => __( 'Verified Homeowner', 'ovr-core' ),
            self::REGISTERED_PM      => __( 'Registered Property Manager', 'ovr-core' ),
        ];
    }

    /**
     * The stored status for a user. Falls back to the legacy boolean
     * `ovr_verified` meta (treated as Verified Homeowner) and finally to
     * Not Yet Verified. Filterable via `ovr_verification_status`.
     */
    public static function get( int $user_id ): string {
        $status = (string) get_user_meta( $user_id, self::META_KEY, true );
        if ( ! isset( self::statuses()[ $status ] ) ) {
            $status = get_user_meta( $user_id, 'ovr_verified', true )
                ? self::VERIFIED_HOMEOWNER
                : self::NOT_VERIFIED;
        }
        /** @var string $status */
        $status = (string) apply_filters( 'ovr_verification_status', $status, $user_id );
        return isset( self::statuses()[ $status ] ) ? $status : self::NOT_VERIFIED;
    }

    /** Human label for a status key. */
    public static function label( string $status ): string {
        return self::statuses()[ $status ] ?? self::statuses()[ self::NOT_VERIFIED ];
    }

    /** Whether a status counts as a positive verification (badge-worthy). */
    public static function is_verified( string $status ): bool {
        return in_array( $status, [ self::VERIFIED_HOMEOWNER, self::REGISTERED_PM ], true );
    }

    /** Material Symbols icon name for a status. */
    public static function icon( string $status ): string {
        return self::is_verified( $status ) ? 'verified' : 'gpp_maybe';
    }
}
