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
$logo_html    = $logo_html ?? '';
$site_name    = $site_name ?? ( get_bloginfo( 'name' ) ?: __( 'Our Villages Rental', 'ovr-core' ) );
?>
<header class="ovr-topnav" role="banner">
    <div class="ovr-topnav-inner">

        <!-- Brand -->
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ovr-brand">
            <?php
            if ( $logo_html ) {
                echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wp_get_attachment_image().
            }
            ?>
            <span class="ovr-brand-name"><?php echo esc_html( $site_name ); ?></span>
        </a>

        <!-- Primary Navigation -->
        <nav class="ovr-nav-links" aria-label="<?php esc_attr_e( 'Primary navigation', 'ovr-core' ); ?>">
            <?php foreach ( $nav_items as $slug => $item ) : ?>
                <?php if ( ! empty( $item['children'] ) ) : ?>
                    <div class="ovr-nav-item ovr-has-menu">
                        <button type="button" class="ovr-nav-link ovr-nav-toggle" aria-haspopup="true" aria-expanded="false" data-ovr-nav-toggle>
                            <?php echo esc_html( $item['label'] ); ?>
                            <span class="material-symbols-outlined ovr-nav-caret" aria-hidden="true">expand_more</span>
                        </button>
                        <div class="ovr-nav-dropdown" role="menu">
                            <?php foreach ( $item['children'] as $child ) : ?>
                                <?php if ( ! empty( $child['divider'] ) ) : ?>
                                    <div class="ovr-nav-dropdown-divider" role="separator"></div>
                                <?php else : ?>
                                    <a class="ovr-nav-dropdown-link" role="menuitem" href="<?php echo esc_url( $child['url'] ); ?>"><?php echo esc_html( $child['label'] ); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <a href="<?php echo esc_url( $item['url'] ); ?>"
                       class="ovr-nav-link <?php echo $active === $slug ? 'active' : ''; ?>"<?php echo ! empty( $item['target'] ) ? ' target="_blank" rel="noopener"' : ''; ?>>
                        <?php echo esc_html( $item['label'] ); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Actions -->
        <div class="ovr-nav-actions">
            <button type="button" class="ovr-nav-icon-btn" aria-label="<?php esc_attr_e( 'Search', 'ovr-core' ); ?>" data-ovr-action="search-toggle">
                <span class="material-symbols-outlined">search</span>
            </button>

            <?php if ( $is_logged_in ) : ?>
                <button type="button" class="ovr-nav-icon-btn" aria-label="<?php esc_attr_e( 'Favorites', 'ovr-core' ); ?>">
                    <span class="material-symbols-outlined">favorite</span>
                </button>

                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_dashboard' ) ); ?>"
                   class="ovr-btn ovr-btn-primary ovr-btn-pill" style="padding:10px 20px;font-size:14px">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px">dashboard</span>
                    <?php esc_html_e( 'Dashboard', 'ovr-core' ); ?>
                </a>

                <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"
                   class="ovr-btn ovr-btn-outline ovr-btn-pill" style="padding:10px 20px;font-size:14px">
                    <?php esc_html_e( 'Sign Out', 'ovr-core' ); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_login' ) ); ?>"
                   class="ovr-nav-link-cta">
                    <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:4px">login</span>
                    <?php esc_html_e( 'Log In', 'ovr-core' ); ?>
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
                    <?php if ( ! empty( $item['children'] ) ) : ?>
                        <div class="ovr-mobile-group">
                            <div class="ovr-mobile-group-title"><?php echo esc_html( $item['label'] ); ?></div>
                            <?php foreach ( $item['children'] as $child ) : ?>
                                <?php if ( ! empty( $child['divider'] ) ) : ?>
                                    <div class="ovr-mobile-divider"></div>
                                <?php else : ?>
                                    <a href="<?php echo esc_url( $child['url'] ); ?>" class="ovr-mobile-link"><?php echo esc_html( $child['label'] ); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $item['url'] ); ?>"
                           class="ovr-mobile-link <?php echo $active === $slug ? 'active' : ''; ?>">
                            <?php echo esc_html( $item['label'] ); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <div class="ovr-mobile-divider"></div>
            <?php if ( $is_logged_in ) : ?>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_dashboard' ) ); ?>" class="ovr-mobile-link">
                    <?php esc_html_e( 'Dashboard', 'ovr-core' ); ?>
                </a>
                <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="ovr-mobile-link">
                    <?php esc_html_e( 'Sign Out', 'ovr-core' ); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_login' ) ); ?>" class="ovr-mobile-link">
                    <?php esc_html_e( 'Log In', 'ovr-core' ); ?>
                </a>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_register' ) ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-full" style="margin-top:12px">
                    <?php esc_html_e( 'List Your Property', 'ovr-core' ); ?>
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <script>
    (function () {
        var toggles = document.querySelectorAll('[data-ovr-nav-toggle]');
        toggles.forEach(function (btn) {
            var menu = btn.parentElement ? btn.parentElement.querySelector('.ovr-nav-dropdown') : null;
            if (!menu) { return; }
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', open ? 'false' : 'true');
                btn.parentElement.classList.toggle('is-open', !open);
            });
            menu.addEventListener('click', function (e) { e.stopPropagation(); });
        });
        document.addEventListener('click', function () {
            toggles.forEach(function (btn) {
                btn.setAttribute('aria-expanded', 'false');
                if (btn.parentElement) { btn.parentElement.classList.remove('is-open'); }
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                toggles.forEach(function (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                    if (btn.parentElement) { btn.parentElement.classList.remove('is-open'); }
                });
            }
        });
    })();
    </script>
</header>
