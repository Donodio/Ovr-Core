<?php
/**
 * Global admin search (Milestone 3 Feature 1).
 *
 * One search box that spans the platform: users, listings (incl. property IDs),
 * payments / transaction IDs, reviews, inquiries, and memberships. Results are
 * grouped by entity with deep links into the relevant admin screen.
 *
 * @package OVR\Admin
 * @since   2.3.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GlobalSearch {

    public const PAGE_SLUG = 'ovr-core-search';

    /** Max rows per result group. */
    private const PER_GROUP = 15;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Global Search', 'ovr-core' ),
            __( 'Search', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    private function page_url(): string {
        return add_query_arg( [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG ], admin_url( 'edit.php' ) );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to search.', 'ovr-core' ) );
        }
        $term   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $groups = ( '' !== $term ) ? $this->search( $term ) : [];
        ?>
        <div class="wrap ovr-adm">
            <style>#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}</style>
            <div class="ovr-adm-wrap">
                <div class="ovr-adm-head">
                    <div>
                        <h1><?php esc_html_e( 'Global Search', 'ovr-core' ); ?></h1>
                        <p><?php esc_html_e( 'Search across listings, members, payments, reviews and inquiries from one place.', 'ovr-core' ); ?></p>
                    </div>
                </div>

                <div class="ovr-adm-card">
                    <div class="ovr-adm-toolbar">
                        <form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
                            <input type="hidden" name="post_type" value="ovr_property">
                            <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
                            <div class="ovr-adm-search">
                                <span class="material-symbols-outlined">search</span>
                                <input type="search" name="s" value="<?php echo esc_attr( $term ); ?>"
                                       placeholder="<?php esc_attr_e( 'Search users, listings, payments, reviews, inquiries, IDs…', 'ovr-core' ); ?>">
                            </div>
                            <button type="submit" class="ovr-adm-btn ovr-adm-btn--primary"><span class="material-symbols-outlined">search</span><?php esc_html_e( 'Search', 'ovr-core' ); ?></button>
                        </form>
                    </div>
                </div>

                <?php if ( '' === $term ) : ?>
                    <div class="ovr-adm-card">
                        <div class="ovr-adm-empty">
                            <span class="material-symbols-outlined">manage_search</span>
                            <h3><?php esc_html_e( 'Enter a search query', 'ovr-core' ); ?></h3>
                            <p><?php esc_html_e( 'Enter a name, email, property ID, transaction ID, or keyword.', 'ovr-core' ); ?></p>
                        </div>
                    </div>
                <?php else :
                    $any = false;
                    foreach ( $groups as $group ) :
                        if ( empty( $group['rows'] ) ) {
                            continue;
                        }
                        $any = true;
                        ?>
                        <div class="ovr-adm-card">
                            <div class="ovr-adm-card-head">
                                <h2><?php echo esc_html( $group['label'] ); ?></h2>
                                <span class="ovr-adm-count"><?php echo (int) count( $group['rows'] ); ?></span>
                            </div>
                            <table class="ovr-adm-table">
                                <tbody>
                                <?php foreach ( $group['rows'] as $row ) : ?>
                                    <tr>
                                        <td style="width:60%">
                                            <?php if ( ! empty( $row['url'] ) ) : ?>
                                                <a href="<?php echo esc_url( $row['url'] ); ?>"><span class="ovr-adm-name"><?php echo esc_html( $row['title'] ); ?></span></a>
                                            <?php else : ?>
                                                <span class="ovr-adm-name"><?php echo esc_html( $row['title'] ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="ovr-adm-sub"><?php echo esc_html( $row['meta'] ); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <?php if ( ! $any ) : ?>
                        <div class="ovr-adm-card">
                            <div class="ovr-adm-empty">
                                <span class="material-symbols-outlined">search_off</span>
                                <h3>
                                    <?php
                                    /* translators: %s: term */
                                    printf( esc_html__( 'No results for "%s"', 'ovr-core' ), esc_html( $term ) );
                                    ?>
                                </h3>
                                <p><?php esc_html_e( 'Try a different name, email, ID, or keyword.', 'ovr-core' ); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Run the cross-entity search.
     *
     * @return array<int, array{label:string, rows:array<int, array{title:string, meta:string, url:string}>}>
     */
    private function search( string $term ): array {
        global $wpdb;
        $like   = '%' . $wpdb->esc_like( $term ) . '%';
        $is_num = ctype_digit( $term );
        $groups = [];

        // ── Listings (title or exact property ID) ──
        $rows = [];
        if ( $is_num && ( $p = get_post( (int) $term ) ) && 'ovr_property' === $p->post_type ) {
            $rows[] = [ 'title' => $p->post_title ?: ( '#' . $p->ID ), 'meta' => 'ID ' . $p->ID, 'url' => (string) get_edit_post_link( $p->ID, '' ) ];
        }
        $listing_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type='ovr_property' AND post_status IN ('publish','draft','pending','trash') AND post_title LIKE %s ORDER BY post_date DESC LIMIT %d",
            $like, self::PER_GROUP
        ) );
        foreach ( $listing_ids as $pid ) {
            $rows[] = [ 'title' => get_the_title( (int) $pid ) ?: ( '#' . $pid ), 'meta' => 'ID ' . (int) $pid, 'url' => (string) get_edit_post_link( (int) $pid, '' ) ];
        }
        $groups[] = [ 'label' => __( 'Listings', 'ovr-core' ), 'rows' => $rows ];

        // ── Users / Members (name, email, login) ──
        $rows  = [];
        $users = get_users( [
            'search'         => '*' . $term . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
            'number'         => self::PER_GROUP,
        ] );
        foreach ( $users as $u ) {
            $plan   = get_user_meta( $u->ID, 'ovr_plan', true ) ?: get_user_meta( $u->ID, '_ovr_plan', true );
            $rows[] = [
                'title' => $u->display_name ?: $u->user_login,
                'meta'  => $u->user_email . ( $plan ? ' · ' . $plan : '' ),
                'url'   => admin_url( 'user-edit.php?user_id=' . $u->ID ),
            ];
        }
        $groups[] = [ 'label' => __( 'Members', 'ovr-core' ), 'rows' => $rows ];

        // ── Payments / transaction IDs ──
        $rows = [];
        $pay_url = add_query_arg( [ 'post_type' => 'ovr_property', 'page' => 'ovr-core-payments' ], admin_url( 'edit.php' ) );
        $pays = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, user_id, amount, currency, transaction_id, payment_type, status, created_at
             FROM {$wpdb->prefix}ovr_payments
             WHERE transaction_id LIKE %s OR description LIKE %s" . ( $is_num ? ' OR id = %d' : '' ) . "
             ORDER BY created_at DESC LIMIT %d",
            ...( $is_num ? [ $like, $like, (int) $term, self::PER_GROUP ] : [ $like, $like, self::PER_GROUP ] )
        ), ARRAY_A );
        foreach ( (array) $pays as $pay ) {
            $sym    = ( (array) get_option( 'ovr_settings', [] ) )['currency_symbol'] ?? '$';
            $rows[] = [
                'title' => $sym . number_format( (float) $pay['amount'], 2 ) . ' · ' . $pay['payment_type'],
                'meta'  => trim( ( $pay['transaction_id'] ?: ( '#' . $pay['id'] ) ) . ' · ' . $pay['status'] ),
                'url'   => $pay_url,
            ];
        }
        $groups[] = [ 'label' => __( 'Payments', 'ovr-core' ), 'rows' => $rows ];

        // ── Reviews ──
        $rows = [];
        $rev_url = add_query_arg( [ 'post_type' => 'ovr_property', 'page' => 'ovr-core-reviews' ], admin_url( 'edit.php' ) );
        $revs = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, property_id, guest_name, guest_email, rating, status FROM {$wpdb->prefix}ovr_reviews
             WHERE guest_name LIKE %s OR guest_email LIKE %s OR body LIKE %s ORDER BY created_at DESC LIMIT %d",
            $like, $like, $like, self::PER_GROUP
        ), ARRAY_A );
        foreach ( (array) $revs as $rv ) {
            $rows[] = [
                'title' => ( $rv['guest_name'] ?: __( 'Guest', 'ovr-core' ) ) . ' — ' . get_the_title( (int) $rv['property_id'] ),
                'meta'  => $rv['rating'] . '★ · ' . $rv['status'],
                'url'   => $rev_url,
            ];
        }
        $groups[] = [ 'label' => __( 'Reviews', 'ovr-core' ), 'rows' => $rows ];

        // ── Inquiries ──
        $rows = [];
        $inqs = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, property_id, guest_name, guest_email, status FROM {$wpdb->prefix}ovr_inquiries
             WHERE guest_name LIKE %s OR guest_email LIKE %s OR message LIKE %s ORDER BY created_at DESC LIMIT %d",
            $like, $like, $like, self::PER_GROUP
        ), ARRAY_A );
        foreach ( (array) $inqs as $inq ) {
            $rows[] = [
                'title' => ( $inq['guest_name'] ?: __( 'Guest', 'ovr-core' ) ) . ' — ' . get_the_title( (int) $inq['property_id'] ),
                'meta'  => $inq['guest_email'] . ' · ' . $inq['status'],
                'url'   => (string) get_edit_post_link( (int) $inq['property_id'], '' ),
            ];
        }
        $groups[] = [ 'label' => __( 'Inquiries', 'ovr-core' ), 'rows' => $rows ];

        return $groups;
    }
}
