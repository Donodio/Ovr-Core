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
 * @since   1.1.2
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

		/* CONTENT — Card elements (visibility toggles) */
		$this->start_controls_section( 'section_card_elements', [
			'label' => esc_html__( 'Card Elements', 'ovr-core' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'show_location', [
			'label'   => esc_html__( 'Village / Location', 'ovr-core' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		] );

		$this->add_control( 'show_id', [
			'label'   => esc_html__( 'Listing ID', 'ovr-core' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		] );

		$this->add_control( 'show_name', [
			'label'   => esc_html__( 'Listing Name', 'ovr-core' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		] );

		$this->add_control( 'show_type', [
			'label'   => esc_html__( 'Property Type', 'ovr-core' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		] );

		$this->add_control( 'show_bedrooms', [
			'label'   => esc_html__( 'Bedrooms', 'ovr-core' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		] );

		$this->add_control( 'show_bathrooms', [
			'label'   => esc_html__( 'Bathrooms', 'ovr-core' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		] );

		$this->add_control( 'show_size', [
			'label'   => esc_html__( 'Square Footage', 'ovr-core' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		] );

		$this->add_control( 'show_price', [
			'label'   => esc_html__( 'Price', 'ovr-core' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		] );

		$this->add_control( 'show_details', [
			'label'   => esc_html__( 'Details Link', 'ovr-core' ),
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

		/* STYLE — Card Content Area (the info panel below the image) */
		$this->start_controls_section( 'style_card_body', [
			'label' => esc_html__( 'Card Content Area', 'ovr-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'card_body_bg', [
			'label'     => esc_html__( 'Background', 'ovr-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#f4f7f7',
			'selectors' => [ '{{WRAPPER}} .ovr-pc-body' => 'background:{{VALUE}}' ],
		] );

		$this->add_control( 'card_body_gap', [
			'label'      => esc_html__( 'Gap From Image', 'ovr-core' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 12 ],
			'selectors'  => [ '{{WRAPPER}} .ovr-pc-body' => 'margin-top:{{SIZE}}{{UNIT}}' ],
		] );

		$this->add_responsive_control( 'card_body_padding', [
			'label'      => esc_html__( 'Padding', 'ovr-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'default'    => [ 'top' => '14', 'right' => '14', 'bottom' => '14', 'left' => '14', 'unit' => 'px' ],
			'selectors'  => [ '{{WRAPPER}} .ovr-pc-body' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' ],
		] );

		$this->add_control( 'card_body_radius', [
			'label'      => esc_html__( 'Border Radius', 'ovr-core' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
			'default'    => [ 'unit' => 'px', 'size' => 10 ],
			'selectors'  => [ '{{WRAPPER}} .ovr-pc-body' => 'border-radius:{{SIZE}}{{UNIT}}' ],
		] );

		$this->add_group_control( Group_Control_Border::get_type(), [
			'name'     => 'card_body_border',
			'selector' => '{{WRAPPER}} .ovr-pc-body',
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
						<?php
						$bath_display = rtrim( rtrim( number_format( (float) $data['bathrooms'], 1 ), '0' ), '.' );
						// Location-first label, matching the search/village cards:
						// the Village Name when known, else the property type.
						$loc = (string) ( $data['village'] ?? '' );

						// Elementor SWITCHER stores 'yes' when ON and '' when OFF
						// (never 'no'); unset means the control default applies.
						$show_location  = 'yes' === ( $s['show_location']  ?? 'yes' );
						$show_id        = 'yes' === ( $s['show_id']        ?? 'yes' );
						$show_name      = 'yes' === ( $s['show_name']      ?? 'yes' );
						$show_type      = 'yes' === ( $s['show_type']      ?? 'yes' );
						$show_bedrooms  = 'yes' === ( $s['show_bedrooms']  ?? 'yes' );
						$show_bathrooms = 'yes' === ( $s['show_bathrooms'] ?? 'yes' );
						$show_size      = 'yes' === ( $s['show_size']      ?? 'yes' );
						$show_price     = 'yes' === ( $s['show_price']     ?? 'yes' );
						$show_details   = 'yes' === ( $s['show_details']   ?? 'yes' );

						$has_specs = ( $show_bedrooms && (int) $data['bedrooms'] > 0 )
							|| ( $show_bathrooms && (float) $data['bathrooms'] > 0 )
							|| ( $show_size && (int) $data['sqft'] > 0 );
						$has_meta  = $show_price || $show_details;
						?>
						<article class="ovr-pc-card">
							<a class="ovr-pc-media" href="<?php echo esc_url( $data['permalink'] ); ?>">
								<div class="ovr-pc-image">
									<img src="<?php echo esc_url( $data['thumbnail'] ); ?>"
									     alt="<?php echo esc_attr( $data['title'] ); ?>"
									     loading="lazy">
								</div>
							</a>
							<div class="ovr-pc-body">
								<?php if ( $show_location || $show_id ) : ?>
									<div class="ovr-pc-top">
										<?php if ( $show_location && '' !== $loc ) : ?>
											<span class="ovr-pc-loc"><?php echo esc_html( $loc ); ?></span>
										<?php endif; ?>
										<?php if ( $show_id ) : ?>
											<span class="ovr-pc-id">#<?php echo esc_html( number_format_i18n( (int) $data['post_id'] ) ); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( $show_name ) : ?>
									<h3 class="ovr-pc-name"><a href="<?php echo esc_url( $data['permalink'] ); ?>"><?php echo esc_html( $data['title'] ); ?></a></h3>
								<?php endif; ?>
								<?php if ( $show_type && ! empty( $data['property_type'] ) && $data['property_type'] !== $loc ) : ?>
									<div class="ovr-pc-type"><?php echo esc_html( $data['property_type'] ); ?></div>
								<?php endif; ?>
								<?php if ( $has_specs ) : ?>
									<div class="ovr-pc-specs">
										<?php if ( $show_bedrooms && (int) $data['bedrooms'] > 0 ) : ?>
											<span class="ovr-pc-spec">
												<span class="material-symbols-outlined" aria-hidden="true">bed</span>
												<?php echo esc_html( (string) $data['bedrooms'] ); ?>
											</span>
										<?php endif; ?>
										<?php if ( $show_bathrooms && (float) $data['bathrooms'] > 0 ) : ?>
											<span class="ovr-pc-spec">
												<span class="material-symbols-outlined" aria-hidden="true">bathtub</span>
												<?php echo esc_html( $bath_display ); ?>
											</span>
										<?php endif; ?>
										<?php if ( $show_size && (int) $data['sqft'] > 0 ) : ?>
											<span class="ovr-pc-spec">
												<span class="material-symbols-outlined" aria-hidden="true">straighten</span>
												<?php echo esc_html( number_format_i18n( (int) $data['sqft'] ) ); ?>
											</span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( $has_meta ) : ?>
									<div class="ovr-pc-meta">
										<?php if ( $show_price ) : ?>
											<?php if ( (float) $data['base_price'] > 0 ) : ?>
												<span class="ovr-pc-price"><?php echo esc_html( $symbol . number_format_i18n( (float) $data['base_price'], 0 ) ); ?><em>/ <?php esc_html_e( 'night', 'ovr-core' ); ?></em></span>
											<?php else : ?>
												<span class="ovr-pc-size"><?php esc_html_e( 'See description for pricing', 'ovr-core' ); ?></span>
											<?php endif; ?>
										<?php endif; ?>
										<?php if ( $show_details ) : ?>
											<a class="ovr-pc-details" href="<?php echo esc_url( $data['permalink'] ); ?>"><?php esc_html_e( 'Details', 'ovr-core' ); ?><span aria-hidden="true">&nbsp;&#8250;</span></a>
										<?php endif; ?>
									</div>
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
			@media (prefers-reduced-motion: reduce){.ovr-pc-track{transition:none}.ovr-pc-card:hover .ovr-pc-image img{transform:none}.ovr-pc-arrow:hover{transform:none}.ovr-pc-dot.is-active{transform:none}}
			.ovr-pc-card{flex:0 0 calc((100% - (var(--ovr-pc-per) - 1) * 24px) / var(--ovr-pc-per));box-sizing:border-box;display:flex;flex-direction:column;background:#fff;border-radius:14px;padding:16px;cursor:grab;box-shadow:0 1px 2px rgba(16,24,40,.05),0 4px 16px rgba(16,24,40,.06);transition:box-shadow .22s ease,transform .22s ease}
			.ovr-pc-card:hover{box-shadow:0 14px 34px rgba(10,20,40,.18);transform:translateY(-4px)}
			.ovr-pc-track:active .ovr-pc-card{cursor:grabbing}
			.ovr-pc-media{display:block;text-decoration:none}
			.ovr-pc-image{aspect-ratio:4/3;border-radius:10px;overflow:hidden;background:#e8ecec;flex:0 0 auto}
			.ovr-pc-image img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s ease}
			.ovr-pc-card:hover .ovr-pc-image img{transform:scale(1.04)}
			.ovr-pc-body{display:flex;flex-direction:column;flex:0 1 auto;min-width:0;margin-top:12px;background:#f4f7f7;padding:14px;border-radius:10px}
			.ovr-pc-top{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px}
			.ovr-pc-loc{font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--ovr-secondary,#1466a8);line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
			.ovr-pc-id{font-size:11px;font-weight:600;color:#6f7979;letter-spacing:.8px;text-transform:uppercase;line-height:1.3;flex-shrink:0}
			.ovr-pc-name{margin:0 0 3px;font-size:17px;font-weight:700;line-height:1.28}
			.ovr-pc-name a{color:inherit;text-decoration:none;transition:color .15s ease}
			.ovr-pc-name a:hover{color:var(--ovr-secondary,#1466a8)}
			.ovr-pc-name a:focus-visible{outline:3px solid var(--ovr-secondary,#00a2e8);outline-offset:2px}
			.ovr-pc-type{font-size:13px;color:#3f4944;margin:2px 0 12px;line-height:1.4}
			.ovr-pc-specs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
			.ovr-pc-spec{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:8px;background:#f2f5f5;font-size:12px;font-weight:600;color:#3f4944;line-height:1.2}
			.ovr-pc-spec .material-symbols-outlined{font-size:14px;color:#6b7979}
			.ovr-pc-meta{display:flex;gap:10px;align-items:center;justify-content:space-between;border-top:1px solid rgba(128,128,128,.28);margin-top:12px;padding-top:12px;flex-wrap:wrap}
			.ovr-pc-price{font-weight:700;color:#1466a8;font-size:17px;line-height:1.2}
			.ovr-pc-price em{font-style:normal;font-size:12px;font-weight:500;color:inherit;opacity:.72}
			.ovr-pc-size{font-size:13px;color:#6b7979}
			.ovr-pc-details{display:inline-flex;align-items:center;font-size:13px;font-weight:700;color:var(--ovr-secondary,#1466a8);text-decoration:none;transition:color .15s ease}
			.ovr-pc-details:hover{text-decoration:underline}
			.ovr-pc-details:focus-visible{outline:3px solid var(--ovr-secondary,#00a2e8);outline-offset:2px}
			.ovr-pc-arrow{flex:0 0 auto;width:44px;height:44px;border:none;border-radius:50%;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.12);font-size:26px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .15s ease,opacity .15s ease}
			.ovr-pc-arrow:hover{transform:scale(1.08)}
			.ovr-pc-arrow:focus-visible{outline:3px solid var(--ovr-secondary,#00a2e8);outline-offset:2px}
			.ovr-pc-arrow:disabled{opacity:.35;cursor:default}
			.ovr-pc-dots{display:flex;justify-content:center;gap:8px;margin-top:22px}
			.ovr-pc-dot{width:9px;height:9px;border:none;border-radius:50%;background:#bec9c8;padding:0;cursor:pointer;transition:transform .15s ease,background .15s ease}
			.ovr-pc-dot:hover{background:#8fa3a3}
			.ovr-pc-dot:focus-visible{outline:3px solid var(--ovr-secondary,#00a2e8);outline-offset:2px}
			.ovr-pc-dot.is-active{background:#006676;transform:scale(1.25)}
			@media (max-width:768px){.ovr-pc-arrow{display:none}}
		</style>
		<?php
	}
}