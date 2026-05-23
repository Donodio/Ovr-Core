<?php
/**
 * Onboarding / Welcome Template.
 *
 * @var WP_User $user
 * @var int     $profile_complete
 * @var string  $pricing_url
 * @var string  $dashboard_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$first_name = esc_html( $user->first_name ?: $user->display_name );
?>
<div class="ovr-wrap">
    <div class="ovr-container ovr-section">

        <!-- Welcome Toast -->
        <div class="ovr-onboarding-toast">
            <span class="material-symbols-outlined">waving_hand</span>
            <span><?php printf( esc_html__( 'Welcome aboard, %s! Let\'s get you set up.', 'ovr-core' ), '<strong>' . $first_name . '</strong>' ); ?></span>
        </div>

        <!-- Hero Card -->
        <div class="ovr-onboarding-hero">
            <div>
                <p class="ovr-label-caps" style="color:var(--ovr-primary);margin-bottom:8px"><?php esc_html_e( 'GETTING STARTED', 'ovr-core' ); ?></p>
                <h1 class="ovr-h2" style="margin-bottom:16px"><?php esc_html_e( 'Your journey starts here', 'ovr-core' ); ?></h1>
                <p class="ovr-body-lg" style="color:var(--ovr-on-surface-variant);margin-bottom:24px">
                    <?php esc_html_e( 'Complete the steps below to make the most of Our Villages Rentals. List your first property and reach thousands of travelers.', 'ovr-core' ); ?>
                </p>
                <a href="<?php echo esc_url( $dashboard_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-lg">
                    <span class="material-symbols-outlined">dashboard</span>
                    <?php esc_html_e( 'Go to Dashboard', 'ovr-core' ); ?>
                </a>
            </div>
            <div class="ovr-onboarding-hero-image">
                <img src="<?php echo esc_url( OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg' ); ?>"
                     alt="<?php esc_attr_e( 'Beautiful rental property', 'ovr-core' ); ?>">
            </div>
        </div>

        <!-- Quick Start Bento Grid -->
        <h2 class="ovr-h3" style="margin-bottom:24px"><?php esc_html_e( 'Quick Start Guide', 'ovr-core' ); ?></h2>

        <div class="ovr-quick-start-grid">

            <!-- Profile Card -->
            <div class="ovr-qs-card ovr-qs-card-profile">
                <div>
                    <div class="ovr-qs-icon" style="background:var(--ovr-surface-container)">
                        <span class="material-symbols-outlined" style="color:var(--ovr-primary)">person</span>
                    </div>
                    <h3 class="ovr-h3" style="font-size:18px;margin-bottom:8px"><?php esc_html_e( 'Complete Profile', 'ovr-core' ); ?></h3>
                    <p style="font-size:14px;color:var(--ovr-on-surface-variant);margin-bottom:16px">
                        <?php esc_html_e( 'Add your details to build trust with potential guests.', 'ovr-core' ); ?>
                    </p>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                        <span style="font-size:12px;color:var(--ovr-on-surface-variant)"><?php esc_html_e( 'Progress', 'ovr-core' ); ?></span>
                        <span style="font-size:12px;font-weight:600;color:var(--ovr-primary)"><?php echo esc_html( $profile_complete ); ?>%</span>
                    </div>
                    <div class="ovr-progress-bar">
                        <div class="ovr-progress-fill" style="width:<?php echo esc_attr( $profile_complete ); ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Add First Listing Card (Primary) -->
            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ovr_property' ) ); ?>" class="ovr-qs-card ovr-qs-card-listing" style="text-decoration:none">
                <div>
                    <div class="ovr-qs-icon" style="background:rgba(255,255,255,0.2)">
                        <span class="material-symbols-outlined" style="color:#fff">add_home</span>
                    </div>
                    <h3 style="font-size:20px;font-weight:700;margin-bottom:8px;color:#fff"><?php esc_html_e( 'Add Your First Listing', 'ovr-core' ); ?></h3>
                    <p style="font-size:14px;color:rgba(255,255,255,0.8)">
                        <?php esc_html_e( 'Create your first property listing with photos, pricing, and availability to start attracting guests.', 'ovr-core' ); ?>
                    </p>
                </div>
                <span class="material-symbols-outlined" style="color:rgba(255,255,255,0.6);align-self:flex-end">arrow_forward</span>
            </a>

            <!-- Choose Subscription -->
            <a href="<?php echo esc_url( $pricing_url ); ?>" class="ovr-qs-card ovr-qs-card-subscription" style="text-decoration:none;color:inherit">
                <div>
                    <div class="ovr-qs-icon" style="background:var(--ovr-tertiary-fixed)">
                        <span class="material-symbols-outlined" style="color:var(--ovr-tertiary)">workspace_premium</span>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;margin-bottom:8px"><?php esc_html_e( 'Choose a Plan', 'ovr-core' ); ?></h3>
                    <p style="font-size:14px;color:var(--ovr-on-surface-variant)">
                        <?php esc_html_e( 'Upgrade for more listings, priority placement, and premium features.', 'ovr-core' ); ?>
                    </p>
                </div>
                <span class="material-symbols-outlined" style="color:var(--ovr-outline);align-self:flex-end">arrow_forward</span>
            </a>

            <!-- Explore Properties -->
            <a href="<?php echo esc_url( home_url( '/properties/' ) ); ?>" class="ovr-qs-card ovr-qs-card-explore" style="text-decoration:none;color:inherit">
                <div>
                    <div class="ovr-qs-icon" style="background:var(--ovr-secondary-container)">
                        <span class="material-symbols-outlined" style="color:var(--ovr-secondary)">explore</span>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;margin-bottom:8px"><?php esc_html_e( 'Explore Properties', 'ovr-core' ); ?></h3>
                    <p style="font-size:14px;color:var(--ovr-on-surface-variant)">
                        <?php esc_html_e( 'Browse listings in your area for inspiration and competitive research.', 'ovr-core' ); ?>
                    </p>
                </div>
                <span class="material-symbols-outlined" style="color:var(--ovr-outline);align-self:flex-end">arrow_forward</span>
            </a>

        </div>

    </div>
</div>
