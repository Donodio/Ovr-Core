<?php
/**
 * Elementor Villages Slider Widget.
 *
 * Displays the `ovr_village` taxonomy terms as a horizontal snap-scroll
 * slider (or grid) of image cards, linking through to each village.
 *
 * Image resolution order per village term:
 *   1. Attachment ID in term meta `ovr_village_image_id`.
 *   2. Plain URL in term meta `ovr_village_image`.
 *   3. Slug-keyed default map (filter: `ovr_village_default_images`).
 *   4. Bundled placeholder image.
 *
 * @package OVR\Elementor\Widgets
 * @since   1.0.0
 */

namespace OVR\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VillagesSliderWidget extends Widget_Base {

    public function get_name(): string {
        return 'ovr_villages_slider';
    }

    public function get_title(): string {
        return esc_html__( 'OVR Villages Slider', 'ovr-core' );
    }

    public function get_icon(): string {
        return 'eicon-slider-album';
    }

    public function get_categories(): array {
        return [ 'ovr-widgets' ];
    }

    public function get_keywords(): array {
        return [ 'village', 'slider', 'taxonomy', 'explore', 'ovr' ];
    }

    protected function register_controls(): void {

        $this->start_controls_section( 'content_section', [
            'label' => esc_html__( 'Villages', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'layout', [
            'label'   => esc_html__( 'Layout', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'slider',
            'options' => [
                'slider' => esc_html__( 'Horizontal Slider', 'ovr-core' ),
                'grid'   => esc_html__( 'Grid', 'ovr-core' ),
            ],
        ] );

        $this->add_control( 'count', [
            'label'   => esc_html__( 'Number of Villages', 'ovr-core' ),
            'type'    => Controls_Manager::NUMBER,
            'default' => 6,
            'min'     => 1,
            'max'     => 24,
        ] );

        $this->add_control( 'orderby', [
            'label'   => esc_html__( 'Order By', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'count',
            'options' => [
                'count' => esc_html__( 'Listing Count', 'ovr-core' ),
                'name'  => esc_html__( 'Name', 'ovr-core' ),
            ],
        ] );

        $this->add_control( 'order', [
            'label'   => esc_html__( 'Order', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'DESC',
            'options' => [
                'DESC' => esc_html__( 'Descending', 'ovr-core' ),
                'ASC'  => esc_html__( 'Ascending', 'ovr-core' ),
            ],
        ] );

        $this->add_control( 'hide_empty', [
            'label'        => esc_html__( 'Hide Villages With No Listings', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
        ] );

        $this->add_control( 'show_count', [
            'label'        => esc_html__( 'Show Listing Count', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $terms = get_terms( [
            'taxonomy'   => 'ovr_village',
            'hide_empty' => 'yes' === ( $settings['hide_empty'] ?? '' ),
            'number'     => absint( $settings['count'] ),
            'orderby'    => 'name' === $settings['orderby'] ? 'name' : 'count',
            'order'      => 'ASC' === $settings['order'] ? 'ASC' : 'DESC',
        ] );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            echo '<div class="ovr-wrap" style="text-align:center;padding:32px">';
            echo '<span class="material-symbols-outlined" style="font-size:40px;color:var(--ovr-outline-variant)">holiday_village</span>';
            echo '<p style="color:var(--ovr-on-surface-variant);margin-top:8px">' . esc_html__( 'No villages found yet.', 'ovr-core' ) . '</p>';
            echo '</div>';
            return;
        }

        $is_grid    = 'grid' === $settings['layout'];
        $show_count = 'yes' === ( $settings['show_count'] ?? '' );
        $container  = $is_grid ? 'ovr-villages-grid' : 'ovr-villages-slider';

        echo '<div class="ovr-wrap"><div class="' . esc_attr( $container ) . '">';

        foreach ( $terms as $term ) {
            $link = get_term_link( $term );
            $url  = ( ! is_wp_error( $link ) ) ? $link : '#';
            $img  = $this->get_village_image( $term );
            ?>
            <a href="<?php echo esc_url( $url ); ?>" class="ovr-village-card">
                <div class="ovr-village-card-image">
                    <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy">
                </div>
                <h3><?php echo esc_html( $term->name ); ?></h3>
                <?php if ( $show_count ) : ?>
                    <span class="ovr-village-card-count">
                        <?php
                        printf(
                            /* translators: %s: number of rental listings. */
                            esc_html( _n( '%s rental', '%s rentals', (int) $term->count, 'ovr-core' ) ),
                            esc_html( number_format_i18n( $term->count ) )
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php
        }

        echo '</div></div>';
    }

    /**
     * Resolve a display image URL for a village term.
     *
     * @param \WP_Term $term Village term.
     * @return string Image URL.
     */
    private function get_village_image( \WP_Term $term ): string {
        $att_id = (int) get_term_meta( $term->term_id, 'ovr_village_image_id', true );
        if ( $att_id ) {
            $url = wp_get_attachment_image_url( $att_id, 'large' );
            if ( $url ) {
                return $url;
            }
        }

        $url = get_term_meta( $term->term_id, 'ovr_village_image', true );
        if ( is_string( $url ) && '' !== $url ) {
            return $url;
        }

        $map = apply_filters( 'ovr_village_default_images', self::default_image_map() );
        if ( isset( $map[ $term->slug ] ) ) {
            return $map[ $term->slug ];
        }

        return OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';
    }

    /**
     * Default slug-keyed image map for the well-known Villages town squares.
     *
     * Thin wrapper around the canonical map in {@see SearchFilters}, kept so
     * the widget's existing call site stays unchanged.
     *
     * @return array<string,string>
     */
    private static function default_image_map(): array {
        return \OVR\Search\SearchFilters::default_village_images();
    }
}
