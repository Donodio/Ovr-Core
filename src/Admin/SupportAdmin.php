<?php
/**
 * Support Center admin (Feature 12).
 *
 * One "Support" submenu with two tabs — Tickets and Knowledge Base — plus a
 * dashboard summary. Tickets get a full lifecycle (open → in progress → waiting
 * → resolved → closed) with a reply thread; KB articles get create/edit/
 * categorize/publish/archive. Both lists use the shared ListTable engine and
 * route writes through TicketRepository / KbRepository (audit + soft-delete).
 *
 * @package OVR\Admin
 * @since   2.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Support\TicketRepository;
use OVR\Support\KbRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SupportAdmin {

    public const PAGE_SLUG = 'ovr-core-support';
    public const PER_PAGE  = 20;
    private const CAP       = 'ovr_manage_support';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_ticket_save',    [ $this, 'handle_ticket_save' ] );
        add_action( 'admin_post_ovr_ticket_reply',   [ $this, 'handle_ticket_reply' ] );
        add_action( 'admin_post_ovr_ticket_status',  [ $this, 'handle_ticket_status' ] );
        add_action( 'admin_post_ovr_ticket_delete',  [ $this, 'handle_ticket_delete' ] );
        add_action( 'admin_post_ovr_kb_save',        [ $this, 'handle_kb_save' ] );
        add_action( 'admin_post_ovr_kb_status',      [ $this, 'handle_kb_status' ] );
        add_action( 'admin_post_ovr_kb_delete',      [ $this, 'handle_kb_delete' ] );
        // Run the export before admin-header.php emits HTML, or the download
        // headers fail "headers already sent" and the CSV is appended to the page.
        add_action( 'admin_init', [ $this, 'maybe_export' ] );
    }

    /** Stream the tickets CSV export early (admin_init) so it downloads as a file. */
    public function maybe_export(): void {
        if ( ( $_GET['page'] ?? '' ) !== self::PAGE_SLUG || empty( $_GET['export_csv'] ) ) {
            return;
        }
        $this->require_cap();
        $this->export_tickets_csv();
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Support', 'ovr-core' ),
            __( 'Support', 'ovr-core' ),
            self::CAP,
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    private function require_cap(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'You do not have permission to access the Support Center.', 'ovr-core' ) );
        }
    }

    // ── Routing ──────────────────────────────────────────────────────────

    public function render(): void {
        $this->require_cap();
        $view = sanitize_key( wp_unslash( $_GET['view'] ?? '' ) );
        $tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'tickets' ) );

        if ( 'ticket' === $view ) {
            $this->render_ticket();
            return;
        }
        if ( 'new-ticket' === $view ) {
            $this->render_ticket_form();
            return;
        }
        if ( 'kb-edit' === $view || 'kb-new' === $view ) {
            $this->render_kb_form( 'kb-edit' === $view );
            return;
        }

        if ( 'kb' === $tab ) {
            $this->render_kb_list();
            return;
        }
        $this->render_tickets_list();
    }

    // ── Tickets ──────────────────────────────────────────────────────────

    private function tickets_list_table(): ListTable {
        global $wpdb;
        return new ListTable( [
            'table'       => $wpdb->prefix . 'ovr_support_tickets',
            'searchable'  => [ 'subject', 'message' ],
            'sortable'    => [ 'id', 'subject', 'priority', 'status', 'created_at', 'updated_at' ],
            'default'     => [ 'orderby' => 'updated_at', 'order' => 'DESC' ],
            'per_page'    => self::PER_PAGE,
            'soft_delete' => true,
            'filters'     => [
                'status'   => [ 'column' => 'status' ],
                'priority' => [ 'column' => 'priority' ],
                'category' => [ 'column' => 'category' ],
            ],
        ] );
    }

    private function render_tickets_list(): void {
        if ( ! empty( $_GET['export_csv'] ) ) {
            $this->export_tickets_csv();
        }

        $list = $this->tickets_list_table();
        $data = $list->query();

        TemplateLoader::render( 'admin/support.php', [
            'tab'           => 'tickets',
            'data'          => $data,
            'list'          => $list,
            'page_url'      => $this->page_url(),
            'base_url'      => $this->base_url(),
            'kb_url'        => add_query_arg( 'tab', 'kb', $this->page_url() ),
            'new_url'       => add_query_arg( 'view', 'new-ticket', $this->page_url() ),
            'csv_url'       => add_query_arg( 'export_csv', '1', $this->page_url() ),
            'stats'         => array_merge( TicketRepository::stats(), [ 'kb' => KbRepository::stats()['published'] ] ),
            'status_labels' => TicketRepository::status_labels(),
            'priorities'    => TicketRepository::PRIORITIES,
            'categories'    => TicketRepository::CATEGORIES,
            'notice'        => $this->read_notice(),
        ] );
    }

    private function render_ticket(): void {
        $id     = (int) ( $_GET['id'] ?? 0 );
        $ticket = TicketRepository::get( $id );
        if ( ! $ticket ) {
            wp_die( esc_html__( 'Ticket not found.', 'ovr-core' ) );
        }

        TemplateLoader::render( 'admin/support-ticket.php', [
            'ticket'        => $ticket,
            'replies'       => TicketRepository::get_replies( $id ),
            'requester'     => $ticket['user_id'] ? get_userdata( (int) $ticket['user_id'] ) : null,
            'status_labels' => TicketRepository::status_labels(),
            'priorities'    => TicketRepository::PRIORITIES,
            'agents'        => $this->agent_options(),
            'back_url'      => $this->page_url(),
            'action_url'    => admin_url( 'admin-post.php' ),
            'reply_nonce'   => wp_create_nonce( 'ovr_ticket_reply_' . $id ),
            'status_nonce'  => wp_create_nonce( 'ovr_ticket_status_' . $id ),
            'notice'        => $this->read_notice(),
        ] );
    }

    private function render_ticket_form(): void {
        TemplateLoader::render( 'admin/ticket-form.php', [
            'categories' => TicketRepository::CATEGORIES,
            'priorities' => TicketRepository::PRIORITIES,
            'users'      => $this->user_options(),
            'back_url'   => $this->page_url(),
            'action_url' => admin_url( 'admin-post.php' ),
            'nonce'      => wp_create_nonce( 'ovr_ticket_save' ),
        ] );
    }

    public function handle_ticket_save(): void {
        $this->require_cap_post();
        check_admin_referer( 'ovr_ticket_save' );

        $data = [
            'user_id'  => (int) ( $_POST['user_id'] ?? 0 ),
            'subject'  => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
            'category' => sanitize_key( wp_unslash( $_POST['category'] ?? 'general' ) ),
            'priority' => sanitize_key( wp_unslash( $_POST['priority'] ?? 'normal' ) ),
            'message'  => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
        ];
        $user = get_userdata( (int) ( $_POST['user_id'] ?? 0 ) );
        if ( '' === $data['subject'] || '' === $data['message'] ) {
            wp_safe_redirect( add_query_arg( [ 'view' => 'new-ticket', 'msg' => 'invalid' ], $this->page_url() ) );
            exit;
        }
        $id = TicketRepository::create( $data );
        do_action( 'ovr_support_ticket_created', $id, [
            'subject'   => $data['subject'] ?? '',
            'message'   => $data['message'] ?? '',
            'user_name' => $user ? $user->display_name : '',
            'user_email'=> $user ? $user->user_email : '',
        ] );
        wp_safe_redirect( add_query_arg( [ 'view' => 'ticket', 'id' => $id, 'msg' => 'created' ], $this->page_url() ) );
        exit;
    }

    public function handle_ticket_reply(): void {
        $this->require_cap_post();
        $id = (int) ( $_POST['ticket_id'] ?? 0 );
        check_admin_referer( 'ovr_ticket_reply_' . $id );

        $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
        if ( '' !== $message ) {
            TicketRepository::add_reply( $id, $message, true );
            do_action( 'ovr_support_ticket_reply', $id, $message );
            // A staff reply moves an untouched/open ticket into progress.
            $ticket = TicketRepository::get( $id );
            if ( $ticket && 'open' === $ticket['status'] ) {
                TicketRepository::set_status( $id, 'in_progress' );
            }
        }
        wp_safe_redirect( add_query_arg( [ 'view' => 'ticket', 'id' => $id, 'msg' => 'replied' ], $this->page_url() ) );
        exit;
    }

    public function handle_ticket_status(): void {
        $this->require_cap_post();
        $id = (int) ( $_POST['ticket_id'] ?? 0 );
        check_admin_referer( 'ovr_ticket_status_' . $id );

        TicketRepository::update( $id, [
            'status'      => sanitize_key( wp_unslash( $_POST['status'] ?? 'open' ) ),
            'priority'    => sanitize_key( wp_unslash( $_POST['priority'] ?? 'normal' ) ),
            'assigned_to' => (int) ( $_POST['assigned_to'] ?? 0 ),
        ] );
        wp_safe_redirect( add_query_arg( [ 'view' => 'ticket', 'id' => $id, 'msg' => 'updated' ], $this->page_url() ) );
        exit;
    }

    public function handle_ticket_delete(): void {
        $this->require_cap_post();
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_ticket_delete_' . $id );
        TicketRepository::trash( $id );
        wp_safe_redirect( add_query_arg( 'msg', 'deleted', $this->page_url() ) );
        exit;
    }

    private function export_tickets_csv(): void {
        $this->tickets_list_table()->export_csv(
            'ovr-tickets',
            [
                __( 'ID', 'ovr-core' )       => 'id',
                __( 'Subject', 'ovr-core' )  => 'subject',
                __( 'Category', 'ovr-core' ) => 'category',
                __( 'Priority', 'ovr-core' ) => 'priority',
                __( 'Status', 'ovr-core' )   => 'status',
                __( 'Requester', 'ovr-core' )=> 'user_id',
                __( 'Created', 'ovr-core' )  => 'created_at',
                __( 'Updated', 'ovr-core' )  => 'updated_at',
            ],
            static function ( array $r ): array {
                return [
                    $r['id'], $r['subject'], $r['category'], $r['priority'], $r['status'],
                    $r['user_id'] ? get_the_author_meta( 'display_name', (int) $r['user_id'] ) : '—',
                    $r['created_at'], $r['updated_at'],
                ];
            }
        );
    }

    // ── Knowledge Base ───────────────────────────────────────────────────

    private function kb_list_table(): ListTable {
        global $wpdb;
        return new ListTable( [
            'table'       => $wpdb->prefix . 'ovr_kb_articles',
            'searchable'  => [ 'title', 'body' ],
            'sortable'    => [ 'id', 'title', 'category', 'status', 'sort_order', 'updated_at' ],
            'default'     => [ 'orderby' => 'sort_order', 'order' => 'ASC' ],
            'per_page'    => self::PER_PAGE,
            'soft_delete' => true,
            'filters'     => [
                'status'   => [ 'column' => 'status' ],
                'category' => [ 'column' => 'category' ],
            ],
        ] );
    }

    private function render_kb_list(): void {
        $list = $this->kb_list_table();
        $data = $list->query();

        TemplateLoader::render( 'admin/support.php', [
            'tab'           => 'kb',
            'data'          => $data,
            'list'          => $list,
            'page_url'      => $this->page_url(),
            'base_url'      => add_query_arg( 'tab', 'kb', $this->base_url() ),
            'tickets_url'   => $this->page_url(),
            'new_url'       => add_query_arg( 'view', 'kb-new', $this->page_url() ),
            'stats'         => array_merge( TicketRepository::stats(), [ 'kb' => KbRepository::stats()['published'] ] ),
            'kb_statuses'   => KbRepository::status_labels(),
            'categories'    => KbRepository::CATEGORIES,
            'notice'        => $this->read_notice(),
        ] );
    }

    private function render_kb_form( bool $is_edit ): void {
        $article = null;
        if ( $is_edit ) {
            $article = KbRepository::get( (int) ( $_GET['id'] ?? 0 ) );
            if ( ! $article ) {
                wp_die( esc_html__( 'Article not found.', 'ovr-core' ) );
            }
        }
        TemplateLoader::render( 'admin/kb-form.php', [
            'article'    => $article,
            'is_edit'    => $is_edit,
            'statuses'   => KbRepository::status_labels(),
            'categories' => KbRepository::CATEGORIES,
            'back_url'   => add_query_arg( 'tab', 'kb', $this->page_url() ),
            'action_url' => admin_url( 'admin-post.php' ),
            'nonce'      => wp_create_nonce( 'ovr_kb_save' ),
        ] );
    }

    public function handle_kb_save(): void {
        $this->require_cap_post();
        check_admin_referer( 'ovr_kb_save' );

        $id   = (int) ( $_POST['article_id'] ?? 0 );
        $data = [
            'title'      => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            'category'   => sanitize_title( wp_unslash( $_POST['category'] ?? 'general' ) ),
            'body'       => wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) ),
            'status'     => sanitize_key( wp_unslash( $_POST['status'] ?? 'draft' ) ),
            'sort_order' => (int) ( $_POST['sort_order'] ?? 0 ),
        ];
        if ( '' === $data['title'] ) {
            wp_safe_redirect( add_query_arg( [ 'view' => 'kb-new', 'msg' => 'invalid' ], $this->page_url() ) );
            exit;
        }
        KbRepository::save( $data, $id );
        wp_safe_redirect( add_query_arg( [ 'tab' => 'kb', 'msg' => $id ? 'kb_updated' : 'kb_created' ], $this->page_url() ) );
        exit;
    }

    public function handle_kb_status(): void {
        $this->require_cap_post();
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_kb_status_' . $id );
        KbRepository::set_status( $id, sanitize_key( wp_unslash( $_GET['status'] ?? 'draft' ) ) );
        wp_safe_redirect( add_query_arg( [ 'tab' => 'kb', 'msg' => 'kb_updated' ], $this->page_url() ) );
        exit;
    }

    public function handle_kb_delete(): void {
        $this->require_cap_post();
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'ovr_kb_delete_' . $id );
        KbRepository::trash( $id );
        wp_safe_redirect( add_query_arg( [ 'tab' => 'kb', 'msg' => 'kb_deleted' ], $this->page_url() ) );
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function require_cap_post(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( '403' );
        }
    }

    /**
     * Staff/admin users for the "assigned to" dropdown.
     *
     * @return array<int, string>
     */
    private function agent_options(): array {
        $users   = get_users( [ 'role__in' => [ 'administrator', 'ovr_support' ], 'fields' => [ 'ID', 'display_name' ] ] );
        $options = [];
        foreach ( $users as $u ) {
            $options[ (int) $u->ID ] = $u->display_name;
        }
        return $options;
    }

    /**
     * Recent users for the "requester" dropdown on a new ticket.
     *
     * @return array<int, string>
     */
    private function user_options(): array {
        $users   = get_users( [ 'number' => 200, 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => [ 'ID', 'display_name', 'user_email' ] ] );
        $options = [];
        foreach ( $users as $u ) {
            $options[ (int) $u->ID ] = $u->display_name . ' (' . $u->user_email . ')';
        }
        return $options;
    }

    private function read_notice(): ?array {
        if ( empty( $_GET['msg'] ) ) {
            return null;
        }
        $map = [
            'created'    => [ 'success', __( 'Ticket created.', 'ovr-core' ) ],
            'updated'    => [ 'success', __( 'Ticket updated.', 'ovr-core' ) ],
            'replied'    => [ 'success', __( 'Reply added.', 'ovr-core' ) ],
            'deleted'    => [ 'success', __( 'Ticket moved to trash.', 'ovr-core' ) ],
            'invalid'    => [ 'error', __( 'Subject and message are required.', 'ovr-core' ) ],
            'kb_created' => [ 'success', __( 'Article created.', 'ovr-core' ) ],
            'kb_updated' => [ 'success', __( 'Article updated.', 'ovr-core' ) ],
            'kb_deleted' => [ 'success', __( 'Article moved to trash.', 'ovr-core' ) ],
        ];
        $key = sanitize_key( wp_unslash( $_GET['msg'] ) );
        if ( ! isset( $map[ $key ] ) ) {
            return null;
        }
        return [ 'type' => $map[ $key ][0], 'text' => $map[ $key ][1] ];
    }

    private function base_url(): string {
        return add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
            'tab'       => 'tickets',
        ], admin_url( 'edit.php' ) );
    }

    private function page_url(): string {
        return ListTable::preserve_url( $this->base_url() );
    }
}
