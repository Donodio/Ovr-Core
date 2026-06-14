<?php
/**
 * Frontend listing form handlers.
 *
 * Lets landlords create/edit their own properties from the dashboard — no
 * wp-admin, no shared media library. Photos are uploaded one-by-one via AJAX
 * and attached to the uploader's account; landlords never browse other users'
 * media.
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

use OVR\Core\Pages;
use OVR\Property\Availability;
use OVR\Property\Bump;
use OVR\Property\Geocoder;
use OVR\Property\ImageTools;
use OVR\Property\SeasonalPricing;
use OVR\Subscription\UserSubscription;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ListingForm {

    // Single-value taxonomies set from the listing form. `village_section`
    // maps to the ovr_village taxonomy — a required dropdown that powers the
    // Village Section search facet (distinct from the free-text Village Name).
    private const SINGLE_TAX = [
        'village_section' => 'ovr_village',
        'property_type'   => 'ovr_property_type',
        'rental_type'     => 'ovr_rental_type',
    ];

    public function init(): void {
        add_action( 'admin_post_ovr_save_listing',        [ $this, 'handle_save' ] );
        add_action( 'admin_post_nopriv_ovr_save_listing', [ $this, 'handle_save_nopriv' ] );
        add_action( 'admin_post_ovr_delete_listing',      [ $this, 'handle_delete' ] );
        add_action( 'admin_post_ovr_bump_listing',        [ $this, 'handle_bump' ] );
        add_action( 'admin_post_ovr_toggle_listing_status', [ $this, 'handle_toggle_status' ] );
        add_action( 'wp_ajax_ovr_upload_listing_photo',   [ $this, 'handle_upload' ] );
        add_action( 'wp_ajax_ovr_rotate_listing_photo',   [ $this, 'handle_rotate' ] );
        add_action( 'wp_ajax_ovr_crop_listing_photo',      [ $this, 'handle_crop' ] );
        add_action( 'wp_ajax_ovr_watermark_listing_photo', [ $this, 'handle_watermark' ] );
        add_action( 'wp_ajax_ovr_upload_listing_media',     [ $this, 'handle_media_upload' ] );

        // Defense-in-depth: if a non-admin ever reaches the wp.media grid, only
        // surface their own uploads — never the whole site library.
        add_filter( 'ajax_query_attachments_args', [ $this, 'restrict_attachments' ] );
    }

    public function handle_save_nopriv(): void {
        wp_safe_redirect( Pages::get_page_url( 'ovr_page_login' ) );
        exit;
    }

    /**
     * Delete (trash) a listing the current landlord owns. Triggered from the
     * dashboard's per-listing Delete action (nonce-protected GET + JS confirm).
     */
    public function handle_delete(): void {
        $dash  = Pages::get_page_url( 'ovr_page_dashboard' );
        $props = add_query_arg( 'tab', 'properties', $dash );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_login' ) );
            exit;
        }
        if ( ! UserSubscription::has_listing_access( get_current_user_id() ) ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_subscription_select' ) );
            exit;
        }

        $post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
        $nonce   = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

        if ( ! $post_id || ! wp_verify_nonce( $nonce, 'ovr_delete_listing_' . $post_id ) ) {
            wp_safe_redirect( $props );
            exit;
        }

        $post = get_post( $post_id );
        if ( ! $post || 'ovr_property' !== $post->post_type || (int) $post->post_author !== get_current_user_id() ) {
            wp_safe_redirect( $props );
            exit;
        }

        wp_trash_post( $post_id );
        do_action( 'ovr_listing_deleted', $post_id, get_current_user_id() );

        wp_safe_redirect( add_query_arg( 'ovr_listing', 'deleted', $props ) );
        exit;
    }

    /**
     * Free "Bump Listing" action (Feature F): refresh a listing's position in
     * the default search ordering, subject to the per-landlord daily limit.
     * Nonce-protected GET from the My Properties table.
     */
    public function handle_bump(): void {
        $dash  = Pages::get_page_url( 'ovr_page_dashboard' );
        $props = add_query_arg( 'tab', 'properties', $dash );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_login' ) );
            exit;
        }

        $post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
        $nonce   = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

        if ( ! $post_id || ! wp_verify_nonce( $nonce, 'ovr_bump_listing_' . $post_id ) ) {
            wp_safe_redirect( $props );
            exit;
        }

        $user_id = get_current_user_id();
        $post    = get_post( $post_id );
        $is_admin_user = current_user_can( 'manage_options' );
        if ( ! $post || 'ovr_property' !== $post->post_type || ( (int) $post->post_author !== $user_id && ! $is_admin_user ) ) {
            wp_safe_redirect( $props );
            exit;
        }

        $result = Bump::bump( $post_id, $user_id );

        wp_safe_redirect( add_query_arg( 'ovr_listing', $result['success'] ? 'bumped' : 'bump_limit', $props ) );
        exit;
    }

    /**
     * Activate / deactivate a listing (Feature H). Flips the owner-facing
     * `_ovr_listing_status` between active and inactive; inactive listings are
     * hidden from search + the public single page (PropertyQuery visibility).
     * Nonce-protected GET from the My Properties table.
     */
    public function handle_toggle_status(): void {
        $dash  = Pages::get_page_url( 'ovr_page_dashboard' );
        $props = add_query_arg( 'tab', 'properties', $dash );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_login' ) );
            exit;
        }

        $post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
        $nonce   = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

        if ( ! $post_id || ! wp_verify_nonce( $nonce, 'ovr_toggle_status_' . $post_id ) ) {
            wp_safe_redirect( $props );
            exit;
        }

        $user_id = get_current_user_id();
        $post    = get_post( $post_id );
        $is_admin_user = current_user_can( 'manage_options' );
        if ( ! $post || 'ovr_property' !== $post->post_type || ( (int) $post->post_author !== $user_id && ! $is_admin_user ) ) {
            wp_safe_redirect( $props );
            exit;
        }

        $current = (string) get_post_meta( $post_id, '_ovr_listing_status', true ) ?: 'active';
        $next    = 'inactive' === $current ? 'active' : 'inactive';
        update_post_meta( $post_id, '_ovr_listing_status', $next );

        wp_safe_redirect( add_query_arg( 'ovr_listing', 'active' === $next ? 'activated' : 'deactivated', $props ) );
        exit;
    }

    /**
     * Create or update a property from the dashboard form.
     */
    public function handle_save(): void {
        $dash = Pages::get_page_url( 'ovr_page_dashboard' );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_login' ) );
            exit;
        }
        if ( ! isset( $_POST['ovr_listing_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_listing_nonce'] ) ), 'ovr_listing_action' ) ) {
            wp_safe_redirect( add_query_arg( 'tab', 'properties', $dash ) );
            exit;
        }

        $user_id = get_current_user_id();
        $is_admin_user = current_user_can( 'manage_options' );

        // Gate: creating OR editing a listing requires an active paid
        // subscription (Section 1: no subscription = no landlord access).
        // Admins (who use the same editor inside wp-admin) bypass the gate.
        if ( ! $is_admin_user && ! UserSubscription::has_listing_access( $user_id ) ) {
            wp_safe_redirect( Pages::get_page_url( 'ovr_page_subscription_select' ) );
            exit;
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $editing = false;

        if ( $post_id ) {
            $existing = get_post( $post_id );
            $owns     = $existing && (int) $existing->post_author === $user_id;
            if ( ! $existing || 'ovr_property' !== $existing->post_type || ( ! $owns && ! $is_admin_user ) ) {
                wp_safe_redirect( add_query_arg( 'tab', 'properties', $dash ) );
                exit;
            }
            $editing = true;
        } elseif ( ! $is_admin_user && ! UserSubscription::can_create_listing( $user_id ) ) {
            // Distinguish "no active paid subscription" from "plan limit reached".
            $reason = UserSubscription::has_listing_access( $user_id ) ? 'limit' : 'subscription';
            wp_safe_redirect( add_query_arg( [ 'tab' => 'add-listing', 'ovr_listing' => $reason ], $dash ) );
            exit;
        }

        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        if ( '' === $title ) {
            $title = __( 'Untitled Listing', 'ovr-core' );
        }

        // There is no draft step anymore (Phase 1): every save publishes the
        // listing immediately. Public visibility is governed instead by the
        // owner "Listing Status" + admin status meta (Phase 8B), enforced in
        // PropertyQuery and the single-listing template.
        // Short Description (Phase 9): a ≤200-char summary stored in post_excerpt
        // and shown in search results / featured / cards. Long Description lives
        // in post_content and shows on the full listing page.
        $short_desc = sanitize_text_field( wp_unslash( $_POST['short_description'] ?? '' ) );
        if ( function_exists( 'mb_substr' ) ) {
            $short_desc = mb_substr( $short_desc, 0, 200 );
        } else {
            $short_desc = substr( $short_desc, 0, 200 );
        }

        $postarr = [
            'post_type'    => 'ovr_property',
            'post_title'   => $title,
            'post_content' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'post_excerpt' => $short_desc,
            'post_status'  => 'publish',
            'post_author'  => $user_id,
        ];
        if ( $editing ) {
            $postarr['ID'] = $post_id;
            $post_id = wp_update_post( $postarr, true );
        } else {
            $post_id = wp_insert_post( $postarr, true );
        }

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'add-listing', 'ovr_listing' => 'error' ], $dash ) );
            exit;
        }

        // Scalar meta.
        update_post_meta( $post_id, '_ovr_bedrooms',   (int) ( $_POST['bedrooms'] ?? 0 ) );
        update_post_meta( $post_id, '_ovr_bathrooms',  (float) ( $_POST['bathrooms'] ?? 0 ) );
        update_post_meta( $post_id, '_ovr_beds',       (int) ( $_POST['beds'] ?? 0 ) );
        update_post_meta( $post_id, '_ovr_max_guests', (int) ( $_POST['max_guests'] ?? 0 ) );
        update_post_meta( $post_id, '_ovr_sqft',       (int) ( $_POST['sqft'] ?? 0 ) );
        update_post_meta( $post_id, '_ovr_pets_allowed', empty( $_POST['pets_allowed'] ) ? 0 : 1 );

        // Nightly rate / listing-wide min stay were removed from the editor
        // (no Airbnb-style nightly assumption). Only persist them if some other
        // flow posts them, so we don't clobber legacy data here.
        if ( isset( $_POST['base_price'] ) ) {
            update_post_meta( $post_id, '_ovr_base_price', (float) $_POST['base_price'] );
        }
        if ( isset( $_POST['min_stay'] ) ) {
            update_post_meta( $post_id, '_ovr_min_stay', max( 1, (int) $_POST['min_stay'] ) );
        }

        foreach ( [ 'address', 'state', 'zip' ] as $f ) {
            update_post_meta( $post_id, '_ovr_' . $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ?? '' ) ) );
        }

        // Videos tab: video + 360°/virtual-tour links (shown on the listing page).
        // Availability tab: iCal feed URL (synced via the "Sync now" button).
        foreach ( [ 'video_url', 'panorama_url', 'ical_url' ] as $u ) {
            update_post_meta( $post_id, '_ovr_' . $u, esc_url_raw( wp_unslash( $_POST[ $u ] ?? '' ) ) );
        }

        // Uploaded media: native video (Feature B) + 360 panorama image (Feature
        // C). Stored as attachment IDs; validated to be real attachments.
        foreach ( [ 'video_id' => '_ovr_video_id', 'panorama_id' => '_ovr_panorama_id' ] as $field => $key ) {
            $att = isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
            if ( $att && 'attachment' === get_post_type( $att ) ) {
                update_post_meta( $post_id, $key, $att );
            } else {
                delete_post_meta( $post_id, $key );
            }
        }

        // Documents (Feature D): up to MAX_DOCS PDF/DOCX/XLSX with a title and a
        // display order. Stored as an ordered id CSV plus a meta map of titles.
        $this->save_documents( $post_id );
        // US-only marketplace: country is implied, never collected/displayed.
        update_post_meta( $post_id, '_ovr_country', 'United States' );
        // City defaults to "The Villages" when left blank (editable per listing).
        $city = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
        update_post_meta( $post_id, '_ovr_city', '' !== $city ? $city : 'The Villages' );
        // Village Name = free text (100+ villages, always growing). The Village
        // Section is the taxonomy below (the search facet).
        update_post_meta( $post_id, '_ovr_village_name', sanitize_text_field( wp_unslash( $_POST['village_name'] ?? '' ) ) );

        // Owner-facing listing status (Phase 8B): Active or Inactive. The status
        // control only renders when editing; on create we default to Active.
        if ( $editing && isset( $_POST['listing_status'] ) ) {
            $ostatus = sanitize_key( wp_unslash( $_POST['listing_status'] ) );
            update_post_meta( $post_id, '_ovr_listing_status', in_array( $ostatus, [ 'active', 'inactive' ], true ) ? $ostatus : 'active' );
        } elseif ( ! $editing ) {
            update_post_meta( $post_id, '_ovr_listing_status', 'active' );
        }

        // Admin-only status (Phase 8B): Approved / Hidden / Suspended / Pending
        // Review. Defaults to Approved so listings go live immediately on create
        // (Phase 1). Only admins may change it; owners never see this field.
        if ( $is_admin_user && isset( $_POST['admin_status'] ) ) {
            $astatus = sanitize_key( wp_unslash( $_POST['admin_status'] ) );
            $allowed_admin = [ 'approved', 'hidden', 'suspended', 'pending_review' ];
            update_post_meta( $post_id, '_ovr_admin_status', in_array( $astatus, $allowed_admin, true ) ? $astatus : 'approved' );
        } elseif ( ! $editing ) {
            update_post_meta( $post_id, '_ovr_admin_status', 'approved' );
        }

        // Single-value taxonomies.
        foreach ( self::SINGLE_TAX as $field => $tax ) {
            $tid = absint( $_POST[ $field ] ?? 0 );
            wp_set_object_terms( $post_id, $tid ? [ $tid ] : [], $tax, false );
        }
        // Amenities (multi).
        $amenities = ( isset( $_POST['amenities'] ) && is_array( $_POST['amenities'] ) )
            ? array_map( 'absint', $_POST['amenities'] )
            : [];
        wp_set_object_terms( $post_id, $amenities, 'ovr_amenity', false );

        // Gallery: validated attachment ids, in order; first = cover.
        $raw  = isset( $_POST['gallery_ids'] ) ? (string) wp_unslash( $_POST['gallery_ids'] ) : '';
        $gids = array_values( array_filter(
            array_map( 'absint', explode( ',', $raw ) ),
            static fn( $id ) => $id && 'attachment' === get_post_type( $id )
        ) );
        update_post_meta( $post_id, '_ovr_gallery_ids', implode( ',', $gids ) );
        if ( $gids ) {
            set_post_thumbnail( $post_id, $gids[0] );
        } else {
            delete_post_thumbnail( $post_id );
        }

        // Per-photo captions: keep only captions for photos still in the gallery.
        $caps_in   = ( isset( $_POST['captions'] ) && is_array( $_POST['captions'] ) ) ? wp_unslash( $_POST['captions'] ) : [];
        $captions  = [];
        $listing_t = get_the_title( $post_id );
        $pos       = 0;
        foreach ( $gids as $gid ) {
            $pos++;
            $c = isset( $caps_in[ $gid ] ) ? sanitize_text_field( (string) $caps_in[ $gid ] ) : '';
            if ( '' !== $c ) {
                $captions[ (string) $gid ] = $c;
            }
            // SEO: mirror the caption into WordPress' standard ALT meta so the
            // Media Library, REST API and block editor see it. Never leave ALT
            // empty — fall back to "<Listing> — photo N".
            $alt = '' !== $c
                ? $c
                : trim( $listing_t . ' — ' . sprintf( /* translators: %d: photo number */ __( 'photo %d', 'ovr-core' ), $pos ) );
            update_post_meta( $gid, '_wp_attachment_image_alt', $alt );
        }
        update_post_meta( $post_id, '_ovr_gallery_captions', $captions );

        // Watermark safety net (Phase 3): ensure every photo in the gallery is
        // watermarked — covers existing/imported photos that predate auto-
        // watermarking, or any upload where it didn't take. Idempotent (skips
        // already-watermarked images); uses a fresh filename to bust caches on
        // images that may already be displayed elsewhere.
        foreach ( $gids as $gid ) {
            $this->ensure_watermarked( $gid, true );
        }

        // Additional Information sections (Phase 20): What's Nearby, Policies,
        // Payment Information — multi-line owner content shown on the listing.
        foreach ( [ 'nearby', 'policies', 'payment_info' ] as $rt ) {
            update_post_meta( $post_id, '_ovr_' . $rt, wp_kses_post( wp_unslash( $_POST[ $rt ] ?? '' ) ) );
        }

        // Pricing tab (Phase 4): "Check Description For Pricing" hides the table
        // but preserves the rows; then save the production-shaped rate rows.
        update_post_meta( $post_id, '_ovr_hide_pricing', empty( $_POST['hide_pricing'] ) ? 0 : 1 );
        SeasonalPricing::save_pricing( $post_id, $_POST['pricing'] ?? [] );

        // Availability Calendar tab: replace this listing's manual block-out
        // ranges (iCal-sourced rows are preserved by the helper).
        Availability::save_manual_blocks( $post_id, $_POST['avail'] ?? [] );

        // Auto-generate the map (Phase 2): geocode the address to coordinates
        // when the address changed or none are stored yet. Landlords never pick
        // a point by hand. Runs inline; failures are silent (the backfill cron
        // and the next save will retry).
        Geocoder::geocode_listing( $post_id );

        do_action( 'ovr_listing_saved', $post_id, $user_id, $editing );

        // When the editor is embedded elsewhere (e.g. wp-admin), it passes a
        // same-site return URL to come back to instead of the dashboard.
        $return_url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : '';
        if ( '' !== $return_url ) {
            wp_safe_redirect( add_query_arg( 'ovr_listing', $editing ? 'updated' : 'created', $return_url ) );
            exit;
        }

        wp_safe_redirect( add_query_arg(
            [ 'tab' => 'properties', 'ovr_listing' => $editing ? 'updated' : 'created' ],
            $dash
        ) );
        exit;
    }

    /**
     * Persist the listing's documents (Feature D) from the submitted form.
     * Reads document_ids[] (ordered), doc_titles[id], doc_orders[id]; caps at
     * MAX_DOCS; keeps only real attachments. Stores the ordered id CSV in
     * `_ovr_document_ids` and a title/order map in `_ovr_document_meta`.
     */
    private function save_documents( int $post_id ): void {
        $ids_raw = isset( $_POST['document_ids'] ) ? (array) wp_unslash( $_POST['document_ids'] ) : [];
        $titles  = isset( $_POST['doc_titles'] ) ? (array) wp_unslash( $_POST['doc_titles'] ) : [];
        $orders  = isset( $_POST['doc_orders'] ) ? (array) wp_unslash( $_POST['doc_orders'] ) : [];

        // Clean to real attachment IDs.
        $ids = array_values( array_filter(
            array_map( 'absint', $ids_raw ),
            static fn( $id ) => $id && 'attachment' === get_post_type( $id )
        ) );

        // Sort by submitted display order (stable), then cap.
        usort( $ids, static function ( $a, $b ) use ( $orders ) {
            $oa = (int) ( $orders[ $a ] ?? 0 );
            $ob = (int) ( $orders[ $b ] ?? 0 );
            return $oa <=> $ob;
        } );
        $ids = array_slice( $ids, 0, self::MAX_DOCS );

        $meta = [];
        foreach ( $ids as $i => $id ) {
            $title = isset( $titles[ $id ] ) ? sanitize_text_field( (string) $titles[ $id ] ) : '';
            $meta[ (string) $id ] = [
                'title' => $title,
                'order' => isset( $orders[ $id ] ) ? (int) $orders[ $id ] : $i,
            ];
            // Mirror the title onto the attachment so the file is recognisable.
            if ( '' !== $title ) {
                wp_update_post( [ 'ID' => $id, 'post_title' => $title ] );
            }
        }

        update_post_meta( $post_id, '_ovr_document_ids', implode( ',', $ids ) );
        update_post_meta( $post_id, '_ovr_document_meta', $meta );
    }

    /**
     * Ordered documents for display (Feature D): each as
     * [id, title, url, filename, ext]. Shared by the editor + the single page.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_documents( int $post_id ): array {
        $csv  = (string) get_post_meta( $post_id, '_ovr_document_ids', true );
        $ids  = array_values( array_filter( array_map( 'absint', explode( ',', $csv ) ) ) );
        $meta = (array) get_post_meta( $post_id, '_ovr_document_meta', true );

        $out = [];
        foreach ( $ids as $id ) {
            if ( 'attachment' !== get_post_type( $id ) ) {
                continue;
            }
            $file  = (string) get_attached_file( $id );
            $title = (string) ( $meta[ (string) $id ]['title'] ?? '' );
            $out[] = [
                'id'       => $id,
                'title'    => '' !== $title ? $title : get_the_title( $id ),
                'url'      => (string) wp_get_attachment_url( $id ),
                'filename' => basename( $file ),
                'ext'      => strtoupper( (string) pathinfo( $file, PATHINFO_EXTENSION ) ),
            ];
        }
        return $out;
    }

    /**
     * AJAX: accept a single image upload, attach it to the current user, and
     * return its id + thumbnail URL. The landlord only ever sees what they just
     * uploaded — no media-library browsing.
     */
    public function handle_upload(): void {
        if ( ! check_ajax_referer( 'ovr_listing_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }
        if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to upload.', 'ovr-core' ) ], 403 );
        }
        if ( ! UserSubscription::has_listing_access( get_current_user_id() ) ) {
            wp_send_json_error( [ 'message' => __( 'An active subscription is required to manage listings.', 'ovr-core' ) ], 403 );
        }
        if ( empty( $_FILES['file'] ) || empty( $_FILES['file']['tmp_name'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No file received.', 'ovr-core' ) ], 400 );
        }

        // Only images, capped at 10MB.
        $allowed = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
        $mime    = function_exists( 'mime_content_type' )
            ? (string) mime_content_type( $_FILES['file']['tmp_name'] )
            : (string) ( wp_check_filetype( (string) ( $_FILES['file']['name'] ?? '' ) )['type'] ?? '' );
        if ( ! in_array( $mime, $allowed, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Please upload a JPG, PNG, WEBP, or GIF image.', 'ovr-core' ) ], 400 );
        }
        if ( (int) ( $_FILES['file']['size'] ?? 0 ) > 10 * 1024 * 1024 ) {
            wp_send_json_error( [ 'message' => __( 'That image is larger than 10MB.', 'ovr-core' ) ], 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $att_id = media_handle_upload( 'file', 0 );
        if ( is_wp_error( $att_id ) ) {
            wp_send_json_error( [ 'message' => $att_id->get_error_message() ], 400 );
        }

        // Bake any EXIF orientation into the pixels so the photo can never show
        // up sideways on the front end, then automatically watermark it
        // (Phase 3: every uploaded image is watermarked — no user setting).
        $file = get_attached_file( (int) $att_id );
        if ( $file && ImageTools::normalize_orientation( $file ) ) {
            $this->regenerate( (int) $att_id, $file );
        }
        $this->ensure_watermarked( (int) $att_id, false );

        wp_send_json_success( [
            'id'  => (int) $att_id,
            'url' => wp_get_attachment_image_url( (int) $att_id, 'medium' ) ?: wp_get_attachment_url( (int) $att_id ),
        ] );
    }

    /** Max documents per listing (Feature D). */
    public const MAX_DOCS = 3;

    /**
     * AJAX: accept a single video / 360-panorama / document upload for the
     * current user (Features B, C, D). Validates MIME + size per `kind` and
     * returns the new attachment id + URL (+ filename for documents). Uploaded
     * files flow through the normal media pipeline, so B2 offloading (Feature E)
     * applies automatically when enabled.
     */
    public function handle_media_upload(): void {
        if ( ! check_ajax_referer( 'ovr_listing_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }
        if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to upload.', 'ovr-core' ) ], 403 );
        }
        if ( ! UserSubscription::has_listing_access( get_current_user_id() ) ) {
            wp_send_json_error( [ 'message' => __( 'An active subscription is required to manage listings.', 'ovr-core' ) ], 403 );
        }
        if ( empty( $_FILES['file'] ) || empty( $_FILES['file']['tmp_name'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No file received.', 'ovr-core' ) ], 400 );
        }

        $kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
        $rules = [
            'video' => [
                'mimes' => [ 'video/mp4', 'video/quicktime', 'video/webm' ],
                'max'   => 256 * 1024 * 1024,
                'error' => __( 'Please upload an MP4, MOV, or WebM video.', 'ovr-core' ),
            ],
            'pano'  => [
                'mimes' => [ 'image/jpeg', 'image/png', 'image/webp' ],
                'max'   => 30 * 1024 * 1024,
                'error' => __( 'Please upload a JPG, PNG, or WEBP panorama image.', 'ovr-core' ),
            ],
            'doc'   => [
                'mimes' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
                'max'   => 25 * 1024 * 1024,
                'error' => __( 'Please upload a PDF, DOCX, or XLSX document.', 'ovr-core' ),
            ],
        ];
        if ( ! isset( $rules[ $kind ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Unsupported upload type.', 'ovr-core' ) ], 400 );
        }
        $rule = $rules[ $kind ];

        $mime = function_exists( 'mime_content_type' )
            ? (string) mime_content_type( $_FILES['file']['tmp_name'] )
            : (string) ( wp_check_filetype( (string) ( $_FILES['file']['name'] ?? '' ) )['type'] ?? '' );
        if ( ! in_array( $mime, $rule['mimes'], true ) ) {
            wp_send_json_error( [ 'message' => $rule['error'] ], 400 );
        }
        if ( (int) ( $_FILES['file']['size'] ?? 0 ) > $rule['max'] ) {
            wp_send_json_error( [ 'message' => __( 'That file is larger than the allowed size.', 'ovr-core' ) ], 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $att_id = media_handle_upload( 'file', 0 );
        if ( is_wp_error( $att_id ) ) {
            wp_send_json_error( [ 'message' => $att_id->get_error_message() ], 400 );
        }

        wp_send_json_success( [
            'id'       => (int) $att_id,
            'url'      => wp_get_attachment_url( (int) $att_id ),
            'filename' => basename( (string) get_attached_file( (int) $att_id ) ),
        ] );
    }

    /**
     * AJAX: rotate one of the landlord's own photos 90° clockwise. Rewrites the
     * source file and regenerates its thumbnails so the rotation is permanent
     * everywhere the image is shown.
     */
    public function handle_rotate(): void {
        if ( ! check_ajax_referer( 'ovr_listing_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }
        if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to edit photos.', 'ovr-core' ) ], 403 );
        }

        $att_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $file   = $this->editable_photo_file( $att_id );
        if ( ! $file ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to edit this photo.', 'ovr-core' ) ], 403 );
        }

        $dir = ( isset( $_POST['dir'] ) && 'left' === sanitize_key( wp_unslash( $_POST['dir'] ) ) ) ? 'left' : 'right';
        $ok  = 'left' === $dir ? ImageTools::rotate_left( $file ) : ImageTools::rotate( $file );
        if ( ! $ok ) {
            wp_send_json_error( [ 'message' => __( 'Could not rotate the photo on the server.', 'ovr-core' ) ], 500 );
        }

        $this->regenerate_fresh( $att_id, $file );

        wp_send_json_success( [
            'id'  => $att_id,
            'url' => wp_get_attachment_image_url( $att_id, 'medium' ) ?: wp_get_attachment_url( $att_id ),
        ] );
    }

    /**
     * AJAX: crop one of the landlord's own photos to a sub-rectangle. Crop
     * coordinates arrive in the image's natural pixels.
     */
    public function handle_crop(): void {
        if ( ! check_ajax_referer( 'ovr_listing_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }
        if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to edit photos.', 'ovr-core' ) ], 403 );
        }

        $att_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $file   = $this->editable_photo_file( $att_id );
        if ( ! $file ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to edit this photo.', 'ovr-core' ) ], 403 );
        }

        // Coordinates arrive as fractions (0..1) of the image so the client can
        // measure against any derivative; we convert against the real file size.
        $size = getimagesize( $file );
        if ( false === $size ) {
            wp_send_json_error( [ 'message' => __( 'Could not read the photo.', 'ovr-core' ) ], 500 );
        }
        $iw = (int) $size[0];
        $ih = (int) $size[1];

        $fx = isset( $_POST['fx'] ) ? max( 0.0, min( 1.0, (float) $_POST['fx'] ) ) : 0.0;
        $fy = isset( $_POST['fy'] ) ? max( 0.0, min( 1.0, (float) $_POST['fy'] ) ) : 0.0;
        $fw = isset( $_POST['fw'] ) ? max( 0.0, min( 1.0, (float) $_POST['fw'] ) ) : 0.0;
        $fh = isset( $_POST['fh'] ) ? max( 0.0, min( 1.0, (float) $_POST['fh'] ) ) : 0.0;

        $x = (int) round( $fx * $iw );
        $y = (int) round( $fy * $ih );
        $w = (int) round( $fw * $iw );
        $h = (int) round( $fh * $ih );

        if ( $w < 10 || $h < 10 ) {
            wp_send_json_error( [ 'message' => __( 'Crop area is too small.', 'ovr-core' ) ], 400 );
        }

        if ( ! ImageTools::crop( $file, $x, $y, $w, $h ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not crop the photo on the server.', 'ovr-core' ) ], 500 );
        }

        $this->regenerate_fresh( $att_id, $file );

        wp_send_json_success( [
            'id'  => $att_id,
            'url' => wp_get_attachment_image_url( $att_id, 'medium' ) ?: wp_get_attachment_url( $att_id ),
        ] );
    }

    /**
     * AJAX: stamp a watermark onto one of the landlord's own photos. Rewrites
     * the source file + regenerates thumbnails so the mark is permanent.
     */
    public function handle_watermark(): void {
        if ( ! check_ajax_referer( 'ovr_listing_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }
        if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to edit photos.', 'ovr-core' ) ], 403 );
        }

        $att_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $file   = $this->editable_photo_file( $att_id );
        if ( ! $file ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to edit this photo.', 'ovr-core' ) ], 403 );
        }

        // Watermark text: the configured setting (or site name), filterable.
        $text = self::watermark_text();

        if ( ! ImageTools::watermark( $file, $text ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not watermark the photo on the server.', 'ovr-core' ) ], 500 );
        }

        update_post_meta( $att_id, '_ovr_watermarked', 1 );
        $this->regenerate_fresh( $att_id, $file );

        wp_send_json_success( [
            'id'  => $att_id,
            'url' => wp_get_attachment_image_url( $att_id, 'medium' ) ?: wp_get_attachment_url( $att_id ),
        ] );
    }

    /**
     * Resolve an attachment's file path if the current user is allowed to edit
     * the photo, else ''. Allowed when: admin · they uploaded it · or the photo
     * belongs to a listing they own (passed as post_id). The last case matters
     * because demo/imported photos are often authored by the admin, not the
     * landlord who now owns and edits the listing.
     */
    private function editable_photo_file( int $att_id ): string {
        $att = $att_id ? get_post( $att_id ) : null;
        if ( ! $att || 'attachment' !== $att->post_type ) {
            return '';
        }
        if ( ! $this->user_can_edit_photo( $att_id, (int) $att->post_author ) ) {
            return '';
        }
        $file = get_attached_file( $att_id );
        return ( $file && file_exists( $file ) ) ? $file : '';
    }

    /**
     * Whether the current user may edit a given photo.
     */
    private function user_can_edit_photo( int $att_id, int $att_author ): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $uid = get_current_user_id();
        if ( $att_author === $uid ) {
            return true;
        }
        // The photo is part of a listing this user owns?
        $listing_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( $listing_id ) {
            $listing = get_post( $listing_id );
            if ( $listing && 'ovr_property' === $listing->post_type && (int) $listing->post_author === $uid ) {
                $gids = array_map( 'absint', array_filter( explode( ',', (string) get_post_meta( $listing_id, '_ovr_gallery_ids', true ) ) ) );
                if ( in_array( $att_id, $gids, true ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * The watermark caption: the configured "Watermark Text" setting if set,
     * otherwise the site name. Filterable via `ovr_watermark_text`.
     */
    public static function watermark_text(): string {
        $settings = (array) get_option( 'ovr_settings', [] );
        $default  = ! empty( $settings['watermark_text'] ) ? (string) $settings['watermark_text'] : (string) get_bloginfo( 'name' );
        return (string) apply_filters( 'ovr_watermark_text', $default );
    }

    /**
     * Watermark an attachment unless it has already been watermarked (Phase 3).
     * Idempotent via the `_ovr_watermarked` flag, so it is safe to call on every
     * upload and on every save. `$fresh` renames the file (busting browser/page
     * caches) — use it for photos already displayed somewhere (existing
     * galleries); a brand-new upload can keep its name.
     */
    private function ensure_watermarked( int $att_id, bool $fresh = true ): void {
        if ( get_post_meta( $att_id, '_ovr_watermarked', true ) ) {
            return; // already watermarked.
        }
        $att = get_post( $att_id );
        if ( ! $att || 'attachment' !== $att->post_type ) {
            return;
        }
        $file = get_attached_file( $att_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return;
        }
        $text = self::watermark_text();
        if ( '' === trim( $text ) ) {
            return;
        }
        if ( ImageTools::watermark( $file, $text ) ) {
            update_post_meta( $att_id, '_ovr_watermarked', 1 );
            if ( $fresh ) {
                $this->regenerate_fresh( $att_id, $file );
            } else {
                $this->regenerate( $att_id, $file );
            }
        }
    }

    /**
     * Rebuild an attachment's sized derivatives after its source file changed.
     */
    private function regenerate( int $att_id, string $file ): void {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata( $att_id, $file );
        if ( $meta ) {
            wp_update_attachment_metadata( $att_id, $meta );
        }
    }

    /**
     * After an in-place pixel edit (rotate/watermark), rewrite the photo under a
     * NEW filename and rebuild its sizes. Editing in place keeps the same URLs,
     * so browsers and page caches keep serving the pre-edit image and the change
     * never shows on the front end. A fresh filename changes every URL, so the
     * edit appears everywhere immediately. Old derivatives are removed first.
     *
     * @return string The new absolute file path (or the original on failure).
     */
    private function regenerate_fresh( int $att_id, string $file ): string {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $dir = trailingslashit( dirname( $file ) );

        // Delete the stale sized derivatives (same-named files browsers cache).
        $old = wp_get_attachment_metadata( $att_id );
        if ( is_array( $old ) && ! empty( $old['sizes'] ) ) {
            foreach ( $old['sizes'] as $size ) {
                if ( ! empty( $size['file'] ) && is_file( $dir . $size['file'] ) ) {
                    @unlink( $dir . $size['file'] );
                }
            }
        }

        // Move the edited original to a unique name so its URL changes too.
        $info    = pathinfo( $file );
        $ext     = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
        $newname = $info['filename'] . '-r' . time() . wp_rand( 100, 999 ) . $ext;
        $newpath = $dir . wp_unique_filename( $dir, $newname );

        if ( @rename( $file, $newpath ) ) {
            update_attached_file( $att_id, $newpath );
            $file = $newpath;
        }

        $meta = wp_generate_attachment_metadata( $att_id, $file );
        if ( $meta ) {
            wp_update_attachment_metadata( $att_id, $meta );
        }
        return $file;
    }

    /**
     * Limit the media-grid query to the current user's own uploads for
     * non-admins (admins/editors keep full access).
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function restrict_attachments( array $args ): array {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_others_posts' ) ) {
            $args['author'] = get_current_user_id();
        }
        return $args;
    }
}
