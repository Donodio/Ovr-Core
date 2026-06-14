<?php
/**
 * Add / Edit Listing — unified, senior-friendly TABBED editor (no wp-admin).
 *
 * One workflow for both create and edit. Five numbered steps:
 *   1 Property Information · 2 Photos · 3 Pricing · 4 Availability Calendar
 *   · 5 Videos
 * A sticky footer always shows one large action: Save Changes (which publishes
 * immediately — there is no draft step). Photos upload privately via AJAX;
 * taxonomy pickers are plain HTML controls. Scoped under `.ovr-ld`.
 *
 * @package OVR
 * @var \WP_Post|null $post
 * @var bool          $can_create
 * @var string        $save_action
 * @var string        $ajax_url
 * @var string        $listing_nonce
 * @var string        $props_url
 * @var string        $subscription_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$is_edit = ( $post instanceof \WP_Post );
$pid     = $is_edit ? (int) $post->ID : 0;
$notice  = isset( $_GET['ovr_listing'] ) ? sanitize_key( wp_unslash( $_GET['ovr_listing'] ) ) : '';

$m = static function ( string $key, $default = '' ) use ( $pid, $is_edit ) {
    return $is_edit ? get_post_meta( $pid, '_ovr_' . $key, true ) : $default;
};

$sel = static function ( string $tax ) use ( $pid, $is_edit ): array {
    if ( ! $is_edit ) { return []; }
    $ids = wp_get_object_terms( $pid, $tax, [ 'fields' => 'ids' ] );
    return is_wp_error( $ids ) ? [] : array_map( 'intval', $ids );
};

$terms = static function ( string $tax ): array {
    $t = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
    return is_wp_error( $t ) ? [] : $t;
};

$title   = $is_edit ? $post->post_title : '';
$desc    = $is_edit ? $post->post_content : '';
$sel_vil = $sel( 'ovr_village' );
$sel_pt  = $sel( 'ovr_property_type' );
$sel_rt  = $sel( 'ovr_rental_type' );
$sel_am  = $sel( 'ovr_amenity' );

$gids = [];
if ( $is_edit ) {
    foreach ( explode( ',', (string) get_post_meta( $pid, '_ovr_gallery_ids', true ) ) as $g ) {
        $g = (int) $g;
        if ( $g ) { $gids[] = $g; }
    }
}
$caption_map = $is_edit ? (array) get_post_meta( $pid, '_ovr_gallery_captions', true ) : [];

// Existing manual availability blocks (Calendar tab).
$avail_rows = $is_edit ? \OVR\Property\Availability::get_manual_blocks( $pid ) : [];

// Pricing rows (Pricing tab) — the production-compatible table (Phase 4):
// Month/Season Name-Year · From · To · Price (free text) · Per · Minimum Term.
// When editing, load saved rows; for a NEW listing seed example rows.
$price_rows = [];
if ( $is_edit ) {
    foreach ( \OVR\Property\SeasonalPricing::get_pricing( $pid ) as $row ) {
        $d            = \OVR\Property\SeasonalPricing::row_display( $row );
        $price_rows[] = [
            'period' => $d['period'],
            'from'   => $d['from'],
            'to'     => $d['to'],
            'price'  => $d['price'] > 0 ? (string) ( $d['price'] == (int) $d['price'] ? (int) $d['price'] : $d['price'] ) : '',
            'per'    => $d['per_key'],
            'min'    => (string) (int) ( $row['min_stay'] ?? 0 ),
        ];
    }
} else {
    $price_rows = [
        [ 'period' => __( 'Peak Season 2026 (sample)', 'ovr-core' ), 'from' => '', 'to' => '', 'price' => '3200', 'per' => 'per_month', 'min' => '1' ],
        [ 'period' => __( 'Summer 2026 (sample)', 'ovr-core' ),      'from' => '', 'to' => '', 'price' => '700',  'per' => 'per_week',  'min' => '1' ],
    ];
}
$hide_pricing = $is_edit ? (bool) get_post_meta( $pid, '_ovr_hide_pricing', true ) : false;

// "Per" dropdown options (Phase 4B). Keys match SeasonalPricing::PER_UNITS.
$per_options = [
    'per_day'   => __( 'Per Day', 'ovr-core' ),
    'per_week'  => __( 'Per Week', 'ovr-core' ),
    'per_month' => __( 'Per Month', 'ovr-core' ),
    'flat'      => __( 'Flat Rate', 'ovr-core' ),
];

// Owner + admin listing-status values (Phase 8B).
$owner_status = $is_edit ? ( (string) get_post_meta( $pid, '_ovr_listing_status', true ) ?: 'active' ) : 'active';
if ( ! in_array( $owner_status, [ 'active', 'inactive' ], true ) ) {
    $owner_status = 'active'; // tolerate legacy values (e.g. pending_renewal).
}
$admin_status   = $is_edit ? ( (string) get_post_meta( $pid, '_ovr_admin_status', true ) ?: 'approved' ) : 'approved';
$is_admin_user  = current_user_can( 'manage_options' );
$admin_statuses = [
    'approved'       => __( 'Approved', 'ovr-core' ),
    'hidden'         => __( 'Hidden', 'ovr-core' ),
    'suspended'      => __( 'Suspended', 'ovr-core' ),
    'pending_review' => __( 'Pending Review', 'ovr-core' ),
];

// Tab definitions (number, key, label). The "Review & Publish" step was removed
// (Phase 7); Videos is the final step and Save Changes is always available.
$ld_tabs = [
    [ 'info',      __( 'Property Information', 'ovr-core' ) ],
    [ 'photos',    __( 'Photos', 'ovr-core' ) ],
    [ 'pricing',   __( 'Pricing', 'ovr-core' ) ],
    [ 'calendar',  __( 'Availability Calendar', 'ovr-core' ) ],
    [ 'videos',    __( 'Video', 'ovr-core' ) ],
    [ 'panorama',  __( 'Panorama', 'ovr-core' ) ],
    [ 'documents', __( 'Documents', 'ovr-core' ) ],
];

// Existing uploaded media (Features B/C/D) for re-render on edit.
$video_att_id = (int) $m( 'video_id' );
$video_att_url = $video_att_id ? ( wp_get_attachment_url( $video_att_id ) ?: '' ) : '';
$pano_att_id  = (int) $m( 'panorama_id' );
$pano_att_url = $pano_att_id ? ( wp_get_attachment_image_url( $pano_att_id, 'large' ) ?: wp_get_attachment_url( $pano_att_id ) ) : '';
$doc_rows     = \OVR\Frontend\ListingForm::get_documents( $pid );
?>
<div class="ld-fm">

    <header class="ld-fm-head">
        <div>
            <a href="<?php echo esc_url( $props_url ); ?>" class="ld-fm-back"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'My Listings', 'ovr-core' ); ?></a>
            <h1 class="ld-fm-h1"><?php echo $is_edit ? esc_html__( 'Edit Listing', 'ovr-core' ) : esc_html__( 'Create a Listing', 'ovr-core' ); ?></h1>
            <?php if ( $is_edit ) : ?>
                <span class="ld-fm-statuspill is-id">
                    <?php
                    /* translators: %d: listing/property ID */
                    printf( esc_html__( 'Property ID: #%d', 'ovr-core' ), (int) $pid );
                    ?>
                </span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ( 'error' === $notice ) : ?>
        <div class="ld-fm-alert err"><span class="material-symbols-outlined">error</span><?php esc_html_e( 'Something went wrong saving your listing. Please try again.', 'ovr-core' ); ?></div>
    <?php elseif ( 'updated' === $notice ) : ?>
        <div class="ld-fm-alert ok"><span class="material-symbols-outlined">check_circle</span><?php esc_html_e( 'Listing updated successfully.', 'ovr-core' ); ?></div>
    <?php endif; ?>

    <?php if ( ! $is_edit && ! $can_create ) :
        $is_sub_block = ( 'subscription' === ( $block_reason ?? '' ) );
    ?>
        <div class="ld-fm-limit">
            <span class="material-symbols-outlined">lock</span>
            <?php if ( $is_sub_block ) : ?>
                <h2><?php esc_html_e( 'An active subscription is required to list a property', 'ovr-core' ); ?></h2>
                <p><?php esc_html_e( 'Choose and activate a subscription plan to start publishing your listings.', 'ovr-core' ); ?></p>
                <a href="<?php echo esc_url( $subscription_url ); ?>" class="ld-fm-btn ld-fm-btn--primary"><?php esc_html_e( 'Choose a Plan', 'ovr-core' ); ?></a>
            <?php else : ?>
                <h2><?php esc_html_e( "You've reached your plan's listing limit", 'ovr-core' ); ?></h2>
                <p><?php esc_html_e( 'Renew or review your subscription to manage more properties.', 'ovr-core' ); ?></p>
                <a href="<?php echo esc_url( $subscription_url ); ?>" class="ld-fm-btn ld-fm-btn--primary"><?php esc_html_e( 'View Subscription', 'ovr-core' ); ?></a>
            <?php endif; ?>
        </div>
    <?php else : ?>

    <!-- Step tabs (clickable; also driven by Back/Next in the footer) -->
    <nav class="ld-fm-tabs" id="ld-fm-tabs" aria-label="<?php esc_attr_e( 'Listing steps', 'ovr-core' ); ?>">
        <?php foreach ( $ld_tabs as $i => $t ) : [ $key, $label ] = $t; ?>
            <button type="button" class="ld-fm-tab<?php echo 0 === $i ? ' is-active' : ''; ?>" data-ld-tab="<?php echo esc_attr( $key ); ?>">
                <span class="ld-fm-tab-num"><?php echo (int) ( $i + 1 ); ?></span>
                <span class="ld-fm-tab-label"><?php echo esc_html( $label ); ?></span>
            </button>
        <?php endforeach; ?>
    </nav>

    <form class="ld-fm-form" method="post" action="<?php echo esc_url( $save_action ); ?>" data-ajax="<?php echo esc_url( $ajax_url ); ?>" data-nonce="<?php echo esc_attr( $listing_nonce ); ?>">
        <input type="hidden" name="action" value="ovr_save_listing">
        <input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $pid ); ?>">
        <input type="hidden" name="ovr_listing_nonce" value="<?php echo esc_attr( $listing_nonce ); ?>">
        <input type="hidden" name="gallery_ids" id="ld-fm-gallery-ids" value="<?php echo esc_attr( implode( ',', $gids ) ); ?>">
        <?php if ( ! empty( $return_url ) ) : ?>
            <input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>">
        <?php endif; ?>

        <!-- ── TAB 1: Property Information ── -->
        <section class="ld-fm-panel is-active" data-ld-panel="info">
            <?php if ( $is_edit ) : ?>
                <div class="ld-fm-card ld-fm-statuscard">
                    <div class="ld-fm-statuscard-id">
                        <span class="ld-fm-statuscard-id-label"><?php esc_html_e( 'Property ID', 'ovr-core' ); ?></span>
                        <span class="ld-fm-statuscard-id-num">#<?php echo (int) $pid; ?></span>
                        <span class="ld-fm-subhint" style="margin:0"><?php esc_html_e( 'Use this number when contacting support or sharing your listing.', 'ovr-core' ); ?></span>
                    </div>
                    <div class="ld-fm-statuscard-controls">
                        <div class="ld-fm-field" style="margin-bottom:0">
                            <label class="ld-fm-label" for="ld-fm-owner-status"><?php esc_html_e( 'Listing Status', 'ovr-core' ); ?></label>
                            <select class="ld-fm-input" id="ld-fm-owner-status" name="listing_status">
                                <option value="active" <?php selected( $owner_status, 'active' ); ?>><?php esc_html_e( 'Active — visible on the site', 'ovr-core' ); ?></option>
                                <option value="inactive" <?php selected( $owner_status, 'inactive' ); ?>><?php esc_html_e( 'Inactive — hidden from the site & searches', 'ovr-core' ); ?></option>
                            </select>
                        </div>
                        <?php if ( $is_admin_user ) : ?>
                            <div class="ld-fm-field ld-fm-adminonly" style="margin-bottom:0">
                                <label class="ld-fm-label" for="ld-fm-admin-status"><?php esc_html_e( 'Admin Status', 'ovr-core' ); ?> <span class="ld-fm-adminbadge"><?php esc_html_e( 'Admins only', 'ovr-core' ); ?></span></label>
                                <select class="ld-fm-input" id="ld-fm-admin-status" name="admin_status">
                                    <?php foreach ( $admin_statuses as $av => $al ) : ?>
                                        <option value="<?php echo esc_attr( $av ); ?>" <?php selected( $admin_status, $av ); ?>><?php echo esc_html( $al ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="ld-fm-subhint"><?php esc_html_e( 'Overrides the owner status. Anything other than “Approved” removes the listing from the public site.', 'ovr-core' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Property Information', 'ovr-core' ); ?></h2>
                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-title"><?php esc_html_e( 'Listing Title', 'ovr-core' ); ?></label>
                    <input class="ld-fm-input" id="ld-fm-title" name="title" type="text" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Sunny Grove Villa', 'ovr-core' ); ?>">
                </div>
                <?php $short_desc = $is_edit ? (string) $post->post_excerpt : ''; ?>
                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-shortdesc"><?php esc_html_e( 'Short Description', 'ovr-core' ); ?> <span class="ld-fm-req">*</span></label>
                    <textarea class="ld-fm-input ld-fm-textarea" id="ld-fm-shortdesc" name="short_description" rows="2" maxlength="200" placeholder="<?php esc_attr_e( 'A one-line summary shown in search results and listing cards…', 'ovr-core' ); ?>"><?php echo esc_textarea( $short_desc ); ?></textarea>
                    <p class="ld-fm-subhint"><span id="ld-fm-shortdesc-count">0</span>/200 <?php esc_html_e( 'characters. Appears in search results, featured listings, and cards.', 'ovr-core' ); ?></p>
                </div>
                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-desc"><?php esc_html_e( 'Full Description', 'ovr-core' ); ?></label>
                    <textarea class="ld-fm-input ld-fm-textarea" id="ld-fm-desc" name="description" rows="5" placeholder="<?php esc_attr_e( 'Describe your property in full — shown on the listing page…', 'ovr-core' ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
                </div>
                <div class="ld-fm-grid">
                    <div class="ld-fm-field">
                        <label class="ld-fm-label" for="ld-fm-beds"><?php esc_html_e( 'Bedrooms', 'ovr-core' ); ?></label>
                        <input class="ld-fm-input" id="ld-fm-beds" name="bedrooms" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $m( 'bedrooms' ) ); ?>">
                    </div>
                    <div class="ld-fm-field">
                        <label class="ld-fm-label" for="ld-fm-baths"><?php esc_html_e( 'Bathrooms', 'ovr-core' ); ?></label>
                        <input class="ld-fm-input" id="ld-fm-baths" name="bathrooms" type="number" min="0" step="0.5" value="<?php echo esc_attr( (string) $m( 'bathrooms' ) ); ?>">
                    </div>
                    <div class="ld-fm-field">
                        <label class="ld-fm-label" for="ld-fm-bedcount"><?php esc_html_e( 'Beds', 'ovr-core' ); ?></label>
                        <input class="ld-fm-input" id="ld-fm-bedcount" name="beds" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $m( 'beds' ) ); ?>">
                    </div>
                    <div class="ld-fm-field">
                        <label class="ld-fm-label" for="ld-fm-guests"><?php esc_html_e( 'Max Guests', 'ovr-core' ); ?></label>
                        <input class="ld-fm-input" id="ld-fm-guests" name="max_guests" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $m( 'max_guests' ) ); ?>">
                    </div>
                    <div class="ld-fm-field">
                        <label class="ld-fm-label" for="ld-fm-sqft"><?php esc_html_e( 'Size (sq ft)', 'ovr-core' ); ?></label>
                        <input class="ld-fm-input" id="ld-fm-sqft" name="sqft" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $m( 'sqft' ) ); ?>">
                    </div>
                    <label class="ld-fm-check">
                        <input type="checkbox" name="pets_allowed" value="1" <?php checked( (string) $m( 'pets_allowed' ), '1' ); ?>>
                        <span><?php esc_html_e( 'Pets allowed', 'ovr-core' ); ?></span>
                    </label>
                </div>
            </div>

            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Location', 'ovr-core' ); ?></h2>
                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-addr"><?php esc_html_e( 'Street Address', 'ovr-core' ); ?></label>
                    <input class="ld-fm-input" id="ld-fm-addr" name="address" type="text" value="<?php echo esc_attr( (string) $m( 'address' ) ); ?>">
                </div>
                <?php
                $city_val = (string) $m( 'city' );
                if ( '' === $city_val && ! $is_edit ) { $city_val = 'The Villages'; }
                ?>
                <div class="ld-fm-grid">
                    <div class="ld-fm-field">
                        <label class="ld-fm-label" for="ld-fm-city"><?php esc_html_e( 'City', 'ovr-core' ); ?></label>
                        <input class="ld-fm-input" id="ld-fm-city" name="city" type="text" value="<?php echo esc_attr( $city_val ); ?>">
                    </div>
                    <div class="ld-fm-field">
                        <label class="ld-fm-label" for="ld-fm-state"><?php esc_html_e( 'State', 'ovr-core' ); ?></label>
                        <input class="ld-fm-input" id="ld-fm-state" name="state" type="text" value="<?php echo esc_attr( (string) $m( 'state' ) ); ?>">
                    </div>
                    <div class="ld-fm-field">
                        <label class="ld-fm-label" for="ld-fm-zip"><?php esc_html_e( 'ZIP', 'ovr-core' ); ?></label>
                        <input class="ld-fm-input" id="ld-fm-zip" name="zip" type="text" value="<?php echo esc_attr( (string) $m( 'zip' ) ); ?>">
                    </div>
                </div>

                <?php $section_terms = $terms( 'ovr_village' ); ?>
                <div class="ld-fm-field" style="margin-top:16px">
                    <label class="ld-fm-label" for="ld-fm-village-section"><?php esc_html_e( 'Village Section', 'ovr-core' ); ?> <span class="ld-fm-req">*</span></label>
                    <select class="ld-fm-input" id="ld-fm-village-section" name="village_section">
                        <option value="0"><?php esc_html_e( '— Select a section —', 'ovr-core' ); ?></option>
                        <?php foreach ( $section_terms as $term ) : ?>
                            <option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $sel_vil, true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ld-fm-subhint"><?php esc_html_e( 'The broad section/area of The Villages this property sits in. Renters use it to filter listings in search.', 'ovr-core' ); ?></p>
                </div>

                <div class="ld-fm-field" style="margin-top:16px">
                    <label class="ld-fm-label" for="ld-fm-villagename"><?php esc_html_e( 'Village Name', 'ovr-core' ); ?> <span class="ld-fm-req">*</span></label>
                    <input class="ld-fm-input" id="ld-fm-villagename" name="village_name" type="text" value="<?php echo esc_attr( (string) $m( 'village_name' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Village of Caroline', 'ovr-core' ); ?>">
                </div>
            </div>

            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Classification', 'ovr-core' ); ?></h2>
                <div class="ld-fm-grid">
                    <?php
                    $single = [
                        [ 'property_type', 'ovr_property_type', __( 'Property Type', 'ovr-core' ), $sel_pt[0] ?? 0 ],
                        [ 'rental_type', 'ovr_rental_type', __( 'Rental Type', 'ovr-core' ), $sel_rt[0] ?? 0 ],
                    ];
                    foreach ( $single as $s ) :
                        [ $field, $tax, $label, $current ] = $s;
                        $opts = $terms( $tax );
                    ?>
                        <div class="ld-fm-field">
                            <label class="ld-fm-label" for="ld-fm-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
                            <select class="ld-fm-input" id="ld-fm-<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>">
                                <option value="0"><?php esc_html_e( '— Select —', 'ovr-core' ); ?></option>
                                <?php foreach ( $opts as $term ) : ?>
                                    <option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( (int) $current, (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php $amenity_terms = $terms( 'ovr_amenity' ); ?>
                <?php if ( $amenity_terms ) : ?>
                    <div class="ld-fm-field" style="margin-top:16px">
                        <label class="ld-fm-label"><?php esc_html_e( 'Amenities', 'ovr-core' ); ?></label>
                        <div class="ld-fm-amenities">
                            <?php foreach ( $amenity_terms as $term ) : ?>
                                <label class="ld-fm-chip">
                                    <input type="checkbox" name="amenities[]" value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php checked( in_array( (int) $term->term_id, $sel_am, true ) ); ?>>
                                    <span><?php echo esc_html( $term->name ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Additional Information', 'ovr-core' ); ?></h2>
                <p class="ld-fm-hint"><?php esc_html_e( 'Optional. These appear as their own sections on your listing page. Put each item on its own line.', 'ovr-core' ); ?></p>
                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-nearby"><?php esc_html_e( "What's Nearby", 'ovr-core' ); ?></label>
                    <textarea class="ld-fm-input ld-fm-textarea" id="ld-fm-nearby" name="nearby" rows="4" placeholder="<?php esc_attr_e( "e.g. Golf courses, pools, restaurants, shopping…", 'ovr-core' ); ?>"><?php echo esc_textarea( (string) $m( 'nearby' ) ); ?></textarea>
                </div>
                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-policies"><?php esc_html_e( 'Policies', 'ovr-core' ); ?></label>
                    <textarea class="ld-fm-input ld-fm-textarea" id="ld-fm-policies" name="policies" rows="4" placeholder="<?php esc_attr_e( 'e.g. No smoking, no pets, cancellation policy…', 'ovr-core' ); ?>"><?php echo esc_textarea( (string) $m( 'policies' ) ); ?></textarea>
                </div>
                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-payinfo"><?php esc_html_e( 'Payment Information', 'ovr-core' ); ?></label>
                    <textarea class="ld-fm-input ld-fm-textarea" id="ld-fm-payinfo" name="payment_info" rows="4" placeholder="<?php esc_attr_e( 'e.g. Venmo accepted, check accepted, wire transfer…', 'ovr-core' ); ?>"><?php echo esc_textarea( (string) $m( 'payment_info' ) ); ?></textarea>
                </div>
            </div>
        </section>

        <!-- ── TAB 2: Photos ── -->
        <section class="ld-fm-panel" data-ld-panel="photos">
            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Photos', 'ovr-core' ); ?></h2>
                <p class="ld-fm-hint">
                    <?php esc_html_e( 'Upload your photos (JPG, PNG, WEBP — up to 10MB each).', 'ovr-core' ); ?>
                    <strong><?php esc_html_e( 'Drag photos to reorder them.', 'ovr-core' ); ?></strong>
                    <?php esc_html_e( 'The one marked ★ Main Listing Photo is shown first; press “Set as main” on any other photo to change it.', 'ovr-core' ); ?>
                    <?php esc_html_e( 'Every photo is automatically watermarked when you upload it. Use the buttons on each photo to rotate or remove it.', 'ovr-core' ); ?>
                </p>

                <div class="ld-fm-uploader" id="ld-fm-uploader">
                    <label class="ld-fm-drop">
                        <input type="file" id="ld-fm-file" accept="image/*" multiple hidden>
                        <span class="material-symbols-outlined">add_photo_alternate</span>
                        <span><?php esc_html_e( 'Click to upload photos', 'ovr-core' ); ?></span>
                    </label>
                    <div class="ld-fm-thumbs" id="ld-fm-thumbs">
                        <?php
                        $render_thumb = static function ( $g, $url, $caption, $watermarked ) {
                            ?>
                            <div class="ld-fm-thumb<?php echo $watermarked ? ' is-watermarked' : ''; ?>" data-id="<?php echo esc_attr( (string) $g ); ?>" draggable="true">
                                <div class="ld-fm-thumb-media">
                                    <img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $caption ); ?>">
                                    <span class="ld-fm-thumb-main"><span class="material-symbols-outlined">star</span><?php esc_html_e( 'Main Listing Photo', 'ovr-core' ); ?></span>
                                    <span class="ld-fm-thumb-wm"><span class="material-symbols-outlined">verified</span><?php esc_html_e( 'Watermarked', 'ovr-core' ); ?></span>
                                    <div class="ld-fm-thumb-tools">
                                        <button type="button" class="ld-fm-thumb-btn" data-act="rotate" data-dir="left" title="<?php esc_attr_e( 'Rotate left', 'ovr-core' ); ?>" aria-label="<?php esc_attr_e( 'Rotate photo left', 'ovr-core' ); ?>"><span class="material-symbols-outlined">rotate_left</span></button>
                                        <button type="button" class="ld-fm-thumb-btn" data-act="rotate" data-dir="right" title="<?php esc_attr_e( 'Rotate right', 'ovr-core' ); ?>" aria-label="<?php esc_attr_e( 'Rotate photo right', 'ovr-core' ); ?>"><span class="material-symbols-outlined">rotate_right</span></button>
                                        <button type="button" class="ld-fm-thumb-btn" data-act="crop" title="<?php esc_attr_e( 'Crop', 'ovr-core' ); ?>" aria-label="<?php esc_attr_e( 'Crop photo', 'ovr-core' ); ?>"><span class="material-symbols-outlined">crop</span></button>
                                        <button type="button" class="ld-fm-thumb-btn" data-act="delete" title="<?php esc_attr_e( 'Remove', 'ovr-core' ); ?>" aria-label="<?php esc_attr_e( 'Remove photo', 'ovr-core' ); ?>"><span class="material-symbols-outlined">delete</span></button>
                                    </div>
                                    <button type="button" class="ld-fm-setmain" data-act="setmain"><span class="material-symbols-outlined">star</span><?php esc_html_e( 'Set as main', 'ovr-core' ); ?></button>
                                </div>
                                <input type="text" class="ld-fm-cap" name="captions[<?php echo esc_attr( (string) $g ); ?>]" value="<?php echo esc_attr( (string) $caption ); ?>" placeholder="<?php esc_attr_e( 'Add a caption…', 'ovr-core' ); ?>" maxlength="160">
                            </div>
                            <?php
                        };
                        foreach ( $gids as $g ) {
                            $url = wp_get_attachment_image_url( $g, 'medium' ) ?: wp_get_attachment_url( $g );
                            if ( ! $url ) { continue; }
                            $render_thumb( $g, $url, (string) ( $caption_map[ (string) $g ] ?? '' ), (bool) get_post_meta( $g, '_ovr_watermarked', true ) );
                        }
                        ?>
                    </div>
                    <p class="ld-fm-uperr" id="ld-fm-uperr" role="alert"></p>
                </div>
            </div>
        </section>

        <!-- ── TAB 3: Pricing ── -->
        <section class="ld-fm-panel" data-ld-panel="pricing">
            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Pricing', 'ovr-core' ); ?></h2>

                <label class="ld-fm-check ld-fm-pricehide" style="align-self:flex-start;margin-bottom:16px">
                    <input type="checkbox" name="hide_pricing" id="ld-fm-hide-pricing" value="1" <?php checked( $hide_pricing ); ?>>
                    <span><?php esc_html_e( 'Check Description For Pricing', 'ovr-core' ); ?></span>
                </label>
                <p class="ld-fm-subhint" style="margin-top:-8px;margin-bottom:16px"><?php esc_html_e( 'When ticked, the rates table is hidden on your listing (renters are told to check the description). Your saved rows are kept.', 'ovr-core' ); ?></p>

                <div id="ld-fm-price-wrap">
                    <p class="ld-fm-hint">
                        <?php esc_html_e( 'Add a row for each way you rent — daily, weekly, monthly, seasonal, or a flat rate. Enter rows chronologically (Month/Season first, then weekly/daily). Drag the handle or use the arrows to reorder.', 'ovr-core' ); ?>
                        <?php if ( ! $is_edit ) : ?><br><strong><?php esc_html_e( 'The rows below are examples — edit or remove them.', 'ovr-core' ); ?></strong><?php endif; ?>
                    </p>

                    <div class="ld-fm-price-head" aria-hidden="true">
                        <span></span>
                        <span><?php esc_html_e( 'Month or Season Name - Year', 'ovr-core' ); ?></span>
                        <span><?php esc_html_e( 'From', 'ovr-core' ); ?></span>
                        <span><?php esc_html_e( 'To', 'ovr-core' ); ?></span>
                        <span><?php esc_html_e( 'Price', 'ovr-core' ); ?></span>
                        <span><?php esc_html_e( 'Per', 'ovr-core' ); ?></span>
                        <span><?php esc_html_e( 'Min Term', 'ovr-core' ); ?></span>
                        <span></span>
                    </div>

                    <div class="ld-fm-price-list" id="ld-fm-price-list">
                        <?php
                        $render_price_row = static function ( $i, $r ) use ( $per_options ) {
                            ?>
                            <div class="ld-fm-price-row" draggable="true">
                                <div class="ld-fm-price-handle" title="<?php esc_attr_e( 'Drag to reorder', 'ovr-core' ); ?>" aria-hidden="true"><span class="material-symbols-outlined">drag_indicator</span></div>
                                <div class="ld-fm-field">
                                    <label class="ld-fm-label ld-fm-price-mlabel"><?php esc_html_e( 'Month or Season Name - Year', 'ovr-core' ); ?></label>
                                    <input class="ld-fm-input" type="text" name="pricing[<?php echo (int) $i; ?>][season_name]" value="<?php echo esc_attr( (string) ( $r['period'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Peak Season 2026, January 2027', 'ovr-core' ); ?>">
                                </div>
                                <div class="ld-fm-field">
                                    <label class="ld-fm-label ld-fm-price-mlabel"><?php esc_html_e( 'From', 'ovr-core' ); ?></label>
                                    <input class="ld-fm-input" type="date" name="pricing[<?php echo (int) $i; ?>][start_date]" value="<?php echo esc_attr( (string) ( $r['from'] ?? '' ) ); ?>">
                                </div>
                                <div class="ld-fm-field">
                                    <label class="ld-fm-label ld-fm-price-mlabel"><?php esc_html_e( 'To', 'ovr-core' ); ?></label>
                                    <input class="ld-fm-input" type="date" name="pricing[<?php echo (int) $i; ?>][end_date]" value="<?php echo esc_attr( (string) ( $r['to'] ?? '' ) ); ?>">
                                </div>
                                <div class="ld-fm-field">
                                    <label class="ld-fm-label ld-fm-price-mlabel"><?php esc_html_e( 'Price', 'ovr-core' ); ?></label>
                                    <input class="ld-fm-input" type="text" inputmode="decimal" name="pricing[<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( (string) ( $r['price'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. $1,200', 'ovr-core' ); ?>">
                                </div>
                                <div class="ld-fm-field">
                                    <label class="ld-fm-label ld-fm-price-mlabel"><?php esc_html_e( 'Per', 'ovr-core' ); ?></label>
                                    <select class="ld-fm-input" name="pricing[<?php echo (int) $i; ?>][per]">
                                        <?php foreach ( $per_options as $pv => $pl ) : ?>
                                            <option value="<?php echo esc_attr( $pv ); ?>" <?php selected( (string) ( $r['per'] ?? 'per_month' ), $pv ); ?>><?php echo esc_html( $pl ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ld-fm-field">
                                    <label class="ld-fm-label ld-fm-price-mlabel"><?php esc_html_e( 'Min Term', 'ovr-core' ); ?></label>
                                    <input class="ld-fm-input" type="number" min="0" step="1" name="pricing[<?php echo (int) $i; ?>][min_stay]" value="<?php echo esc_attr( (string) ( $r['min'] ?? '0' ) ); ?>" placeholder="0">
                                </div>
                                <div class="ld-fm-price-actions">
                                    <button type="button" class="ld-fm-price-move" data-move="up" aria-label="<?php esc_attr_e( 'Move up', 'ovr-core' ); ?>" title="<?php esc_attr_e( 'Move up', 'ovr-core' ); ?>"><span class="material-symbols-outlined">keyboard_arrow_up</span></button>
                                    <button type="button" class="ld-fm-price-move" data-move="down" aria-label="<?php esc_attr_e( 'Move down', 'ovr-core' ); ?>" title="<?php esc_attr_e( 'Move down', 'ovr-core' ); ?>"><span class="material-symbols-outlined">keyboard_arrow_down</span></button>
                                    <button type="button" class="ld-fm-price-x" aria-label="<?php esc_attr_e( 'Remove this row', 'ovr-core' ); ?>" title="<?php esc_attr_e( 'Remove', 'ovr-core' ); ?>"><span class="material-symbols-outlined">delete</span></button>
                                </div>
                            </div>
                            <?php
                        };
                        foreach ( $price_rows as $i => $r ) {
                            $render_price_row( $i, $r );
                        }
                        ?>
                    </div>

                    <template id="ld-fm-price-tpl">
                        <?php $render_price_row( '__i__', [ 'period' => '', 'from' => '', 'to' => '', 'price' => '', 'per' => 'per_month', 'min' => '0' ] ); ?>
                    </template>

                    <button type="button" class="ld-fm-btn ld-fm-btn--ghost" id="ld-fm-price-add">
                        <span class="material-symbols-outlined">add</span><?php esc_html_e( 'Add a pricing row', 'ovr-core' ); ?>
                    </button>
                </div>
            </div>
        </section>

        <!-- ── TAB 4: Availability Calendar ── -->
        <section class="ld-fm-panel" data-ld-panel="calendar">
            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Availability Calendar', 'ovr-core' ); ?></h2>
                <p class="ld-fm-hint"><?php esc_html_e( 'Block out date ranges when your property is NOT available. Leave this empty if it is open year-round.', 'ovr-core' ); ?></p>

                <div class="ld-fm-avail" id="ld-fm-avail">
                    <?php
                    $render_avail_row = static function ( $i, $start, $end, $renter ) {
                        ?>
                        <div class="ld-fm-avail-row">
                            <div class="ld-fm-field">
                                <label class="ld-fm-label"><?php esc_html_e( 'From', 'ovr-core' ); ?></label>
                                <input class="ld-fm-input" type="date" name="avail[<?php echo (int) $i; ?>][start_date]" value="<?php echo esc_attr( (string) $start ); ?>">
                            </div>
                            <div class="ld-fm-field">
                                <label class="ld-fm-label"><?php esc_html_e( 'To', 'ovr-core' ); ?></label>
                                <input class="ld-fm-input" type="date" name="avail[<?php echo (int) $i; ?>][end_date]" value="<?php echo esc_attr( (string) $end ); ?>">
                            </div>
                            <div class="ld-fm-field">
                                <label class="ld-fm-label"><?php esc_html_e( 'Renter Name', 'ovr-core' ); ?></label>
                                <input class="ld-fm-input" type="text" name="avail[<?php echo (int) $i; ?>][notes]" value="<?php echo esc_attr( (string) $renter ); ?>" placeholder="<?php esc_attr_e( 'e.g. John Smith, Winter Guest', 'ovr-core' ); ?>" maxlength="120">
                            </div>
                            <button type="button" class="ld-fm-avail-x" aria-label="<?php esc_attr_e( 'Remove this date range', 'ovr-core' ); ?>"><span class="material-symbols-outlined">delete</span></button>
                        </div>
                        <?php
                    };
                    foreach ( $avail_rows as $i => $row ) {
                        $render_avail_row( $i, $row['start_date'] ?? '', $row['end_date'] ?? '', $row['notes'] ?? '' );
                    }
                    ?>
                </div>

                <!-- Hidden template row cloned by JS for new ranges. -->
                <template id="ld-fm-avail-tpl">
                    <?php $render_avail_row( '__i__', '', '', '' ); ?>
                </template>

                <button type="button" class="ld-fm-btn ld-fm-btn--ghost" id="ld-fm-avail-add">
                    <span class="material-symbols-outlined">add</span><?php esc_html_e( 'Add blocked date range', 'ovr-core' ); ?>
                </button>
            </div>

            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Calendar Sync (iCal)', 'ovr-core' ); ?></h2>
                <p class="ld-fm-hint"><?php esc_html_e( 'Optional. Paste an iCal link from Airbnb, VRBO, Booking.com, or Google Calendar to import booked dates automatically. Save the listing first, then press “Sync now” — we also refresh it every hour.', 'ovr-core' ); ?></p>
                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-ical"><?php esc_html_e( 'iCal feed URL', 'ovr-core' ); ?></label>
                    <input class="ld-fm-input" id="ld-fm-ical" name="ical_url" type="text" inputmode="url" value="<?php echo esc_attr( (string) $m( 'ical_url' ) ); ?>" placeholder="https://…/calendar.ics">
                </div>
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                    <button type="button" class="ld-fm-btn ld-fm-btn--ghost" id="ld-fm-ical-sync" <?php echo $is_edit ? '' : 'disabled'; ?>>
                        <span class="material-symbols-outlined">sync</span><?php esc_html_e( 'Sync now', 'ovr-core' ); ?>
                    </button>
                    <span class="ld-fm-subhint" id="ld-fm-ical-status" style="margin-top:0">
                        <?php
                        if ( ! $is_edit ) {
                            esc_html_e( 'Save the listing first to enable syncing.', 'ovr-core' );
                        } else {
                            $last = (string) get_post_meta( $pid, '_ovr_ical_last_sync', true );
                            if ( $last ) {
                                printf(
                                    /* translators: %s: date/time of last sync */
                                    esc_html__( 'Last synced: %s', 'ovr-core' ),
                                    esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last ) )
                                );
                            }
                        }
                        ?>
                    </span>
                </div>

                <?php if ( $is_edit ) : ?>
                    <div class="ld-fm-field" style="margin-top:20px">
                        <label class="ld-fm-label" for="ld-fm-ical-export"><?php esc_html_e( 'Your shareable calendar link (export)', 'ovr-core' ); ?></label>
                        <input class="ld-fm-input" id="ld-fm-ical-export" type="text" readonly value="<?php echo esc_attr( \OVR\Property\IcalSync::export_url( $pid ) ); ?>" onclick="this.select()">
                        <p class="ld-fm-subhint"><?php esc_html_e( 'Paste this link into Airbnb, VRBO, or Google Calendar so they block the dates you have booked here.', 'ovr-core' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ── TAB 5: Videos ── -->
        <section class="ld-fm-panel" data-ld-panel="videos">
            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Video', 'ovr-core' ); ?></h2>
                <p class="ld-fm-hint"><?php esc_html_e( 'When a listing has a video it becomes the primary media — shown first on the listing page and flagged on search cards. Upload a file (MP4, MOV, or WebM) or paste a YouTube/Vimeo link.', 'ovr-core' ); ?></p>

                <div class="ld-fm-field">
                    <label class="ld-fm-label"><?php esc_html_e( 'Upload a video', 'ovr-core' ); ?></label>
                    <div class="ld-fm-media-up" data-ld-media="video">
                        <input type="hidden" name="video_id" id="ld-fm-video-id" value="<?php echo esc_attr( (string) $video_att_id ); ?>">
                        <input type="file" id="ld-fm-video-file" accept="video/mp4,video/quicktime,video/webm" hidden>
                        <div class="ld-fm-media-preview" id="ld-fm-video-preview"<?php echo $video_att_url ? '' : ' hidden'; ?>>
                            <?php if ( $video_att_url ) : ?>
                                <video src="<?php echo esc_url( $video_att_url ); ?>" controls preload="metadata"></video>
                            <?php endif; ?>
                        </div>
                        <div class="ld-fm-media-actions">
                            <button type="button" class="ld-fm-btn ld-fm-media-pick"><span class="material-symbols-outlined">upload</span><?php esc_html_e( 'Choose video', 'ovr-core' ); ?></button>
                            <button type="button" class="ld-fm-btn ld-fm-media-remove"<?php echo $video_att_id ? '' : ' hidden'; ?>><span class="material-symbols-outlined">delete</span><?php esc_html_e( 'Remove', 'ovr-core' ); ?></button>
                        </div>
                        <p class="ld-fm-media-status" role="status"></p>
                    </div>
                </div>

                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-video"><?php esc_html_e( 'Or paste a video link (YouTube or Vimeo)', 'ovr-core' ); ?></label>
                    <input class="ld-fm-input" id="ld-fm-video" name="video_url" type="text" inputmode="url" value="<?php echo esc_attr( (string) $m( 'video_url' ) ); ?>" placeholder="https://www.youtube.com/watch?v=…">
                </div>
            </div>
        </section>

        <!-- ── TAB 6: Panorama / Virtual Tour ── -->
        <section class="ld-fm-panel" data-ld-panel="panorama">
            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Panorama / Virtual Tour', 'ovr-core' ); ?></h2>
                <p class="ld-fm-hint"><?php esc_html_e( 'Add an immersive 360° experience. Upload a 360°/panorama image, or paste a virtual-tour link (Matterport, Kuula, or any tour URL). A “Virtual Tour” button then appears on your listing.', 'ovr-core' ); ?></p>

                <div class="ld-fm-field">
                    <label class="ld-fm-label"><?php esc_html_e( 'Upload a 360° / panorama image', 'ovr-core' ); ?></label>
                    <div class="ld-fm-media-up" data-ld-media="pano">
                        <input type="hidden" name="panorama_id" id="ld-fm-pano-id" value="<?php echo esc_attr( (string) $pano_att_id ); ?>">
                        <input type="file" id="ld-fm-pano-file" accept="image/jpeg,image/png,image/webp" hidden>
                        <div class="ld-fm-media-preview" id="ld-fm-pano-preview"<?php echo $pano_att_url ? '' : ' hidden'; ?>>
                            <?php if ( $pano_att_url ) : ?>
                                <img src="<?php echo esc_url( $pano_att_url ); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <div class="ld-fm-media-actions">
                            <button type="button" class="ld-fm-btn ld-fm-media-pick"><span class="material-symbols-outlined">upload</span><?php esc_html_e( 'Choose image', 'ovr-core' ); ?></button>
                            <button type="button" class="ld-fm-btn ld-fm-media-remove"<?php echo $pano_att_id ? '' : ' hidden'; ?>><span class="material-symbols-outlined">delete</span><?php esc_html_e( 'Remove', 'ovr-core' ); ?></button>
                        </div>
                        <p class="ld-fm-media-status" role="status"></p>
                    </div>
                </div>

                <div class="ld-fm-field">
                    <label class="ld-fm-label" for="ld-fm-pano"><?php esc_html_e( 'Or paste a virtual-tour link', 'ovr-core' ); ?></label>
                    <input class="ld-fm-input" id="ld-fm-pano" name="panorama_url" type="text" inputmode="url" value="<?php echo esc_attr( (string) $m( 'panorama_url' ) ); ?>" placeholder="https://my.matterport.com/show/?m=…">
                </div>
            </div>
        </section>

        <!-- ── TAB 7: Documents ── -->
        <section class="ld-fm-panel" data-ld-panel="documents">
            <div class="ld-fm-card">
                <h2 class="ld-fm-sec"><?php esc_html_e( 'Documents', 'ovr-core' ); ?></h2>
                <p class="ld-fm-hint">
                    <?php
                    /* translators: %d: max documents */
                    printf( esc_html__( 'Share downloadable resources (rental agreement, house rules, golf-cart info…). Up to %d files — PDF, DOCX, or XLSX. Give each a title; drag the order field to control display order.', 'ovr-core' ), (int) \OVR\Frontend\ListingForm::MAX_DOCS );
                    ?>
                </p>

                <div class="ld-fm-docs" id="ld-fm-docs" data-max="<?php echo (int) \OVR\Frontend\ListingForm::MAX_DOCS; ?>">
                    <?php foreach ( $doc_rows as $i => $doc ) : ?>
                        <div class="ld-fm-doc-row" data-id="<?php echo esc_attr( (string) $doc['id'] ); ?>">
                            <input type="hidden" name="document_ids[]" value="<?php echo esc_attr( (string) $doc['id'] ); ?>">
                            <span class="ld-fm-doc-ext"><?php echo esc_html( $doc['ext'] ?: 'DOC' ); ?></span>
                            <input type="text" class="ld-fm-input ld-fm-doc-title" name="doc_titles[<?php echo esc_attr( (string) $doc['id'] ); ?>]" value="<?php echo esc_attr( (string) $doc['title'] ); ?>" placeholder="<?php esc_attr_e( 'Document title', 'ovr-core' ); ?>" maxlength="160">
                            <input type="number" class="ld-fm-input ld-fm-doc-order" name="doc_orders[<?php echo esc_attr( (string) $doc['id'] ); ?>]" value="<?php echo esc_attr( (string) $i ); ?>" min="0" step="1" title="<?php esc_attr_e( 'Display order', 'ovr-core' ); ?>">
                            <a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener" class="ld-fm-doc-view" title="<?php esc_attr_e( 'Open', 'ovr-core' ); ?>"><span class="material-symbols-outlined">open_in_new</span></a>
                            <button type="button" class="ld-fm-doc-remove" title="<?php esc_attr_e( 'Remove', 'ovr-core' ); ?>"><span class="material-symbols-outlined">delete</span></button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <input type="file" id="ld-fm-doc-file" accept=".pdf,.doc,.docx,.xls,.xlsx,application/pdf" hidden>
                <button type="button" class="ld-fm-btn ld-fm-media-pick" id="ld-fm-doc-add" data-target="ld-fm-doc-file"><span class="material-symbols-outlined">note_add</span><?php esc_html_e( 'Add document', 'ovr-core' ); ?></button>
                <p class="ld-fm-media-status" id="ld-fm-doc-status" role="status"></p>
            </div>
        </section>

        <!-- Sticky action bar — single, always-visible Save Changes button -->
        <div class="ld-fm-bar">
            <div class="ld-fm-bar-steps">
                <button type="button" class="ld-fm-stepbtn" id="ld-fm-prev"><span class="material-symbols-outlined">chevron_left</span><?php esc_html_e( 'Back', 'ovr-core' ); ?></button>
                <button type="button" class="ld-fm-stepbtn" id="ld-fm-next"><?php esc_html_e( 'Next', 'ovr-core' ); ?><span class="material-symbols-outlined">chevron_right</span></button>
            </div>
            <div class="ld-fm-bar-actions">
                <button type="submit" class="ld-fm-btn ld-fm-btn--xl ld-fm-btn--primary" id="ld-fm-publish">
                    <span class="material-symbols-outlined">save</span><?php esc_html_e( 'Save Changes', 'ovr-core' ); ?>
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<style>
    .ovr-ld .ld-fm{display:flex;flex-direction:column;gap:20px;width:100%;padding-bottom:8px}
    .ovr-ld .ld-fm-back{display:inline-flex;align-items:center;gap:4px;color:var(--sv);font-size:13px;font-weight:600;text-decoration:none;margin-bottom:8px}
    .ovr-ld .ld-fm-back:hover{color:var(--p)}
    .ovr-ld .ld-fm-back .material-symbols-outlined{font-size:18px}
    .ovr-ld .ld-fm-h1{font-size:32px;font-weight:700;letter-spacing:-.01em;color:var(--on);margin:0}
    .ovr-ld .ld-fm-statuspill{display:inline-block;margin-top:10px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:4px 12px;border-radius:9999px;background:#fff3cd;color:#7a5b00;border:1px solid #f0d98a}
    .ovr-ld .ld-fm-statuspill.is-live{background:#d6f3e6;color:#00714e;border-color:#a6e3c8}
    .ovr-ld .ld-fm-statuspill.is-id{background:rgba(0,76,76,.08);color:var(--p);border-color:var(--p);font-variant-numeric:tabular-nums}

    /* Status card (Property ID + owner/admin status) */
    .ovr-ld .ld-fm-statuscard{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;justify-content:space-between}
    .ovr-ld .ld-fm-statuscard-id{display:flex;flex-direction:column;gap:4px;min-width:160px}
    .ovr-ld .ld-fm-statuscard-id-label{font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv)}
    .ovr-ld .ld-fm-statuscard-id-num{font-size:30px;font-weight:800;color:var(--p);line-height:1;font-variant-numeric:tabular-nums}
    .ovr-ld .ld-fm-statuscard-controls{display:flex;flex-wrap:wrap;gap:18px;flex:1 1 360px;min-width:0}
    .ovr-ld .ld-fm-statuscard-controls .ld-fm-field{flex:1 1 220px;min-width:0}
    .ovr-ld .ld-fm-adminbadge{display:inline-block;margin-left:6px;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:2px 7px;border-radius:9999px;background:#fde2e2;color:#93000a;vertical-align:1px}
    @media (max-width:600px){ .ovr-ld .ld-fm-statuscard-controls .ld-fm-field{flex:1 1 100%} }
    .ovr-ld .ld-fm-alert{display:flex;align-items:center;gap:10px;border-radius:12px;padding:14px 18px;font-size:14px;font-weight:600}
    .ovr-ld .ld-fm-alert.err{background:var(--errc);color:#93000a}
    .ovr-ld .ld-fm-alert.ok{background:#d6f3e6;color:#00714e}
    .ovr-ld .ld-fm-alert .material-symbols-outlined{font-size:20px}

    .ovr-ld .ld-fm-limit{background:var(--surf);border:1px solid var(--ov);border-radius:16px;padding:48px 28px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.04)}
    .ovr-ld .ld-fm-limit .material-symbols-outlined{font-size:40px;color:var(--ter)}
    .ovr-ld .ld-fm-limit h2{font-size:22px;font-weight:700;margin:12px 0 6px;color:var(--on)}
    .ovr-ld .ld-fm-limit p{font-size:14px;color:var(--sv);margin:0 0 20px}

    /* Step tabs */
    .ovr-ld .ld-fm-tabs{display:flex;flex-wrap:nowrap;gap:6px;background:var(--surf);border:1px solid var(--ov);border-radius:14px;padding:8px;box-shadow:0 4px 24px rgba(0,0,0,.04)}
    .ovr-ld .ld-fm-tab{display:flex;align-items:center;gap:8px;flex:1 1 0;min-width:0;justify-content:center;background:transparent;border:1px solid transparent;border-radius:10px;padding:10px 10px;font-family:inherit;font-size:13px;font-weight:600;color:var(--sv);cursor:pointer;transition:background .15s,color .15s,border-color .15s}
    .ovr-ld .ld-fm-tab:hover{background:var(--sclow);color:var(--on)}
    .ovr-ld .ld-fm-tab.is-active{background:rgba(0,76,76,.08);border-color:var(--p);color:var(--p)}
    .ovr-ld .ld-fm-tab-num{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:var(--ov);color:var(--on);font-size:13px;font-weight:700;flex-shrink:0}
    .ovr-ld .ld-fm-tab-label{white-space:normal;line-height:1.15;text-align:left}
    .ovr-ld .ld-fm-tab.is-active .ld-fm-tab-num{background:var(--p);color:#fff}
    .ovr-ld .ld-fm-tab.is-done .ld-fm-tab-num{background:#00714e;color:#fff}

    .ovr-ld .ld-fm-form{display:flex;flex-direction:column;gap:20px}
    .ovr-ld .ld-fm-panel{display:none;flex-direction:column;gap:20px}
    .ovr-ld .ld-fm-panel.is-active{display:flex}
    .ovr-ld .ld-fm-card{background:var(--surf);border:1px solid var(--ov);border-radius:16px;padding:26px;box-shadow:0 4px 24px rgba(0,0,0,.04)}
    .ovr-ld .ld-fm-sec{font-size:20px;font-weight:700;color:var(--on);margin:0 0 16px;padding:0 0 14px;border-bottom:1px solid var(--ov)}
    .ovr-ld .ld-fm-field{display:flex;flex-direction:column;gap:7px;margin-bottom:16px}
    .ovr-ld .ld-fm-field:last-child{margin-bottom:0}
    .ovr-ld .ld-fm-label{font-size:13px;font-weight:600;letter-spacing:.03em;color:var(--on)}
    .ovr-ld .ld-fm-req{color:var(--err);font-weight:700}
    .ovr-ld .ld-fm-subhint{font-size:12px;color:var(--sv);margin:6px 0 0;line-height:1.45}
    .ovr-ld .ld-fm-input{width:100%;background:#fff;border:1px solid var(--ov);border-radius:9px;padding:13px 14px;font-family:inherit;font-size:16px;color:var(--on);outline:none;transition:border-color .15s,box-shadow .15s}
    .ovr-ld .ld-fm-input:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(0,76,76,.12)}
    .ovr-ld .ld-fm-input.is-invalid{border-color:var(--err);box-shadow:0 0 0 3px rgba(186,26,26,.14)}
    .ovr-ld .ld-fm-textarea{resize:vertical;min-height:130px;line-height:1.6}
    .ovr-ld .ld-fm-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
    .ovr-ld .ld-fm-grid .ld-fm-field{margin-bottom:0}
    .ovr-ld .ld-fm-check{display:flex;align-items:center;gap:9px;font-size:15px;color:var(--on);cursor:pointer;align-self:center}
    .ovr-ld .ld-fm-check input{width:20px;height:20px;accent-color:var(--p)}

    .ovr-ld .ld-fm-amenities{display:grid;grid-template-columns:repeat(4,1fr);gap:12px 18px}
    .ovr-ld .ld-fm-chip{display:flex;align-items:center;gap:8px;font-size:14px;color:var(--on);cursor:pointer}
    .ovr-ld .ld-fm-chip input{width:18px;height:18px;accent-color:var(--p);flex-shrink:0}
    @media (max-width:820px){ .ovr-ld .ld-fm-amenities{grid-template-columns:repeat(3,1fr)} }

    .ovr-ld .ld-fm-hint{font-size:14px;color:var(--sv);margin:0 0 16px;line-height:1.6}
    .ovr-ld .ld-fm-drop{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:32px;border:2px dashed var(--ov);border-radius:12px;cursor:pointer;color:var(--sv);font-size:15px;font-weight:600;transition:border-color .15s,background .15s}
    .ovr-ld .ld-fm-drop:hover{border-color:var(--p);background:rgba(0,76,76,.03);color:var(--p)}
    .ovr-ld .ld-fm-drop .material-symbols-outlined{font-size:34px}
    .ovr-ld .ld-fm-thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-top:16px}
    .ovr-ld .ld-fm-thumb{position:relative;border-radius:12px;overflow:hidden;border:1px solid var(--ov);background:#fff;display:flex;flex-direction:column}
    .ovr-ld .ld-fm-thumb.is-primary{border-color:var(--p);box-shadow:0 0 0 2px rgba(0,76,76,.28)}
    .ovr-ld .ld-fm-thumb.is-dragging{opacity:.4}
    .ovr-ld .ld-fm-thumb.is-dragover{outline:3px dashed var(--p);outline-offset:-3px}
    .ovr-ld .ld-fm-thumb-media{position:relative;aspect-ratio:4/3;background:var(--sclow);cursor:grab}
    .ovr-ld .ld-fm-thumb-media:active{cursor:grabbing}
    .ovr-ld .ld-fm-thumb-media img{width:100%;height:100%;object-fit:cover;display:block;pointer-events:none}
    .ovr-ld .ld-fm-thumb.is-uploading{display:flex;align-items:center;justify-content:center;aspect-ratio:4/3}
    .ovr-ld .ld-fm-thumb-main{display:none;position:absolute;top:8px;left:8px;align-items:center;gap:4px;background:var(--p);color:#fff;font-size:11px;font-weight:700;padding:4px 9px;border-radius:7px;box-shadow:0 1px 4px rgba(0,0,0,.3)}
    .ovr-ld .ld-fm-thumb-main .material-symbols-outlined{font-size:14px}
    .ovr-ld .ld-fm-thumb.is-primary .ld-fm-thumb-main{display:inline-flex}
    .ovr-ld .ld-fm-thumb-wm{display:none;position:absolute;top:8px;left:8px;align-items:center;gap:4px;background:rgba(10,31,44,.85);color:#fff;font-size:10px;font-weight:700;padding:3px 8px;border-radius:7px;box-shadow:0 1px 4px rgba(0,0,0,.3)}
    .ovr-ld .ld-fm-thumb-wm .material-symbols-outlined{font-size:13px}
    .ovr-ld .ld-fm-thumb.is-watermarked .ld-fm-thumb-wm{display:inline-flex}
    .ovr-ld .ld-fm-thumb.is-primary.is-watermarked .ld-fm-thumb-wm{top:40px}
    .ovr-ld .ld-fm-thumb-tools{position:absolute;top:8px;right:8px;display:flex;gap:6px}
    .ovr-ld .ld-fm-thumb-btn{width:30px;height:30px;border:none;border-radius:7px;background:rgba(0,0,0,.6);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
    .ovr-ld .ld-fm-thumb-btn:hover{background:rgba(0,0,0,.82)}
    .ovr-ld .ld-fm-thumb-btn .material-symbols-outlined{font-size:17px}
    .ovr-ld .ld-fm-setmain{display:inline-flex;align-items:center;justify-content:center;gap:5px;position:absolute;left:8px;right:8px;bottom:8px;border:none;border-radius:8px;background:rgba(255,255,255,.95);color:var(--p);font-family:inherit;font-size:12px;font-weight:700;padding:7px 8px;cursor:pointer;box-shadow:0 1px 6px rgba(0,0,0,.25)}
    .ovr-ld .ld-fm-setmain:hover{background:#fff}
    .ovr-ld .ld-fm-setmain .material-symbols-outlined{font-size:15px}
    .ovr-ld .ld-fm-thumb.is-primary .ld-fm-setmain{display:none}
    .ovr-ld .ld-fm-cap{width:100%;border:none;border-top:1px solid var(--ov);padding:9px 11px;font-family:inherit;font-size:13px;color:var(--on);outline:none;background:#fff;box-sizing:border-box}
    .ovr-ld .ld-fm-cap:focus{background:var(--sclow)}
    .ovr-ld [id="ld-fm-uperr"]{font-size:13px;color:var(--err);margin:10px 0 0;min-height:1px}
    .ovr-ld .ld-fm-spin{width:26px;height:26px;border:3px solid var(--ov);border-top-color:var(--p);border-radius:50%;animation:ldfmspin .8s linear infinite}
    @keyframes ldfmspin{to{transform:rotate(360deg)}}
    .ovr-ld .ld-fm-thumb.is-uploading{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:140px}
    .ovr-ld .ld-fm-prog{position:absolute;left:14px;right:14px;bottom:14px;height:6px;border-radius:9999px;background:var(--ov);overflow:hidden}
    .ovr-ld .ld-fm-prog > i{display:block;height:100%;width:0;background:var(--p);border-radius:9999px;transition:width .15s ease}
    .ovr-ld .ld-fm-prog-pct{position:absolute;bottom:24px;font-size:12px;font-weight:700;color:var(--on)}
    /* Crop modal */
    .ld-crop-overlay{position:fixed;inset:0;z-index:100000;background:rgba(10,14,24,.72);display:flex;align-items:center;justify-content:center;padding:20px}
    .ld-crop-dialog{background:#fff;border-radius:14px;max-width:680px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.4);overflow:hidden;display:flex;flex-direction:column}
    .ld-crop-head{padding:16px 20px;border-bottom:1px solid #e3e3e3;font-size:16px}
    .ld-crop-stage{position:relative;background:#11151c;max-height:60vh;display:flex;align-items:center;justify-content:center;overflow:hidden;user-select:none}
    .ld-crop-img{max-width:100%;max-height:60vh;display:block;-webkit-user-drag:none}
    .ld-crop-sel{position:absolute;border:2px solid #fff;box-shadow:0 0 0 9999px rgba(0,0,0,.45);cursor:move;box-sizing:border-box}
    .ld-crop-sel i{position:absolute;width:14px;height:14px;background:#fff;border-radius:2px}
    .ld-crop-sel i[data-h=nw]{left:-7px;top:-7px;cursor:nwse-resize}
    .ld-crop-sel i[data-h=ne]{right:-7px;top:-7px;cursor:nesw-resize}
    .ld-crop-sel i[data-h=sw]{left:-7px;bottom:-7px;cursor:nesw-resize}
    .ld-crop-sel i[data-h=se]{right:-7px;bottom:-7px;cursor:nwse-resize}
    .ld-crop-foot{padding:14px 20px;border-top:1px solid #e3e3e3;display:flex;justify-content:flex-end;gap:10px}

    /* Availability repeater */
    .ovr-ld .ld-fm-avail{display:flex;flex-direction:column;gap:14px;margin-bottom:16px}
    .ovr-ld .ld-fm-avail-row{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;padding:14px;border:1px solid var(--ov);border-radius:12px;background:var(--sclow)}
    .ovr-ld .ld-fm-avail-row .ld-fm-field{margin-bottom:0}
    .ovr-ld .ld-fm-avail-x{width:42px;height:46px;border:1px solid var(--ov);border-radius:9px;background:#fff;color:var(--err);cursor:pointer;display:flex;align-items:center;justify-content:center}
    .ovr-ld .ld-fm-avail-x:hover{background:var(--errc)}

    /* Pricing table — handle | Name-Year | From | To | Price | Per | Min Term | actions */
    .ovr-ld .ld-fm-price-cols{grid-template-columns:26px 1.5fr 1fr 1fr .9fr 1fr .7fr 82px}
    .ovr-ld .ld-fm-price-head{display:grid;gap:10px;padding:0 4px 8px;font-size:11px;font-weight:700;letter-spacing:.02em;text-transform:uppercase;color:var(--sv);grid-template-columns:26px 1.5fr 1fr 1fr .9fr 1fr .7fr 82px}
    .ovr-ld .ld-fm-price-list{display:flex;flex-direction:column;gap:12px;margin-bottom:16px}
    .ovr-ld .ld-fm-price-row{display:grid;gap:10px;align-items:end;padding:12px;border:1px solid var(--ov);border-radius:12px;background:var(--sclow);grid-template-columns:26px 1.5fr 1fr 1fr .9fr 1fr .7fr 82px}
    .ovr-ld .ld-fm-price-row.is-dragging{opacity:.4}
    .ovr-ld .ld-fm-price-row.is-dragover{outline:2px dashed var(--p);outline-offset:-2px}
    .ovr-ld .ld-fm-price-row .ld-fm-field{margin-bottom:0;min-width:0}
    .ovr-ld .ld-fm-price-handle{display:flex;align-items:center;justify-content:center;color:var(--sv);cursor:grab;height:46px}
    .ovr-ld .ld-fm-price-handle:active{cursor:grabbing}
    .ovr-ld .ld-fm-price-mlabel{display:none}
    .ovr-ld .ld-fm-price-actions{display:flex;align-items:end;gap:4px;height:46px}
    .ovr-ld .ld-fm-price-move,.ovr-ld .ld-fm-price-x{width:34px;height:46px;border:1px solid var(--ov);border-radius:9px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--on)}
    .ovr-ld .ld-fm-price-move:hover{background:var(--sclow)}
    .ovr-ld .ld-fm-price-x{color:var(--err)}
    .ovr-ld .ld-fm-price-x:hover{background:var(--errc)}
    .ovr-ld .ld-fm-price-move .material-symbols-outlined,.ovr-ld .ld-fm-price-x .material-symbols-outlined{font-size:19px}
    .ovr-ld .ld-fm-pricehide input{width:20px;height:20px;accent-color:var(--p)}
    .ovr-ld #ld-fm-price-wrap.is-hidden{opacity:.45;pointer-events:none}
    @media (max-width:900px){
        .ovr-ld .ld-fm-price-head{display:none}
        .ovr-ld .ld-fm-price-row{grid-template-columns:1fr 1fr;align-items:stretch}
        .ovr-ld .ld-fm-price-handle{display:none}
        .ovr-ld .ld-fm-price-mlabel{display:block}
        .ovr-ld .ld-fm-price-row .ld-fm-field:first-of-type{grid-column:1 / -1}
        .ovr-ld .ld-fm-price-actions{grid-column:1 / -1;height:auto}
        .ovr-ld .ld-fm-price-move,.ovr-ld .ld-fm-price-x{flex:1}
    }

    /* Buttons + sticky action bar */
    .ovr-ld .ld-fm-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 26px;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:background .18s}
    .ovr-ld .ld-fm-btn .material-symbols-outlined{font-size:20px}
    .ovr-ld .ld-fm-btn--primary{background:var(--p);color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.12)}
    .ovr-ld .ld-fm-btn--primary:hover{background:#003838}
    .ovr-ld .ld-fm-btn--ghost{background:transparent;color:var(--on);border-color:var(--ov)}
    .ovr-ld .ld-fm-btn--ghost:hover{background:var(--sclow)}
    .ovr-ld .ld-fm-btn--xl{font-size:17px;padding:16px 30px;border-radius:12px}

    .ovr-ld .ld-fm-bar{position:sticky;bottom:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border:1px solid var(--ov);border-radius:14px;padding:14px 18px;box-shadow:0 -2px 16px rgba(0,0,0,.07),0 4px 24px rgba(0,0,0,.04)}
    .ovr-ld .ld-fm-bar-steps{display:flex;gap:8px}
    .ovr-ld .ld-fm-stepbtn{display:inline-flex;align-items:center;gap:4px;background:transparent;border:1px solid var(--ov);border-radius:10px;padding:11px 16px;font-family:inherit;font-size:14px;font-weight:600;color:var(--on);cursor:pointer}
    .ovr-ld .ld-fm-stepbtn:hover{background:var(--sclow)}
    .ovr-ld .ld-fm-stepbtn[disabled]{opacity:.4;cursor:default}
    .ovr-ld .ld-fm-stepbtn .material-symbols-outlined{font-size:20px}
    .ovr-ld .ld-fm-bar-actions{display:flex;gap:12px;flex-wrap:wrap}

    @media (max-width:600px){
        .ovr-ld .ld-fm-grid{grid-template-columns:1fr}
        .ovr-ld .ld-fm-amenities{grid-template-columns:repeat(2,1fr)}
        .ovr-ld .ld-fm-h1{font-size:26px}
        .ovr-ld .ld-fm-tab-label{display:none}
        .ovr-ld .ld-fm-tab{min-width:0;flex:1 1 auto}
        .ovr-ld .ld-fm-avail-row{grid-template-columns:1fr 1fr;}
        .ovr-ld .ld-fm-avail-x{grid-column:1 / -1;width:100%}
        .ovr-ld .ld-fm-price-head{display:none}
        .ovr-ld .ld-fm-price-row{grid-template-columns:1fr;align-items:stretch}
        .ovr-ld .ld-fm-price-mlabel{display:block}
        .ovr-ld .ld-fm-price-x{width:100%}
        .ovr-ld .ld-fm-bar{flex-direction:column;align-items:stretch}
        .ovr-ld .ld-fm-bar-actions{flex-direction:column-reverse}
        .ovr-ld .ld-fm-bar-actions .ld-fm-btn{width:100%}
        .ovr-ld .ld-fm-bar-steps{justify-content:space-between}
    }

    /* Single-file media uploaders (video / panorama) + documents */
    .ovr-ld .ld-fm-media-up{display:flex;flex-direction:column;gap:12px;align-items:flex-start}
    .ovr-ld .ld-fm-media-preview{width:100%;max-width:480px;border-radius:12px;overflow:hidden;background:var(--surf,#000);border:1px solid var(--gray-border,#dbdbdb)}
    .ovr-ld .ld-fm-media-preview video,.ovr-ld .ld-fm-media-preview img{display:block;width:100%;max-height:300px;object-fit:cover}
    .ovr-ld .ld-fm-media-actions{display:flex;gap:10px;flex-wrap:wrap}
    .ovr-ld .ld-fm-media-status{font-size:13px;color:var(--sv,#5f6b7a);margin:0;min-height:1em}
    .ovr-ld .ld-fm-docs{display:flex;flex-direction:column;gap:10px;margin-bottom:14px}
    .ovr-ld .ld-fm-doc-row{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--gray-border,#dbdbdb);border-radius:10px;background:var(--surf,#fff)}
    .ovr-ld .ld-fm-doc-ext{flex:0 0 auto;font-size:11px;font-weight:800;letter-spacing:.04em;padding:4px 8px;border-radius:6px;background:var(--p-light,#e0f0ee);color:var(--p,#004c4c)}
    .ovr-ld .ld-fm-doc-title{flex:1 1 auto;min-width:0}
    .ovr-ld .ld-fm-doc-order{flex:0 0 78px;width:78px}
    .ovr-ld .ld-fm-doc-view,.ovr-ld .ld-fm-doc-remove{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:8px;border:1px solid var(--gray-border,#dbdbdb);background:var(--surf,#fff);cursor:pointer;color:var(--on,#1c2430);text-decoration:none}
    .ovr-ld .ld-fm-doc-remove{color:#93000a;border-color:#93000a}
    @media (max-width:600px){
        .ovr-ld .ld-fm-doc-row{flex-wrap:wrap}
        .ovr-ld .ld-fm-doc-title{flex:1 1 100%;order:1}
    }
</style>

<script>
(function(){
    var root = document.querySelector('.ovr-ld .ld-fm');
    var form = root && root.querySelector('.ld-fm-form');
    if (!form) { return; }

    // ── Tab navigation ──
    var STEPS = ['info','photos','pricing','calendar','videos','panorama','documents'];
    var tabs  = Array.prototype.slice.call(root.querySelectorAll('.ld-fm-tab'));
    var panels= Array.prototype.slice.call(root.querySelectorAll('.ld-fm-panel'));
    var prevBtn = document.getElementById('ld-fm-prev');
    var nextBtn = document.getElementById('ld-fm-next');
    var cur = 0;

    function show(idx){
        cur = Math.max(0, Math.min(STEPS.length - 1, idx));
        var key = STEPS[cur];
        tabs.forEach(function(t, i){
            var active = (t.getAttribute('data-ld-tab') === key);
            t.classList.toggle('is-active', active);
            t.classList.toggle('is-done', i < cur);
        });
        panels.forEach(function(p){
            p.classList.toggle('is-active', p.getAttribute('data-ld-panel') === key);
        });
        if (prevBtn) { prevBtn.disabled = (cur === 0); }
        if (nextBtn) { nextBtn.disabled = (cur === STEPS.length - 1); }
        try { root.scrollIntoView({ behavior:'smooth', block:'start' }); } catch(e){}
    }
    tabs.forEach(function(t){ t.addEventListener('click', function(){ show(STEPS.indexOf(t.getAttribute('data-ld-tab'))); }); });
    if (prevBtn) { prevBtn.addEventListener('click', function(){ show(cur - 1); }); }
    if (nextBtn) { nextBtn.addEventListener('click', function(){ show(cur + 1); }); }

    // ── Short description live character counter (max 200) ──
    var shortDesc = form.querySelector('[name="short_description"]');
    var shortCount = document.getElementById('ld-fm-shortdesc-count');
    if (shortDesc && shortCount) {
        var updateCount = function(){ shortCount.textContent = String(shortDesc.value.length); };
        shortDesc.addEventListener('input', updateCount);
        updateCount();
    }

    // ── Save validation ──
    // Required to save: Title, Village Section, Village Name (all on step 1).
    var publishBtn = document.getElementById('ld-fm-publish');
    if (publishBtn) {
        publishBtn.addEventListener('click', function(e){
            var req = [
                { el: form.querySelector('[name="title"]'),             bad: function(v){ return v.trim() === ''; } },
                { el: form.querySelector('[name="short_description"]'), bad: function(v){ return v.trim() === ''; } },
                { el: form.querySelector('[name="village_section"]'),   bad: function(v){ return v.trim() === '' || v === '0'; } },
                { el: form.querySelector('[name="village_name"]'),      bad: function(v){ return v.trim() === ''; } }
            ];
            var firstBad = null;
            req.forEach(function(r){
                if (!r.el) { return; }
                if (r.bad(r.el.value)) {
                    r.el.classList.add('is-invalid');
                    var clear = function(){ r.el.classList.remove('is-invalid'); };
                    r.el.addEventListener('input', clear, { once:true });
                    r.el.addEventListener('change', clear, { once:true });
                    if (!firstBad) { firstBad = r.el; }
                }
            });
            if (firstBad) {
                e.preventDefault();
                show(STEPS.indexOf('info'));
                firstBad.focus();
            }
        });
    }

    // ── Availability repeater ──
    var availWrap = document.getElementById('ld-fm-avail');
    var availTpl  = document.getElementById('ld-fm-avail-tpl');
    var availAdd  = document.getElementById('ld-fm-avail-add');
    var availIdx  = <?php echo (int) ( count( $avail_rows ) + 1 ); ?>;
    if (availAdd && availTpl && availWrap) {
        availAdd.addEventListener('click', function(){
            var html = availTpl.innerHTML.replace(/__i__/g, String(availIdx++));
            var tmp = document.createElement('div'); tmp.innerHTML = html.trim();
            availWrap.appendChild(tmp.firstElementChild);
        });
        availWrap.addEventListener('click', function(e){
            var x = e.target.closest('.ld-fm-avail-x'); if (!x) { return; }
            var row = x.closest('.ld-fm-avail-row'); if (row) { row.remove(); }
        });
    }

    // ── iCal "Sync now" (uses the SAVED feed URL on the listing) ──
    var icalBtn = document.getElementById('ld-fm-ical-sync');
    var icalStatus = document.getElementById('ld-fm-ical-status');
    if (icalBtn) {
        icalBtn.addEventListener('click', function(){
            var pidEl = form.querySelector('[name="post_id"]');
            var pid = pidEl ? pidEl.value : '0';
            if (!pid || pid === '0') {
                if (icalStatus) { icalStatus.textContent = '<?php echo esc_js( __( 'Save the listing first to enable syncing.', 'ovr-core' ) ); ?>'; }
                return;
            }
            icalBtn.disabled = true;
            if (icalStatus) { icalStatus.textContent = '<?php echo esc_js( __( 'Syncing…', 'ovr-core' ) ); ?>'; }
            var fd = new FormData();
            fd.append('action', 'ovr_ical_sync');
            fd.append('nonce', form.getAttribute('data-nonce'));
            fd.append('post_id', pid);
            fetch(form.getAttribute('data-ajax'), { method:'POST', credentials:'same-origin', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (icalStatus) {
                        icalStatus.textContent = (res && res.data && res.data.message) ? res.data.message
                            : (res && res.success ? '<?php echo esc_js( __( 'Calendar synced.', 'ovr-core' ) ); ?>' : '<?php echo esc_js( __( 'Sync failed. Check the URL and save first.', 'ovr-core' ) ); ?>');
                    }
                })
                .catch(function(){ if (icalStatus) { icalStatus.textContent = '<?php echo esc_js( __( 'Sync failed. Please try again.', 'ovr-core' ) ); ?>'; } })
                .then(function(){ icalBtn.disabled = false; });
        });
    }

    // ── Pricing repeater: add · delete · move up/down · drag-reorder ──
    var priceWrap = document.getElementById('ld-fm-price-list');
    var priceTpl  = document.getElementById('ld-fm-price-tpl');
    var priceAdd  = document.getElementById('ld-fm-price-add');
    var priceIdx  = <?php echo (int) ( count( $price_rows ) + 1 ); ?>;
    var priceDrag = null;
    function bindPriceDrag(row){
        row.addEventListener('dragstart', function(e){
            // Don't start a row drag when interacting with an input/select.
            if (e.target.closest('input,select,button') && !e.target.closest('.ld-fm-price-handle')) { e.preventDefault(); return; }
            priceDrag = row;
            if (e.dataTransfer){ e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain',''); } catch(_){} }
            setTimeout(function(){ row.classList.add('is-dragging'); }, 0);
        });
        row.addEventListener('dragend', function(){ row.classList.remove('is-dragging'); priceDrag = null; });
    }
    if (priceWrap) {
        Array.prototype.forEach.call(priceWrap.querySelectorAll('.ld-fm-price-row'), bindPriceDrag);
        priceWrap.addEventListener('dragover', function(e){
            if (!priceDrag) { return; }
            e.preventDefault();
            var over = e.target.closest('.ld-fm-price-row');
            if (!over || over === priceDrag) { return; }
            var r = over.getBoundingClientRect();
            if (e.clientY < r.top + r.height / 2) { priceWrap.insertBefore(priceDrag, over); }
            else { priceWrap.insertBefore(priceDrag, over.nextSibling); }
        });
    }
    if (priceAdd && priceTpl && priceWrap) {
        priceAdd.addEventListener('click', function(){
            var html = priceTpl.innerHTML.replace(/__i__/g, String(priceIdx++));
            var tmp = document.createElement('div'); tmp.innerHTML = html.trim();
            var row = tmp.firstElementChild;
            priceWrap.appendChild(row);
            bindPriceDrag(row);
        });
        priceWrap.addEventListener('click', function(e){
            var row;
            var x = e.target.closest('.ld-fm-price-x');
            if (x) { row = x.closest('.ld-fm-price-row'); if (row) { row.remove(); } return; }
            var mv = e.target.closest('.ld-fm-price-move');
            if (mv) {
                row = mv.closest('.ld-fm-price-row'); if (!row) { return; }
                if (mv.getAttribute('data-move') === 'up' && row.previousElementSibling) {
                    priceWrap.insertBefore(row, row.previousElementSibling);
                } else if (mv.getAttribute('data-move') === 'down' && row.nextElementSibling) {
                    priceWrap.insertBefore(row.nextElementSibling, row);
                }
            }
        });
    }

    // ── "Check Description For Pricing" — dim/disable the table when ticked ──
    var hideToggle = document.getElementById('ld-fm-hide-pricing');
    var pricePanelWrap = document.getElementById('ld-fm-price-wrap');
    if (hideToggle && pricePanelWrap) {
        var reflectHide = function(){ pricePanelWrap.classList.toggle('is-hidden', hideToggle.checked); };
        hideToggle.addEventListener('change', reflectHide);
        reflectHide();
    }

    // ── Photo management: upload · drag-reorder · rotate · delete · set-main · captions ──
    var ajaxUrl = form.getAttribute('data-ajax'),
        nonce   = form.getAttribute('data-nonce'),
        fileIn  = form.querySelector('#ld-fm-file'),
        thumbs  = form.querySelector('#ld-fm-thumbs'),
        idsIn   = form.querySelector('#ld-fm-gallery-ids'),
        errEl   = form.querySelector('#ld-fm-uperr');

    var T = {
        main:    '<?php echo esc_js( __( 'Main Listing Photo', 'ovr-core' ) ); ?>',
        wm:      '<?php echo esc_js( __( 'Watermarked', 'ovr-core' ) ); ?>',
        setmain: '<?php echo esc_js( __( 'Set as main', 'ovr-core' ) ); ?>',
        rotate:  '<?php echo esc_js( __( 'Rotate photo', 'ovr-core' ) ); ?>',
        crop:    '<?php echo esc_js( __( 'Crop photo', 'ovr-core' ) ); ?>',
        addwm:   '<?php echo esc_js( __( 'Add watermark', 'ovr-core' ) ); ?>',
        del:     '<?php echo esc_js( __( 'Remove photo', 'ovr-core' ) ); ?>',
        cap:     '<?php echo esc_js( __( 'Add a caption…', 'ovr-core' ) ); ?>',
        upfail:  '<?php echo esc_js( __( 'Upload failed. Please try again.', 'ovr-core' ) ); ?>',
        rotfail: '<?php echo esc_js( __( 'Could not rotate the photo. Please try again.', 'ovr-core' ) ); ?>',
        cropfail:'<?php echo esc_js( __( 'Could not crop the photo. Please try again.', 'ovr-core' ) ); ?>',
        cropttl: '<?php echo esc_js( __( 'Crop photo', 'ovr-core' ) ); ?>',
        cropapply:'<?php echo esc_js( __( 'Apply crop', 'ovr-core' ) ); ?>',
        cancel:  '<?php echo esc_js( __( 'Cancel', 'ovr-core' ) ); ?>',
        wmfail:  '<?php echo esc_js( __( 'Could not watermark the photo. Please try again.', 'ovr-core' ) ); ?>'
    };

    if (thumbs) {
        var realTiles = function(){
            return Array.prototype.slice.call(thumbs.querySelectorAll('.ld-fm-thumb'))
                .filter(function(t){ return !t.classList.contains('is-uploading'); });
        };
        // The first photo (DOM order) is the primary; reflect that in the UI.
        var refreshPrimary = function(){
            var first = true;
            realTiles().forEach(function(t){
                if (first) { t.classList.add('is-primary'); first = false; }
                else { t.classList.remove('is-primary'); }
            });
        };
        var syncIds = function(){
            idsIn.value = realTiles().map(function(t){ return t.getAttribute('data-id'); }).join(',');
            refreshPrimary();
        };

        // Build a fresh tile (used for new uploads; existing tiles render in PHP).
        var buildTile = function(id, url){
            var t = document.createElement('div');
            // New uploads are auto-watermarked server-side (Phase 3) — reflect that.
            t.className = 'ld-fm-thumb is-watermarked'; t.setAttribute('data-id', String(id)); t.setAttribute('draggable', 'true');
            t.innerHTML =
                '<div class="ld-fm-thumb-media">' +
                    '<img src="' + url + '" alt="">' +
                    '<span class="ld-fm-thumb-main"><span class="material-symbols-outlined">star</span>' + T.main + '</span>' +
                    '<span class="ld-fm-thumb-wm"><span class="material-symbols-outlined">verified</span>' + T.wm + '</span>' +
                    '<div class="ld-fm-thumb-tools">' +
                        '<button type="button" class="ld-fm-thumb-btn" data-act="rotate" data-dir="left" aria-label="' + T.rotate + '"><span class="material-symbols-outlined">rotate_left</span></button>' +
                        '<button type="button" class="ld-fm-thumb-btn" data-act="rotate" data-dir="right" aria-label="' + T.rotate + '"><span class="material-symbols-outlined">rotate_right</span></button>' +
                        '<button type="button" class="ld-fm-thumb-btn" data-act="crop" aria-label="' + T.crop + '"><span class="material-symbols-outlined">crop</span></button>' +
                        '<button type="button" class="ld-fm-thumb-btn" data-act="delete" aria-label="' + T.del + '"><span class="material-symbols-outlined">delete</span></button>' +
                    '</div>' +
                    '<button type="button" class="ld-fm-setmain" data-act="setmain"><span class="material-symbols-outlined">star</span>' + T.setmain + '</button>' +
                '</div>' +
                '<input type="text" class="ld-fm-cap" name="captions[' + id + ']" value="" placeholder="' + T.cap + '" maxlength="160">';
            bindDrag(t);
            return t;
        };

        // Drag-and-drop reordering (live insert, like a sortable list).
        var dragSrc = null;
        function bindDrag(t){
            t.addEventListener('dragstart', function(e){
                dragSrc = t;
                if (e.dataTransfer){ e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', t.getAttribute('data-id')); } catch (_){} }
                setTimeout(function(){ t.classList.add('is-dragging'); }, 0);
            });
            t.addEventListener('dragend', function(){
                t.classList.remove('is-dragging'); dragSrc = null; syncIds();
            });
        }
        thumbs.addEventListener('dragover', function(e){
            if (!dragSrc) { return; }
            e.preventDefault();
            if (e.dataTransfer) { e.dataTransfer.dropEffect = 'move'; }
            var over = e.target.closest('.ld-fm-thumb');
            if (!over || over === dragSrc || over.classList.contains('is-uploading')) { return; }
            var r = over.getBoundingClientRect();
            if (e.clientX < r.left + r.width / 2) { thumbs.insertBefore(dragSrc, over); }
            else { thumbs.insertBefore(dragSrc, over.nextSibling); }
        });
        thumbs.addEventListener('drop', function(e){ if (dragSrc) { e.preventDefault(); } });

        // Per-tile actions.
        thumbs.addEventListener('click', function(e){
            var btn = e.target.closest('[data-act]'); if (!btn) { return; }
            var tile = btn.closest('.ld-fm-thumb'); if (!tile) { return; }
            var act = btn.getAttribute('data-act');
            if (act === 'delete') {
                tile.remove(); syncIds();
            } else if (act === 'setmain') {
                thumbs.insertBefore(tile, thumbs.firstChild); syncIds();
            } else if (act === 'rotate') {
                editPhoto(tile, btn, 'ovr_rotate_listing_photo', T.rotfail, null, { dir: btn.getAttribute('data-dir') || 'right' });
            } else if (act === 'crop') {
                openCropper(tile);
            }
        });

        // Shared server-side edit (rotate / watermark / crop): re-fetch the image.
        function editPhoto(tile, btn, action, failMsg, onOk, extra){
            var id = tile.getAttribute('data-id'), img = tile.querySelector('img');
            if (btn) { btn.disabled = true; }
            var fd = new FormData();
            fd.append('action', action); fd.append('nonce', nonce); fd.append('id', id);
            var pidEl = form.querySelector('[name="post_id"]');
            if (pidEl) { fd.append('post_id', pidEl.value); }
            if (extra) { Object.keys(extra).forEach(function(k){ fd.append(k, extra[k]); }); }
            return fetch(ajaxUrl, { method:'POST', credentials:'same-origin', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res && res.success && res.data && res.data.url) {
                        img.src = res.data.url + (res.data.url.indexOf('?') > -1 ? '&' : '?') + 'v=' + Date.now();
                        errEl.textContent = '';
                        if (onOk) { onOk(); }
                    } else {
                        errEl.textContent = (res && res.data && res.data.message) ? res.data.message : failMsg;
                    }
                })
                .catch(function(){ errEl.textContent = failMsg; })
                .then(function(){ if (btn) { btn.disabled = false; } });
        }

        function uploadOne(file){
            // Placeholder tile with a live progress ring while the file uploads.
            var ph = document.createElement('div');
            ph.className = 'ld-fm-thumb is-uploading';
            ph.innerHTML = '<span class="ld-fm-spin"></span><span class="ld-fm-prog"><i></i></span><span class="ld-fm-prog-pct">0%</span>';
            thumbs.appendChild(ph);
            var bar = ph.querySelector('.ld-fm-prog > i'), pct = ph.querySelector('.ld-fm-prog-pct');

            return new Promise(function(resolve){
                var fd = new FormData();
                fd.append('action','ovr_upload_listing_photo'); fd.append('nonce',nonce); fd.append('file',file);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxUrl, true);
                xhr.withCredentials = true;
                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function(e){
                        if (e.lengthComputable) {
                            var p = Math.round((e.loaded / e.total) * 100);
                            if (bar) { bar.style.width = p + '%'; }
                            if (pct) { pct.textContent = p + '%'; }
                        }
                    });
                }
                xhr.addEventListener('load', function(){
                    ph.remove();
                    var res = null; try { res = JSON.parse(xhr.responseText); } catch (_) {}
                    if (res && res.success){ thumbs.appendChild(buildTile(res.data.id, res.data.url)); syncIds(); errEl.textContent=''; }
                    else { errEl.textContent = (res && res.data && res.data.message) ? res.data.message : T.upfail; }
                    resolve();
                });
                xhr.addEventListener('error', function(){ ph.remove(); errEl.textContent = T.upfail; resolve(); });
                xhr.send(fd);
            });
        }
        if (fileIn) { fileIn.addEventListener('change', function(){
            var files = Array.prototype.slice.call(fileIn.files || []);
            files.reduce(function(p, f){ return p.then(function(){ return uploadOne(f); }); }, Promise.resolve())
                 .then(function(){ fileIn.value=''; });
        }); }

        // ── Interactive crop ────────────────────────────────────────────
        // Modal with a draggable / corner-resizable selection box. Coordinates
        // are sent as fractions (0..1) so the server maps them onto the real
        // (full-size) file, independent of the medium derivative shown here.
        var cropModal = null;
        function openCropper(tile){
            var img = tile.querySelector('img'); if (!img) { return; }
            closeCropper();

            cropModal = document.createElement('div');
            cropModal.className = 'ld-crop-overlay';
            cropModal.innerHTML =
                '<div class="ld-crop-dialog" role="dialog" aria-modal="true">' +
                    '<div class="ld-crop-head"><strong>' + T.cropttl + '</strong></div>' +
                    '<div class="ld-crop-stage"><img class="ld-crop-img" src="' + img.src + '" alt="">' +
                        '<div class="ld-crop-sel"><i data-h="nw"></i><i data-h="ne"></i><i data-h="sw"></i><i data-h="se"></i></div>' +
                    '</div>' +
                    '<div class="ld-crop-foot">' +
                        '<button type="button" class="ovr-btn ld-crop-cancel">' + T.cancel + '</button>' +
                        '<button type="button" class="ovr-btn ovr-btn-primary ld-crop-apply">' + T.cropapply + '</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(cropModal);

            var stage = cropModal.querySelector('.ld-crop-stage'),
                cimg  = cropModal.querySelector('.ld-crop-img'),
                sel   = cropModal.querySelector('.ld-crop-sel');

            function init(){
                var r = cimg.getBoundingClientRect();
                // Default selection: centered 70%.
                var bw = r.width * 0.7, bh = r.height * 0.7;
                place((r.width - bw) / 2, (r.height - bh) / 2, bw, bh);
            }
            function bounds(){ return cimg.getBoundingClientRect(); }
            function place(x, y, w, h){
                var r = bounds();
                w = Math.max(20, Math.min(w, r.width));
                h = Math.max(20, Math.min(h, r.height));
                x = Math.max(0, Math.min(x, r.width - w));
                y = Math.max(0, Math.min(y, r.height - h));
                sel.style.left = x + 'px'; sel.style.top = y + 'px';
                sel.style.width = w + 'px'; sel.style.height = h + 'px';
            }
            if (cimg.complete) { init(); } else { cimg.addEventListener('load', init); }

            var drag = null;
            function onDown(e){
                var handle = e.target.getAttribute && e.target.getAttribute('data-h');
                var pt = (e.touches ? e.touches[0] : e);
                drag = {
                    mode: handle || 'move',
                    sx: pt.clientX, sy: pt.clientY,
                    L: parseFloat(sel.style.left) || 0, T: parseFloat(sel.style.top) || 0,
                    W: sel.offsetWidth, H: sel.offsetHeight
                };
                e.preventDefault();
            }
            function onMove(e){
                if (!drag) { return; }
                var pt = (e.touches ? e.touches[0] : e);
                var dx = pt.clientX - drag.sx, dy = pt.clientY - drag.sy;
                if (drag.mode === 'move') { place(drag.L + dx, drag.T + dy, drag.W, drag.H); return; }
                var L = drag.L, T2 = drag.T, W = drag.W, H = drag.H;
                if (drag.mode.indexOf('w') > -1) { L = drag.L + dx; W = drag.W - dx; }
                if (drag.mode.indexOf('e') > -1) { W = drag.W + dx; }
                if (drag.mode.indexOf('n') > -1) { T2 = drag.T + dy; H = drag.H - dy; }
                if (drag.mode.indexOf('s') > -1) { H = drag.H + dy; }
                place(L, T2, W, H);
            }
            function onUp(){ drag = null; }
            sel.addEventListener('mousedown', onDown);
            sel.addEventListener('touchstart', onDown, { passive:false });
            document.addEventListener('mousemove', onMove);
            document.addEventListener('touchmove', onMove, { passive:false });
            document.addEventListener('mouseup', onUp);
            document.addEventListener('touchend', onUp);

            cropModal._cleanup = function(){
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('touchmove', onMove);
                document.removeEventListener('mouseup', onUp);
                document.removeEventListener('touchend', onUp);
            };

            cropModal.querySelector('.ld-crop-cancel').addEventListener('click', closeCropper);
            cropModal.addEventListener('click', function(e){ if (e.target === cropModal) { closeCropper(); } });
            cropModal.querySelector('.ld-crop-apply').addEventListener('click', function(){
                var r = bounds();
                var fx = (parseFloat(sel.style.left) || 0) / r.width;
                var fy = (parseFloat(sel.style.top) || 0) / r.height;
                var fw = sel.offsetWidth / r.width;
                var fh = sel.offsetHeight / r.height;
                var applyBtn = cropModal.querySelector('.ld-crop-apply');
                applyBtn.disabled = true;
                editPhoto(tile, applyBtn, 'ovr_crop_listing_photo', T.cropfail, function(){ closeCropper(); },
                    { fx: fx.toFixed(5), fy: fy.toFixed(5), fw: fw.toFixed(5), fh: fh.toFixed(5) });
            });
        }
        function closeCropper(){
            if (cropModal) { if (cropModal._cleanup) { cropModal._cleanup(); } cropModal.remove(); cropModal = null; }
        }

        // Wire the photos rendered server-side, then mark the primary.
        realTiles().forEach(bindDrag);
        refreshPrimary();
    }

    // ── Video / Panorama single-file uploaders (Features B, C) ──
    var MEDIA_T = {
        uploading: '<?php echo esc_js( __( 'Uploading…', 'ovr-core' ) ); ?>',
        upfail:    '<?php echo esc_js( __( 'Upload failed. Please try again.', 'ovr-core' ) ); ?>'
    };
    function wireSingleMedia(kind){
        var wrap = form.querySelector('.ld-fm-media-up[data-ld-media="' + kind + '"]');
        if (!wrap) { return; }
        var idIn   = wrap.querySelector('input[type="hidden"]');
        var fileIn2= wrap.querySelector('input[type="file"]');
        var prev   = wrap.querySelector('.ld-fm-media-preview');
        var pick   = wrap.querySelector('.ld-fm-media-pick');
        var rem    = wrap.querySelector('.ld-fm-media-remove');
        var status = wrap.querySelector('.ld-fm-media-status');
        if (pick && fileIn2) { pick.addEventListener('click', function(){ fileIn2.click(); }); }
        if (rem) {
            rem.addEventListener('click', function(){
                idIn.value = ''; if (prev) { prev.innerHTML = ''; prev.hidden = true; } rem.hidden = true;
                if (status) { status.textContent = ''; }
            });
        }
        if (fileIn2) {
            fileIn2.addEventListener('change', function(){
                var file = fileIn2.files && fileIn2.files[0]; if (!file) { return; }
                if (status) { status.textContent = MEDIA_T.uploading; }
                var fd = new FormData();
                fd.append('action', 'ovr_upload_listing_media');
                fd.append('nonce', nonce); fd.append('kind', kind === 'video' ? 'video' : 'pano');
                fd.append('file', file);
                fetch(ajaxUrl, { method:'POST', credentials:'same-origin', body:fd })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if (res && res.success && res.data && res.data.id) {
                            idIn.value = res.data.id;
                            if (prev) {
                                prev.innerHTML = (kind === 'video')
                                    ? '<video src="' + res.data.url + '" controls preload="metadata"></video>'
                                    : '<img src="' + res.data.url + '" alt="">';
                                prev.hidden = false;
                            }
                            if (rem) { rem.hidden = false; }
                            if (status) { status.textContent = ''; }
                        } else {
                            if (status) { status.textContent = (res && res.data && res.data.message) ? res.data.message : MEDIA_T.upfail; }
                        }
                    })
                    .catch(function(){ if (status) { status.textContent = MEDIA_T.upfail; } })
                    .then(function(){ fileIn2.value = ''; });
            });
        }
    }
    wireSingleMedia('video');
    wireSingleMedia('pano');

    // ── Documents uploader (Feature D) ──
    (function(){
        var list   = form.querySelector('#ld-fm-docs');
        var addBtn = form.querySelector('#ld-fm-doc-add');
        var fileIn3= form.querySelector('#ld-fm-doc-file');
        var status = form.querySelector('#ld-fm-doc-status');
        if (!list || !addBtn || !fileIn3) { return; }
        var MAX = parseInt(list.getAttribute('data-max'), 10) || 3;
        var T2 = {
            full:   '<?php echo esc_js( __( 'Maximum number of documents reached.', 'ovr-core' ) ); ?>',
            up:     '<?php echo esc_js( __( 'Uploading…', 'ovr-core' ) ); ?>',
            fail:   '<?php echo esc_js( __( 'Upload failed. Please try again.', 'ovr-core' ) ); ?>',
            title:  '<?php echo esc_js( __( 'Document title', 'ovr-core' ) ); ?>',
            order:  '<?php echo esc_js( __( 'Display order', 'ovr-core' ) ); ?>',
            open:   '<?php echo esc_js( __( 'Open', 'ovr-core' ) ); ?>',
            remove: '<?php echo esc_js( __( 'Remove', 'ovr-core' ) ); ?>'
        };
        function count(){ return list.querySelectorAll('.ld-fm-doc-row').length; }
        addBtn.addEventListener('click', function(){
            if (count() >= MAX) { if (status) { status.textContent = T2.full; } return; }
            fileIn3.click();
        });
        list.addEventListener('click', function(e){
            var x = e.target.closest('.ld-fm-doc-remove'); if (!x) { return; }
            var row = x.closest('.ld-fm-doc-row'); if (row) { row.remove(); }
            if (status) { status.textContent = ''; }
        });
        fileIn3.addEventListener('change', function(){
            var file = fileIn3.files && fileIn3.files[0]; if (!file) { return; }
            if (count() >= MAX) { if (status) { status.textContent = T2.full; fileIn3.value=''; return; } }
            if (status) { status.textContent = T2.up; }
            var fd = new FormData();
            fd.append('action', 'ovr_upload_listing_media');
            fd.append('nonce', nonce); fd.append('kind', 'doc'); fd.append('file', file);
            fetch(ajaxUrl, { method:'POST', credentials:'same-origin', body:fd })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res && res.success && res.data && res.data.id) {
                        var id = res.data.id, ext = (res.data.filename || '').split('.').pop().toUpperCase();
                        var ord = count();
                        var row = document.createElement('div');
                        row.className = 'ld-fm-doc-row'; row.setAttribute('data-id', id);
                        row.innerHTML =
                            '<input type="hidden" name="document_ids[]" value="' + id + '">' +
                            '<span class="ld-fm-doc-ext">' + (ext || 'DOC') + '</span>' +
                            '<input type="text" class="ld-fm-input ld-fm-doc-title" name="doc_titles[' + id + ']" value="' + (res.data.filename || '').replace(/"/g,'') + '" placeholder="' + T2.title + '" maxlength="160">' +
                            '<input type="number" class="ld-fm-input ld-fm-doc-order" name="doc_orders[' + id + ']" value="' + ord + '" min="0" step="1" title="' + T2.order + '">' +
                            '<a href="' + res.data.url + '" target="_blank" rel="noopener" class="ld-fm-doc-view" title="' + T2.open + '"><span class="material-symbols-outlined">open_in_new</span></a>' +
                            '<button type="button" class="ld-fm-doc-remove" title="' + T2.remove + '"><span class="material-symbols-outlined">delete</span></button>';
                        list.appendChild(row);
                        if (status) { status.textContent = ''; }
                    } else {
                        if (status) { status.textContent = (res && res.data && res.data.message) ? res.data.message : T2.fail; }
                    }
                })
                .catch(function(){ if (status) { status.textContent = T2.fail; } })
                .then(function(){ fileIn3.value = ''; });
        });
    })();

    show(0);
})();
</script>
