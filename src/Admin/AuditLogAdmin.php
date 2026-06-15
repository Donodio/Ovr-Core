<?php
/**
 * Audit Log admin screen (Milestone 3 Feature 2).
 *
 * A themed, filterable, paginated view of wp_ovr_audit_log with CSV + Excel
 * export. Filters: date range, subject user, acting admin, action, entity type,
 * and property/entity id. Admin-only (manage_options).
 *
 * @package OVR\Admin
 * @since   2.2.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuditLogAdmin {

    public const PAGE_SLUG = 'ovr-core-audit-log';
    public const PER_PAGE  = 30;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Audit Log', 'ovr-core' ),
            __( 'Audit Log', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    private function page_url(): string {
        return add_query_arg(
            [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG ],
            admin_url( 'edit.php' )
        );
    }

    private function list_table(): ListTable {
        global $wpdb;
        return new ListTable( [
            'table'      => $wpdb->prefix . 'ovr_audit_log',
            'searchable' => [ 'action', 'object_type', 'ip_address' ],
            'sortable'   => [ 'id', 'created_at', 'action', 'object_type', 'user_id', 'admin_id' ],
            'default'    => [ 'orderby' => 'created_at', 'order' => 'DESC' ],
            'per_page'   => self::PER_PAGE,
            'filters'    => [
                'action'      => [ 'column' => 'action', 'compare' => 'LIKE' ],
                'object_type' => [ 'column' => 'object_type' ],
                'user_id'     => [ 'column' => 'user_id', 'cast' => 'int' ],
                'admin_id'    => [ 'column' => 'admin_id', 'cast' => 'int' ],
                'object_id'   => [ 'column' => 'object_id', 'cast' => 'int' ],
                'date_from'   => [ 'column' => 'created_at', 'compare' => '>=' ],
                'date_to'     => [ 'column' => 'created_at', 'compare' => '<=' ],
            ],
        ] );
    }

    /** Column map shared by the table + exports. */
    private function columns(): array {
        return [
            'ID'        => 'id',
            'Time'      => 'created_at',
            'Action'    => 'action',
            'Entity'    => 'object_type',
            'Entity ID' => 'object_id',
            'Subject'   => 'user_id',
            'Admin'     => 'admin_id',
            'Old'       => 'old_value',
            'New'       => 'new_value',
            'IP'        => 'ip_address',
        ];
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view the audit log.', 'ovr-core' ) );
        }

        $list = $this->list_table();

        // Exports short-circuit before any output.
        $export = isset( $_GET['export'] ) ? sanitize_key( wp_unslash( $_GET['export'] ) ) : '';
        if ( 'csv' === $export ) {
            $list->export_csv( 'ovr-audit-log', $this->columns() );
        } elseif ( 'xlsx' === $export ) {
            $list->export_xlsx( 'ovr-audit-log', $this->columns() );
        }

        $data     = $list->query();
        $page_url = $this->page_url();

        global $wpdb;
        $table   = $wpdb->prefix . 'ovr_audit_log';
        $actions = $wpdb->get_col( "SELECT DISTINCT action FROM {$table} ORDER BY action ASC" );
        $types   = $wpdb->get_col( "SELECT DISTINCT object_type FROM {$table} WHERE object_type <> '' ORDER BY object_type ASC" );

        $f = static fn( $k ) => isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
        ?>
        <div class="wrap ovr-audit">
            <h1 style="margin-bottom:4px"><?php esc_html_e( 'Audit Log', 'ovr-core' ); ?></h1>
            <p class="description" style="margin-top:0">
                <?php
                /* translators: %d: retention days */
                printf( esc_html__( 'All tracked platform changes. Records are retained for %d days, then purged automatically.', 'ovr-core' ), (int) \OVR\Core\AuditLog::RETENTION_DAYS );
                ?>
            </p>

            <form method="get" style="margin:14px 0;display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px">
                <input type="hidden" name="post_type" value="ovr_property">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
                <label style="display:flex;flex-direction:column;font-size:12px;font-weight:600;gap:3px"><?php esc_html_e( 'From', 'ovr-core' ); ?>
                    <input type="date" name="date_from" value="<?php echo esc_attr( $f( 'date_from' ) ); ?>"></label>
                <label style="display:flex;flex-direction:column;font-size:12px;font-weight:600;gap:3px"><?php esc_html_e( 'To', 'ovr-core' ); ?>
                    <input type="date" name="date_to" value="<?php echo esc_attr( $f( 'date_to' ) ); ?>"></label>
                <label style="display:flex;flex-direction:column;font-size:12px;font-weight:600;gap:3px"><?php esc_html_e( 'Action', 'ovr-core' ); ?>
                    <select name="action">
                        <option value=""><?php esc_html_e( 'All', 'ovr-core' ); ?></option>
                        <?php foreach ( (array) $actions as $a ) : ?>
                            <option value="<?php echo esc_attr( $a ); ?>" <?php selected( $f( 'action' ), $a ); ?>><?php echo esc_html( $a ); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label style="display:flex;flex-direction:column;font-size:12px;font-weight:600;gap:3px"><?php esc_html_e( 'Entity', 'ovr-core' ); ?>
                    <select name="object_type">
                        <option value=""><?php esc_html_e( 'All', 'ovr-core' ); ?></option>
                        <?php foreach ( (array) $types as $t ) : ?>
                            <option value="<?php echo esc_attr( $t ); ?>" <?php selected( $f( 'object_type' ), $t ); ?>><?php echo esc_html( $t ); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label style="display:flex;flex-direction:column;font-size:12px;font-weight:600;gap:3px"><?php esc_html_e( 'Property/Entity ID', 'ovr-core' ); ?>
                    <input type="number" name="object_id" value="<?php echo esc_attr( $f( 'object_id' ) ); ?>" style="width:120px"></label>
                <label style="display:flex;flex-direction:column;font-size:12px;font-weight:600;gap:3px"><?php esc_html_e( 'Admin ID', 'ovr-core' ); ?>
                    <input type="number" name="admin_id" value="<?php echo esc_attr( $f( 'admin_id' ) ); ?>" style="width:90px"></label>
                <button class="button button-primary"><?php esc_html_e( 'Filter', 'ovr-core' ); ?></button>
                <a class="button" href="<?php echo esc_url( $page_url ); ?>"><?php esc_html_e( 'Reset', 'ovr-core' ); ?></a>
            </form>

            <p style="margin:0 0 10px">
                <a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $_GET, [ 'export' => 'csv' ] ), $page_url ) ); ?>"><?php esc_html_e( 'Export CSV', 'ovr-core' ); ?></a>
                <a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $_GET, [ 'export' => 'xlsx' ] ), $page_url ) ); ?>"><?php esc_html_e( 'Export Excel', 'ovr-core' ); ?></a>
                <span style="color:#646970;margin-left:8px">
                    <?php
                    /* translators: %d: total rows */
                    printf( esc_html( _n( '%d entry', '%d entries', (int) $data['total'], 'ovr-core' ) ), (int) $data['total'] );
                    ?>
                </span>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Time', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Entity', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Subject', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Admin', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Change', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'IP', 'ovr-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $data['rows'] ) ) : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No audit entries match these filters.', 'ovr-core' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $data['rows'] as $row ) :
                        $subject = $row['user_id'] ? get_userdata( (int) $row['user_id'] ) : null;
                        $admin   = $row['admin_id'] ? get_userdata( (int) $row['admin_id'] ) : null;
                        $change  = '';
                        if ( ! empty( $row['old_value'] ) || ! empty( $row['new_value'] ) ) {
                            $change = trim( (string) $row['old_value'] ) . ' → ' . trim( (string) $row['new_value'] );
                        } elseif ( ! empty( $row['details'] ) ) {
                            $change = (string) $row['details'];
                        }
                    ?>
                        <tr>
                            <td style="white-space:nowrap"><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( (string) $row['created_at'] ) ) ); ?></td>
                            <td><code><?php echo esc_html( (string) $row['action'] ); ?></code></td>
                            <td><?php echo esc_html( trim( (string) $row['object_type'] . ' ' . ( $row['object_id'] ? '#' . $row['object_id'] : '' ) ) ?: '—' ); ?></td>
                            <td><?php echo esc_html( $subject ? $subject->display_name : ( $row['user_id'] ? '#' . $row['user_id'] : '—' ) ); ?></td>
                            <td><?php echo esc_html( $admin ? $admin->display_name : ( $row['admin_id'] ? '#' . $row['admin_id'] : '—' ) ); ?></td>
                            <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis"><span title="<?php echo esc_attr( $change ); ?>"><?php echo esc_html( mb_strimwidth( $change, 0, 80, '…' ) ); ?></span></td>
                            <td style="white-space:nowrap"><?php echo esc_html( (string) $row['ip_address'] ?: '—' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php $this->pagination( $data, $page_url ); ?>
        </div>
        <?php
    }

    /** Simple numbered pagination preserving current query args. */
    private function pagination( array $data, string $page_url ): void {
        $max = (int) $data['max_pages'];
        if ( $max < 2 ) {
            return;
        }
        $cur  = (int) $data['paged'];
        $args = $_GET;
        echo '<div class="tablenav"><div class="tablenav-pages" style="margin:10px 0">';
        for ( $i = 1; $i <= $max; $i++ ) {
            if ( $i === $cur ) {
                echo '<span class="button button-primary" style="margin:2px">' . (int) $i . '</span>';
            } else {
                $args['paged'] = $i;
                echo '<a class="button" style="margin:2px" href="' . esc_url( add_query_arg( $args, $page_url ) ) . '">' . (int) $i . '</a>';
            }
        }
        echo '</div></div>';
    }
}
