<?php
/**
 * Listing Upgrades tab — active boosts + available upgrade products.
 * Scoped under `.ovr-ld`; the dashboard shell supplies the surrounding nav.
 *
 * Purchases are per-listing: a buyer reaches this tab via a listing's "Bump"
 * button (?post=ID), which sets $boost_post. The CTAs then carry that listing
 * into checkout; when paid, UpgradeActivator turns the purchase into a live,
 * time-boxed boost. "Active Upgrades" reflects every listing with a live boost.
 *
 * @package OVR
 * @var \WP_Post[]      $boosted     Listings with a live boost.
 * @var \WP_Post|null   $boost_post  Listing chosen via "Bump" (or null).
 * @var \WP_Post[]      $properties  All the user's listings.
 * @var array           $upgrades
 * @var string          $checkout_url
 * @var string          $props_url
 * @var string          $base_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Subscription\UpgradeActivator;

$boosted      = $boosted ?? [];
$boost_post   = $boost_post ?? null;
$properties   = $properties ?? [];
$upgrades     = $upgrades ?? [];
$checkout_url = $checkout_url ?? '';
$props_url    = $props_url ?? add_query_arg( 'tab', 'properties', $base_url );
$boost_id     = $boost_post ? (int) $boost_post->ID : 0;
$boost_count  = count( $boosted );
?>

<header class="ld-up-header">
    <h1 class="ld-up-title"><?php esc_html_e( 'Listing Upgrades', 'ovr-core' ); ?></h1>
    <p class="ld-up-lede"><?php esc_html_e( "Maximize your property's visibility and secure more bookings. Choose from our premium placement options to stand out in the village.", 'ovr-core' ); ?></p>
</header>

<?php if ( $boost_post ) : ?>
    <div class="ld-up-context is-selected">
        <span class="material-symbols-outlined">trending_up</span>
        <div>
            <p class="ld-up-context-lbl"><?php esc_html_e( 'Boosting this listing', 'ovr-core' ); ?></p>
            <p class="ld-up-context-name"><?php echo esc_html( $boost_post->post_title ?: __( '(untitled listing)', 'ovr-core' ) ); ?></p>
        </div>
        <a class="ld-up-context-change" href="<?php echo esc_url( $props_url ); ?>"><?php esc_html_e( 'Change listing', 'ovr-core' ); ?></a>
    </div>
<?php else : ?>
    <div class="ld-up-context">
        <span class="material-symbols-outlined">info</span>
        <div>
            <p class="ld-up-context-lbl"><?php esc_html_e( 'Pick a listing to boost', 'ovr-core' ); ?></p>
            <p class="ld-up-context-name"><?php esc_html_e( 'Open My Listings and click “Bump” on the property you want to promote — that brings you back here ready to buy.', 'ovr-core' ); ?></p>
        </div>
        <a class="ld-up-context-change" href="<?php echo esc_url( $props_url ); ?>"><?php esc_html_e( 'Go to My Listings', 'ovr-core' ); ?></a>
    </div>
<?php endif; ?>

<!-- Active Upgrades -->
<section class="ld-up-section">
    <div class="ld-up-sechead">
        <h2 class="ld-up-h2"><?php esc_html_e( 'Active Upgrades', 'ovr-core' ); ?></h2>
        <span class="ld-up-count">
            <?php printf( esc_html( _n( '%s Property Boosted', '%s Properties Boosted', $boost_count, 'ovr-core' ) ), esc_html( number_format( $boost_count ) ) ); ?>
        </span>
    </div>

    <?php if ( empty( $boosted ) ) : ?>
        <div class="ld-up-empty">
            <span class="material-symbols-outlined">rocket_launch</span>
            <p><?php esc_html_e( 'None of your listings are boosted yet. Pick an upgrade below to stand out.', 'ovr-core' ); ?></p>
        </div>
    <?php else : ?>
        <div class="ld-up-active-list">
            <?php foreach ( $boosted as $p ) :
                $thumb    = get_the_post_thumbnail_url( $p->ID, 'large' ) ?: OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';
                $beds     = (int) get_post_meta( $p->ID, '_ovr_bedrooms', true );
                $baths_r  = (float) get_post_meta( $p->ID, '_ovr_bathrooms', true );
                $baths    = rtrim( rtrim( number_format( $baths_r, 1 ), '0' ), '.' );
                $city     = (string) get_post_meta( $p->ID, '_ovr_city', true );
                $state    = (string) get_post_meta( $p->ID, '_ovr_state', true );
                $loc      = trim( implode( ', ', array_filter( [ $city, $state ] ) ) );
                $edit     = add_query_arg( [ 'tab' => 'add-listing', 'post' => $p->ID ], $base_url );
                $rebump   = add_query_arg( [ 'tab' => 'upgrades', 'post' => $p->ID ], $base_url );
                $meta     = array_filter( [
                    $beds ? sprintf( _n( '%d Bed', '%d Beds', $beds, 'ovr-core' ), $beds ) : '',
                    $baths ? sprintf( __( '%s Bath', 'ovr-core' ), $baths ) : '',
                    $loc,
                ] );
                // Live boosts on this listing + the soonest expiry.
                $active_ids = UpgradeActivator::active_products( $p->ID );
                $labels     = UpgradeActivator::active_labels( $p->ID );
                $expiries   = array_filter( array_map(
                    static fn( $id ) => UpgradeActivator::expires_for( $p->ID, $id ),
                    $active_ids
                ) );
                sort( $expiries );
                $next_exp = $expiries[0] ?? '';
            ?>
                <article class="ld-up-active">
                    <div class="ld-up-active-media" style="background-image:url('<?php echo esc_url( $thumb ); ?>')">
                        <span class="ld-up-active-tag">
                            <span class="material-symbols-outlined fill">verified</span><?php echo esc_html( $labels ? implode( ' · ', $labels ) : __( 'Boosted', 'ovr-core' ) ); ?>
                        </span>
                    </div>
                    <div class="ld-up-active-body">
                        <div>
                            <div class="ld-up-active-titlerow">
                                <h3><?php echo esc_html( $p->post_title ?: __( '(untitled)', 'ovr-core' ) ); ?></h3>
                                <span class="ld-up-active-status">
                                    <span class="material-symbols-outlined">check_circle</span><?php esc_html_e( 'Active', 'ovr-core' ); ?>
                                </span>
                            </div>
                            <?php if ( $meta ) : ?>
                                <p class="ld-up-active-meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="ld-up-active-foot">
                            <div>
                                <p class="ld-up-active-foot-lbl"><?php esc_html_e( 'Active until', 'ovr-core' ); ?></p>
                                <p class="ld-up-active-foot-val">
                                    <?php echo $next_exp ? esc_html( mysql2date( get_option( 'date_format' ) ?: 'M j, Y', $next_exp ) ) : esc_html__( 'Boosted', 'ovr-core' ); ?>
                                </p>
                            </div>
                            <a href="<?php echo esc_url( (string) $rebump ); ?>" class="ld-up-manage">
                                <?php esc_html_e( 'Extend / add', 'ovr-core' ); ?> <span class="material-symbols-outlined">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Available Upgrades -->
<section class="ld-up-section">
    <div class="ld-up-avail-head">
        <h2 class="ld-up-h2"><?php esc_html_e( 'Available Upgrades', 'ovr-core' ); ?></h2>
        <p class="ld-up-lede"><?php esc_html_e( "Select a boost to elevate your property's performance.", 'ovr-core' ); ?></p>
    </div>

    <?php if ( empty( $upgrades ) ) : ?>
        <div class="ld-up-empty">
            <span class="material-symbols-outlined">sell</span>
            <p><?php esc_html_e( 'No upgrade services are available right now. Please check back soon.', 'ovr-core' ); ?></p>
        </div>
    <?php else : ?>
    <div class="ld-up-grid">
        <?php foreach ( $upgrades as $u ) :
            $hl        = ! empty( $u['highlight'] );
            $price     = (float) ( $u['price'] ?? 0 );
            $price_lbl = rtrim( rtrim( number_format( $price, 2 ), '0' ), '.' );
            $days      = (int) ( $u['duration_days'] ?? 14 );
        ?>
            <div class="ld-up-card<?php echo $hl ? ' is-premium' : ''; ?>">
                <?php if ( ! empty( $u['badge'] ) ) : ?>
                    <span class="ld-up-prembadge">
                        <span class="material-symbols-outlined fill">workspace_premium</span><?php echo esc_html( $u['badge'] ); ?>
                    </span>
                <?php endif; ?>

                <div class="ld-up-card-ic<?php echo $hl ? ' is-premium' : ''; ?>">
                    <span class="material-symbols-outlined fill"><?php echo esc_html( $u['icon'] ?? 'star' ); ?></span>
                </div>
                <h3 class="ld-up-card-name"><?php echo esc_html( $u['name'] ?? '' ); ?></h3>
                <p class="ld-up-card-desc"><?php echo esc_html( $u['desc'] ?? '' ); ?></p>

                <div class="ld-up-price">
                    <span class="ld-up-price-amt">$<span class="ld-up-price-num"><?php echo esc_html( $price_lbl ); ?></span></span>
                    <span class="ld-up-price-term"><?php printf( esc_html( _n( '/ %d day', '/ %d days', $days, 'ovr-core' ) ), $days ); ?></span>
                </div>

                <ul class="ld-up-feats<?php echo $hl ? ' is-premium' : ''; ?>">
                    <?php foreach ( (array) ( $u['features'] ?? [] ) as $feat ) : ?>
                        <li><span class="material-symbols-outlined">check_circle</span><?php echo esc_html( $feat ); ?></li>
                    <?php endforeach; ?>
                </ul>

                <?php if ( $boost_id ) : ?>
                    <?php $cta_url = add_query_arg( [ 'service' => $u['id'] ?? '', 'property' => $boost_id ], $checkout_url ); ?>
                    <a href="<?php echo esc_url( $cta_url ); ?>" class="ld-up-cta<?php echo $hl ? ' is-premium' : ''; ?>">
                        <?php echo $hl ? esc_html__( 'Purchase Upgrade', 'ovr-core' ) : esc_html__( 'Select Upgrade', 'ovr-core' ); ?>
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url( $props_url ); ?>" class="ld-up-cta<?php echo $hl ? ' is-premium' : ''; ?>">
                        <?php esc_html_e( 'Choose a listing first', 'ovr-core' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<style>
    .ovr-ld .ld-up-header{margin-bottom:8px}
    .ovr-ld .ld-up-title{font-size:40px;font-weight:700;letter-spacing:-.02em;color:var(--on);margin:0 0 12px;line-height:1.1}
    .ovr-ld .ld-up-lede{font-size:17px;color:var(--sv);margin:0;max-width:640px;line-height:1.6}

    /* Listing-context banner */
    .ovr-ld .ld-up-context{display:flex;align-items:center;gap:14px;background:var(--sclow);border:1px solid var(--ov);border-radius:14px;padding:16px 20px;margin:18px 0 4px}
    .ovr-ld .ld-up-context.is-selected{background:rgba(0,76,76,.06);border-color:var(--pfd)}
    .ovr-ld .ld-up-context>.material-symbols-outlined{font-size:26px;color:var(--p);flex-shrink:0}
    .ovr-ld .ld-up-context-lbl{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--sv);margin:0 0 2px}
    .ovr-ld .ld-up-context-name{font-size:15px;font-weight:600;color:var(--on);margin:0}
    .ovr-ld .ld-up-context-change{margin-left:auto;flex-shrink:0;color:var(--p);font-weight:600;font-size:13px;text-decoration:none;white-space:nowrap}
    .ovr-ld .ld-up-context-change:hover{color:var(--pc);text-decoration:underline}

    .ovr-ld .ld-up-section{margin-top:8px}
    .ovr-ld .ld-up-sechead{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:16px;flex-wrap:wrap}
    .ovr-ld .ld-up-h2{font-size:26px;font-weight:700;letter-spacing:-.01em;color:var(--on);margin:0}
    .ovr-ld .ld-up-count{font-size:14px;color:var(--sv)}

    /* Active upgrade card */
    .ovr-ld .ld-up-active-list{display:flex;flex-direction:column;gap:20px}
    .ovr-ld .ld-up-active{background:var(--surf);border:1px solid var(--ov);border-radius:18px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 8px 30px rgba(0,0,0,.06)}
    .ovr-ld .ld-up-active-media{width:100%;min-height:200px;background-size:cover;background-position:center;position:relative}
    .ovr-ld .ld-up-active-media::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.45),transparent 60%)}
    .ovr-ld .ld-up-active-tag{position:absolute;top:16px;left:16px;z-index:1;display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.92);backdrop-filter:blur(4px);padding:6px 12px;border-radius:9999px;font-size:12px;font-weight:600;color:var(--on)}
    .ovr-ld .ld-up-active-tag .material-symbols-outlined{font-size:16px;color:var(--terc)}
    .ovr-ld .ld-up-active-body{padding:24px;display:flex;flex-direction:column;justify-content:space-between;flex:1;gap:18px}
    .ovr-ld .ld-up-active-titlerow{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
    .ovr-ld .ld-up-active-titlerow h3{font-size:22px;font-weight:600;color:var(--on);margin:0}
    .ovr-ld .ld-up-active-status{display:inline-flex;align-items:center;gap:5px;color:var(--sec);background:rgba(0,108,74,.12);border:1px solid rgba(0,108,74,.2);padding:4px 12px;border-radius:9999px;font-size:12px;font-weight:600;white-space:nowrap}
    .ovr-ld .ld-up-active-status .material-symbols-outlined{font-size:15px}
    .ovr-ld .ld-up-active-meta{font-size:14px;color:var(--sv);margin:6px 0 0}
    .ovr-ld .ld-up-active-foot{display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--ov);padding-top:18px;gap:16px;flex-wrap:wrap}
    .ovr-ld .ld-up-active-foot-lbl{font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--sv);margin:0 0 3px}
    .ovr-ld .ld-up-active-foot-val{font-size:14px;font-weight:600;color:var(--on);margin:0}
    .ovr-ld .ld-up-manage{display:inline-flex;align-items:center;gap:4px;color:var(--p);font-weight:600;font-size:14px;text-decoration:none}
    .ovr-ld .ld-up-manage:hover{color:var(--pc)}
    .ovr-ld .ld-up-manage .material-symbols-outlined{font-size:17px}

    @media (min-width:760px){
        .ovr-ld .ld-up-active{flex-direction:row}
        .ovr-ld .ld-up-active-media{width:34%;min-height:0}
    }

    /* Empty state */
    .ovr-ld .ld-up-empty{background:var(--surf);border:1px dashed var(--ov);border-radius:16px;padding:40px 24px;text-align:center;color:var(--sv);display:flex;flex-direction:column;align-items:center;gap:12px}
    .ovr-ld .ld-up-empty .material-symbols-outlined{font-size:40px;color:var(--ov)}

    /* Available */
    .ovr-ld .ld-up-avail-head{margin-bottom:24px}
    .ovr-ld .ld-up-avail-head .ld-up-lede{font-size:15px;margin-top:4px}
    .ovr-ld .ld-up-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;align-items:stretch}

    .ovr-ld .ld-up-card{background:var(--surf);border:1px solid var(--ov);border-radius:18px;padding:32px;display:flex;flex-direction:column;box-shadow:0 8px 30px rgba(0,0,0,.06);transition:transform .3s}
    .ovr-ld .ld-up-card:hover{transform:translateY(-4px)}
    .ovr-ld .ld-up-card.is-premium{position:relative;border:2px solid var(--terc);box-shadow:0 8px 30px rgba(204,167,47,.18)}
    .ovr-ld .ld-up-prembadge{position:absolute;top:-15px;left:50%;transform:translateX(-50%);background:var(--terc);color:#4e3d00;font-size:12px;font-weight:700;letter-spacing:.03em;padding:6px 16px;border-radius:9999px;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;box-shadow:0 2px 6px rgba(0,0,0,.12)}
    .ovr-ld .ld-up-prembadge .material-symbols-outlined{font-size:15px}
    .ovr-ld .ld-up-card-ic{width:48px;height:48px;border-radius:12px;background:var(--sclow);display:flex;align-items:center;justify-content:center;margin-bottom:18px}
    .ovr-ld .ld-up-card-ic .material-symbols-outlined{font-size:26px;color:var(--p)}
    .ovr-ld .ld-up-card-ic.is-premium{background:rgba(204,167,47,.12);border:1px solid rgba(204,167,47,.25);margin-top:8px}
    .ovr-ld .ld-up-card-ic.is-premium .material-symbols-outlined{color:var(--terc)}
    .ovr-ld .ld-up-card-name{font-size:22px;font-weight:600;color:var(--on);margin:0 0 8px}
    .ovr-ld .ld-up-card-desc{font-size:14px;color:var(--sv);margin:0 0 22px;line-height:1.6;min-height:44px}

    .ovr-ld .ld-up-toggle{display:flex;background:var(--sclow);padding:4px;border-radius:10px;margin-bottom:22px}
    .ovr-ld .ld-up-term{flex:1;border:none;background:transparent;padding:9px 0;border-radius:7px;font-family:inherit;font-size:12px;font-weight:600;letter-spacing:.03em;color:var(--sv);cursor:pointer;transition:background .15s,color .15s}
    .ovr-ld .ld-up-term.is-active{background:var(--surf);color:var(--on);box-shadow:0 1px 3px rgba(0,0,0,.1)}

    .ovr-ld .ld-up-price{display:flex;align-items:baseline;gap:6px;margin-bottom:4px}
    .ovr-ld .ld-up-price-amt{font-size:34px;font-weight:700;color:var(--on);line-height:1}
    .ovr-ld .ld-up-price-term{font-size:14px;color:var(--sv)}
    .ovr-ld .ld-up-save{font-size:12px;font-weight:600;letter-spacing:.03em;color:var(--sec);margin:0 0 4px}

    .ovr-ld .ld-up-feats{list-style:none;margin:24px 0;padding:0;display:flex;flex-direction:column;gap:12px;flex:1}
    .ovr-ld .ld-up-feats li{display:flex;align-items:flex-start;gap:12px;font-size:14px;color:var(--on)}
    .ovr-ld .ld-up-feats .material-symbols-outlined{font-size:20px;color:var(--p);flex-shrink:0}
    .ovr-ld .ld-up-feats.is-premium .material-symbols-outlined{color:var(--terc)}

    .ovr-ld .ld-up-cta{display:block;text-align:center;padding:13px 16px;border-radius:12px;font-size:14px;font-weight:600;text-decoration:none;margin-top:auto;border:2px solid var(--p);color:var(--p);transition:background .18s,box-shadow .18s}
    .ovr-ld .ld-up-cta:hover{background:rgba(0,76,76,.06);color:var(--p)}
    .ovr-ld .ld-up-cta.is-premium{background:var(--p);color:#fff;border-color:var(--p);box-shadow:0 4px 14px rgba(0,76,76,.39)}
    .ovr-ld .ld-up-cta.is-premium:hover{background:#003838;color:#fff}

    @media (max-width:1100px){
        .ovr-ld .ld-up-grid{grid-template-columns:1fr 1fr}
    }
    @media (max-width:760px){
        .ovr-ld .ld-up-title{font-size:30px}
        .ovr-ld .ld-up-grid{grid-template-columns:1fr}
    }
</style>
