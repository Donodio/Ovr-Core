<?php
/**
 * OVR Testimonials Elementor Widget.
 *
 * Displays a configurable grid of testimonials with rich styling controls.
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
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TestimonialsWidget extends Widget_Base {

    public function get_name(): string     { return 'ovr_testimonials'; }
    public function get_title(): string    { return esc_html__( 'OVR Testimonials', 'ovr-core' ); }
    public function get_icon(): string     { return 'eicon-testimonial-carousel'; }
    public function get_categories(): array{ return [ 'ovr-widgets' ]; }
    public function get_keywords(): array  { return [ 'testimonials', 'reviews', 'quotes', 'ovr' ]; }

    protected function register_controls(): void {

        /* ============================================================
           CONTENT — Testimonials repeater
           ============================================================ */
        $this->start_controls_section( 'section_content', [
            'label' => esc_html__( 'Testimonials', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $repeater = new Repeater();
        $repeater->add_control( 'name', [
            'label'   => esc_html__( 'Name', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Sarah Johnson',
        ] );
        $repeater->add_control( 'role', [
            'label'   => esc_html__( 'Role / Location', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Verified Guest · Oak Village',
        ] );
        $repeater->add_control( 'quote', [
            'label'   => esc_html__( 'Quote', 'ovr-core' ),
            'type'    => Controls_Manager::TEXTAREA,
            'rows'    => 4,
            'default' => 'The cabin was even better than the photos. Beautifully maintained, with everything we needed for a peaceful weekend.',
        ] );
        $repeater->add_control( 'rating', [
            'label'   => esc_html__( 'Rating (1–5)', 'ovr-core' ),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 5,
            'default' => 5,
        ] );
        $repeater->add_control( 'avatar', [
            'label' => esc_html__( 'Avatar', 'ovr-core' ),
            'type'  => Controls_Manager::MEDIA,
        ] );

        $this->add_control( 'testimonials', [
            'label'       => esc_html__( 'Items', 'ovr-core' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ name }}}',
            'default'     => [
                [ 'name' => 'Sarah Johnson',  'role' => 'Verified Guest · Oak Village',     'quote' => 'The cabin was even better than the photos.', 'rating' => 5 ],
                [ 'name' => 'Marcus Lee',     'role' => 'Property Owner · 5 listings',      'quote' => 'Listing was effortless and inquiries came in within 48 hours.', 'rating' => 5 ],
                [ 'name' => 'Emma Rodriguez', 'role' => 'Long-Term Tenant · Cedar Heights', 'quote' => 'Found a place I genuinely call home — pet-friendly and quiet.', 'rating' => 5 ],
            ],
        ] );

        $this->add_control( 'heading', [
            'label'       => esc_html__( 'Section Title', 'ovr-core' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => esc_html__( 'What Our Guests Say', 'ovr-core' ),
        ] );

        $this->add_control( 'subheading', [
            'label'       => esc_html__( 'Section Subtitle', 'ovr-core' ),
            'type'        => Controls_Manager::TEXTAREA,
            'rows'        => 2,
            'default'     => esc_html__( 'Real stays, real reviews from the community.', 'ovr-core' ),
        ] );

        $this->end_controls_section();

        /* ============================================================
           LAYOUT
           ============================================================ */
        $this->start_controls_section( 'section_layout', [
            'label' => esc_html__( 'Layout', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_responsive_control( 'columns', [
            'label'   => esc_html__( 'Columns', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '3',
            'tablet_default' => '2',
            'mobile_default' => '1',
            'options' => [ '1'=>'1', '2'=>'2', '3'=>'3', '4'=>'4' ],
            'selectors' => [
                '{{WRAPPER}} .ovr-tw-grid' => 'grid-template-columns:repeat({{VALUE}},1fr)',
            ],
        ] );

        $this->add_responsive_control( 'gap', [
            'label'   => esc_html__( 'Gap Between Cards', 'ovr-core' ),
            'type'    => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'   => [ 'px' => [ 'min' => 8, 'max' => 80 ] ],
            'default' => [ 'unit' => 'px', 'size' => 24 ],
            'selectors' => [ '{{WRAPPER}} .ovr-tw-grid' => 'gap:{{SIZE}}{{UNIT}}' ],
        ] );

        $this->add_control( 'alignment', [
            'label'   => esc_html__( 'Card Text Align', 'ovr-core' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'left'   => [ 'title'=>esc_html__('Left',   'ovr-core'), 'icon'=>'eicon-text-align-left' ],
                'center' => [ 'title'=>esc_html__('Center', 'ovr-core'), 'icon'=>'eicon-text-align-center' ],
                'right'  => [ 'title'=>esc_html__('Right',  'ovr-core'), 'icon'=>'eicon-text-align-right' ],
            ],
            'default' => 'left',
            'selectors' => [ '{{WRAPPER}} .ovr-tw-card' => 'text-align:{{VALUE}}' ],
        ] );

        $this->add_control( 'show_section_header', [
            'label'        => esc_html__( 'Show Title & Subtitle', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
        ] );

        $this->add_control( 'avatar_shape', [
            'label'   => esc_html__( 'Avatar Shape', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'circle',
            'options' => [
                'circle'  => esc_html__( 'Circle',           'ovr-core' ),
                'square'  => esc_html__( 'Square (rounded)', 'ovr-core' ),
                'none'    => esc_html__( 'No avatar',        'ovr-core' ),
            ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Section Header
           ============================================================ */
        $this->start_controls_section( 'style_section_header', [
            'label'     => esc_html__( 'Section Header', 'ovr-core' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_section_header' => 'yes' ],
        ] );

        $this->add_control( 'heading_color', [
            'label'   => esc_html__( 'Title Color', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#181c1c',
            'selectors' => [ '{{WRAPPER}} .ovr-tw-heading' => 'color:{{VALUE}}' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'heading_typography',
            'selector' => '{{WRAPPER}} .ovr-tw-heading',
        ] );

        $this->add_control( 'subheading_color', [
            'label'   => esc_html__( 'Subtitle Color', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#3f4948',
            'selectors' => [ '{{WRAPPER}} .ovr-tw-sub' => 'color:{{VALUE}}' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'subheading_typography',
            'selector' => '{{WRAPPER}} .ovr-tw-sub',
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
            'label'   => esc_html__( 'Background', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-tw-card' => 'background:{{VALUE}}' ],
        ] );

        $this->add_control( 'card_radius', [
            'label'   => esc_html__( 'Border Radius', 'ovr-core' ),
            'type'    => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'   => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'default' => [ 'unit' => 'px', 'size' => 14 ],
            'selectors' => [ '{{WRAPPER}} .ovr-tw-card' => 'border-radius:{{SIZE}}{{UNIT}}' ],
        ] );

        $this->add_responsive_control( 'card_padding', [
            'label'   => esc_html__( 'Padding', 'ovr-core' ),
            'type'    => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default' => [ 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'unit' => 'px' ],
            'selectors' => [ '{{WRAPPER}} .ovr-tw-card' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' ],
        ] );

        $this->add_group_control( Group_Control_Border::get_type(), [
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .ovr-tw-card',
        ] );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .ovr-tw-card',
        ] );

        $this->add_control( 'card_hover_lift', [
            'label'   => esc_html__( 'Hover Lift Effect', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
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
            'label'   => esc_html__( 'Color', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#3f4948',
            'selectors' => [ '{{WRAPPER}} .ovr-tw-quote' => 'color:{{VALUE}}' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'quote_typography',
            'selector' => '{{WRAPPER}} .ovr-tw-quote',
        ] );

        $this->add_control( 'show_quote_marks', [
            'label'   => esc_html__( 'Show Decorative Quote Mark', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'quote_mark_color', [
            'label'   => esc_html__( 'Quote Mark Color', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#cca72f',
            'condition' => [ 'show_quote_marks' => 'yes' ],
            'selectors' => [ '{{WRAPPER}} .ovr-tw-quote-mark' => 'color:{{VALUE}}' ],
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
            'label'   => esc_html__( 'Filled Star Color', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#cca72f',
            'condition' => [ 'show_stars' => 'yes' ],
            'selectors' => [ '{{WRAPPER}} .ovr-tw-star.is-on' => 'color:{{VALUE}}' ],
        ] );

        $this->add_control( 'star_empty_color', [
            'label'   => esc_html__( 'Empty Star Color', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#bec9c8',
            'condition' => [ 'show_stars' => 'yes' ],
            'selectors' => [ '{{WRAPPER}} .ovr-tw-star.is-off' => 'color:{{VALUE}}' ],
        ] );

        $this->add_control( 'star_size', [
            'label'      => esc_html__( 'Star Size', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 12, 'max' => 32 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 16 ],
            'condition'  => [ 'show_stars' => 'yes' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-tw-star' => 'font-size:{{SIZE}}{{UNIT}}' ],
        ] );

        $this->end_controls_section();

        /* ============================================================
           STYLE — Person (avatar + name + role)
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
            'selectors'  => [
                '{{WRAPPER}} .ovr-tw-avatar' => 'width:{{SIZE}}{{UNIT}};height:{{SIZE}}{{UNIT}}',
            ],
        ] );

        $this->add_control( 'name_color', [
            'label'   => esc_html__( 'Name Color', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#181c1c',
            'selectors' => [ '{{WRAPPER}} .ovr-tw-name' => 'color:{{VALUE}}' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'name_typography',
            'selector' => '{{WRAPPER}} .ovr-tw-name',
        ] );

        $this->add_control( 'role_color', [
            'label'   => esc_html__( 'Role Color', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#6f7979',
            'selectors' => [ '{{WRAPPER}} .ovr-tw-role' => 'color:{{VALUE}}' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'role_typography',
            'selector' => '{{WRAPPER}} .ovr-tw-role',
        ] );

        $this->end_controls_section();
    }

    /**
     * Render output on the front-end.
     */
    protected function render(): void {
        $s = $this->get_settings_for_display();

        $items     = is_array( $s['testimonials'] ?? null ) ? $s['testimonials'] : [];
        $shape     = $s['avatar_shape'] ?? 'circle';
        $show_stars   = ( $s['show_stars'] ?? 'yes' ) === 'yes';
        $show_quote_mark = ( $s['show_quote_marks'] ?? 'yes' ) === 'yes';
        $hover_lift   = ( $s['card_hover_lift'] ?? 'yes' ) === 'yes';
        $show_header  = ( $s['show_section_header'] ?? 'yes' ) === 'yes';

        $avatar_radius = 'circle' === $shape ? '50%' : ( 'square' === $shape ? '10px' : '0' );

        ?>
        <div class="ovr-tw" style="font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif">

            <?php if ( $show_header && ( ! empty( $s['heading'] ) || ! empty( $s['subheading'] ) ) ) : ?>
                <header style="text-align:center;margin-bottom:32px">
                    <?php if ( ! empty( $s['heading'] ) ) : ?>
                        <h2 class="ovr-tw-heading" style="margin:0 0 8px;font-weight:700"><?php echo esc_html( $s['heading'] ); ?></h2>
                    <?php endif; ?>
                    <?php if ( ! empty( $s['subheading'] ) ) : ?>
                        <p class="ovr-tw-sub" style="margin:0;font-size:15px"><?php echo esc_html( $s['subheading'] ); ?></p>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <div class="ovr-tw-grid" style="display:grid">
                <?php foreach ( $items as $item ) :
                    $rating = max( 0, min( 5, (int) ( $item['rating'] ?? 5 ) ) );
                    $avatar = $item['avatar']['url'] ?? '';
                    $initial = strtoupper( substr( $item['name'] ?? '?', 0, 1 ) );
                ?>
                    <article class="ovr-tw-card<?php echo $hover_lift ? ' has-hover-lift' : ''; ?>"
                             style="position:relative;overflow:hidden">

                        <?php if ( $show_quote_mark ) : ?>
                            <span class="ovr-tw-quote-mark" aria-hidden="true"
                                  style="position:absolute;top:8px;right:16px;font-size:64px;line-height:1;font-family:Georgia,serif;font-weight:700;opacity:0.25">&ldquo;</span>
                        <?php endif; ?>

                        <?php if ( $show_stars && $rating > 0 ) : ?>
                            <div style="display:flex;gap:2px;margin-bottom:12px;justify-content:inherit">
                                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                    <span class="ovr-tw-star <?php echo $i <= $rating ? 'is-on' : 'is-off'; ?>"
                                          style="font-size:16px">&#9733;</span>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>

                        <p class="ovr-tw-quote" style="margin:0 0 20px;line-height:1.6;font-size:15px;position:relative;z-index:1">
                            <?php echo esc_html( (string) ( $item['quote'] ?? '' ) ); ?>
                        </p>

                        <div style="display:flex;align-items:center;gap:12px">
                            <?php if ( 'none' !== $shape ) : ?>
                                <?php if ( $avatar ) : ?>
                                    <img class="ovr-tw-avatar" src="<?php echo esc_url( $avatar ); ?>" alt=""
                                         style="object-fit:cover;flex-shrink:0;border-radius:<?php echo esc_attr( $avatar_radius ); ?>">
                                <?php else : ?>
                                    <div class="ovr-tw-avatar"
                                         style="display:flex;align-items:center;justify-content:center;background:#006666;color:#fff;font-weight:600;flex-shrink:0;border-radius:<?php echo esc_attr( $avatar_radius ); ?>">
                                        <?php echo esc_html( $initial ); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div style="min-width:0">
                                <div class="ovr-tw-name" style="font-weight:600;line-height:1.3"><?php echo esc_html( (string) ( $item['name'] ?? '' ) ); ?></div>
                                <?php if ( ! empty( $item['role'] ) ) : ?>
                                    <div class="ovr-tw-role" style="font-size:13px;line-height:1.3;margin-top:2px"><?php echo esc_html( $item['role'] ); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .ovr-tw-card.has-hover-lift{transition:transform 220ms ease,box-shadow 220ms ease}
            .ovr-tw-card.has-hover-lift:hover{transform:translateY(-4px)}
        </style>
        <?php
    }
}
