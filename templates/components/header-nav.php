<?php
/**
 * Header Navigation Component.
 *
 * Fixed top nav bar shown on all OVR pages. Adapts to logged-in state.
 *
 * @package OVR
 *
 * @var string $active  Optional. Active link slug: explore|villages|pricing|help.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\Pages;
use OVR\Frontend\Header;

$active = $active ?? '';
// Role-aware nav comes from Header::nav_items(); fall back if rendered directly.
$nav_items     = $nav_items     ?? Header::nav_items();
$is_admin_user = $is_admin_user ?? current_user_can( 'manage_options' );
$admin_home_url = $admin_home_url ?? admin_url();

$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
?>
<header class="ovr-topnav" role="banner">
    <div class="ovr-topnav-inner">

        <!-- Brand -->
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ovr-brand">
            <?php echo esc_html( get_bloginfo( 'name' ) ?: __( 'Our Village Rentals', 'ovr-core' ) ); ?>
        </a>

        <!-- Primary Navigation -->
        <nav class="ovr-nav-links" aria-label="<?php esc_attr_e( 'Primary navigation', 'ovr-core' ); ?>">
            <?php foreach ( $nav_items as $slug => $item ) : ?>
                <a href="<?php echo esc_url( $item['url'] ); ?>"
                   class="<?php echo $active === $slug ? 'active' : ''; ?>">
                    <?php echo esc_html( $item['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Actions -->
        <div class="ovr-nav-actions">
            <button type="button" class="ovr-nav-icon-btn" aria-label="<?php esc_attr_e( 'Search', 'ovr-core' ); ?>" data-ovr-action="search-toggle">
                <span class="material-symbols-outlined">search</span>
            </button>

            <?php if ( $is_logged_in ) : ?>
                <?php if ( $is_admin_user ) : ?>
                    <a href="<?php echo esc_url( $admin_home_url ); ?>" class="ovr-nav-link-cta" title="<?php esc_attr_e( 'WordPress admin', 'ovr-core' ); ?>">
                        <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px">admin_panel_settings</span>
                        <?php esc_html_e( 'Site Admin', 'ovr-core' ); ?>
                    </a>
                <?php endif; ?>
                <button type="button" class="ovr-nav-icon-btn" aria-label="<?php esc_attr_e( 'Favorites', 'ovr-core' ); ?>">
                    <span class="material-symbols-outlined">favorite</span>
                </button>

                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_dashboard' ) ); ?>"
                   class="ovr-nav-user" title="<?php echo esc_attr( $current_user->display_name ); ?>">
                    <?php echo get_avatar( $current_user->ID, 36, '', '', [ 'class' => 'ovr-nav-avatar' ] ); ?>
                </a>

                <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"
                   class="ovr-btn ovr-btn-outline ovr-btn-pill" style="padding:8px 18px;font-size:14px">
                    <?php esc_html_e( 'Sign Out', 'ovr-core' ); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_login' ) ); ?>"
                   class="ovr-nav-link-cta">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px">login</span>
                    <?php esc_html_e( 'Owner Login', 'ovr-core' ); ?>
                </a>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_register' ) ); ?>"
                   class="ovr-btn ovr-btn-primary ovr-btn-pill" style="padding:10px 20px;font-size:14px">
                    <?php esc_html_e( 'List Your Property', 'ovr-core' ); ?>
                </a>
            <?php endif; ?>

            <!-- Mobile menu toggle -->
            <button type="button" class="ovr-nav-icon-btn ovr-mobile-toggle"
                    aria-label="<?php esc_attr_e( 'Menu', 'ovr-core' ); ?>"
                    data-ovr-action="mobile-menu">
                <span class="material-symbols-outlined">menu</span>
            </button>
        </div>
    </div>

    <!-- Mobile Menu Drawer -->
    <div class="ovr-mobile-drawer" aria-hidden="true" data-ovr-mobile-drawer>
        <nav class="ovr-mobile-drawer-inner" aria-label="<?php esc_attr_e( 'Mobile navigation', 'ovr-core' ); ?>">
            <?php foreach ( $nav_items as $slug => $item ) : ?>
                <a href="<?php echo esc_url( $item['url'] ); ?>"
                   class="ovr-mobile-link <?php echo $active === $slug ? 'active' : ''; ?>">
                    <?php echo esc_html( $item['label'] ); ?>
                </a>
            <?php endforeach; ?>
            <div class="ovr-mobile-divider"></div>
            <?php if ( $is_logged_in ) : ?>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_dashboard' ) ); ?>" class="ovr-mobile-link">
                    <?php esc_html_e( 'Dashboard', 'ovr-core' ); ?>
                </a>
                <?php if ( $is_admin_user ) : ?>
                    <a href="<?php echo esc_url( $admin_home_url ); ?>" class="ovr-mobile-link">
                        <?php esc_html_e( 'Site Admin', 'ovr-core' ); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="ovr-mobile-link">
                    <?php esc_html_e( 'Sign Out', 'ovr-core' ); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_login' ) ); ?>" class="ovr-mobile-link">
                    <?php esc_html_e( 'Owner Login', 'ovr-core' ); ?>
                </a>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_register' ) ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-full" style="margin-top:12px">
                    <?php esc_html_e( 'List Your Property', 'ovr-core' ); ?>
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>
