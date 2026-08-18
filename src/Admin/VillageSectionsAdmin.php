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

    /**
     * Canonical Village Section slugs (declared in Taxonomies::SECTIONS), in
     * display order. Used to seed the curated sections option on first run.
     *
     * @var string[]
     */
    public const CANONICAL_SECTIONS = [
        'north-of-cr466-spanish-springs-historic-area',
        'north-of-cr466a-sumter-landing',
        'south-of-cr466a-brownwood',
        'south-of-44-east-of-tpke-sawgrass-grove',
        'south-of-cr44-eastport-middleton',
    ];

    private string $hook_suffix = '';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_' . self::SAVE_ACTION, [ $this, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        // Idempotently seed the curated sections so the dynamic homepage
        // Village Sections widget has content on a fresh install.
        add_action( 'init', [ $this, 'maybe_seed_sections_option' ] );
    }

    /**
     * Seed the `ovr_village_sections` option with the canonical Section terms
     * (in their declared order) the first time it is empty. Administrators can
     * reorder / replace them from the Village Sections screen afterwards.
     */
    public function maybe_seed_sections_option(): void {
        if ( false !== get_option( self::OPTION ) ) {
            return;
        }
        $ids = [];
        foreach ( self::CANONICAL_SECTIONS as $slug ) {
            $term = get_term_by( 'slug', $slug, 'ovr_village' );
            if ( $term && ! is_wp_error( $term ) ) {
                $ids[] = (int) $term->term_id;
            }
        }
        if ( ! empty( $ids ) ) {
            update_option( self::OPTION, $ids );
        }
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

        // 1. Create a brand-new section when the admin supplied a name. The new
        //    term joins the enabled list so it immediately appears on the site.
        $clean = [];
        $new_name = trim( sanitize_text_field( wp_unslash( $_POST['ovr_section_new_name'] ?? '' ) ) );
        if ( '' !== $new_name ) {
            $new_desc = sanitize_textarea_field( wp_unslash( $_POST['ovr_section_new_desc'] ?? '' ) );
            $insert   = wp_insert_term( $new_name, 'ovr_village', [ 'description' => $new_desc ] );
            if ( is_wp_error( $insert ) && $insert->get_error_code() === 'term_exists' ) {
                // Name already exists — just enable the existing term.
                $clean[] = (int) $insert->get_error_data();
            } elseif ( ! is_wp_error( $insert ) ) {
                $clean[] = (int) $insert['term_id'];
            }
        }

        // 2. Persist the ordered enabled sections.
        $raw = isset( $_POST['ovr_sections'] ) ? (array) wp_unslash( $_POST['ovr_sections'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        foreach ( $raw as $id ) {
            $id = (int) $id;
            if ( $id > 0 && term_exists( $id, 'ovr_village' ) ) {
                $clean[] = $id;
            }
        }
        $clean = array_values( array_unique( array_map( 'absint', $clean ) ) );

        // 3. Per-section edits: rename, re-describe, or permanently delete.
        $names = isset( $_POST['ovr_section_name'] ) ? (array) wp_unslash( $_POST['ovr_section_name'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $descs = isset( $_POST['ovr_section_desc'] ) ? (array) wp_unslash( $_POST['ovr_section_desc'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $dels  = isset( $_POST['ovr_section_delete'] ) ? (array) wp_unslash( $_POST['ovr_section_delete'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        foreach ( $clean as $i => $id ) {
            $id = (int) $id;

            // Permanently delete the section term (and its image meta).
            if ( ! empty( $dels[ $id ] ) ) {
                wp_delete_term( $id, 'ovr_village' );
                unset( $clean[ $i ] );
                continue;
            }

            $name = isset( $names[ $id ] ) ? sanitize_text_field( wp_unslash( $names[ $id ] ) ) : '';
            if ( '' !== $name ) {
                wp_update_term( $id, 'ovr_village', [
                    'name'        => $name,
                    'description' => isset( $descs[ $id ] ) ? sanitize_textarea_field( wp_unslash( $descs[ $id ] ) ) : '',
                ] );
            }
        }
        $clean = array_values( array_unique( array_filter( array_map( 'absint', $clean ) ) ) );

        // 4. Persist the per-section image each admin assigned via the media
        //    picker (stored as term meta so get_village_image() can resolve it).
        $images = isset( $_POST['ovr_section_image'] ) ? (array) wp_unslash( $_POST['ovr_section_image'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        foreach ( $clean as $id ) {
            $img = isset( $images[ $id ] ) ? (int) $images[ $id ] : 0;
            if ( $img > 0 ) {
                update_term_meta( $id, 'ovr_village_image_id', $img );
            } else {
                delete_term_meta( $id, 'ovr_village_image_id' );
            }
        }

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

                            <h2 class="ovr-adm-h2"><?php esc_html_e( 'Add New Section', 'ovr-core' ); ?></h2>
                            <p class="ovr-adm-muted" style="display:block;margin:0 0 12px"><?php esc_html_e( 'Create a new Village Section. The name is the heading; the description is the location shown right after it (e.g. "Sumter Landing" + "North of CR466A").', 'ovr-core' ); ?></p>
                            <div class="ovr-sections-new">
                                <input type="text" class="regular-text" name="ovr_section_new_name" placeholder="<?php esc_attr_e( 'Section name — e.g. Sumter Landing', 'ovr-core' ); ?>" style="max-width:360px">
                                <input type="text" class="regular-text" name="ovr_section_new_desc" placeholder="<?php esc_attr_e( 'Location — e.g. North of CR466A (shown after the name)', 'ovr-core' ); ?>" style="max-width:460px">
                            </div>

                            <h2 class="ovr-adm-h2" style="margin-top:28px"><?php esc_html_e( 'Enabled sections (display order)', 'ovr-core' ); ?></h2>
                            <?php if ( empty( $enabled ) ) : ?>
                                <p class="ovr-adm-muted"><?php esc_html_e( 'No sections picked yet — add a section below.', 'ovr-core' ); ?></p>
                            <?php else : ?>
                                <div id="ovr-sections-list" class="ovr-sections-sortable">
                                    <?php foreach ( $enabled as $id ) : $this->render_picked_row( $id ); endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $enabled ) ) : ?>
                                <p class="ovr-adm-muted" style="display:block;margin:0 0 12px"><span class="material-symbols-outlined" style="vertical-align:-5px">drag_indicator</span><?php esc_html_e( 'Drag to reorder, use the up/down arrows, or edit a section\'s name and location directly. Tick "Delete" to permanently remove a section.', 'ovr-core' ); ?></p>
                            <?php endif; ?>

                            <h2 class="ovr-adm-h2"><?php esc_html_e( 'Available sections', 'ovr-core' ); ?></h2>
                            <?php if ( empty( $remaining ) ) : ?>
                                <p class="ovr-adm-muted"><?php esc_html_e( 'All sections are already shown.', 'ovr-core' ); ?></p>
                            <?php else : ?>
                                <div class="ovr-sections-available">
                                    <?php foreach ( $remaining as $term ) : ?>
                                        <button type="button" class="ovr-adm-btn ovr-adm-btn--ghost ovr-section-add"
                                                data-term="<?php echo esc_attr( (string) $term->term_id ); ?>"
                                                data-name="<?php echo esc_attr( $term->name ); ?>"
                                                data-desc="<?php echo esc_attr( (string) $term->description ); ?>">
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

                <script id="ovr-section-row-tpl" type="text/template"><div class="ovr-section-row" data-term="{{ID}}"><span class="material-symbols-outlined" style="color:var(--ovr-outline-variant)">drag_indicator</span><input type="hidden" name="ovr_sections[]" class="ovr-section-id" value="{{ID}}"><span class="ovr-section-edit"><input type="text" class="ovr-section-name-input" name="ovr_section_name[{{ID}}]" value="{{NAME}}" placeholder="<?php echo esc_attr( __( 'Section name', 'ovr-core' ) ); ?>"><input type="text" class="ovr-section-desc-input" name="ovr_section_desc[{{ID}}]" value="{{DESC}}" placeholder="<?php echo esc_attr( __( 'Location shown after the name (e.g. North of CR466A)', 'ovr-core' ) ); ?>"></span><span class="ovr-vs-image"><img class="ovr-vs-image-preview" src="" alt="" style="display:none"><input type="hidden" name="ovr_section_image[{{ID}}]" class="ovr-vs-image-id" value="0"><button type="button" class="ovr-adm-btn ovr-adm-btn--ghost ovr-vs-image-pick"><span class="material-symbols-outlined">image</span><?php esc_html_e( 'Image', 'ovr-core' ); ?></button><button type="button" class="ovr-adm-btn ovr-adm-btn--ghost ovr-vs-image-clear" style="display:none"><span class="material-symbols-outlined">close</span></button></span><span class="ovr-section-controls"><a data-ovr-move="up" title="<?php esc_attr_e( 'Move up', 'ovr-core' ); ?>"><span class="material-symbols-outlined">arrow_upward</span></a><a data-ovr-move="down" title="<?php esc_attr_e( 'Move down', 'ovr-core' ); ?>"><span class="material-symbols-outlined">arrow_downward</span></a></span><label class="ovr-section-del"><input type="checkbox" name="ovr_section_delete[{{ID}}]" value="1"> <?php esc_html_e( 'Delete', 'ovr-core' ); ?></label><a class="ovr-section-remove" role="button"><span class="material-symbols-outlined">remove_circle</span><?php esc_html_e( 'Hide', 'ovr-core' ); ?></a></div></script>

                <style>
                    .ovr-adm .ovr-adm-h2 { margin:0 0 12px; font-size:15px; font-weight:700; }
                    .ovr-adm .ovr-adm-muted { color:var(--gray); font-size:13px; display:inline-flex; align-items:center; gap:6px; }
                    .ovr-adm .ovr-sections-sortable { display:flex; flex-direction:column; gap:8px; margin-bottom:8px; }
                    .ovr-adm .ovr-section-row { display:flex; align-items:center; flex-wrap:wrap; gap:10px 12px; padding:10px 12px; background:var(--surf); border:1px solid var(--gray-border); border-radius:var(--r-sm); }
                    .ovr-adm .ovr-section-row.ui-sortable-handle { cursor:grab; }
                    .ovr-adm .ovr-section-row .ovr-section-edit { flex:1 1 220px; display:flex; flex-direction:column; gap:6px; min-width:180px; }
                    .ovr-adm .ovr-section-row .ovr-section-name-input { font-weight:600; border:1px solid var(--gray-border); border-radius:var(--r-sm); padding:7px 10px; width:100%; box-sizing:border-box; }
                    .ovr-adm .ovr-section-row .ovr-section-desc-input { font-size:12.5px; color:var(--gray); border:1px solid var(--gray-border); border-radius:var(--r-sm); padding:6px 10px; width:100%; box-sizing:border-box; }
                    .ovr-adm .ovr-section-row .ovr-section-id { display:none; }
                    .ovr-adm .ovr-section-controls { display:inline-flex; gap:2px; }
                    .ovr-adm .ovr-section-controls a { display:inline-flex; align-items:center; padding:4px; color:var(--ovr-on-surface); cursor:pointer; border-radius:4px; }
                    .ovr-adm .ovr-section-controls a:hover { background:var(--gray-light); }
                    .ovr-adm .ovr-section-controls .material-symbols-outlined { font-size:18px; }
                    .ovr-adm .ovr-section-remove { color:var(--red); cursor:pointer; display:inline-flex; align-items:center; gap:4px; font-size:13px; font-weight:600; }
                    .ovr-adm .ovr-section-remove .material-symbols-outlined { font-size:18px; }
                    .ovr-adm .ovr-section-del { color:var(--red); cursor:pointer; display:inline-flex; align-items:center; gap:5px; font-size:13px; font-weight:600; white-space:nowrap; }
                    .ovr-adm .ovr-sections-available { display:flex; flex-wrap:wrap; gap:8px; }
                    .ovr-adm .ovr-sections-new { display:flex; flex-wrap:wrap; gap:10px; }
                    .ovr-adm .ovr-sections-new input { border:1px solid var(--gray-border); border-radius:var(--r-sm); padding:8px 12px; }
                    .ovr-adm .ovr-vs-image { display:inline-flex; align-items:center; gap:8px; margin:0 8px; }
                    .ovr-adm .ovr-vs-image-preview { width:34px; height:34px; object-fit:cover; border-radius:var(--r-sm); border:1px solid var(--gray-border); }
                    .ovr-adm .ovr-vs-image-clear { padding:2px 6px; color:var(--red); }
                </style>

                <script>
                    jQuery( function ( $ ) {
                        var list = $( '#ovr-sections-list' );

                        function makeRow( id, name, desc ) {
                            return $( '#ovr-section-row-tpl' ).html()
                                .replace( /{{ID}}/g, String( id ) )
                                .replace( /{{NAME}}/g, name )
                                .replace( /{{DESC}}/g, desc || '' );
                        }

                        // The picker button doubles as the only "add" affordance.
                        $( document ).on( 'click', '.ovr-section-add', function () {
                            var id   = $( this ).data( 'term' );
                            var name = $( this ).data( 'name' );
                            var desc = $( this ).data( 'desc' ) || '';
                            if ( ! id ) { return; }
                            if ( list.length === 0 ) {
                                // First row: create the sortable container.
                                $( '.ovr-adm-card-body' ).find( '#ovr-sections-empty' ).remove();
                                $('<div id="ovr-sections-list" class="ovr-sections-sortable"></div>')
                                    .insertBefore( $('.ovr-sections-available').length ? $('.ovr-sections-available').closest('h2') : $( '.ovr-adm-card-body h2:first' ) );
                                list = $( '#ovr-sections-list' );
                                list.sortable( { handle: '.ovr-section-row' } );
                            }
                            $( list ).append( makeRow( id, name, desc ) );
                            $( this ).remove();
                        } );

                        // Remove re-adds the chip to the available pool and handles the
                        // empty state.
                        $( document ).on( 'click', '.ovr-section-remove', function () {
                            var row    = $( this ).closest( '.ovr-section-row' );
                            var termId = row.find( '.ovr-section-id' ).val();
                            var name   = row.find( '.ovr-section-name-input' ).val();
                            var desc   = row.find( '.ovr-section-desc-input' ).val();
                            row.remove();

                            var chip = $( '<button>', {
                                type: 'button',
                                'class': 'ovr-adm-btn ovr-adm-btn--ghost ovr-section-add',
                                'data-term': termId,
                                'data-name': name,
                                'data-desc': desc,
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

                        // Per-section image media picker.
                        var vsFrame;
                        $( document ).on( 'click', '.ovr-vs-image-pick', function ( e ) {
                            e.preventDefault();
                            var row = $( this ).closest( '.ovr-section-row' );
                            if ( ! vsFrame ) {
                                vsFrame = wp.media( {
                                    title: '<?php echo esc_js( __( 'Select Village Image', 'ovr-core' ) ); ?>',
                                    button: { text: '<?php echo esc_js( __( 'Use this image', 'ovr-core' ) ); ?>' },
                                    multiple: false,
                                    library: { type: 'image' }
                                } );
                            }
                            vsFrame.off( 'select' ).on( 'select', function () {
                                var att = vsFrame.state().get( 'selection' ).first().toJSON();
                                row.find( '.ovr-vs-image-id' ).val( att.id );
                                row.find( '.ovr-vs-image-preview' ).attr( 'src', att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url ).show();
                                row.find( '.ovr-vs-image-clear' ).show();
                            } );
                            vsFrame.open();
                        } );

                        $( document ).on( 'click', '.ovr-vs-image-clear', function ( e ) {
                            e.preventDefault();
                            var row = $( this ).closest( '.ovr-section-row' );
                            row.find( '.ovr-vs-image-id' ).val( 0 );
                            row.find( '.ovr-vs-image-preview' ).attr( 'src', '' ).hide();
                            $( this ).hide();
                        } );
                    } );
                </script>
            </div>
        </div>
        <?php
    }

    private function render_picked_row( int|string $term_id, string $name = '', string $description = '' ): void {
        if ( '' === $name ) {
            $term = get_term( (int) $term_id, 'ovr_village' );
            if ( is_wp_error( $term ) || ! $term ) {
                return;
            }
            $name        = $term->name;
            $description = (string) $term->description;
            $term_id     = (int) $term->term_id;
        } else {
            $name        = (string) $name;
            $description = (string) $description;
        }
        $image_id = (int) get_term_meta( (int) $term_id, 'ovr_village_image_id', true );
        $preview  = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
        ?>
        <div class="ovr-section-row" data-term="<?php echo esc_attr( (string) $term_id ); ?>">
            <span class="material-symbols-outlined" style="color:var(--ovr-outline-variant)">drag_indicator</span>
            <input type="hidden" name="ovr_sections[]" class="ovr-section-id" value="<?php echo esc_attr( (string) $term_id ); ?>">
            <span class="ovr-section-edit">
                <input type="text" class="ovr-section-name-input" name="ovr_section_name[<?php echo esc_attr( (string) $term_id ); ?>]"
                       value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Section name', 'ovr-core' ); ?>">
                <input type="text" class="ovr-section-desc-input" name="ovr_section_desc[<?php echo esc_attr( (string) $term_id ); ?>]"
                       value="<?php echo esc_attr( $description ); ?>" placeholder="<?php esc_attr_e( 'Location shown after the name (e.g. North of CR466A)', 'ovr-core' ); ?>">
            </span>
            <span class="ovr-vs-image">
                <img class="ovr-vs-image-preview" src="<?php echo esc_url( $preview ); ?>" alt="" style="<?php echo $preview ? '' : 'display:none'; ?>">
                <input type="hidden" name="ovr_section_image[<?php echo esc_attr( (string) $term_id ); ?>]" class="ovr-vs-image-id" value="<?php echo esc_attr( (string) $image_id ); ?>">
                <button type="button" class="ovr-adm-btn ovr-adm-btn--ghost ovr-vs-image-pick"><span class="material-symbols-outlined">image</span><?php esc_html_e( 'Image', 'ovr-core' ); ?></button>
                <button type="button" class="ovr-adm-btn ovr-adm-btn--ghost ovr-vs-image-clear" style="<?php echo $preview ? '' : 'display:none'; ?>"><span class="material-symbols-outlined">close</span></button>
            </span>
            <span class="ovr-section-controls">
                <a data-ovr-move="up" title="<?php esc_attr_e( 'Move up', 'ovr-core' ); ?>"><span class="material-symbols-outlined">arrow_upward</span></a>
                <a data-ovr-move="down" title="<?php esc_attr_e( 'Move down', 'ovr-core' ); ?>"><span class="material-symbols-outlined">arrow_downward</span></a>
            </span>
            <label class="ovr-section-del" title="<?php esc_attr_e( 'Permanently delete this section', 'ovr-core' ); ?>">
                <input type="checkbox" name="ovr_section_delete[<?php echo esc_attr( (string) $term_id ); ?>]" value="1"> <?php esc_html_e( 'Delete', 'ovr-core' ); ?>
            </label>
            <a class="ovr-section-remove" role="button" title="<?php esc_attr_e( 'Stop showing this section (keep the term)', 'ovr-core' ); ?>"><span class="material-symbols-outlined">remove_circle</span><?php esc_html_e( 'Hide', 'ovr-core' ); ?></a>
        </div>
        <?php
    }
}