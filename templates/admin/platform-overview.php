<?php
/**
 * Platform Overview template — site-owner admin dashboard.
 *
 * Bento-grid metrics + growth chart + activity feed, rendered inside the
 * WordPress admin (which supplies its own sidebar/topbar, so the standalone
 * mockup chrome is intentionally dropped here).
 *
 * @package OVR
 * @var array      $stats
 * @var \WP_Post[] $recent_props
 * @var array      $activity
 * @var string     $settings_url
 * @var string     $add_property_url
 * @var string     $all_props_url
 * @var string     $users_url
 * @var string     $all_users_url
 * @var string     $payments_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$sym = $stats['currency_symbol'] ?? '$';

// Growth Trends: scale bars against the tallest month so the chart always fits.
$series   = is_array( $stats['revenue_series'] ?? null ) ? $stats['revenue_series'] : [];
$peak     = 0.0;
foreach ( $series as $pt ) { $peak = max( $peak, (float) $pt['total'] ); }

$rev_change = $stats['revenue_change'];
$avg_reply  = $stats['avg_reply_hours'];

// Tone → icon-chip colours for the activity feed.
$tones = [
    'secondary' => [ 'bg' => '#74f7be', 'fg' => '#00513a' ],
    'tertiary'  => [ 'bg' => '#ffe088', 'fg' => '#4e3d00' ],
    'error'     => [ 'bg' => '#ffdad6', 'fg' => '#93000a' ],
    'neutral'   => [ 'bg' => '#e0e3e2', 'fg' => '#181c1c' ],
];
?>
<div class="wrap ovr-dash">

    <style>
        /* Match the WP admin canvas to the design's off-white background and
           drop the default left gutter so the dashboard can run full width. */
        #wpcontent,#wpbody-content{background:#f7faf9}
        #wpcontent{padding-left:0}
        .ovr-dash{--p:#004c4c;--pc:#006666;--opc:#93e1e0;--sec:#006c4a;--secc:#74f7be;--ter:#735c00;--terc:#cca72f;--err:#ba1a1a;--errc:#ffdad6;--surf:#fff;--sv:#3f4948;--ov:#bec9c8;--on:#181c1c;font-family:'Inter',system-ui,sans-serif;max-width:none;margin:20px 0 56px;padding:0 40px;color:var(--on)}
        .ovr-dash,.ovr-dash *{box-sizing:border-box}
        .ovr-dash .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24}
        .ovr-dash .fill-icon{font-variation-settings:'FILL' 1}

        .ovr-dh{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin:6px 0 28px}
        .ovr-dh h1{font-size:34px;font-weight:700;letter-spacing:-.02em;margin:0;padding:0;color:var(--on);line-height:1.15}
        .ovr-dh p{margin:6px 0 0;color:var(--sv);font-size:15px}
        .ovr-dh-actions{display:flex;gap:10px;flex-wrap:wrap}
        .ovr-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:9999px;font-size:13px;font-weight:600;text-decoration:none;line-height:1;border:1px solid transparent;cursor:pointer}
        .ovr-btn .material-symbols-outlined{font-size:18px}
        .ovr-btn--primary{background:var(--p);color:#fff}
        .ovr-btn--primary:hover{background:#003838;color:#fff}
        .ovr-btn--ghost{background:var(--surf);color:var(--p);border-color:var(--ov)}
        .ovr-btn--ghost:hover{background:#eef4f4;color:var(--p)}

        .ovr-bento{display:grid;grid-template-columns:repeat(4,1fr);gap:24px;margin-bottom:24px}
        .ovr-tile{background:var(--surf);border:1px solid #e3e8e7;border-radius:16px;padding:26px;box-shadow:0 1px 2px rgba(0,0,0,.04);transition:transform .25s,box-shadow .25s;text-decoration:none;color:inherit;display:flex;flex-direction:column}
        a.ovr-tile:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(0,0,0,.08)}
        .ovr-tile-h{display:flex;align-items:center;gap:9px;margin-bottom:16px}
        .ovr-tile-h .material-symbols-outlined{font-size:20px;color:var(--p)}
        .ovr-tile-lbl{font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--sv);margin:0}
        .ovr-tile-val{font-size:32px;font-weight:700;line-height:1.05;color:var(--on);margin:0 0 6px}
        .ovr-tile-sub{font-size:13px;color:var(--sv);margin:auto 0 0;display:flex;align-items:center;justify-content:space-between;gap:6px;flex-wrap:wrap}
        .ovr-tile-sub .material-symbols-outlined{font-size:16px}
        .ovr-up{color:var(--sec);font-weight:600;display:inline-flex;align-items:center;gap:2px}
        .ovr-down{color:var(--err);font-weight:600;display:inline-flex;align-items:center;gap:2px}
        .ovr-link{color:var(--p);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:3px;margin-top:8px}
        .ovr-link:hover{text-decoration:underline;color:var(--p)}

        /* Hero revenue tile (dark teal — light text per contrast rule). */
        .ovr-hero{grid-column:span 2;background:var(--pc);color:var(--opc);position:relative;overflow:hidden;border:none;box-shadow:0 4px 24px rgba(0,76,76,.16)}
        .ovr-hero::after{content:"";position:absolute;right:-40px;top:-40px;width:240px;height:240px;background:rgba(255,255,255,.08);border-radius:50%;filter:blur(30px)}
        .ovr-hero .ovr-tile-h .material-symbols-outlined,.ovr-hero .ovr-tile-lbl{color:rgba(255,255,255,.82)}
        .ovr-hero .ovr-tile-val{color:#fff;font-size:clamp(34px,5vw,60px);letter-spacing:-.02em;line-height:1;margin:4px 0 10px;position:relative;z-index:1;word-break:break-word}
        .ovr-hero .ovr-tile-sub{color:var(--secc);font-weight:500}

        .ovr-tile--alert{background:#fff5f4;border-color:#f4cfca}
        .ovr-tile--alert .ovr-tile-h .material-symbols-outlined{color:var(--err)}
        .ovr-tile--warn{background:#fffaf0;border-color:#ecdca8}
        .ovr-tile--warn .ovr-tile-h .material-symbols-outlined{color:var(--ter)}
        .ovr-tile--qa{justify-content:center;align-items:center;text-align:center;gap:14px}
        .ovr-qa-row{display:flex;gap:10px;justify-content:center}
        .ovr-qa-btn{width:46px;height:46px;border-radius:50%;background:var(--surf);border:1px solid var(--ov);display:flex;align-items:center;justify-content:center;color:var(--p);text-decoration:none;transition:background .2s,color .2s}
        .ovr-qa-btn:hover{background:var(--p);color:#fff}

        /* Lower section: chart + activity. */
        .ovr-lower{display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:stretch}
        .ovr-panel{background:var(--surf);border:1px solid #e3e8e7;border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.04);display:flex;flex-direction:column}
        .ovr-panel-h{display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #eceeed}
        .ovr-panel-h h2{font-size:18px;font-weight:600;margin:0;padding:0;color:var(--on)}
        .ovr-panel-h a{font-size:12px;font-weight:600;letter-spacing:.04em;color:var(--p);text-decoration:none}

        .ovr-chart{padding:24px}
        .ovr-bars{display:flex;align-items:flex-end;gap:14px;height:220px;padding-top:8px}
        .ovr-bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;height:100%;justify-content:flex-end}
        .ovr-bar-wrap{width:100%;flex:1;display:flex;align-items:flex-end;justify-content:center}
        .ovr-bar{width:58%;max-width:44px;min-height:4px;background:var(--p);border-radius:8px 8px 0 0;position:relative;transition:background .2s}
        .ovr-bar:hover{background:var(--pc)}
        .ovr-bar-val{position:absolute;top:-20px;left:50%;transform:translateX(-50%);font-size:11px;font-weight:600;color:var(--sv);white-space:nowrap}
        .ovr-bar-lbl{font-size:12px;color:var(--sv);font-weight:500}
        .ovr-chart-empty{height:220px;display:flex;align-items:center;justify-content:center;color:var(--sv);font-size:14px;border:1px dashed var(--ov);border-radius:12px}

        .ovr-feed{padding:8px 24px 16px;flex:1}
        .ovr-feed li{display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f1f3f2;list-style:none;margin:0}
        .ovr-feed li:last-child{border-bottom:none}
        .ovr-feed-ic{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .ovr-feed-ic .material-symbols-outlined{font-size:19px}
        .ovr-feed-tx{font-size:14px;line-height:1.45;color:var(--on);margin:0}
        .ovr-feed-tx strong{font-weight:600}
        .ovr-feed-ago{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);margin:4px 0 0}
        .ovr-feed-empty{padding:40px 0;text-align:center;color:var(--sv);font-size:14px}

        @media (max-width:1100px){
            .ovr-bento{grid-template-columns:repeat(2,1fr)}
            .ovr-hero{grid-column:span 2}
            .ovr-lower{grid-template-columns:1fr}
        }
        @media (max-width:600px){
            .ovr-dash{padding:0 16px}
            .ovr-dh h1{font-size:26px}
            .ovr-bento{grid-template-columns:1fr;gap:16px}
            .ovr-lower{gap:16px}
            .ovr-hero{grid-column:span 1}
            .ovr-tile{padding:20px}
            .ovr-bars{gap:6px;height:180px}
            .ovr-bar-val{display:none}
            .ovr-panel-h{flex-wrap:wrap;gap:4px}
        }
    </style>

    <div class="ovr-dh">
        <div>
            <h1><?php esc_html_e( 'Platform Overview', 'ovr-core' ); ?></h1>
            <p><?php esc_html_e( 'Here is the latest snapshot of every property, member, and transaction across Our Village Rentals.', 'ovr-core' ); ?></p>
        </div>
        <div class="ovr-dh-actions">
            <a class="ovr-btn ovr-btn--ghost" href="<?php echo esc_url( $settings_url ); ?>">
                <span class="material-symbols-outlined">settings</span><?php esc_html_e( 'Settings', 'ovr-core' ); ?>
            </a>
            <a class="ovr-btn ovr-btn--primary" href="<?php echo esc_url( $add_property_url ); ?>">
                <span class="material-symbols-outlined">add</span><?php esc_html_e( 'Add Property', 'ovr-core' ); ?>
            </a>
        </div>
    </div>

    <div class="ovr-bento">

        <!-- Revenue this month (hero) -->
        <div class="ovr-tile ovr-hero">
            <div class="ovr-tile-h">
                <span class="material-symbols-outlined fill-icon">monitoring</span>
                <p class="ovr-tile-lbl"><?php esc_html_e( 'Revenue This Month', 'ovr-core' ); ?></p>
            </div>
            <p class="ovr-tile-val"><?php echo esc_html( $sym . number_format( (float) $stats['revenue_month'], 2 ) ); ?></p>
            <div class="ovr-tile-sub">
                <?php if ( null === $rev_change ) : ?>
                    <span><?php echo esc_html( $sym . number_format( (float) $stats['revenue_total'], 0 ) ); ?> <?php esc_html_e( 'all-time', 'ovr-core' ); ?></span>
                <?php else : ?>
                    <span>
                        <span class="material-symbols-outlined"><?php echo $rev_change >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                        <?php echo esc_html( sprintf( __( '%s%% vs last month', 'ovr-core' ), ( $rev_change >= 0 ? '+' : '' ) . $rev_change ) ); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Total listings -->
        <a class="ovr-tile" href="<?php echo esc_url( $all_props_url ); ?>">
            <div class="ovr-tile-h">
                <span class="material-symbols-outlined">real_estate_agent</span>
                <p class="ovr-tile-lbl"><?php esc_html_e( 'Total Listings', 'ovr-core' ); ?></p>
            </div>
            <p class="ovr-tile-val"><?php echo esc_html( number_format( (int) $stats['properties_total'] ) ); ?></p>
            <div class="ovr-tile-sub">
                <span><?php printf( esc_html__( 'Active: %s', 'ovr-core' ), '<strong style="color:var(--p)">' . esc_html( number_format( (int) $stats['properties_active'] ) ) . '</strong>' ); ?></span>
                <?php if ( $stats['properties_new_week'] > 0 ) : ?>
                    <span class="ovr-up"><span class="material-symbols-outlined">arrow_upward</span><?php echo esc_html( number_format( (int) $stats['properties_new_week'] ) ); ?></span>
                <?php endif; ?>
            </div>
        </a>

        <!-- Total users -->
        <a class="ovr-tile" href="<?php echo esc_url( $all_users_url ); ?>">
            <div class="ovr-tile-h">
                <span class="material-symbols-outlined">group</span>
                <p class="ovr-tile-lbl"><?php esc_html_e( 'Total Members', 'ovr-core' ); ?></p>
            </div>
            <p class="ovr-tile-val"><?php echo esc_html( number_format( (int) $stats['users_total'] ) ); ?></p>
            <div class="ovr-tile-sub">
                <?php if ( $stats['users_new_week'] > 0 ) : ?>
                    <span class="ovr-up"><span class="material-symbols-outlined">arrow_upward</span>
                        <?php echo esc_html( sprintf( _n( '%s new this week', '%s new this week', (int) $stats['users_new_week'], 'ovr-core' ), number_format( (int) $stats['users_new_week'] ) ) ); ?>
                    </span>
                <?php else : ?>
                    <span><?php printf( esc_html__( '%s owners', 'ovr-core' ), esc_html( number_format( (int) $stats['landlords_total'] ) ) ); ?></span>
                <?php endif; ?>
            </div>
        </a>

        <!-- Pending reviews -->
        <div class="ovr-tile ovr-tile--alert">
            <div class="ovr-tile-h">
                <span class="material-symbols-outlined">rate_review</span>
                <p class="ovr-tile-lbl"><?php esc_html_e( 'Pending Reviews', 'ovr-core' ); ?></p>
            </div>
            <p class="ovr-tile-val"><?php echo esc_html( number_format( (int) $stats['reviews_pending'] ) ); ?></p>
            <span class="ovr-tile-sub"><?php esc_html_e( 'Awaiting moderation', 'ovr-core' ); ?></span>
        </div>

        <!-- Pending renewals -->
        <a class="ovr-tile ovr-tile--warn" href="<?php echo esc_url( $all_props_url ); ?>">
            <div class="ovr-tile-h">
                <span class="material-symbols-outlined">autorenew</span>
                <p class="ovr-tile-lbl"><?php esc_html_e( 'Pending Renewals', 'ovr-core' ); ?></p>
            </div>
            <p class="ovr-tile-val"><?php echo esc_html( number_format( (int) $stats['renewals_pending'] ) ); ?></p>
            <span class="ovr-link"><?php esc_html_e( 'Manage', 'ovr-core' ); ?> <span class="material-symbols-outlined" style="font-size:16px">arrow_forward</span></span>
        </a>

        <!-- Inquiries today -->
        <div class="ovr-tile">
            <div class="ovr-tile-h">
                <span class="material-symbols-outlined">forum</span>
                <p class="ovr-tile-lbl"><?php esc_html_e( 'Inquiries Today', 'ovr-core' ); ?></p>
            </div>
            <p class="ovr-tile-val"><?php echo esc_html( number_format( (int) $stats['inquiries_today'] ) ); ?></p>
            <span class="ovr-tile-sub">
                <?php if ( null === $avg_reply ) : ?>
                    <?php printf( esc_html__( '%s total all-time', 'ovr-core' ), esc_html( number_format( (int) $stats['inquiries_total'] ) ) ); ?>
                <?php else : ?>
                    <?php printf( esc_html__( 'Avg response: %s hrs', 'ovr-core' ), esc_html( $avg_reply ) ); ?>
                <?php endif; ?>
            </span>
        </div>

        <!-- Quick actions -->
        <div class="ovr-tile ovr-tile--qa">
            <p class="ovr-tile-lbl"><?php esc_html_e( 'Quick Actions', 'ovr-core' ); ?></p>
            <div class="ovr-qa-row">
                <a class="ovr-qa-btn" href="<?php echo esc_url( $all_props_url ); ?>" title="<?php esc_attr_e( 'Manage Listings', 'ovr-core' ); ?>"><span class="material-symbols-outlined">home_work</span></a>
                <a class="ovr-qa-btn" href="<?php echo esc_url( $users_url ); ?>" title="<?php esc_attr_e( 'Manage Members', 'ovr-core' ); ?>"><span class="material-symbols-outlined">person_add</span></a>
                <a class="ovr-qa-btn" href="<?php echo esc_url( $payments_url ); ?>" title="<?php esc_attr_e( 'Settings & Payments', 'ovr-core' ); ?>"><span class="material-symbols-outlined">receipt_long</span></a>
            </div>
        </div>
    </div>

    <div class="ovr-lower">

        <!-- Growth Trends -->
        <div class="ovr-panel">
            <div class="ovr-panel-h">
                <h2><?php esc_html_e( 'Growth Trends', 'ovr-core' ); ?></h2>
                <span style="font-size:12px;color:var(--sv);font-weight:600"><?php esc_html_e( 'Revenue · last 6 months', 'ovr-core' ); ?></span>
            </div>
            <div class="ovr-chart">
                <?php if ( $peak <= 0 ) : ?>
                    <div class="ovr-chart-empty"><?php esc_html_e( 'No completed revenue to chart yet.', 'ovr-core' ); ?></div>
                <?php else : ?>
                    <div class="ovr-bars">
                        <?php foreach ( $series as $pt ) :
                            $h = max( 2, round( ( (float) $pt['total'] / $peak ) * 100 ) );
                        ?>
                            <div class="ovr-bar-col">
                                <div class="ovr-bar-wrap">
                                    <div class="ovr-bar" style="height:<?php echo esc_attr( $h ); ?>%">
                                        <?php if ( $pt['total'] > 0 ) : ?>
                                            <span class="ovr-bar-val"><?php echo esc_html( $sym . number_format( (float) $pt['total'], 0 ) ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="ovr-bar-lbl"><?php echo esc_html( $pt['label'] ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="ovr-panel">
            <div class="ovr-panel-h">
                <h2><?php esc_html_e( 'Recent Activity', 'ovr-core' ); ?></h2>
                <a href="<?php echo esc_url( $all_props_url ); ?>"><?php esc_html_e( 'VIEW ALL', 'ovr-core' ); ?></a>
            </div>
            <?php if ( empty( $activity ) ) : ?>
                <div class="ovr-feed-empty"><?php esc_html_e( 'No activity recorded yet.', 'ovr-core' ); ?></div>
            <?php else : ?>
                <ul class="ovr-feed">
                    <?php foreach ( $activity as $a ) :
                        $tone = $tones[ $a['tone'] ] ?? $tones['neutral'];
                    ?>
                        <li>
                            <span class="ovr-feed-ic" style="background:<?php echo esc_attr( $tone['bg'] ); ?>;color:<?php echo esc_attr( $tone['fg'] ); ?>">
                                <span class="material-symbols-outlined"><?php echo esc_html( $a['icon'] ); ?></span>
                            </span>
                            <div>
                                <p class="ovr-feed-tx"><?php echo wp_kses( $a['text'], [ 'strong' => [] ] ); ?></p>
                                <p class="ovr-feed-ago"><?php echo esc_html( sprintf( __( '%s ago', 'ovr-core' ), human_time_diff( $a['ts'] ) ) ); ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

</div>
