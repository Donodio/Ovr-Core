<?php
/**
 * Elementor Search Bar Widget.
 *
 * Renders the OVR search pill with rich style + layout controls.
 *
 * @package OVR\Elementor\Widgets
 * @since   1.0.0
 */

namespace OVR\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SearchBarWidget extends Widget_Base {

    public function get_name(): string     { return 'ovr_search_bar'; }
    public function get_title(): string    { return esc_html__( 'OVR Search Bar', 'ovr-core' ); }
    public function get_icon(): string     { return 'eicon-search'; }
    public function get_categories(): array{ return [ 'ovr-widgets' ]; }
    public function get_keywords(): array  { return [ 'search', 'filter', 'property', 'ovr' ]; }

    protected function register_controls(): void {

        /* ============================================================
           CONTENT
           ============================================================ */
        $this->start_controls_section( 'content_section', [
            'label' => esc_html__( 'Search Bar Settings', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'placeholder', [
            'label'   => esc_html__( 'Placeholder Text', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__( 'Search by village or keyword…', 'ovr-core' ),
        ] );

        $this->add_control( 'location_label', [
            'label'   => esc_html__( 'Location Label', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__( 'Location', 'ovr-core' ),
        ] );

        $this->add_control( 'show_type_filter', [
            'label'        => esc_html__( 'Show Property Type Filter', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'type_label', [
            'label'   => esc_html__( 'Property Type Label', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__( 'Property Type', 'ovr-core' ),
            'condition' => [ 'show_type_filter' => 'yes' ],
        ] );

        $this->add_control( 'show_date_field', [
            'label'        => esc_html__( 'Show Check-in Date', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'show_guests_field', [
            'label'        => esc_html__( 'Show Guests Field', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'show_button_label', [
            'label'        => esc_html__( 'Show Button Text', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'return_value' => 'yes',
            'description'  => esc_html__( 'Off = icon only.', 'ovr-core' ),
        ] );

        $this->add_control( 'button_label', [
            'label'   => esc_html__( 'Button Label', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__( 'Search', 'ovr-core' ),
            'condition' => [ 'show_button_label' => 'yes' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           LAYOUT
           ============================================================ */
        $this->start_controls_section( 'layout_section', [
            'label' => esc_html__( 'Layout', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'layout', [
            'label'   => esc_html__( 'Direction', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'horizontal',
            'options' => [
                'horizontal' => esc_html__( 'Horizontal (pill)', 'ovr-core' ),
                'stacked'    => esc_html__( 'Stacked (vertical)', 'ovr-core' ),
            ],
            'selectors' => [
                '{{WRAPPER}} .ovr-search-pill' => 'flex-direction: {{VALUE === "stacked" ? "column" : "row"}}; align-items: {{VALUE === "stacked" ? "stretch" : "center"}};',
            ],
        ] );

        $this->add_responsive_control( 'max_width', [
            'label'      => esc_html__( 'Max Width', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', '%' ],
            'range'      => [
                'px' => [ 'min' => 280, 'max' => 1400 ],
                '%'  => [ 'min' => 30,  'max' => 100 ],
            ],
            'default'    => [ 'unit' => 'px', 'size' => 720 ],
            'selectors'  => [
                '{{WRAPPER}} .ovr-search-pill' => 'max-width: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->add_responsive_control( 'alignment', [
            'label'   => esc_html__( 'Alignment', 'ovr-core' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'flex-start' => [ 'title'=>esc_html__('Left',  'ovr-core'), 'icon'=>'eicon-h-align-left' ],
                'center'     => [ 'title'=>esc_html__('Center','ovr-core'), 'icon'=>'eicon-h-align-center' ],
                'flex-end'   => [ 'title'=>esc_html__('Right', 'ovr-core'), 'icon'=>'eicon-h-align-right' ],
            ],
            'default' => 'center',
            'selectors' => [
                '{{WRAPPER}} .ovr-search-wrap' => 'display:flex;justify-content:{{VALUE}}',
            ],
        ] );

        $this->add_responsive_control( 'gap', [
            'label'      => esc_html__( 'Gap Between Fields', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 24 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 4 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-search-pill' => 'gap: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Bar
           ============================================================ */
        $this->start_controls_section( 'style_bar', [
            'label' => esc_html__( 'Bar', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'bar_bg_color', [
            'label'     => esc_html__( 'Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-search-pill' => 'background:{{VALUE}};' ],
        ] );

        $this->add_responsive_control( 'bar_height', [
            'label'      => esc_html__( 'Bar Height', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 36, 'max' => 96 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 56 ],
            'selectors'  => [
                '{{WRAPPER}} .ovr-search-pill' => 'min-height: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->add_responsive_control( 'bar_padding', [
            'label'      => esc_html__( 'Padding', 'ovr-core' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-search-pill' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ] );

        $this->add_responsive_control( 'bar_radius', [
            'label'      => esc_html__( 'Border Radius', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 100 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 999 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-search-pill' => 'border-radius: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_group_control( Group_Control_Border::get_type(), [
            'name'     => 'bar_border',
            'selector' => '{{WRAPPER}} .ovr-search-pill',
        ] );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), [
            'name'     => 'bar_shadow',
            'selector' => '{{WRAPPER}} .ovr-search-pill',
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Field labels
           ============================================================ */
        $this->start_controls_section( 'style_labels', [
            'label' => esc_html__( 'Field Labels', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'show_labels', [
            'label'        => esc_html__( 'Show Labels', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'selectors'    => [
                '{{WRAPPER}} .ovr-search-field-label' => 'display: {{VALUE === "yes" ? "block" : "none"}};',
            ],
        ] );

        $this->add_control( 'label_color', [
            'label'     => esc_html__( 'Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#3f4948',
            'selectors' => [ '{{WRAPPER}} .ovr-search-field-label' => 'color:{{VALUE}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'label_typography',
            'selector' => '{{WRAPPER}} .ovr-search-field-label',
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Inputs
           ============================================================ */
        $this->start_controls_section( 'style_inputs', [
            'label' => esc_html__( 'Inputs', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'input_color', [
            'label'     => esc_html__( 'Text Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#181c1c',
            'selectors' => [ '{{WRAPPER}} .ovr-search-field input, {{WRAPPER}} .ovr-search-field select' => 'color:{{VALUE}};' ],
        ] );

        $this->add_control( 'placeholder_color', [
            'label'     => esc_html__( 'Placeholder Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#6f7979',
            'selectors' => [ '{{WRAPPER}} .ovr-search-field input::placeholder' => 'color:{{VALUE}};' ],
        ] );

        $this->add_control( 'field_hover_bg', [
            'label'     => esc_html__( 'Field Hover Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#f1f4f3',
            'selectors' => [ '{{WRAPPER}} .ovr-search-field:hover' => 'background:{{VALUE}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'input_typography',
            'selector' => '{{WRAPPER}} .ovr-search-field input, {{WRAPPER}} .ovr-search-field select',
        ] );

        $this->add_responsive_control( 'field_padding', [
            'label'      => esc_html__( 'Field Padding', 'ovr-core' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '6', 'right' => '14', 'bottom' => '6', 'left' => '14', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-search-field' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
        ] );

        $this->add_control( 'divider_color', [
            'label'     => esc_html__( 'Divider Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#d6dede',
            'selectors' => [ '{{WRAPPER}} .ovr-search-divider' => 'background:{{VALUE}};' ],
        ] );

        $this->add_control( 'divider_height', [
            'label'      => esc_html__( 'Divider Height', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 28 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-search-divider' => 'height: {{SIZE}}{{UNIT}};' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Submit Button
           ============================================================ */
        $this->start_controls_section( 'style_button', [
            'label' => esc_html__( 'Submit Button', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->start_controls_tabs( 'button_tabs' );

        // Normal.
        $this->start_controls_tab( 'button_tab_normal', [ 'label' => esc_html__( 'Normal', 'ovr-core' ) ] );

        $this->add_control( 'button_bg', [
            'label'     => esc_html__( 'Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#006666',
            'selectors' => [ '{{WRAPPER}} .ovr-search-submit' => 'background:{{VALUE}};' ],
        ] );

        $this->add_control( 'button_color', [
            'label'     => esc_html__( 'Text / Icon Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-search-submit, {{WRAPPER}} .ovr-search-submit .material-symbols-outlined' => 'color:{{VALUE}};' ],
        ] );

        $this->end_controls_tab();

        // Hover.
        $this->start_controls_tab( 'button_tab_hover', [ 'label' => esc_html__( 'Hover', 'ovr-core' ) ] );

        $this->add_control( 'button_hover_bg', [
            'label'     => esc_html__( 'Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#004c4c',
            'selectors' => [ '{{WRAPPER}} .ovr-search-submit:hover' => 'background:{{VALUE}};' ],
        ] );

        $this->add_control( 'button_hover_color', [
            'label'     => esc_html__( 'Text / Icon Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [ '{{WRAPPER}} .ovr-search-submit:hover, {{WRAPPER}} .ovr-search-submit:hover .material-symbols-outlined' => 'color:{{VALUE}};' ],
        ] );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control( 'button_size', [
            'label'      => esc_html__( 'Button Size (square)', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 32, 'max' => 80 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 44 ],
            'selectors'  => [
                '{{WRAPPER}} .ovr-search-submit' => 'width:{{SIZE}}{{UNIT}}!important;height:{{SIZE}}{{UNIT}}!important;',
            ],
        ] );

        $this->add_control( 'button_radius', [
            'label'      => esc_html__( 'Border Radius', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px', '%' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ], '%' => [ 'min' => 0, 'max' => 50 ] ],
            'default'    => [ 'unit' => '%', 'size' => 50 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-search-submit' => 'border-radius:{{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_control( 'button_icon_size', [
            'label'      => esc_html__( 'Icon Size', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 14, 'max' => 36 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 20 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-search-submit .material-symbols-outlined' => 'font-size:{{SIZE}}{{UNIT}};' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'button_typography',
            'selector' => '{{WRAPPER}} .ovr-search-submit-text',
            'condition' => [ 'show_button_label' => 'yes' ],
        ] );

        $this->end_controls_section();
    }

    /**
     * Render output on the frontend.
     */
    protected function render(): void {
        $s          = $this->get_settings_for_display();
        $search_url = Pages::get_page_url( 'ovr_page_search' );
        ?>
        <div class="ovr-search-wrap">
        <form class="ovr-search-pill" action="<?php echo esc_url( $search_url ); ?>" method="get">

            <!-- Location -->
            <div class="ovr-search-field">
                <span class="ovr-search-field-label"><?php echo esc_html( $s['location_label'] ?? __( 'Location', 'ovr-core' ) ); ?></span>
                <input type="text" name="keyword" placeholder="<?php echo esc_attr( $s['placeholder'] ?? '' ); ?>">
            </div>

            <?php if ( 'yes' === ( $s['show_type_filter'] ?? 'yes' ) ) : ?>
                <div class="ovr-search-divider"></div>
                <div class="ovr-search-field">
                    <span class="ovr-search-field-label"><?php echo esc_html( $s['type_label'] ?? __( 'Property Type', 'ovr-core' ) ); ?></span>
                    <select name="property_type">
                        <option value=""><?php esc_html_e( 'All Types', 'ovr-core' ); ?></option>
                        <?php
                        $types = get_terms( [ 'taxonomy' => 'ovr_property_type', 'hide_empty' => true ] );
                        if ( ! is_wp_error( $types ) ) :
                            foreach ( $types as $type ) : ?>
                                <option value="<?php echo esc_attr( $type->slug ); ?>"><?php echo esc_html( $type->name ); ?></option>
                            <?php endforeach;
                        endif; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ( 'yes' === ( $s['show_date_field'] ?? '' ) ) : ?>
                <div class="ovr-search-divider"></div>
                <div class="ovr-search-field">
                    <span class="ovr-search-field-label"><?php esc_html_e( 'Check In', 'ovr-core' ); ?></span>
                    <input type="date" name="checkin">
                </div>
            <?php endif; ?>

            <?php if ( 'yes' === ( $s['show_guests_field'] ?? '' ) ) : ?>
                <div class="ovr-search-divider"></div>
                <div class="ovr-search-field">
                    <span class="ovr-search-field-label"><?php esc_html_e( 'Guests', 'ovr-core' ); ?></span>
                    <input type="number" name="guests" min="1" max="20" placeholder="<?php esc_attr_e( 'Add guests', 'ovr-core' ); ?>">
                </div>
            <?php endif; ?>

            <button type="submit" class="ovr-btn ovr-btn-primary ovr-search-submit"
                    aria-label="<?php esc_attr_e( 'Search', 'ovr-core' ); ?>">
                <span class="material-symbols-outlined">search</span>
                <?php if ( 'yes' === ( $s['show_button_label'] ?? '' ) && ! empty( $s['button_label'] ) ) : ?>
                    <span class="ovr-search-submit-text" style="margin-left:6px"><?php echo esc_html( $s['button_label'] ); ?></span>
                <?php endif; ?>
            </button>
        </form>
        </div>
        <?php
    }
}
