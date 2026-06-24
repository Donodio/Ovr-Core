<?php
/**
 * Elementor Homepage Slider Widget.
 *
 * A homepage hero block split into two columns:
 *   • ~70% — a full-width hero slider of the listings holding an active, paid
 *     "Homepage Slider" boost (see {@see \OVR\Subscription\UpgradeActivator}).
 *     One property per slide: a full-bleed cover image with the property name,
 *     location, beds/baths, price and a CTA aligned bottom-left.
 *   • ~30% — a search panel (location + property type) posting to the search
 *     results page.
 *
 * Every overlay element (title, location, meta, price, button) has its own
 * show/hide + typography + colour controls, and the image exposes native
 * fit / position / repeat controls plus a full-screen height mode.
 *
 * Slide resolution is delegated to PropertyQuery::get_slider() (manual override
 * → active boosts → featured → newest fallback) so the slider is never blank.
 * Behaviour is driven by the shared public CSS/JS (.ovr-hps-slider).
 *
 * @package OVR\Elementor\Widgets
 * @since   2.7.0
 */

namespace OVR\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use OVR\Core\Pages;
use OVR\Property\PropertyQuery;
use OVR\Property\SeasonalPricing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HomepageSliderWidget extends Widget_Base {

    public function get_name(): string {
        return 'ovr_homepage_slider';
    }

    public function get_title(): string {
        return esc_html__( 'OVR Homepage Slider', 'ovr-core' );
    }

    public function get_icon(): string {
        return 'eicon-slides';
    }

    public function get_categories(): array {
        return [ 'ovr-widgets' ];
    }

    public function get_keywords(): array {
        return [ 'slider', 'carousel', 'hero', 'search', 'featured', 'homepage', 'listing', 'ovr' ];
    }

    /**
     * Tie the widget's CSS + icon font to itself so Elementor loads them
     * wherever the widget is placed — even with "optimized asset loading" on,
     * or when an optimization/caching plugin would otherwise strip the global
     * stylesheet as "unused". Handles are registered by Core\Assets.
     *
     * @return string[]
     */
    public function get_style_depends(): array {
        return [ 'ovr-public', 'ovr-material-symbols', 'ovr-google-fonts' ];
    }

    /**
     * The carousel behaviour (transform track, arrows, dots, swipe) lives in
     * the shared public script.
     *
     * @return string[]
     */
    public function get_script_depends(): array {
        return [ 'ovr-public' ];
    }

    protected function register_controls(): void {

        /* ============================================================
           CONTENT — Slider
           ============================================================ */
        $this->start_controls_section( 'slider_section', [
            'label' => esc_html__( 'Slider', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'count', [
            'label'   => esc_html__( 'Number of Listings', 'ovr-core' ),
            'type'    => Controls_Manager::NUMBER,
            'default' => 8,
            'min'     => 1,
            'max'     => 24,
        ] );

        $this->add_control( 'cta_text', [
            'label'   => esc_html__( 'Button Text', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__( 'View Property', 'ovr-core' ),
        ] );

        // Per-element visibility.
        foreach ( [
            'show_badge'    => esc_html__( 'Show Featured Badge', 'ovr-core' ),
            'show_title'    => esc_html__( 'Show Title', 'ovr-core' ),
            'show_location' => esc_html__( 'Show Location', 'ovr-core' ),
            'show_meta'     => esc_html__( 'Show Beds / Baths', 'ovr-core' ),
            'show_price'    => esc_html__( 'Show Price', 'ovr-core' ),
            'show_button'   => esc_html__( 'Show Button', 'ovr-core' ),
            'show_arrows'   => esc_html__( 'Show Arrows', 'ovr-core' ),
            'show_dots'     => esc_html__( 'Show Dots', 'ovr-core' ),
        ] as $key => $label ) {
            $this->add_control( $key, [
                'label'        => $label,
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ] );
        }

        $this->add_control( 'autoplay', [
            'label'        => esc_html__( 'Autoplay', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'separator'    => 'before',
        ] );

        $this->add_control( 'autoplay_speed', [
            'label'     => esc_html__( 'Autoplay Speed (ms)', 'ovr-core' ),
            'type'      => Controls_Manager::NUMBER,
            'default'   => 6000,
            'min'       => 2000,
            'max'       => 15000,
            'step'      => 500,
            'condition' => [ 'autoplay' => 'yes' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           CONTENT — Search Panel
           ============================================================ */
        $this->start_controls_section( 'search_section', [
            'label' => esc_html__( 'Search Panel', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'show_search', [
            'label'        => esc_html__( 'Show Search Panel', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'description'  => esc_html__( 'Off = slider fills the full width.', 'ovr-core' ),
        ] );

        $this->add_control( 'search_title', [
            'label'     => esc_html__( 'Panel Heading', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Find Your Rental', 'ovr-core' ),
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        $this->add_control( 'search_subtitle', [
            'label'     => esc_html__( 'Panel Subheading', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Search homes across The Villages.', 'ovr-core' ),
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        $this->add_control( 'address_placeholder', [
            'label'     => esc_html__( 'Address Field Placeholder', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Street or address…', 'ovr-core' ),
            'condition' => [ 'show_search' => 'yes', 'show_address' => 'yes' ],
        ] );

        $this->add_control( 'fields_heading', [
            'label'     => esc_html__( 'Fields', 'ovr-core' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        // Village Section (ovr_village taxonomy) is the primary facet and is
        // always shown. Dates and Property Type complete the primary block;
        // the rest render under an "Optional" subheading. Each toggles here.
        foreach ( [
            'show_dates'         => esc_html__( 'Show Check-in / Check-out', 'ovr-core' ),
            'show_property_type' => esc_html__( 'Show Property Type', 'ovr-core' ),
            'show_rental_term'   => esc_html__( 'Show Rental Term', 'ovr-core' ),
            'show_village'       => esc_html__( 'Show Village', 'ovr-core' ),
            'show_address'       => esc_html__( 'Show Address', 'ovr-core' ),
            'show_bedrooms'      => esc_html__( 'Show Bedrooms', 'ovr-core' ),
            'show_bathrooms'     => esc_html__( 'Show Bathrooms', 'ovr-core' ),
            'show_pets'          => esc_html__( 'Show Pets Allowed', 'ovr-core' ),
        ] as $key => $label ) {
            $this->add_control( $key, [
                'label'        => $label,
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => [ 'show_search' => 'yes' ],
            ] );
        }

        $this->add_control( 'optional_label', [
            'label'     => esc_html__( '"Optional" Subheading Text', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Optional', 'ovr-core' ),
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        $this->add_control( 'search_button_label', [
            'label'     => esc_html__( 'Search Button Text', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Search Rentals', 'ovr-core' ),
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        // Per-field width — like Elementor form columns. Full / Half / Third.
        $this->add_control( 'widths_heading', [
            'label'     => esc_html__( 'Field Widths', 'ovr-core' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        $width_opts = [
            '100' => esc_html__( 'Full · 100%', 'ovr-core' ),
            '50'  => esc_html__( 'Half · 50%', 'ovr-core' ),
            '33'  => esc_html__( 'Third · 33%', 'ovr-core' ),
        ];
        foreach ( [
            'section'  => [ esc_html__( 'Village Section', 'ovr-core' ), '100', [] ],
            'checkin'  => [ esc_html__( 'Check-in', 'ovr-core' ),       '50',  [ 'show_dates' => 'yes' ] ],
            'checkout' => [ esc_html__( 'Check-out', 'ovr-core' ),      '50',  [ 'show_dates' => 'yes' ] ],
            'ptype'    => [ esc_html__( 'Property Type', 'ovr-core' ),  '100', [ 'show_property_type' => 'yes' ] ],
            'term'     => [ esc_html__( 'Rental Term', 'ovr-core' ),    '100', [ 'show_rental_term' => 'yes' ] ],
            'village'  => [ esc_html__( 'Village', 'ovr-core' ),        '100', [ 'show_village' => 'yes' ] ],
            'address'  => [ esc_html__( 'Address', 'ovr-core' ),        '100', [ 'show_address' => 'yes' ] ],
            'beds'     => [ esc_html__( 'Bedrooms', 'ovr-core' ),       '50',  [ 'show_bedrooms' => 'yes' ] ],
            'baths'    => [ esc_html__( 'Bathrooms', 'ovr-core' ),      '50',  [ 'show_bathrooms' => 'yes' ] ],
            'pets'     => [ esc_html__( 'Pets', 'ovr-core' ),           '100', [ 'show_pets' => 'yes' ] ],
        ] as $key => $cfg ) {
            $this->add_responsive_control( 'w_' . $key, [
                /* translators: %s: field name. */
                'label'     => sprintf( esc_html__( '%s Width', 'ovr-core' ), $cfg[0] ),
                'type'      => Controls_Manager::SELECT,
                'default'   => $cfg[1],
                'options'   => $width_opts,
                'selectors' => [
                    '{{WRAPPER}} .ovr-hps-f-' . $key => 'flex: 0 0 calc({{VALUE}}% - 7px); max-width: calc({{VALUE}}% - 7px);',
                ],
                'condition' => array_merge( [ 'show_search' => 'yes' ], $cfg[2] ),
            ] );
        }

        $this->end_controls_section();

        /* ============================================================
           CONTENT — Layout
           ============================================================ */
        $this->start_controls_section( 'layout_section', [
            'label' => esc_html__( 'Layout', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_responsive_control( 'slider_width', [
            'label'      => esc_html__( 'Slider Width', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ '%' ],
            'range'      => [ '%' => [ 'min' => 50, 'max' => 100 ] ],
            'default'    => [ 'unit' => '%', 'size' => 70 ],
            'selectors'  => [
                '{{WRAPPER}} .ovr-hps-row[data-search="1"] .ovr-hps-col-main' => 'flex: 0 0 {{SIZE}}%; max-width: {{SIZE}}%;',
            ],
            'condition'  => [ 'show_search' => 'yes' ],
            'description' => esc_html__( 'Search panel fills the remaining width.', 'ovr-core' ),
        ] );

        $this->add_responsive_control( 'column_gap', [
            'label'      => esc_html__( 'Gap Between Columns', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 24 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-row' => 'gap: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_control( 'height_mode', [
            'label'   => esc_html__( 'Height Mode', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'fixed',
            'options' => [
                'fixed'      => esc_html__( 'Fixed height', 'ovr-core' ),
                'fullscreen' => esc_html__( 'Full screen (100vh)', 'ovr-core' ),
            ],
        ] );

        $this->add_responsive_control( 'slide_height', [
            'label'      => esc_html__( 'Height', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', 'vh' ],
            'range'      => [
                'px' => [ 'min' => 280, 'max' => 900 ],
                'vh' => [ 'min' => 30, 'max' => 100 ],
            ],
            'default'    => [ 'size' => 520, 'unit' => 'px' ],
            'selectors'  => [
                // min-height so a tall search panel can grow the row and the
                // slide fills it; a short panel still gets this baseline height.
                '{{WRAPPER}} .ovr-hps-slide' => 'min-height: {{SIZE}}{{UNIT}};',
            ],
            'condition'  => [ 'height_mode' => 'fixed' ],
        ] );

        $this->add_control( 'radius', [
            'label'      => esc_html__( 'Corner Radius', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'default'    => [ 'size' => 16, 'unit' => 'px' ],
            'selectors'  => [
                '{{WRAPPER}} .ovr-hps-slider, {{WRAPPER}} .ovr-hps-search' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Image
           ============================================================ */
        $this->start_controls_section( 'style_image', [
            'label' => esc_html__( 'Image', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_responsive_control( 'image_fit', [
            'label'     => esc_html__( 'Image Size', 'ovr-core' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'cover',
            'options'   => [
                'cover'   => esc_html__( 'Cover (fill)', 'ovr-core' ),
                'contain' => esc_html__( 'Contain (fit)', 'ovr-core' ),
                'auto'    => esc_html__( 'Auto (original)', 'ovr-core' ),
            ],
            'selectors' => [ '{{WRAPPER}} .ovr-hps-slide' => 'background-size: {{VALUE}};' ],
        ] );

        $this->add_responsive_control( 'image_position', [
            'label'     => esc_html__( 'Image Position', 'ovr-core' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'center center',
            'options'   => [
                'center center' => esc_html__( 'Center', 'ovr-core' ),
                'top center'    => esc_html__( 'Top', 'ovr-core' ),
                'bottom center' => esc_html__( 'Bottom', 'ovr-core' ),
                'center left'   => esc_html__( 'Left', 'ovr-core' ),
                'center right'  => esc_html__( 'Right', 'ovr-core' ),
                'top left'      => esc_html__( 'Top Left', 'ovr-core' ),
                'top right'     => esc_html__( 'Top Right', 'ovr-core' ),
                'bottom left'   => esc_html__( 'Bottom Left', 'ovr-core' ),
                'bottom right'  => esc_html__( 'Bottom Right', 'ovr-core' ),
            ],
            'selectors' => [ '{{WRAPPER}} .ovr-hps-slide' => 'background-position: {{VALUE}};' ],
        ] );

        $this->add_control( 'image_repeat', [
            'label'     => esc_html__( 'Image Repeat', 'ovr-core' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'no-repeat',
            'options'   => [
                'no-repeat' => esc_html__( 'No Repeat', 'ovr-core' ),
                'repeat'    => esc_html__( 'Repeat', 'ovr-core' ),
                'repeat-x'  => esc_html__( 'Repeat Horizontally', 'ovr-core' ),
                'repeat-y'  => esc_html__( 'Repeat Vertically', 'ovr-core' ),
            ],
            'selectors' => [ '{{WRAPPER}} .ovr-hps-slide' => 'background-repeat: {{VALUE}};' ],
        ] );

        $this->add_control( 'overlay_color', [
            'label'     => esc_html__( 'Overlay / Shade Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(0,0,0,0.78)',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-shade' => '--ovr-shade: {{VALUE}};' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Title
           ============================================================ */
        $this->start_controls_section( 'style_title', [
            'label' => esc_html__( 'Title', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_title' => 'yes' ],
        ] );

        $this->add_control( 'title_color', [
            'label'     => esc_html__( 'Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-title' => 'color: {{VALUE}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'title_typography',
            'selector' => '{{WRAPPER}} .ovr-hps-title',
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Location & Meta
           ============================================================ */
        $this->start_controls_section( 'style_meta', [
            'label' => esc_html__( 'Location & Meta', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'meta_color', [
            'label'     => esc_html__( 'Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(255,255,255,0.92)',
            'selectors' => [
                '{{WRAPPER}} .ovr-hps-location, {{WRAPPER}} .ovr-hps-meta' => 'color: {{VALUE}};',
            ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'meta_typography',
            'selector' => '{{WRAPPER}} .ovr-hps-location, {{WRAPPER}} .ovr-hps-meta',
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Price
           ============================================================ */
        $this->start_controls_section( 'style_price', [
            'label' => esc_html__( 'Price', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_price' => 'yes' ],
        ] );

        $this->add_control( 'price_color', [
            'label'     => esc_html__( 'Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-price' => 'color: {{VALUE}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'price_typography',
            'selector' => '{{WRAPPER}} .ovr-hps-price',
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Button
           ============================================================ */
        $this->start_controls_section( 'style_button', [
            'label' => esc_html__( 'Button', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_button' => 'yes' ],
        ] );

        $this->start_controls_tabs( 'btn_tabs' );

        $this->start_controls_tab( 'btn_normal', [ 'label' => esc_html__( 'Normal', 'ovr-core' ) ] );
        $this->add_control( 'btn_bg', [
            'label'     => esc_html__( 'Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#006666',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-btn' => 'background: {{VALUE}};' ],
        ] );
        $this->add_control( 'btn_color', [
            'label'     => esc_html__( 'Text Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-btn' => 'color: {{VALUE}};' ],
        ] );
        $this->end_controls_tab();

        $this->start_controls_tab( 'btn_hover', [ 'label' => esc_html__( 'Hover', 'ovr-core' ) ] );
        $this->add_control( 'btn_bg_hover', [
            'label'     => esc_html__( 'Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#004c4c',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-btn:hover' => 'background: {{VALUE}};' ],
        ] );
        $this->add_control( 'btn_color_hover', [
            'label'     => esc_html__( 'Text Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-btn:hover' => 'color: {{VALUE}};' ],
        ] );
        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_control( 'btn_radius', [
            'label'      => esc_html__( 'Border Radius', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'default'    => [ 'size' => 10, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-btn' => 'border-radius: {{SIZE}}{{UNIT}};' ],
            'separator'  => 'before',
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'btn_typography',
            'selector' => '{{WRAPPER}} .ovr-hps-btn',
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Search Panel
           ============================================================ */
        $this->start_controls_section( 'style_search', [
            'label' => esc_html__( 'Search Panel', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        $this->add_control( 'panel_bg', [
            'label'     => esc_html__( 'Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-search' => 'background: {{VALUE}};' ],
        ] );

        $this->add_responsive_control( 'panel_padding', [
            'label'      => esc_html__( 'Padding', 'ovr-core' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em', '%' ],
            'default'    => [ 'top' => '28', 'right' => '24', 'bottom' => '28', 'left' => '24', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-search' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ] );

        $this->add_responsive_control( 'panel_margin', [
            'label'      => esc_html__( 'Margin', 'ovr-core' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em', '%' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-search' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ] );

        $this->add_responsive_control( 'field_gap', [
            'label'      => esc_html__( 'Gap Between Fields', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 32 ] ],
            'default'    => [ 'size' => 10, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-search-form' => 'gap: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->end_controls_section();

        /* ── Search Panel — Heading ── */
        $this->start_controls_section( 'style_panel_heading', [
            'label'     => esc_html__( 'Panel Heading', 'ovr-core' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        $this->add_control( 'panel_title_color', [
            'label'     => esc_html__( 'Heading Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#101828',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-search-title' => 'color: {{VALUE}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'panel_title_typography',
            'selector' => '{{WRAPPER}} .ovr-hps-search-title',
        ] );

        $this->add_responsive_control( 'panel_title_gap', [
            'label'      => esc_html__( 'Space Below Heading', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'default'    => [ 'size' => 4, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-search-title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_control( 'panel_sub_color', [
            'label'     => esc_html__( 'Subtext Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#667085',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-search-sub' => 'color: {{VALUE}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'panel_sub_typography',
            'selector' => '{{WRAPPER}} .ovr-hps-search-sub',
        ] );

        $this->add_responsive_control( 'panel_sub_gap', [
            'label'      => esc_html__( 'Space Below Subtext', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 48 ] ],
            'default'    => [ 'size' => 20, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-search-sub' => 'margin-bottom: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->end_controls_section();

        /* ── Search Panel — Fields ── */
        $this->start_controls_section( 'style_search_fields', [
            'label'     => esc_html__( 'Search Fields', 'ovr-core' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'field_typography',
            'label'    => esc_html__( 'Text Typography', 'ovr-core' ),
            'selector' => '{{WRAPPER}} .ovr-hps-search-field input, {{WRAPPER}} .ovr-hps-search-field select, {{WRAPPER}} .ovr-hps-search-check',
        ] );

        $this->add_control( 'field_text_color', [
            'label'     => esc_html__( 'Text Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#181c1c',
            'selectors' => [
                '{{WRAPPER}} .ovr-hps-search-field input, {{WRAPPER}} .ovr-hps-search-field select, {{WRAPPER}} .ovr-hps-search-check' => 'color: {{VALUE}};',
            ],
        ] );

        $this->add_responsive_control( 'field_icon_size', [
            'label'      => esc_html__( 'Icon Size', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 12, 'max' => 40 ] ],
            'default'    => [ 'size' => 20, 'unit' => 'px' ],
            'selectors'  => [
                '{{WRAPPER}} .ovr-hps-search-field .material-symbols-outlined, {{WRAPPER}} .ovr-hps-search-check .material-symbols-outlined' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->add_control( 'field_icon_color', [
            'label'     => esc_html__( 'Icon Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#667085',
            'selectors' => [
                '{{WRAPPER}} .ovr-hps-search-field .material-symbols-outlined, {{WRAPPER}} .ovr-hps-search-check .material-symbols-outlined' => 'color: {{VALUE}};',
            ],
        ] );

        $this->add_responsive_control( 'field_padding', [
            'label'      => esc_html__( 'Field Height (padding)', 'ovr-core' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '11', 'right' => '12', 'bottom' => '11', 'left' => '12', 'unit' => 'px' ],
            'selectors'  => [
                '{{WRAPPER}} .ovr-hps-search-field' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                '{{WRAPPER}} .ovr-hps-search-field input, {{WRAPPER}} .ovr-hps-search-field select' => 'padding-top: 0; padding-bottom: 0;',
            ],
        ] );

        $this->add_control( 'field_bg', [
            'label'     => esc_html__( 'Field Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-search-field' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'field_border_color', [
            'label'     => esc_html__( 'Field Border Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#d6dede',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-search-field' => 'border-color: {{VALUE}};' ],
        ] );

        $this->add_responsive_control( 'field_radius', [
            'label'      => esc_html__( 'Field Radius', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
            'default'    => [ 'size' => 10, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-search-field' => 'border-radius: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_control( 'optional_color', [
            'label'     => esc_html__( '"Optional" Label Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#98a2b3',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-search-optional' => 'color: {{VALUE}};' ],
            'separator' => 'before',
        ] );

        $this->end_controls_section();

        /* ── Search Panel — Button ── */
        $this->start_controls_section( 'style_search_button', [
            'label'     => esc_html__( 'Search Button', 'ovr-core' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_search' => 'yes' ],
        ] );

        $this->add_control( 'search_btn_bg', [
            'label'     => esc_html__( 'Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#006666',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-search-submit' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'search_btn_bg_hover', [
            'label'     => esc_html__( 'Background (Hover)', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#004c4c',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-search-submit:hover' => 'background: {{VALUE}};' ],
        ] );

        $this->add_control( 'search_btn_color', [
            'label'     => esc_html__( 'Text Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-hps-search-submit' => 'color: {{VALUE}};' ],
        ] );

        $this->add_responsive_control( 'search_btn_width', [
            'label'      => esc_html__( 'Width', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ '%', 'px' ],
            'range'      => [ '%' => [ 'min' => 20, 'max' => 100 ], 'px' => [ 'min' => 100, 'max' => 600 ] ],
            'default'    => [ 'size' => 100, 'unit' => '%' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-search-submit' => 'width: {{SIZE}}{{UNIT}}; flex-basis: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_responsive_control( 'search_btn_padding', [
            'label'      => esc_html__( 'Padding', 'ovr-core' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '14', 'right' => '20', 'bottom' => '14', 'left' => '20', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-search-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ] );

        $this->add_control( 'search_btn_radius', [
            'label'      => esc_html__( 'Border Radius', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'default'    => [ 'size' => 10, 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-hps-search-submit' => 'border-radius: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'search_btn_typography',
            'selector' => '{{WRAPPER}} .ovr-hps-search-submit',
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        // Print the critical layout CSS inline (once per page). This guarantees
        // the slider is an actual slider with the panel on the right even if the
        // external stylesheet is cache-stale, purged by a "remove unused CSS"
        // optimizer, or fails to load — the failure mode we kept hitting on live.
        self::print_critical_css();

        $settings = $this->get_settings_for_display();
        $count    = absint( $settings['count'] ) ?: 8;

        $query = PropertyQuery::get_slider( $count );
        if ( ! $query->have_posts() ) {
            $query = PropertyQuery::query( [ 'per_page' => $count, 'sort' => 'newest' ] );
        }

        if ( ! $query->have_posts() ) {
            echo '<div class="ovr-wrap" style="text-align:center;padding:40px">';
            echo '<span class="material-symbols-outlined" style="font-size:48px;color:var(--ovr-outline-variant)">image</span>';
            echo '<p style="color:var(--ovr-on-surface-variant);margin-top:12px">' . esc_html__( 'No listings to feature yet.', 'ovr-core' ) . '</p>';
            echo '</div>';
            wp_reset_postdata();
            return;
        }

        $autoplay   = 'yes' === ( $settings['autoplay'] ?? 'yes' );
        $speed      = max( 2000, absint( $settings['autoplay_speed'] ?? 6000 ) );
        $arrows     = 'yes' === ( $settings['show_arrows'] ?? 'yes' );
        $dots       = 'yes' === ( $settings['show_dots'] ?? 'yes' );
        $show_price = 'yes' === ( $settings['show_price'] ?? 'yes' );
        $show_meta  = 'yes' === ( $settings['show_meta'] ?? 'yes' );
        $show_title = 'yes' === ( $settings['show_title'] ?? 'yes' );
        $show_loc   = 'yes' === ( $settings['show_location'] ?? 'yes' );
        $show_badge = 'yes' === ( $settings['show_badge'] ?? 'yes' );
        $show_btn   = 'yes' === ( $settings['show_button'] ?? 'yes' );
        $show_search = 'yes' === ( $settings['show_search'] ?? 'yes' );
        $fullscreen = 'fullscreen' === ( $settings['height_mode'] ?? 'fixed' );
        $cta_text   = '' !== trim( (string) ( $settings['cta_text'] ?? '' ) ) ? (string) $settings['cta_text'] : __( 'View Property', 'ovr-core' );

        // Collect slide data first so dots can be built in one pass.
        $slides = [];
        while ( $query->have_posts() ) {
            $query->the_post();
            $pid     = (int) get_the_ID();
            $beds    = (int) get_post_meta( $pid, '_ovr_bedrooms', true );
            $baths_r = (float) get_post_meta( $pid, '_ovr_bathrooms', true );
            $baths   = rtrim( rtrim( number_format( $baths_r, 1 ), '0' ), '.' );
            $village = (string) get_post_meta( $pid, '_ovr_village_name', true );

            $slides[] = [
                'title'     => get_the_title( $pid ) ?: __( 'Village Rental', 'ovr-core' ),
                'image'     => get_the_post_thumbnail_url( $pid, 'full' ) ?: ( OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg' ),
                'location'  => $village ? sprintf( __( 'Village of %s', 'ovr-core' ), $village ) : __( 'Owner-Direct Rental', 'ovr-core' ),
                'price'     => SeasonalPricing::price_summary( $pid ),
                'beds'      => $beds,
                'baths'     => $baths,
                'permalink' => (string) get_permalink( $pid ),
                'featured'  => '1' === (string) get_post_meta( $pid, '_ovr_is_featured', true ),
            ];
        }
        wp_reset_postdata();

        $row_classes = 'ovr-hps-row' . ( $fullscreen ? ' ovr-hps--fullscreen' : '' );

        echo '<div class="ovr-wrap">';
        printf( '<div class="%s" data-search="%s">', esc_attr( $row_classes ), $show_search ? '1' : '0' );

        /* ── 70% slider column ── */
        echo '<div class="ovr-hps-col-main">';
        printf(
            '<div class="ovr-hps-slider" data-autoplay="%s" data-interval="%d">',
            $autoplay ? '1' : '0',
            (int) $speed
        );

        echo '<div class="ovr-hps-viewport"><div class="ovr-hps-track">';
        foreach ( $slides as $s ) {
            ?>
            <div class="ovr-hps-slide" style="background-image:url('<?php echo esc_url( $s['image'] ); ?>')">
                <div class="ovr-hps-shade"></div>
                <div class="ovr-hps-content">
                    <?php if ( $show_badge && $s['featured'] ) : ?>
                        <span class="ovr-hps-badge"><span class="material-symbols-outlined">star</span><?php esc_html_e( 'Featured', 'ovr-core' ); ?></span>
                    <?php endif; ?>
                    <?php if ( $show_title ) : ?>
                        <h3 class="ovr-hps-title"><?php echo esc_html( $s['title'] ); ?></h3>
                    <?php endif; ?>
                    <?php if ( $show_loc ) : ?>
                        <p class="ovr-hps-location"><span class="material-symbols-outlined">location_on</span><?php echo esc_html( $s['location'] ); ?></p>
                    <?php endif; ?>
                    <?php if ( $show_meta && ( $s['beds'] || $s['baths'] ) ) : ?>
                        <p class="ovr-hps-meta">
                            <?php if ( $s['beds'] ) : ?><span><span class="material-symbols-outlined">bed</span><?php echo esc_html( sprintf( _n( '%d Bed', '%d Beds', $s['beds'], 'ovr-core' ), $s['beds'] ) ); ?></span><?php endif; ?>
                            <?php if ( $s['baths'] ) : ?><span><span class="material-symbols-outlined">bathtub</span><?php echo esc_html( sprintf( __( '%s Bath', 'ovr-core' ), $s['baths'] ) ); ?></span><?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if ( $show_btn || ( $show_price && '' !== $s['price'] ) ) : ?>
                        <div class="ovr-hps-actions">
                            <?php if ( $show_btn ) : ?>
                                <a class="ovr-hps-btn" href="<?php echo esc_url( $s['permalink'] ); ?>"><?php echo esc_html( $cta_text ); ?></a>
                            <?php endif; ?>
                            <?php if ( $show_price && '' !== $s['price'] ) : ?>
                                <span class="ovr-hps-price"><?php echo esc_html( $s['price'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        echo '</div></div>';

        if ( $arrows && count( $slides ) > 1 ) {
            echo '<button type="button" class="ovr-hps-nav ovr-hps-prev" aria-label="' . esc_attr__( 'Previous property', 'ovr-core' ) . '"><span class="material-symbols-outlined">chevron_left</span></button>';
            echo '<button type="button" class="ovr-hps-nav ovr-hps-next" aria-label="' . esc_attr__( 'Next property', 'ovr-core' ) . '"><span class="material-symbols-outlined">chevron_right</span></button>';
        }

        if ( $dots && count( $slides ) > 1 ) {
            echo '<div class="ovr-hps-dots" role="tablist">';
            foreach ( $slides as $i => $s ) {
                printf(
                    '<button type="button" class="ovr-hps-dot%s" data-index="%d" aria-label="%s"></button>',
                    0 === $i ? ' is-active' : '',
                    (int) $i,
                    esc_attr( sprintf( __( 'Go to slide %d', 'ovr-core' ), $i + 1 ) )
                );
            }
            echo '</div>';
        }

        echo '</div>'; // .ovr-hps-slider
        echo '</div>'; // .ovr-hps-col-main

        /* ── 30% search column ── */
        if ( $show_search ) {
            $this->render_search_panel( $settings );
        }

        echo '</div></div>'; // .ovr-hps-row .ovr-wrap
    }

    /**
     * The 30% search panel. Village Section (ovr_village taxonomy) is the
     * primary field; Village, Address, Bedrooms and Bathrooms are optional and
     * toggle via controls. Posts as GET to the search results page — field
     * names match SearchHandler::get_filters_from_request().
     */
    private function render_search_panel( array $s ): void {
        $search_url = Pages::get_page_url( 'ovr_page_search' );
        $title      = (string) ( $s['search_title'] ?? '' );
        $subtitle   = (string) ( $s['search_subtitle'] ?? '' );
        $addr_ph    = (string) ( $s['address_placeholder'] ?? __( 'Street or address…', 'ovr-core' ) );
        $opt_label  = (string) ( $s['optional_label'] ?? __( 'Optional', 'ovr-core' ) );
        $btn_label  = '' !== trim( (string) ( $s['search_button_label'] ?? '' ) ) ? (string) $s['search_button_label'] : __( 'Search Rentals', 'ovr-core' );

        // Primary fields.
        $show_dates  = 'yes' === ( $s['show_dates'] ?? 'yes' );
        $show_ptype  = 'yes' === ( $s['show_property_type'] ?? 'yes' );
        // Optional fields.
        $show_term   = 'yes' === ( $s['show_rental_term'] ?? 'yes' );
        $show_village   = 'yes' === ( $s['show_village'] ?? 'yes' );
        $show_address   = 'yes' === ( $s['show_address'] ?? 'yes' );
        $show_bedrooms  = 'yes' === ( $s['show_bedrooms'] ?? 'yes' );
        $show_bathrooms = 'yes' === ( $s['show_bathrooms'] ?? 'yes' );
        $show_pets      = 'yes' === ( $s['show_pets'] ?? 'yes' );

        $has_optional = $show_term || $show_village || $show_address || $show_bedrooms || $show_bathrooms || $show_pets;

        $sections      = get_terms( [ 'taxonomy' => 'ovr_village', 'hide_empty' => false ] );
        $property_types = $show_ptype ? get_terms( [ 'taxonomy' => 'ovr_property_type', 'hide_empty' => false ] ) : [];
        $rental_types  = $show_term ? get_terms( [ 'taxonomy' => 'ovr_rental_type', 'hide_empty' => false ] ) : [];
        $villages      = $show_village ? self::village_names() : [];

        // Each field carries an .ovr-hps-f-<key> class so its width is controlled
        // independently (Field Widths controls), like Elementor form columns.
        $term_dropdown = static function ( $terms, string $name, string $icon, string $all_label, string $field_class ) {
            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                return;
            }
            ?>
            <label class="ovr-hps-search-field <?php echo esc_attr( $field_class ); ?>">
                <span class="material-symbols-outlined"><?php echo esc_html( $icon ); ?></span>
                <select name="<?php echo esc_attr( $name ); ?>">
                    <option value=""><?php echo esc_html( $all_label ); ?></option>
                    <?php foreach ( $terms as $t ) : ?>
                        <option value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php
        };
        ?>
        <div class="ovr-hps-col-side">
            <div class="ovr-hps-search">
                <?php if ( '' !== $title ) : ?>
                    <h4 class="ovr-hps-search-title"><?php echo esc_html( $title ); ?></h4>
                <?php endif; ?>
                <?php if ( '' !== $subtitle ) : ?>
                    <p class="ovr-hps-search-sub"><?php echo esc_html( $subtitle ); ?></p>
                <?php endif; ?>
                <form class="ovr-hps-search-form" action="<?php echo esc_url( $search_url ); ?>" method="get">

                    <!-- ── Primary ── -->
                    <?php $term_dropdown( $sections, 'village_section[]', 'map', __( 'All Sections', 'ovr-core' ), 'ovr-hps-f-section' ); ?>

                    <?php if ( $show_dates ) : ?>
                        <label class="ovr-hps-search-field ovr-hps-f-checkin">
                            <span class="material-symbols-outlined">login</span>
                            <input type="date" name="checkin" aria-label="<?php esc_attr_e( 'Check-in', 'ovr-core' ); ?>">
                        </label>
                        <label class="ovr-hps-search-field ovr-hps-f-checkout">
                            <span class="material-symbols-outlined">logout</span>
                            <input type="date" name="checkout" aria-label="<?php esc_attr_e( 'Check-out', 'ovr-core' ); ?>">
                        </label>
                    <?php endif; ?>

                    <?php if ( $show_ptype ) { $term_dropdown( $property_types, 'property_type[]', 'home', __( 'All Property Types', 'ovr-core' ), 'ovr-hps-f-ptype' ); } ?>

                    <?php if ( $has_optional ) : ?>
                        <div class="ovr-hps-search-optional"><?php echo esc_html( $opt_label ); ?></div>
                    <?php endif; ?>

                    <!-- ── Optional ── -->
                    <?php if ( $show_term ) { $term_dropdown( $rental_types, 'rental_type[]', 'event_repeat', __( 'Any Rental Term', 'ovr-core' ), 'ovr-hps-f-term' ); } ?>

                    <?php if ( $show_village && ! empty( $villages ) ) : ?>
                        <label class="ovr-hps-search-field ovr-hps-f-village">
                            <span class="material-symbols-outlined">holiday_village</span>
                            <select name="village[]">
                                <option value=""><?php esc_html_e( 'Any Village', 'ovr-core' ); ?></option>
                                <?php foreach ( $villages as $name ) : ?>
                                    <option value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <?php if ( $show_address ) : ?>
                        <label class="ovr-hps-search-field ovr-hps-f-address">
                            <span class="material-symbols-outlined">location_on</span>
                            <input type="text" name="address" placeholder="<?php echo esc_attr( $addr_ph ); ?>">
                        </label>
                    <?php endif; ?>

                    <?php if ( $show_bedrooms ) : ?>
                        <label class="ovr-hps-search-field ovr-hps-f-beds">
                            <span class="material-symbols-outlined">bed</span>
                            <select name="bedrooms">
                                <option value=""><?php esc_html_e( 'Any Beds', 'ovr-core' ); ?></option>
                                <?php for ( $i = 1; $i <= 6; $i++ ) : ?>
                                    <option value="<?php echo (int) $i; ?>"><?php echo esc_html( sprintf( __( '%d+ Beds', 'ovr-core' ), $i ) ); ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <?php if ( $show_bathrooms ) : ?>
                        <label class="ovr-hps-search-field ovr-hps-f-baths">
                            <span class="material-symbols-outlined">bathtub</span>
                            <select name="bathrooms">
                                <option value=""><?php esc_html_e( 'Any Baths', 'ovr-core' ); ?></option>
                                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                    <option value="<?php echo (int) $i; ?>"><?php echo esc_html( sprintf( __( '%d+ Baths', 'ovr-core' ), $i ) ); ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <?php if ( $show_pets ) : ?>
                        <label class="ovr-hps-search-check ovr-hps-f-pets">
                            <input type="checkbox" name="pets" value="1">
                            <span class="material-symbols-outlined">pets</span>
                            <span><?php esc_html_e( 'Pets allowed', 'ovr-core' ); ?></span>
                        </label>
                    <?php endif; ?>

                    <button type="submit" class="ovr-hps-search-submit"><?php echo esc_html( $btn_label ); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Print the slider's critical structural CSS inline, once per request.
     *
     * Decorative styling still lives in ovr-public.css (with var() fallbacks);
     * this covers only the layout that makes it recognisably a working slider
     * with a right-hand search panel, so the widget can never render as a raw
     * unstyled stack regardless of asset caching/optimization on the host site.
     */
    private static function print_critical_css(): void {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;

        $css = <<<CSS
.ovr-hps-row{display:flex;align-items:stretch;gap:24px;width:100%}
.ovr-hps-col-main{flex:1 1 auto;min-width:0}
.ovr-hps-row[data-search="1"] .ovr-hps-col-main{flex:0 0 70%;max-width:70%}
.ovr-hps-col-side{flex:1 1 0;min-width:0;display:flex;align-items:stretch}
.ovr-hps-slider{position:relative;width:100%;height:100%;min-height:100%;overflow:hidden;border-radius:16px}
.ovr-hps-viewport{overflow:hidden;width:100%;height:100%}
.ovr-hps-track{display:flex;width:100%;height:100%;transition:transform .55s cubic-bezier(.4,0,.2,1)}
.ovr-hps-slide{position:relative;flex:0 0 100%;width:100%;height:100%;min-height:480px;overflow:hidden;background-size:cover;background-position:center center;background-repeat:no-repeat}
.ovr-hps-shade{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.78) 0%,rgba(0,0,0,.28) 42%,rgba(0,0,0,0) 70%)}
.ovr-hps-content{position:absolute;left:0;bottom:0;z-index:2;width:100%;max-width:60%;padding:0 6% 7%;color:#fff;display:flex;flex-direction:column;align-items:flex-start;text-align:left}
.ovr-hps-title{margin:0 0 6px;font-size:clamp(20px,2vw,30px);line-height:1.18;font-weight:700;color:#fff}
.ovr-hps-location,.ovr-hps-meta{display:flex;align-items:center;gap:6px;margin:0 0 8px;font-size:15px;color:rgba(255,255,255,.92)}
.ovr-hps-meta{gap:18px;flex-wrap:wrap}
.ovr-hps-meta span{display:inline-flex;align-items:center;gap:6px}
.ovr-hps-actions{display:flex;align-items:center;gap:18px;margin-top:16px;flex-wrap:wrap}
.ovr-hps-slider .ovr-hps-btn{display:inline-flex;align-items:center;justify-content:center;background:var(--ovr-primary,#006666);color:#fff;font-weight:600;padding:12px 26px;border-radius:10px;text-decoration:none}
.ovr-hps-price{font-weight:700;color:#fff}
.ovr-hps-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:5;display:flex;align-items:center;justify-content:center;width:48px;height:48px;border:none;border-radius:50%;background:rgba(255,255,255,.92);cursor:pointer}
.ovr-hps-prev{left:18px}.ovr-hps-next{right:18px}
.ovr-hps-dots{position:absolute;bottom:16px;right:24px;z-index:5;display:flex;gap:8px}
.ovr-hps-dot{width:9px;height:9px;padding:0;border:none;border-radius:50%;background:rgba(255,255,255,.5);cursor:pointer}
.ovr-hps-dot.is-active{background:#fff;width:24px;border-radius:999px}
.ovr-hps-search{width:100%;background:#fff;border-radius:16px;padding:28px 24px;display:flex;flex-direction:column;justify-content:center}
.ovr-hps-search-title{margin:0 0 4px;font-size:22px;font-weight:700;color:#101828}
.ovr-hps-search-sub{margin:0 0 20px;font-size:14px}
.ovr-hps-search-form{display:flex;flex-wrap:wrap;align-items:flex-start;gap:10px}
.ovr-hps-search-field,.ovr-hps-search-check{flex:0 0 100%;max-width:100%;min-width:0}
.ovr-hps-f-checkin,.ovr-hps-f-checkout,.ovr-hps-f-beds,.ovr-hps-f-baths{flex:0 0 calc(50% - 7px);max-width:calc(50% - 7px)}
.ovr-hps-search-optional,.ovr-hps-search-submit{flex:0 0 100%}
.ovr-hps-search-optional{margin:6px 0 0;font-size:12px;font-weight:700;text-transform:uppercase;border-top:1px solid #eaecf0;padding-top:12px}
.ovr-hps-search-check{display:flex;align-items:center;gap:8px}
.ovr-hps-search-field{display:flex;align-items:center;gap:8px;border:1px solid var(--ovr-border-gray,#d6dede);border-radius:10px;padding:0 12px;background:#fff}
.ovr-hps-search-field input,.ovr-hps-search-field select{-webkit-appearance:none;appearance:none;flex:1;min-width:0;width:100%;border:none;outline:none;background:transparent;padding:11px 0;font-family:inherit;font-size:14px;color:#181c1c}
.ovr-hps-search-submit{-webkit-appearance:none;appearance:none;width:100%;border:none;cursor:pointer;background:var(--ovr-primary,#006666);color:#fff;font-weight:600;padding:14px 20px;border-radius:10px}
@media (max-width:1024px){.ovr-hps-row{flex-direction:column !important}.ovr-hps-row .ovr-hps-col-main,.ovr-hps-row[data-search="1"] .ovr-hps-col-main,.ovr-hps-row .ovr-hps-col-side{flex:1 1 auto !important;width:100% !important;max-width:100% !important}}
@media (max-width:768px){.ovr-hps-nav{display:none}.ovr-hps-dots{left:50%;right:auto;transform:translateX(-50%)}}
@media (max-width:540px){.ovr-hps-search-form>.ovr-hps-search-field,.ovr-hps-search-form>.ovr-hps-search-check,.ovr-hps-f-checkin,.ovr-hps-f-checkout,.ovr-hps-f-beds,.ovr-hps-f-baths{flex:0 0 100% !important;max-width:100% !important}}
CSS;

        echo "\n<style id='ovr-hps-critical'>" . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
    }

    /**
     * Distinct, non-empty Village Name meta values across published listings,
     * alphabetised — used to populate the optional Village dropdown.
     *
     * @return string[]
     */
    private static function village_names(): array {
        global $wpdb;
        $rows = $wpdb->get_col(
            "SELECT DISTINCT pm.meta_value
               FROM {$wpdb->postmeta} pm
               INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE pm.meta_key = '_ovr_village_name'
                AND pm.meta_value <> ''
                AND p.post_type = 'ovr_property'
                AND p.post_status = 'publish'
              ORDER BY pm.meta_value ASC
              LIMIT 200"
        );
        return array_values( array_filter( array_map( 'strval', (array) $rows ) ) );
    }
}
