<?php
/**
 * Homepage Template.
 *
 * @var WP_Query $featured_properties
 * @var array    $slider_cards  Real boosted-listing cards (empty → static demo).
 * @var array    $villages WP_Term[]
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use OVR\Core\Pages;

$slider_cards = $slider_cards ?? [];

$search_url    = Pages::get_page_url( 'ovr_page_search' );
$register_url  = Pages::get_page_url( 'ovr_page_register' );
$featured_url  = Pages::get_page_url( 'ovr_page_featured' );

$hero_image = 'https://lh3.googleusercontent.com/aida-public/AB6AXuDNBGqlk9yFUDZZCmqhdgUmDQ8MM2PjBSYgtB2niQK5_3kPGTdyf-daWGpU2a_F57FJVx9B4yIi0ZuWZabo_6ZeN9F0dzCmLvfI-97LMX6YfP3h6bZYgjUuhlyEstHWzBLtGDl7jJ3vqOUMfS3OjAZjxT9bULgywWWO1GKT4uQXy-mqyl3xN9kpnylhFyaBF4smSQZ1yzWcmq6S0o--_wXcub5jBBd5Eu6BpaDgZmgiUjFXkb82IF83Qc62pDQtTIiXDGv5Wx0MTDo';

$village_cards = [
    [
        'name'  => __( 'Spanish Springs', 'ovr-core' ),
        'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuC1YsABaBy8WoSIubfYsTyuqB7NW4wTFqh-j5wV_88XOwrqJ5r3dH5JIbi8-sOR_GGWleTF8R-yqqRgm3nxkn8FUkMtMqs8IYpUiM0tNU3beMOQOwCNqYXUcGteHwXsZoserQe9gSVOgebZRpzdOpurn70JqWTO8-CTfSAUX-khoyATK2Fdhs92pzKx2eiSnTLbybaRvtCQKAfbrsG0UmT5HR6p0QVV6mkqDsL1_NnuPJKPBpBTyYLIJkp3HYxG4xlSJ1jSpHVIRJA',
    ],
    [
        'name'  => __( 'Lake Sumter Landing', 'ovr-core' ),
        'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCqTrKzoFWLVrTX1HaQEIvRXvpdIVVQ8zVu1rdrbiElbtGu9sWNOyDWSQ5e4rhmmgvpeeFldV8ywrKTlVcADYpYIY__pd6M4eKJmDF1VY_y52oH6ALHseplnGAZTvyXvUyWU8SckB8P1dK8AWDAZ1PsvfnzjvO282YUYIqPNeTFgp7wgNQxyOxhWGglRygmqEj2_W8q4MJ1ln77K5r5mAW87Nn04B7KoM3LJJ_iUcH-QE5UY-q3yz1KcJZuT5RzlA0MjCqrka0j9po',
    ],
    [
        'name'  => __( 'Brownwood', 'ovr-core' ),
        'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuD7Rry-5U3dUW-EV1UkaaZwOmnmf1YOqMs4-RyT5v3X79Mcvp1elcz_BEtqM6--l29C_4WncFg01UNcyoekAheJ5YOsSRUBh0AtHr8CSdjqeVzz5QFLIBHWairGsAmboqcV4jkuK7ePPRZNbnOfA-UmqQekaWAIRvtYD_tbUqyqupEcsbOcSkULJe91xeP40Awf0Dnixq4z_ednVxwxAzPIoLwlB1CPfPoXyAN1cN-WEM8-rkogUiso49UEF9fRyUt01HrOvHG3C9c',
    ],
    [
        'name'  => __( 'Sawgrass', 'ovr-core' ),
        'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDiEtvuNzzDvPZ40zz9dzi1lo36EXIrlW4ab7yZFkCPlou3gVM3_7A4547cGV1CwMmls5jyNUx0iM-TGM6RgP8T2b_LyJ4o7-MOX6D_d9K_I6nBiUIgtqpc8nwvH2MjT7G2GuVajUXzPudYZ1QrE5sbnwmyarG2IVFZGAz7P8P-1NZDXIZAyzabBJPDZAnAUc15grnSXWo2Dk3LXyIF8oGbzvBOAUAw4AKFSAw0O8zKDLqnsefGwGFENprSFpfmRRouxk471uLK-kg',
    ],
    [
        'name'  => __( 'Eastport', 'ovr-core' ),
        'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuClP_zhhYZZr2clqU1AjxE0_iWBAUvCxN4ENAlZ3ksCrrywrxBmlWN8mqjji9jiMvSLdTCVqKfUgHk6VP34f-Hkch6dqWkqRORJmCdlOn8tols0lqrNyly5fDuxEMut20oZs2ReVrf_2DRkGJ3mhFw-yCGgXMwIZHlQ64_k8hAk-0oqYBDl4ikXw1LGf6a6gAZJ27FM3lfnQGJVHwUwg9vbV-yqhVbIpKYGaMxjJPIGdUXDXzSiTjh_Oxn9uu6cnf3NKRxiC1OFj8w',
    ],
];

$featured_cards = [
    [
        'image'        => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAkamtI0VGhzFiNIgUzpS1XTLZMi-MFPnfY2ue8twKIdv8r7pbshzOy5RZ-D6-28B8RVvg5d9Q3WLO8wFP2DuSujDIClb1hehqgLxuXbNwrWQJHgDMA3qg50HUYOgX9Kc3MBgq9LsGSpZem7PZ9jaB2HkUACn-8KGiKQHk7ZdzjUijEuXBEU3R-DBCzNnEjOql31QN6vYf3RXmf5w4P7HFx1ZP-oDfLnusVz0gDhVxnPlJp99ToKuVbrWU0VJeUdnWgb4l3jVJDQqY',
        'title'        => __( 'Village of Caroline', 'ovr-core' ),
        'id'           => '8472',
        'details'      => __( '2 Bed, 2 Bath • Courtyard Villa', 'ovr-core' ),
        'availability' => __( 'Available: Jan-Mar Term', 'ovr-core' ),
        'price'        => __( '$4,500 / month', 'ovr-core' ),
    ],
    [
        'image'        => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBBlDYeUe41OaVeNzmu1rrhQU7GZyOVXdNYzYqDnBmDHpy7XJXqXztGBFhSdmAfupthVlVQzixmjBc6Yx6hdtkSQTR_01YmUHsPOZsdv96UOXQFHhT3N15Xski-6VE0mq5OgyC2TjmDM8QlDInY1TloRn9YlFW_kC9PrzRG01NduU1Nl2xnv4ylgH5DFUEL8HmAjvLfD1VypUlEShZ77zLdcVZIEKmP-apuWcDSh2IECfl9vKnnE9HO-S0XTvwYLs-XvRjhRyBnqgE',
        'title'        => __( 'Village of Fenney', 'ovr-core' ),
        'id'           => '9104',
        'details'      => __( '3 Bed, 2 Bath • Designer Home', 'ovr-core' ),
        'availability' => __( 'Available: Year-Round Long Term', 'ovr-core' ),
        'price'        => __( '$2,800 / month', 'ovr-core' ),
    ],
    [
        'image'        => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAv5t0lF-dy1YJDy3Q5awAVLs7hXZO6M2s4__WkITlSt8LqEBaujVdNvK21qRN8_I3KXXU0N-a3v-9qaDCHKL43YycAqZjEDdPky2UXIWpWM09SWSxNDID81DGNo_OU6ngTpkpCoOHn055ePaCimlVKTJG5TJsekx-RNU0qtfnFAarpuVJEG_TBm2uGj5x_iZ2gSPL8Et5bcKrD1ZcvlsEuKF3K4lVNKmH2AXDjgx2Z7h0AcIl4taeNKfnAhCvD57eDPDiXmfN07WE',
        'title'        => __( 'Village of Bridgeport', 'ovr-core' ),
        'id'           => '7552',
        'details'      => __( '3 Bed, 3 Bath • Premier Home', 'ovr-core' ),
        'availability' => __( 'Available: Oct-Dec', 'ovr-core' ),
        'price'        => __( '$5,200 / month', 'ovr-core' ),
    ],
];
?>
<div class="antialiased min-h-screen flex flex-col bg-soft-page-white text-on-surface font-body-md">
    <?php // The single site-wide header is rendered by OVR\Frontend\Header on wp_body_open, above this shortcode's output — no inline header here. ?>

    <main class="flex-grow">
        <section class="relative w-full h-[600px] flex items-center justify-center overflow-hidden bg-primary-container">
            <img alt="<?php esc_attr_e( 'A sunny, vibrant daytime view of a bustling town square in a lively senior community.', 'ovr-core' ); ?>" class="absolute inset-0 w-full h-full object-cover opacity-60 mix-blend-overlay" src="<?php echo esc_url( $hero_image ); ?>">
            <div class="relative z-10 max-w-container-max-width px-margin-desktop text-center">
                <h1 class="text-headline-lg-mobile md:text-headline-lg font-headline-lg-mobile md:font-headline-lg text-white mb-6 drop-shadow-md"><?php esc_html_e( 'Rental Homes in The Villages, Florida', 'ovr-core' ); ?></h1>
                <p class="text-body-lg font-body-lg text-white max-w-2xl mx-auto mb-10 drop-shadow-md"><?php esc_html_e( 'Owner-direct rentals, seasonal stays, and monthly homes. Connect directly with landlords in our community.', 'ovr-core' ); ?></p>
                <div class="flex flex-col sm:flex-row gap-6 justify-center max-w-3xl mx-auto">
                    <div class="bg-surface rounded-lg p-6 shadow-lg flex-1 border border-border-gray flex flex-col items-center text-center transform transition-transform hover:-translate-y-1">
                        <span class="material-symbols-outlined text-4xl text-secondary mb-4" style="font-variation-settings: 'FILL' 1;">search</span>
                        <h2 class="text-card-title font-card-title text-on-surface mb-2"><?php esc_html_e( 'Find a Rental', 'ovr-core' ); ?></h2>
                        <p class="text-body-md font-body-md text-muted-text mb-6"><?php esc_html_e( 'Browse our extensive directory of seasonal and long-term homes.', 'ovr-core' ); ?></p>
                        <a class="mt-auto w-full bg-primary-container text-white text-label-md font-label-md py-3 rounded h-tap-target-min hover:opacity-90 transition-opacity" href="<?php echo esc_url( $search_url ); ?>"><?php esc_html_e( 'Search Now', 'ovr-core' ); ?></a>
                    </div>
                    <div class="bg-surface rounded-lg p-6 shadow-lg flex-1 border border-border-gray flex flex-col items-center text-center transform transition-transform hover:-translate-y-1">
                        <span class="material-symbols-outlined text-4xl text-featured-gold mb-4" style="font-variation-settings: 'FILL' 1;">home</span>
                        <h2 class="text-card-title font-card-title text-on-surface mb-2"><?php esc_html_e( 'List My Property', 'ovr-core' ); ?></h2>
                        <p class="text-body-md font-body-md text-muted-text mb-6"><?php esc_html_e( 'Reach thousands of renters looking for homes in our community.', 'ovr-core' ); ?></p>
                        <a class="mt-auto w-full bg-surface text-primary-container border border-primary-container text-label-md font-label-md py-3 rounded h-tap-target-min hover:bg-surface-container-low transition-colors" href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Start Listing', 'ovr-core' ); ?></a>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-16 bg-surface-container-low border-b border-border-gray">
            <div class="max-w-container-max-width mx-auto px-margin-desktop text-center">
                <h2 class="text-headline-md-mobile md:text-headline-md font-headline-md-mobile md:font-headline-md text-on-surface mb-4"><?php esc_html_e( 'Who We Are', 'ovr-core' ); ?></h2>
                <p class="text-body-lg font-body-lg text-on-surface-variant max-w-4xl mx-auto"><?php esc_html_e( 'Serving landlords and renters since 2013, OVR is the premier local platform dedicated to connecting property owners with reliable renters in The Villages community. We pride ourselves on offering an authentic, owner-direct experience, fostering trust and clarity without the corporate overhead of global marketplaces.', 'ovr-core' ); ?></p>
            </div>
        </section>

        <section class="py-12 max-w-container-max-width mx-auto px-margin-desktop overflow-hidden">
            <h2 class="text-headline-md-mobile md:text-headline-md font-headline-md-mobile md:font-headline-md text-on-surface mb-8"><?php esc_html_e( 'Explore The Villages', 'ovr-core' ); ?></h2>
            <div class="flex overflow-x-auto pb-6 gap-6 snap-x snap-mandatory hide-scrollbar">
                <?php foreach ( $village_cards as $card ) : ?>
                    <div class="min-w-[250px] flex-shrink-0 snap-start group cursor-pointer">
                        <div class="h-40 rounded-lg overflow-hidden mb-3 relative shadow-sm border border-border-gray">
                            <img alt="<?php echo esc_attr( $card['name'] ); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" src="<?php echo esc_url( $card['image'] ); ?>">
                        </div>
                        <h3 class="text-label-md font-label-md text-on-surface text-center"><?php echo esc_html( $card['name'] ); ?></h3>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="py-16 bg-surface-container-lowest border-t border-b border-border-gray">
            <div class="max-w-container-max-width mx-auto px-margin-desktop">
                <div class="flex justify-between items-end mb-8">
                    <h2 class="text-headline-md-mobile md:text-headline-md font-headline-md-mobile md:font-headline-md text-on-surface"><?php esc_html_e( 'Featured Rentals', 'ovr-core' ); ?></h2>
                    <a class="text-label-md font-label-md text-secondary hover:underline flex items-center gap-1" href="<?php echo esc_url( $featured_url ); ?>"><?php esc_html_e( 'View All', 'ovr-core' ); ?> <span class="material-symbols-outlined text-sm">arrow_forward</span></a>
                </div>
                <?php
                // Prefer real boosted listings (Homepage Slider upgrade); fall
                // back to the static demo cards only when none are boosted yet.
                $cards     = ! empty( $slider_cards ) ? $slider_cards : $featured_cards;
                $is_linked = ! empty( $slider_cards );
                ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ( $cards as $card ) :
                        $card_url = $is_linked && ! empty( $card['permalink'] ) ? $card['permalink'] : '';
                        $tag      = $card_url ? 'a' : 'div';
                    ?>
                        <<?php echo $tag; ?> <?php if ( $card_url ) : ?>href="<?php echo esc_url( $card_url ); ?>"<?php endif; ?> class="bg-surface rounded-lg border border-featured-gold overflow-hidden shadow-sm flex flex-col relative group">
                            <div class="absolute top-4 left-4 bg-featured-gold text-ink-text px-3 py-1 rounded-sm text-metadata font-metadata font-bold z-10 shadow-sm flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span> <?php esc_html_e( 'Featured', 'ovr-core' ); ?>
                            </div>
                            <div class="h-48 relative overflow-hidden">
                                <img alt="<?php echo esc_attr( $card['title'] ?? __( 'Property Image', 'ovr-core' ) ); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="<?php echo esc_url( $card['image'] ); ?>">
                            </div>
                            <div class="p-5 flex-grow flex flex-col">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-card-title font-card-title text-on-surface"><?php echo esc_html( $card['title'] ); ?></h3>
                                    <span class="text-metadata font-metadata text-muted-text"><?php echo esc_html( 'ID: ' . $card['id'] ); ?></span>
                                </div>
                                <p class="text-body-md font-body-md text-on-surface-variant mb-4"><?php echo esc_html( $card['details'] ); ?></p>
                                <div class="mt-auto bg-surface-container-low p-3 rounded border border-border-gray">
                                    <p class="text-metadata font-metadata text-secondary font-semibold"><?php echo esc_html( $card['availability'] ); ?></p>
                                    <p class="text-body-md font-body-md text-on-surface mt-1"><?php echo esc_html( $card['price'] ); ?></p>
                                </div>
                            </div>
                        </<?php echo $tag; ?>>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="py-16 max-w-container-max-width mx-auto px-margin-desktop">
            <h2 class="text-headline-md-mobile md:text-headline-md font-headline-md-mobile md:font-headline-md text-on-surface mb-8 text-center"><?php esc_html_e( 'Helpful Resources', 'ovr-core' ); ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a class="bg-surface border border-border-gray rounded-lg p-6 flex flex-col items-center text-center hover:bg-surface-container-low transition-colors shadow-sm" href="<?php echo esc_url( home_url( '/villages-info/' ) ); ?>">
                    <span class="material-symbols-outlined text-4xl text-primary-container mb-3">info</span>
                    <h3 class="text-card-title font-card-title text-on-surface mb-2"><?php esc_html_e( 'Villages Info', 'ovr-core' ); ?></h3>
                    <p class="text-body-md font-body-md text-muted-text"><?php esc_html_e( 'Learn about amenities, town squares, and community lifestyle.', 'ovr-core' ); ?></p>
                </a>
                <a class="bg-surface border border-border-gray rounded-lg p-6 flex flex-col items-center text-center hover:bg-surface-container-low transition-colors shadow-sm" href="<?php echo esc_url( home_url( '/faqs/' ) ); ?>">
                    <span class="material-symbols-outlined text-4xl text-primary-container mb-3">help</span>
                    <h3 class="text-card-title font-card-title text-on-surface mb-2"><?php esc_html_e( 'FAQ', 'ovr-core' ); ?></h3>
                    <p class="text-body-md font-body-md text-muted-text"><?php esc_html_e( 'Answers to common questions for both renters and landlords.', 'ovr-core' ); ?></p>
                </a>
                <a class="bg-surface border border-border-gray rounded-lg p-6 flex flex-col items-center text-center hover:bg-surface-container-low transition-colors shadow-sm" href="<?php echo esc_url( home_url( '/pdf-updates/' ) ); ?>">
                    <span class="material-symbols-outlined text-4xl text-primary-container mb-3">description</span>
                    <h3 class="text-card-title font-card-title text-on-surface mb-2"><?php esc_html_e( 'PDF Updates', 'ovr-core' ); ?></h3>
                    <p class="text-body-md font-body-md text-muted-text"><?php esc_html_e( 'Downloadable guides, seasonal newsletters, and rental forms.', 'ovr-core' ); ?></p>
                </a>
            </div>
        </section>
    </main>
    <?php // The single site-wide footer is rendered by the theme's footer.php via get_footer() below this shortcode's output — no inline footer here. ?>
</div>
<style>
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
