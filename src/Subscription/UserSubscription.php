<?php
/**
 * User Subscription Management.
 *
 * @package OVR\Subscription
 * @since   1.0.0
 */

namespace OVR\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserSubscription {

    public const STATUS_NONE       = 'none';
    public const STATUS_PENDING    = 'pending';
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_EXPIRED    = 'expired';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_SUSPENDED  = 'suspended';

    public const META_STATUS       = 'ovr_subscription_status';
    public const META_PLAN         = 'ovr_subscription_plan';
    public const META_EXPIRES      = 'ovr_subscription_expires';
    public const META_START        = 'ovr_subscription_start';
    public const META_EDITING      = 'ovr_editing_enabled';

    public function init(): void {
        add_action( 'init', [ $this, 'maybe_migrate_statuses' ] );
    }

    /**
     * One-time migration: set ovr_subscription_status for all existing users
     * so the new status-based access control works immediately on deploy.
     */
    public function maybe_migrate_statuses(): void {
        if ( get_option( 'ovr_subscription_status_migrated' ) ) {
            return;
        }

        $by_role = get_users( [ 'role' => 'ovr_landlord', 'fields' => 'ID' ] );
        $by_meta = get_users( [ 'meta_key' => 'ovr_is_landlord', 'meta_value' => '1', 'fields' => 'ID' ] );
        $ids     = array_unique( array_map( 'intval', array_merge( (array) $by_role, (array) $by_meta ) ) );

        $default_paid = 'standard_homeowner_5';
        $through = gmdate( 'Y-m-d', strtotime( '+1 year' ) );

        foreach ( $ids as $uid ) {
            $existing_status = get_user_meta( $uid, self::META_STATUS, true );
            if ( $existing_status ) {
                continue;
            }

            $plan_slug = (string) get_user_meta( $uid, 'ovr_subscription_plan', true );

            if ( ! self::is_paid_plan( $plan_slug ) ) {
                update_user_meta( $uid, self::META_STATUS, self::STATUS_NONE );
                continue;
            }

            $expiry = get_user_meta( $uid, 'ovr_subscription_expires', true );

            if ( ! $expiry || strtotime( $expiry ) < strtotime( $through ) ) {
                update_user_meta( $uid, 'ovr_subscription_expires', $through );
                $expiry = $through;
            }

            if ( strtotime( $expiry ) > time() ) {
                update_user_meta( $uid, self::META_STATUS, self::STATUS_ACTIVE );
            } else {
                update_user_meta( $uid, self::META_STATUS, self::STATUS_EXPIRED );
            }

            update_user_meta( $uid, self::META_EDITING, true );
        }

        update_option( 'ovr_subscription_status_migrated', 1 );
    }

    /**
     * Get a user's current subscription plan slug.
     */
    public static function get_plan_slug( int $user_id = 0 ): string {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        return get_user_meta( $user_id, self::META_PLAN, true ) ?: '';
    }

    /**
     * Get full plan data for a user.
     */
    public static function get_plan( int $user_id = 0 ): ?array {
        $slug = self::get_plan_slug( $user_id );
        return $slug ? Plans::get_plan( $slug ) : null;
    }

    /**
     * Whether a plan slug represents a paid subscription (price > 0).
     */
    public static function is_paid_plan( string $slug ): bool {
        $plan = Plans::get_plan( $slug );
        return $plan && (float) ( $plan['price'] ?? 0 ) > 0;
    }

