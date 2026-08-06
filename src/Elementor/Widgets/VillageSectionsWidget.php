<?php
/**
 * Elementor Village Sections Widget.
 *
 * Renders the "Village Sections" homepage cards from the sections an admin has
 * enabled + ordered (option `ovr_village_sections`, managed via the OVR →
 * Village Sections admin screen). Each card shows an image, the section name,
 * the number of available rentals, and links through to that section's search.
 *
 * Falls back to all populated villages in count order when nothing has been
 * configured yet.
 *
 * @package OVR\Elementor\Widgets
 * @since   1.2.0
 */

namespace OVR\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use OVR\Admin\VillageSectionsAdmin;
use OVR\Search\SearchFilters;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VillageSectionsWidget extends Widget_Base {

    public function get_name(): string {
        return 'ovr_village_sections';
    }

    public function get_title(): string {
        return esc_html__( 'OVR Village Sections', 'ovr-core' );
    }

    public function get_icon(): string {
        return 'eicon-gallery-grid';
    }

    public function get_categories(): array {
        return [ 'ovr-widgets' ];
    }

    public function get_keywords(): array {
        return [ 'village', 'section', 'cards', 'homepage', 'ovr' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => esc_html__( 'Villages', 'ovr-core' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'columns', [
            'label'   => esc_html__( 'Columns', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '3',
            'options' => [
                '2' => esc_html__( '2', 'ovr-core' ),
                '3' => esc_html__( '3', 'ovr-core' ),
                '4' => esc_html__( '4', 'ovr-core' ),
            ],
        ] );

        $this->add_control( 'show_count', [
            'label'        => esc_html__( 'Show Listing Count', 'ovr-core' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $terms = VillageSectionsAdmin::get_enabled_terms();
        if ( empty( $terms ) ) {
            // Nothing configured yet — fall back to the populated sections.
            $terms = $this->fallback_terms();
        }
        if ( empty( $terms ) ) {
            echo '<div class="ovr-wrap" style="text-align:center;padding:32px">';
            echo '<span class="material-symbols-outlined" style="font-size:40px;color:var(--ovr-outline-variant)">holiday_village</span>';
            echo '<p style="color:var(--ovr-on-surface-variant);margin-top:8px">' . esc_html__( 'No village sections found yet.', 'ovr-core' ) . '</p>';
            echo '</div>';
            return;
        }

        $cols       = (string) ( $settings['columns'] ?? '3' );
        $show_count = 'yes' === ( $settings['show_count'] ?? 'yes' );
        $grid_class = 'ovr-vs-grid ovr-vs-grid--' . $cols;

        echo '<div class="ovr-wrap"><div class="' . esc_attr( $grid_class ) . '">';

        foreach ( $terms as $term ) {
            $link = get_term_link( $term );
            $url  = ( ! is_wp_error( $link ) ) ? $link : '#';
            $img  = SearchFilters::get_village_image( $term );
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
     * Populated sections as a sensible default when nothing has been curated.
     *
     * @return array<int,\WP_Term>
     */
    private function fallback_terms(): array {
        $terms = get_terms( [
            'taxonomy'   => 'ovr_village',
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );
        return is_wp_error( $terms ) ? [] : $terms;
    }
}