<?php
/**
 * Overview tab — welcome + announcement, KPI stats, and the landlord's listings.
 *
 * @package OVR
 * @var array    $stats
 * @var array    $properties
 * @var \WP_User $user
 * @var string   $base_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\Pages;

$add_url     = admin_url( 'post-new.php?post_type=ovr_property' );
$pricing_url = Pages::get_page_url( 'ovr_page_pricing' );
$properties  = $properties ?? [];
$stats       = $stats ?? [];
?>

<!-- Welcome + announcement -->
<section class="ovr-dash-welcome">
    <div>
        <h1 class="ovr-dash-page-title"><?php esc_html_e( 'Landlord Dashboard', 'ovr-core' ); ?></h1>
        <p class="ovr-dash-subtitle"><?php esc_html_e( 'Manage your properties and track performance.', 'ovr-core' ); ?></p>
    </div>
    <div class="ovr-dash-announce">
        <span class="material-symbols-outlined">campaign</span>
        <div>
            <h4><?php esc_html_e( 'Site Announcement', 'ovr-core' ); ?></h4>
            <p>
                <?php esc_html_e( 'Winter 2025 seasonal pricing guidelines are now available.', 'ovr-core' ); ?>
                <a href="#"><?php esc_html_e( 'Read more', 'ovr-core' ); ?></a>
            </p>
        </div>
    </div>
</section>

<!-- Stats overview -->
<section class="ovr-dash-stats">
    <?php
    $cards = [
        [ 'label' => __( 'Active Listings', 'ovr-core' ), 'value' => $stats['active_properties'] ?? 0, 'icon' => 'real_estate_agent',  'tone' => 'navy' ],
        [ 'label' => __( 'New Inquiries',   'ovr-core' ), 'value' => $stats['new_inquiries']     ?? 0, 'icon' => 'mark_email_unread', 'tone' => 'blue' ],
        [ 'label' => __( 'Total Inquiries', 'ovr-core' ), 'value' => $stats['total_inquiries']   ?? 0, 'icon' => 'mail',              'tone' => 'gray' ],
    ];
    foreach ( $cards as $c ) : ?>
        <div class="ovr-stat-card">
            <div>
                <p class="ovr-stat-label"><?php echo esc_html( $c['label'] ); ?></p>
                <p class="ovr-stat-value"><?php echo esc_html( number_format( (int) $c['value'] ) ); ?></p>
            </div>
            <div class="ovr-stat-icon ovr-stat-icon--<?php echo esc_attr( $c['tone'] ); ?>">
                <span class="material-symbols-outlined"><?php echo esc_html( $c['icon'] ); ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<!-- My Listings -->
<section class="ovr-dash-listings">
    <div class="ovr-dash-listings-head">
        <h2><?php esc_html_e( 'My Listings', 'ovr-core' ); ?></h2>
        <a href="<?php echo esc_url( $add_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-sm">
            <span class="material-symbols-outlined">add</span>
            <?php esc_html_e( 'New Listing', 'ovr-core' ); ?>
        </a>
    </div>

    <?php if ( empty( $properties ) ) : ?>
        <div class="ovr-dash-empty">
            <span class="material-symbols-outlined">add_home</span>
            <p><?php esc_html_e( 'No listings yet. Create your first listing to start receiving inquiries.', 'ovr-core' ); ?></p>
            <a href="<?php echo esc_url( $add_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-pill">
                <?php esc_html_e( 'List a Property', 'ovr-core' ); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="ovr-dash-listing-list">
            <?php foreach ( $properties as $p ) :
                $thumb       = get_the_post_thumbnail_url( $p->ID, 'medium' ) ?: OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';
                $beds        = (int) get_post_meta( $p->ID, '_ovr_bedrooms', true );
                $baths_raw   = (float) get_post_meta( $p->ID, '_ovr_bathrooms', true );
                $baths       = rtrim( rtrim( number_format( $baths_raw, 1 ), '0' ), '.' );
                $price       = (float) get_post_meta( $p->ID, '_ovr_base_price', true );
                $is_featured = (bool) get_post_meta( $p->ID, '_ovr_is_featured', true );
                $status      = (string) get_post_meta( $p->ID, '_ovr_listing_status', true ) ?: 'active';
                $rentals     = wp_get_post_terms( $p->ID, 'ovr_rental_type', [ 'fields' => 'names' ] );
                $rental      = ( ! is_wp_error( $rentals ) && $rentals ) ? $rentals[0] : '';
                $edit_url    = get_edit_post_link( $p->ID );
            ?>
                <article class="ovr-listing-item">
                    <div class="ovr-listing-thumb" style="background-image:url('<?php echo esc_url( $thumb ); ?>')">
                        <span class="ovr-listing-status ovr-listing-status--<?php echo esc_attr( $status ); ?>">
                            <?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?>
                        </span>
                    </div>

                    <div class="ovr-listing-body">
                        <div class="ovr-listing-titlerow">
                            <h3><?php echo esc_html( $p->post_title ); ?></h3>
                            <?php if ( $is_featured ) : ?>
                                <span class="ovr-listing-featured">
                                    <span class="material-symbols-outlined">star</span>
                                    <?php esc_html_e( 'Featured', 'ovr-core' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="ovr-listing-meta">
                            <?php
                            /* translators: 1: listing ID, 2: bedrooms, 3: bathrooms */
                            printf(
                                esc_html__( 'ID: #OVR-%1$s • %2$d Bed, %3$s Bath', 'ovr-core' ),
                                esc_html( (string) $p->ID ),
                                $beds,
                                esc_html( $baths )
                            );
                            ?>
                        </p>
                        <p class="ovr-listing-rate">
                            <strong><?php esc_html_e( 'Rate:', 'ovr-core' ); ?></strong>
                            <?php echo esc_html( '$' . number_format( $price, 0 ) ); ?>/<?php esc_html_e( 'night', 'ovr-core' ); ?>
                            <?php if ( $rental ) : ?>(<?php echo esc_html( $rental ); ?>)<?php endif; ?>
                        </p>
                    </div>

                    <div class="ovr-listing-actions">
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-btn ovr-btn-outline ovr-btn-sm">
                            <span class="material-symbols-outlined">edit</span>
                            <?php esc_html_e( 'Edit Listing', 'ovr-core' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-btn ovr-btn-outline ovr-btn-sm">
                            <span class="material-symbols-outlined">calendar_month</span>
                            <?php esc_html_e( 'Update Calendar', 'ovr-core' ); ?>
                        </a>
                        <?php if ( ! $is_featured ) : ?>
                            <a href="<?php echo esc_url( $pricing_url ); ?>" class="ovr-btn ovr-btn-gold ovr-btn-sm">
                                <span class="material-symbols-outlined">upgrade</span>
                                <?php esc_html_e( 'Boost Visibility', 'ovr-core' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
