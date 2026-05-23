<?php
/**
 * Elementor Pricing Table Widget.
 *
 * Displays OVR subscription plans in an Elementor-configurable layout.
 *
 * @package OVR\Elementor\Widgets
 * @since   1.0.0
 */

namespace OVR\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use OVR\Subscription\Plans;
use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PricingTableWidget extends Widget_Base {

    public function get_name(): string {
        return 'ovr_pricing_table';
    }

    public function get_title(): string {
        return esc_html__( 'OVR Pricing Plans', 'ovr-core' );
    }

    public function get_icon(): string {
        return 'eicon-price-table';
    }

    public function get_categories(): array {
        return [ 'ovr-widgets' ];
    }

    public function get_keywords(): array {
        return [ 'pricing', 'plans', 'subscription', 'ovr' ];
    }

    protected function register_controls(): void {

        $this->start_controls_section( 'content_section', [
            'label' => esc_html__( 'Display Settings', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'show_header', [
            'label'        => esc_html__( 'Show Section Header', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'header_title', [
            'label'     => esc_html__( 'Header Title', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Choose Your Plan', 'ovr-core' ),
            'condition' => [ 'show_header' => 'yes' ],
        ] );

        $this->add_control( 'header_subtitle', [
            'label'     => esc_html__( 'Header Subtitle', 'ovr-core' ),
            'type'      => Controls_Manager::TEXTAREA,
            'default'   => esc_html__( 'Start free and scale as your portfolio grows.', 'ovr-core' ),
            'condition' => [ 'show_header' => 'yes' ],
        ] );

        $this->add_control( 'show_promo', [
            'label'        => esc_html__( 'Show Promo Code Input', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        // Plan visibility toggles.
        $all_plans = Plans::get_plans();
        foreach ( $all_plans as $slug => $plan ) {
            $this->add_control( 'show_plan_' . $slug, [
                'label'        => sprintf( esc_html__( 'Show: %s', 'ovr-core' ), $plan['name'] ),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ] );
        }

        $this->end_controls_section();

        /* ── Style ── */
        $this->start_controls_section( 'style_section', [
            'label' => esc_html__( 'Card Style', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'popular_border_color', [
            'label'     => esc_html__( 'Popular Plan Border', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#006c4a',
            'selectors' => [
                '{{WRAPPER}} .ovr-pricing-card.is-popular' => 'border-color: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'button_color', [
            'label'     => esc_html__( 'Button Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#006666',
            'selectors' => [
                '{{WRAPPER}} .ovr-btn-primary' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .ovr-btn-outline' => 'border-color: {{VALUE}}; color: {{VALUE}};',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings  = $this->get_settings_for_display();
        $all_plans = Plans::get_plans();
        $sym       = get_option( 'ovr_settings', [] )['currency_symbol'] ?? '$';

        // Filter visible plans.
        $plans = [];
        foreach ( $all_plans as $slug => $plan ) {
            if ( 'yes' === ( $settings[ 'show_plan_' . $slug ] ?? 'yes' ) && ! empty( $plan['is_active'] ) ) {
                $plans[ $slug ] = $plan;
            }
        }

        uasort( $plans, fn( $a, $b ) => ( $a['sort_order'] ?? 0 ) <=> ( $b['sort_order'] ?? 0 ) );

        echo '<div class="ovr-wrap">';

        // Header.
        if ( 'yes' === $settings['show_header'] ) {
            echo '<div style="text-align:center;max-width:640px;margin:0 auto 48px">';
            echo '<p class="ovr-label-caps" style="color:var(--ovr-primary);margin-bottom:8px">' . esc_html__( 'PRICING', 'ovr-core' ) . '</p>';
            echo '<h2 class="ovr-h1" style="margin-bottom:16px">' . esc_html( $settings['header_title'] ) . '</h2>';
            echo '<p class="ovr-body-lg" style="color:var(--ovr-on-surface-variant)">' . esc_html( $settings['header_subtitle'] ) . '</p>';
            echo '</div>';
        }

        // Cards.
        echo '<div class="ovr-pricing-grid">';
        foreach ( $plans as $plan ) {
            $is_popular = ! empty( $plan['is_popular'] );
            $is_free    = 0 == $plan['price'];

            echo '<div class="ovr-pricing-card' . ( $is_popular ? ' is-popular' : '' ) . '">';

            if ( $is_popular ) {
                echo '<div class="ovr-pricing-popular-badge">' . esc_html__( 'Most Popular', 'ovr-core' ) . '</div>';
            }

            echo '<div class="ovr-pricing-name">' . esc_html( $plan['name'] ) . '</div>';

            echo '<div class="ovr-pricing-price">';
            if ( $is_free ) {
                echo '<span class="ovr-pricing-amount">' . esc_html__( 'Free', 'ovr-core' ) . '</span>';
            } else {
                echo '<span class="ovr-pricing-amount">' . esc_html( $sym . number_format( $plan['price'], 0 ) ) . '</span>';
                echo '<span class="ovr-pricing-period">/' . esc_html( $plan['period'] ) . '</span>';
            }
            echo '</div>';

            echo '<p class="ovr-pricing-desc">' . esc_html( $plan['description'] ) . '</p>';

            echo '<ul class="ovr-pricing-features">';
            foreach ( $plan['features'] as $feat ) {
                echo '<li><span class="material-symbols-outlined">check_circle</span>' . esc_html( $feat ) . '</li>';
            }
            echo '</ul>';

            $btn_class = $is_popular ? 'ovr-btn-secondary' : 'ovr-btn-outline';
            echo '<a href="' . esc_url( Pages::get_page_url( 'ovr_page_register' ) ) . '" class="ovr-btn ' . $btn_class . ' ovr-btn-full ovr-btn-pill">' . esc_html__( 'Get Started', 'ovr-core' ) . '</a>';

            echo '</div>'; // .ovr-pricing-card
        }
        echo '</div>'; // .ovr-pricing-grid

        // Promo code.
        if ( 'yes' === $settings['show_promo'] ) {
            echo '<div style="text-align:center;margin-top:48px">';
            echo '<div style="display:inline-flex;align-items:center;gap:12px;background:var(--ovr-surface-container-lowest);border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-full);padding:4px 4px 4px 20px;box-shadow:var(--ovr-shadow-sm)">';
            echo '<span class="material-symbols-outlined" style="color:var(--ovr-tertiary-container);font-size:20px">confirmation_number</span>';
            echo '<input type="text" id="ovr-promo-input" placeholder="' . esc_attr__( 'Enter promo code', 'ovr-core' ) . '" style="border:none;background:transparent;font-family:var(--ovr-font);font-size:16px;outline:none;width:180px;color:var(--ovr-on-surface)">';
            echo '<button id="ovr-promo-apply" class="ovr-btn ovr-btn-primary ovr-btn-pill" style="padding:10px 20px">' . esc_html__( 'Apply', 'ovr-core' ) . '</button>';
            echo '</div>';
            echo '<div id="ovr-promo-msg" style="margin-top:12px;font-size:14px" aria-live="polite"></div>';
            echo '</div>';
        }

        echo '</div>'; // .ovr-wrap
    }
}
