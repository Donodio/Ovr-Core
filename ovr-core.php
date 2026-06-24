<?php
/**
 * Plugin Name:       OVR Core — Our Village Rentals
 * Plugin URI:        https://ourvillagesrentals.com
 * Description:       Premium vacation & long-term rental listing platform. Complete SaaS-ready solution with property management, subscription plans, advanced search, and landlord dashboards.
 * Version:           1.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Our Village Rentals
 * Author URI:        https://ourvillagesrentals.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ovr-core
 * Domain Path:       /languages
 *
 * @package OVR
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Plugin Constants
|--------------------------------------------------------------------------
*/
define( 'OVR_VERSION', '1.1.1' );
define( 'OVR_PLUGIN_FILE', __FILE__ );
define( 'OVR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OVR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OVR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'OVR_PLUGIN_SLUG', 'ovr-core' );
define( 'OVR_TEXT_DOMAIN', 'ovr-core' );
define( 'OVR_DB_VERSION', '2.7.0' );

/*
|--------------------------------------------------------------------------
| Composer Autoloader
|--------------------------------------------------------------------------
| Load PSR-4 autoloader. If Composer autoload is not available, fall back
| to a simple manual autoloader for the OVR namespace.
*/
if ( file_exists( OVR_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once OVR_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    spl_autoload_register( function ( $class ) {
        // Only handle OVR namespace.
        $prefix = 'OVR\\';
        $len    = strlen( $prefix );

        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }

        $relative_class = substr( $class, $len );
        $file           = OVR_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    });
}

/*
|--------------------------------------------------------------------------
| Activation & Deactivation Hooks
|--------------------------------------------------------------------------
*/
register_activation_hook( __FILE__, [ 'OVR\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'OVR\\Deactivator', 'deactivate' ] );

/*
|--------------------------------------------------------------------------
| Initialize Plugin
|--------------------------------------------------------------------------
| Boot the plugin after all plugins are loaded so we can safely check
| for dependencies (Elementor, ACF, etc.).
*/
add_action( 'plugins_loaded', function () {
    // Check PHP version requirement.
    if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
        add_action( 'admin_notices', function () {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__(
                    'OVR Core requires PHP 8.2 or higher. Please upgrade your PHP version.',
                    'ovr-core'
                )
            );
        });
        return;
    }

    // Boot the plugin.
    OVR\Plugin::instance()->init();
});
