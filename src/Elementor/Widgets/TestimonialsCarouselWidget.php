<?php
/**
 * OVR Testimonials Carousel Elementor Widget.
 *
 * A sliding carousel that pulls testimonials from a central store rather than
 * a per-instance repeater. Source is selectable:
 *
 *   - "testimonial"  manually-entered Testimonials (ovr_testimonial CPT)
 *   - "review"       approved property reviews (wp_ovr_reviews)
 *   - "both"         combined, strongest social proof first
 *
 * All items are gated by a minimum star rating (reputation management) — set
 * globally in OVR Settings → Reputation, or overridden per widget.
 *
 * @package OVR\Elementor\Widgets
 * @since   1.1.0
 */

namespace OVR\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use OVR\Testimonials\TestimonialRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TestimonialsCarouselWidget extends Widget_Base {

    /** @var bool Whether structural CSS has been printed this request. */
    private static bool $css_printed = false;

    public function get_name(): string      { return 'ovr_testimonials_carousel'; }
    public function get_title(): string     { return esc_html__( 'OVR Testimonials Carousel', 'ovr-core' ); }
    public function get_icon(): string      { return 'eicon-testimonial-carousel'; }
    public function get_categories(): array { return [ 'ovr-widgets' ]; }
    public function get_keywords(): array   { return [ 'testimonials', 'reviews', 'carousel', 'slider', 'quotes', 'ovr' ]; }
    public function get_script_depends(): array { return [ 'ovr-testimonials' ]; }

