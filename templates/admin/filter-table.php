<?php
/**
 * FilterTable template with inline column filters, sticky header, and checkbox column.
 *
 * Variables provided by FilterTable::render_template():
 *   $items        - array of post/user objects
 *   $filters      - array of current filter values
 *   $page_data    - array with page, total, total_pages, per_page, orderby, order, search
 *   $columns      - array of column key => column label
 *   $sortable     - array of sortable column keys
 *   $singular     - singular label string
 *   $plural       - plural label string
 *   $bulk_actions - array of bulk action value => label
 *   $screen_id    - string screen identifier
 *   $engine       - FilterEngine instance
 *
 * @package OVR\Admin
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$has_cb       = isset( $columns['cb'] );
$has_bulk     = ! empty( $bulk_actions );
$col_keys     = array_keys( $columns );
$total_cols   = count( $columns );
$non_cb_cols  = $has_cb ? array_slice( $col_keys, 1 ) : $col_keys;
?>
<div class="ovr-ft-wrap">
    <form method="post" class="ovr-ft-form">
        <table class="wp-list-table widefat fixed ovr-ft-table">
            <thead>
                <tr class="ovr-ft-headers">
                    <?php foreach ( $columns as $key => $label ) :
                        $is_sortable = in_array( $key, $sortable, true );
                        $current_orderby = $page_data['orderby'] ?? '';
                        $current_order   = $page_data['order'] ?? 'ASC';
                        $sorted = $current_orderby === $key;
                        $cls = 'manage-column column-' . sanitize_html_class( $key );
                        if ( $sorted ) {
                            $cls .= ' sorted ' . strtolower( $current_order );
                        } elseif ( $is_sortable ) {
                            $cls .= ' sortable asc';
                        }
                        if ( 'cb' === $key ) {
                            $cls .= ' column-cb check-column';
                        }
                    ?>
                        <th scope="col" class="<?php echo esc_attr( $cls ); ?>" data-orderby="<?php echo $is_sortable ? esc_attr( $key ) : ''; ?>">
                            <?php if ( 'cb' === $key ) : ?>
                                <?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php elseif ( $is_sortable ) : ?>
                                <a href="#"><span><?php echo esc_html( $label ); ?></span><span class="sorting-indicators"></span></a>
                            <?php else : ?>
                                <span><?php echo esc_html( $label ); ?></span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
                <tr class="ovr-ft-filters">
                    <?php foreach ( $columns as $key => $label ) : ?>
                        <td class="column-filter-cell column-filter-<?php echo esc_attr( $key ); ?>">
                            <?php if ( 'cb' === $key ) : ?>
                                &nbsp;
                            <?php elseif ( 'actions' === $key ) : ?>
                                &nbsp;
                            <?php else : ?>
                                <?php echo $engine->render_single_control( $key, $filters[ $key ] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $items ) ) : ?>
                    <tr class="no-items">
                        <td colspan="<?php echo (int) $total_cols; ?>">
                            <?php esc_html_e( 'No items found.', 'ovr-core' ); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $items as $item ) : ?>
                        <tr>
                            <?php foreach ( $columns as $key => $label ) : ?>
                                <td class="column-<?php echo sanitize_html_class( $key ); ?>">
                                    <?php $this->render_cell( $key, $item ); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <div class="tablenav bottom ovr-ft-nav">
        <?php if ( $has_bulk ) : ?>
        <div class="alignleft actions bulkactions"></div>
        <?php endif; ?>
        <?php $this->render_pagination( $page_data ); ?>
        <br class="clear">
    </div>
</div>
