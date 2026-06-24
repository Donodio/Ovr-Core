<?php
/**
 * Reviews & Comments Management template — admin moderation screen.
 *
 * Bento layout: a scrollable column of review cards (left) and a sticky
 * details/editor panel (right), rendered inside the WordPress admin (which
 * supplies its own chrome, so the standalone mockup sidebar/topbar is dropped).
 *
 * @package OVR
 * @var array      $rows         Review rows (each joined to property_title).
 * @var int        $total        Total reviews in the active tab.
 * @var int        $pages        Total pages.
 * @var int        $paged        Current page.
 * @var string     $status       Active tab: all|pending|approved|rejected.
 * @var array      $counts       ['all','pending','approved','rejected'] counts.
 * @var array|null $editing      Review row currently being edited, or null.
 * @var string     $base_url     Screen base URL (post_type + page).
 * @var string     $nonce_action Nonce action for moderation forms.
 * @var array|null $notice       ['type','text'] result notice, or null.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Build a tab URL preserving nothing but the status. */
$tab_url = static function ( string $st ) use ( $base_url ): string {
    return 'all' === $st ? $base_url : add_query_arg( 'status', $st, $base_url );
};

/** Build an edit-link URL preserving the active tab + page. */
$edit_url = static function ( int $id ) use ( $base_url, $status, $paged ): string {
    $args = [ 'edit' => $id ];
    if ( 'all' !== $status ) { $args['status'] = $status; }
    if ( $paged > 1 )        { $args['paged']  = $paged; }
    return add_query_arg( $args, $base_url );
};

$status_meta = [
    'pending'  => [ 'label' => __( 'Pending', 'ovr-core' ),  'icon' => 'pending',      'accent' => '#DEAF0C', 'chip_bg' => '#fef5d6', 'chip_fg' => '#b8920a' ],
    'approved' => [ 'label' => __( 'Approved', 'ovr-core' ), 'icon' => 'check_circle', 'accent' => '#2E7D32', 'chip_bg' => '#e4f4e4', 'chip_fg' => '#2E7D32' ],
    'rejected' => [ 'label' => __( 'Rejected', 'ovr-core' ), 'icon' => 'cancel',       'accent' => '#B3261E', 'chip_bg' => '#f9e4e2', 'chip_fg' => '#B3261E' ],
];

$tabs = [
    'all'      => __( 'All Reviews', 'ovr-core' ),
    'pending'  => __( 'Pending Approval', 'ovr-core' ),
    'approved' => __( 'Approved', 'ovr-core' ),
    'rejected' => __( 'Rejected', 'ovr-core' ),
];

/** Render 5 stars, $n filled. */
$stars = static function ( int $n ): void {
    $n = max( 0, min( 5, $n ) );
    for ( $i = 1; $i <= 5; $i++ ) {
        printf(
            '<span class="material-symbols-outlined%s">star</span>',
            $i <= $n ? ' filled' : ''
        );
    }
};

