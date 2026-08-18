<?php
/**
 * AJAX Handler.
 *
 * @package OVR\Ajax
 * @since   1.0.0
 */

namespace OVR\Ajax;

use OVR\Property\PropertyQuery;
use OVR\Property\PropertyCard;
use OVR\Property\IcalSync;
use OVR\Property\Geocoder;
use OVR\Search\SearchHandler;
use OVR\Core\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AjaxHandler {

    public function init(): void {
        // Public AJAX (no login required).
        add_action( 'wp_ajax_ovr_search_properties', [ $this, 'search_properties' ] );
        add_action( 'wp_ajax_nopriv_ovr_search_properties', [ $this, 'search_properties' ] );

        // Village chip refresh — re-renders the results region without reload.
        add_action( 'wp_ajax_ovr_search_chips', [ $this, 'search_chips' ] );
        add_action( 'wp_ajax_nopriv_ovr_search_chips', [ $this, 'search_chips' ] );

        add_action( 'wp_ajax_ovr_load_more', [ $this, 'load_more' ] );
        add_action( 'wp_ajax_nopriv_ovr_load_more', [ $this, 'load_more' ] );

        // Inquiries: AJAX path + non-JS admin-post fallback.
        add_action( 'wp_ajax_ovr_submit_inquiry', [ $this, 'submit_inquiry' ] );
        add_action( 'wp_ajax_nopriv_ovr_submit_inquiry', [ $this, 'submit_inquiry' ] );
        add_action( 'admin_post_ovr_submit_inquiry', [ $this, 'submit_inquiry_post' ] );
        add_action( 'wp_ajax_nopriv_ovr_submit_inquiry', [ $this, 'submit_inquiry_post' ] );

        // Reviews.
        add_action( 'wp_ajax_ovr_submit_review', [ $this, 'submit_review' ] );
        add_action( 'wp_ajax_nopriv_ovr_submit_review', [ $this, 'submit_review' ] );

        add_action( 'wp_ajax_ovr_apply_promo', [ $this, 'apply_promo' ] );

        // Admin: manual iCal sync trigger.
        add_action( 'wp_ajax_ovr_ical_sync', [ $this, 'ical_sync' ] );

        // Frontend: dashboard profile update.
        add_action( 'admin_post_ovr_update_profile',  [ $this, 'update_profile' ] );
        add_action( 'admin_post_ovr_change_password', [ $this, 'change_password' ] );

        // Map interaction analytics (M3 F10) — beacon from ovr-search.js.
        add_action( 'wp_ajax_ovr_map_track', [ $this, 'map_track' ] );
        add_action( 'wp_ajax_nopriv_ovr_map_track', [ $this, 'map_track' ] );

        // Frontend: direct profile-photo upload (simple, in-house — no Gravatar).
        add_action( 'wp_ajax_ovr_upload_avatar', [ $this, 'upload_avatar' ] );
        // Admin: remove a user's profile photo (own or managed), restoring the
        // default placeholder. Admins may manage any user; a normal user may
        // only remove their own.
        add_action( 'wp_ajax_ovr_remove_avatar', [ $this, 'remove_avatar' ] );
        // Serve the uploaded photo wherever WordPress asks for the user's avatar.
        add_filter( 'get_avatar_data', [ $this, 'filter_avatar_data' ], 10, 2 );

        // Frontend: auto-geocode address fields (landlord editor).
        add_action( 'wp_ajax_ovr_geocode_address', [ $this, 'geocode_address' ] );

        // Frontend: village search autocomplete (Section 7). Returns lightweight
        // suggestions as you type, never the full village list.
        add_action( 'wp_ajax_ovr_suggest_villages', [ $this, 'suggest_villages' ] );
        add_action( 'wp_ajax_nopriv_ovr_suggest_villages', [ $this, 'suggest_villages' ] );
    }

    /**
     * Record a map interaction event (M3 F10). Increments a per-event counter
     * plus a per-day total in the `ovr_map_stats` option. Whitelisted events
     * only; nonce-guarded but tolerant (analytics is non-critical).
     */
    public function map_track(): void {
        if ( ! check_ajax_referer( 'ovr_public_nonce', 'nonce', false ) ) {
            wp_send_json_error( [], 403 );
        }
        $event   = sanitize_key( wp_unslash( $_POST['event'] ?? '' ) );
        $allowed = [ 'map_view', 'marker_click', 'popup_view', 'card_focus' ];
        if ( ! in_array( $event, $allowed, true ) ) {
            wp_send_json_error( [], 400 );
        }

        $stats = get_option( 'ovr_map_stats', [] );
        if ( ! is_array( $stats ) ) {
            $stats = [];
        }
        $stats['total']          = (int) ( $stats['total'] ?? 0 ) + 1;
        $stats[ $event ]         = (int) ( $stats[ $event ] ?? 0 ) + 1;
        $day                     = current_time( 'Y-m-d' );
        $stats['by_day']         = isset( $stats['by_day'] ) && is_array( $stats['by_day'] ) ? $stats['by_day'] : [];
        $stats['by_day'][ $day ] = (int) ( $stats['by_day'][ $day ] ?? 0 ) + 1;
        // Keep only the last 60 days of the daily series so the option stays small.
        if ( count( $stats['by_day'] ) > 60 ) {
            ksort( $stats['by_day'] );
            $stats['by_day'] = array_slice( $stats['by_day'], -60, null, true );
        }

        update_option( 'ovr_map_stats', $stats, false );
        wp_send_json_success();
    }

    /**
     * Resolve a custom uploaded avatar URL for a user, replacing Gravatar.
     *
     * @param array $args        Avatar args (includes 'url').
     * @param mixed $id_or_email User ID, WP_User, WP_Post, WP_Comment, or email.
     * @return array
     */
    public function filter_avatar_data( array $args, $id_or_email ): array {
        $user_id = 0;
        if ( is_numeric( $id_or_email ) ) {
            $user_id = (int) $id_or_email;
        } elseif ( $id_or_email instanceof \WP_User ) {
            $user_id = (int) $id_or_email->ID;
        } elseif ( $id_or_email instanceof \WP_Post ) {
            $user_id = (int) $id_or_email->post_author;
        } elseif ( $id_or_email instanceof \WP_Comment ) {
            $user_id = (int) $id_or_email->user_id;
        } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
            $u = get_user_by( 'email', $id_or_email );
            if ( $u ) {
                $user_id = (int) $u->ID;
            }
        }

        if ( $user_id ) {
            $att = (int) get_user_meta( $user_id, 'ovr_avatar_id', true );
            if ( $att ) {
                $url = wp_get_attachment_image_url( $att, 'thumbnail' );
                if ( $url ) {
                    $args['url']          = $url;
                    $args['found_avatar'] = true;
                    return $args;
                }
            }
        }

        // No locally-uploaded photo → serve a locally-generated initials avatar.
        // This deliberately replaces the Gravatar fallback so the site never
        // calls an external photo provider (no third-party, no network request).
        $args['url']          = self::local_default_avatar( $user_id, (int) ( $args['size'] ?? 96 ) );
        $args['found_avatar'] = true;

        return $args;
    }

    /**
     * Build an initials avatar so we never fall back to Gravatar. Fully local —
     * no external request is ever made.
     *
     * The SVG is written to the uploads directory and served by URL rather than
     * inlined as a data: URI. `data:` is not in wp_allowed_protocols(), so
     * esc_url() — which core get_avatar() and our own templates both run the
     * avatar through — silently reduces a data URI to an empty string and emits
     * `src=""`. A normal URL survives escaping, caches, and scales (the SVG is
     * vector, so one file serves every requested size).
     */
    private static function local_default_avatar( int $user_id, int $size = 96 ): string {
        $name = '';
        if ( $user_id ) {
            $u = get_userdata( $user_id );
            if ( $u ) {
                $name = trim( (string) $u->display_name );
            }
        }

        $initial = '?';
        if ( '' !== $name ) {
            $initial = function_exists( 'mb_substr' )
                ? mb_strtoupper( mb_substr( $name, 0, 1 ) )
                : strtoupper( substr( $name, 0, 1 ) );
        }

        // Deterministic brand-aligned background colour per user.
        $palette = [ '#006666', '#00714e', '#1f4e79', '#7a4f9e', '#b4530a', '#0b6e75' ];
        $bg      = $palette[ $user_id % count( $palette ) ];

        // Fixed 96-unit canvas + viewBox: the browser scales it to whatever
        // width/height the <img> asks for, so one file covers all sizes.
        $box = 96;
        $fs  = (int) round( $box * 0.46 );

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $box . '" height="' . $box . '" viewBox="0 0 ' . $box . ' ' . $box . '">'
            . '<rect width="' . $box . '" height="' . $box . '" fill="' . $bg . '"/>'
            . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Inter,Segoe UI,Arial,sans-serif" font-weight="600" font-size="' . $fs . '" fill="#ffffff">'
            . htmlspecialchars( $initial, ENT_QUOTES )
            . '</text></svg>';

        $uploads = wp_get_upload_dir();
        if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
            return self::avatar_placeholder_url();
        }

        // Hash the rendered content so a display-name or palette change yields a
        // new filename instead of serving a stale cached avatar.
        $dir      = trailingslashit( $uploads['basedir'] ) . 'ovr-avatars';
        $file     = $user_id . '-' . substr( md5( $svg ), 0, 8 ) . '.svg';
        $abs_path = $dir . '/' . $file;

        if ( ! file_exists( $abs_path ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                return self::avatar_placeholder_url();
            }
            // Write via a temp file + rename so a concurrent request never reads
            // a half-written SVG.
            $tmp = $abs_path . '.' . wp_generate_password( 6, false ) . '.tmp';
            if ( false === file_put_contents( $tmp, $svg ) || ! @rename( $tmp, $abs_path ) ) {
                @unlink( $tmp );
                return self::avatar_placeholder_url();
            }
        }

        return trailingslashit( $uploads['baseurl'] ) . 'ovr-avatars/' . $file;
    }

    /**
     * Last-resort avatar when the uploads directory is not writable. Ships with
     * the plugin, so it is always present and always a real (escapable) URL.
     */
    private static function avatar_placeholder_url(): string {
        return OVR_PLUGIN_URL . 'assets/images/avatar-default.svg';
    }

    /**
     * Handle a direct profile-photo upload from the dashboard.
     */
    public function upload_avatar(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Please sign in.', 'ovr-core' ) ], 403 );
        }
        if ( ! check_ajax_referer( 'ovr_avatar_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }
        if ( empty( $_FILES['avatar']['name'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No file received.', 'ovr-core' ) ], 400 );
        }

        // Validate: image only, max 5MB.
        $allowed = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
        $type    = (string) ( wp_check_filetype( (string) $_FILES['avatar']['name'] )['type'] ?? '' );
        if ( ! in_array( $type, $allowed, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Please upload a JPG, PNG, WebP, or GIF image.', 'ovr-core' ) ], 400 );
        }
        if ( (int) ( $_FILES['avatar']['size'] ?? 0 ) > 5 * 1024 * 1024 ) {
            wp_send_json_error( [ 'message' => __( 'Image must be 5MB or smaller.', 'ovr-core' ) ], 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $att_id = media_handle_upload( 'avatar', 0 );
        if ( is_wp_error( $att_id ) ) {
            wp_send_json_error( [ 'message' => $att_id->get_error_message() ], 500 );
        }

        $user_id = get_current_user_id();

        // Admin may manage another user's avatar (target user via POST).
        $target = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
        if ( $target && $target !== $user_id ) {
            if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'ovr_manage_users' ) ) {
                wp_send_json_error( [ 'message' => __( 'You are not allowed to change this user\'s photo.', 'ovr-core' ) ], 403 );
            }
            $user_id = $target;
        }

        // Clean up a previously uploaded avatar to avoid orphaned media.
        $previous = (int) get_user_meta( $user_id, 'ovr_avatar_id', true );
        if ( $previous && $previous !== (int) $att_id ) {
            wp_delete_attachment( $previous, true );
        }

        update_user_meta( $user_id, 'ovr_avatar_id', (int) $att_id );

        wp_send_json_success( [
            'url'     => wp_get_attachment_image_url( $att_id, 'thumbnail' ),
            'message' => __( 'Profile photo updated.', 'ovr-core' ),
        ] );
    }

    /**
     * Admin (or the account owner) removes a user's profile photo, restoring the
     * generated placeholder. Only admins may remove another user's photo.
     */
    public function remove_avatar(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Please sign in.', 'ovr-core' ) ], 403 );
        }
        if ( ! check_ajax_referer( 'ovr_avatar_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }

        $user_id = get_current_user_id();
        $target  = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
        if ( $target && $target !== $user_id ) {
            if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'ovr_manage_users' ) ) {
                wp_send_json_error( [ 'message' => __( 'You are not allowed to change this user\'s photo.', 'ovr-core' ) ], 403 );
            }
            $user_id = $target;
        }

        $previous = (int) get_user_meta( $user_id, 'ovr_avatar_id', true );
        if ( $previous ) {
            wp_delete_attachment( $previous, true );
        }
        delete_user_meta( $user_id, 'ovr_avatar_id' );

        wp_send_json_success( [
            'message' => __( 'Profile photo removed.', 'ovr-core' ),
            'url'     => self::local_default_avatar( $user_id, 96 ),
        ] );
    }

    /**
     * Change password from dashboard.
     */
    public function change_password(): void {
        $referer = wp_get_referer() ?: home_url( '/' );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( $referer );
            exit;
        }

        if ( ! isset( $_POST['ovr_password_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_password_nonce'] ) ), 'ovr_password_action' ) ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'nonce_failed' ], $referer ) );
            exit;
        }

        $user    = wp_get_current_user();
        $current = (string) wp_unslash( $_POST['current_password']  ?? '' );
        $new     = (string) wp_unslash( $_POST['new_password']      ?? '' );
        $confirm = (string) wp_unslash( $_POST['confirm_password']  ?? '' );

        if ( strlen( $new ) < 8 ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'weak' ], $referer ) );
            exit;
        }
        if ( $new !== $confirm ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'mismatch' ], $referer ) );
            exit;
        }
        if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
            wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'wrong_current' ], $referer ) );
            exit;
        }

        wp_set_password( $new, $user->ID );

        // wp_set_password destroys the session — re-auth so the user stays logged in.
        wp_set_auth_cookie( $user->ID, true );
        wp_set_current_user( $user->ID );

        wp_safe_redirect( add_query_arg( [ 'tab' => 'password', 'pw' => 'success' ], $referer ) );
        exit;
    }

    /**
     * Profile update from the dashboard Profile tab (non-AJAX).
     */
    public function update_profile(): void {
        $referer = wp_get_referer() ?: home_url( '/' );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( $referer );
            exit;
        }
        if ( ! isset( $_POST['ovr_profile_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_profile_nonce'] ) ), 'ovr_profile_action' ) ) {
            wp_safe_redirect( $referer );
            exit;
        }

        $user_id = get_current_user_id();
        $email   = sanitize_email(      wp_unslash( $_POST['email']   ?? '' ) );
        $phone   = sanitize_text_field( wp_unslash( $_POST['phone']   ?? '' ) );
        $address = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );
        $bio     = sanitize_textarea_field( wp_unslash( $_POST['bio'] ?? '' ) );

        $update = [ 'ID' => $user_id ];

        // The profile form uses a single "Full Name" field; split it into
        // first/last for compatibility and keep display_name in sync. Fall back
        // to the legacy first_name/last_name fields if a full name wasn't sent.
        $full = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
        if ( '' !== $full ) {
            $parts                 = preg_split( '/\s+/', $full, 2 );
            $update['first_name']  = $parts[0] ?? '';
            $update['last_name']   = $parts[1] ?? '';
            $update['display_name']= $full;
        } else {
            $first = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
            $last  = sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) );
            if ( $first ) $update['first_name'] = $first;
            if ( $last )  $update['last_name']  = $last;
        }

        if ( $email && is_email( $email ) ) $update['user_email'] = $email;

        // WordPress stores the bio in the user's `description` field.
        $update['description'] = $bio;

        wp_update_user( $update );

        if ( $phone ) {
            update_user_meta( $user_id, 'ovr_phone', $phone );
        } else {
            delete_user_meta( $user_id, 'ovr_phone' );
        }

        if ( '' !== $address ) {
            update_user_meta( $user_id, 'ovr_address', $address );
        } else {
            delete_user_meta( $user_id, 'ovr_address' );
        }

        wp_safe_redirect( add_query_arg( [ 'tab' => 'profile', 'profile_saved' => '1' ], $referer ) );
        exit;
    }

    /**
     * Manual iCal sync trigger from the property edit screen.
     */
    public function ical_sync(): void {
        // Accept the admin meta-box nonce, the public nonce, or the landlord
        // listing-editor nonce (the frontend editor now offers iCal sync too).
        if ( ! check_ajax_referer( 'ovr_admin_nonce', 'nonce', false ) &&
             ! check_ajax_referer( 'ovr_public_nonce', 'nonce', false ) &&
             ! check_ajax_referer( 'ovr_listing_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => __( 'Please save the listing first, then sync.', 'ovr-core' ) ], 400 );
        }
        // Allow the listing's owner (landlord) as well as users with edit_post.
        $post = get_post( $post_id );
        $owns = $post && (int) $post->post_author === get_current_user_id();
        if ( ! current_user_can( 'edit_post', $post_id ) && ! $owns ) {
            wp_send_json_error( [ 'message' => __( 'You cannot edit this property.', 'ovr-core' ) ], 403 );
        }

        // Persist the URL the user just typed so "paste → Sync now" works without
        // a separate save (the field may not have been saved to meta yet).
        if ( isset( $_POST['ical_url'] ) ) {
            update_post_meta( $post_id, '_ovr_ical_url', esc_url_raw( wp_unslash( $_POST['ical_url'] ) ) );
        }

        $sync   = new IcalSync();
        $result = $sync->sync_property( $post_id );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ], 200 );
        }

        wp_send_json_success( [
            'message'  => $result['message'],
            'imported' => $result['imported'],
        ] );
    }

    /**
     * AJAX refresh of the results region when a village chip is clicked.
     *
     * Expects `qs` — the raw query string of the chip's target URL (e.g.
     * `village_section[0]=fruit&paged=1`). It is parsed exactly the way PHP
     * parses $_GET and laundered through the same sanitizer, so the AJAX
     * result set is identical to a hard navigation to the chip's URL — minus
     * the page reload. Returns the freshly rendered region plus the canonical
     * URL for pushState.
     */
    public function search_chips(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        $qs = wp_unslash( (string) ( $_POST['qs'] ?? '' ) );
        if ( '' === $qs ) {
            wp_send_json_error( [ 'message' => __( 'Missing query.', 'ovr-core' ) ], 400 );
        }

        wp_parse_str( $qs, $parsed );
        $parsed = is_array( $parsed ) ? $parsed : [];
        $parsed['paged'] = 1; // chips always return to the first page.

        $filters = SearchHandler::sanitize_filters( $parsed );
        $view    = isset( $parsed['view'] ) ? sanitize_key( $parsed['view'] ) : 'grid';
        if ( ! in_array( $view, [ 'grid', 'list', 'map' ], true ) ) {
            $view = 'grid';
        }
        $query = PropertyQuery::query( $filters );

        wp_send_json_success( [
            'html'  => SearchHandler::render_region( $filters, $query, $view ),
            'url'   => SearchHandler::filter_url( $filters, $view ),
            'total' => $query->found_posts,
        ] );
    }

    /**
     * AJAX property search.
     */
    public function search_properties(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        $filters = SearchHandler::get_filters_from_request();
        $query   = PropertyQuery::query( $filters );

        $html = '';
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $html .= PropertyCard::render_grid( get_the_ID() );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( [
            'html'      => $html,
            'total'     => $query->found_posts,
            'max_pages' => $query->max_num_pages,
        ] );
    }

    /**
     * Load more properties (pagination).
     */
    public function load_more(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        $paged = absint( $_POST['page'] ?? 2 );
        $filters = SearchHandler::get_filters_from_request();
        $filters['paged'] = $paged;

        $query = PropertyQuery::query( $filters );

        $html = '';
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $html .= PropertyCard::render_grid( get_the_ID() );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( [
            'html'      => $html,
            'has_more'  => $paged < $query->max_num_pages,
        ] );
    }

    /**
     * Submit property inquiry (AJAX path).
     */
    public function submit_inquiry(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        $property_id  = absint( $_POST['property_id'] ?? 0 );
        $booking_mode = get_post_meta( $property_id, '_ovr_booking_mode', true );

        $result = $this->process_inquiry( $_POST );

        if ( is_wp_error( $result ) ) {
            $code = (int) ( $result->get_error_data() ?: 400 );
            wp_send_json_error( [ 'message' => $result->get_error_message() ], $code );
        }

        if ( 'direct' === $booking_mode ) {
            $inquiry_id = $result;
            $base_price = (float) get_post_meta( $property_id, '_ovr_base_price', true );
            $checkin    = sanitize_text_field( wp_unslash( $_POST['checkin_date'] ?? '' ) );
            $checkout   = sanitize_text_field( wp_unslash( $_POST['checkout_date'] ?? '' ) );

            $nights = 1;
            if ( $checkin && $checkout ) {
                $diff = strtotime( $checkout ) - strtotime( $checkin );
                if ( $diff > 0 ) {
                    $nights = floor( $diff / DAY_IN_SECONDS );
                }
            }

            $total_amount = $base_price * $nights;
            if ( $total_amount <= 0 ) {
                $total_amount = $base_price;
            }

            $paypal = new \OVR\Payment\PayPalGateway();
            $checkout_args = [
                'user_id'      => get_current_user_id() ?: 0,
                'plan_slug'    => 'booking_' . $inquiry_id,
                'amount'       => $total_amount,
                'payment_type' => 'booking',
                'currency'     => get_option( 'ovr_settings' )['currency'] ?? 'USD',
                'return_url'   => home_url( '/' ),
                'cancel_url'   => home_url( '/' ),
            ];

            $checkout_res = $paypal->start_checkout( $checkout_args );

            if ( ! empty( $checkout_res['redirect_url'] ) && $paypal->is_configured() ) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'ovr_inquiries',
                    [ 'status' => 'pending_payment' ],
                    [ 'id' => $inquiry_id ],
                    [ '%s' ],
                    [ '%d' ]
                );

                wp_send_json_success( [
                    'redirect_url' => $checkout_res['redirect_url'],
                    'message'      => __( 'Redirecting to PayPal…', 'ovr-core' ),
                ] );
            }
        }

        wp_send_json_success( [
            'message'     => __( 'Your inquiry has been sent successfully!', 'ovr-core' ),
            'inquiry_id'  => $result,
        ] );
    }

    /**
     * Submit property review (AJAX path).
     */
    public function submit_review(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        if ( ! class_exists( '\OVR\Property\Reviews' ) ) {
            wp_send_json_error( [ 'message' => __( 'Reviews module not found.', 'ovr-core' ) ], 500 );
        }

        $result = \OVR\Property\Reviews::submit( wp_unslash( $_POST ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
        }

        wp_send_json_success( [
            'message'   => __( 'Thank you! Your testimonial is awaiting moderation.', 'ovr-core' ),
            'review_id' => $result,
        ] );
    }

    public function submit_inquiry_post(): void {
        $referer = wp_get_referer() ?: home_url( '/' );

        if ( ! isset( $_POST['ovr_inquiry_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_inquiry_nonce'] ) ), 'ovr_inquiry_action' ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_inquiry', 'nonce_failed', $referer ) . '#ovr-inquiry' );
            exit;
        }

        $property_id  = absint( $_POST['property_id'] ?? 0 );
        $booking_mode = get_post_meta( $property_id, '_ovr_booking_mode', true );

        $result = $this->process_inquiry( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'ovr_inquiry', 'error', $referer ) . '#ovr-inquiry' );
            exit;
        }

        if ( 'direct' === $booking_mode ) {
            $inquiry_id = $result;
            $base_price = (float) get_post_meta( $property_id, '_ovr_base_price', true );
            $checkin    = sanitize_text_field( wp_unslash( $_POST['checkin_date'] ?? '' ) );
            $checkout   = sanitize_text_field( wp_unslash( $_POST['checkout_date'] ?? '' ) );

            $nights = 1;
            if ( $checkin && $checkout ) {
                $diff = strtotime( $checkout ) - strtotime( $checkin );
                if ( $diff > 0 ) {
                    $nights = floor( $diff / DAY_IN_SECONDS );
                }
            }

            $total_amount = $base_price * $nights;
            if ( $total_amount <= 0 ) {
                $total_amount = $base_price; // Fallback
            }

            $paypal = new \OVR\Payment\PayPalGateway();
            $checkout_args = [
                'user_id'      => get_current_user_id() ?: 0,
                'plan_slug'    => 'booking_' . $inquiry_id,
                'amount'       => $total_amount,
                'payment_type' => 'booking',
                'currency'     => get_option( 'ovr_settings' )['currency'] ?? 'USD',
                'return_url'   => add_query_arg( 'ovr_inquiry', 'sent', $referer ),
                'cancel_url'   => add_query_arg( 'ovr_inquiry', 'canceled', $referer ),
            ];

            $checkout_res = $paypal->start_checkout( $checkout_args );

            if ( ! empty( $checkout_res['redirect_url'] ) && $paypal->is_configured() ) {
                // Update inquiry status to pending_payment
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'ovr_inquiries',
                    [ 'status' => 'pending_payment' ],
                    [ 'id' => $inquiry_id ],
                    [ '%s' ],
                    [ '%d' ]
                );

                wp_safe_redirect( $checkout_res['redirect_url'] );
                exit;
            }
        }

        wp_safe_redirect( add_query_arg( 'ovr_inquiry', 'sent', $referer ) . '#ovr-inquiry' );
        exit;
    }

    /**
     * Shared inquiry processing — validates input and inserts into ovr_inquiries.
     *
     * Accepts both AJAX field names and form field names:
     *   checkin / checkin_date, checkout / checkout_date.
     *
     * @param array $data The $_POST or AJAX payload.
     * @return int|\WP_Error Inserted inquiry ID, or WP_Error with HTTP status code.
     */
    private function process_inquiry( array $data ) {
        $property_id = absint( $data['property_id'] ?? 0 );
        $name        = sanitize_text_field( wp_unslash( $data['guest_name']  ?? '' ) );
        $email       = sanitize_email(     wp_unslash( $data['guest_email'] ?? '' ) );
        $phone       = sanitize_text_field( wp_unslash( $data['guest_phone'] ?? '' ) );
        $message     = sanitize_textarea_field( wp_unslash( $data['message'] ?? '' ) );

        // Accept either field name; prefer the longer (canonical) form.
        $checkin_raw  = $data['checkin_date']  ?? $data['checkin']  ?? '';
        $checkout_raw = $data['checkout_date'] ?? $data['checkout'] ?? '';
        $checkin      = sanitize_text_field( wp_unslash( $checkin_raw ) );
        $checkout     = sanitize_text_field( wp_unslash( $checkout_raw ) );
        $guests       = absint( $data['guests'] ?? 0 );

        // Validate property post.
        $post = get_post( $property_id );
        if ( ! $post || 'ovr_property' !== $post->post_type ) {
            return new \WP_Error( 'invalid_property', __( 'Invalid property.', 'ovr-core' ), 400 );
        }

        $booking_mode = get_post_meta( $property_id, '_ovr_booking_mode', true );

        // Validate required fields.
        if ( ! $property_id ) {
            return new \WP_Error( 'invalid_property', __( 'Property is required.', 'ovr-core' ), 400 );
        }
        // Phase 23: Name, Email, Phone, and Message are all required for every
        // inquiry so the owner always receives full contact details.
        if ( empty( $name ) || empty( $email ) || empty( $phone ) || empty( $message ) ) {
            return new \WP_Error( 'missing_fields', __( 'Please provide your name, email, phone, and a message.', 'ovr-core' ), 400 );
        }
        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'bad_email', __( 'Please enter a valid email address.', 'ovr-core' ), 400 );
        }

        // Validate date order if both supplied.
        if ( $checkin && $checkout && strtotime( $checkin ) >= strtotime( $checkout ) ) {
            return new \WP_Error( 'bad_dates', __( 'Checkout must be after check-in.', 'ovr-core' ), 400 );
        }

        // Honeypot spam guard — silent success if filled.
        if ( ! empty( $data['ovr_hp'] ) ) {
            return 0; // Pretend success without inserting.
        }

        // "I'm not a robot" confirmation (inquiry form) must be ticked.
        if ( empty( $data['ovr_human'] ) ) {
            return new \WP_Error( 'human_required', __( 'Please confirm you are not a robot.', 'ovr-core' ), 400 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_inquiries';

        $inserted = $wpdb->insert( $table, [
            'property_id'  => $property_id,
            'landlord_id'  => (int) $post->post_author,
            'guest_name'   => $name,
            'guest_email'  => $email,
            'guest_phone'  => $phone,
            'message'      => $message,
            'checkin_date' => $checkin  ?: null,
            'checkout_date'=> $checkout ?: null,
            'guests'       => $guests   ?: null,
            'status'       => 'new',
        ], [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ] );

        if ( false === $inserted ) {
            return new \WP_Error( 'db_error', __( 'Failed to submit inquiry. Please try again.', 'ovr-core' ), 500 );
        }

        $inquiry_id = (int) $wpdb->insert_id;

        do_action( 'ovr_inquiry_submitted', $inquiry_id, $property_id );

        return $inquiry_id;
    }

    /**
     * Auto-geocode address fields from the listing editor. Returns lat/lng
     * via OpenStreetMap Nominatim (no API key required). Uses Geocoder::geocode()
     * which caches results in transients to stay within rate limits.
     */
    public function geocode_address(): void {
        if ( ! check_ajax_referer( 'ovr_listing_action', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'ovr-core' ) ], 403 );
        }

        $address = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );
        $city    = sanitize_text_field( wp_unslash( $_POST['city']    ?? '' ) );
        $state   = sanitize_text_field( wp_unslash( $_POST['state']   ?? '' ) );
        $zip     = sanitize_text_field( wp_unslash( $_POST['zip']     ?? '' ) );

        $coords = Geocoder::geocode( $address, $city, $state, $zip );

        if ( $coords ) {
            wp_send_json_success( $coords );
        }

        wp_send_json_error( [ 'message' => __( 'Could not locate that address.', 'ovr-core' ) ], 200 );
    }

    /**
     * Village-search autocomplete (Section 7).
     *
     * Returns a small, ranked list of village suggestions for the given query
     * so the search bar can offer live suggestions. Two sources are combined:
     *  1. `ovr_village` taxonomy terms (curated sections).
     *  2. `_ovr_village_name` meta values actually entered on published listings.
     *
     * Matching is tolerant of typos: prefix/substring hits rank first, then a
     * Levenshtein-distance pass over the candidate names catches a misspelling
     * (e.g. "Spanish Sprng" → "Spanish Springs"). Never more than the requested
     * limit is returned, so this stays lightweight (no load-all-villages).
     */
    public function suggest_villages(): void {
        check_ajax_referer( 'ovr_public_nonce', 'nonce' );

        $q     = strtolower( trim( sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) ) ) );
        $limit = min( 8, max( 1, absint( $_POST['limit'] ?? 6 ) ) );

        if ( '' === $q ) {
            wp_send_json_success( [ 'suggestions' => [], 'query' => $q ] );
        }

        $candidates = $this->village_candidates();

        $ranked = [];
        foreach ( $candidates as $name ) {
            // Compute a similarity score (higher = better). Substring + prefix
            // matches are near-exact; fallbacks are fuzzy-only.
            $score = 0;
            if ( strpos( $name, $q ) !== false ) {
                $score = 100 + ( str_starts_with( $name, $q ) ? 50 : 0 );
                if ( $name === $q ) {
                    $score = 300;
                }
            } else {
                $len  = max( strlen( $q ), 1 );
                $dist = levenshtein( substr( $name, 0, max( $len, 12 ) ), $q );
                $rel  = ( $len - $dist ) / $len;
                if ( $rel >= 0.4 ) {
                    $score = (int) ( $rel * 60 );
                }
            }
            if ( $score > 0 ) {
                $ranked[] = [ 'name' => $name, 'score' => $score ];
            }
        }

        usort( $ranked, static fn( $a, $b ) => $b['score'] <=> $a['score'] );

        $suggestions = array_column( array_slice( $ranked, 0, $limit ), 'name' );

        wp_send_json_success( [ 'suggestions' => $suggestions, 'query' => $q ] );
    }

    /**
     * All distinct village names to run autocomplete against. Combines the
     * curated taxonomy terms with the free-text names entered on listings,
     * cached briefly so the (rare) listing-driven set doesn't hit the DB on
     * every keystroke.
     *
     * @return string[]
     */
    private function village_candidates(): array {
        global $wpdb;

        $cached = wp_cache_get( 'ovr_suggest_candidates', 'ovr' );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $names = $wpdb->get_col(
            "SELECT m.meta_value
             FROM {$wpdb->postmeta} m
             INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE m.meta_key = '_ovr_village_name'
               AND m.meta_value <> ''
               AND p.post_type = 'ovr_property'
               AND p.post_status = 'publish'"
        );

        $terms = get_terms( [ 'taxonomy' => 'ovr_village', 'hide_empty' => false, 'fields' => 'names' ] );
        if ( ! is_wp_error( $terms ) ) {
            $names = array_merge( $names, $terms );
        }

        $out = [];
        foreach ( $names as $n ) {
            $orig  = trim( (string) $n );
            $clean = strtolower( $orig );
            if ( '' !== $clean ) {
                // Key by lowercase for de-duplication, but keep the original
                // casing so suggestions render as "Spanish Springs", not
                // "spanish springs".
                $out[ $clean ] = $orig;
            }
        }

        $out = array_values( $out );
        wp_cache_set( 'ovr_suggest_candidates', $out, 'ovr', 5 * MINUTE_IN_SECONDS );

        return $out;
    }
}

