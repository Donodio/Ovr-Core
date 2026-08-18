<?php
/**
 * Theme Color Schemes.
 *
 * The client remembers the previous platform offering a handful of predefined
 * color-scheme choices in the theme configuration. This module restores that
 * concept cleanly: six predefined palettes are exposed as a single selector in
 * OVR Settings → General. The chosen palette is applied site-wide by printing a
 * small CSS custom-property override on top of the existing design tokens
 * (--ovr-primary, --ovr-secondary, --ovr-tertiary, --ovr-gold, …), so no page
 * markup needs to change.
 *
 * No legacy palettes existed in the codebase, database or git history (verified
 * during the master implementation audit), so the palettes below are built from
 * the current design system rather than recovered from an earlier version.
 *
 * @package OVR\Core
 * @since   1.1.2
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThemeSchemes {

    public const OPTION_KEY = 'color_scheme';

    /**
     * Predefined palettes. Each maps CSS custom-property name → hex value.
     * Palette keys are stable slugs stored in ovr_settings['color_scheme'];
     * renaming a key later would orphan saved selections.
     *
     * @return array<string, array<string,string>>
     */
    public static function palettes(): array {
        return [
            'navy'    => [
                'label'  => __( 'Classic Navy', 'ovr-core' ),
                'colors' => [
                    '--ovr-primary'              => '#010b62',
                    '--ovr-primary-dark'         => '#010848',
                    '--ovr-primary-light'        => '#2a3490',
                    '--ovr-primary-container'    => '#010b62',
                    '--ovr-on-primary-container' => '#bdc2ff',
                    '--ovr-secondary'            => '#006492',
                    '--ovr-secondary-container'  => '#c9e6ff',
                    '--ovr-on-secondary-container' => '#004667',
                    '--ovr-tertiary'             => '#735c00',
                    '--ovr-gold'                 => '#DEAF0C',
                    '--ovr-featured-gold'        => '#DEAF0C',
                    '--ovr-tertiary-container'   => '#DEAF0C',
                ],
            ],
            'emerald' => [
                'label'  => __( 'Emerald', 'ovr-core' ),
                'colors' => [
                    '--ovr-primary'              => '#004d40',
                    '--ovr-primary-dark'         => '#00332b',
                    '--ovr-primary-light'        => '#39796b',
                    '--ovr-primary-container'    => '#004d40',
                    '--ovr-on-primary-container' => '#b2dfdb',
                    '--ovr-secondary'            => '#00695c',
                    '--ovr-secondary-container'  => '#b2dfdb',
                    '--ovr-on-secondary-container' => '#00352d',
                    '--ovr-tertiary'             => '#735c00',
                    '--ovr-gold'                 => '#c6a700',
                    '--ovr-featured-gold'        => '#c6a700',
                    '--ovr-tertiary-container'   => '#c6a700',
                ],
            ],
            'royal'   => [
                'label'  => __( 'Royal Purple', 'ovr-core' ),
                'colors' => [
                    '--ovr-primary'              => '#4a148c',
                    '--ovr-primary-dark'         => '#2a0a5e',
                    '--ovr-primary-light'        => '#7c4dff',
                    '--ovr-primary-container'    => '#4a148c',
                    '--ovr-on-primary-container' => '#e1bee7',
                    '--ovr-secondary'            => '#6a1b9a',
                    '--ovr-secondary-container'  => '#e1bee7',
                    '--ovr-on-secondary-container' => '#38006b',
                    '--ovr-tertiary'             => '#4e342e',
                    '--ovr-gold'                 => '#DEAF0C',
                    '--ovr-featured-gold'        => '#DEAF0C',
                    '--ovr-tertiary-container'   => '#DEAF0C',
                ],
            ],
            'forest'  => [
                'label'  => __( 'Forest & Gold', 'ovr-core' ),
                'colors' => [
                    '--ovr-primary'              => '#1b5e20',
                    '--ovr-primary-dark'         => '#0d3310',
                    '--ovr-primary-light'        => '#4c8c4f',
                    '--ovr-primary-container'    => '#1b5e20',
                    '--ovr-on-primary-container' => '#c8e6c9',
                    '--ovr-secondary'            => '#33691e',
                    '--ovr-secondary-container'  => '#c8e6c9',
                    '--ovr-on-secondary-container' => '#1a3a10',
                    '--ovr-tertiary'             => '#e65100',
                    '--ovr-gold'                 => '#DEAF0C',
                    '--ovr-featured-gold'        => '#DEAF0C',
                    '--ovr-tertiary-container'   => '#DEAF0C',
                ],
            ],
            'ocean'   => [
                'label'  => __( 'Ocean Blue', 'ovr-core' ),
                'colors' => [
                    '--ovr-primary'              => '#0d47a1',
                    '--ovr-primary-dark'         => '#002171',
                    '--ovr-primary-light'        => '#5472d3',
                    '--ovr-primary-container'    => '#0d47a1',
                    '--ovr-on-primary-container' => '#bbdefb',
                    '--ovr-secondary'            => '#00a2e8',
                    '--ovr-secondary-container'  => '#bbdefb',
                    '--ovr-on-secondary-container' => '#01579b',
                    '--ovr-tertiary'             => '#00695c',
                    '--ovr-gold'                 => '#f9a825',
                    '--ovr-featured-gold'        => '#f9a825',
                    '--ovr-tertiary-container'   => '#f9a825',
                ],
            ],
            'terracotta' => [
                'label'  => __( 'Terracotta', 'ovr-core' ),
                'colors' => [
                    '--ovr-primary'              => '#bf360c',
                    '--ovr-primary-dark'         => '#8f2000',
                    '--ovr-primary-light'        => '#ff7043',
                    '--ovr-primary-container'    => '#bf360c',
                    '--ovr-on-primary-container' => '#ffccbc',
                    '--ovr-secondary'            => '#8d6e63',
                    '--ovr-secondary-container'  => '#ffccbc',
                    '--ovr-on-secondary-container' => '#4e342e',
                    '--ovr-tertiary'             => '#5d4037',
                    '--ovr-gold'                 => '#DEAF0C',
                    '--ovr-featured-gold'        => '#DEAF0C',
                    '--ovr-tertiary-container'   => '#DEAF0C',
                ],
            ],
        ];
    }

    /**
     * The active palette key (falls back to 'navy').
     */
    public static function active_key(): string {
        $settings = get_option( 'ovr_settings', [] );
        $key      = (string) ( $settings[ self::OPTION_KEY ] ?? 'navy' );
        $keys     = array_keys( self::palettes() );
        return in_array( $key, $keys, true ) ? $key : 'navy';
    }

    /**
     * CSS custom-property override for the active palette.
     */
    public static function css_vars(): string {
        $palette = self::palettes()[ self::active_key() ]['colors'] ?? [];
        if ( empty( $palette ) ) {
            return '';
        }
        $lines = [];
        foreach ( $palette as $var => $value ) {
            $lines[] = $var . ': ' . $value;
        }
        return ':root{' . implode( ';', $lines ) . '}';
    }

    /**
     * Hook up front-end output + settings integration.
     */
    public function init(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'print_scheme_css' ], 1000 );
        add_action( 'admin_enqueue_scripts', [ $this, 'print_scheme_css' ], 1000 );
    }

    /**
     * Print the active palette override after the core stylesheet so the CSS
     * custom properties always win. Runs late (priority 1000) to outlast any
     * plugin/theme stylesheet that also defines the tokens.
     */
    public function print_scheme_css(): void {
        $css = self::css_vars();
        if ( '' === $css ) {
            return;
        }
        wp_register_style( 'ovr-theme-scheme', false, [], OVR_VERSION );
        wp_enqueue_style( 'ovr-theme-scheme' );
        wp_add_inline_style( 'ovr-theme-scheme', $css );
    }
}
