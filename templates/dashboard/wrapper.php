<?php
/**
 * Dashboard wrapper — landlord app shell: grouped sidebar nav + active tab.
 *
 * Page-scoped under `.ovr-ld` with the teal mockup palette (Inter), matching
 * the admin Platform Overview. The sidebar is shared across every tab; on
 * mobile it becomes a CSS-only slide-in drawer (no JS dependency).
 *
 * @package OVR
 *
 * @var \WP_User $user
 * @var array    $tabs
 * @var string   $current_tab
 * @var string   $base_url
 * @var int      $nav_new_inquiries
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\TemplateLoader;
use OVR\Core\Pages;

$nav_new_inquiries = $nav_new_inquiries ?? 0;
$pricing_url       = Pages::get_page_url( 'ovr_page_pricing' );

$tab_url = static fn( string $key ): string => add_query_arg( 'tab', $key, $base_url );

// "List Your Property" must stay in the frontend dashboard — landlords have no
// wp-admin access, so post-new.php would 403/fatal and strand them.
$add_url = $tab_url( 'add-listing' );

// Grouped navigation. Each item: [ label, icon, href, key|null, badge ].
// `key` set => internal tab (gets active state); null => external action link.
$nav_groups = [
    __( 'Dashboard', 'ovr-core' ) => [
        [ __( 'Overview', 'ovr-core' ), 'dashboard', $tab_url( 'overview' ), 'overview', 0 ],
    ],
    __( 'Properties', 'ovr-core' ) => [
        [ __( 'My Listings', 'ovr-core' ),       'home_work',    $tab_url( 'properties' ), 'properties', 0 ],
        [ __( 'List Your Property', 'ovr-core' ),'add_business', $add_url,                 null,         0 ],
        [ __( 'Listing Upgrades', 'ovr-core' ),  'trending_up',  $tab_url( 'upgrades' ),   'upgrades',   0 ],
    ],
    __( 'Communication', 'ovr-core' ) => [
        [ __( 'My Inquiries', 'ovr-core' ), 'forum', $tab_url( 'inquiries' ), 'inquiries', $nav_new_inquiries ],
    ],
    __( 'Account', 'ovr-core' ) => [
        [ __( 'My Information', 'ovr-core' ),          'person',                 $tab_url( 'profile' ),      'profile',      0 ],
        [ __( 'My Payments', 'ovr-core' ),             'payments',               $tab_url( 'payments' ),     'payments',     0 ],
        [ __( 'Subscription Management', 'ovr-core' ), 'subscriptions',          $tab_url( 'subscription' ), 'subscription', 0 ],
        [ __( 'Change Password', 'ovr-core' ),         'key',                    $tab_url( 'password' ),     'password',     0 ],
    ],
];
?>
<div class="ovr-wrap ovr-ld">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        .ovr-ld{--p:#004c4c;--pc:#006666;--opc:#93e1e0;--pfd:#86d4d3;--sec:#006c4a;--secc:#74f7be;--ter:#735c00;--terc:#cca72f;--err:#ba1a1a;--errc:#ffdad6;--bg:#f7faf9;--surf:#fff;--sclow:#f1f4f3;--sv:#3f4948;--outline:#6f7979;--ov:#bec9c8;--on:#181c1c;
            font-family:'Inter',system-ui,-apple-system,sans-serif;color:var(--on);background:var(--bg)}
        .ovr-ld .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;line-height:1}
        .ovr-ld .fill{font-variation-settings:'FILL' 1}
        .ovr-ld-navtoggle{position:absolute;opacity:0;pointer-events:none}

        /* Mobile topbar */
        .ovr-ld-topbar{display:none;align-items:center;justify-content:space-between;padding:14px 20px;background:rgba(247,250,249,.9);backdrop-filter:blur(8px);position:sticky;top:0;z-index:30;border-bottom:1px solid var(--ov)}
        .ovr-ld-brand{font-size:20px;font-weight:700;color:var(--p);letter-spacing:-.01em;text-decoration:none}
        .ovr-ld-burger{display:inline-flex;padding:8px;border:none;background:transparent;color:var(--p);cursor:pointer;border-radius:8px}
        .ovr-ld-burger:hover{background:rgba(0,76,76,.08)}

        /* Shell layout */
        .ovr-ld-shell{display:flex;gap:28px;max-width:1400px;margin:0 auto;padding:24px 40px 56px;align-items:flex-start}

        /* Sidebar */
        .ovr-ld-sidebar{width:256px;flex-shrink:0;background:var(--sclow);border:1px solid var(--ov);border-radius:18px;position:sticky;top:24px;display:flex;flex-direction:column;max-height:calc(100vh - 48px);box-shadow:0 4px 24px rgba(0,0,0,.03)}
        .ovr-ld-sidehead{height:64px;display:flex;align-items:center;padding:0 24px;border-bottom:1px solid rgba(190,201,200,.5);flex-shrink:0}
        .ovr-ld-sidehead .ovr-ld-brand{font-size:22px}
        .ovr-ld-nav{flex:1;overflow-y:auto;padding:20px 16px}
        .ovr-ld-group{margin-bottom:22px}
        .ovr-ld-group:last-child{margin-bottom:0}
        .ovr-ld-grouplbl{font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--outline);margin:0 0 8px;padding:0 12px}
        .ovr-ld-link{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;color:var(--sv);text-decoration:none;font-size:14.5px;font-weight:500;transition:background .15s,color .15s;margin-bottom:2px}
        .ovr-ld-link .material-symbols-outlined{font-size:21px}
        .ovr-ld-link:hover{background:rgba(0,76,76,.07);color:var(--p)}
        .ovr-ld-link.is-active{background:#fff;color:var(--p);font-weight:700;box-shadow:0 1px 3px rgba(0,0,0,.07)}
        .ovr-ld-link.is-active .material-symbols-outlined{font-variation-settings:'FILL' 1}
        .ovr-ld-badge{margin-left:auto;background:var(--p);color:#fff;font-size:11px;font-weight:700;min-width:20px;height:20px;padding:0 6px;border-radius:9999px;display:inline-flex;align-items:center;justify-content:center}
        .ovr-ld-profile{display:flex;align-items:center;gap:12px;padding:16px;border-top:1px solid rgba(190,201,200,.5);flex-shrink:0}
        .ovr-ld-profile img,.ovr-ld-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;border:1px solid var(--ov);flex-shrink:0}
        .ovr-ld-avatar{display:flex;align-items:center;justify-content:center;background:var(--secc);color:var(--sec);font-weight:700}
        .ovr-ld-pname{font-size:14px;font-weight:700;color:var(--on);margin:0;line-height:1.2}
        .ovr-ld-prole{font-size:12px;color:var(--sv);margin:2px 0 0}
        .ovr-ld-scrim{display:none}

        /* Canvas */
        .ovr-ld-canvas{flex:1;min-width:0;display:flex;flex-direction:column;gap:40px}

        @media (max-width:900px){
            .ovr-ld-topbar{display:flex}
            .ovr-ld-shell{padding:18px 16px 48px;gap:0}
            .ovr-ld-sidebar{position:fixed;top:0;left:0;height:100%;max-height:none;border-radius:0;border-width:0 1px 0 0;width:280px;z-index:60;transform:translateX(-100%);transition:transform .25s ease}
            .ovr-ld-navtoggle:checked ~ .ovr-ld-shell .ovr-ld-sidebar{transform:translateX(0)}
            .ovr-ld-navtoggle:checked ~ .ovr-ld-shell .ovr-ld-scrim{display:block;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:50}
            .ovr-ld-canvas{gap:32px}
        }
    </style>

    <input type="checkbox" id="ovr-ld-nav" class="ovr-ld-navtoggle">

    <header class="ovr-ld-topbar">
        <a href="<?php echo esc_url( $base_url ); ?>" class="ovr-ld-brand"><?php esc_html_e( 'Our Villages Rentals', 'ovr-core' ); ?></a>
        <label for="ovr-ld-nav" class="ovr-ld-burger" aria-label="<?php esc_attr_e( 'Open menu', 'ovr-core' ); ?>">
            <span class="material-symbols-outlined">menu</span>
        </label>
    </header>

    <div class="ovr-ld-shell">

        <aside class="ovr-ld-sidebar">
            <div class="ovr-ld-sidehead">
                <a href="<?php echo esc_url( $base_url ); ?>" class="ovr-ld-brand">OVR</a>
            </div>

            <nav class="ovr-ld-nav">
                <?php foreach ( $nav_groups as $group_label => $items ) : ?>
                    <div class="ovr-ld-group">
                        <p class="ovr-ld-grouplbl"><?php echo esc_html( $group_label ); ?></p>
                        <?php foreach ( $items as $item ) :
                            [ $label, $icon, $href, $key, $badge ] = $item;
                            $active = ( null !== $key && $key === $current_tab );
                        ?>
                            <a href="<?php echo esc_url( $href ); ?>" class="ovr-ld-link<?php echo $active ? ' is-active' : ''; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
                                <span class="material-symbols-outlined"><?php echo esc_html( $icon ); ?></span>
                                <span><?php echo esc_html( $label ); ?></span>
                                <?php if ( $badge > 0 ) : ?>
                                    <span class="ovr-ld-badge"><?php echo esc_html( (string) $badge ); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="ovr-ld-profile">
                <?php
                $avatar = get_avatar_url( $user->ID, [ 'size' => 84 ] );
                if ( $avatar ) :
                    ?>
                    <img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>">
                <?php else : ?>
                    <span class="ovr-ld-avatar"><?php echo esc_html( strtoupper( substr( $user->display_name, 0, 2 ) ) ); ?></span>
                <?php endif; ?>
                <div>
                    <p class="ovr-ld-pname"><?php echo esc_html( $user->display_name ); ?></p>
                    <p class="ovr-ld-prole"><?php esc_html_e( 'Landlord', 'ovr-core' ); ?></p>
                </div>
            </div>
        </aside>

        <label for="ovr-ld-nav" class="ovr-ld-scrim" aria-hidden="true"></label>

        <main class="ovr-ld-canvas">
            <?php
            $payload = get_defined_vars();
            switch ( $current_tab ) {
                case 'overview':     TemplateLoader::render( 'dashboard/tab-overview.php',     $payload ); break;
                case 'properties':   TemplateLoader::render( 'dashboard/tab-properties.php',   $payload ); break;
                case 'add-listing':  TemplateLoader::render( 'dashboard/tab-add-listing.php',  $payload ); break;
                case 'upgrades':     TemplateLoader::render( 'dashboard/tab-upgrades.php',     $payload ); break;
                case 'inquiries':    TemplateLoader::render( 'dashboard/tab-inquiries.php',    $payload ); break;
                case 'subscription': TemplateLoader::render( 'dashboard/tab-subscription.php', $payload ); break;
                case 'profile':      TemplateLoader::render( 'dashboard/tab-profile.php',      $payload ); break;
                case 'payments':     TemplateLoader::render( 'dashboard/tab-payments.php',     $payload ); break;
                case 'password':     TemplateLoader::render( 'dashboard/tab-password.php',     $payload ); break;
            }
            ?>
        </main>
    </div>
</div>
