<?php
/**
 * Elementor Property Card Grid Widget.
 *
 * Displays a configurable grid of property cards with filtering options.
 *
 * @package OVR\Elementor\Widgets
 * @since   1.0.0
 */

namespace OVR\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use OVR\Property\PropertyQuery;
use OVR\Property\PropertyCard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PropertyCardWidget extends Widget_Base {

    public function get_name(): string {
        return 'ovr_property_cards';
    }

    public function get_title(): string {
        return esc_html__( 'OVR Property Cards', 'ovr-core' );
    }

    public function get_icon(): string {
        return 'eicon-gallery-grid';
    }

    public function get_categories(): array {
        return [ 'ovr-widgets' ];
    }

    public function get_keywords(): array {
        return [ 'property', 'listing', 'rental', 'card', 'grid', 'ovr' ];
    }

    protected function register_controls(): void {

        /* ── Content Section ── */
        $this->start_controls_section( 'content_section', [
            'label' => esc_html__( 'Query Settings', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'posts_per_page', [
            'label'   => esc_html__( 'Number of Properties', 'ovr-core' ),
            'type'    => Controls_Manager::NUMBER,
            'default' => 6,
            'min'     => 1,
            'max'     => 24,
        ] );

        $this->add_control( 'columns', [
            'label'   => esc_html__( 'Columns', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '3',
            'options' => [
                '2' => '2',
                '3' => '3',
                '4' => '4',
            ],
        ] );

        // Source: where the listings come from. "Homepage Slider" is driven by
        // the per-listing Homepage Slider boost (see UpgradeActivator) — the same
        // boost a landlord buys + pays for. Falls back to newest so the rail is
        // never empty during a demo or between boosts.
        $this->add_control( 'source', [
            'label'   => esc_html__( 'Source', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'standard',
            'options' => [
                'standard' => esc_html__( 'Standard query (filters below)', 'ovr-core' ),
                'featured' => esc_html__( 'Featured boost only', 'ovr-core' ),
                'slider'   => esc_html__( 'Homepage Slider boost', 'ovr-core' ),
            ],
            'description' => esc_html__( 'Homepage Slider shows listings with an active, paid Homepage Slider boost (newest published as fallback).', 'ovr-core' ),
        ] );

        $this->add_control( 'featured_only', [
            'label'        => esc_html__( 'Featured Only', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => esc_html__( 'Yes', 'ovr-core' ),
            'label_off'    => esc_html__( 'No', 'ovr-core' ),
            'return_value' => 'yes',
            'default'      => '',
            'condition'    => [ 'source' => 'standard' ],
        ] );

        // Village filter.
        $villages = get_terms( [ 'taxonomy' => 'ovr_village', 'hide_empty' => false ] );
        $village_opts = [ '' => esc_html__( 'All Villages', 'ovr-core' ) ];
        if ( ! is_wp_error( $villages ) ) {
            foreach ( $villages as $v ) {
                $village_opts[ $v->slug ] = $v->name;
            }
        }

        $this->add_control( 'village', [
            'label'   => esc_html__( 'Filter by Village', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $village_opts,
        ] );

        // Property type filter.
        $types = get_terms( [ 'taxonomy' => 'ovr_property_type', 'hide_empty' => false ] );
        $type_opts = [ '' => esc_html__( 'All Types', 'ovr-core' ) ];
        if ( ! is_wp_error( $types ) ) {
            foreach ( $types as $t ) {
                $type_opts[ $t->slug ] = $t->name;
            }
        }

        $this->add_control( 'property_type', [
            'label'   => esc_html__( 'Filter by Type', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $type_opts,
        ] );

        $this->add_control( 'sort', [
            'label'   => esc_html__( 'Sort By', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'newest',
            'options' => [
                'newest'     => esc_html__( 'Newest First', 'ovr-core' ),
                'price_low'  => esc_html__( 'Price: Low to High', 'ovr-core' ),
                'price_high' => esc_html__( 'Price: High to Low', 'ovr-core' ),
                'rating'     => esc_html__( 'Top Rated', 'ovr-core' ),
            ],
        ] );

        $this->end_controls_section();

        /* ── Style Section ── */
        $this->start_controls_section( 'style_section', [
            'label' => esc_html__( 'Card Style', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'image_height', [
            'label'      => esc_html__( 'Image Height', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 120, 'max' => 500 ] ],
            'default'    => [ 'size' => 240, 'unit' => 'px' ],
            'selectors'  => [
                '{{WRAPPER}} .ovr-property-card .ovr-card-image' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->add_control( 'elements_heading', [
            'label'     => esc_html__( 'Show / Hide Elements', 'ovr-core' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ] );

        $this->add_control( 'show_badges', [
            'label'        => esc_html__( 'Featured Badge', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'show_favorite', [
            'label'        => esc_html__( 'Favorite Button', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'show_id', [
            'label'        => esc_html__( 'Listing ID', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'show_compare', [
            'label'        => esc_html__( 'Compare Checkbox', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'show_location', [
            'label'        => esc_html__( 'Location', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'show_stats', [
            'label'        => esc_html__( 'Beds / Baths / SqFt', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'show_rates', [
            'label'        => esc_html__( 'Rates Note', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'show_button', [
            'label'        => esc_html__( 'Action Button', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ] );

        $this->add_control( 'card_gap', [
            'label'     => esc_html__( 'Card Gap', 'ovr-core' ),
            'type'      => Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 8, 'max' => 48 ] ],
            'default'   => [ 'size' => 24, 'unit' => 'px' ],
            'selectors' => [
                '{{WRAPPER}} .ovr-grid' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $count  = absint( $settings['posts_per_page'] );
        $source = $settings['source'] ?? 'standard';

        if ( 'slider' === $source ) {
            // Homepage Slider boost set (manual override → active boosts →
            // featured fallback, all inside get_slider). If that resolves to
            // nothing, fall back to newest published so the rail still shows
            // listings for the demo / between boosts.
            $query = PropertyQuery::get_slider( $count );
            if ( ! $query->have_posts() ) {
                $query = PropertyQuery::query( [ 'per_page' => $count, 'sort' => 'newest' ] );
            }
        } else {
            $filters = [
                'per_page'      => $count,
                'featured_only' => ( 'featured' === $source ) || 'yes' === $settings['featured_only'],
                'sort'          => $settings['sort'],
            ];

            if ( ! empty( $settings['village'] ) ) {
                $filters['village'] = [ $settings['village'] ];
            }

            if ( ! empty( $settings['property_type'] ) ) {
                $filters['property_type'] = [ $settings['property_type'] ];
            }

            $query = PropertyQuery::query( $filters );
        }

        if ( ! $query->have_posts() ) {
            echo '<div class="ovr-wrap" style="text-align:center;padding:40px">';
            echo '<span class="material-symbols-outlined" style="font-size:48px;color:var(--ovr-outline-variant)">search_off</span>';
            echo '<p style="color:var(--ovr-on-surface-variant);margin-top:12px">' . esc_html__( 'No properties found.', 'ovr-core' ) . '</p>';
            echo '</div>';
            return;
        }

        $card_options = [
            'show_featured_badge' => 'yes' === ( $settings['show_badges']  ?? 'yes' ),
            'show_favorite'       => 'yes' === ( $settings['show_favorite'] ?? 'yes' ),
            'show_id'             => 'yes' === ( $settings['show_id']       ?? 'yes' ),
            'show_compare'        => 'yes' === ( $settings['show_compare']  ?? 'yes' ),
            'show_location'       => 'yes' === ( $settings['show_location'] ?? 'yes' ),
            'show_stats'          => 'yes' === ( $settings['show_stats']    ?? 'yes' ),
            'show_rates'          => 'yes' === ( $settings['show_rates']    ?? 'yes' ),
            'show_button'         => 'yes' === ( $settings['show_button']   ?? 'yes' ),
        ];

        $cols = absint( $settings['columns'] );
        echo '<div class="ovr-wrap"><div class="ovr-grid ovr-grid-' . esc_attr( $cols ) . '">';

        while ( $query->have_posts() ) {
            $query->the_post();
            echo PropertyCard::render_grid( get_the_ID(), $card_options );
        }
        wp_reset_postdata();

        echo '</div></div>';
    }
}
