<?php
/**
 * Footer Component.
 *
 * Site-wide footer with brand, link columns, and copyright.
 *
 * @package OVR
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\Pages;

$year      = (int) current_time( 'Y' );
$site_name = get_bloginfo( 'name' ) ?: __( 'Our Villages Rentals', 'ovr-core' );

$footer_links = [
    [ 'label' => __( 'About Us', 'ovr-core' ),         'url' => home_url( '/about/' ) ],
    [ 'label' => __( 'Careers', 'ovr-core' ),          'url' => home_url( '/careers/' ) ],
    [ 'label' => __( 'Terms of Service', 'ovr-core' ), 'url' => home_url( '/terms/' ) ],
    [ 'label' => __( 'Privacy Policy', 'ovr-core' ),   'url' => home_url( '/privacy/' ) ],
    [ 'label' => __( 'Contact Support', 'ovr-core' ),  'url' => home_url( '/contact/' ) ],
    [ 'label' => __( 'Trust & Safety', 'ovr-core' ),   'url' => home_url( '/trust-safety/' ) ],
];
?>
<footer class="ovr-footer" role="contentinfo">
    <div class="ovr-footer-brand">
        <?php echo esc_html( $site_name ); ?>
    </div>

    <nav class="ovr-footer-links" aria-label="<?php esc_attr_e( 'Footer navigation', 'ovr-core' ); ?>">
        <?php foreach ( $footer_links as $link ) : ?>
            <a href="<?php echo esc_url( $link['url'] ); ?>">
                <?php echo esc_html( $link['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="ovr-footer-copy">
        <?php
        printf(
            /* translators: 1: current year, 2: site name */
            esc_html__( '© %1$d %2$s. All rights reserved.', 'ovr-core' ),
            esc_html( $year ),
            esc_html( $site_name )
        );
        ?>
    </div>
</footer>
