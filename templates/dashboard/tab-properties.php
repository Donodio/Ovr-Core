<?php
/**
 * My Properties tab — list with quick actions.
 *
 * @package OVR
 * @var array  $properties  WP_Post objects
 * @var string $add_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<section class="ovr-card" style="padding:24px">

    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
        <div>
            <h2 style="font-size:20px;font-weight:600;margin:0 0 4px">
                <?php esc_html_e( 'My Properties', 'ovr-core' ); ?>
            </h2>
            <p style="margin:0;font-size:13px;color:var(--ovr-on-surface-variant)">
                <?php
                /* translators: %d: property count */
                printf( esc_html( _n( '%d listing', '%d listings', count( $properties ), 'ovr-core' ) ), count( $properties ) );
                ?>
            </p>
        </div>
        <a href="<?php echo esc_url( $add_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-pill">
            <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">add</span>
            <?php esc_html_e( 'Add New Property', 'ovr-core' ); ?>
        </a>
    </header>

    <?php if ( empty( $properties ) ) : ?>
        <div style="padding:48px 24px;text-align:center;background:var(--ovr-surface-container-low);border-radius:var(--ovr-radius-md)">
            <span class="material-symbols-outlined" style="font-size:56px;color:var(--ovr-outline);margin-bottom:12px">add_home</span>
            <h3 style="margin:0 0 8px"><?php esc_html_e( 'No properties yet', 'ovr-core' ); ?></h3>
            <p style="margin:0 0 20px;color:var(--ovr-on-surface-variant);font-size:14px;max-width:380px;margin-left:auto;margin-right:auto">
                <?php esc_html_e( 'Create your first listing with photos, amenities, pricing, and availability. Inquiries arrive in this dashboard the moment a guest reaches out.', 'ovr-core' ); ?>
            </p>
            <a href="<?php echo esc_url( $add_url ); ?>" class="ovr-btn ovr-btn-primary ovr-btn-pill">
                <?php esc_html_e( 'List Your First Property', 'ovr-core' ); ?>
            </a>
        </div>
    <?php else : ?>
        <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px">
            <?php foreach ( $properties as $p ) :
                $thumb        = get_the_post_thumbnail_url( $p->ID, 'thumbnail' );
                $price        = (float) get_post_meta( $p->ID, '_ovr_base_price', true );
                $bedrooms     = (int) get_post_meta( $p->ID, '_ovr_bedrooms', true );
                $bathrooms    = (float) get_post_meta( $p->ID, '_ovr_bathrooms', true );
                $listing_st   = (string) get_post_meta( $p->ID, '_ovr_listing_status', true ) ?: 'active';
                $is_featured  = (int) get_post_meta( $p->ID, '_ovr_is_featured', true );
                $status_color = [
                    'active'           => 'var(--ovr-secondary-container)',
                    'inactive'         => 'var(--ovr-error-container)',
                    'pending_renewal'  => 'var(--ovr-tertiary-container)',
                    'draft'            => 'var(--ovr-surface-container)',
                ][ $listing_st ] ?? 'var(--ovr-surface-container)';
                $status_text  = [
                    'active'          => __( 'Active', 'ovr-core' ),
                    'inactive'        => __( 'Inactive', 'ovr-core' ),
                    'pending_renewal' => __( 'Pending', 'ovr-core' ),
                    'draft'           => __( 'Draft', 'ovr-core' ),
                ][ $listing_st ] ?? $listing_st;
            ?>
                <li style="display:flex;gap:16px;align-items:center;padding:14px;background:var(--ovr-surface-container-low);border-radius:var(--ovr-radius-md)">
                    <?php if ( $thumb ) : ?>
                        <img src="<?php echo esc_url( $thumb ); ?>" alt=""
                             style="width:80px;height:60px;object-fit:cover;border-radius:var(--ovr-radius-sm);flex-shrink:0">
                    <?php else : ?>
                        <div style="width:80px;height:60px;background:var(--ovr-surface-container);border-radius:var(--ovr-radius-sm);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <span class="material-symbols-outlined" style="color:var(--ovr-outline)">image</span>
                        </div>
                    <?php endif; ?>

                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
                            <strong style="font-size:15px"><?php echo esc_html( $p->post_title ); ?></strong>
                            <span style="background:<?php echo esc_attr( $status_color ); ?>;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600">
                                <?php echo esc_html( $status_text ); ?>
                            </span>
                            <?php if ( $is_featured ) : ?>
                                <span style="background:#ffe088;color:#241a00;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600">
                                    ★ <?php esc_html_e( 'Featured', 'ovr-core' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:13px;color:var(--ovr-on-surface-variant)">
                            $<?php echo esc_html( number_format( $price, 0 ) ); ?> /<?php esc_html_e( 'night', 'ovr-core' ); ?>
                            · <?php
                            /* translators: %d: bedroom count */
                            printf( esc_html( _n( '%d bed', '%d beds', $bedrooms, 'ovr-core' ) ), $bedrooms ); ?>
                            · <?php
                            /* translators: %s: bathroom count */
                            printf( esc_html__( '%s bath', 'ovr-core' ), esc_html( (string) $bathrooms ) ); ?>
                        </div>
                    </div>

                    <div style="display:flex;gap:6px;flex-shrink:0">
                        <a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" target="_blank" rel="noopener"
                           class="ovr-btn ovr-btn-outline" style="padding:6px 14px;font-size:13px"
                           title="<?php esc_attr_e( 'View on site', 'ovr-core' ); ?>">
                            <span class="material-symbols-outlined" style="font-size:16px">open_in_new</span>
                        </a>
                        <a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"
                           class="ovr-btn ovr-btn-primary" style="padding:6px 14px;font-size:13px">
                            <?php esc_html_e( 'Edit', 'ovr-core' ); ?>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
