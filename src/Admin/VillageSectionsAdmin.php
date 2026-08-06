<?php
/**
 * Village Sections admin portal.
 *
 * Adds a "Village Sections" submenu under the OVR Properties menu where an admin
 * can pick which sections of The Villages appear as homepage cards, and the
 * order they are shown in. Sections are `ovr_village` taxonomy terms; the admin
 * chooses from the existing terms and orders them (drag handle / up-down).
 *
 * Stored as an ordered list of term IDs in the `ovr_village_sections` option.
 * The order in this list is the display order on the homepage.
 *
 * @package OVR\Admin
 * @since   1.2.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VillageSectionsAdmin {

    public const OPTION      = 'ovr_village_sections';
    public const PAGE_SLUG   = 'ovr-core-village-sections';
    public const SAVE_ACTION = 'ovr_save_village_sections';

    private string $hook_suffix = '';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_' . self::SAVE_ACTION, [ $this, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function register_page(): void {
        $this->hook_suffix = (string) add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Village Sections', 'ovr-core' ),
            __( 'Village Sections', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function enqueue( string $hook ): void {
        if ( $hook === $this->hook_suffix ) {
            wp_enqueue_media();
        }
    }

    /**
     * Ordered list of enabled section term IDs, in display order.
     *
     * @return array<int,int>
     */
    public static function get_ordered_ids(): array {
        $ids = get_option( self::OPTION, [] );
        if ( ! is_array( $ids ) ) {
            return [];
        }
        return array_values( array_map( 'absint', array_filter( $ids ) ) );
    }

    /**
     * The village terms available for selection (all sections, not just the
     * enabled ones). Sorted by name for a stable admin picker.
     *
     * @return \WP_Term[]
     */
    public static function get_all_terms(): array {
        $terms = get_terms( [
            'taxonomy'   => 'ovr_village',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );
        return is_wp_error( $terms ) ? [] : $terms;
    }

    /**
     * Enabled, ordered, existing section terms (as \WP_Term) for the front end.
     *
     * @return \WP_Term[]
     */
    public static function get_enabled_terms(): array {
        $ids     = self::get_ordered_ids();
        $out     = [];
        $by_id   = [];
        foreach ( self::get_all_terms() as $term ) {
            $by_id[ $term->term_id ] = $term;
        }
        foreach ( $ids as $id ) {
            if ( isset( $by_id[ $id ] ) ) {
                $out[] = $by_id[ $id ];
            }
        }
        return $out;
    }

    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do that.', 'ovr-core' ) );
        }
        check_admin_referer( self::SAVE_ACTION );

        $raw = isset( $_POST['ovr_sections'] ) ? (array) wp_unslash( $_POST['ovr_sections'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        $clean = [];
        foreach ( $raw as $id ) {
            $id = (int) $id;
            if ( $id > 0 && term_exists( $id, 'ovr_village' ) ) {
                $clean[] = $id;
            }
        }
        $clean = array_values( array_unique( $clean ) );

        update_option( self::OPTION, $clean );

        wp_safe_redirect( add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
            'updated'   => '1',
        ], admin_url( 'edit.php' ) ) );
        exit;
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $all      = self::get_all_terms();
        $enabled  = self::get_ordered_ids();

        // Terms already picked & ordered cannot be re-added to the picker.
        $remaining = array_values(
            array_filter( $all, static fn( $t ) => ! in_array( (int) $t->term_id, $enabled, true ) )
        );
        ?>
        <div class="wrap ovr-adm">
            <div class="ovr-adm-wrap">
                <div class="ovr-adm-head">
                    <div>
                        <h1><?php esc_html_e( 'Village Sections', 'ovr-core' ); ?></h1>
                        <p><?php esc_html_e( 'Choose which sections appear as homepage cards and their display order. Sections are the existing Village Section terms — pick the ones you want to feature.', 'ovr-core' ); ?></p>
                    </div>
                </div>

                <?php if ( ! empty( $_GET['updated'] ) ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--success"><span class="material-symbols-outlined">check_circle</span><span><?php esc_html_e( 'Village sections saved.', 'ovr-core' ); ?></span></div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
                    <?php wp_nonce_field( self::SAVE_ACTION ); ?>

                    <div class="ovr-adm-card">
                        <div class="ovr-adm-card-body">

                            <h2 class="ovr-adm-h2"><?php esc_html_e( 'Enabled sections (display order)', 'ovr-core' ); ?></h2>
                            <?php if ( empty( $enabled ) ) : ?>
                                <p class="ovr-adm-muted"><?php esc_html_e( 'No sections picked yet — add a section below.', 'ovr-core' ); ?></p>
                            <?php else : ?>
                                <div id="ovr-sections-list" class="ovr-sections-sortable">
                                    <?php foreach ( $enabled as $id ) : $this->render_picked_row( $id ); endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $enabled ) ) : ?>
                                <p class="ovr-adm-muted"><span class="material-symbols-outlined">drag_indicator</span><?php esc_html_e( 'Drag to reorder, or use the up/down arrows.', 'ovr-core' ); ?></p>
                            <?php endif; ?>

                            <h2 class="ovr-adm-h2"><?php esc_html_e( 'Available sections', 'ovr-core' ); ?></h2>
                            <?php if ( empty( $remaining ) ) : ?>
                                <p class="ovr-adm-muted"><?php esc_html_e( 'All sections are already shown.', 'ovr-core' ); ?></p>
                            <?php else : ?>
                                <div class="ovr-sections-available">
                                    <?php foreach ( $remaining as $term ) : ?>
                                        <button type="button" class="ovr-adm-btn ovr-adm-btn--ghost ovr-section-add" data-term="<?php echo esc_attr( (string) $term->term_id ); ?>" data-name="<?php echo esc_attr( $term->name ); ?>">
                                            <span class="material-symbols-outlined">add_circle</span><?php echo esc_html( $term->name ); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="ovr-adm-form-foot">
                            <button type="submit" class="ovr-adm-btn ovr-adm-btn--primary"><span class="material-symbols-outlined">save</span><?php esc_html_e( 'Save Village Sections', 'ovr-core' ); ?></button>
                        </div>
                    </div>
                </form>

                <script id="ovr-section-row-tpl" type="text/template"><?php $this->render_picked_row( '{{ID}}', '{{NAME}}' ); ?></script>

                <style>
                    .ovr-adm .ovr-adm-h2 { margin:0 0 12px; font-size:15px; font-weight:700; }
                    .ovr-adm .ovr-adm-muted { color:var(--gray); font-size:13px; display:inline-flex; align-items:center; gap:6px; }
                    .ovr-adm .ovr-sections-sortable { display:flex; flex-direction:column; gap:8px; margin-bottom:8px; }
                    .ovr-adm .ovr-section-row { display:flex; align-items:center; gap:12px; padding:10px 12px; background:var(--surf); border:1px solid var(--gray-border); border-radius:var(--r-sm); }
                    .ovr-adm .ovr-section-row.ui-sortable-handle { cursor:grab; }
                    .ovr-adm .ovr-section-row .ovr-section-name { flex:1 1 auto; font-weight:600; }
                    .ovr-adm .ovr-section-row .ovr-section-id { display:none; }
                    .ovr-adm .ovr-section-controls { display:inline-flex; gap:2px; }
                    .ovr-adm .ovr-section-controls a { display:inline-flex; align-items:center; padding:4px; color:var(--ovr-on-surface); cursor:pointer; border-radius:4px; }
                    .ovr-adm .ovr-section-controls a:hover { background:var(--gray-light); }
                    .ovr-adm .ovr-section-controls .material-symbols-outlined { font-size:18px; }
                    .ovr-adm .ovr-section-remove { color:var(--red); cursor:pointer; display:inline-flex; align-items:center; gap:4px; font-size:13px; font-weight:600; }
                    .ovr-adm .ovr-section-remove .material-symbols-outlined { font-size:18px; }
                    .ovr-adm .ovr-sections-available { display:flex; flex-wrap:wrap; gap:8px; }
                </style>

                <script>
                    jQuery( function ( $ ) {
                        var list = $( '#ovr-sections-list' );

                        function makeRow( id, name ) {
                            return $( '#ovr-section-row-tpl' ).html()
                                .replace( /{{ID}}/g, String( id ) )
                                .replace( /{{NAME}}/g, name );
                        }

                        // The picker button doubles as the only "add" affordance.
                        $( document ).on( 'click', '.ovr-section-add', function () {
                            var id   = $( this ).data( 'term' );
                            var name = $( this ).data( 'name' );
                            if ( ! id ) { return; }
                            if ( list.length === 0 ) {
                                // First row: create the sortable container.
                                $( '.ovr-adm-card-body' ).find( '#ovr-sections-empty' ).remove();
                                $('<div id="ovr-sections-list" class="ovr-sections-sortable"></div>')
                                    .insertBefore( $('.ovr-sections-available').length ? $('.ovr-sections-available').closest('h2') : $( '.ovr-adm-card-body h2:first' ) );
                                list = $( '#ovr-sections-list' );
                                list.sortable( { handle: '.ovr-section-row' } );
                            }
                            $( list ).append( makeRow( id, name ) );
                            $( this ).remove();
                        } );

                        // Remove re-adds the chip to the available pool and handles the
                        // empty state.
                        $( document ).on( 'click', '.ovr-section-remove', function () {
                            var row    = $( this ).closest( '.ovr-section-row' );
                            var termId = row.find( '.ovr-section-id' ).val();
                            var name   = row.find( '.ovr-section-name' ).text();
                            row.remove();

                            var chip = $( '<button>', {
                                type: 'button',
                                'class': 'ovr-adm-btn ovr-adm-btn--ghost ovr-section-add',
                                'data-term': termId,
                                'data-name': name,
                                html: '<span class="material-symbols-outlined">add_circle</span> ' + name
                            } );
                            $( '.ovr-sections-available' ).prepend( chip );

                            if ( ! list.length ) {
                                $( '.ovr-adm-card-body h2:first' ).after(
                                    '<p id="ovr-sections-empty" class="ovr-adm-muted"><?php echo esc_js( __( 'No sections picked yet. Add a section below.', 'ovr-core' ) ); ?></p>'
                                );
                            }
                        } );

                        // Move a row up/down in the sortable list.
                        $( document ).on( 'click', '[data-ovr-move]', function () {
                            var row = $( this ).closest( '.ovr-section-row' );
                            var dir = $( this ).data( 'ovr-move' );
                            if ( dir === 'up' && row.prev().length ) { row.prev().before( row ); }
                            if ( dir === 'down' && row.next().length ) { row.next().after( row ); }
                        } );

                        if ( list.length ) { list.sortable( { handle: '.ovr-section-row' } ); }
                    } );
                </script>
            </div>
        </div>
        <?php
    }

    private function render_picked_row( int|string $term_id, string $name = '' ): void {
        if ( '' === $name ) {
            $term = get_term( (int) $term_id, 'ovr_village' );
            if ( is_wp_error( $term ) || ! $term ) {
                return;
            }
            $name = $term->name;
            $term_id = (int) $term->term_id;
        } else {
            $name = (string) $name;
        }
        ?>
        <div class="ovr-section-row">
            <span class="material-symbols-outlined" style="color:var(--ovr-outline-variant)">drag_indicator</span>
            <input type="hidden" name="ovr_sections[]" class="ovr-section-id" value="<?php echo esc_attr( (string) $term_id ); ?>">
            <span class="ovr-section-name"><?php echo esc_html( $name ); ?></span>
            <span class="ovr-section-controls">
                <a data-ovr-move="up" title="<?php esc_attr_e( 'Move up', 'ovr-core' ); ?>"><span class="material-symbols-outlined">arrow_upward</span></a>
                <a data-ovr-move="down" title="<?php esc_attr_e( 'Move down', 'ovr-core' ); ?>"><span class="material-symbols-outlined">arrow_downward</span></a>
            </span>
            <a class="ovr-section-remove" role="button"><span class="material-symbols-outlined">delete</span><?php esc_html_e( 'Remove', 'ovr-core' ); ?></a>
        </div>
        <?php
    }
}