    protected function register_controls(): void {

        /* ============================================================
           CONTENT — Source
           ============================================================ */
        $this->start_controls_section( 'section_source', [
            'label' => esc_html__( 'Source', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'source', [
            'label'   => esc_html__( 'Pull testimonials from', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'both',
            'options' => [
                'both'        => esc_html__( 'Both (testimonials + reviews)', 'ovr-core' ),
                'testimonial' => esc_html__( 'Manual testimonials only', 'ovr-core' ),
                'review'      => esc_html__( 'Approved property reviews only', 'ovr-core' ),
            ],
        ] );

        $this->add_control( 'min_rating', [
            'label'       => esc_html__( 'Minimum Rating', 'ovr-core' ),
            'type'        => Controls_Manager::SELECT,
            'default'     => '0',
            'options'     => [
                '0' => esc_html__( 'Use global setting', 'ovr-core' ),
                '1' => esc_html__( '1 star & up', 'ovr-core' ),
                '2' => esc_html__( '2 stars & up', 'ovr-core' ),
                '3' => esc_html__( '3 stars & up', 'ovr-core' ),
                '4' => esc_html__( '4 stars & up', 'ovr-core' ),
                '5' => esc_html__( '5 stars only', 'ovr-core' ),
            ],
            'description' => esc_html__( 'Reputation gate. "Use global setting" follows OVR Settings → Reputation.', 'ovr-core' ),
        ] );

        $this->add_control( 'limit', [
            'label'   => esc_html__( 'Max Testimonials', 'ovr-core' ),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 50,
            'default' => 12,
        ] );

        $this->add_control( 'heading', [
            'label'   => esc_html__( 'Section Title', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__( 'What Our Guests Say', 'ovr-core' ),
        ] );

        $this->add_control( 'subheading', [
            'label'   => esc_html__( 'Section Subtitle', 'ovr-core' ),
            'type'    => Controls_Manager::TEXTAREA,
            'rows'    => 2,
            'default' => esc_html__( 'Real stays, real reviews from the community.', 'ovr-core' ),
        ] );

        $this->add_control( 'show_section_header', [
            'label'   => esc_html__( 'Show Title & Subtitle', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->end_controls_section();

        /* ============================================================
           LAYOUT / CAROUSEL BEHAVIOUR
           ============================================================ */
        $this->start_controls_section( 'section_layout', [
            'label' => esc_html__( 'Carousel', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_responsive_control( 'per_view', [
            'label'          => esc_html__( 'Cards Per View', 'ovr-core' ),
            'type'           => Controls_Manager::SELECT,
            'default'        => '3',
            'tablet_default' => '2',
            'mobile_default' => '1',
            'options'        => [ '1' => '1', '2' => '2', '3' => '3', '4' => '4' ],
            'selectors'      => [ '{{WRAPPER}} .ovr-tc' => '--ovr-tc-per:{{VALUE}}' ],
        ] );

        $this->add_responsive_control( 'gap', [
            'label'      => esc_html__( 'Gap Between Cards', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 8, 'max' => 60 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 24 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-tc-track' => 'gap:{{SIZE}}{{UNIT}}' ],
        ] );

        $this->add_control( 'autoplay', [
            'label'   => esc_html__( 'Autoplay', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'autoplay_speed', [
            'label'     => esc_html__( 'Autoplay Delay (ms)', 'ovr-core' ),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1500,
            'max'       => 12000,
            'step'      => 250,
            'default'   => 5000,
            'condition' => [ 'autoplay' => 'yes' ],
        ] );

        $this->add_control( 'loop', [
            'label'   => esc_html__( 'Loop', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'show_arrows', [
            'label'   => esc_html__( 'Show Arrows', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'show_dots', [
            'label'   => esc_html__( 'Show Dots', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'alignment', [
            'label'     => esc_html__( 'Card Text Align', 'ovr-core' ),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'left'   => [ 'title' => esc_html__( 'Left', 'ovr-core' ),   'icon' => 'eicon-text-align-left' ],
                'center' => [ 'title' => esc_html__( 'Center', 'ovr-core' ), 'icon' => 'eicon-text-align-center' ],
                'right'  => [ 'title' => esc_html__( 'Right', 'ovr-core' ),  'icon' => 'eicon-text-align-right' ],
            ],
            'default'   => 'center',
            'selectors' => [
                '{{WRAPPER}} .ovr-tc-card'   => 'text-align:{{VALUE}}',
                '{{WRAPPER}} .ovr-tc-person' => 'justify-content:{{VALUE}}',
                '{{WRAPPER}} .ovr-tc-stars'  => 'justify-content:{{VALUE}}',
            ],
        ] );

        $this->add_control( 'avatar_shape', [
            'label'   => esc_html__( 'Avatar Shape', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'circle',
            'options' => [
                'circle' => esc_html__( 'Circle', 'ovr-core' ),
                'square' => esc_html__( 'Square (rounded)', 'ovr-core' ),
                'none'   => esc_html__( 'No avatar', 'ovr-core' ),
            ],
        ] );

        $this->add_control( 'card_layout', [
            'label'   => esc_html__( 'Card Layout', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'centered',
            'options' => [
                'centered' => esc_html__( 'Centered (avatar on top)', 'ovr-core' ),
                'classic'  => esc_html__( 'Classic (avatar at bottom)', 'ovr-core' ),
            ],
            'description' => esc_html__( 'Centered matches the "What our owners and renters say" style.', 'ovr-core' ),
        ] );

        $this->add_control( 'quote_lines', [
            'label'       => esc_html__( 'Quote Lines Before "Read more"', 'ovr-core' ),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 0,
            'max'         => 20,
            'default'     => 5,
            'description' => esc_html__( '0 = never clamp (always show the full quote).', 'ovr-core' ),
        ] );

        $this->add_control( 'peek_fade', [
            'label'       => esc_html__( 'Fade Side Cards', 'ovr-core' ),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => esc_html__( 'Soft gradient over the carousel edges so neighbouring cards fade out.', 'ovr-core' ),
        ] );

        $this->add_control( 'peek_fade_color', [
            'label'     => esc_html__( 'Fade / Section Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'condition' => [ 'peek_fade' => 'yes' ],
            'description' => esc_html__( 'Set this to the section background behind the carousel (e.g. your navy).', 'ovr-core' ),
            'selectors' => [ '{{WRAPPER}} .ovr-tc-viewport' => '--tc-fade:{{VALUE}}' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Section Header
           ============================================================ */
        $this->start_controls_section( 'style_header', [
            'label'     => esc_html__( 'Section Header', 'ovr-core' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_section_header' => 'yes' ],
        ] );

        $this->add_control( 'heading_color', [
            'label'     => esc_html__( 'Title Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#181c1c',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-heading' => 'color:{{VALUE}}' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'heading_typography',
            'selector' => '{{WRAPPER}} .ovr-tc-heading',
        ] );

        $this->add_control( 'subheading_color', [
            'label'     => esc_html__( 'Subtitle Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#3f4948',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-sub' => 'color:{{VALUE}}' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Card
           ============================================================ */
        $this->start_controls_section( 'style_card', [
            'label' => esc_html__( 'Card', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'card_bg', [
            'label'     => esc_html__( 'Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-card' => 'background:{{VALUE}}' ],
        ] );

        $this->add_control( 'card_radius', [
            'label'      => esc_html__( 'Border Radius', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 14 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-tc-card' => 'border-radius:{{SIZE}}{{UNIT}}' ],
        ] );

        $this->add_responsive_control( 'card_padding', [
            'label'      => esc_html__( 'Padding', 'ovr-core' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-tc-card' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' ],
        ] );

        $this->add_group_control( Group_Control_Border::get_type(), [
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .ovr-tc-card',
        ] );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .ovr-tc-card',
            'fields_options' => [
                'box_shadow_type' => [ 'default' => 'yes' ],
                'box_shadow'      => [ 'default' => [ 'horizontal' => 0, 'vertical' => 8, 'blur' => 24, 'spread' => 0, 'color' => 'rgba(0,0,0,0.08)' ] ],
            ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Quote
           ============================================================ */
        $this->start_controls_section( 'style_quote', [
            'label' => esc_html__( 'Quote', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'quote_color', [
            'label'     => esc_html__( 'Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#3f4948',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-quote' => 'color:{{VALUE}}' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'quote_typography',
            'selector' => '{{WRAPPER}} .ovr-tc-quote',
        ] );

        $this->add_control( 'show_quote_marks', [
            'label'   => esc_html__( 'Show Decorative Quote Mark', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'quote_mark_color', [
            'label'     => esc_html__( 'Quote Mark Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#cca72f',
            'condition' => [ 'show_quote_marks' => 'yes' ],
            'selectors' => [ '{{WRAPPER}} .ovr-tc-mark' => 'color:{{VALUE}}' ],
        ] );

        $this->add_control( 'readmore_color', [
            'label'     => esc_html__( '"Read more" Link Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#1466b8',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-readmore' => 'color:{{VALUE}}' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Stars
           ============================================================ */
        $this->start_controls_section( 'style_stars', [
            'label' => esc_html__( 'Star Rating', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'show_stars', [
            'label'   => esc_html__( 'Show Stars', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'star_color', [
            'label'     => esc_html__( 'Filled Star Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#cca72f',
            'condition' => [ 'show_stars' => 'yes' ],
            'selectors' => [ '{{WRAPPER}} .ovr-tc-star.is-on' => 'color:{{VALUE}}' ],
        ] );

        $this->add_control( 'star_empty_color', [
            'label'     => esc_html__( 'Empty Star Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#bec9c8',
            'condition' => [ 'show_stars' => 'yes' ],
            'selectors' => [ '{{WRAPPER}} .ovr-tc-star.is-off' => 'color:{{VALUE}}' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Person
           ============================================================ */
        $this->start_controls_section( 'style_person', [
            'label' => esc_html__( 'Person', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_responsive_control( 'avatar_size', [
            'label'      => esc_html__( 'Avatar Size', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 32, 'max' => 96 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 48 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-tc-avatar' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}}' ],
        ] );

        $this->add_control( 'name_color', [
            'label'     => esc_html__( 'Name Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#181c1c',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-name' => 'color:{{VALUE}}' ],
        ] );

        $this->add_control( 'role_color', [
            'label'     => esc_html__( 'Role Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#6f7979',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-role' => 'color:{{VALUE}}' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Arrows & Dots
           ============================================================ */
        $this->start_controls_section( 'style_nav', [
            'label' => esc_html__( 'Arrows & Dots', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'arrow_color', [
            'label'     => esc_html__( 'Arrow Icon Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#181c1c',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-arrow' => 'color:{{VALUE}}' ],
        ] );

        $this->add_control( 'arrow_bg', [
            'label'     => esc_html__( 'Arrow Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-arrow' => 'background:{{VALUE}}' ],
        ] );

        $this->add_control( 'dot_color', [
            'label'     => esc_html__( 'Dot Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#bec9c8',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-dot' => 'background:{{VALUE}}' ],
        ] );

        $this->add_control( 'dot_active_color', [
            'label'     => esc_html__( 'Active Dot Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#006666',
            'selectors' => [ '{{WRAPPER}} .ovr-tc-dot.is-active' => 'background:{{VALUE}}' ],
        ] );

        $this->end_controls_section();
    }

    /**
     * Render on the front-end.
     */
    protected function render(): void {
        $s = $this->get_settings_for_display();

        $source     = in_array( ( $s['source'] ?? 'both' ), [ 'both', 'testimonial', 'review' ], true ) ? $s['source'] : 'both';
        $limit      = max( 1, (int) ( $s['limit'] ?? 12 ) );
        $min_rating = (int) ( $s['min_rating'] ?? 0 );

        $items = TestimonialRepository::get( $source, $limit, $min_rating );

        // Editor placeholder when nothing matches yet.
        if ( empty( $items ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:32px;text-align:center;border:1px dashed #bec9c8;border-radius:12px;color:#6f7979;font-family:Inter,sans-serif">'
                    . esc_html__( 'No testimonials match the current source / rating filter yet. Add testimonials under the Testimonials menu, or approve some property reviews.', 'ovr-core' )
                    . '</div>';
            }
            return;
        }

        $shape           = $s['avatar_shape'] ?? 'circle';
        $layout          = ( $s['card_layout'] ?? 'centered' ) === 'classic' ? 'classic' : 'centered';
        $show_stars      = ( $s['show_stars'] ?? 'yes' ) === 'yes';
        $show_quote_mark = ( $s['show_quote_marks'] ?? 'yes' ) === 'yes';
        $show_header     = ( $s['show_section_header'] ?? 'yes' ) === 'yes';
        $show_arrows     = ( $s['show_arrows'] ?? 'yes' ) === 'yes';
        $show_dots       = ( $s['show_dots'] ?? 'yes' ) === 'yes';
        $autoplay        = ( $s['autoplay'] ?? 'yes' ) === 'yes';
        $loop            = ( $s['loop'] ?? 'yes' ) === 'yes';
        $interval        = max( 1500, (int) ( $s['autoplay_speed'] ?? 5000 ) );
        $quote_lines     = max( 0, (int) ( $s['quote_lines'] ?? 5 ) );
        $fade            = ( $s['peek_fade'] ?? 'yes' ) === 'yes';

        $avatar_radius = 'circle' === $shape ? '50%' : ( 'square' === $shape ? '10px' : '0' );
        $read_more_lbl = esc_html__( 'Read more', 'ovr-core' );
        $read_less_lbl = esc_html__( 'Read less', 'ovr-core' );

        $this->print_structural_css();
        ?>
        <div class="ovr-tc ovr-tc--<?php echo esc_attr( $layout ); ?><?php echo $fade ? ' ovr-tc--fade' : ''; ?>"
             data-ovr-carousel
             data-autoplay="<?php echo $autoplay ? '1' : '0'; ?>"
             data-interval="<?php echo esc_attr( (string) $interval ); ?>"
             data-loop="<?php echo $loop ? '1' : '0'; ?>"
             tabindex="0"
             role="region"
             aria-roledescription="carousel"
             aria-label="<?php esc_attr_e( 'Testimonials', 'ovr-core' ); ?>">

            <?php if ( $show_header && ( ! empty( $s['heading'] ) || ! empty( $s['subheading'] ) ) ) : ?>
                <header class="ovr-tc-header">
                    <?php if ( ! empty( $s['heading'] ) ) : ?>
                        <h2 class="ovr-tc-heading"><?php echo esc_html( $s['heading'] ); ?></h2>
                    <?php endif; ?>
                    <?php if ( ! empty( $s['subheading'] ) ) : ?>
                        <p class="ovr-tc-sub"><?php echo esc_html( $s['subheading'] ); ?></p>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <div class="ovr-tc-stage">
                <?php if ( $show_arrows ) : ?>
                    <button type="button" class="ovr-tc-arrow ovr-tc-prev" aria-label="<?php esc_attr_e( 'Previous', 'ovr-core' ); ?>">
                        <span aria-hidden="true">&#8249;</span>
                    </button>
                <?php endif; ?>

                <div class="ovr-tc-viewport">
                    <div class="ovr-tc-track">
                        <?php foreach ( $items as $item ) :
                            $rating  = max( 0, min( 5, (int) ( $item['rating'] ?? 5 ) ) );
                            $avatar  = (string) ( $item['avatar'] ?? '' );
                            $name    = (string) ( $item['name'] ?? '' );
                            $role    = (string) ( $item['role'] ?? '' );
                            $purl    = (string) ( $item['property_url'] ?? '' );
                            $ptitle  = (string) ( $item['property_title'] ?? '' );
                            $initial = strtoupper( mb_substr( $name ?: '?', 0, 1 ) );

                            // Avatar markup (shared between layouts).
                            ob_start();
                            if ( 'none' !== $shape ) {
                                if ( $avatar ) {
                                    printf(
                                        '<img class="ovr-tc-avatar" src="%s" alt="" style="border-radius:%s">',
                                        esc_url( $avatar ),
                                        esc_attr( $avatar_radius )
                                    );
                                } else {
                                    printf(
                                        '<div class="ovr-tc-avatar ovr-tc-avatar-fallback" style="border-radius:%s">%s</div>',
                                        esc_attr( $avatar_radius ),
                                        esc_html( $initial )
                                    );
                                }
                            }
                            $avatar_html = ob_get_clean();
                        ?>
                            <article class="ovr-tc-card">
                                <?php if ( $show_quote_mark ) : ?>
                                    <span class="ovr-tc-mark" aria-hidden="true">&ldquo;</span>
                                <?php endif; ?>

                                <?php if ( 'centered' === $layout && $avatar_html ) : ?>
                                    <div class="ovr-tc-avatar-top"><?php echo $avatar_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                <?php endif; ?>

                                <?php if ( $show_stars && $rating > 0 ) : ?>
                                    <?php // role="img" is required for aria-label to be permitted on a
                                          // div (axe `aria-prohibited-attr`); the stars are a graphic
                                          // whose meaning the label conveys. ?>
                                    <div class="ovr-tc-stars" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: rating */ __( 'Rated %d out of 5', 'ovr-core' ), $rating ) ); ?>">
                                        <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                            <span class="ovr-tc-star <?php echo $i <= $rating ? 'is-on' : 'is-off'; ?>" aria-hidden="true">&#9733;</span>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>

                                <p class="ovr-tc-quote<?php echo $quote_lines ? ' is-clamped' : ''; ?>"
                                   <?php if ( $quote_lines ) : ?>style="--tc-lines:<?php echo (int) $quote_lines; ?>"<?php endif; ?>><?php echo esc_html( (string) ( $item['quote'] ?? '' ) ); ?></p>

                                <?php if ( $quote_lines ) : ?>
                                    <button type="button" class="ovr-tc-readmore" hidden
                                            data-more="<?php echo esc_attr( $read_more_lbl ); ?>"
                                            data-less="<?php echo esc_attr( $read_less_lbl ); ?>"><?php echo esc_html( $read_more_lbl ); ?></button>
                                <?php endif; ?>

                                <?php if ( 'centered' === $layout ) : ?>
                                    <p class="ovr-tc-attr">
                                        <?php echo '— ' . esc_html( $name ); ?><?php
                                        if ( $purl && $ptitle ) {
                                            echo '. <a class="ovr-tc-attr-link" href="' . esc_url( $purl ) . '">' . esc_html( $ptitle ) . '</a>';
                                        } elseif ( $role ) {
                                            echo '. <span class="ovr-tc-attr-role">' . esc_html( $role ) . '</span>';
                                        }
                                        ?>
                                    </p>
                                <?php else : ?>
                                    <div class="ovr-tc-person">
                                        <?php echo $avatar_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <div class="ovr-tc-who">
                                            <div class="ovr-tc-name"><?php echo esc_html( $name ); ?></div>
                                            <?php if ( $purl && $ptitle ) : ?>
                                                <div class="ovr-tc-role"><a class="ovr-tc-attr-link" href="<?php echo esc_url( $purl ); ?>"><?php echo esc_html( $ptitle ); ?></a></div>
                                            <?php elseif ( $role ) : ?>
                                                <div class="ovr-tc-role"><?php echo esc_html( $role ); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ( $show_arrows ) : ?>
                    <button type="button" class="ovr-tc-arrow ovr-tc-next" aria-label="<?php esc_attr_e( 'Next', 'ovr-core' ); ?>">
                        <span aria-hidden="true">&#8250;</span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ( $show_dots ) : ?>
                <div class="ovr-tc-dots" role="tablist" aria-label="<?php esc_attr_e( 'Carousel pagination', 'ovr-core' ); ?>"></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Print the structural CSS once per request. Colours/spacing come from the
     * Style controls (selectors above); this only handles layout & motion.
     */
    private function print_structural_css(): void {
        if ( self::$css_printed ) {
            return;
        }
        self::$css_printed = true;
        ?>
        <style id="ovr-tc-structural">
            .ovr-tc{--ovr-tc-per:3;font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;position:relative}
            .ovr-tc-header{text-align:center;margin-bottom:28px}
            .ovr-tc-heading{margin:0 0 8px;font-weight:700}
            .ovr-tc-sub{margin:0;font-size:15px}
            .ovr-tc-stage{display:flex;align-items:center;gap:8px}
            .ovr-tc-viewport{overflow:hidden;flex:1 1 auto;width:100%;position:relative}
            .ovr-tc-track{display:flex;gap:24px;will-change:transform;transition:transform 420ms cubic-bezier(.22,.61,.36,1)}
            .ovr-tc-card{flex:0 0 calc((100% - (var(--ovr-tc-per) - 1) * 24px) / var(--ovr-tc-per));box-sizing:border-box;position:relative;overflow:hidden;background:#fff;border-radius:14px;padding:24px;cursor:grab}
            .ovr-tc-track:active .ovr-tc-card{cursor:grabbing}
            .ovr-tc-mark{position:absolute;top:8px;right:16px;font-size:64px;line-height:1;font-family:Georgia,serif;font-weight:700;opacity:.25;color:#cca72f;pointer-events:none}
            .ovr-tc-stars{display:flex;gap:2px;margin-bottom:12px;justify-content:inherit}
            .ovr-tc-star{font-size:16px}
            .ovr-tc-star.is-on{color:#cca72f}
            .ovr-tc-star.is-off{color:#bec9c8}
            .ovr-tc-quote{margin:0 0 14px;line-height:1.6;font-size:15px;position:relative;z-index:1}
            .ovr-tc-quote.is-clamped{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:var(--tc-lines,5);overflow:hidden}
            .ovr-tc-quote.is-expanded{display:block;-webkit-line-clamp:unset;overflow:visible}
            .ovr-tc-readmore{display:inline-block;margin:0 0 10px;padding:0;border:0;background:none;font:inherit;font-weight:600;color:#1466b8;cursor:pointer}
            .ovr-tc-readmore:hover{text-decoration:underline}
            .ovr-tc-person{display:flex;align-items:center;gap:12px}
            .ovr-tc-avatar{width:48px;height:48px;object-fit:cover;flex-shrink:0}
            .ovr-tc-avatar-fallback{display:flex;align-items:center;justify-content:center;background:#006666;color:#fff;font-weight:600}
            .ovr-tc-name{font-weight:600;line-height:1.3}
            .ovr-tc-role{font-size:13px;line-height:1.3;margin-top:2px}
            .ovr-tc-role a,.ovr-tc-attr-link{color:inherit;text-decoration:underline}
            /* Centered layout (avatar on top, attribution below). */
            .ovr-tc--centered .ovr-tc-avatar-top{display:flex;justify-content:center;margin-bottom:16px}
            .ovr-tc--centered .ovr-tc-avatar{width:64px;height:64px}
            .ovr-tc-attr{margin:8px 0 0;font-size:14px;font-weight:600;color:#3f4948}
            /* Faded side cards. --tc-fade = section background colour. */
            .ovr-tc--fade .ovr-tc-viewport::before,
            .ovr-tc--fade .ovr-tc-viewport::after{content:"";position:absolute;top:0;bottom:0;width:7%;z-index:3;pointer-events:none}
            .ovr-tc--fade .ovr-tc-viewport::before{left:0;background:linear-gradient(to right,var(--tc-fade,#fff),transparent)}
            .ovr-tc--fade .ovr-tc-viewport::after{right:0;background:linear-gradient(to left,var(--tc-fade,#fff),transparent)}
            .ovr-tc-arrow{flex:0 0 auto;width:44px;height:44px;border:none;border-radius:50%;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.12);font-size:26px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .15s ease,opacity .15s ease}
            .ovr-tc-arrow:hover{transform:scale(1.08)}
            .ovr-tc-arrow:disabled{opacity:.35;cursor:default}
            .ovr-tc-dots{display:flex;justify-content:center;gap:8px;margin-top:22px}
            .ovr-tc-dot{width:9px;height:9px;border:none;border-radius:50%;background:#bec9c8;padding:0;cursor:pointer;transition:transform .15s ease,background .15s ease}
            .ovr-tc-dot.is-active{background:#006666;transform:scale(1.25)}
            @media (max-width:768px){.ovr-tc-arrow{display:none}}
        </style>
        <?php
    }
}
