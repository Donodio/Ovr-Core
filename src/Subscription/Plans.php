<?php
/**
 * Subscription Plans.
 *
 * Defines and manages subscription plan data. Plans are stored
 * as a WordPress option for admin configurability.
 *
 * @package OVR\Subscription
 * @since   1.0.0
 */

namespace OVR\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plans {

    /** @var string Option key for plans data. */
    private const OPTION_KEY = 'ovr_subscription_plans';

    public function init(): void {
        // Ensure default plans exist.
        if ( false === get_option( self::OPTION_KEY ) ) {
            update_option( self::OPTION_KEY, self::get_default_plans() );
        }
    }

    /**
     * Get all active subscription plans.
     *
     * @return array
     */
    public static function get_plans(): array {
        $plans = get_option( self::OPTION_KEY, [] );
        return ! empty( $plans ) ? $plans : self::get_default_plans();
    }

    /**
     * Get a single plan by slug.
     */
    public static function get_plan( string $slug ): ?array {
        $plans = self::get_plans();
        return $plans[ $slug ] ?? null;
    }

    /**
     * Default subscription plans matching the design system.
     */
    public static function get_default_plans(): array {
        return [
            'base_subscriber' => [
                'name'          => __( 'Base Subscriber (Free)', 'ovr-core' ),
                'slug'          => 'base_subscriber',
                'price'         => 0.00,
                'period'        => 'monthly',
                'max_listings'  => 0,
                'is_popular'    => false,
                'is_free'       => true,
                'description'   => __( 'Free account with no listing access. Upgrade to unlock landlord features.', 'ovr-core' ),
                'features'      => [
                    __( 'Account Creation', 'ovr-core' ),
                    __( 'View Public Listings', 'ovr-core' ),
                    __( 'Contact Property Owners', 'ovr-core' ),
                ],
                'sort_order'    => 1,
                'is_active'     => true,
            ],
            'standard_homeowner_5' => [
                'name'          => __( 'Standard Homeowner 5', 'ovr-core' ),
                'slug'          => 'standard_homeowner_5',
                'price'         => 29.00,
                'period'        => 'annually',
                'max_listings'  => 5,
                'is_popular'    => true,
                'description'   => __( 'Ideal for individual owners. Billed annually. Max 5 listings.', 'ovr-core' ),
                'features'      => [
                    __( 'Up to 5 Listings', 'ovr-core' ),
                    __( 'Priority Support', 'ovr-core' ),
                    __( 'Enhanced Analytics', 'ovr-core' ),
                    __( 'Featured Placement Options', 'ovr-core' ),
                ],
                'sort_order'    => 2,
                'is_active'     => true,
            ],
            'property_manager_25' => [
                'name'          => __( 'Property Manager 25', 'ovr-core' ),
                'slug'          => 'property_manager_25',
                'price'         => 99.00,
                'period'        => 'annually',
                'max_listings'  => 25,
                'is_popular'    => false,
                'description'   => __( 'For growing management teams. Billed annually. Max 25 listings.', 'ovr-core' ),
                'features'      => [
                    __( 'Up to 25 Listings', 'ovr-core' ),
                    __( 'Dedicated Account Manager', 'ovr-core' ),
                    __( 'API Access', 'ovr-core' ),
                ],
                'sort_order'    => 3,
                'is_active'     => true,
            ],
            'property_manager_40' => [
                'name'          => __( 'Property Manager 40', 'ovr-core' ),
                'slug'          => 'property_manager_40',
                'price'         => 149.00,
                'period'        => 'annually',
                'max_listings'  => 40,
                'is_popular'    => false,
                'description'   => __( 'High volume portfolio management. Billed annually. Max 40 listings.', 'ovr-core' ),
                'features'      => [
                    __( 'Up to 40 Listings', 'ovr-core' ),
                    __( 'White-label Reports', 'ovr-core' ),
                    __( 'Bulk Import/Export', 'ovr-core' ),
                ],
                'sort_order'    => 4,
                'is_active'     => true,
            ],
            'long_term_only' => [
                'name'          => __( 'Long-Term Only', 'ovr-core' ),
                'slug'          => 'long_term_only',
                'price'         => 49.00,
                'period'        => 'annually',
                'max_listings'  => -1, // Unlimited.
                'is_popular'    => false,
                'description'   => __( 'Specialized for annual leases. Billed annually. Unlimited listings.', 'ovr-core' ),
                'features'      => [
                    __( 'Unlimited Listings (Long-Term)', 'ovr-core' ),
                    __( 'Tenant Screening Integration', 'ovr-core' ),
                    __( 'Lease Document Generator', 'ovr-core' ),
                ],
                'sort_order'    => 5,
                'is_active'     => true,
            ],
        ];
    }
}
