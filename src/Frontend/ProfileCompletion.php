<?php
/**
 * Profile Completion Helper.
 *
 * Single source of truth for "how complete is this landlord's profile?"
 * used by LoginHandler (to decide whether to show the onboarding screen)
 * and Onboarding (to render the progress bar).
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ProfileCompletion {

    /**
     * Field weights. Sum should equal 100. Today every field is equally
     * important, so each contributes 25 points.
     *
     * @var array<string, int>
     */
    private const FIELDS = [
        'first_name' => 25,
        'last_name'  => 25,
        'ovr_phone'  => 25,
        'description'=> 25,
    ];

    /**
     * Compute profile completion percentage (0-100, integer).
     */
    public static function percent( int $user_id ): int {
        if ( $user_id <= 0 ) {
            return 0;
        }
        $earned = 0;
        foreach ( self::FIELDS as $key => $weight ) {
            if ( ! empty( get_user_meta( $user_id, $key, true ) ) ) {
                $earned += $weight;
            }
        }
        return max( 0, min( 100, $earned ) );
    }

    /**
     * Whether the profile is complete enough to skip the onboarding screen.
     */
    public static function is_complete( int $user_id ): bool {
        return self::percent( $user_id ) >= 100;
    }
}
