# Sponsored Property Carousel — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An "OVR Property Carousel" Elementor widget (4/3/1 cards per view desktop/tablet/mobile) showing sponsored listings first, then owner-curated "homepage carousel" picks, then most-recent fill — plus an admin page to manage the curated picks.

**Architecture:** Four seams — (1) `PropertyQuery::get_carousel_ids()` composes the ordered, de-duplicated, truncated ID list; (2) a new Elementor `Widget_Base` renders square-image property cards inside the shared carousel markup; (3) the existing `assets/js/ovr-testimonials.js` engine is generalized with a configurable `data-ovr-prefix` (default `ovr-tc` keeps the testimonials widget unchanged; the new widget uses `ovr-pc`); (4) a new admin submenu page lets the owner toggle a listing into the carousel and reorder it, persisted in `ovr_settings['homepage_carousel_ids']`.

**Tech Stack:** WordPress 6.4+, PHP 8.2+, `Elementor\Widget_Base` (Controls_Manager, Group_Control_*), WP_Query, WP admin menus, existing `OVR\` PSR-4 autoloader.

**Verification:** No PHPUnit harness is present. Verify with `php -l` (PHP syntax), `node --check` (JS syntax), and WP-CLI. Every changed PHP/JS file must lint clean.

Repo root = this plugin dir. All paths are relative to it.

---

## Task 1 — Add `PropertyQuery::get_carousel_ids()`

**Files:**
- Modify: `src/Property/PropertyQuery.php`

- [ ] **Step 1: Append the method** to the end of the class, after `get_similar()`:

```php
    /**
     * Ordered property IDs for the Sponsored Property Carousel.
     *
     * Composition order (left → right), de-duplicated (first wins), truncated to
     * $count:
     *   1. Sponsored   — active Featured boost, newest first.
     *   2. Curated     — owner's "Homepage Carousel" picks, in stored order.
     *   3. Recent fill — newest-published listings to reach $count.
     *
     * @param int $count Max number of cards.
     * @return int[]
     */
    public static function get_carousel_ids( int $count = 4 ): array {
        $count = max( 1, min( 120, absint( $count ) ) );

        $sponsored = array_map( 'absint', (array) ( new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => array_merge(
                [ 'relation' => 'AND' ],
                self::visibility_clauses(),
                [ self::active_boost_clause( '_ovr_is_featured', '_ovr_featured_expires' ) ]
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
        ] ) )->posts );

        $settings = (array) get_option( 'ovr_settings', [] );
        $picks    = preg_split( '/[\s,]+/', (string) ( $settings['homepage_carousel_ids'] ?? '' ) );
        $picks    = array_map( 'absint', (array) $picks );
        $picks    = array_values( array_filter( $picks ) );

        $curated = [];
        if ( $picks ) {
            $curated = array_map( 'absint', (array) ( new \WP_Query( [
                'post_type'      => 'ovr_property',
                'post_status'    => 'publish',
                'posts_per_page' => count( $picks ),
                'post__in'       => $picks,
                'orderby'        => 'post__in',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ] ) )->posts );
        }

        $combined = [];
        foreach ( array_merge( $sponsored, $curated ) as $id ) {
            if ( $id > 0 && ! isset( $combined[ $id ] ) ) {
                $combined[ $id ] = true;
            }
        }

        if ( count( $combined ) < $count ) {
            $recent = array_map( 'absint', (array) ( new \WP_Query( [
                'post_type'      => 'ovr_property',
                'post_status'    => 'publish',
                'posts_per_page' => $count - count( $combined ),
                'post__not_in'   => $combined ? array_keys( $combined ) : [ 0 ],
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ] ) )->posts );
            foreach ( $recent as $id ) {
                if ( $id > 0 && ! isset( $combined[ $id ] ) ) {
                    $combined[ $id ] = true;
                }
            }
        }

        return array_slice( array_keys( $combined ), 0, $count );
    }
