<?php
/**
 * Elementor Integration.
 *
 * Registers OVR custom widgets and dynamic tags with Elementor.
 * Only loaded when Elementor is active.
 *
 * @package OVR\Elementor
 * @since   1.0.0
 */

namespace OVR\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ElementorIntegration {

    /**
     * Initialize Elementor integration hooks.
     */
    public function init(): void {
        // Register custom widget category.
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );

        // Register widgets.
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );

        // Register dynamic tags.
        add_action( 'elementor/dynamic_tags/register', [ $this, 'register_dynamic_tags' ] );

        // Enqueue editor styles.
        add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'editor_styles' ] );

        // Enqueue preview styles.
        add_action( 'elementor/preview/enqueue_styles', [ $this, 'preview_styles' ] );
    }

    /**
     * Register OVR widget category in Elementor panel.
     *
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function register_category( $elements_manager ): void {
        $elements_manager->add_category( 'ovr-widgets', [
            'title' => esc_html__( 'OVR — Our Villages Rentals', 'ovr-core' ),
            'icon'  => 'eicon-home',
        ] );
    }

    /**
     * Register all OVR Elementor widgets.
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets( $widgets_manager ): void {
        // Include widget files.
        require_once OVR_PLUGIN_DIR . 'src/Elementor/Widgets/PropertyCardWidget.php';
        require_once OVR_PLUGIN_DIR . 'src/Elementor/Widgets/SearchBarWidget.php';
        require_once OVR_PLUGIN_DIR . 'src/Elementor/Widgets/PricingTableWidget.php';
        require_once OVR_PLUGIN_DIR . 'src/Elementor/Widgets/HeroSliderWidget.php';
        require_once OVR_PLUGIN_DIR . 'src/Elementor/Widgets/TestimonialsWidget.php';
        require_once OVR_PLUGIN_DIR . 'src/Elementor/Widgets/VillagesSliderWidget.php';

        // Register widgets.
        $widgets_manager->register( new Widgets\PropertyCardWidget() );
        $widgets_manager->register( new Widgets\SearchBarWidget() );
        $widgets_manager->register( new Widgets\PricingTableWidget() );
        $widgets_manager->register( new Widgets\HeroSliderWidget() );
        $widgets_manager->register( new Widgets\TestimonialsWidget() );
        $widgets_manager->register( new Widgets\VillagesSliderWidget() );
    }

    /**
     * Register OVR dynamic tags.
     *
     * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager
     */
    public function register_dynamic_tags( $dynamic_tags_manager ): void {
        // Register OVR tag group.
        $dynamic_tags_manager->register_group( 'ovr', [
            'title' => esc_html__( 'OVR Properties', 'ovr-core' ),
        ] );

        require_once OVR_PLUGIN_DIR . 'src/Elementor/DynamicTags/PropertyPriceTag.php';

        $dynamic_tags_manager->register( new DynamicTags\PropertyPriceTag() );
    }

    /**
     * Enqueue styles in Elementor editor.
     */
    public function editor_styles(): void {
        wp_enqueue_style(
            'ovr-elementor-editor',
            OVR_PLUGIN_URL . 'assets/css/ovr-public.css',
            [],
            OVR_VERSION
        );
    }

    /**
     * Enqueue styles in Elementor preview.
     */
    public function preview_styles(): void {
        wp_enqueue_style(
            'ovr-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            OVR_VERSION
        );

        wp_enqueue_style(
            'ovr-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap',
            [],
            OVR_VERSION
        );
    }
}
