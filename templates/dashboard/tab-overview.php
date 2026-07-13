<?php
/**
 * Overview tab — landlord portfolio dashboard (welcome, bento stats, recent
 * listings, recent inquiries, quick actions). Scoped under `.ovr-ld`.
 *
 * @package OVR
 * @var array     $stats
 * @var \WP_Post[] $properties
 * @var array     $recent_inquiries
 * @var float     $balance
 * @var array     $subscription
 * @var \WP_User  $user
 * @var string    $base_url
 * @var string    $add_url
 * @var string    $pricing_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\Pages;

$stats            = $stats ?? [];
$properties       = $properties ?? [];
$recent_inquiries = $recent_inquiries ?? [];
$balance          = (float) ( $balance ?? 0 );
$subscription     = $subscription ?? [];
$sub_status_label = $sub_status_label ?? __( 'Unknown', 'ovr-core' );
$sub_days_left    = $sub_days_left ?? null;
$add_url          = $add_url ?? admin_url( 'post-new.php?post_type=ovr_property' );
$pricing_url      = $pricing_url ?? Pages::get_page_url( 'ovr_page_pricing' );

$first_name   = $user->first_name ?: $user->display_name;
$inq_url      = add_query_arg( 'tab', 'inquiries', $base_url );
$props_url    = add_query_arg( 'tab', 'properties', $base_url );
$sub_url      = add_query_arg( 'tab', 'subscription', $base_url );

// Subscription summary.
$sub_name    = (string) ( $subscription['plan_name'] ?? __( 'No plan', 'ovr-core' ) );
$sub_expires = (string) ( $subscription['expires'] ?? '' );
$sub_ts      = $sub_expires ? strtotime( $sub_expires ) : 0;
$sub_status  = (string) ( $subscription['status'] ?? '' );
$is_active   = ( 'active' === $sub_status );
$is_expired  = ( 'expired' === $sub_status );
?>

<!-- Header -->
<section class="ld-ov-head">
    <div>
        <h1 class="ld-ov-title"><?php printf( esc_html__( 'Welcome back, %s!', 'ovr-core' ), esc_html( $first_name ) ); ?></h1>
        <p class="ld-ov-sub"><?php esc_html_e( 'Here is an overview of your property portfolio.', 'ovr-core' ); ?></p>
    </div>
    <a href="<?php echo esc_url( $add_url ); ?>" class="ld-ov-btn ld-ov-btn--primary">
        <span class="material-symbols-outlined">add</span><?php esc_html_e( 'List New Property', 'ovr-core' ); ?>
    </a>
</section>

<!-- Stats bento -->
<section class="ld-ov-stats">

    <!-- Active listings -->
    <div class="ld-stat">
        <div class="ld-stat-top">
            <span class="ld-stat-ic ld-stat-ic--p"><span class="material-symbols-outlined">holiday_village</span></span>
            <?php if ( ( $stats['new_this_month'] ?? 0 ) > 0 ) : ?>
                <span class="ld-stat-pill">+<?php echo esc_html( (string) (int) $stats['new_this_month'] ); ?> <?php esc_html_e( 'this month', 'ovr-core' ); ?></span>
            <?php endif; ?>
        </div>
        <p class="ld-stat-lbl"><?php esc_html_e( 'Active Listings', 'ovr-core' ); ?></p>
        <p class="ld-stat-val"><?php echo esc_html( number_format( (int) ( $stats['active_properties'] ?? 0 ) ) ); ?></p>
    </div>

    <!-- Total inquiries 30d -->
    <div class="ld-stat">
        <div class="ld-stat-top">
            <span class="ld-stat-ic ld-stat-ic--ter"><span class="material-symbols-outlined">mark_email_unread</span></span>
        </div>
        <p class="ld-stat-lbl"><?php esc_html_e( 'Inquiries (Last 12 Months)', 'ovr-core' ); ?></p>
        <p class="ld-stat-val"><?php echo esc_html( number_format( (int) ( $stats['inquiries_12mo'] ?? 0 ) ) ); ?></p>
    </div>

    <!-- Subscription (plan, status, expiry, days remaining, credit) -->
    <a href="<?php echo esc_url( $sub_url ); ?>" class="ld-stat ld-stat--sub">
        <div class="ld-stat-top">
            <span class="ld-stat-ic ld-stat-ic--glass"><span class="material-symbols-outlined">workspace_premium</span></span>
            <span class="ld-stat-status<?php echo $is_active ? '' : ' is-off'; ?>"><?php echo esc_html( $sub_status_label ); ?></span>
        </div>
        <p class="ld-stat-lbl ld-stat-lbl--light"><?php esc_html_e( 'Subscription', 'ovr-core' ); ?></p>
        <p class="ld-sub-name"><?php echo esc_html( $sub_name ); ?></p>
        <?php if ( $is_active && $sub_days_left > 0 ) : ?>
            <p class="ld-sub-renew">
                <span class="material-symbols-outlined">event</span>
                <?php printf( esc_html__( '%d days remaining', 'ovr-core' ), (int) $sub_days_left ); ?>
                &middot;
                <?php printf( esc_html__( 'Renews %s', 'ovr-core' ), esc_html( mysql2date( 'M j, Y', $sub_expires ) ) ); ?>
            </p>
        <?php elseif ( $sub_ts ) : ?>
            <p class="ld-sub-renew">
                <span class="material-symbols-outlined">event</span>
                <?php printf( esc_html__( 'Valid Through: %s', 'ovr-core' ), esc_html( mysql2date( 'M j, Y', $sub_expires ) ) ); ?>
            </p>
        <?php endif; ?>
        <?php if ( $is_expired ) : ?>
            <p class="ld-sub-renew" style="color:var(--errc)"><?php esc_html_e( 'Your subscription has expired. Renew to reactivate your listings.', 'ovr-core' ); ?></p>
        <?php endif; ?>
        <p class="ld-sub-balance">
            <span class="material-symbols-outlined">account_balance_wallet</span>
            <?php printf( esc_html__( 'Available Credit: %s', 'ovr-core' ), esc_html( '$' . number_format( $balance, 2 ) ) ); ?>
        </p>
    </a>
</section>

<!-- Listings + inquiries -->
<div class="ld-ov-grid">

    <!-- Recent listings -->
    <section class="ld-ov-col-main">
        <div class="ld-ov-secthead">
            <h2><?php esc_html_e( 'Recent Listings', 'ovr-core' ); ?></h2>
            <a href="<?php echo esc_url( $props_url ); ?>" class="ld-ov-viewall"><?php esc_html_e( 'View All', 'ovr-core' ); ?> <span class="material-symbols-outlined">chevron_right</span></a>
        </div>

        <?php if ( empty( $properties ) ) : ?>
            <div class="ld-ov-empty">
                <span class="material-symbols-outlined">add_home</span>
                <p><?php esc_html_e( 'No listings yet. Create your first listing to start receiving inquiries.', 'ovr-core' ); ?></p>
                <a href="<?php echo esc_url( $add_url ); ?>" class="ld-ov-btn ld-ov-btn--primary"><?php esc_html_e( 'List a Property', 'ovr-core' ); ?></a>
            </div>
        <?php else : ?>
            <div class="ld-cards">
                <?php foreach ( array_slice( $properties, 0, 4 ) as $p ) :
                    $thumb   = get_the_post_thumbnail_url( $p->ID, 'medium_large' ) ?: OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';
                    $price   = (float) get_post_meta( $p->ID, '_ovr_base_price', true );
                    $beds    = (int) get_post_meta( $p->ID, '_ovr_bedrooms', true );
                    $baths_r = (float) get_post_meta( $p->ID, '_ovr_bathrooms', true );
                    $baths   = rtrim( rtrim( number_format( $baths_r, 1 ), '0' ), '.' );
                    $views   = (int) get_post_meta( $p->ID, '_ovr_view_count', true );
                    $city    = (string) get_post_meta( $p->ID, '_ovr_city', true );
                    $state   = (string) get_post_meta( $p->ID, '_ovr_state', true );
                    $loc     = trim( implode( ', ', array_filter( [ $city, $state ] ) ) );
                    $edit    = add_query_arg( [ 'tab' => 'add-listing', 'post' => $p->ID ], $base_url );
                    $view    = get_permalink( $p->ID );
                    $bump    = wp_nonce_url(
                        admin_url( 'admin-post.php?action=ovr_bump_listing&post=' . $p->ID ),
                        'ovr_bump_listing_' . $p->ID
                    );
                    $upgrade = add_query_arg( [ 'tab' => 'upgrades', 'post' => $p->ID ], $base_url );
                    $delete  = wp_nonce_url(
                        admin_url( 'admin-post.php?action=ovr_delete_listing&post=' . $p->ID ),
                        'ovr_delete_listing_' . $p->ID
                    );

                    $is_pub  = ( 'publish' === $p->post_status );
                    $st_lbl  = $is_pub ? __( 'Published', 'ovr-core' ) : ucfirst( $p->post_status );
                    $st_cls  = $is_pub ? 'pub' : ( 'draft' === $p->post_status ? 'draft' : 'pending' );
                ?>
                    <article class="ld-card">
                        <div class="ld-card-media">
                            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $p->post_title ); ?>" loading="lazy">
                            <span class="ld-card-status ld-card-status--<?php echo esc_attr( $st_cls ); ?>">
                                <span class="ld-dot"></span><?php echo esc_html( $st_lbl ); ?>
                            </span>
                        </div>
                        <div class="ld-card-body">
                            <div class="ld-card-titlerow">
                                <h3><?php echo esc_html( $p->post_title ?: __( '(untitled)', 'ovr-core' ) ); ?></h3>
                                <?php if ( $price > 0 ) : ?>
                                    <span class="ld-card-price">$<?php echo esc_html( number_format( $price, 0 ) ); ?><span>/nt</span></span>
                                <?php endif; ?>
                            </div>
                            <?php if ( $loc ) : ?>
                                <p class="ld-card-loc"><span class="material-symbols-outlined">location_on</span><?php echo esc_html( $loc ); ?></p>
                            <?php endif; ?>
                            <div class="ld-card-meta">
                                <span><span class="material-symbols-outlined">bed</span><?php echo esc_html( (string) $beds ); ?></span>
                                <span><span class="material-symbols-outlined">shower</span><?php echo esc_html( $baths ?: '0' ); ?></span>
                                <?php if ( $is_pub ) : ?>
                                    <span><span class="material-symbols-outlined">visibility</span><?php printf( esc_html__( '%s views', 'ovr-core' ), esc_html( number_format( $views ) ) ); ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Quick actions (what 99% of landlords log in to do) -->
                            <div class="ld-card-actions">
                                <a href="<?php echo esc_url( (string) $view ); ?>" target="_blank" rel="noopener" class="ld-card-act"><span class="material-symbols-outlined">visibility</span><?php esc_html_e( 'View', 'ovr-core' ); ?></a>
                                <a href="<?php echo esc_url( (string) $edit ); ?>" class="ld-card-act"><span class="material-symbols-outlined">edit</span><?php esc_html_e( 'Edit', 'ovr-core' ); ?></a>
                                <a href="<?php echo esc_url( (string) $bump ); ?>" class="ld-card-act" title="<?php esc_attr_e( 'Bump to top of results (free, daily limit)', 'ovr-core' ); ?>"><span class="material-symbols-outlined">trending_up</span><?php esc_html_e( 'Bump', 'ovr-core' ); ?></a>
                                <a href="<?php echo esc_url( (string) $delete ); ?>" class="ld-card-act ld-card-act--danger" data-ovr-confirm="<?php echo esc_attr( sprintf( __( 'Delete “%s”? This cannot be undone.', 'ovr-core' ), $p->post_title ?: __( 'this listing', 'ovr-core' ) ) ); ?>"><span class="material-symbols-outlined">delete</span><?php esc_html_e( 'Delete', 'ovr-core' ); ?></a>
                                <a href="<?php echo esc_url( (string) $upgrade ); ?>" class="ld-card-act ld-card-act--upgrade" title="<?php esc_attr_e( 'Purchase a promotion upgrade', 'ovr-core' ); ?>"><span class="material-symbols-outlined">rocket_launch</span><?php esc_html_e( 'Upgrade', 'ovr-core' ); ?></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Recent inquiries -->
    <section class="ld-ov-col-side">
        <div class="ld-ov-secthead">
            <h2><?php esc_html_e( 'Recent Inquiries', 'ovr-core' ); ?></h2>
        </div>

        <div class="ld-inq-card">
            <?php if ( empty( $recent_inquiries ) ) : ?>
                <div class="ld-inq-empty">
                    <span class="material-symbols-outlined">forum</span>
                    <p><?php esc_html_e( 'No inquiries yet.', 'ovr-core' ); ?></p>
                </div>
            <?php else :
                $tones = [ 'p', 'sec', 'ter' ];
                foreach ( array_slice( $recent_inquiries, 0, 4 ) as $i => $inq ) :
                    $name    = (string) ( $inq['guest_name'] ?? __( 'Guest', 'ovr-core' ) );
                    $initials = strtoupper( implode( '', array_map( static fn( $w ) => $w[0] ?? '', array_slice( preg_split( '/\s+/', trim( $name ) ) ?: [], 0, 2 ) ) ) ) ?: '?';
                    $prop    = $inq['property_id'] ? get_the_title( (int) $inq['property_id'] ) : '';
                    $msg     = (string) ( $inq['message'] ?? '' );
                    $is_new  = ( 'new' === ( $inq['status'] ?? '' ) );
                    $ago     = ! empty( $inq['created_at'] ) ? human_time_diff( strtotime( $inq['created_at'] ) ) : '';
                    $tone    = $tones[ $i % 3 ];
                ?>
                    <a href="<?php echo esc_url( $inq_url ); ?>" class="ld-inq<?php echo $is_new ? ' is-new' : ''; ?>">
                        <span class="ld-inq-av ld-inq-av--<?php echo esc_attr( $tone ); ?>">
                            <?php echo esc_html( $initials ); ?>
                            <?php if ( $is_new ) : ?><span class="ld-inq-unread"></span><?php endif; ?>
                        </span>
                        <span class="ld-inq-body">
                            <span class="ld-inq-row">
                                <span class="ld-inq-name"><?php echo esc_html( $name ); ?></span>
                                <?php if ( $ago ) : ?><span class="ld-inq-ago"><?php printf( esc_html__( '%s ago', 'ovr-core' ), esc_html( $ago ) ); ?></span><?php endif; ?>
                            </span>
                            <?php if ( $prop ) : ?><span class="ld-inq-prop"><?php printf( esc_html__( 'Re: %s', 'ovr-core' ), esc_html( $prop ) ); ?></span><?php endif; ?>
                            <span class="ld-inq-msg"><?php echo esc_html( wp_trim_words( $msg, 14 ) ); ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
                <a href="<?php echo esc_url( $inq_url ); ?>" class="ld-inq-foot"><?php esc_html_e( 'View All Inquiries', 'ovr-core' ); ?> <span class="material-symbols-outlined">arrow_forward</span></a>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Quick actions — upgrades are managed per-listing (on each card above), not globally. -->
<section class="ld-ov-actions">
    <a href="<?php echo esc_url( $add_url ); ?>" class="ld-ov-btn ld-ov-btn--primary"><?php esc_html_e( 'List New Property', 'ovr-core' ); ?></a>
    <a href="<?php echo esc_url( $inq_url ); ?>" class="ld-ov-btn ld-ov-btn--ghost"><?php esc_html_e( 'View Inquiries', 'ovr-core' ); ?></a>
</section>

<style>
    .ovr-ld .ld-ov-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap}
    .ovr-ld .ld-ov-title{font-size:40px;font-weight:700;letter-spacing:-.02em;color:var(--p);margin:0 0 6px;line-height:1.1}
    .ovr-ld .ld-ov-sub{font-size:17px;color:var(--sv);margin:0}
    .ovr-ld .ld-ov-btn{display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;line-height:1;border:1px solid transparent;cursor:pointer;transition:background .18s,color .18s,box-shadow .18s}
    .ovr-ld .ld-ov-btn .material-symbols-outlined{font-size:20px}
    .ovr-ld .ld-ov-btn--primary{background:var(--p);color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.12)}
    .ovr-ld .ld-ov-btn--primary:hover{background:#003838;color:#fff;box-shadow:0 4px 12px rgba(0,76,76,.25)}
    .ovr-ld .ld-ov-btn--outline{border-color:var(--p);color:var(--p);background:transparent}
    .ovr-ld .ld-ov-btn--outline:hover{background:rgba(0,76,76,.06);color:var(--p)}
    .ovr-ld .ld-ov-btn--ghost{color:var(--sv);background:transparent}
    .ovr-ld .ld-ov-btn--ghost:hover{color:var(--p);background:rgba(0,76,76,.06)}

    /* Stats bento */
    .ovr-ld .ld-ov-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
    .ovr-ld .ld-stat{background:var(--surf);border:1px solid rgba(190,201,200,.4);border-radius:14px;padding:24px;box-shadow:0 4px 24px rgba(0,0,0,.04);display:flex;flex-direction:column;transition:transform .25s}
    .ovr-ld .ld-stat:hover{transform:translateY(-2px)}
    .ovr-ld .ld-stat-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px}
    .ovr-ld .ld-stat-ic{width:46px;height:46px;border-radius:11px;display:flex;align-items:center;justify-content:center}
    .ovr-ld .ld-stat-ic .material-symbols-outlined{font-size:23px}
    .ovr-ld .ld-stat-ic--p{background:rgba(0,76,76,.1);color:var(--p)}
    .ovr-ld .ld-stat-ic--ter{background:rgba(115,92,0,.12);color:var(--ter)}
    .ovr-ld .ld-stat-ic--sec{background:rgba(0,108,74,.12);color:var(--sec)}
    .ovr-ld .ld-stat-ic--glass{background:rgba(255,255,255,.2);color:#fff;backdrop-filter:blur(4px)}
    .ovr-ld .ld-stat-pill{font-size:12px;font-weight:700;color:var(--sec);background:rgba(0,108,74,.1);padding:5px 9px;border-radius:7px}
    .ovr-ld .ld-stat-action{color:var(--p);padding:5px;border-radius:8px;display:inline-flex;text-decoration:none;transition:background .15s}
    .ovr-ld .ld-stat-action:hover{background:rgba(0,76,76,.1)}
    .ovr-ld .ld-stat-lbl{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--outline);margin:0 0 4px}
    .ovr-ld .ld-stat-val{font-size:32px;font-weight:700;color:var(--on);margin:0;line-height:1.1}
    .ovr-ld .ld-stat-val--money{font-size:28px}

    /* Subscription tile (dark teal) */
    .ovr-ld .ld-stat--sub{background:var(--p);color:#fff;text-decoration:none;position:relative;overflow:hidden;border:none}
    .ovr-ld .ld-stat--sub::after{content:"";position:absolute;right:-30px;top:-40px;width:130px;height:130px;background:rgba(255,255,255,.1);border-radius:50%;filter:blur(24px)}
    .ovr-ld .ld-stat--sub:hover{transform:translateY(-2px)}
    .ovr-ld .ld-stat-lbl--light{color:var(--pfd)}
    .ovr-ld .ld-stat-status{font-size:11px;font-weight:700;background:rgba(255,255,255,.2);color:#fff;padding:4px 9px;border-radius:7px;position:relative;z-index:1}
    .ovr-ld .ld-stat-status.is-off{background:rgba(255,255,255,.15);color:var(--errc)}
    .ovr-ld .ld-sub-name{font-size:17px;font-weight:700;margin:0 0 5px;position:relative;z-index:1}
    .ovr-ld .ld-sub-renew{font-size:13px;color:var(--pfd);margin:0;display:flex;align-items:center;gap:5px;position:relative;z-index:1}
    .ovr-ld .ld-sub-renew .material-symbols-outlined{font-size:16px}
    .ovr-ld .ld-sub-balance{font-size:13px;font-weight:600;color:#fff;margin:8px 0 0;display:flex;align-items:center;gap:5px;position:relative;z-index:1}
    .ovr-ld .ld-sub-balance .material-symbols-outlined{font-size:16px;color:var(--secc)}

    /* Listings + inquiries grid */
    .ovr-ld .ld-ov-grid{display:grid;grid-template-columns:2fr 1fr;gap:32px;align-items:start}
    .ovr-ld .ld-ov-secthead{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:1px solid rgba(190,201,200,.5);padding-bottom:14px;margin-bottom:22px}
    .ovr-ld .ld-ov-secthead h2{font-size:22px;font-weight:600;color:var(--on);margin:0}
    .ovr-ld .ld-ov-viewall{display:inline-flex;align-items:center;gap:2px;color:var(--p);font-weight:700;font-size:14px;text-decoration:none}
    .ovr-ld .ld-ov-viewall:hover{text-decoration:underline}
    .ovr-ld .ld-ov-viewall .material-symbols-outlined{font-size:18px}

    .ovr-ld .ld-cards{display:grid;grid-template-columns:repeat(2,1fr);gap:24px}
    .ovr-ld .ld-card{background:var(--surf);border:1px solid rgba(190,201,200,.4);border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.03);display:flex;flex-direction:column}
    .ovr-ld .ld-card-media{position:relative;aspect-ratio:4/3;overflow:hidden;background:var(--sclow)}
    .ovr-ld .ld-card-media img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
    .ovr-ld .ld-card:hover .ld-card-media img{transform:scale(1.05)}
    .ovr-ld .ld-card-status{position:absolute;top:12px;right:12px;background:rgba(255,255,255,.92);backdrop-filter:blur(4px);padding:5px 10px;border-radius:7px;font-size:12px;font-weight:700;display:flex;align-items:center;gap:6px;box-shadow:0 1px 3px rgba(0,0,0,.12)}
    .ovr-ld .ld-card-status .ld-dot{width:8px;height:8px;border-radius:50%}
    .ovr-ld .ld-card-status--pub{color:var(--p)}.ovr-ld .ld-card-status--pub .ld-dot{background:var(--sec)}
    .ovr-ld .ld-card-status--draft{color:var(--ter)}.ovr-ld .ld-card-status--draft .ld-dot{background:var(--terc)}
    .ovr-ld .ld-card-status--pending{color:var(--err)}.ovr-ld .ld-card-status--pending .ld-dot{background:var(--err)}
    .ovr-ld .ld-card-body{padding:18px;display:flex;flex-direction:column;flex:1}
    .ovr-ld .ld-card-titlerow{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:6px}
    .ovr-ld .ld-card-titlerow h3{font-size:17px;font-weight:700;color:var(--on);margin:0;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ovr-ld .ld-card-price{font-size:18px;font-weight:700;color:var(--p);white-space:nowrap}
    .ovr-ld .ld-card-price span{font-size:13px;color:var(--outline);font-weight:400}
    .ovr-ld .ld-card-loc{display:flex;align-items:center;gap:4px;font-size:13px;color:var(--sv);margin:0 0 14px}
    .ovr-ld .ld-card-loc .material-symbols-outlined{font-size:16px}
    .ovr-ld .ld-card-meta{display:flex;align-items:center;gap:16px;font-size:13px;color:var(--outline);margin-top:auto;padding-top:14px;border-top:1px solid rgba(190,201,200,.35)}
    .ovr-ld .ld-card-meta>span{display:flex;align-items:center;gap:4px}
    .ovr-ld .ld-card-meta .material-symbols-outlined{font-size:18px}
    .ovr-ld .ld-card-edit{margin-left:auto;color:var(--sv);font-weight:600;text-decoration:none}
    .ovr-ld .ld-card-edit:hover{color:var(--p)}
    .ovr-ld .ld-card-actions{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-top:14px;padding-top:14px;border-top:1px solid rgba(190,201,200,.35)}
    .ovr-ld .ld-card-act{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;padding:9px 3px;border:1px solid var(--ov);border-radius:9px;background:#fff;color:var(--sv);font-size:11.5px;font-weight:600;text-decoration:none;cursor:pointer;transition:background .15s,color .15s,border-color .15s}
    .ovr-ld .ld-card-act .material-symbols-outlined{font-size:18px}
    .ovr-ld .ld-card-act:hover{background:rgba(0,76,76,.07);color:var(--p);border-color:var(--p)}
    .ovr-ld .ld-card-act--danger:hover{background:var(--errc);color:var(--err);border-color:var(--err)}
    .ovr-ld .ld-card-act--upgrade{color:#6b4e00;border-color:#e7cf7e;background:#fffdf5}
    .ovr-ld .ld-card-act--upgrade:hover{background:#fff6d9;color:#6b4e00;border-color:#dcbf5e}
    @media (max-width:520px){.ovr-ld .ld-card-actions{grid-template-columns:repeat(3,1fr)}}

    /* Inquiries list */
    .ovr-ld .ld-inq-card{background:var(--surf);border:1px solid rgba(190,201,200,.4);border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.03)}
    .ovr-ld .ld-inq{display:flex;gap:12px;padding:16px;border-bottom:1px solid rgba(190,201,200,.3);text-decoration:none;transition:background .15s}
    .ovr-ld .ld-inq:hover{background:var(--sclow)}
    .ovr-ld .ld-inq.is-new{background:rgba(0,108,74,.04)}
    .ovr-ld .ld-inq-av{position:relative;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
    .ovr-ld .ld-inq-av--p{background:rgba(0,76,76,.1);color:var(--p)}
    .ovr-ld .ld-inq-av--sec{background:rgba(0,108,74,.12);color:var(--sec)}
    .ovr-ld .ld-inq-av--ter{background:rgba(115,92,0,.12);color:var(--ter)}
    .ovr-ld .ld-inq-unread{position:absolute;top:-1px;right:-1px;width:11px;height:11px;background:var(--err);border-radius:50%;border:2px solid var(--surf)}
    .ovr-ld .ld-inq-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
    .ovr-ld .ld-inq-row{display:flex;justify-content:space-between;align-items:baseline;gap:8px}
    .ovr-ld .ld-inq-name{font-size:14px;font-weight:700;color:var(--on);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ovr-ld .ld-inq-ago{font-size:12px;color:var(--outline);flex-shrink:0}
    .ovr-ld .ld-inq-prop{font-size:12px;color:var(--sv);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ovr-ld .ld-inq-msg{font-size:13.5px;color:var(--sv);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ovr-ld .ld-inq.is-new .ld-inq-msg{color:var(--on);font-weight:500}
    .ovr-ld .ld-inq-foot{display:flex;align-items:center;justify-content:center;gap:5px;padding:14px;background:var(--sclow);color:var(--p);font-weight:700;font-size:14px;text-decoration:none}
    .ovr-ld .ld-inq-foot:hover{text-decoration:underline}
    .ovr-ld .ld-inq-foot .material-symbols-outlined{font-size:16px}
    .ovr-ld .ld-inq-empty{padding:40px 20px;text-align:center;color:var(--sv)}
    .ovr-ld .ld-inq-empty .material-symbols-outlined{font-size:34px;color:var(--ov);display:block;margin:0 auto 8px}

    /* Empty listings */
    .ovr-ld .ld-ov-empty{background:var(--surf);border:1px dashed var(--ov);border-radius:14px;padding:48px 24px;text-align:center;color:var(--sv);display:flex;flex-direction:column;align-items:center;gap:12px}
    .ovr-ld .ld-ov-empty .material-symbols-outlined{font-size:40px;color:var(--ov)}

    /* Quick actions footer */
    .ovr-ld .ld-ov-actions{display:flex;flex-wrap:wrap;gap:16px;align-items:center;padding-top:32px;border-top:1px solid rgba(190,201,200,.3)}

    @media (max-width:1100px){
        .ovr-ld .ld-ov-stats{grid-template-columns:repeat(2,1fr)}
        .ovr-ld .ld-ov-grid{grid-template-columns:1fr;gap:36px}
    }
    @media (max-width:600px){
        .ovr-ld .ld-ov-title{font-size:30px}
        .ovr-ld .ld-ov-stats{grid-template-columns:1fr}
        .ovr-ld .ld-cards{grid-template-columns:1fr}
        .ovr-ld .ld-ov-actions .ld-ov-btn{flex:1;justify-content:center}
    }
</style>

<script>
(function(){
    // Confirm destructive listing actions before navigating.
    document.querySelectorAll('[data-ovr-confirm]').forEach(function(el){
        el.addEventListener('click', function(e){
            if (!window.confirm(el.getAttribute('data-ovr-confirm'))) { e.preventDefault(); }
        });
    });
})();
</script>