```

- [ ] **Step 2: Lint**

Run: `php -l src/Property/PropertyQuery.php`
Expected: `No syntax errors detected in src/Property/PropertyQuery.php`

- [ ] **Step 3: Commit**

```bash
git add src/Property/PropertyQuery.php
git commit -m "feat(Property): add PropertyQuery::get_carousel_ids()"
```

## Task 2 — Create the Elementor widget

**Files:**
- Create: `src/Elementor/Widgets/PropertyCarouselWidget.php`

- [ ] **Step 1: Write the widget file** (model closely on `TestimonialsCarouselWidget.php`):

```php
<?php
/**
 * OVR Sponsored Property Carousel Elementor.
 *
 * A sliding, drag-and-swipeable carousel of property cards for the homepage.
 * Each card links through to the property and shows a square image plus
 * ID / name / type / price / size. Order is composed by
 * PropertyQuery::get_carousel_ids(): sponsored (active Featured boost) first,
 * then owner-curated "Homepage Carousel" picks, then most-recent fill. The
 * carousel behaviour comes from the shared engine in ovr-testimonials.js under
 * the `ovr-pc` prefix.
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
use OVR\Property\PropertyQuery;
use OVR\Property\PropertyCard;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PropertyCarouselWidget extends Widget_Base {

    /** @var bool Whether structural CSS has been printed this request. */
    private static bool $css_printed = false;

    public function get_name(): string { return 'ovr_property_carousel'; }
    public function get_title(): string { return esc_html__( 'OVR Property Carousel', 'ovr-core' ); }
    public function get_icon(): string { return 'eicon-carousel'; }
    public function get_categories(): array { return [ 'ovr-widgets' ]; }
    public function get_keywords(): array { return [ 'property', 'carousel', 'slider', 'sponsored', 'featured', 'ovr' ]; }
    public function get_script_depends(): array { return [ 'ovr-testimonials' ]; }

    protected function register_controls(): void {

        /* CONTENT — Query */
        $this->start_controls_section( 'section_query', [
            'label' => esc_html__( 'Query', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'count', [
            'label'   => esc_html__( 'Number of cards', 'ovr-core' ),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 24,
            'default' => 8,
        ] );

        $this->end_controls_section();

        /* CONTENT — Section Header */
        $this->start_controls_section( 'section_header', [
            'label' => esc_html__( 'Section Header', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'show_section_header', [
            'label'   => esc_html__( 'Show Title & Subtitle', 'ovr-core' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'heading', [
            'label'   => esc_html__( 'Title', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__( 'Featured & Sponsored Properties', 'ovr-core' ),
        ] );

        $this->add_control( 'subheading', [
            'label'   => esc_html__( 'Subtitle', 'ovr-core' ),
            'type'    => Controls_Manager::TEXTAREA,
            'rows'    => 2,
            'default' => esc_html__( 'Hand-picked stays waiting for you.', 'ovr-core' ),
        ] );

        $this->end_controls_section();

        /* CONTENT — Carousel behaviour */
        $this->start_controls_section( 'section_carousel', [
            'label' => esc_html__( 'Carousel', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_responsive_control( 'per_view', [
            'label'          => esc_html__( 'Cards Per View', 'ovr-core' ),
            'type'           => Controls_Manager::SELECT,
            'default'        => '4',
            'tablet_default' => '3',
            'mobile_default' => '1',
            'options'        => [ '1' => '1', '2' => '2', '3' => '3', '4' => '4' ],
            'selectors'      => [ '{{WRAPPER}} .ovr-pc' => '--ovr-pc-per:{{VALUE}}' ],
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

        $this->end_controls_section();

        /* STYLE — Section Header */
        $this->start_controls_section( 'style_header', [
            'label'     => esc_html__( 'Section Header', 'ovr-core' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => [ 'show_section_header' => 'yes' ],
        ] );

        $this->add_control( 'heading_color', [
            'label'     => esc_html__( 'Title Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#181c1c',
            'selectors' => [ '{{WRAPPER}} .ovr-pc-heading' => 'color:{{VALUE}}' ],
        ] );

        $this->add_group_control( Group_Control_Typography::get_type(), [
            'name'     => 'heading_typography',
            'selector' => '{{WRAPPER}} .ovr-pc-heading',
        ] );

        $this->add_control( 'subheading_color', [
            'label'     => esc_html__( 'Subtitle Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#3f4948',
            'selectors' => [ '{{WRAPPER}} .ovr-pc-sub' => 'color:{{VALUE}}' ],
        ] );

        $this->end_controls_section();

        /* STYLE — Card */
        $this->start_controls_section( 'style_card', [
            'label' => esc_html__( 'Card', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'card_bg', [
            'label'     => esc_html__( 'Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-pc-card' => 'background:{{VALUE}}' ],
        ] );

        $this->add_control( 'card_radius', [
            'label'      => esc_html__( 'Border Radius', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 14 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-pc-card' => 'border-radius:{{SIZE}}{{UNIT}}' ],
        ] );

        $this->add_responsive_control( 'card_padding', [
            'label'      => esc_html__( 'Padding', 'ovr-core' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => [ 'px', 'em' ],
            'default'    => [ 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px' ],
            'selectors'  => [ '{{WRAPPER}} .ovr-pc-card' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' ],
        ] );

        $this->add_group_control( Group_Control_Border::get_type(), [
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .ovr-pc-card',
        ] );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .ovr-pc-card',
        ] );

        $this->end_controls_section();

        /* STYLE — Image */
        $this->start_controls_section( 'style_image', [
            'label' => esc_html__( 'Image', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'image_radius', [
            'label'      => esc_html__( 'Border Radius', 'ovr-core' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => [ 'px' ],
            'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
            'default'    => [ 'unit' => 'px', 'size' => 10 ],
            'selectors'  => [ '{{WRAPPER}} .ovr-pc-image' => 'border-radius:{{SIZE}}{{UNIT}}' ],
        ] );

        $this->end_controls_section();

        /* STYLE — Field colors + typography (ID, Name, Type, Price, Size) */
        $fields = [
            'id'    => [ '.ovr-pc-id',    esc_html__( 'Listing ID', 'ovr-core' ),    '#6f7979' ],
            'name'  => [ '.ovr-pc-name',  esc_html__( 'Listing Name', 'ovr-core' ),  '#181c1c' ],
            'type'  => [ '.ovr-pc-type',  esc_html__( 'Property Type', 'ovr-core' ), '#3f4944' ],
            'price' => [ '.ovr-pc-price', esc_html__( 'Price', 'ovr-core' ),         '#1466a8' ],
            'size'  => [ '.ovr-pc-size',  esc_html__( 'Size', 'ovr-core' ),          '#6f7979' ],
        ];
        foreach ( $fields as $key => $def ) {
            $this->start_controls_section( 'style_' . $key, [
                'label' => $def[1],
                'tab'   => Controls_Manager::TAB_STYLE,
            ] );

            $this->add_control( $key . '_color', [
                'label'     => esc_html__( 'Color', 'ovr-core' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => $def[2],
                'selectors' => [ '{{WRAPPER}} ' . $def[0] => 'color:{{VALUE}}' ],
            ] );

            $this->add_group_control( Group_Control_Typography::get_type(), [
                'name'     => $key . '_typography',
                'selector' => '{{WRAPPER}} ' . $def[0],
            ] );

            $this->end_controls_section();
        }

        /* STYLE — Arrows & Dots */
        $this->start_controls_section( 'style_nav', [
            'label' => esc_html__( 'Arrows & Dots', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );
        $this->add_control( 'arrow_color', [
            'label'     => esc_html__( 'Arrow Icon Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#181c1c',
            'selectors' => [ '{{WRAPPER}} .ovr-pc-arrow' => 'color:{{VALUE}}' ],
        ] );
        $this->add_control( 'arrow_bg', [
            'label'     => esc_html__( 'Arrow Background', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [ '{{WRAPPER}} .ovr-pc-arrow' => 'background:{{VALUE}}' ],
        ] );
        $this->add_control( 'dot_color', [
            'label'     => esc_html__( 'Dot Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#bec9c8',
            'selectors' => [ '{{WRAPPER}} .ovr-pc-dot' => 'background:{{VALUE}}' ],
        ] );
        $this->add_control( 'dot_active_color', [
            'label'     => esc_html__( 'Active Dot Color', 'ovr-core' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#006676',
            'selectors' => [ '{{WRAPPER}} .ovr-pc-dot.is-active' => 'background:{{VALUE}}' ],
        ] );
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();

        $count        = max( 1, min( 24, (int) ( $s['count'] ?? 8 ) ) );
        $autoplay    = ( $s['autoplay'] ?? 'yes' ) === 'yes';
        $loop        = ( $s['loop'] ?? 'yes' ) === 'yes';
        $show_header = ( $s['show_section_header'] ?? 'yes' ) === 'yes';
        $show_arrows = ( $s['show_arrows'] ?? 'yes' ) === 'yes';
        $show_dots   = ( $s['show_dots'] ?? 'yes' ) === 'yes';
        $interval   = max( 1500, (int) ( $s['autoplay_speed'] ?? 5000 ) );

        $ids = PropertyQuery::get_carousel_ids( $count );

        if ( empty( $ids ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:32px;text-align:center;border:1px dashed #bec9c8;border-radius:12px;color:#6f7979;font-family:Inter,sans-serif">'
                    . esc_html__( 'No published properties yet. Add listings and set some to "Homepage Carousel" to fill this rail.', 'ovr-core' )
                    . '</div>';
            }
            return;
        }

        $rows = [];
        foreach ( $ids as $id ) {
            $rows[] = PropertyCard::get_card_data( (int) $id );
        }
        $symbol = (string) ( ( (array) get_option( 'ovr_settings', [] ) )['currency_symbol'] ?? '$' );

        $this->print_structural_css();
        ?>
        <div class="ovr-pc" data-ovr-carousel data-ovr-prefix="ovr-pc"
             data-autoplay="<?php echo $autoplay ? '1' : '0'; ?>"
             data-interval="<?php echo esc_attr( (string) $interval ); ?>"
             data-loop="<?php echo $loop ? '1' : '0'; ?>"
             tabindex="0" role="region" aria-roledescription="carousel"
             aria-label="<?php esc_attr_e( 'Sponsored properties', 'ovr-core' ); ?>">

            <?php if ( $show_header && ( ! empty( $s['heading'] ) || ! empty( $s['subheading'] ) ) ) : ?>
                <header class="ovr-pc-header">
                    <?php if ( ! empty( $s['heading'] ) ) : ?>
                        <h2 class="ovr-pc-heading"><?php echo esc_html( $s['heading'] ); ?></h2>
                    <?php endif; ?>
                    <?php if ( ! empty( $s['subheading'] ) ) : ?>
                        <p class="ovr-pc-sub"><?php echo esc_html( $s['subheading'] ); ?></p>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <div class="ovr-pc-stage">
                <?php if ( $show_arrows ) : ?>
                    <button type="button" class="ovr-pc-arrow ovr-pc-prev" aria-label="<?php esc_attr_e( 'Previous', 'ovr-core' ); ?>">
                        <span aria-hidden="true">&#8249;</span>
                    </button>
                <?php endif; ?>

                <div class="ovr-pc-viewport">
                    <div class="ovr-pc-track">
                        <?php foreach ( $rows as $data ) : ?>
                            <article class="ovr-pc-card">
                                <a class="ovr-pc-media" href="<?php echo esc_url( $data['permalink'] ); ?>">
                                    <div class="ovr-pc-image">
                                        <img src="<?php echo esc_url( $data['thumbnail'] ); ?>"
                                             alt="<?php echo esc_attr( $data['title'] ); ?>"
                                             loading="lazy">
                                    </div>
                                </a>
                                <div class="ovr-pc-id">#<?php echo esc_html( number_format_i18n( (int) $data['post_id'] ) ); ?></div>
                                <h3 class="ovr-pc-name"><a href="<?php echo esc_url( $data['permalink'] ); ?>"><?php echo esc_html( $data['title'] ); ?></a></h3>
                                <?php if ( ! empty( $data['property_type'] ) ) : ?>
                                    <div class="ovr-pc-type"><?php echo esc_html( $data['property_type'] ); ?></div>
                                <?php endif; ?>
                                <div class="ovr-pc-meta">
                                    <?php if ( (float) $data['base_price'] > 0 ) : ?>
                                        <span class="ovr-pc-price"><?php echo esc_html( $symbol . number_format_i18n( (float) $data['base_price'], 0 ) ); ?><em>/ <?php esc_html_e( 'night', 'ovr-core' ); ?></em></span>
                                    <?php endif; ?>
                                    <?php if ( (int) $data['sqft'] > 0 ) : ?>
                                        <span class="ovr-pc-size"><?php echo esc_html( number_format_i18n( (int) $data['sqft'] ) ); ?> <?php esc_html_e( 'sq ft', 'ovr-core' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ( $show_arrows ) : ?>
                    <button type="button" class="ovr-pc-arrow ovr-pc-next" aria-label="<?php esc_attr_e( 'Next', 'ovr-core' ); ?>">
                        <span aria-hidden="true">&#8250;</span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ( $show_dots ) : ?>
                <div class="ovr-pc-dots" role="tablist" aria-label="<?php esc_attr_e( 'Carousel pagination', 'ovr-core' ); ?>"></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Structural layout/motion CSS; colours/spacing come from the controls. */
    private function print_structural_css(): void {
        if ( self::$css_printed ) { return; }
        self::$css_printed = true;
        ?>
        <style id="ovr-pc-structural">
            .ovr-pc{--ovr-pc-per:4;font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;position:relative}
            .ovr-pc-header{text-align:center;margin-bottom:28px}
            .ovr-pc-heading{margin:0 0 8px;font-weight:700}
            .ovr-pc-sub{margin:0;font-size:15px}
            .ovr-pc-stage{display:flex;align-items:center;gap:8px}
            .ovr-pc-viewport{overflow:hidden;flex:1 1 auto;width:100%}
            .ovr-pc-track{display:flex;gap:24px;will-change:transform;transition:transform 420ms cubic-bezier(.22,.61,.36,1)}
            .ovr-pc-card{flex:0 0 calc((100% - (var(--ovr-pc-per) - 1) * 24px) / var(--ovr-pc-per));box-sizing:border-box;background:#fff;border-radius:14px;padding:16px;cursor:grab}
            .ovr-pc-track:active .ovr-pc-card{cursor:grabbing}
            .ovr-pc-media{display:block;text-decoration:none}
            .ovr-pc-image{aspect-ratio:1/1;border-radius:10px;overflow:hidden;background:#e8ecec}
            .ovr-pc-image img{width:100%;height:100%;object-fit:cover;display:block}
            .ovr-pc-id{font-size:12px;font-weight:600;color:#6f7979;margin-top:12px;letter-spacing:.3px}
            .ovr-pc-name{margin:4px 0 2px;font-size:16px;font-weight:700;line-height:1.3}
            .ovr-pc-name a{color:inherit;text-decoration:none}
            .ovr-pc-type{font-size:13px;color:#3f4944;margin-bottom:8px}
            .ovr-pc-meta{display:flex;gap:12px;align-items:baseline;border-top:1px solid #eef1f1;padding-top:10px;flex-wrap:wrap}
            .ovr-pc-price{font-weight:700;color:#1466a8;font-size:16px}
            .ovr-pc-price em{font-style:normal;font-size:12px;font-weight:500;color:#6b7979}
            .ovr-pc-size{font-size:13px;color:#6b7979}
            .ovr-pc-arrow{flex:0 0 auto;width:44px;height:44px;border:none;border-radius:50%;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.12);font-size:26px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .15s ease,opacity .15s ease}
            .ovr-pc-arrow:hover{transform:scale(1.08)}
            .ovr-pc-arrow:disabled{opacity:.35;cursor:default}
            .ovr-pc-dots{display:flex;justify-content:center;gap:8px;margin-top:22px}
            .ovr-pc-dot{width:9px;height:9px;border:none;border-radius:50%;background:#bec9c8;padding:0;cursor:pointer;transition:transform .15s ease,background .15s ease}
            .ovr-pc-dot.is-active{background:#006676;transform:scale(1.25)}
            @media (max-width:768px){.ovr-pc-arrow{display:none}}
        </style>
        <?php
    }
}
```

- [ ] **Step 2: Lint**

Run: `php -l src/Elementor/Widgets/PropertyCarouselWidget.php`
Expected: `No syntax errors detected …`

- [ ] **Step 3: Commit**

```bash
git add src/Elementor/Widgets/PropertyCarouselWidget.php
git commit -m "feat(Elementor): add OVR Property Carousel widget"
```

## Task 3 — Register the widget with Elementor

**Files:**
- Modify: `src/Elementor/ElementorIntegration.php`

- [ ] **Step 1: Add require + register** after the testimonials entries:

```php
        require_once OVR_PLUGIN_DIR . 'src/Elementor/Widgets/PropertyCarouselWidget.php';
        // …
        $widgets_manager->register( new Widgets\PropertyCarouselWidget() );
```

- [ ] **Step 2: Lint**

Run: `php -l src/Elementor/ElementorIntegration.php`
Expected: clean.

- [ ] **Step 3: Commit**

```bash
git add src/Elementor/ElementorIntegration.php
git commit -m "feat(Elementor): register Property Carousel widget"
```

## Task 4 — Parameterize `assets/js/ovr-testimonials.js`

**Files:**
- Modify: `assets/js/ovr-testimonials.js`

- [ ] **Step 1: Prefix-driven selectors.** At the top of `init(root)`:

```js
var prefix = root.getAttribute('data-ovr-prefix') || 'ovr-tc';
```

Replace the hard-coded selectors (exhaustive in the current file):

```js
if (!root || root.getAttribute('data-' + prefix + '-ready') === '1') return;
var track = root.querySelector('.' + prefix + '-track');
if (!track) return;
var slides = Array.prototype.slice.call(track.querySelectorAll('.' + prefix + '-card'));
if (!slides.length) { root.setAttribute('data-' + prefix + '-ready', '1'); return; }
root.setAttribute('data-' + prefix + '-ready', '1');

var prevBtn = root.querySelector('.' + prefix + '-prev');
var nextBtn = root.querySelector('.' + prefix + '-next');
var dotsBox = root.querySelector('.' + prefix + '-dots');
```

- `readPerView()`: `parseFloat(getComputedStyle(root).getPropertyValue('--' + prefix + '-per'));`
- `buildDots()`: `dot.className = prefix + '-dot';`
- `setupReadMore(root)`: capture `var qPrefix = root.getAttribute('data-ovr-prefix') || 'ovr-tc';` and use `'.' + qPrefix + '-readmore'` / `'.' + qPrefix + '-quote'` for its selectors.

- [ ] **Step 2: Add the property widget's element-ready hook** inside the existing `elementor/frontend/init` handler:

```js
window.elementorFrontend.hooks.addAction(
    'frontend/element_ready/ovr_property_carousel.default',
    function ($scope) { init($scope[0].querySelector('.ovr-pc[data-ovr-carousel]')); }
);
```

- [ ] **Step 3: Lint**

Run: `node --check assets/js/ovr-testimonials.js`
Expected: no errors.

- [ ] **Step 4: Keep the testimonials widget's per-view CSS var in sync.** The engine now reads the key as `--<prefix>-per`, so testimonials (`ovr-tc`) reads `--ovr-tc-per`, but the widget currently sets `--tc-per`. In `src/Elementor/Widgets/TestimonialsCarouselWidget.php` replace `--tc-per` with `--ovr-tc-per` in: the `per_view` responsive control's `selectors` (line ~122) and the `.ovr-tc{--tc-per:3;…}` default plus the two `var(--tc-per)` in `.ovr-tc-track` card-width calc in the structural CSS (`print_structural_css()`). Leave `--tc-fade` as-is (no `-per` suffix, unrelated to per-view). Then `php -l src/Elementor/Widgets/TestimonialsCarouselWidget.php`.

- [ ] **Step 5: Confirm testimonials stays otherwise unchanged** — its markup has no `data-ovr-prefix`, so `prefix` = `'ovr-tc'`. Grep for leftover literal `.ovr-tc-` selectors that weren't parameterized.

- [ ] **Step 6: Commit**

```bash
git add assets/js/ovr-testimonials.js src/Elementor/Widgets/TestimonialsCarouselWidget.php
git commit -m "feat(js): generalize carousel engine via data-ovr-prefix"
```

## Task 5 — Admin page + wiring

**Files:**
- Create: `src/Admin/PropertyCarouselAdmin.php`
- Modify: `src/Plugin.php`

- [ ] **Step 1: Write `PropertyCarouselAdmin.php`:**

```php
<?php
/**
 * Homepage Carousel admin page.
 *
 * Lets the site owner pick which published listings appear in the curated tier
 * of the Sponsored Property Carousel, and reorder them with up/down. The
 * ordered pick list is persisted in `ovr_settings['homepage_carousel_ids']`.
 *
 * @package OVR\Admin
 * @since   1.1.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PropertyCarouselAdmin {

    public const PAGE_SLUG      = 'ovr-core-property-carousel';
    public const TOGGLE_ACTION = 'ovr_carousel_toggle';
    public const MOVE_ACTION   = 'ovr_carousel_move';
    public const SETTING_KEY   = 'homepage_carousel_ids';

    private string $hook_suffix = '';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_' . self::TOGGLE_ACTION, [ $this, 'handle_toggle' ] );
        add_action( 'admin_post_' . self::MOVE_ACTION,   [ $this, 'handle_move' ] );
    }

    public function register_page(): void {
        $this->hook_suffix = (string) add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Homepage Carousel', 'ovr-core' ),
            __( 'Homepage Carousel', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    /** @return int[] Ordered curated IDs from settings. */
    public static function read_ids(): array {
        $settings = (array) get_option( 'ovr_settings', [] );
        $raw      = (string) ( $settings[ self::SETTING_KEY ] ?? '' );
        return array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $raw ) ) ) );
    }

    /** Persist the ordered curated ID list, preserving unrelated settings keys. */
    public static function save_ids( array $ids ): void {
        $settings                        = (array) get_option( 'ovr_settings', [] );
        $settings[ self::SETTING_KEY ] = implode( ',', array_map( 'absint', $ids ) );
        update_option( 'ovr_settings', $settings );
    }

    public function handle_toggle(): void {
        check_admin_referer( self::TOGGLE_ACTION );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do that.', 'ovr-core' ) );
        }

        $id  = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $ids = self::read_ids();
        if ( in_array( $id, $ids, true ) ) {
            $ids = array_values( array_diff( $ids, [ $id ] ) );
        } elseif ( $id > 0 ) {
            $ids[] = $id;
        }
        self::save_ids( $ids );
        $this->redirect_back();
    }

    public function handle_move(): void {
        check_admin_referer( self::MOVE_ACTION );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do that.', 'ovr-core' ) );
        }

        $id  = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $dir = ( isset( $_GET['dir'] ) && 'down' === $_GET['dir'] ) ? 'down' : 'up';
        $ids = self::read_ids();
        $pos = array_search( $id, $ids, true );
        if ( false !== $pos ) {
            $swap = ( 'down' === $dir ) ? $pos + 1 : $pos - 1;
            if ( isset( $ids[ $swap ] ) ) {
                $tmp         = $ids[ $pos ];
                $ids[ $pos ] = $ids[ $swap ];
                $ids[ $swap ] = $tmp;
                $ids         = array_values( $ids );
            }
        }
        self::save_ids( $ids );
        $this->redirect_back();
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do that.', 'ovr-core' ) );
        }

        $q = new \WP_Query( [
            'post_type'      => 'ovr_property',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $all = array_map( 'absint', (array) $q->posts );

        if ( empty( $all ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Homepage Carousel', 'ovr-core' ) . '</h1>';
            echo '<p>' . esc_html__( 'No published properties yet. Add properties first, then choose which appear in the homepage carousel.', 'ovr-core' ) . '</p></div>';
            return;
        }

        $included     = array_values( array_intersect( self::read_ids(), $all ) );
        $included_flip = array_flip( $included );
        $rest         = array_values( array_filter( $all, fn( $id ) => ! isset( $included_flip[ $id ] ) ) );
        $rows         = array_merge( $included, $rest );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Homepage Carousel', 'ovr-core' ); ?></h1>
            <p><?php esc_html_e( 'These listings fill the curated tier of the Sponsored Property Carousel, shown after the sponsored listings. Click a listing to add or remove it; use Up / Down to reorder included ones.', 'ovr-core' ); ?></p>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th style="width:60px;"><?php esc_html_e( 'Image', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Listing', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'ID', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'In Carousel', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Order', 'ovr-core' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $rows as $id ) :
                    $is_included = in_array( $id, $included, true );
                    ?>
                    <tr>
                        <td><?php echo wp_kses_post( get_the_post_thumbnail( $id, 'thumbnail' ) ); ?></td>
                        <td><a href="<?php echo esc_url( (string) get_edit_post_link( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></td>
                        <td><?php echo esc_html( (string) $id ); ?></td>
                        <td>
                            <a class="button" href="<?php echo esc_url( add_query_arg( [
                                'action'   => self::TOGGLE_ACTION,
                                'id'       => $id,
                                '_wpnonce' => wp_create_nonce( self::TOGGLE_ACTION ),
                            ], admin_url( 'admin-post.php' ) ) ); ?>">
                                <?php echo $is_included ? esc_html__( 'Remove', 'ovr-core' ) : esc_html__( 'Add', 'ovr-core' ); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ( $is_included ) : ?>
                                <a class="button" href="<?php echo esc_url( add_query_arg( [
                                    'action'   => self::MOVE_ACTION,
                                    'id'       => $id,
                                    'dir'      => 'up',
                                    '_wpnonce' => wp_create_nonce( self::MOVE_ACTION ),
                                ], admin_url( 'admin-post.php' ) ) ); ?>">&uarr; <?php esc_html_e( 'Up', 'ovr-core' ); ?></a>
                                <a class="button" href="<?php echo esc_url( add_query_arg( [
                                    'action'   => self::MOVE_ACTION,
                                    'id'       => $id,
                                    'dir'      => 'down',
                                    '_wpnonce' => wp_create_nonce( self::MOVE_ACTION ),
                                ], admin_url( 'admin-post.php' ) ) ); ?>">&darr; <?php esc_html_e( 'Down', 'ovr-core' ); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function redirect_back(): void {
        wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
        exit;
    }
}
```

- [ ] **Step 2: Wire into `Plugin.php`:** add `use OVR\Admin\PropertyCarouselAdmin;` to the `use OVR\Admin\…` block (after line 67 `use OVR\Admin\FeaturedCities;`). In `boot_admin()`, add instantiation right after `$this->modules['admin_featured_cities'] = new FeaturedCities();` (line 258):

```php
        $this->modules['admin_property_carousel'] = new PropertyCarouselAdmin();
```

And add the `init()` call right after `$this->modules['admin_featured_cities']->init();` (line 286):

```php
        $this->modules['admin_property_carousel']->init();
```

(These are two separate blocks — instantiation lists all `new` first, then a second loop calls `->init()` on each. Add one line to each block.)

- [ ] **Step 3: Lint both files**

Run: `php -l src/Admin/PropertyCarouselAdmin.php` and `php -l src/Plugin.php`
Expected: both clean.

- [ ] **Step 4: Commit**

```bash
git add src/Admin/PropertyCarouselAdmin.php src/Plugin.php
git commit -m "feat(admin): add Homepage Carousel curation page"
```

## Task 6 — Changelog + final verification

**Files:**
- Modify: `readme.txt`

- [ ] Step 1: Add changelog:

```
= 1.2.0 =
* New OVR Property Carousel Elementor widget (sponsored first, curated + recent fill, 4/3/1 responsive, drag/dots/autoplay).
* New Homepage Carousel admin page (add/remove/reorder curated listings).
* PropertyQuery::get_carousel_ids() composes for the carousel order.
```

- [ ] **Step 2: Commit**

```bash
git add readme.txt
git commit -m "docs: changelog for sponsored property carousel"
```

Final QA checklist:
- [ ] `php -l` passes on: `src/Property/PropertyQuery.php`, `src/Elementor/Widgets/PropertyCarouselWidget.php`, `src/Elementor/ElementorIntegration.php`, `src/Admin/PropertyCarouselAdmin.php`, `src/Plugin.php`.
- [ ] `node --check` passes on `assets/js/ovr-testimonials.js`.
- [ ] WP-CLI: seed 3 active-featured + 4 recent published; `wp eval "print_r(\OVR\Property\PropertyQuery::get_carousel_ids(4));"` returns the sponsored newest-first + recent fill order. (titles elided)
- [ ] Elementor: desktop 4, tablet 3, mobile 1; drag, arrows, dots, autoplay, cards square image + id/name/type/price/size.
- [ ] Admin page add/reorder/remove/order; carousel order follows.
- [ ] Empty-state placeholder only in Elementor editor.
- [ ] Commit the plan-created changes only after QA passes.