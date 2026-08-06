<?php

namespace OVR\Admin;

use OVR\Core\AuditLog;
use OVR\Subscription\UpgradeActivator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PropertyListScreen {

    public const PAGE_SLUG = 'ovr-properties';
    public const PT        = 'ovr_property';
    public const PER_PAGE  = 25;

    private ?FilterTable $filter_table = null;
    private string $title_search = '';

    public function init(): void {
        // All of the setup below builds translated labels/filters via __(). That
        // must not run before `init` (WP 6.7+ flags just-in-time textdomain
        // loading). Plugin::init() fires on plugins_loaded, so defer to `init`.
        // Every hook registered in setup() (admin_menu, admin_init,
        // admin_enqueue_scripts, wp_ajax_*) fires after `init`, so this is safe.
        add_action( 'init', [ $this, 'setup' ], 9 );
    }

    public function setup(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_menu', [ $this, 'remove_native_submenu' ], 20 );
        add_action( 'admin_init', [ $this, 'redirect_native_list' ] );
        add_action( 'admin_init', [ $this, 'handle_csv_export' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

        $this->filter_table = new FilterTable( self::PAGE_SLUG );
        $this->filter_table->set_columns( $this->get_column_labels() );
        $this->filter_table->set_sortable( [ 'pid', 'display_status', 'property_type', 'address', 'village', 'owner_email', 'property_name', 'last_updated', 'paid_services', 'views' ] );
        $this->filter_table->set_bulk_actions( [
            'activate'   => __( 'Activate', 'ovr-core' ),
            'deactivate' => __( 'Deactivate', 'ovr-core' ),
            'approve'    => __( 'Approve', 'ovr-core' ),
            'hide'       => __( 'Hide', 'ovr-core' ),
            'delete'     => __( 'Move to Trash', 'ovr-core' ),
        ] );
        $this->filter_table->set_labels( __( 'Property', 'ovr-core' ), __( 'Properties', 'ovr-core' ) );
        $this->filter_table->set_query_callback( [ $this, 'build_custom_query' ] );
        $this->filter_table->set_render_cell_callback( [ $this, 'render_table_cell' ] );
        // ?author=<id> scopes the screen to one owner ("View Listings" on the
        // Users screen) and must survive the table's AJAX refresh.
        $this->filter_table->set_context_params( [ 'author' ] );
        $this->filter_table->init();

        $engine = $this->filter_table->get_engine();

        $engine->add_column_filter( 'display_status', [
            'type'     => 'dropdown',
            'label'    => __( 'Display Status', 'ovr-core' ),
            'options'  => [
                'approved' => __( 'ACTIVE', 'ovr-core' ),
                'hidden'   => __( 'HIDDEN', 'ovr-core' ),
                'deleted'  => __( 'SOFT DELETED', 'ovr-core' ),
            ],
            'meta_key' => '_ovr_admin_status',
        ] );

        $engine->add_column_filter( 'property_type', [
            'type'   => 'dropdown',
            'label'  => __( 'Type', 'ovr-core' ),
            'source' => 'taxonomy:ovr_property_type',
        ] );

        $engine->add_column_filter( 'address', [
            'type'        => 'text',
            'label'       => __( 'Address', 'ovr-core' ),
            'placeholder' => __( 'Search address…', 'ovr-core' ),
            'meta_keys'   => [ '_ovr_address', '_ovr_city' ],
        ] );

        $engine->add_column_filter( 'village', [
            'type'   => 'dropdown',
            'label'  => __( 'Village Section', 'ovr-core' ),
            'source' => 'taxonomy:ovr_village',
        ] );

        $engine->add_column_filter( 'village_of', [
            'type'        => 'text',
            'label'       => __( 'Village Of', 'ovr-core' ),
            'placeholder' => __( 'Search village…', 'ovr-core' ),
            'meta_keys'   => [ '_ovr_village_name' ],
        ] );

        $engine->add_column_filter( 'owner_email', [
            'type'        => 'text',
            'label'       => __( 'Owner Email', 'ovr-core' ),
            'placeholder' => __( 'Search email…', 'ovr-core' ),
            'meta_keys'   => [ '_ovr_owner_email' ],
        ] );

        $engine->add_column_filter( 'property_name', [
            'type'        => 'text',
            'label'       => __( 'Property Name', 'ovr-core' ),
            'placeholder' => __( 'Search name…', 'ovr-core' ),
            'meta_keys'   => [ '_ovr_property_name' ],
        ] );

        $engine->add_column_filter( 'last_updated', [
            'type'   => 'date',
            'label'  => __( 'Last Updated', 'ovr-core' ),
            'single' => true,             // one date picker, not a from/to range.
            'column' => 'post_modified',  // filter on the modified date, not published.
        ] );

        $engine->add_column_filter( 'paid_services', [
            'type'   => 'dropdown',
            'label'  => __( 'Paid Services', 'ovr-core' ),
            'source' => 'table:ovr_paid_services|id|name',
        ] );

        $engine->add_column_filter( 'pid', [
            'type'        => 'text',
            'label'       => __( 'Property ID', 'ovr-core' ),
            'placeholder' => __( 'Search ID…', 'ovr-core' ),
            'meta_keys'   => [ '_ovr_pid_search' ],
        ] );

        add_action( 'wp_ajax_ovr_admin_add_listing_service',  [ $this, 'ajax_add_service' ] );
        add_action( 'wp_ajax_ovr_admin_remove_listing_service', [ $this, 'ajax_remove_service' ] );
        add_action( 'wp_ajax_ovr_admin_get_services',  [ $this, 'ajax_get_services' ] );
        add_action( 'wp_ajax_ovr_admin_bulk_action',    [ $this, 'ajax_bulk_action' ] );
        add_action( 'wp_ajax_ovr_admin_duplicate_property', [ $this, 'ajax_duplicate_property' ] );
        add_action( 'wp_ajax_ovr_admin_restore_property', [ $this, 'ajax_restore_property' ] );
        add_action( 'wp_ajax_ovr_admin_perma_delete_property', [ $this, 'ajax_perma_delete_property' ] );
        // P8 §8 — Admin tab: owner reassignment + user search.
        add_action( 'wp_ajax_ovr_admin_search_users',      [ $this, 'ajax_search_users' ] );
        add_action( 'wp_ajax_ovr_admin_reassign_listing',  [ $this, 'ajax_reassign_listing' ] );

        add_action( 'ovr_service_expiry', [ self::class, 'expire_services' ] );
        if ( ! wp_next_scheduled( 'ovr_service_expiry' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ovr_service_expiry' );
        }
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . self::PT,
            __( 'All Properties', 'ovr-core' ),
            __( 'All Properties', 'ovr-core' ),
            'read',
            self::PAGE_SLUG,
            [ $this, 'render' ],
            1 // Second item, directly below "Overview" (which is position 0).
        );
    }

    public function remove_native_submenu(): void {
        remove_submenu_page( 'edit.php?post_type=' . self::PT, 'edit.php?post_type=' . self::PT );
    }

    public function redirect_native_list(): void {
        global $pagenow;
        if ( 'edit.php' !== $pagenow ) {
            return;
        }
        $pt = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
        if ( self::PT !== $pt ) {
            return;
        }
        $params = $_GET;
        unset( $params['post_type'] );
        $url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        if ( $params ) {
            $url = add_query_arg( $params, $url );
        }
        wp_safe_redirect( $url );
        exit;
    }

    public function handle_csv_export(): void {
        if ( ! isset( $_GET['export_csv'] ) || ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        // The export previously ran its own unfiltered get_posts(), so an admin who
        // had narrowed the table down and clicked Export silently received EVERY
        // listing instead of the rows on screen. Reuse the same filtered query the
        // table itself uses (per_page -1 = all matches), so the file always mirrors
        // the current filter state.
        $ids = [];
        if ( $this->filter_table instanceof FilterTable ) {
            $query = $this->build_custom_query(
                $this->filter_table->get_current_filters(),
                1,
                $this->filter_table->get_current_orderby(),
                $this->filter_table->get_current_order(),
                $this->filter_table->get_current_search(),
                $this->filter_table,
                -1
            );
            $ids = array_map(
                static fn( $p ) => is_object( $p ) ? (int) $p->ID : (int) $p,
                $query->posts ?? []
            );
        } else {
            // Defensive fallback — should not happen, the table is built in init().
            $args = [
                'post_type'      => self::PT,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ];
            if ( ! current_user_can( 'manage_options' ) ) {
                $args['author'] = get_current_user_id();
            }
            $ids = get_posts( $args );
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="ovr-properties-' . date( 'Y-m-d' ) . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        // UTF-8 BOM so Excel reads accented data correctly (P6.2 encoding).
        fwrite( $output, "\xEF\xBB\xBF" );
        fputcsv( $output, [
            'Property ID', 'Display Status', 'Price', 'Property Type', 'Address',
            'Village', 'Owner Email', 'Owner Subscription', 'Property Name', 'Last Updated', 'Paid Services', 'Views',
        ] );

        foreach ( $ids as $pid ) {
            $post = get_post( $pid );
            if ( ! $post ) continue;
            $types = wp_get_object_terms( $pid, 'ovr_property_type', [ 'fields' => 'names' ] );
            $villages = wp_get_object_terms( $pid, 'ovr_village', [ 'fields' => 'names' ] );
            $owner = get_userdata( (int) $post->post_author );
            $services = $this->listing_services( $pid );
            $svc_names = array_column( $services, 'service_name' );

            // Owner's subscription plan name (P6.2 requires a Subscription column).
            $owner_plan = '';
            if ( $owner ) {
                $plan       = \OVR\Subscription\UserSubscription::get_plan( (int) $post->post_author );
                $owner_plan = $plan['name'] ?? \OVR\Subscription\UserSubscription::get_plan_slug( (int) $post->post_author );
            }

            fputcsv( $output, self::csv_safe_row( [
                $pid,
                get_post_meta( $pid, '_ovr_admin_status', true ) ?: 'approved',
                get_post_meta( $pid, '_ovr_base_price', true ),
                ! is_wp_error( $types ) && $types ? $types[0] : '',
                get_post_meta( $pid, '_ovr_address', true ),
                ! is_wp_error( $villages ) && $villages ? $villages[0] : '',
                $owner ? $owner->user_email : '',
                $owner_plan,
                get_the_title( $pid ),
                $post->post_modified,
                implode( ', ', $svc_names ),
                (int) get_post_meta( $pid, '_ovr_view_count', true ),
            ] ) );
        }

        fclose( $output );
        exit;
    }

    /**
     * Neutralise CSV/formula injection: prefix a cell that starts with a
     * spreadsheet formula trigger (= + - @, tab, CR) with an apostrophe so it
     * renders as literal text in Excel/Sheets. fputcsv handles the rest.
     *
     * @param array<int, int|string> $row
     * @return array<int, int|string>
     */
    private static function csv_safe_row( array $row ): array {
        return array_map( static function ( $v ) {
            $s = (string) $v;
            return ( '' !== $s && strpbrk( $s[0], "=+-@\t\r" ) !== false ) ? "'" . $s : $v;
        }, $row );
    }

    public function enqueue( string $hook ): void {
        if ( ( $_GET['page'] ?? '' ) !== self::PAGE_SLUG ) {
            return;
        }
        wp_enqueue_style(
            'ovr-pls',
            OVR_PLUGIN_URL . 'assets/css/ovr-pls.css',
            [ 'ovr-filter-table' ],
            filemtime( OVR_PLUGIN_DIR . 'assets/css/ovr-pls.css' )
        );
        wp_enqueue_script(
            'ovr-pls',
            OVR_PLUGIN_URL . 'assets/js/ovr-pls.js',
            [ 'ovr-filter-table' ],
            filemtime( OVR_PLUGIN_DIR . 'assets/js/ovr-pls.js' ),
            true
        );
        wp_localize_script( 'ovr-pls', 'ovrPls', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ovr_admin_nonce' ),
            'today'   => current_time( 'Y-m-d' ),
        ] );
        if ( $this->filter_table ) {
            $this->filter_table->enqueue_assets( $hook );
        }
    }

    public function render(): void {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You do not have permission to view properties.', 'ovr-core' ) );
        }

        $is_admin     = current_user_can( 'manage_options' );
        $current_user = wp_get_current_user();
        $stats        = $this->get_stats( $is_admin ? 0 : $current_user->ID );
        $service_types = $this->get_service_types();

        echo '<div class="wrap ovr-pls-page" id="ovr-filter-table-wrap">';

        $this->render_header();
        $this->render_stats_bar( $stats );
        $this->render_toolbar( $service_types );

        if ( $this->filter_table ) {
            $this->filter_table->render();
        }

        $this->render_service_modal( $service_types );
        echo '</div>';
    }

    private function render_header(): void {
        ?>
        <div class="ovr-pls-header">
            <div>
                <h1 class="wp-heading-inline"><?php esc_html_e( 'All Properties', 'ovr-core' ); ?></h1>
                <span class="ovr-pls-count" id="ovr-pls-count"></span>
            </div>
            <div class="ovr-pls-header-actions">
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::PT ) ); ?>" class="button ovr-pls-btn-primary">
                    <span class="material-symbols-outlined">add</span>
                    <?php esc_html_e( 'Add Listing', 'ovr-core' ); ?>
                </a>
            </div>
        </div>
        <hr class="wp-header-end">
        <?php
    }

    private function render_toolbar( array $service_types ): void {
        $subscription_plans = [];
        if ( function_exists( 'ovr_subscription_plans' ) ) {
            $subscription_plans = ovr_subscription_plans();
        }
        ?>
        <div class="ovr-pls-toolbar">
            <div class="ovr-pls-toolbar-left">
                <div class="ovr-pls-toolbar-actions">
                    <div class="ovr-table-search">
                        <span class="material-symbols-outlined">search</span>
                        <input type="search" name="s" placeholder="<?php esc_attr_e( 'Search properties…', 'ovr-core' ); ?>" value="<?php echo esc_attr( $this->filter_table->get_current_search() ); ?>" aria-label="<?php esc_attr_e( 'Search', 'ovr-core' ); ?>">
                    </div>
                    <select name="action" id="ovr-pls-bulk-action">
                        <option value=""><?php esc_html_e( 'Bulk Actions', 'ovr-core' ); ?></option>
                        <?php foreach ( $this->filter_table->get_bulk_actions() as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" id="ovr-pls-bulk-apply"><?php esc_html_e( 'Apply', 'ovr-core' ); ?></button>
                </div>
            </div>
            <div class="ovr-pls-toolbar-right">
                <?php if ( current_user_can( 'manage_options' ) && ! empty( $subscription_plans ) ) : ?>
                <select id="ovr-pls-filter-subscription" class="ovr-pls-toolbar-filter">
                    <option value=""><?php esc_html_e( 'All Subscriptions', 'ovr-core' ); ?></option>
                    <?php foreach ( $subscription_plans as $slug => $plan ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $plan['name'] ?? $slug ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <a href="<?php echo esc_url( add_query_arg( 'export_csv', '1' ) ); ?>" class="button ovr-pls-btn-export">
                    <span class="material-symbols-outlined">download</span>
                    <?php esc_html_e( 'Export CSV', 'ovr-core' ); ?>
                </a>
                <button type="button" class="button ovr-pls-btn-ghost" id="ovr-pls-reset-filters">
                    <span class="material-symbols-outlined">filter_alt_off</span>
                    <?php esc_html_e( 'Reset', 'ovr-core' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    private function heading( string $text ): void {
        echo '<h2 class="ovr-pls-heading">' . esc_html( $text ) . '</h2>';
    }

    public function get_column_labels(): array {
        return [
            'cb'             => '<input type="checkbox" id="ovr-pls-cb-all">',
            'pid'            => __( 'Property ID', 'ovr-core' ),
            'property_name'  => __( 'Property Name', 'ovr-core' ),
            'display_status' => __( 'Display Status', 'ovr-core' ),
            'property_type'  => __( 'Property Type', 'ovr-core' ),
            'address'        => __( 'Address', 'ovr-core' ),
            'village'        => __( 'Village Section', 'ovr-core' ),
            'village_of'     => __( 'Village Of', 'ovr-core' ),
            'owner_email'    => __( 'Owner Email', 'ovr-core' ),
            'last_updated'   => __( 'Last Updated', 'ovr-core' ),
            'paid_services'  => __( 'Paid Services', 'ovr-core' ),
            'views'          => __( 'Views', 'ovr-core' ),
            'actions'        => __( 'Actions', 'ovr-core' ),
        ];
    }

    /**
     * @param int $per_page Rows per page; pass -1 to return every match (used by
     *                      the CSV export so it honours the active filters).
     */
    public function build_custom_query( array $filters, int $page, string $orderby, string $order, string $search, FilterTable $table, int $per_page = self::PER_PAGE ): \WP_Query {
        $author_id = current_user_can( 'manage_options' ) ? 0 : get_current_user_id();

        $orderby_map = [
            'pid'            => 'ID',
            'display_status' => 'meta_value',
            'price'          => 'meta_value_num',
            'property_type'  => 'title',
            'address'        => 'meta_value',
            'village'        => 'title',
            'owner_email'    => 'author',
            'property_name'  => 'title',
            'last_updated'   => 'modified',
            'paid_services'  => 'meta_value',
            'views'          => 'meta_value_num',
        ];
        $wp_orderby = $orderby_map[ $orderby ] ?? 'ID';

        $args = [
            'post_type'      => self::PT,
            'post_status'    => 'any',
            'paged'          => $page,
            'posts_per_page' => $per_page,
            'orderby'        => $wp_orderby,
            'order'          => $order,
        ];

        if ( 'price' === $orderby ) {
            $args['meta_key'] = '_ovr_base_price';
        } elseif ( 'views' === $orderby ) {
            $args['meta_key'] = '_ovr_view_count';
        } elseif ( 'display_status' === $orderby ) {
            $args['meta_key'] = '_ovr_admin_status';
        } elseif ( 'address' === $orderby ) {
            $args['meta_key'] = '_ovr_address';
        } elseif ( 'paid_services' === $orderby ) {
            $args['meta_key'] = '_ovr_has_paid_services';
        }

        if ( $author_id ) {
            $args['author'] = $author_id;
        }

        // P6.4/P8: "View Listings" from the Users screen passes ?author=<id> to
        // show only that owner's properties (admins only; non-admins are already
        // scoped to themselves above). Read from $_REQUEST, not $_GET: the table
        // refreshes over admin-ajax POST, where a $_GET-only read would drop the
        // scope and re-render every listing.
        if ( ! $author_id && current_user_can( 'manage_options' ) && ! empty( $_REQUEST['author'] ) ) {
            $args['author'] = (int) $_REQUEST['author'];
        }

        // ──────────────────────────────────────────────────────────────
        //  Special column filters that don't map to a plain meta_query.
        //  These are resolved to WP_Query args here and then removed from
        //  $filters so the generic engine doesn't apply a (wrong) meta query
        //  for them. post__in / author__in constraints are intersected so
        //  multiple active filters combine with AND semantics.
        // ──────────────────────────────────────────────────────────────
        $post_in_sets   = [];
        $author_in_sets = [];

        // Subscription plan (admin toolbar) → author set.
        $subscription = $filters['subscription'] ?? '';
        if ( '' !== $subscription ) {
            $sub_users = get_users( [
                'meta_key'   => 'ovr_subscription_plan',
                'meta_value' => $subscription,
                'fields'     => 'ID',
                'number'     => 1000,
            ] );
            $author_in_sets[] = ! empty( $sub_users ) ? array_map( 'intval', $sub_users ) : [ 0 ];
        }

        // Owner Email (column) → author set (match against the user table).
        $email_term = trim( (string) ( $filters['owner_email'] ?? '' ) );
        if ( '' !== $email_term ) {
            $owner_ids = get_users( [
                'search'         => '*' . $email_term . '*',
                'search_columns' => [ 'user_email' ],
                'fields'         => 'ID',
                'number'         => 500,
            ] );
            $author_in_sets[] = ! empty( $owner_ids ) ? array_map( 'intval', $owner_ids ) : [ 0 ];
        }
        unset( $filters['owner_email'] );

        // Paid Services: toolbar (paid_service) + column (paid_services) both
        // resolve through the listing↔service assignment table.
        $paid_service = absint( $filters['paid_service'] ?? 0 );
        if ( $paid_service ) {
            $ids = $this->listing_ids_with_service( $paid_service );
            $post_in_sets[] = ! empty( $ids ) ? $ids : [ 0 ];
        }
        $paid_service_col = absint( $filters['paid_services'] ?? 0 );
        if ( $paid_service_col ) {
            $ids = $this->listing_ids_with_service( $paid_service_col );
            $post_in_sets[] = ! empty( $ids ) ? $ids : [ 0 ];
        }
        unset( $filters['paid_services'] );

        // Property ID (column) → match post IDs (partial allowed, e.g. "38").
        $pid_term = trim( (string) ( $filters['pid'] ?? '' ) );
        if ( '' !== $pid_term ) {
            $ids = $this->listing_ids_matching_id( $pid_term );
            $post_in_sets[] = ! empty( $ids ) ? $ids : [ 0 ];
        }
        unset( $filters['pid'] );

        // Property Name (column) → post_title LIKE (added via posts_where).
        $this->title_search = trim( (string) ( $filters['property_name'] ?? '' ) );
        unset( $filters['property_name'] );

        // Display Status (column): "approved" must also include listings that
        // have no _ovr_admin_status meta yet (they default to approved).
        $disp = (string) ( $filters['display_status'] ?? '' );
        unset( $filters['display_status'] );

        // Soft-deleted = trashed post. Scoping the base query to trash gives a
        // clean "recycle bin" view on the same screen.
        if ( 'deleted' === $disp ) {
            $args['post_status'] = 'trash';
        }

        // Intersect the collected constraint sets.
        if ( ! empty( $author_in_sets ) ) {
            $inter = array_shift( $author_in_sets );
            foreach ( $author_in_sets as $set ) {
                $inter = array_intersect( $inter, $set );
            }
            $args['author__in'] = ! empty( $inter ) ? array_values( $inter ) : [ 0 ];
            unset( $args['author'] ); // author + author__in conflict; author__in already scopes.
        }
        if ( ! empty( $post_in_sets ) ) {
            $inter = array_shift( $post_in_sets );
            foreach ( $post_in_sets as $set ) {
                $inter = array_intersect( $inter, $set );
            }
            $args['post__in'] = ! empty( $inter ) ? array_values( $inter ) : [ 0 ];
        }

        if ( '' !== $search ) {
            $args['s'] = $search;
            add_filter( 'posts_search', [ $this, 'extend_search' ], 10, 2 );
        }

        $qb = QueryBuilder::for_posts( $args );

        if ( 'approved' === $disp ) {
            $qb->add_meta_query( [
                'relation' => 'OR',
                [ 'key' => '_ovr_admin_status', 'value' => 'approved' ],
                [ 'key' => '_ovr_admin_status', 'compare' => 'NOT EXISTS' ],
            ] );
        } elseif ( 'hidden' === $disp ) {
            $qb->add_meta_query( [ 'key' => '_ovr_admin_status', 'value' => 'hidden' ] );
        }

        $table->get_engine()->apply_filters( $filters, $qb );

        if ( '' !== $this->title_search ) {
            add_filter( 'posts_where', [ $this, 'filter_title_where' ], 10, 2 );
        }

        $query = $qb->run();

        if ( '' !== $this->title_search ) {
            remove_filter( 'posts_where', [ $this, 'filter_title_where' ], 10 );
            $this->title_search = '';
        }

        if ( '' !== $search ) {
            remove_filter( 'posts_search', [ $this, 'extend_search' ], 10 );
        }

        return $query;
    }

    /** Match listing post IDs by (partial) numeric ID. */
    private function listing_ids_matching_id( string $term ): array {
        global $wpdb;
        $digits = preg_replace( '/\D+/', '', $term );
        if ( '' === $digits ) {
            return [];
        }
        $like = '%' . $wpdb->esc_like( $digits ) . '%';
        $ids  = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND CAST(ID AS CHAR) LIKE %s",
            self::PT,
            $like
        ) );
        return array_map( 'absint', $ids );
    }

    /** posts_where callback: restrict to titles matching the property-name filter. */
    public function filter_title_where( string $where, \WP_Query $query ): string {
        global $wpdb;
        $term = $this->title_search;
        if ( '' === $term ) {
            return $where;
        }
        $where .= $wpdb->prepare(
            " AND {$wpdb->posts}.post_title LIKE %s ",
            '%' . $wpdb->esc_like( $term ) . '%'
        );
        return $where;
    }

    public function extend_search( string $search, \WP_Query $query ): string {
        global $wpdb;
        $term = trim( (string) $query->get( 's' ) );
        if ( '' === $term ) {
            return $search;
        }

        if ( is_numeric( $term ) ) {
            $id_like = '%' . $wpdb->esc_like( $term ) . '%';
            return $wpdb->prepare(
                " AND ({$wpdb->posts}.ID LIKE %s OR {$wpdb->posts}.post_title LIKE %s) ",
                $id_like,
                '%' . $wpdb->esc_like( $term ) . '%'
            );
        }

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

        $in     = implode( ',', array_map( 'absint', $ids ) );
        $search = preg_replace(
            '/^\s*AND\s*\((.*)\)\s*$/s',
            " AND (($1) OR {$wpdb->posts}.ID IN ({$in}))",
            $search
        );
        return $search;
    }

    private function listing_ids_with_service( int $service_id ): array {
        global $wpdb;
        $rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT listing_id FROM {$wpdb->prefix}ovr_listing_services
             WHERE service_id = %d AND active = 1 AND (end_date IS NULL OR end_date >= %s)",
            $service_id,
            current_time( 'Y-m-d' )
        ) );
        return array_map( 'absint', $rows );
    }

    private function get_stats( int $author_id = 0 ): array {
        $author_q = $author_id ? [ 'author' => $author_id ] : [];

        $all   = (int) wp_count_posts( self::PT )->publish;
        $draft = (int) wp_count_posts( self::PT )->draft;
        $total = $author_id ? $this->count_author_posts( $author_id ) : $all + $draft;

        $active_q   = new \WP_Query( array_merge( $author_q, [
            'post_type'   => self::PT,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'posts_per_page' => -1,
            'meta_query'  => [ [ 'key' => '_ovr_listing_status', 'value' => 'active' ] ],
            'no_found_rows' => true,
        ] ) );
        $active = (int) $active_q->post_count;

        $inactive = $total - $active;

        $featured_q = new \WP_Query( array_merge( $author_q, [
            'post_type'   => self::PT,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'posts_per_page' => -1,
            'meta_query'  => [ [ 'key' => '_ovr_is_featured', 'value' => '1' ] ],
            'no_found_rows' => true,
        ] ) );
        $featured = (int) $featured_q->post_count;

        global $wpdb;
        $paid = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT listing_id) FROM {$wpdb->prefix}ovr_listing_services WHERE active = 1"
        );

        return compact( 'total', 'active', 'inactive', 'featured', 'paid' );
    }

    private function count_author_posts( int $author_id ): int {
        $q = new \WP_Query( [
            'author'         => $author_id,
            'post_type'      => self::PT,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ] );
        return (int) $q->post_count;
    }

    private function get_service_types(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT id, name, slug, description, duration_days, badge, service_type
             FROM {$wpdb->prefix}ovr_paid_services
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC",
            ARRAY_A
        );
        return $rows ?: [];
    }

    private function listing_services( int $listing_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ls.*, ps.name AS service_name, ps.badge, ps.slug AS service_slug
             FROM {$wpdb->prefix}ovr_listing_services ls
             LEFT JOIN {$wpdb->prefix}ovr_paid_services ps ON ls.service_id = ps.id
             WHERE ls.listing_id = %d AND ps.deleted_at IS NULL
             ORDER BY ls.active DESC, ls.end_date ASC",
            $listing_id
        ), ARRAY_A );
        return $rows ?: [];
    }

    public function render_table_cell( string $key, $item ): void {
        $pid = (int) $item->ID;

        switch ( $key ) {
            case 'cb':
                echo '<input type="checkbox" class="ovr-pls-cb" value="' . $pid . '">';
                break;
            case 'pid':
                $this->render_pid_cell( $pid );
                break;
            case 'display_status':
                $this->render_display_status_cell( $pid );
                break;
            case 'price':
                $this->render_price_cell( $pid );
                break;
            case 'property_type':
                $this->render_property_type_cell( $pid );
                break;
            case 'address':
                $this->render_address_cell( $pid );
                break;
            case 'village':
                $this->render_village_cell( $pid );
                break;
            case 'village_of':
                $this->render_village_of_cell( $pid );
                break;
            case 'owner_email':
                $this->render_owner_email_cell( $item );
                break;
            case 'property_name':
                $this->render_property_name_cell( $pid );
                break;
            case 'last_updated':
                $this->render_last_updated_cell( $item );
                break;
            case 'paid_services':
                $this->render_paid_services_cell( $pid );
                break;
            case 'views':
                $this->render_views_cell( $pid );
                break;
            case 'actions':
                $this->render_actions_cell( $pid );
                break;
            default:
                echo esc_html( $item->$key ?? $item->post_title ?? '' );
                break;
        }
    }

    private function render_pid_cell( int $pid ): void {
        $edit_url = admin_url( 'admin.php?page=ovr-edit-listing&post=' . $pid );
        printf(
            '<a href="%s" class="ovr-pls-pid">%d</a>
             <button type="button" class="ovr-pls-copy-id" data-clipboard="%d" title="%s">
                 <span class="material-symbols-outlined">content_copy</span>
             </button>',
            esc_url( $edit_url ),
            $pid,
            $pid,
            esc_attr__( 'Copy ID', 'ovr-core' )
        );
    }

    private function render_display_status_cell( int $pid ): void {
        if ( 'trash' === get_post_status( $pid ) ) {
            $by    = get_post_meta( $pid, '_ovr_deleted_by', true );
            $title = 'owner' === $by
                ? __( 'Deleted by Landlord — restore to bring it back', 'ovr-core' )
                : __( 'Soft deleted by administrator', 'ovr-core' );
            printf(
                '<span class="ovr-pls-status ovr-pls-status--deleted" title="%s">%s</span>',
                esc_attr( $title ),
                esc_html__( 'SOFT DELETED', 'ovr-core' )
            );
            return;
        }
        $status = get_post_meta( $pid, '_ovr_admin_status', true ) ?: 'approved';
        if ( 'approved' === $status ) {
            echo '<span class="ovr-pls-status ovr-pls-status--active">' . esc_html__( 'ACTIVE', 'ovr-core' ) . '</span>';
        } else {
            echo '<span class="ovr-pls-status ovr-pls-status--hidden">' . esc_html__( 'HIDDEN', 'ovr-core' ) . '</span>';
        }
    }

    private function render_price_cell( int $pid ): void {
        $raw = get_post_meta( $pid, '_ovr_base_price', true );
        if ( ! $raw ) {
            echo '<span class="ovr-pls-na">&mdash;</span>';
            return;
        }
        $settings = get_option( 'ovr_settings', [] );
        $symbol   = $settings['currency_symbol'] ?? '$';
        $val      = (float) $raw;
        if ( $val <= 0 ) {
            echo '<span class="ovr-pls-na">&mdash;</span>';
            return;
        }
        echo '<span class="ovr-pls-price">' . esc_html( $symbol . number_format( $val ) ) . '</span>';
    }

    private function render_property_type_cell( int $pid ): void {
        $types = wp_get_object_terms( $pid, 'ovr_property_type', [ 'fields' => 'names' ] );
        if ( is_wp_error( $types ) || empty( $types ) ) {
            echo '<span class="ovr-pls-na">&mdash;</span>';
            return;
        }
        echo '<span class="ovr-pls-type">' . esc_html( $types[0] ) . '</span>';
    }

    private function render_address_cell( int $pid ): void {
        $address = (string) get_post_meta( $pid, '_ovr_address', true );
        $city    = (string) get_post_meta( $pid, '_ovr_city', true );
        $display = $address ? $address : ( $city ?: '—' );
        echo '<span class="ovr-pls-address">' . esc_html( $display ) . '</span>';
    }

    private function render_village_cell( int $pid ): void {
        $villages = wp_get_object_terms( $pid, 'ovr_village', [ 'fields' => 'names' ] );
        if ( is_wp_error( $villages ) || empty( $villages ) ) {
            echo '<span class="ovr-pls-na">&mdash;</span>';
            return;
        }
        echo '<span class="ovr-pls-village">' . esc_html( $villages[0] ) . '</span>';
    }

    private function render_village_of_cell( int $pid ): void {
        $village_name = trim( (string) get_post_meta( $pid, '_ovr_village_name', true ) );
        if ( '' === $village_name ) {
            echo '<span class="ovr-pls-na">&mdash;</span>';
            return;
        }
        echo '<span class="ovr-pls-village-of">' . esc_html( $village_name ) . '</span>';
    }

    private function render_owner_email_cell( \WP_Post $post ): void {
        $owner = get_userdata( (int) $post->post_author );
        if ( ! $owner || ! $owner->user_email ) {
            echo '<span class="ovr-pls-na">&mdash;</span>';
            return;
        }
        printf(
            '<a href="mailto:%s" class="ovr-pls-email">%s</a>',
            esc_attr( $owner->user_email ),
            esc_html( $owner->user_email )
        );
    }

    private function render_property_name_cell( int $pid ): void {
        $title   = get_the_title( $pid );
        $edit_url = admin_url( 'admin.php?page=ovr-edit-listing&post=' . $pid );
        if ( ! $title ) {
            echo '<span class="ovr-pls-na">' . esc_html__( '(no title)', 'ovr-core' ) . '</span>';
            return;
        }
        printf(
            '<a href="%s" class="ovr-pls-name">%s</a>',
            esc_url( $edit_url ),
            esc_html( $title )
        );
    }

    private function render_last_updated_cell( \WP_Post $post ): void {
        $modified = $post->post_modified;
        if ( ! $modified || '0000-00-00 00:00:00' === $modified ) {
            echo '<span class="ovr-pls-na">&mdash;</span>';
            return;
        }
        echo '<span class="ovr-pls-date">' . esc_html( mysql2date( get_option( 'date_format' ), $modified ) ) . '</span>';
    }

    private function render_paid_services_cell( int $pid ): void {
        $services = $this->listing_services( $pid );
        $active   = array_filter( $services, function( $s ) { return ! empty( $s['active'] ); } );

        echo '<div class="ovr-pls-services">';
        if ( empty( $active ) ) {
            echo '<span class="ovr-pls-na">' . esc_html__( 'None', 'ovr-core' ) . '</span>';
        } else {
            foreach ( $active as $svc ) {
                $badge_class = 'ovr-pls-svc-badge';
                if ( ! empty( $svc['slug'] ) ) {
                    $badge_class .= ' ovr-pls-svc-badge--' . sanitize_html_class( $svc['slug'] );
                }
                echo '<span class="' . $badge_class . '">' . esc_html( $svc['service_name'] ?? $svc['service_slug'] ?? '' ) . '</span>';
            }
        }
        if ( current_user_can( 'manage_options' ) ) {
            printf(
                '<button type="button" class="ovr-pls-svc-add button button-small" data-listing-id="%d">%s</button>',
                $pid,
                esc_html__( 'Add', 'ovr-core' )
            );
        }
        echo '</div>';
    }

    private function render_views_cell( int $pid ): void {
        $views = (int) get_post_meta( $pid, '_ovr_view_count', true );
        echo '<span class="ovr-pls-views">' . esc_html( number_format_i18n( $views ) ) . '</span>';
    }

    private function render_actions_cell( int $pid ): void {
        $edit_url   = admin_url( 'admin.php?page=ovr-edit-listing&post=' . $pid );
        $view_url   = get_permalink( $pid );
        $delete_url = get_delete_post_link( $pid );
        $duplicate_nonce = wp_create_nonce( 'ovr_duplicate_property' );
        $is_trashed = 'trash' === get_post_status( $pid );

        echo '<div class="ovr-pls-actions">';

        if ( $is_trashed ) {
            // Soft-deleted rows: Restore + Permanently Delete (both confirmed in
            // JS). Editing a trashed listing is blocked by core, so we swap the
            // usual edit/duplicate actions for recovery ones.
            $restore_nonce = wp_create_nonce( 'ovr_restore_property_' . $pid );
            $purge_nonce   = wp_create_nonce( 'ovr_perma_delete_property_' . $pid );

            if ( $view_url ) {
                printf(
                    '<a href="%s" class="ovr-pls-act ovr-pls-act--view" title="%s" target="_blank"><span class="material-symbols-outlined">visibility</span></a>',
                    esc_url( $view_url ),
                    esc_attr__( 'View', 'ovr-core' )
                );
            }

            printf(
                '<button type="button" class="ovr-pls-act ovr-pls-act--restore" data-pid="%d" data-nonce="%s" title="%s"><span class="material-symbols-outlined">restore_from_trash</span></button>',
                $pid,
                esc_attr( $restore_nonce ),
                esc_attr__( 'Restore Property', 'ovr-core' )
            );

            printf(
                '<button type="button" class="ovr-pls-act ovr-pls-act--perma" data-pid="%d" data-nonce="%s" title="%s"><span class="material-symbols-outlined">delete_forever</span></button>',
                $pid,
                esc_attr( $purge_nonce ),
                esc_attr__( 'Permanently Delete Property', 'ovr-core' )
            );

            echo '</div>';
            return;
        }

        if ( $view_url ) {
            printf(
                '<a href="%s" class="ovr-pls-act ovr-pls-act--view" title="%s" target="_blank"><span class="material-symbols-outlined">visibility</span></a>',
                esc_url( $view_url ),
                esc_attr__( 'View', 'ovr-core' )
            );
        }

        printf(
            '<a href="%s" class="ovr-pls-act ovr-pls-act--edit" title="%s"><span class="material-symbols-outlined">edit</span></a>',
            esc_url( $edit_url ),
            esc_attr__( 'Edit', 'ovr-core' )
        );

        printf(
            '<button type="button" class="ovr-pls-act ovr-pls-act--dup" data-pid="%d" data-nonce="%s" title="%s"><span class="material-symbols-outlined">content_copy</span></button>',
            $pid,
            esc_attr( $duplicate_nonce ),
            esc_attr__( 'Duplicate', 'ovr-core' )
        );

        if ( $delete_url ) {
            printf(
                '<a href="%s" class="ovr-pls-act ovr-pls-act--delete" title="%s"><span class="material-symbols-outlined">delete</span></a>',
                esc_url( $delete_url ),
                esc_attr__( 'Delete', 'ovr-core' )
            );
        }

        echo '</div>';
    }

    private function render_stats_bar( array $stats ): void {
        ?>
        <div class="ovr-pls-stats">
            <div class="ovr-pls-stat ovr-pls-stat--total">
                <span class="ovr-pls-stat-ic"><span class="material-symbols-outlined">home_work</span></span>
                <span class="ovr-pls-stat-body">
                    <span class="ovr-pls-stat-val"><?php echo (int) ( $stats['total'] ?? 0 ); ?></span>
                    <span class="ovr-pls-stat-lbl"><?php esc_html_e( 'Total Listings', 'ovr-core' ); ?></span>
                </span>
            </div>
            <div class="ovr-pls-stat ovr-pls-stat--active">
                <span class="ovr-pls-stat-ic"><span class="material-symbols-outlined">check_circle</span></span>
                <span class="ovr-pls-stat-body">
                    <span class="ovr-pls-stat-val"><?php echo (int) ( $stats['active'] ?? 0 ); ?></span>
                    <span class="ovr-pls-stat-lbl"><?php esc_html_e( 'Active', 'ovr-core' ); ?></span>
                </span>
            </div>
            <div class="ovr-pls-stat ovr-pls-stat--inactive">
                <span class="ovr-pls-stat-ic"><span class="material-symbols-outlined">pause_circle</span></span>
                <span class="ovr-pls-stat-body">
                    <span class="ovr-pls-stat-val"><?php echo (int) ( $stats['inactive'] ?? 0 ); ?></span>
                    <span class="ovr-pls-stat-lbl"><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></span>
                </span>
            </div>
            <div class="ovr-pls-stat ovr-pls-stat--featured">
                <span class="ovr-pls-stat-ic"><span class="material-symbols-outlined">star</span></span>
                <span class="ovr-pls-stat-body">
                    <span class="ovr-pls-stat-val"><?php echo (int) ( $stats['featured'] ?? 0 ); ?></span>
                    <span class="ovr-pls-stat-lbl"><?php esc_html_e( 'Featured', 'ovr-core' ); ?></span>
                </span>
            </div>
            <div class="ovr-pls-stat ovr-pls-stat--paid">
                <span class="ovr-pls-stat-ic"><span class="material-symbols-outlined">workspace_premium</span></span>
                <span class="ovr-pls-stat-body">
                    <span class="ovr-pls-stat-val"><?php echo (int) ( $stats['paid'] ?? 0 ); ?></span>
                    <span class="ovr-pls-stat-lbl"><?php esc_html_e( 'Paid Services', 'ovr-core' ); ?></span>
                </span>
            </div>
        </div>
        <?php
    }

    private function render_service_modal( array $service_types ): void {
        ?>
        <div id="ovr-pls-service-modal" class="ovr-pls-modal" style="display:none;">
            <div class="ovr-pls-modal-backdrop"></div>
            <div class="ovr-pls-modal-content">
                <div class="ovr-pls-modal-header">
                    <h3><?php esc_html_e( 'Assign Paid Service', 'ovr-core' ); ?></h3>
                    <button type="button" class="ovr-pls-modal-close button">&times;</button>
                </div>
                <div class="ovr-pls-modal-body">
                    <input type="hidden" id="ovr-pls-svc-listing-id" value="">
                    <p>
                        <label for="ovr-pls-svc-select"><?php esc_html_e( 'Service:', 'ovr-core' ); ?></label>
                        <select id="ovr-pls-svc-select">
                            <option value=""><?php esc_html_e( 'Select…', 'ovr-core' ); ?></option>
                            <?php foreach ( $service_types as $svc ) : ?>
                                <option value="<?php echo (int) $svc['id']; ?>">
                                    <?php echo esc_html( $svc['name'] . ( $svc['badge'] ? ' (' . $svc['badge'] . ')' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="ovr-pls-svc-start"><?php esc_html_e( 'Start Date:', 'ovr-core' ); ?></label>
                        <input type="date" id="ovr-pls-svc-start" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                    </p>
                    <p>
                        <label for="ovr-pls-svc-end"><?php esc_html_e( 'End Date (optional):', 'ovr-core' ); ?></label>
                        <input type="date" id="ovr-pls-svc-end" value="">
                    </p>
                    <p>
                        <label for="ovr-pls-svc-notes"><?php esc_html_e( 'Notes:', 'ovr-core' ); ?></label>
                        <textarea id="ovr-pls-svc-notes" rows="2"></textarea>
                    </p>
                    <div class="ovr-pls-modal-actions">
                        <button type="button" class="button button-primary" id="ovr-pls-svc-save"><?php esc_html_e( 'Assign', 'ovr-core' ); ?></button>
                        <button type="button" class="button ovr-pls-modal-close"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────
    //  AJAX: Add service
    // ──────────────────────────────────────────────

    public function ajax_add_service(): void {
        if ( ! check_ajax_referer( 'ovr_admin_nonce', 'nonce', false )
             || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }

        $listing_id = absint( $_POST['listing_id'] ?? 0 );
        $service_id = absint( $_POST['service_id'] ?? 0 );
        $start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
        $end_date   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
        $notes      = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

        if ( ! $listing_id || ! $service_id ) {
            wp_send_json_error( [ 'message' => __( 'Missing parameters.', 'ovr-core' ) ], 400 );
        }

        $post = get_post( $listing_id );
        if ( ! $post || self::PT !== $post->post_type ) {
            wp_send_json_error( [ 'message' => __( 'Invalid listing.', 'ovr-core' ) ], 400 );
        }

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ovr_listing_services',
            [
                'listing_id'  => $listing_id,
                'service_id'  => $service_id,
                'start_date'  => $start_date ?: current_time( 'Y-m-d' ),
                'end_date'    => $end_date ?: null,
                'active'      => 1,
                'notes'       => $notes ?: null,
                'assigned_by' => get_current_user_id(),
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%s', '%d' ]
        );

        if ( false === $inserted ) {
            wp_send_json_error( [ 'message' => __( 'Failed to assign service.', 'ovr-core' ) ], 500 );
        }

        AuditLog::record(
            'admin.service.add',
            'listing',
            $listing_id,
            [ 'service_id' => $service_id, 'end_date' => $end_date ],
            (int) $post->post_author,
            [ 'new' => $notes ]
        );

        // Immediately activate the underlying boost so the complimentary service
        // takes effect right away (Section 5: Apply → immediately activate) — the
        // same meta flags the search ordering + homepage slider read.
        $this->activate_service_boost( $listing_id, $service_id, $end_date );

        wp_send_json_success( [
            'message' => __( 'Service assigned.', 'ovr-core' ),
        ] );
    }

    /**
     * Turn a complimentary service grant into its live boost. Resolves the
     * catalogue service's boost behaviour (`service_type`) + duration, then sets
     * the boost meta with an explicit expiry (the admin's end date, or today +
     * the catalogue duration when left blank).
     */
    private function activate_service_boost( int $listing_id, int $service_id, string $end_date ): void {
        global $wpdb;
        $svc = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT service_type, duration_days FROM {$wpdb->prefix}ovr_paid_services WHERE id = %d",
                $service_id
            ),
            ARRAY_A
        );
        if ( ! $svc || empty( $svc['service_type'] ) ) {
            return;
        }
        $expires = $end_date
            ?: gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +' . max( 1, (int) $svc['duration_days'] ) . ' days' ) );
        UpgradeActivator::activate_until( $listing_id, (string) $svc['service_type'], $expires );
    }

    /**
     * After a complimentary service is removed, clear its boost only when no
     * other active service of the same boost type remains on the listing, so a
     * "Remove" click takes effect immediately without disturbing an overlapping
     * grant. (Self-serve paid boosts are tracked via payment meta and expire on
     * their own schedule.)
     */
    private function deactivate_service_boost( int $listing_id, int $service_id ): void {
        global $wpdb;
        $type = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT service_type FROM {$wpdb->prefix}ovr_paid_services WHERE id = %d",
                $service_id
            )
        );
        if ( '' === $type ) {
            return;
        }
        $remaining = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                   FROM {$wpdb->prefix}ovr_listing_services ls
                   JOIN {$wpdb->prefix}ovr_paid_services ps ON ls.service_id = ps.id
                  WHERE ls.listing_id = %d AND ls.active = 1 AND ps.service_type = %s",
                $listing_id,
                $type
            )
        );
        if ( 0 === $remaining ) {
            UpgradeActivator::deactivate( $listing_id, $type );
        }
    }

    // ──────────────────────────────────────────────
    //  AJAX: Remove service
    // ──────────────────────────────────────────────

    public function ajax_remove_service(): void {
        if ( ! check_ajax_referer( 'ovr_admin_nonce', 'nonce', false )
             || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'Missing ID.', 'ovr-core' ) ], 400 );
        }

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ovr_listing_services WHERE id = %d", $id
        ), ARRAY_A );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Service not found.', 'ovr-core' ) ], 404 );
        }

        $wpdb->update(
            $wpdb->prefix . 'ovr_listing_services',
            [ 'active' => 0, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        AuditLog::record(
            'admin.service.remove',
            'listing',
            (int) $row['listing_id'],
            [ 'service_id' => (int) $row['service_id'] ],
            null,
            [ 'old' => 'active', 'new' => 'inactive' ]
        );

        // Clear the underlying boost if this was the last active grant of its type.
        $this->deactivate_service_boost( (int) $row['listing_id'], (int) $row['service_id'] );

        wp_send_json_success( [ 'message' => __( 'Service removed.', 'ovr-core' ) ] );
    }

    // ──────────────────────────────────────────────
    //  AJAX: Get listing services (for modal refresh)
    // ──────────────────────────────────────────────

    public function ajax_get_services(): void {
        if ( ! check_ajax_referer( 'ovr_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }

        $listing_id = absint( $_POST['listing_id'] ?? 0 );
        if ( ! $listing_id ) {
            wp_send_json_error( [ 'message' => __( 'Missing listing ID.', 'ovr-core' ) ], 400 );
        }

        wp_send_json_success( [
            'services' => $this->listing_services( $listing_id ),
        ] );
    }

    /**
     * AJAX (P8 §8): search users for the "Reassign Owner" picker.
     */
    public function ajax_search_users(): void {
        if ( ! check_ajax_referer( 'ovr_admin_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }
        $term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
        if ( strlen( $term ) < 2 ) {
            wp_send_json_success( [ 'users' => [] ] );
        }
        $users = get_users( [
            'search'         => '*' . $term . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name', 'user_nicename' ],
            'number'         => 15,
            'orderby'        => 'display_name',
        ] );
        $out = [];
        foreach ( $users as $u ) {
            $out[] = [
                'id'    => (int) $u->ID,
                'name'  => $u->display_name,
                'email' => $u->user_email,
            ];
        }
        wp_send_json_success( [ 'users' => $out ] );
    }

    /**
     * AJAX (P8 §8): transfer a listing's ownership to another user.
     */
    public function ajax_reassign_listing(): void {
        if ( ! check_ajax_referer( 'ovr_admin_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }
        $listing_id = absint( $_POST['listing_id'] ?? 0 );
        $new_owner  = absint( $_POST['user_id'] ?? 0 );

        $post = $listing_id ? get_post( $listing_id ) : null;
        if ( ! $post || self::PT !== $post->post_type ) {
            wp_send_json_error( [ 'message' => __( 'Invalid listing.', 'ovr-core' ) ], 400 );
        }
        $user = $new_owner ? get_userdata( $new_owner ) : null;
        if ( ! $user ) {
            wp_send_json_error( [ 'message' => __( 'Please choose a valid user.', 'ovr-core' ) ], 400 );
        }

        wp_update_post( [ 'ID' => $listing_id, 'post_author' => $new_owner ] );
        update_post_meta( $listing_id, '_ovr_owner_email', $user->user_email );

        if ( class_exists( '\OVR\Core\AuditLog' ) ) {
            \OVR\Core\AuditLog::record( 'listing.reassign', 'listing', $listing_id );
        }

        wp_send_json_success( [
            'message'     => sprintf( /* translators: %s: user name */ __( 'Ownership transferred to %s.', 'ovr-core' ), $user->display_name ),
            'owner_name'  => $user->display_name,
            'owner_email' => $user->user_email,
        ] );
    }

    // ──────────────────────────────────────────────
    //  AJAX: Bulk actions
    // ──────────────────────────────────────────────

    public function ajax_duplicate_property(): void {
        if ( ! check_ajax_referer( 'ovr_duplicate_property', 'nonce', false )
             || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }

        $post_id = absint( $_POST['listing_id'] ?? 0 );
        $post    = get_post( $post_id );
        if ( ! $post || self::PT !== $post->post_type ) {
            wp_send_json_error( [ 'message' => __( 'Listing not found.', 'ovr-core' ) ], 404 );
        }

        $new = wp_insert_post( [
            'post_type'    => self::PT,
            'post_status'  => 'draft',
            'post_title'   => $post->post_title . ' — Copy',
            'post_author'  => $post->post_author,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
        ] );

        if ( is_wp_error( $new ) || ! $new ) {
            wp_send_json_error( [ 'message' => __( 'Could not duplicate this listing.', 'ovr-core' ) ], 500 );
        }

        // Copy every piece of post meta (except the redundant naming flags).
        $meta = get_post_meta( $post_id );
        foreach ( $meta as $key => $values ) {
            if ( in_array( $key, [ '_edit_last', '_edit_lock' ], true ) ) {
                continue;
            }
            foreach ( $values as $value ) {
                add_post_meta( $new, $key, $value );
            }
        }

        // Clone the taxonomies (village section, type, amenities, features, views…).
        $taxonomies = get_object_taxonomies( self::PT );
        foreach ( $taxonomies as $tax ) {
            $terms = wp_get_object_terms( $post_id, $tax, [ 'fields' => 'ids' ] );
            if ( ! is_wp_error( $terms ) ) {
                wp_set_object_terms( $new, $terms, $tax );
            }
        }

        AuditLog::record( 'admin.duplicate', 'listing', (int) $new, [ 'source' => $post_id ], (int) $post->post_author );

        wp_send_json_success( [
            'message' => sprintf( __( 'Listing %d duplicated to #%d (draft).', 'ovr-core' ), $post_id, $new ),
        ] );
    }

    /**
     * Restore a soft-deleted (trashed) listing back to publish. Mirrors the
     * Deleted Listings screen behaviour so admins can act directly from the
     * Properties management grid.
     */
    public function ajax_restore_property(): void {
        $post_id = absint( $_POST['listing_id'] ?? 0 );

        if ( ! current_user_can( 'manage_options' )
             || ! check_ajax_referer( 'ovr_restore_property_' . $post_id, 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }

        $post = get_post( $post_id );
        if ( ! $post || self::PT !== $post->post_type ) {
            wp_send_json_error( [ 'message' => __( 'Listing not found.', 'ovr-core' ) ], 404 );
        }
        if ( 'trash' !== $post->post_status ) {
            wp_send_json_error( [ 'message' => __( 'This listing is not soft-deleted.', 'ovr-core' ) ], 400 );
        }

        wp_untrash_post( $post_id );
        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );

        // Clear the soft-delete reason markers.
        delete_post_meta( $post_id, '_ovr_deleted_by' );
        delete_post_meta( $post_id, '_ovr_deleted_at' );

        AuditLog::record( 'listing.restored', 'listing', $post_id, [ 'deleted_by' => get_post_meta( $post_id, '_ovr_deleted_by', true ) ], get_current_user_id() );

        wp_send_json_success( [ 'message' => __( 'Property restored and visible again.', 'ovr-core' ) ] );
    }

    /**
     * Permanently delete a soft-deleted listing: removes the post, its metadata
     * and attached media. Requires a fresh nonce (JS confirmation first).
     */
    public function ajax_perma_delete_property(): void {
        $post_id = absint( $_POST['listing_id'] ?? 0 );

        if ( ! current_user_can( 'manage_options' )
             || ! check_ajax_referer( 'ovr_perma_delete_property_' . $post_id, 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }

        $post = get_post( $post_id );
        if ( ! $post || self::PT !== $post->post_type ) {
            wp_send_json_error( [ 'message' => __( 'Listing not found.', 'ovr-core' ) ], 404 );
        }
        if ( 'trash' !== $post->post_status ) {
            wp_send_json_error( [ 'message' => __( 'Only soft-deleted listings can be permanently removed.', 'ovr-core' ) ], 400 );
        }

        $deleted_by = get_post_meta( $post_id, '_ovr_deleted_by', true );
        $title      = $post->post_title;

        wp_delete_post( $post_id, true );
        AuditLog::record( 'listing.permanent_delete', 'listing', $post_id, [ 'was_deleted_by' => $deleted_by, 'title' => $title ], get_current_user_id() );

        wp_send_json_success( [ 'message' => __( 'Property permanently deleted.', 'ovr-core' ) ] );
    }

    public function ajax_bulk_action(): void {
        if ( ! check_ajax_referer( 'ovr_admin_nonce', 'nonce', false )
             || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }

        $action    = sanitize_key( wp_unslash( $_POST['bulk_action'] ?? '' ) );
        $listing_ids = isset( $_POST['listing_ids'] ) && is_array( $_POST['listing_ids'] )
            ? array_map( 'absint', $_POST['listing_ids'] )
            : [];

        if ( empty( $listing_ids ) ) {
            wp_send_json_error( [ 'message' => __( 'No listings selected.', 'ovr-core' ) ], 400 );
        }

        $allowed = [ 'activate', 'deactivate', 'approve', 'hide', 'delete' ];
        if ( ! in_array( $action, $allowed, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid action.', 'ovr-core' ) ], 400 );
        }

        $updated = 0;
        foreach ( $listing_ids as $lid ) {
            $post = get_post( $lid );
            if ( ! $post || self::PT !== $post->post_type ) {
                continue;
            }

            switch ( $action ) {
                case 'activate':
                    update_post_meta( $lid, '_ovr_listing_status', 'active' );
                    break;
                case 'deactivate':
                    update_post_meta( $lid, '_ovr_listing_status', 'inactive' );
                    break;
                case 'approve':
                    update_post_meta( $lid, '_ovr_admin_status', 'approved' );
                    break;
                case 'hide':
                    update_post_meta( $lid, '_ovr_admin_status', 'hidden' );
                    break;
                case 'delete':
                    wp_trash_post( $lid );
                    update_post_meta( $lid, '_ovr_deleted_by', 'admin' );
                    update_post_meta( $lid, '_ovr_deleted_at', current_time( 'mysql' ) );
                    break;
            }

            AuditLog::record(
                'admin.bulk.' . $action,
                'listing',
                $lid,
                [ 'bulk_action' => $action, 'affected' => count( $listing_ids ) ],
                (int) $post->post_author
            );
            $updated++;
        }

        wp_send_json_success( [
            'message' => sprintf( __( '%d listing(s) updated.', 'ovr-core' ), $updated ),
        ] );
    }

    // ──────────────────────────────────────────────
    //  CRON: Expire overdue services
    // ──────────────────────────────────────────────

    public static function expire_services(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ovr_listing_services';
        $now   = current_time( 'Y-m-d' );

        $expired = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE active = 1 AND end_date IS NOT NULL AND end_date < %s",
                $now
            ),
            ARRAY_A
        );

        if ( ! $expired ) {
            return;
        }

        foreach ( $expired as $row ) {
            $wpdb->update(
                $table,
                [ 'active' => 0, 'updated_at' => current_time( 'mysql' ) ],
                [ 'id' => (int) $row['id'] ],
                [ '%d', '%s' ],
                [ '%d' ]
            );

            AuditLog::record(
                'admin.service.expire',
                'listing',
                (int) $row['listing_id'],
                [ 'service_id' => (int) $row['service_id'], 'end_date' => $row['end_date'] ]
            );
        }
    }
}
