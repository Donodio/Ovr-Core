<?php

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FilterTable {
    private FilterEngine $engine;
    private string $screen_id;
    private string $ajax_action;
    private array $columns     = [];
    private array $sortable    = [];
    private array $defaults    = [];
    private array $bulk_actions = [];
    private string $singular   = 'item';
    private string $plural     = 'items';
    private $query_callback = null;
    private $render_cell_callback = null;
    private string $type = 'post';

    public function __construct( string $screen_id ) {
        $this->screen_id   = $screen_id;
        $this->ajax_action = "ovr_filter_table_{$screen_id}";
        $this->engine      = new FilterEngine( $screen_id );
    }

    public function get_engine(): FilterEngine {
        return $this->engine;
    }

    public function set_columns( array $columns ): void {
        $this->columns = $columns;
    }

    public function get_columns(): array {
        return $this->columns;
    }

    public function set_sortable( array $sortable ): void {
        $this->sortable = $sortable;
    }

    public function set_defaults( array $defaults ): void {
        $this->defaults = $defaults;
    }

    public function set_bulk_actions( array $actions ): void {
        $this->bulk_actions = $actions;
    }

    public function get_bulk_actions(): array {
        return $this->bulk_actions;
    }

    public function set_labels( string $singular, string $plural ): void {
        $this->singular = $singular;
        $this->plural   = $plural;
    }

    public function set_query_callback( callable $callback ): void {
        $this->query_callback = $callback;
    }

    public function set_render_cell_callback( callable $callback ): void {
        $this->render_cell_callback = $callback;
    }

    public function init(): void {
        add_action( "wp_ajax_{$this->ajax_action}", [ $this, 'handle_ajax' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, $this->screen_id ) ) {
            return;
        }
        wp_enqueue_script(
            'ovr-filter-table',
            OVR_PLUGIN_URL . 'assets/js/ovr-filter-table.js',
            [],
            filemtime( OVR_PLUGIN_DIR . 'assets/js/ovr-filter-table.js' ),
            true
        );
        wp_localize_script( 'ovr-filter-table', 'ovrFilterTable', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'action'   => $this->ajax_action,
            'screenId' => $this->screen_id,
            'nonce'    => wp_create_nonce( $this->ajax_action ),
        ] );
        wp_enqueue_style(
            'ovr-filter-table',
            OVR_PLUGIN_URL . 'assets/css/ovr-filter-table.css',
            [],
            filemtime( OVR_PLUGIN_DIR . 'assets/css/ovr-filter-table.css' )
        );
    }

    public function render(): void {
        $filters = $this->get_current_filters();
        $page    = $this->get_current_page();
        $orderby = $this->get_current_orderby();
        $order   = $this->get_current_order();
        $search  = $this->get_current_search();

        $query = $this->build_query( $filters, $page, $orderby, $order, $search );
        $items = $this->type === 'user' ? $query->get_results() : $query->posts ?? [];
        $total = $this->type === 'user' ? $query->get_total() : $query->found_posts;
        $per_page = $this->get_per_page();
        $total_pages = (int) ceil( $total / max( 1, $per_page ) );

        $this->render_template( $items, $filters, [
            'page'        => $page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'per_page'    => $per_page,
            'orderby'     => $orderby,
            'order'       => $order,
            'search'      => $search,
        ] );
    }

    public function get_state(): array {
        return [
            'filters' => $this->get_current_filters(),
            'page'    => $this->get_current_page(),
            'orderby' => $this->get_current_orderby(),
            'order'   => $this->get_current_order(),
            'search'  => $this->get_current_search(),
        ];
    }

    public function get_current_filters(): array {
        $input = [];
        if ( isset( $_GET['ovr_filters'] ) && is_array( $_GET['ovr_filters'] ) ) {
            $input = $_GET['ovr_filters'];
        }
        $values = $this->engine->process_input( $input );
        return array_merge( $this->defaults, $values );
    }

    public function get_current_page(): int {
        return max( 1, (int) ( $_GET['paged'] ?? 1 ) );
    }

    public function get_current_orderby(): string {
        return sanitize_key( $_GET['orderby'] ?? $this->sortable[0] ?? 'title' );
    }

    public function get_current_order(): string {
        $order = strtoupper( $_GET['order'] ?? 'ASC' );
        return in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'ASC';
    }

    public function get_current_search(): string {
        return isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    }

    private function get_per_page(): int {
        $user = get_user_meta( get_current_user_id(), 'edit_' . $this->screen_id . '_per_page', true );
        return $user ? (int) $user : 20;
    }

    public function handle_ajax(): void {
        check_ajax_referer( $this->ajax_action, 'nonce' );

        $filters = [];
        if ( isset( $_POST['ovr_filters'] ) && is_array( $_POST['ovr_filters'] ) ) {
            $filters = $this->engine->process_input( $_POST['ovr_filters'] );
        }
        $filters = array_merge( $this->defaults, $filters );

        $page    = max( 1, (int) ( $_POST['paged'] ?? 1 ) );
        $orderby = sanitize_key( $_POST['orderby'] ?? $this->sortable[0] ?? 'title' );
        $order   = in_array( strtoupper( $_POST['order'] ?? 'ASC' ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $_POST['order'] ) : 'ASC';
        $search  = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';

        $query = $this->build_query( $filters, $page, $orderby, $order, $search );
        $items = $this->type === 'user' ? $query->get_results() : $query->posts ?? [];
        $total = $this->type === 'user' ? $query->get_total() : $query->found_posts;
        $per_page = $this->get_per_page();
        $total_pages = (int) ceil( $total / max( 1, $per_page ) );

        ob_start();
        $this->render_rows( $items );
        $rows_html = ob_get_clean();

        ob_start();
        $this->render_pagination( [
            'page'        => $page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'per_page'    => $per_page,
        ] );
        $pagination_html = ob_get_clean();

        wp_send_json_success( [
            'rows'       => $rows_html,
            'pagination' => $pagination_html,
            'total'      => (int) $total,
            'page'       => $page,
            'totalPages' => $total_pages,
        ] );
    }

    protected function build_query( array $filters, int $page, string $orderby, string $order, string $search ): \WP_Query|\WP_User_Query {
        if ( null !== $this->query_callback ) {
            return call_user_func( $this->query_callback, $filters, $page, $orderby, $order, $search, $this );
        }
        $per_page = $this->get_per_page();
        $base     = [
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
        ];
        $this->type = 'post';
        $query = QueryBuilder::for_posts( $base );

        if ( '' !== $search ) {
            $query->set( 's', $search );
        }

        $this->engine->apply_filters( $filters, $query );

        return $query->run();
    }

    protected function render_template( array $items, array $filters, array $page_data ): void {
        $columns    = $this->columns;
        $sortable   = $this->sortable;
        $singular   = $this->singular;
        $plural     = $this->plural;
        $bulk_actions = $this->bulk_actions;
        $screen_id  = $this->screen_id;
        $engine     = $this->engine;

        include OVR_PLUGIN_DIR . 'templates/admin/filter-table.php';
    }

    protected function render_rows( array $items ): void {
        foreach ( $items as $item ) {
            echo '<tr>';
            foreach ( $this->columns as $key => $label ) {
                echo '<td class="column-' . esc_attr( $key ) . '">';
                $this->render_cell( $key, $item );
                echo '</td>';
            }
            echo '</tr>';
        }
    }

    protected function render_cell( string $key, $item ): void {
        if ( null !== $this->render_cell_callback ) {
            call_user_func( $this->render_cell_callback, $key, $item );
            return;
        }
        $method = "render_{$key}_cell";
        if ( method_exists( $this, $method ) ) {
            $this->$method( $item );
            return;
        }
        echo esc_html( $item->$key ?? $item->post_title ?? '' );
    }

    protected function render_pagination( array $page_data ): void {
        $page        = $page_data['page'];
        $total       = $page_data['total'];
        $total_pages = $page_data['total_pages'];
        $per_page    = $page_data['per_page'];

        if ( $total_pages <= 1 ) {
            return;
        }

        echo '<div class="tablenav-pages"><span class="displaying-num">'
            . sprintf( esc_html__( '%d items', 'ovr-core' ), $total ) . '</span>';

        echo '<span class="pagination-links">';

        if ( $page > 1 ) {
            echo '<a class="first-page button" href="#" data-page="1">&laquo;</a>';
            echo '<a class="prev-page button" href="#" data-page="' . ( $page - 1 ) . '">&lsaquo;</a>';
        } else {
            echo '<span class="tablenav-pages-navspan button disabled">&laquo;</span>';
            echo '<span class="tablenav-pages-navspan button disabled">&lsaquo;</span>';
        }

        echo sprintf(
            '<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">%s</label>'
            . '<input class="current-page" id="current-page-selector" type="text" name="paged" value="%d" size="1" aria-describedby="table-paging">'
            . ' <span class="tablenav-paging-text"> of <span class="total-pages">%d</span></span></span>',
            esc_html__( 'Current Page', 'ovr-core' ),
            $page,
            $total_pages
        );

        if ( $page < $total_pages ) {
            echo '<a class="next-page button" href="#" data-page="' . ( $page + 1 ) . '">&rsaquo;</a>';
            echo '<a class="last-page button" href="#" data-page="' . $total_pages . '">&raquo;</a>';
        } else {
            echo '<span class="tablenav-pages-navspan button disabled">&rsaquo;</span>';
            echo '<span class="tablenav-pages-navspan button disabled">&raquo;</span>';
        }

        echo '</span></div>';
    }
}
