<?php
/**
 * Lookup Data Management (§12).
 *
 * Restores administrator maintenance for the Amenities, Features, and Views
 * taxonomies beyond WordPress's built-in add/edit/delete: each term gains an
 * "Enabled" toggle and a numeric "Order", surfaced both on the term add/edit
 * forms and as sortable columns on the term list table. The front-end pickers
 * and search facets consume {@see enabled_terms()} so disabled terms disappear
 * from new selections and everything renders in the admin-defined order.
 *
 * @package OVR\Admin
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LookupTaxonomies {

    /** Taxonomies managed here. */
    public const TAXONOMIES = [ 'ovr_amenity', 'ovr_feature', 'ovr_view' ];

    public const META_ENABLED = 'ovr_term_enabled';
    public const META_ORDER   = 'ovr_term_order';

    public function init(): void {
        add_action( 'init', [ $this, 'register_meta' ], 20 );

        foreach ( self::TAXONOMIES as $tax ) {
            add_action( "{$tax}_add_form_fields",  [ $this, 'add_form_fields' ] );
            add_action( "{$tax}_edit_form_fields", [ $this, 'edit_form_fields' ] );
            add_action( "created_{$tax}", [ $this, 'save_term_fields' ] );
            add_action( "edited_{$tax}",  [ $this, 'save_term_fields' ] );

            add_filter( "manage_edit-{$tax}_columns", [ $this, 'columns' ] );
            add_filter( "manage_edit-{$tax}_sortable_columns", [ $this, 'sortable_columns' ] );
            add_filter( "manage_{$tax}_custom_column", [ $this, 'column_content' ], 10, 3 );
        }

        // Apply the "Order" sort when browsing a managed taxonomy in wp-admin.
        add_action( 'pre_get_terms', [ $this, 'apply_admin_order' ] );
    }

    public function register_meta(): void {
        foreach ( self::TAXONOMIES as $tax ) {
            register_term_meta( $tax, self::META_ENABLED, [
                'type'         => 'boolean',
                'single'       => true,
                'show_in_rest' => false,
            ] );
            register_term_meta( $tax, self::META_ORDER, [
                'type'         => 'integer',
                'single'       => true,
                'show_in_rest' => false,
            ] );
        }
    }

    // ── Term forms ──────────────────────────────────────────────────────────

    public function add_form_fields(): void {
        ?>
        <div class="form-field">
            <label for="ovr_term_order"><?php esc_html_e( 'Display Order', 'ovr-core' ); ?></label>
            <input type="number" name="ovr_term_order" id="ovr_term_order" value="0" step="1">
            <p><?php esc_html_e( 'Lower numbers appear first in listing forms and search filters.', 'ovr-core' ); ?></p>
        </div>
        <div class="form-field">
            <label><input type="checkbox" name="ovr_term_enabled" value="1" checked> <?php esc_html_e( 'Enabled (available for selection)', 'ovr-core' ); ?></label>
        </div>
        <?php
    }

    public function edit_form_fields( \WP_Term $term ): void {
        $order   = (int) get_term_meta( $term->term_id, self::META_ORDER, true );
        $enabled = '0' !== (string) get_term_meta( $term->term_id, self::META_ENABLED, true );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="ovr_term_order"><?php esc_html_e( 'Display Order', 'ovr-core' ); ?></label></th>
            <td>
                <input type="number" name="ovr_term_order" id="ovr_term_order" value="<?php echo esc_attr( (string) $order ); ?>" step="1">
                <p class="description"><?php esc_html_e( 'Lower numbers appear first in listing forms and search filters.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
            <td>
                <label><input type="checkbox" name="ovr_term_enabled" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'Enabled (available for selection)', 'ovr-core' ); ?></label>
                <p class="description"><?php esc_html_e( 'Disabled values stay on existing listings but cannot be newly selected.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_term_fields( int $term_id ): void {
        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }
        // Only act on our own form submissions (both fields are always posted
        // together by the add/edit forms above).
        if ( ! isset( $_POST['ovr_term_order'] ) && ! isset( $_POST['ovr_term_enabled'] ) ) {
            return;
        }
        update_term_meta( $term_id, self::META_ORDER, (int) ( $_POST['ovr_term_order'] ?? 0 ) );
        update_term_meta( $term_id, self::META_ENABLED, empty( $_POST['ovr_term_enabled'] ) ? '0' : '1' );
    }

    // ── Term list-table columns ─────────────────────────────────────────────

    public function columns( array $columns ): array {
        // Insert Order + Status before the Count column.
        $count = $columns['posts'] ?? null;
        unset( $columns['posts'] );
        $columns['ovr_order']  = __( 'Order', 'ovr-core' );
        $columns['ovr_status'] = __( 'Status', 'ovr-core' );
        if ( null !== $count ) {
            $columns['posts'] = $count;
        }
        return $columns;
    }

    public function sortable_columns( array $columns ): array {
        $columns['ovr_order'] = 'ovr_order';
        return $columns;
    }

    /**
     * @param string $content
     * @param string $column
     * @param int    $term_id
     */
    public function column_content( $content, $column, $term_id ) {
        if ( 'ovr_order' === $column ) {
            return esc_html( (string) (int) get_term_meta( $term_id, self::META_ORDER, true ) );
        }
        if ( 'ovr_status' === $column ) {
            $enabled = '0' !== (string) get_term_meta( $term_id, self::META_ENABLED, true );
            $label   = $enabled ? __( 'Enabled', 'ovr-core' ) : __( 'Disabled', 'ovr-core' );
            $color   = $enabled ? '#1e7e34' : '#8a8a8a';
            return '<span style="color:' . esc_attr( $color ) . ';font-weight:600">' . esc_html( $label ) . '</span>';
        }
        return $content;
    }

    /**
     * Sort managed taxonomies by our Order meta when the list table is shown
     * without an explicit orderby.
     */
    public function apply_admin_order( \WP_Term_Query $query ): void {
        if ( ! is_admin() ) {
            return;
        }
        $taxes = (array) ( $query->query_vars['taxonomy'] ?? [] );
        if ( ! array_intersect( $taxes, self::TAXONOMIES ) ) {
            return;
        }
        $orderby = $query->query_vars['orderby'] ?? '';
        if ( '' === $orderby || 'name' === $orderby || 'ovr_order' === $orderby ) {
            $query->query_vars['meta_key'] = self::META_ORDER;
            $query->query_vars['orderby']  = 'meta_value_num';
        }
    }

    // ── Front-end consumption ───────────────────────────────────────────────

    /**
     * Enabled terms for a managed taxonomy, ordered by the admin-defined Order
     * then name. Terms with no meta yet are treated as enabled / order 0.
     *
     * @return \WP_Term[]
     */
    public static function enabled_terms( string $taxonomy, array $args = [] ): array {
        $terms = get_terms( array_merge( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ], $args ) );
        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return [];
        }
        if ( in_array( $taxonomy, self::TAXONOMIES, true ) ) {
            $terms = array_values( array_filter(
                $terms,
                static fn( $t ) => '0' !== (string) get_term_meta( $t->term_id, self::META_ENABLED, true )
            ) );
            // Unset order sorts *after* explicitly-ordered terms (so setting a low
            // number actually promotes a term to the front); ties break by name.
            $order_of = static function ( $t ): int {
                $raw = get_term_meta( $t->term_id, self::META_ORDER, true );
                return '' === $raw ? PHP_INT_MAX : (int) $raw;
            };
            usort( $terms, static function ( $a, $b ) use ( $order_of ) {
                $oa = $order_of( $a );
                $ob = $order_of( $b );
                return $oa === $ob ? strcasecmp( $a->name, $b->name ) : $oa <=> $ob;
            } );
        }
        return $terms;
    }
}
