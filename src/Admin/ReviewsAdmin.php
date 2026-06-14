<?php
/**
 * Reviews & Comments Management — admin moderation screen.
 *
 * Adds a "Reviews" submenu under the OVR Properties menu where the site owner
 * approves, rejects, edits, or deletes guest reviews. Reviews flow in as
 * 'pending' and only become publicly visible once approved, so this screen is
 * the gate for the platform's reputation management.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Property\Reviews;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ReviewsAdmin {

    public const PAGE_SLUG = 'ovr-reviews';

    private const NONCE_ACTION = 'ovr_reviews_moderation';
    private const PER_PAGE      = 12;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
    }

    public function register_page(): void {
        $pending = Reviews::count_by_status()['pending'] ?? 0;

        $title = __( 'Reviews', 'ovr-core' );
        if ( $pending > 0 ) {
            $title .= ' <span class="awaiting-mod"><span class="pending-count">' . number_format_i18n( $pending ) . '</span></span>';
        }

        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Reviews & Comments', 'ovr-core' ),
            $title,
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    /**
     * Process moderation POSTs (approve/reject/delete/bulk/edit) and redirect
     * back to the screen with a result notice. Runs early so the redirect
     * happens before any output.
     */
    public function handle_actions(): void {
        if ( empty( $_POST['ovr_reviews_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( self::NONCE_ACTION );

        $action  = sanitize_key( wp_unslash( $_POST['ovr_reviews_action'] ) );
        $msg     = '';
        $count   = 0;

        switch ( $action ) {
            case 'single':
                $id  = absint( $_POST['review_id'] ?? 0 );
                $op  = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
                if ( $id && 'delete' === $op ) {
                    $count = Reviews::delete( $id ) ? 1 : 0;
                    $msg   = 'deleted';
                } elseif ( $id && in_array( $op, [ 'approve', 'reject' ], true ) ) {
                    $status = 'approve' === $op ? 'approved' : 'rejected';
                    $count  = Reviews::set_status( $id, $status ) ? 1 : 0;
                    $msg    = $status;
                }
                break;

            case 'bulk':
                $ids = array_map( 'absint', (array) ( $_POST['ids'] ?? [] ) );
                $ids = array_filter( $ids );
                $op  = sanitize_key( wp_unslash( $_POST['bulk_op'] ?? '' ) );
                if ( $ids && 'delete' === $op ) {
                    foreach ( $ids as $id ) {
                        $count += Reviews::delete( $id ) ? 1 : 0;
                    }
                    $msg = 'deleted';
                } elseif ( $ids && in_array( $op, [ 'approve', 'reject' ], true ) ) {
                    $status = 'approve' === $op ? 'approved' : 'rejected';
                    $count  = Reviews::bulk_set_status( $ids, $status );
                    $msg    = $status;
                }
                break;

            case 'save_edit':
                $id     = absint( $_POST['review_id'] ?? 0 );
                $rating = absint( $_POST['rating'] ?? 0 );
                $body   = (string) ( $_POST['body'] ?? '' );
                $title  = (string) ( $_POST['title'] ?? '' );
                if ( $id ) {
                    $count = Reviews::update_content( $id, $rating, $body, $title ) ? 1 : 0;
                    $msg   = 'saved';
                }
                break;
        }

        wp_safe_redirect( $this->redirect_url( $msg, $count ) );
        exit;
    }

    /**
     * Build the post-action redirect, preserving the active tab and page while
     * carrying a result code, and dropping any stale ?edit= context.
     */
    private function redirect_url( string $msg, int $count ): string {
        $args = [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
        ];
        $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'all';
        $paged  = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1;
        if ( $status && 'all' !== $status ) { $args['status'] = $status; }
        if ( $paged > 1 ) { $args['paged'] = $paged; }
        if ( $msg ) {
            $args['ovr_msg']   = $msg;
            $args['ovr_count'] = $count;
        }
        return add_query_arg( $args, admin_url( 'edit.php' ) );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
        if ( ! in_array( $status, [ 'all', 'pending', 'approved', 'rejected' ], true ) ) {
            $status = 'all';
        }
        $paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

        $list   = Reviews::get_admin_list( $status, $paged, self::PER_PAGE );
        $counts = Reviews::count_by_status();

        $editing = null;
        if ( isset( $_GET['edit'] ) ) {
            $editing = Reviews::get( absint( $_GET['edit'] ) );
        }

        $base_url = add_query_arg(
            [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG ],
            admin_url( 'edit.php' )
        );

        TemplateLoader::render( 'admin/reviews.php', [
            'rows'        => $list['rows'],
            'total'       => $list['total'],
            'pages'       => $list['pages'],
            'paged'       => $paged,
            'status'      => $status,
            'counts'      => $counts,
            'editing'     => $editing,
            'base_url'    => $base_url,
            'nonce_action' => self::NONCE_ACTION,
            'notice'      => $this->read_notice(),
        ] );
    }

    /**
     * Translate the ?ovr_msg=… result code into a human notice.
     *
     * @return array{type:string, text:string}|null
     */
    private function read_notice(): ?array {
        if ( empty( $_GET['ovr_msg'] ) ) {
            return null;
        }
        $msg   = sanitize_key( wp_unslash( $_GET['ovr_msg'] ) );
        $count = isset( $_GET['ovr_count'] ) ? absint( $_GET['ovr_count'] ) : 0;

        switch ( $msg ) {
            case 'approved':
                return [ 'type' => 'success', 'text' => sprintf( _n( '%s review approved and is now live.', '%s reviews approved and are now live.', $count, 'ovr-core' ), number_format_i18n( $count ) ) ];
            case 'rejected':
                return [ 'type' => 'success', 'text' => sprintf( _n( '%s review rejected.', '%s reviews rejected.', $count, 'ovr-core' ), number_format_i18n( $count ) ) ];
            case 'deleted':
                return [ 'type' => 'success', 'text' => sprintf( _n( '%s review permanently deleted.', '%s reviews permanently deleted.', $count, 'ovr-core' ), number_format_i18n( $count ) ) ];
            case 'saved':
                return $count
                    ? [ 'type' => 'success', 'text' => __( 'Review updated.', 'ovr-core' ) ]
                    : [ 'type' => 'error', 'text' => __( 'Could not update review — content cannot be empty.', 'ovr-core' ) ];
        }
        return null;
    }
}
