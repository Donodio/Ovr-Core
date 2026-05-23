<?php
/**
 * Property Amenities Component.
 *
 * "What this place offers" — icon grid sourced from the ovr_amenity taxonomy.
 *
 * @package OVR
 *
 * @var int   $post_id    Required. Property post ID.
 * @var array $amenities  Optional. Pre-fetched WP_Term[]. Auto-loaded if empty.
 * @var int   $limit      Optional. Items shown before "Show all". Default 6.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$post_id   = $post_id ?? 0;
$amenities = $amenities ?? wp_get_post_terms( $post_id, 'ovr_amenity' );
$limit     = $limit ?? 6;

if ( is_wp_error( $amenities ) || empty( $amenities ) ) {
    return;
}

// Map amenity slug -> Material icon. Falls back to "check_circle".
$icon_map = [
    'wifi'              => 'wifi',
    'kitchen'           => 'kitchen',
    'pool'              => 'pool',
    'private-pool'      => 'pool',
    'hot-tub'           => 'hot_tub',
    'parking'           => 'local_parking',
    'ev-charger'        => 'ev_station',
    'air-conditioning'  => 'ac_unit',
    'heating'           => 'mode_heat',
    'fireplace'         => 'fireplace',
    'bbq'               => 'outdoor_grill',
    'grill'             => 'outdoor_grill',
    'tv'                => 'tv',
    'washer'            => 'local_laundry_service',
    'dryer'             => 'local_laundry_service',
    'pets-allowed'      => 'pets',
    'workspace'         => 'desk',
    'gym'               => 'fitness_center',
    'beach-access'      => 'beach_access',
    'lake-access'       => 'kayaking',
    'mountain-view'     => 'landscape',
    'garden'            => 'yard',
    'balcony'           => 'balcony',
    'patio'             => 'deck',
    'security-system'   => 'security',
    'smoke-detector'    => 'smoke_detector',
    'first-aid'         => 'medical_services',
];

$total   = count( $amenities );
$visible = array_slice( $amenities, 0, $limit );
?>
<section class="ovr-amenities" style="padding-top:32px;border-top:1px solid var(--ovr-outline-variant)">
    <h2 class="ovr-h2" style="margin-bottom:20px">
        <?php esc_html_e( 'What this place offers', 'ovr-core' ); ?>
    </h2>

    <ul class="ovr-amenity-list"
        style="list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:6px 32px">
        <?php foreach ( $visible as $amenity ) :
            $icon = $icon_map[ $amenity->slug ] ?? 'check_circle';
        ?>
            <li style="display:flex;align-items:center;gap:12px;padding:6px 0">
                <span class="material-symbols-outlined"
                      style="font-size:24px;color:var(--ovr-primary);flex-shrink:0">
                    <?php echo esc_html( $icon ); ?>
                </span>
                <span style="font-size:15px;color:var(--ovr-on-surface)">
                    <?php echo esc_html( $amenity->name ); ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ( $total > $limit ) : ?>
        <button type="button"
                class="ovr-amenities-toggle-btn"
                onclick="document.getElementById('ovr-amenities-modal').classList.add('is-open');document.body.style.overflow='hidden'">
            <span class="material-symbols-outlined" style="font-size:18px">grid_view</span>
            <?php
            /* translators: %d: total amenity count */
            printf( esc_html__( 'Show all %d amenities', 'ovr-core' ), $total ); ?>
        </button>

        <!-- Amenities Modal -->
        <div id="ovr-amenities-modal" class="ovr-amenities-modal-overlay"
             onclick="if(event.target===this){this.classList.remove('is-open');document.body.style.overflow=''}">
            <div class="ovr-amenities-modal">
                <div class="ovr-amenities-modal-header">
                    <h3><?php esc_html_e( 'All Amenities', 'ovr-core' ); ?></h3>
                    <button type="button" class="ovr-amenities-modal-close"
                            onclick="document.getElementById('ovr-amenities-modal').classList.remove('is-open');document.body.style.overflow=''">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <ul class="ovr-amenities-modal-grid" style="list-style:none;margin:0;padding:0">
                    <?php foreach ( $amenities as $amenity ) :
                        $icon = $icon_map[ $amenity->slug ] ?? 'check_circle';
                    ?>
                        <li>
                            <span class="material-symbols-outlined"
                                  style="font-size:22px;color:var(--ovr-primary);flex-shrink:0">
                                <?php echo esc_html( $icon ); ?>
                            </span>
                            <?php echo esc_html( $amenity->name ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</section>

