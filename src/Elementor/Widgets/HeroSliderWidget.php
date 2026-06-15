<?php
/**
 * Elementor Hero Slider Widget.
 *
 * Full-width hero section with background image, headline, subtitle,
 * and either an embedded search bar OR two action-card CTAs.
 *
 * @package OVR\Elementor\Widgets
 * @since   1.0.0
 */

namespace OVR\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use OVR\Core\Pages;
use OVR\Frontend\HeroSlides;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HeroSliderWidget extends Widget_Base {

    public function get_name(): string {
        return 'ovr_hero_slider';
    }

    public function get_title(): string {
        return esc_html__( 'OVR Hero Section', 'ovr-core' );
    }

    public function get_icon(): string {
        return 'eicon-slider-push';
    }

    public function get_categories(): array {
        return [ 'ovr-widgets' ];
    }

    public function get_keywords(): array {
        return [ 'hero', 'slider', 'banner', 'search', 'ovr' ];
    }

    protected function register_controls(): void {

        $this->start_controls_section( 'content_section', [
            'label' => esc_html__( 'Content', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        /* ── Layout Switcher ── */
        $this->add_control( 'hero_layout', [
            'label'   => esc_html__( 'Hero Layout', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'action_cards',
            'options' => [
                'search_bar'   => esc_html__( 'Search Bar', 'ovr-core' ),
                'action_cards' => esc_html__( 'Action Cards (CTA)', 'ovr-core' ),
            ],
        ] );

        /* ── Background source: single image or the admin-managed slideshow ── */
        $this->add_control( 'bg_source', [
            'label'       => esc_html__( 'Background', 'ovr-core' ),
            'type'        => Controls_Manager::SELECT,
            'default'     => 'single',
            'options'     => [
                'single'    => esc_html__( 'Single Image', 'ovr-core' ),
                'slideshow' => esc_html__( 'Homepage Slideshow', 'ovr-core' ),
            ],
            'description' => esc_html__( 'Slides are managed under Properties → Homepage Slides. If none are set, the single image below is used.', 'ovr-core' ),
        ] );

        $this->add_control( 'slide_content', [
            'label'       => esc_html__( 'Slide Captions', 'ovr-core' ),
            'type'        => Controls_Manager::SELECT,
            'default'     => 'widget',
            'options'     => [
                'widget'    => esc_html__( 'Use the heading/content below', 'ovr-core' ),
                'per_slide' => esc_html__( 'Use each slide\'s own heading/subtitle/button', 'ovr-core' ),
            ],
            'condition'   => [ 'bg_source' => 'slideshow' ],
        ] );

        $this->add_control( 'slide_interval', [
            'label'       => esc_html__( 'Slide Interval (seconds)', 'ovr-core' ),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 6,
            'min'         => 2,
            'max'         => 30,
            'condition'   => [ 'bg_source' => 'slideshow' ],
        ] );

        $this->add_control( 'bg_image', [
            'label'   => esc_html__( 'Background Image', 'ovr-core' ),
            'type'    => Controls_Manager::MEDIA,
            'default' => [
                'url' => OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg',
            ],
            'condition' => [ 'bg_source' => 'single' ],
        ] );

        $this->add_control( 'heading', [
            'label'   => esc_html__( 'Heading', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__( 'Find Your Perfect Village Retreat', 'ovr-core' ),
        ] );

        $this->add_control( 'subtitle', [
            'label'   => esc_html__( 'Subtitle', 'ovr-core' ),
            'type'    => Controls_Manager::TEXTAREA,
            'default' => esc_html__( 'Discover hand-picked vacation homes and long-term rentals in the most charming villages.', 'ovr-core' ),
        ] );

        /* ── Search Bar controls ── */
        $this->add_control( 'show_search', [
            'label'        => esc_html__( 'Show Search Bar', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => [ 'hero_layout' => 'search_bar' ],
        ] );

        $this->add_control( 'show_cta_buttons', [
            'label'        => esc_html__( 'Show CTA Buttons', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'return_value' => 'yes',
            'condition'    => [ 'hero_layout' => 'search_bar' ],
        ] );

        $this->add_control( 'cta_primary_text', [
            'label'     => esc_html__( 'Primary Button Text', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Browse Properties', 'ovr-core' ),
            'condition' => [ 'show_cta_buttons' => 'yes' ],
        ] );

        $this->add_control( 'cta_secondary_text', [
            'label'     => esc_html__( 'Secondary Button Text', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'List Your Property', 'ovr-core' ),
            'condition' => [ 'show_cta_buttons' => 'yes' ],
        ] );

        /* ── Action Cards controls ── */
        $this->add_control( 'card1_title', [
            'label'     => esc_html__( 'Card 1 — Title', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Find a Rental', 'ovr-core' ),
            'condition' => [ 'hero_layout' => 'action_cards' ],
            'separator' => 'before',
        ] );

        $this->add_control( 'card1_desc', [
            'label'     => esc_html__( 'Card 1 — Description', 'ovr-core' ),
            'type'      => Controls_Manager::TEXTAREA,
            'default'   => esc_html__( 'Browse vacation homes and long-term rentals in The Villages community.', 'ovr-core' ),
            'condition' => [ 'hero_layout' => 'action_cards' ],
        ] );

        $this->add_control( 'card1_btn_text', [
            'label'     => esc_html__( 'Card 1 — Button Text', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Search Rentals', 'ovr-core' ),
            'condition' => [ 'hero_layout' => 'action_cards' ],
        ] );

        $this->add_control( 'card1_btn_link', [
            'label'       => esc_html__( 'Card 1 — Button Link', 'ovr-core' ),
            'type'        => Controls_Manager::URL,
            'placeholder' => esc_html__( 'https://your-link.com', 'ovr-core' ),
            'default'     => [ 'url' => Pages::get_page_url( 'ovr_page_search' ) ],
            'condition'   => [ 'hero_layout' => 'action_cards' ],
        ] );

        $this->add_control( 'card2_title', [
            'label'     => esc_html__( 'Card 2 — Title', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'List My Property', 'ovr-core' ),
            'condition' => [ 'hero_layout' => 'action_cards' ],
            'separator' => 'before',
        ] );

        $this->add_control( 'card2_desc', [
            'label'     => esc_html__( 'Card 2 — Description', 'ovr-core' ),
            'type'      => Controls_Manager::TEXTAREA,
            'default'   => esc_html__( 'Reach thousands of Villages residents and visitors looking for their perfect rental.', 'ovr-core' ),
            'condition' => [ 'hero_layout' => 'action_cards' ],
        ] );

        $this->add_control( 'card2_btn_text', [
            'label'     => esc_html__( 'Card 2 — Button Text', 'ovr-core' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => esc_html__( 'Get Started', 'ovr-core' ),
            'condition' => [ 'hero_layout' => 'action_cards' ],
        ] );

        $this->add_control( 'card2_btn_link', [
            'label'       => esc_html__( 'Card 2 — Button Link', 'ovr-core' ),
            'type'        => Controls_Manager::URL,
            'placeholder' => esc_html__( 'https://your-link.com', 'ovr-core' ),
            'default'     => [ 'url' => Pages::get_page_url( 'ovr_page_register' ) ],
            'condition'   => [ 'hero_layout' => 'action_cards' ],
        ] );

        $this->end_controls_section();

        /* ── Style ── */
        $this->start_controls_section( 'style_section', [
            'label' => esc_html__( 'Style', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'min_height', [
            'label'     => esc_html__( 'Minimum Height', 'ovr-core' ),
            'type'      => Controls_Manager::SLIDER,
            'range'     => [ 'px' => [ 'min' => 300, 'max' => 900 ] ],
            'default'   => [ 'size' => 650, 'unit' => 'px' ],
            'selectors' => [
                '{{WRAPPER}} .ovr-hero' => 'min-height: {{SIZE}}{{UNIT}};',
            ],
        ] );

        $this->add_control( 'overlay_color', [
            'label'   => esc_html__( 'Overlay Color', 'ovr-core' ),
            'type'    => Controls_Manager::COLOR,
            'default' => 'rgba(0,0,0,0.4)',
            'selectors' => [
                '{{WRAPPER}} .ovr-hero-overlay' => 'background: linear-gradient(to bottom, {{VALUE}}, transparent, {{VALUE}});',
            ],
        ] );

        $this->add_control( 'text_alignment', [
            'label'   => esc_html__( 'Text Alignment', 'ovr-core' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'left'   => [ 'title' => esc_html__( 'Left', 'ovr-core' ), 'icon' => 'eicon-text-align-left' ],
                'center' => [ 'title' => esc_html__( 'Center', 'ovr-core' ), 'icon' => 'eicon-text-align-center' ],
            ],
            'default'   => 'center',
            'selectors' => [
                '{{WRAPPER}} .ovr-hero-content' => 'text-align: {{VALUE}};',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings   = $this->get_settings_for_display();
        $bg_url     = $settings['bg_image']['url'] ?? '';
        $search_url = Pages::get_page_url( 'ovr_page_search' );
        $layout     = $settings['hero_layout'] ?? 'action_cards';

        // Slideshow mode (M3 F7): pull admin-managed slides; fall back to the
        // single background image when the slideshow is empty.
        $is_slideshow = ( 'slideshow' === ( $settings['bg_source'] ?? 'single' ) );
        $slides       = $is_slideshow ? HeroSlides::enabled() : [];
        $is_slideshow  = $is_slideshow && ! empty( $slides );
        $per_slide     = $is_slideshow && 'per_slide' === ( $settings['slide_content'] ?? 'widget' );
        $interval_ms   = max( 2, (int) ( $settings['slide_interval'] ?? 6 ) ) * 1000;
        ?>
        <div class="ovr-wrap">
            <section class="ovr-hero<?php echo $is_slideshow ? ' ovr-hero--slideshow' : ''; ?>">
                <?php if ( $is_slideshow ) : ?>
                    <div class="ovr-hero-bg ovr-hero-slideshow" data-interval="<?php echo esc_attr( (string) $interval_ms ); ?>">
                        <?php foreach ( $slides as $i => $slide ) : ?>
                            <div class="ovr-hero-slide<?php echo 0 === $i ? ' is-active' : ''; ?>" data-slide-index="<?php echo esc_attr( (string) $i ); ?>">
                                <img src="<?php echo esc_url( $slide['image'] ); ?>" alt="" loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>">
                            </div>
                        <?php endforeach; ?>
                        <div class="ovr-hero-overlay"></div>
                    </div>
                <?php else : ?>
                    <div class="ovr-hero-bg">
                        <?php if ( $bg_url ) : ?>
                            <img src="<?php echo esc_url( $bg_url ); ?>" alt="" loading="eager">
                        <?php endif; ?>
                        <div class="ovr-hero-overlay"></div>
                    </div>
                <?php endif; ?>

                <div class="ovr-hero-content">
                    <?php if ( $per_slide ) : ?>
                        <?php $this->render_slide_captions( $slides ); ?>
                    <?php else : ?>
                    <?php if ( ! empty( $settings['heading'] ) ) : ?>
                        <h1 class="ovr-h1"><?php echo esc_html( $settings['heading'] ); ?></h1>
                    <?php endif; ?>

                    <?php if ( ! empty( $settings['subtitle'] ) ) : ?>
                        <p><?php echo esc_html( $settings['subtitle'] ); ?></p>
                    <?php endif; ?>

                    <?php if ( 'action_cards' === $layout ) : ?>
                        <?php $this->render_action_cards( $settings, $search_url ); ?>
                    <?php else : ?>
                        <?php if ( 'yes' === $settings['show_search'] ) : ?>
                            <form class="ovr-search-pill" action="<?php echo esc_url( $search_url ); ?>" method="get">
                                <div class="ovr-search-field">
                                    <span class="ovr-search-field-label"><?php esc_html_e( 'Location', 'ovr-core' ); ?></span>
                                    <input type="text" name="keyword" placeholder="<?php esc_attr_e( 'Where are you going?', 'ovr-core' ); ?>">
                                </div>
                                <div class="ovr-search-divider"></div>
                                <div class="ovr-search-field">
                                    <span class="ovr-search-field-label"><?php esc_html_e( 'Check In', 'ovr-core' ); ?></span>
                                    <input type="date" name="checkin">
                                </div>
                                <div class="ovr-search-divider"></div>
                                <div class="ovr-search-field">
                                    <span class="ovr-search-field-label"><?php esc_html_e( 'Guests', 'ovr-core' ); ?></span>
                                    <input type="number" name="guests" min="1" max="20" placeholder="<?php esc_attr_e( 'Add guests', 'ovr-core' ); ?>">
                                </div>
                                <button type="submit" class="ovr-btn ovr-btn-primary ovr-btn-pill" style="padding:16px;border-radius:50%;width:56px;height:56px;flex-shrink:0">
                                    <span class="material-symbols-outlined">search</span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ( 'yes' === $settings['show_cta_buttons'] ) : ?>
                            <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;margin-top:24px">
                                <a href="<?php echo esc_url( $search_url ); ?>" class="ovr-btn ovr-btn-lg ovr-btn-pill" style="background:#fff;color:var(--ovr-primary)">
                                    <span class="material-symbols-outlined">search</span>
                                    <?php echo esc_html( $settings['cta_primary_text'] ); ?>
                                </a>
                                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_register' ) ); ?>" class="ovr-btn ovr-btn-outline ovr-btn-lg ovr-btn-pill" style="border-color:rgba(255,255,255,0.4);color:#fff">
                                    <?php echo esc_html( $settings['cta_secondary_text'] ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php endif; /* per_slide */ ?>
                </div>
            </section>
        </div>
        <?php
    }

    /**
     * Render rotating per-slide captions (heading / subtitle / CTA) for the
     * slideshow background. The active caption is synced to the active slide by
     * the front-end rotator via matching data-slide-index attributes.
     *
     * @param array<int, array{image:string,heading:string,subtitle:string,cta_text:string,cta_url:string}> $slides
     */
    private function render_slide_captions( array $slides ): void {
        ?>
        <div class="ovr-hero-captions">
            <?php foreach ( $slides as $i => $slide ) : ?>
                <div class="ovr-hero-caption<?php echo 0 === $i ? ' is-active' : ''; ?>" data-slide-index="<?php echo esc_attr( (string) $i ); ?>">
                    <?php if ( '' !== $slide['heading'] ) : ?>
                        <h1 class="ovr-h1"><?php echo esc_html( $slide['heading'] ); ?></h1>
                    <?php endif; ?>
                    <?php if ( '' !== $slide['subtitle'] ) : ?>
                        <p><?php echo esc_html( $slide['subtitle'] ); ?></p>
                    <?php endif; ?>
                    <?php if ( '' !== $slide['cta_text'] && '' !== $slide['cta_url'] ) : ?>
                        <a href="<?php echo esc_url( $slide['cta_url'] ); ?>" class="ovr-btn ovr-btn-lg ovr-btn-pill" style="background:#fff;color:var(--ovr-primary)">
                            <?php echo esc_html( $slide['cta_text'] ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render the two action-card CTAs (Find a Rental / List My Property).
     *
     * @param array  $settings   Widget settings.
     * @param string $search_url Search page URL.
     */
    private function render_action_cards( array $settings, string $search_url ): void {
        $register_url = Pages::get_page_url( 'ovr_page_register' );

        $card1_url  = ! empty( $settings['card1_btn_link']['url'] ) ? $settings['card1_btn_link']['url'] : $search_url;
        $card2_url  = ! empty( $settings['card2_btn_link']['url'] ) ? $settings['card2_btn_link']['url'] : $register_url;
        $card1_attr = $this->link_attributes( $settings['card1_btn_link'] ?? [] );
        $card2_attr = $this->link_attributes( $settings['card2_btn_link'] ?? [] );
        ?>
        <div class="ovr-hero-actions">
            <a href="<?php echo esc_url( $card1_url ); ?>" class="ovr-hero-action-card"<?php echo $card1_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <span class="material-symbols-outlined ovr-hero-action-icon">search</span>
                <h3 class="ovr-h3"><?php echo esc_html( $settings['card1_title'] ?? 'Find a Rental' ); ?></h3>
                <p class="ovr-body-md"><?php echo esc_html( $settings['card1_desc'] ?? '' ); ?></p>
                <span class="ovr-btn ovr-btn-primary"><?php echo esc_html( $settings['card1_btn_text'] ?? 'Search Rentals' ); ?></span>
            </a>
            <a href="<?php echo esc_url( $card2_url ); ?>" class="ovr-hero-action-card"<?php echo $card2_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <span class="material-symbols-outlined ovr-hero-action-icon ovr-hero-action-icon--gold">home</span>
                <h3 class="ovr-h3"><?php echo esc_html( $settings['card2_title'] ?? 'List My Property' ); ?></h3>
                <p class="ovr-body-md"><?php echo esc_html( $settings['card2_desc'] ?? '' ); ?></p>
                <span class="ovr-btn ovr-btn-outline"><?php echo esc_html( $settings['card2_btn_text'] ?? 'Get Started' ); ?></span>
            </a>
        </div>
        <?php
    }

    /**
     * Build target/rel attributes from an Elementor URL control value.
     *
     * @param array $link Elementor URL control value (url/is_external/nofollow).
     * @return string Pre-escaped attribute string (leading space) or ''.
     */
    private function link_attributes( array $link ): string {
        $attr = '';
        if ( ! empty( $link['is_external'] ) ) {
            $attr .= ' target="_blank"';
        }
        if ( ! empty( $link['nofollow'] ) ) {
            $attr .= ' rel="nofollow"';
        }
        return $attr;
    }
}
