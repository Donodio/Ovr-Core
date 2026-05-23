<?php
/**
 * Property Meta Boxes — premium tabbed admin UI.
 *
 * Renders a single full-width meta box below the Gutenberg editor on the
 * ovr_property edit screen. Inside, six tabs cover every property field:
 *
 *   General · Pricing · Location · Media · Seasonal Pricing · Availability
 *
 * Saves into post meta (PropertyMeta keys) plus the wp_ovr_seasonal_pricing
 * and wp_ovr_availability custom tables.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Property\PropertyMeta;
use OVR\Property\SeasonalPricing;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PropertyMetaBoxes {

    /** @var string Nonce action. */
    private const NONCE_ACTION = 'ovr_property_meta_save';
    private const NONCE_NAME   = 'ovr_property_meta_nonce';

    /** @var string Post type. */
    private const POST_TYPE = 'ovr_property';

    /** @var string[] Numeric meta keys (cast on save). */
    private const NUMERIC_INT = [ 'bedrooms', 'beds', 'max_guests', 'sqft', 'min_stay', 'rating_count' ];
    private const NUMERIC_DEC = [ 'bathrooms', 'base_price', 'latitude', 'longitude', 'rating_avg' ];
    private const BOOLEAN     = [ 'pets_allowed', 'is_featured', 'is_bumped' ];
    private const TEXT        = [
        'address', 'city', 'state', 'zip', 'country',
        'video_url', 'panorama_url', 'ical_url',
        'bump_expires', 'featured_expires', 'listing_status', 'booking_mode',
    ];

    /** @var int Document upload cap. */
    public const MAX_DOCS = 3;

    public function init(): void {
        add_action( 'add_meta_boxes_' . self::POST_TYPE, [ $this, 'register_meta_box' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save' ], 10, 2 );

        // Move the publish/excerpt boxes into expected positions and hide custom-fields meta.
        add_action( 'admin_menu', [ $this, 'remove_default_boxes' ] );
    }

    /**
     * Strip the legacy "Custom Fields" meta box — we own the field UI now.
     */
    public function remove_default_boxes(): void {
        remove_meta_box( 'postcustom', self::POST_TYPE, 'normal' );
    }

    /**
     * Register the single tabbed meta box below the editor.
     */
    public function register_meta_box(): void {
        add_meta_box(
            'ovr_property_details',
            __( 'Property Details', 'ovr-core' ),
            [ $this, 'render' ],
            self::POST_TYPE,
            'normal',
            'high'
        );

        // Sidebar: status / promotion.
        add_meta_box(
            'ovr_property_status',
            __( 'Listing Status & Promotion', 'ovr-core' ),
            [ $this, 'render_status_sidebar' ],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render the main tabbed meta box.
     */
    public function render( \WP_Post $post ): void {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

        $meta            = PropertyMeta::get_all( $post->ID );
        $seasonal        = SeasonalPricing::get_pricing( $post->ID );
        $availability    = $this->get_availability_blocks( $post->ID );
        $gallery_ids     = self::parse_id_string( (string) ( $meta['gallery_ids'] ?? '' ) );

        $tabs = [
            'general'      => [ 'label' => __( 'General',          'ovr-core' ), 'icon' => 'home_work' ],
            'pricing'      => [ 'label' => __( 'Pricing',          'ovr-core' ), 'icon' => 'sell' ],
            'location'     => [ 'label' => __( 'Location',         'ovr-core' ), 'icon' => 'map' ],
            'media'        => [ 'label' => __( 'Photos & Media',   'ovr-core' ), 'icon' => 'photo_library' ],
            'seasonal'     => [ 'label' => __( 'Seasonal Pricing', 'ovr-core' ), 'icon' => 'calendar_month' ],
            'availability' => [ 'label' => __( 'Availability',     'ovr-core' ), 'icon' => 'event_available' ],
        ];

        TemplateLoader::render( 'admin/property-meta-tabs.php', [
            'post'         => $post,
            'meta'         => $meta,
            'tabs'         => $tabs,
            'seasonal'     => $seasonal,
            'availability' => $availability,
            'gallery_ids'  => $gallery_ids,
        ] );
    }

    /**
     * Render the sidebar promotion box (Featured / Bumped).
     */
    public function render_status_sidebar( \WP_Post $post ): void {
        // Nonce already printed in main meta box, but include a separate one for safety
        // when only the sidebar is submitted (rare but possible via admin-ajax).
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME . '_side' );
        $meta = PropertyMeta::get_all( $post->ID );
        TemplateLoader::render( 'admin/property-tabs/status-sidebar.php', [
            'post' => $post,
            'meta' => $meta,
        ] );
    }

    /**
     * Save handler.
     */
    public function save( int $post_id, \WP_Post $post ): void {
        // Nonce.
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
            return;
        }

        // Skip autosave / quick edit.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

        // Capability.
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Pull payload.
        $raw = $_POST['ovr_meta'] ?? [];
        if ( ! is_array( $raw ) ) {
            $raw = [];
        }

        // Integer fields.
        foreach ( self::NUMERIC_INT as $key ) {
            $val = isset( $raw[ $key ] ) ? absint( $raw[ $key ] ) : 0;
            update_post_meta( $post_id, '_ovr_' . $key, $val );
        }

        // Decimal fields.
        foreach ( self::NUMERIC_DEC as $key ) {
            $val = isset( $raw[ $key ] ) ? (float) wp_unslash( $raw[ $key ] ) : 0.0;
            update_post_meta( $post_id, '_ovr_' . $key, $val );
        }

        // Boolean checkboxes (presence = true).
        foreach ( self::BOOLEAN as $key ) {
            $val = ! empty( $raw[ $key ] ) ? 1 : 0;
            update_post_meta( $post_id, '_ovr_' . $key, $val );
        }

        // Plain text fields.
        foreach ( self::TEXT as $key ) {
            $value = sanitize_text_field( wp_unslash( $raw[ $key ] ?? '' ) );
            // URL fields get extra esc_url_raw treatment.
            if ( in_array( $key, [ 'video_url', 'panorama_url', 'ical_url' ], true ) ) {
                $value = $value ? esc_url_raw( $value ) : '';
            }
            // Listing status whitelist.
            if ( 'listing_status' === $key ) {
                $allowed = [ 'active', 'inactive', 'pending_renewal', 'draft' ];
                $value   = in_array( $value, $allowed, true ) ? $value : 'active';
            }
            // Booking mode whitelist.
            if ( 'booking_mode' === $key ) {
                $allowed_modes = [ 'direct', 'inquiry' ];
                $value = in_array( $value, $allowed_modes, true ) ? $value : 'inquiry';
            }
            update_post_meta( $post_id, '_ovr_' . $key, $value );
        }

        // Gallery IDs (comma-separated list of integers).
        $gallery_raw = isset( $raw['gallery_ids'] ) ? (string) wp_unslash( $raw['gallery_ids'] ) : '';
        $gallery_ids = self::parse_id_string( $gallery_raw );
        update_post_meta( $post_id, '_ovr_gallery_ids', implode( ',', $gallery_ids ) );

        // Document IDs — capped at MAX_DOCS. Validates each ID exists & is an attachment.
        $docs_raw = isset( $raw['document_ids'] ) ? (string) wp_unslash( $raw['document_ids'] ) : '';
        $doc_ids  = array_slice( self::parse_id_string( $docs_raw ), 0, self::MAX_DOCS );
        $doc_ids  = array_values( array_filter( $doc_ids, function ( $id ) {
            $att = get_post( (int) $id );
            return $att && 'attachment' === $att->post_type;
        } ) );
        update_post_meta( $post_id, '_ovr_document_ids', implode( ',', $doc_ids ) );

        // Seasonal pricing (custom table).
        $this->save_seasonal_pricing( $post_id, $raw['seasonal'] ?? [] );

        // Availability blocks (custom table).
        $this->save_availability( $post_id, $raw['availability'] ?? [] );

        // Bust caches updated by frontend templates.
        wp_cache_delete( 'ovr_pricing_'  . $post_id, 'ovr' );
        wp_cache_delete( 'ovr_avail_'    . $post_id, 'ovr' );
        wp_cache_delete( 'ovr_price_range', 'ovr' );

        do_action( 'ovr_property_saved', $post_id, $post );
    }

    /**
     * Replace seasonal pricing rows for a property.
     */
    private function save_seasonal_pricing( int $post_id, $rows ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_seasonal_pricing';

        $wpdb->delete( $table, [ 'property_id' => $post_id ], [ '%d' ] );

        if ( ! is_array( $rows ) ) {
            return;
        }

        $sort = 0;
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) continue;

            $name  = sanitize_text_field( wp_unslash( $row['season_name']  ?? '' ) );
            $start = sanitize_text_field( wp_unslash( $row['start_date']   ?? '' ) );
            $end   = sanitize_text_field( wp_unslash( $row['end_date']     ?? '' ) );
            $rate  = (float) ( $row['nightly_rate'] ?? 0 );
            $min   = absint( $row['min_stay'] ?? 1 );

            // Drop empty rows.
            if ( '' === $name && '' === $start && '' === $end && 0.0 === $rate ) {
                continue;
            }

            $wpdb->insert( $table, [
                'property_id'  => $post_id,
                'season_name'  => $name,
                'start_date'   => $start ?: null,
                'end_date'     => $end ?: null,
                'nightly_rate' => $rate,
                'min_stay'     => $min,
                'sort_order'   => $sort++,
            ], [ '%d', '%s', '%s', '%s', '%f', '%d', '%d' ] );
        }
    }

    /**
     * Replace manual availability-block rows for a property.
     * Only manages rows where source='manual' — leaves ical-imported rows alone.
     */
    private function save_availability( int $post_id, $rows ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';

        // Clear only manually-managed rows.
        $wpdb->delete(
            $table,
            [ 'property_id' => $post_id, 'source' => 'manual' ],
            [ '%d', '%s' ]
        );

        if ( ! is_array( $rows ) ) {
            return;
        }

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) continue;

            $start = sanitize_text_field( wp_unslash( $row['start_date'] ?? '' ) );
            $end   = sanitize_text_field( wp_unslash( $row['end_date']   ?? '' ) );
            if ( ! $start || ! $end ) continue;

            $type  = sanitize_key( $row['block_type'] ?? 'blocked' );
            $allow = [ 'blocked', 'booked', 'tentative', 'maintenance' ];
            if ( ! in_array( $type, $allow, true ) ) $type = 'blocked';

            $notes  = sanitize_textarea_field( wp_unslash( $row['notes'] ?? '' ) );
            $show   = ! empty( $row['show_as_available'] ) ? 1 : 0;

            $wpdb->insert( $table, [
                'property_id'        => $post_id,
                'block_type'         => $type,
                'start_date'         => $start,
                'end_date'           => $end,
                'source'             => 'manual',
                'notes'              => $notes,
                'show_as_available'  => $show,
            ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%d' ] );
        }
    }

    /**
     * Pull manually-managed availability blocks for the edit screen.
     */
    private function get_availability_blocks( int $post_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_availability';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE property_id = %d AND source = 'manual'
                 ORDER BY start_date ASC",
                $post_id
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Parse a comma- or space-delimited string of IDs into a clean integer array.
     *
     * @param string $raw
     * @return int[]
     */
    public static function parse_id_string( string $raw ): array {
        if ( '' === trim( $raw ) ) return [];
        $parts = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
        $ids   = array_map( 'absint', $parts ?: [] );
        return array_values( array_filter( $ids ) );
    }
}