    /**
     * Get the explicit subscription status for a user.
     */
    public static function get_status( int $user_id = 0 ): string {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return self::STATUS_NONE;
        }
        $status = get_user_meta( $user_id, self::META_STATUS, true );
        if ( ! $status ) {
            $plan_slug = self::get_plan_slug( $user_id );
            if ( self::is_paid_plan( $plan_slug ) ) {
                return self::STATUS_ACTIVE;
            }
            return self::STATUS_NONE;
        }
        return $status;
    }

    /**
     * Human-readable label for a status value.
     */
    public static function status_label( string $status ): string {
        $labels = [
            self::STATUS_NONE      => __( 'No Subscription', 'ovr-core' ),
            self::STATUS_PENDING   => __( 'Pending Payment', 'ovr-core' ),
            self::STATUS_ACTIVE    => __( 'Active', 'ovr-core' ),
            self::STATUS_EXPIRED   => __( 'Expired', 'ovr-core' ),
            self::STATUS_CANCELLED => __( 'Cancelled', 'ovr-core' ),
            self::STATUS_SUSPENDED => __( 'Suspended', 'ovr-core' ),
        ];
        return $labels[ $status ] ?? __( 'Unknown', 'ovr-core' );
    }

    /**
     * Days remaining until the subscription expires (null = no expiry).
     */
    public static function get_days_remaining( int $user_id = 0 ): ?int {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        $expiry = get_user_meta( $user_id, self::META_EXPIRES, true );
        if ( ! $expiry ) {
            return null;
        }
        $diff = (int) ceil( ( strtotime( $expiry ) - time() ) / DAY_IN_SECONDS );
        return max( 0, $diff );
    }

    /**
     * Full subscription info array for dashboard widget and management page.
     *
     * @return array{plan_name:string,plan_slug:string,status:string,status_label:string,start_date:string,expiry_date:string,days_remaining:int|null,credit_balance:float,max_listings:int,listings_used:int,currency_symbol:string}
     */
    public static function get_info( int $user_id = 0 ): array {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        $plan_slug = self::get_plan_slug( $user_id );
        $plan      = Plans::get_plan( $plan_slug );
        $status    = self::get_status( $user_id );
        $expiry    = get_user_meta( $user_id, self::META_EXPIRES, true );
        $start     = get_user_meta( $user_id, self::META_START, true );
        $credit    = 0.0;
        if ( class_exists( '\OVR\Payment\Wallet' ) ) {
            $credit = (float) \OVR\Payment\Wallet::get_balance( $user_id );
        }
        $settings  = (array) get_option( 'ovr_settings', [] );

        return [
            'plan_name'       => $plan['name'] ?? __( 'No Plan', 'ovr-core' ),
            'plan_slug'       => $plan_slug,
            'status'          => $status,
            'status_label'    => self::status_label( $status ),
            'start_date'      => $start ?: '',
            'expiry_date'     => $expiry ?: '',
            'days_remaining'  => self::get_days_remaining( $user_id ),
            'credit_balance'  => $credit,
            'max_listings'    => $plan['max_listings'] ?? 0,
            'listings_used'   => self::get_listing_count( $user_id ),
            'currency_symbol' => $settings['currency_symbol'] ?? '$',
        ];
    }

    /**
     * Whether the user may publish listings at all.
     *
     * A landlord with no active, paid subscription cannot list a property.
     */
    public static function has_listing_access( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        return self::is_active( $user_id ) && self::is_paid_plan( self::get_plan_slug( $user_id ) );
    }

    /**
     * Why a user is blocked from adding a listing: 'subscription' (no active
     * paid plan), 'limit' (plan listing cap reached), or '' (allowed).
     */
    public static function listing_block_reason( int $user_id = 0 ): string {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! self::has_listing_access( $user_id ) ) {
            return 'subscription';
        }
        return self::can_create_listing( $user_id ) ? '' : 'limit';
    }

    /**
     * Check if user can create more listings.
     */
    public static function can_create_listing( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! self::has_listing_access( $user_id ) ) {
            return false;
        }

        $plan = self::get_plan( $user_id );
        if ( ! $plan ) {
            return false;
        }

        if ( -1 === $plan['max_listings'] ) {
            return true;
        }

        $current_count = self::get_listing_count( $user_id );
        return $current_count < $plan['max_listings'];
    }

    /**
     * Get count of user's active listings.
     */
    public static function get_listing_count( int $user_id ): int {
        $query = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'posts_per_page' => 999,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        return $query->post_count;
    }

    /**
     * Check if user's subscription is active (status-based).
     *
     * Also validates the expiry date hasn't passed as a safety net.
     */
    public static function is_active( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $account_status = get_user_meta( $user_id, 'ovr_account_status', true );
        if ( 'inactive' === $account_status ) {
            return false;
        }

        $status = self::get_status( $user_id );
        if ( self::STATUS_ACTIVE !== $status ) {
            return false;
        }

        // Double-check expiry hasn't passed.
        $expiry = get_user_meta( $user_id, self::META_EXPIRES, true );
        if ( $expiry && strtotime( $expiry ) <= time() ) {
            return false;
        }

        return true;
    }

    /**
     * Check if user's editing is enabled.
     */
    public static function is_editing_enabled( int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        return (bool) get_user_meta( $user_id, self::META_EDITING, true );
    }
}
