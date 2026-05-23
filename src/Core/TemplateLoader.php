<?php
/**
 * Template Loader.
 *
 * Provides a theme-overridable template system. Templates are loaded from:
 * 1. Theme: yourtheme/ovr-core/{template}.php
 * 2. Plugin: ovr-core/templates/{template}.php
 *
 * @package OVR\Core
 * @since   1.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TemplateLoader {

    public function init(): void {
        add_filter( 'template_include', [ $this, 'load_property_template' ] );
    }

    /**
     * Override single property template.
     */
    public function load_property_template( string $template ): string {
        if ( is_singular( 'ovr_property' ) ) {
            $custom = self::locate( 'property/single.php' );
            if ( $custom ) {
                return $custom;
            }
        }

        if ( is_post_type_archive( 'ovr_property' ) ) {
            $custom = self::locate( 'search/results.php' );
            if ( $custom ) {
                return $custom;
            }
        }

        if ( is_tax( 'ovr_village' ) ) {
            $custom = self::locate( 'pages/village-landing.php' );
            if ( $custom ) {
                return $custom;
            }
        }

        return $template;
    }

    /**
     * Locate a template file. Theme overrides take precedence.
     *
     * @param string $template_name Relative template path.
     * @return string|false Full path to template, or false.
     */
    public static function locate( string $template_name ): string|false {
        // 1. Check theme override directory.
        $theme_path = get_stylesheet_directory() . '/ovr-core/' . $template_name;
        if ( file_exists( $theme_path ) ) {
            return $theme_path;
        }

        // 2. Check parent theme.
        $parent_path = get_template_directory() . '/ovr-core/' . $template_name;
        if ( $parent_path !== $theme_path && file_exists( $parent_path ) ) {
            return $parent_path;
        }

        // 3. Plugin default.
        $plugin_path = OVR_PLUGIN_DIR . 'templates/' . $template_name;
        if ( file_exists( $plugin_path ) ) {
            return $plugin_path;
        }

        return false;
    }

    /**
     * Load and render a template part with data.
     *
     * @param string $template_name Relative template path.
     * @param array  $args          Variables to extract into template scope.
     */
    public static function render( string $template_name, array $args = [] ): void {
        $template = self::locate( $template_name );

        if ( ! $template ) {
            return;
        }

        // Extract args into template scope.
        if ( ! empty( $args ) ) {
            // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            extract( $args, EXTR_SKIP );
        }

        include $template;
    }

    /**
     * Render a template and return as string.
     *
     * @param string $template_name Relative template path.
     * @param array  $args          Variables to extract into template scope.
     * @return string Rendered HTML.
     */
    public static function get_rendered( string $template_name, array $args = [] ): string {
        ob_start();
        self::render( $template_name, $args );
        return ob_get_clean();
    }
}
