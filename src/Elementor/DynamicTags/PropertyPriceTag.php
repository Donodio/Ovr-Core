<?php
/**
 * Elementor Dynamic Tag: Property Price.
 *
 * Outputs the current nightly rate for a property,
 * including seasonal pricing awareness.
 *
 * @package OVR\Elementor\DynamicTags
 * @since   1.0.0
 */

namespace OVR\Elementor\DynamicTags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Controls_Manager;
use OVR\Property\SeasonalPricing;
use OVR\Property\PropertyMeta;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PropertyPriceTag extends Tag {

    public function get_name(): string {
        return 'ovr_property_price';
    }

    public function get_title(): string {
        return esc_html__( 'OVR Property Price', 'ovr-core' );
    }

    public function get_group(): string {
        return 'ovr';
    }

    public function get_categories(): array {
        return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
    }

    protected function register_controls(): void {
        $this->add_control( 'price_type', [
            'label'   => esc_html__( 'Price Type', 'ovr-core' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'current',
            'options' => [
                'current' => esc_html__( 'Current Nightly Rate', 'ovr-core' ),
                'base'    => esc_html__( 'Base Price', 'ovr-core' ),
            ],
        ] );

        $this->add_control( 'prefix', [
            'label'   => esc_html__( 'Prefix', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '$',
        ] );

        $this->add_control( 'suffix', [
            'label'   => esc_html__( 'Suffix', 'ovr-core' ),
            'type'    => Controls_Manager::TEXT,
            'default' => ' / night',
        ] );
    }

    public function render(): void {
        $post_id = get_the_ID();

        if ( ! $post_id || 'ovr_property' !== get_post_type( $post_id ) ) {
            echo '';
            return;
        }

        $settings = $this->get_settings();

        if ( 'current' === $settings['price_type'] ) {
            $price = SeasonalPricing::get_current_rate( $post_id );
        } else {
            $price = (float) PropertyMeta::get( $post_id, 'base_price', 0 );
        }

        $formatted = number_format( $price, 0 );

        echo esc_html( $settings['prefix'] . $formatted . $settings['suffix'] );
    }
}