/** Initials for the avatar bubble. */
$initials = static function ( string $name ): string {
    $name  = trim( $name );
    if ( '' === $name ) { return '?'; }
    $parts = preg_split( '/\s+/', $name );
    $first = mb_substr( $parts[0], 0, 1 );
    $last  = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';
    return mb_strtoupper( $first . $last );
};
?>
<div class="wrap ovr-rev">

    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}
        #wpcontent{padding-left:0}
        .ovr-rev{--p:#000961;--p-hover:#000740;--pc:#000961;--p-light:#e8eaf3;--blue:#00A2E8;--blue-light:#e5f5fe;--gold:#DEAF0C;--gold-dark:#b8920a;--gold-light:#fef5d6;--sec:#2E7D32;--sec-dark:#1f5d23;--secc:#e4f4e4;--ter:#b8920a;--terc:#DEAF0C;--err:#B3261E;--errc:#f9e4e2;--surf:#fff;--sv:#5F6B7A;--ov:#DBDBDB;--on:#1C2430;font-family:'Inter',system-ui,sans-serif;max-width:none;margin:20px 0 56px;padding:0 40px;color:var(--on)}
        .ovr-rev,.ovr-rev *{box-sizing:border-box}
        .ovr-rev .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;line-height:1}
        .ovr-rev .material-symbols-outlined.filled{font-variation-settings:'FILL' 1}

        /* Notice */
        .ovr-rev-notice{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:8px;font-size:14px;font-weight:500;margin:0 0 22px}
        .ovr-rev-notice .material-symbols-outlined{font-size:20px}
        .ovr-rev-notice--success{background:var(--secc);border:1px solid #b8d8b8;color:var(--sec)}
        .ovr-rev-notice--error{background:var(--errc);border:1px solid #e6b8b4;color:var(--err)}

        /* Header */
        .ovr-rev-head{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;flex-wrap:wrap;margin:6px 0 26px}
        .ovr-rev-head h1{font-size:30px;font-weight:700;letter-spacing:-.01em;margin:0;padding:0;line-height:1.2;color:var(--on)}
        .ovr-rev-head p{margin:7px 0 0;color:var(--sv);font-size:15px;max-width:560px}
        .ovr-rev-head p em{color:var(--p);font-style:normal;font-weight:600}
        .ovr-rev-bulk{display:flex;gap:10px;flex-wrap:wrap;margin:0}
        .ovr-btn{display:inline-flex;align-items:center;gap:7px;padding:0 20px;min-height:46px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;line-height:1;border:1px solid transparent;cursor:pointer;font-family:inherit;transition:all .2s}
        .ovr-btn .material-symbols-outlined{font-size:18px}
        .ovr-btn--primary{background:var(--gold);color:var(--p);border-color:var(--gold);box-shadow:0 2px 8px rgba(222,175,12,.25)}
        .ovr-btn--primary:hover{background:var(--gold-dark);color:var(--p)}
        .ovr-btn--ghost{background:var(--surf);color:var(--p);border-color:var(--ov);box-shadow:0 1px 3px rgba(0,9,97,.06)}
        .ovr-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-btn--danger{background:var(--surf);color:var(--err);border-color:var(--ov)}
        .ovr-btn--danger:hover{background:var(--errc);border-color:var(--err);color:var(--err)}
        .ovr-btn:disabled{opacity:.5;cursor:not-allowed}

        /* Tabs — navy pills (shared OVR admin convention) */
        .ovr-rev-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 26px}
        .ovr-rev-tab{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:9999px;font-size:14px;font-weight:600;color:var(--sv);background:var(--surf);border:1px solid var(--ov);text-decoration:none;white-space:nowrap}
        .ovr-rev-tab:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-rev-tab.is-active{color:#fff;background:var(--p);border-color:var(--p)}
        .ovr-rev-tab.is-active:hover{color:#fff}
        .ovr-rev-tab .ovr-rev-badge{background:var(--gold);color:var(--p);font-size:11px;font-weight:700;padding:2px 9px;border-radius:9999px;line-height:1.5}
        .ovr-rev-tab.is-active .ovr-rev-badge{background:rgba(255,255,255,.2);color:#fff}

        /* Bento grid */
        .ovr-rev-grid{display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start}
        .ovr-rev-list{display:flex;flex-direction:column;gap:16px;min-width:0}

        /* Review card */
        .ovr-rc{position:relative;background:var(--surf);border:1px solid var(--ov);border-radius:12px;padding:24px 24px 24px 28px;box-shadow:0 4px 12px rgba(0,9,97,.06);transition:box-shadow .25s;overflow:hidden}
        .ovr-rc:hover{box-shadow:0 8px 32px rgba(0,9,97,.1)}
        .ovr-rc-accent{position:absolute;top:0;left:0;width:4px;height:100%}
        .ovr-rc-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px}
        .ovr-rc-id{display:flex;align-items:center;gap:12px;min-width:0}
        .ovr-rc-check{margin:4px 0 0;width:17px;height:17px;flex-shrink:0;accent-color:var(--p);cursor:pointer}
        .ovr-rc-av{width:46px;height:46px;border-radius:50%;background:var(--p-light);color:var(--p);font-weight:700;font-size:15px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .ovr-rc-name{font-size:18px;font-weight:700;margin:0;color:var(--on);line-height:1.2}
        .ovr-rc-meta{font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--sv);margin:5px 0 0}
        .ovr-rc-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:9999px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;white-space:nowrap;flex-shrink:0}
        .ovr-rc-chip .material-symbols-outlined{font-size:14px}
        .ovr-rc-stars{display:flex;gap:1px;color:var(--terc);margin-bottom:10px}
        .ovr-rc-stars .material-symbols-outlined{font-size:20px}
        .ovr-rc-title{font-size:15px;font-weight:700;margin:0 0 4px;color:var(--on)}
        .ovr-rc-body{font-size:14px;line-height:1.6;color:var(--sv);margin:0 0 18px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
        .ovr-rc-acts{display:flex;gap:10px;flex-wrap:wrap;margin:0}
        .ovr-rc-acts form{margin:0;display:contents}
        .ovr-cbtn{flex:1 1 120px;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:600;line-height:1;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;transition:all .18s}
        .ovr-cbtn .material-symbols-outlined{font-size:17px}
        .ovr-cbtn--edit{background:var(--surf);color:var(--p);border-color:var(--ov)}
        .ovr-cbtn--edit:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-cbtn--edit.is-active{background:var(--p);color:#fff;border-color:var(--p)}
        .ovr-cbtn--approve{background:var(--sec);color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.08)}
        .ovr-cbtn--approve:hover{background:var(--sec-dark);color:#fff}
        .ovr-cbtn--reject{background:var(--surf);color:var(--err);border-color:var(--err)}
        .ovr-cbtn--reject:hover{background:var(--errc);color:var(--err)}

        /* Empty state */
        .ovr-rev-empty{background:var(--surf);border:1px dashed var(--ov);border-radius:12px;padding:64px 24px;text-align:center;color:var(--sv)}
        .ovr-rev-empty .material-symbols-outlined{font-size:44px;color:var(--ov);display:block;margin:0 auto 12px}
        .ovr-rev-empty h3{font-size:18px;font-weight:600;color:var(--on);margin:0 0 6px}
        .ovr-rev-empty p{margin:0;font-size:14px}

        /* Pagination */
        .ovr-rev-pager{display:flex;gap:6px;justify-content:center;align-items:center;margin-top:22px;flex-wrap:wrap}
        .ovr-rev-pager a,.ovr-rev-pager span{min-width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;padding:0 12px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid var(--ov);color:var(--sv);background:var(--surf)}
        .ovr-rev-pager a:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-rev-pager .is-current{background:var(--p);color:#fff;border-color:var(--p)}
        .ovr-rev-pager .is-disabled{opacity:.4;pointer-events:none}

        /* Sidebar */
        .ovr-rev-side{position:sticky;top:46px;background:var(--surf);border:1px solid var(--ov);border-radius:12px;box-shadow:0 4px 12px rgba(0,9,97,.06);padding:24px}
        .ovr-side-h{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .ovr-side-h h2{font-size:20px;font-weight:700;margin:0;padding:0;color:var(--on)}
        .ovr-side-del{background:none;border:none;color:var(--sv);cursor:pointer;padding:4px;border-radius:6px;display:inline-flex}
        .ovr-side-del:hover{color:var(--err);background:var(--errc)}
        .ovr-side-ctx{background:var(--p-light);border-radius:8px;padding:14px 16px;margin-bottom:20px}
        .ovr-side-ctx-lbl{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);margin:0 0 6px}
        .ovr-side-ctx-lbl .material-symbols-outlined{font-size:18px;color:var(--p)}
        .ovr-side-ctx p{margin:0;font-size:14px;font-weight:600;color:var(--on)}
        .ovr-fld{margin-bottom:18px}
        .ovr-fld>label{display:block;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);margin-bottom:8px}
        .ovr-fld textarea{width:100%;background:#fff;border:1px solid var(--ov);border-radius:8px;padding:12px;font-size:14px;line-height:1.6;color:var(--on);font-family:inherit;resize:vertical;outline:none}
        .ovr-fld textarea:focus,.ovr-fld input[type=text]:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.12)}
        .ovr-fld input[type=text]{width:100%;background:#fff;border:1px solid var(--ov);border-radius:8px;padding:10px 12px;font-size:14px;color:var(--on);font-family:inherit;outline:none}

        /* No-JS star rating editor (reverse-order trick) */
        .ovr-stars-input{display:inline-flex;flex-direction:row-reverse;justify-content:flex-end}
        .ovr-stars-input input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
        .ovr-stars-input label{cursor:pointer;color:var(--ov);padding:0 1px;line-height:1}
        .ovr-stars-input label .material-symbols-outlined{font-size:30px}
        .ovr-stars-input input:checked ~ label .material-symbols-outlined,
        .ovr-stars-input label:hover .material-symbols-outlined,
        .ovr-stars-input label:hover ~ label .material-symbols-outlined{font-variation-settings:'FILL' 1;color:var(--terc)}
        .ovr-stars-input input:focus-visible ~ label .material-symbols-outlined{outline:2px solid var(--p);border-radius:4px}

        .ovr-side-status{padding-top:18px;border-top:1px solid #eceeed}
        .ovr-side-toggle{display:flex;gap:10px}
        .ovr-side-toggle button{flex:1;padding:10px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;border:1px solid transparent}
        .ovr-st-approve{background:var(--sec);color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.08)}
        .ovr-st-approve:hover{background:var(--sec-dark)}
        .ovr-st-approve.is-current{outline:2px solid var(--sec-dark);outline-offset:2px}
        .ovr-st-reject{background:var(--surf);color:var(--err);border-color:var(--err)}
        .ovr-st-reject:hover{background:var(--errc)}
        .ovr-st-reject.is-current{outline:2px solid var(--err);outline-offset:2px}
        .ovr-side-note{font-size:12px;color:var(--sv);margin:12px 0 0;text-align:center}
        .ovr-side-save{width:100%;margin-top:18px;justify-content:center}

        /* Details placeholder (no review selected) */
        .ovr-side-stats{list-style:none;margin:0;padding:0}
        .ovr-side-stats li{display:flex;justify-content:space-between;align-items:center;padding:13px 0;border-bottom:1px solid #f1f3f2;font-size:14px;color:var(--sv)}
        .ovr-side-stats li:last-child{border-bottom:none}
        .ovr-side-stats b{font-size:18px;font-weight:700;color:var(--on)}
        .ovr-side-hint{display:flex;gap:10px;align-items:flex-start;background:#f1f4f3;border-radius:12px;padding:14px 16px;margin-top:18px;font-size:13px;line-height:1.5;color:var(--sv)}
        .ovr-side-hint .material-symbols-outlined{font-size:20px;color:var(--p);flex-shrink:0}

        @media (max-width:1100px){
            .ovr-rev-grid{grid-template-columns:1fr}
            .ovr-rev-side{position:static;order:-1}
        }
        @media (max-width:782px){
            .ovr-rev{padding:0 12px}
        }
        @media (max-width:600px){
            .ovr-rev-head h1{font-size:25px}
            .ovr-rev-bulk{width:100%}
            .ovr-rev-bulk .ovr-btn{flex:1;justify-content:center}
            .ovr-rev-tabs{gap:16px}
            .ovr-rev-tab{font-size:15px}
            .ovr-rc{padding:18px 18px 18px 22px}
            .ovr-cbtn{flex:1 1 100%}
        }
    </style>

    <?php if ( $notice ) : ?>
        <div class="ovr-rev-notice ovr-rev-notice--<?php echo esc_attr( $notice['type'] ); ?>">
            <span class="material-symbols-outlined"><?php echo 'success' === $notice['type'] ? 'check_circle' : 'error'; ?></span>
            <span><?php echo esc_html( $notice['text'] ); ?></span>
        </div>
    <?php endif; ?>

    <div class="ovr-rev-head">
        <div>
            <h1><?php esc_html_e( 'Reviews & Comments', 'ovr-core' ); ?></h1>
            <p>
                <?php esc_html_e( 'Manage guest feedback across all properties.', 'ovr-core' ); ?>
                <em><?php esc_html_e( 'Only approved reviews are shown publicly.', 'ovr-core' ); ?></em>
            </p>
        </div>

        <!-- Bulk-action form: empty wrapper; card checkboxes attach via form="ovr-rev-bulk". -->
        <form method="post" id="ovr-rev-bulk" class="ovr-rev-bulk">
            <?php wp_nonce_field( $nonce_action ); ?>
            <input type="hidden" name="ovr_reviews_action" value="bulk">
            <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
            <input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
            <button type="submit" name="bulk_op" value="reject" class="ovr-btn ovr-btn--ghost">
                <span class="material-symbols-outlined">block</span><?php esc_html_e( 'Bulk Reject', 'ovr-core' ); ?>
            </button>
            <button type="submit" name="bulk_op" value="approve" class="ovr-btn ovr-btn--primary">
                <span class="material-symbols-outlined">done_all</span><?php esc_html_e( 'Bulk Approve', 'ovr-core' ); ?>
            </button>
        </form>
    </div>

    <div class="ovr-rev-tabs">
        <?php foreach ( $tabs as $key => $label ) : ?>
            <a class="ovr-rev-tab<?php echo $status === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $tab_url( $key ) ); ?>">
                <?php echo esc_html( $label ); ?>
                <?php if ( 'pending' === $key && $counts['pending'] > 0 ) : ?>
                    <span class="ovr-rev-badge"><?php echo esc_html( number_format_i18n( $counts['pending'] ) ); ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="ovr-rev-grid">

        <!-- Review list -->
        <div class="ovr-rev-list">
            <?php if ( empty( $rows ) ) : ?>
                <div class="ovr-rev-empty">
                    <span class="material-symbols-outlined">reviews</span>
                    <h3><?php esc_html_e( 'No reviews here', 'ovr-core' ); ?></h3>
                    <p>
                        <?php
                        echo 'all' === $status
                            ? esc_html__( 'When guests leave reviews, they will appear here for moderation.', 'ovr-core' )
                            : esc_html__( 'No reviews match this filter yet.', 'ovr-core' );
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <?php foreach ( $rows as $r ) :
                    $rid      = (int) $r['id'];
                    $st       = (string) $r['status'];
                    $meta     = $status_meta[ $st ] ?? $status_meta['pending'];
                    $prop     = $r['property_title'] ? $r['property_title'] : __( '(deleted property)', 'ovr-core' );
                    $when     = $r['created_at'] ? date_i18n( 'M j, Y', strtotime( $r['created_at'] . ' UTC' ) ) : '';
                    $approved = ! empty( $r['approved_at'] ) ? date_i18n( 'M j, Y', strtotime( $r['approved_at'] . ' UTC' ) ) : '';
                    $stayed   = ! empty( $r['stay_date'] ) ? date_i18n( 'M Y', strtotime( (string) $r['stay_date'] ) ) : '';
                    $is_edit  = $editing && (int) $editing['id'] === $rid;
                ?>
                    <div class="ovr-rc">
                        <span class="ovr-rc-accent" style="background:<?php echo esc_attr( $meta['accent'] ); ?>"></span>

                        <div class="ovr-rc-top">
                            <div class="ovr-rc-id">
                                <input type="checkbox" class="ovr-rc-check" name="ids[]" value="<?php echo esc_attr( $rid ); ?>" form="ovr-rev-bulk" aria-label="<?php esc_attr_e( 'Select review', 'ovr-core' ); ?>">
                                <span class="ovr-rc-av"><?php echo esc_html( $initials( (string) $r['guest_name'] ) ); ?></span>
                                <div>
                                    <h3 class="ovr-rc-name"><?php echo esc_html( $r['guest_name'] ?: __( 'Anonymous', 'ovr-core' ) ); ?></h3>
                                    <p class="ovr-rc-meta"><?php echo esc_html( trim( $when . ' • ' . $prop, ' •' ) ); ?><?php if ( $stayed ) : ?> <span style="color:#8b95a5">• <?php printf( esc_html__( 'Stayed %s', 'ovr-core' ), esc_html( $stayed ) ); ?></span><?php endif; ?><?php if ( $approved ) : ?> <span style="color:var(--sec,#006c4a)">• <?php printf( esc_html__( 'Approved %s', 'ovr-core' ), esc_html( $approved ) ); ?></span><?php endif; ?></p>
                                </div>
                            </div>
                            <span class="ovr-rc-chip" style="background:<?php echo esc_attr( $meta['chip_bg'] ); ?>;color:<?php echo esc_attr( $meta['chip_fg'] ); ?>">
                                <span class="material-symbols-outlined"><?php echo esc_html( $meta['icon'] ); ?></span><?php echo esc_html( $meta['label'] ); ?>
                            </span>
                        </div>

                        <div class="ovr-rc-stars"><?php $stars( (int) $r['rating'] ); ?></div>

                        <?php if ( ! empty( $r['title'] ) ) : ?>
                            <p class="ovr-rc-title"><?php echo esc_html( $r['title'] ); ?></p>
                        <?php endif; ?>
                        <p class="ovr-rc-body"><?php echo esc_html( $r['body'] ); ?></p>

                        <div class="ovr-rc-acts">
                            <a class="ovr-cbtn ovr-cbtn--edit<?php echo $is_edit ? ' is-active' : ''; ?>" href="<?php echo esc_url( $is_edit ? $base_url : $edit_url( $rid ) ); ?>">
                                <span class="material-symbols-outlined">edit</span><?php echo $is_edit ? esc_html__( 'Editing', 'ovr-core' ) : esc_html__( 'Edit / View', 'ovr-core' ); ?>
                            </a>
                            <?php if ( 'approved' !== $st ) : ?>
                                <form method="post">
                                    <?php wp_nonce_field( $nonce_action ); ?>
                                    <input type="hidden" name="ovr_reviews_action" value="single">
                                    <input type="hidden" name="review_id" value="<?php echo esc_attr( $rid ); ?>">
                                    <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                                    <input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
                                    <button type="submit" name="op" value="approve" class="ovr-cbtn ovr-cbtn--approve">
                                        <span class="material-symbols-outlined">check</span><?php esc_html_e( 'Approve', 'ovr-core' ); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ( 'rejected' !== $st ) : ?>
                                <form method="post">
                                    <?php wp_nonce_field( $nonce_action ); ?>
                                    <input type="hidden" name="ovr_reviews_action" value="single">
                                    <input type="hidden" name="review_id" value="<?php echo esc_attr( $rid ); ?>">
                                    <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                                    <input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
                                    <button type="submit" name="op" value="reject" class="ovr-cbtn ovr-cbtn--reject">
                                        <span class="material-symbols-outlined">block</span><?php esc_html_e( 'Reject', 'ovr-core' ); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ( $pages > 1 ) : ?>
                    <nav class="ovr-rev-pager" aria-label="<?php esc_attr_e( 'Reviews pages', 'ovr-core' ); ?>">
                        <?php
                        $page_link = static function ( int $p ) use ( $base_url, $status ): string {
                            $args = [];
                            if ( 'all' !== $status ) { $args['status'] = $status; }
                            if ( $p > 1 )            { $args['paged']  = $p; }
                            return $args ? add_query_arg( $args, $base_url ) : $base_url;
                        };
                        ?>
                        <?php if ( $paged > 1 ) : ?>
                            <a href="<?php echo esc_url( $page_link( $paged - 1 ) ); ?>"><span class="material-symbols-outlined">chevron_left</span></a>
                        <?php else : ?>
                            <span class="is-disabled"><span class="material-symbols-outlined">chevron_left</span></span>
                        <?php endif; ?>

                        <?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
                            <?php if ( $p === $paged ) : ?>
                                <span class="is-current"><?php echo esc_html( $p ); ?></span>
                            <?php else : ?>
                                <a href="<?php echo esc_url( $page_link( $p ) ); ?>"><?php echo esc_html( $p ); ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ( $paged < $pages ) : ?>
                            <a href="<?php echo esc_url( $page_link( $paged + 1 ) ); ?>"><span class="material-symbols-outlined">chevron_right</span></a>
                        <?php else : ?>
                            <span class="is-disabled"><span class="material-symbols-outlined">chevron_right</span></span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar: editor when a review is selected, else a details summary. -->
        <aside class="ovr-rev-side">
            <?php if ( $editing ) :
                $e_id     = (int) $editing['id'];
                $e_rating = (int) $editing['rating'];
                $e_status = (string) $editing['status'];
            ?>
                <div class="ovr-side-h">
                    <h2><?php esc_html_e( 'Review Details', 'ovr-core' ); ?></h2>
                    <form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete this review? This cannot be undone.', 'ovr-core' ) ); ?>');">
                        <?php wp_nonce_field( $nonce_action ); ?>
                        <input type="hidden" name="ovr_reviews_action" value="single">
                        <input type="hidden" name="op" value="delete">
                        <input type="hidden" name="review_id" value="<?php echo esc_attr( $e_id ); ?>">
                        <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                        <input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
                        <button type="submit" class="ovr-side-del" title="<?php esc_attr_e( 'Delete review', 'ovr-core' ); ?>" aria-label="<?php esc_attr_e( 'Delete review', 'ovr-core' ); ?>">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </form>
                </div>

                <div class="ovr-side-ctx">
                    <p class="ovr-side-ctx-lbl"><span class="material-symbols-outlined">edit_note</span><?php esc_html_e( 'Currently editing', 'ovr-core' ); ?></p>
                    <p>
                        <?php
                        echo esc_html( ( $editing['guest_name'] ?: __( 'Anonymous', 'ovr-core' ) ) . ' — ' . ( $editing['property_title'] ?? __( 'Unknown property', 'ovr-core' ) ) );
                        ?>
                    </p>
                </div>

                <form method="post">
                    <?php wp_nonce_field( $nonce_action ); ?>
                    <input type="hidden" name="ovr_reviews_action" value="save_edit">
                    <input type="hidden" name="review_id" value="<?php echo esc_attr( $e_id ); ?>">
                    <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                    <input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">

                    <div class="ovr-fld">
                        <label><?php esc_html_e( 'Adjust rating', 'ovr-core' ); ?></label>
                        <div class="ovr-stars-input">
                            <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                                <input type="radio" id="ovr-star-<?php echo esc_attr( $i ); ?>" name="rating" value="<?php echo esc_attr( $i ); ?>" <?php checked( $e_rating, $i ); ?>>
                                <label for="ovr-star-<?php echo esc_attr( $i ); ?>" title="<?php echo esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'ovr-core' ), $i ) ); ?>">
                                    <span class="material-symbols-outlined">star</span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="ovr-fld">
                        <label for="ovr-rev-title"><?php esc_html_e( 'Title', 'ovr-core' ); ?></label>
                        <input type="text" id="ovr-rev-title" name="title" value="<?php echo esc_attr( $editing['title'] ); ?>" maxlength="255">
                    </div>

                    <div class="ovr-fld">
                        <label for="ovr-rev-body"><?php esc_html_e( 'Review content', 'ovr-core' ); ?></label>
                        <textarea id="ovr-rev-body" name="body" rows="6"><?php echo esc_textarea( $editing['body'] ); ?></textarea>
                    </div>

                    <button type="submit" class="ovr-btn ovr-btn--primary ovr-side-save">
                        <span class="material-symbols-outlined">save</span><?php esc_html_e( 'Save Changes', 'ovr-core' ); ?>
                    </button>
                </form>

                <div class="ovr-side-status">
                    <label style="display:block;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);margin-bottom:10px"><?php esc_html_e( 'Visibility status', 'ovr-core' ); ?></label>
                    <div class="ovr-side-toggle">
                        <form method="post" style="flex:1">
                            <?php wp_nonce_field( $nonce_action ); ?>
                            <input type="hidden" name="ovr_reviews_action" value="single">
                            <input type="hidden" name="op" value="approve">
                            <input type="hidden" name="review_id" value="<?php echo esc_attr( $e_id ); ?>">
                            <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                            <input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
                            <button type="submit" class="ovr-st-approve<?php echo 'approved' === $e_status ? ' is-current' : ''; ?>" style="width:100%"><?php esc_html_e( 'Approve', 'ovr-core' ); ?></button>
                        </form>
                        <form method="post" style="flex:1">
                            <?php wp_nonce_field( $nonce_action ); ?>
                            <input type="hidden" name="ovr_reviews_action" value="single">
                            <input type="hidden" name="op" value="reject">
                            <input type="hidden" name="review_id" value="<?php echo esc_attr( $e_id ); ?>">
                            <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                            <input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
                            <button type="submit" class="ovr-st-reject<?php echo 'rejected' === $e_status ? ' is-current' : ''; ?>" style="width:100%"><?php esc_html_e( 'Reject', 'ovr-core' ); ?></button>
                        </form>
                    </div>
                    <p class="ovr-side-note"><?php esc_html_e( 'Approved reviews are immediately visible on the public listing.', 'ovr-core' ); ?></p>
                </div>
            <?php else : ?>
                <div class="ovr-side-h">
                    <h2><?php esc_html_e( 'Review Details', 'ovr-core' ); ?></h2>
                </div>
                <ul class="ovr-side-stats">
                    <li><span><?php esc_html_e( 'Total reviews', 'ovr-core' ); ?></span><b><?php echo esc_html( number_format_i18n( $counts['all'] ) ); ?></b></li>
                    <li><span><?php esc_html_e( 'Average rating', 'ovr-core' ); ?></span><b style="color:var(--p,#004c4c)"><?php echo esc_html( number_format( (float) ( $analytics['avg_rating'] ?? 0 ), 2 ) ); ?> ★</b></li>
                    <li><span><?php esc_html_e( 'Pending approval', 'ovr-core' ); ?></span><b style="color:var(--ter)"><?php echo esc_html( number_format_i18n( $counts['pending'] ) ); ?></b></li>
                    <li><span><?php esc_html_e( 'Approved', 'ovr-core' ); ?></span><b style="color:var(--sec)"><?php echo esc_html( number_format_i18n( $counts['approved'] ) ); ?></b></li>
                    <li><span><?php esc_html_e( 'Rejected', 'ovr-core' ); ?></span><b style="color:var(--err)"><?php echo esc_html( number_format_i18n( $counts['rejected'] ) ); ?></b></li>
                </ul>
                <?php if ( ! empty( $analytics['per_property'] ) ) : ?>
                    <div class="ovr-side-h" style="margin-top:18px"><h2 style="font-size:14px"><?php esc_html_e( 'Reviews Per Property', 'ovr-core' ); ?></h2></div>
                    <ul class="ovr-side-stats">
                        <?php foreach ( $analytics['per_property'] as $pp ) : ?>
                            <li>
                                <span style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html( $pp['title'] ); ?></span>
                                <b><?php echo esc_html( number_format_i18n( $pp['count'] ) ); ?> · <?php echo esc_html( $pp['avg'] ); ?>★</b>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="ovr-side-hint">
                    <span class="material-symbols-outlined">lightbulb</span>
                    <span><?php esc_html_e( 'Select “Edit / View” on any review to adjust its rating, edit its content, or change its visibility here.', 'ovr-core' ); ?></span>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>
