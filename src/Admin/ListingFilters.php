<?php
/**
 * Listing admin filters (Phase 11).
 *
 * Adds a filter bar to the wp-admin Listings table
 * (edit.php?post_type=ovr_property) so admins can locate any listing in
 * seconds. Property ID is the first and most prominent control. All filters are
 * combinable and applied server-side via pre_get_posts; the native search box is
 * also extended to match a listing's address and village name.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Subscription\Plans;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ListingFilters {

    private const PT = 'ovr_property';

    public function init(): void {
        add_action( 'restrict_manage_posts', [ $this, 'render_filters' ] );
        add_action( 'pre_get_posts', [ $this, 'apply_filters' ] );
        add_filter( 'posts_search', [ $this, 'extend_search' ], 10, 2 );
    }

    /** Whether we're on the listings list-table screen. */
    private function on_screen(): bool {
        if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
            return false;
        }
        $screen = get_current_screen();
        return $screen && 'edit' === $screen->base && self::PT === $screen->post_type;
    }

    /**
     * Render the filter controls above the list table.
     */
    public function render_filters( string $post_type ): void {
        if ( self::PT !== $post_type ) {
            return;
        }

        $pid     = isset( $_GET['ovr_pid'] ) ? absint( $_GET['ovr_pid'] ) : '';
        $owner   = isset( $_GET['ovr_owner'] ) ? absint( $_GET['ovr_owner'] ) : 0;
        $section = isset( $_GET['ovr_section'] ) ? sanitize_text_field( wp_unslash( $_GET['ovr_section'] ) ) : '';
        $sub     = isset( $_GET['ovr_sub'] ) ? sanitize_key( wp_unslash( $_GET['ovr_sub'] ) ) : '';
        $feat    = isset( $_GET['ovr_featured'] ) ? sanitize_key( wp_unslash( $_GET['ovr_featured'] ) ) : '';
        $prem    = isset( $_GET['ovr_premium'] ) ? sanitize_key( wp_unslash( $_GET['ovr_premium'] ) ) : '';
        $lstatus = isset( $_GET['ovr_status'] ) ? sanitize_key( wp_unslash( $_GET['ovr_status'] ) ) : '';

        // Property ID — most prominent (Phase 11).
        echo '<input type="number" name="ovr_pid" value="' . esc_attr( (string) $pid ) . '" placeholder="' . esc_attr__( 'Property ID #', 'ovr-core' ) . '" style="width:130px;height:32px;margin-right:6px;font-weight:600" />';

        // Owner (landlords).
        $owners = get_users( [ 'role__in' => [ 'ovr_landlord', 'administrator' ], 'orderby' => 'display_name', 'number' => 500 ] );
        echo '<select name="ovr_owner"><option value="0">' . esc_html__( 'All owners', 'ovr-core' ) . '</option>';
        foreach ( $owners as $u ) {
            printf(
                '<option value="%d"%s>%s</option>',
                (int) $u->ID,
                selected( $owner, (int) $u->ID, false ),
                esc_html( $u->display_name . ' (' . $u->user_email . ')' )
            );
        }
        echo '</select>';

        // Section (ovr_village taxonomy).
        $terms = get_terms( [ 'taxonomy' => 'ovr_village', 'hide_empty' => false ] );
        echo '<select name="ovr_section"><option value="">' . esc_html__( 'All sections', 'ovr-core' ) . '</option>';
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                printf( '<option value="%s"%s>%s</option>', esc_attr( $t->slug ), selected( $section, $t->slug, false ), esc_html( $t->name ) );
            }
        }
        echo '</select>';

        // Subscription type (owner's plan).
        echo '<select name="ovr_sub"><option value="">' . esc_html__( 'Any subscription', 'ovr-core' ) . '</option>';
        foreach ( (array) Plans::get_plans() as $slug => $plan ) {
            $label = is_array( $plan ) ? ( $plan['name'] ?? $slug ) : (string) $plan;
            printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $sub, $slug, false ), esc_html( $label ) );
        }
        echo '</select>';

        // Featured / Premium (bumped) / Listing status.
        $yn = [ '' => __( 'Featured: any', 'ovr-core' ), 'yes' => __( 'Featured: yes', 'ovr-core' ), 'no' => __( 'Featured: no', 'ovr-core' ) ];
        echo '<select name="ovr_featured">';
        foreach ( $yn as $v => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $feat, $v, false ), esc_html( $l ) ); }
        echo '</select>';

        $ynp = [ '' => __( 'Premium: any', 'ovr-core' ), 'yes' => __( 'Premium: yes', 'ovr-core' ), 'no' => __( 'Premium: no', 'ovr-core' ) ];
        echo '<select name="ovr_premium">';
        foreach ( $ynp as $v => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $prem, $v, false ), esc_html( $l ) ); }
        echo '</select>';

        $statuses = [
            ''         => __( 'Status: any', 'ovr-core' ),
            'active'   => __( 'Active', 'ovr-core' ),
            'inactive' => __( 'Inactive', 'ovr-core' ),
            'approved' => __( 'Admin: Approved', 'ovr-core' ),
            'hidden'   => __( 'Admin: Hidden', 'ovr-core' ),
            'suspended'=> __( 'Admin: Suspended', 'ovr-core' ),
            'pending_review' => __( 'Admin: Pending Review', 'ovr-core' ),
        ];
        echo '<select name="ovr_status">';
        foreach ( $statuses as $v => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $lstatus, $v, false ), esc_html( $l ) ); }
        echo '</select>';
    }

    /**
     * Apply the filter selections to the listings query.
     */
    public function apply_filters( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        if ( self::PT !== $query->get( 'post_type' ) ) {
            return;
        }

        // Property ID — exact match wins outright (Phase 11: locate in seconds).
        $pid = isset( $_GET['ovr_pid'] ) ? absint( $_GET['ovr_pid'] ) : 0;
        if ( $pid ) {
            $query->set( 'p', $pid );
            return;
        }

        $meta_query = (array) $query->get( 'meta_query' );
        $tax_query  = (array) $query->get( 'tax_query' );

        // Owner.
        $owner = isset( $_GET['ovr_owner'] ) ? absint( $_GET['ovr_owner'] ) : 0;
        if ( $owner ) {
            $query->set( 'author', $owner );
        }

        // Subscription type → restrict to listings authored by owners on that plan.
        $sub = isset( $_GET['ovr_sub'] ) ? sanitize_key( wp_unslash( $_GET['ovr_sub'] ) ) : '';
        if ( $sub ) {
            $owner_ids = get_users( [
                'meta_key'   => 'ovr_subscription_plan',
                'meta_value' => $sub,
                'fields'     => 'ID',
                'number'     => 1000,
            ] );
            $query->set( 'author__in', $owner_ids ?: [ 0 ] );
        }

        // Section taxonomy.
        $section = isset( $_GET['ovr_section'] ) ? sanitize_text_field( wp_unslash( $_GET['ovr_section'] ) ) : '';
        if ( $section ) {
            $tax_query[] = [ 'taxonomy' => 'ovr_village', 'field' => 'slug', 'terms' => [ $section ] ];
        }

        // Featured / Premium (bumped) yes/no.
        $feat = isset( $_GET['ovr_featured'] ) ? sanitize_key( wp_unslash( $_GET['ovr_featured'] ) ) : '';
        if ( 'yes' === $feat ) {
            $meta_query[] = [ 'key' => '_ovr_is_featured', 'value' => '1' ];
        } elseif ( 'no' === $feat ) {
            $meta_query[] = [ 'relation' => 'OR', [ 'key' => '_ovr_is_featured', 'compare' => 'NOT EXISTS' ], [ 'key' => '_ovr_is_featured', 'value' => '1', 'compare' => '!=' ] ];
        }
        $prem = isset( $_GET['ovr_premium'] ) ? sanitize_key( wp_unslash( $_GET['ovr_premium'] ) ) : '';
        if ( 'yes' === $prem ) {
            $meta_query[] = [ 'key' => '_ovr_is_bumped', 'value' => '1' ];
        } elseif ( 'no' === $prem ) {
            $meta_query[] = [ 'relation' => 'OR', [ 'key' => '_ovr_is_bumped', 'compare' => 'NOT EXISTS' ], [ 'key' => '_ovr_is_bumped', 'value' => '1', 'compare' => '!=' ] ];
        }

        // Status (owner active/inactive or admin status).
        $status = isset( $_GET['ovr_status'] ) ? sanitize_key( wp_unslash( $_GET['ovr_status'] ) ) : '';
        if ( in_array( $status, [ 'active', 'inactive' ], true ) ) {
            $meta_query[] = [ 'key' => '_ovr_listing_status', 'value' => $status ];
        } elseif ( in_array( $status, [ 'approved', 'hidden', 'suspended', 'pending_review' ], true ) ) {
            $meta_query[] = [ 'key' => '_ovr_admin_status', 'value' => $status ];
        }

        if ( $meta_query ) {
            $query->set( 'meta_query', $meta_query );
        }
        if ( $tax_query ) {
            $query->set( 'tax_query', $tax_query );
        }
    }

    /**
     * Extend the native search so it also matches a listing's street address and
     * village name (stored in postmeta), not just title/content.
     *
     * @param string    $search
     * @param \WP_Query $query
     */
    public function extend_search( string $search, \WP_Query $query ): string {
        if ( ! is_admin() || ! $query->is_main_query() || self::PT !== $query->get( 'post_type' ) ) {
            return $search;
        }
        $term = trim( (string) $query->get( 's' ) );
        if ( '' === $term ) {
            return $search;
        }

        global $wpdb;
        $like = '%' . $wpdb->esc_like( $term ) . '%';
        $ids  = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_ovr_address','_ovr_village_name','_ovr_city','_ovr_zip')
               AND meta_value LIKE %s",
            $like
        ) );
        if ( empty( $ids ) ) {
            return $search;
        }

        $in = implode( ',', array_map( 'absint', $ids ) );
        // Inject an OR so meta matches widen the existing title/content search.
        // $search begins with " AND (...)"; splice our clause inside the parens.
        $search = preg_replace(
            '/^\s*AND\s*\((.*)\)\s*$/s',
            " AND (($1) OR {$wpdb->posts}.ID IN ({$in}))",
            $search
        );
        return $search;
    }
}
