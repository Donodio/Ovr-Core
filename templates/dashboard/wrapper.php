<?php
/**
 * Dashboard wrapper — profile + nav sidebar, quick tools, and the active tab.
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
?>
<div class="ovr-wrap">
<div class="ovr-container ovr-section ovr-dashboard-page">
    <div class="ovr-dashboard">

        <!-- Sidebar -->
        <aside class="ovr-dash-sidebar">

            <div class="ovr-card ovr-dash-profile-card">
                <div class="ovr-dash-profile">
                    <div class="ovr-dash-avatar">
                        <span class="material-symbols-outlined fill">person</span>
                    </div>
                    <div>
                        <h2 class="ovr-dash-name"><?php echo esc_html( $user->display_name ); ?></h2>
                        <p class="ovr-dash-id">
                            <?php
                            /* translators: %s: landlord (user) ID */
                            printf( esc_html__( 'Landlord ID: %s', 'ovr-core' ), esc_html( (string) $user->ID ) );
                            ?>
                        </p>
                    </div>
                </div>

                <nav class="ovr-dash-nav">
                    <?php foreach ( $tabs as $key => $tab ) :
                        $active = $key === $current_tab;
                        $url    = add_query_arg( 'tab', $key, $base_url );
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="ovr-dash-navlink<?php echo $active ? ' is-active' : ''; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
                            <span class="material-symbols-outlined"><?php echo esc_html( $tab['icon'] ); ?></span>
                            <span class="ovr-dash-navlabel"><?php echo esc_html( $tab['label'] ); ?></span>
                            <?php if ( 'inquiries' === $key && $nav_new_inquiries > 0 ) : ?>
                                <span class="ovr-dash-badge"><?php echo esc_html( (string) $nav_new_inquiries ); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Quick Tools -->
            <div class="ovr-card ovr-dash-tools">
                <h3 class="ovr-dash-tools-title"><?php esc_html_e( 'Quick Tools', 'ovr-core' ); ?></h3>
                <ul>
                    <li>
                        <a href="#">
                            <span class="material-symbols-outlined">picture_as_pdf</span>
                            <?php esc_html_e( 'Download Help Guide', 'ovr-core' ); ?>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="material-symbols-outlined">description</span>
                            <?php esc_html_e( 'Villages ID Request Form', 'ovr-core' ); ?>
                        </a>
                    </li>
                    <li>
                        <a class="is-gold" href="<?php echo esc_url( $pricing_url ); ?>">
                            <span class="material-symbols-outlined">star</span>
                            <?php esc_html_e( 'Upgrade to Featured', 'ovr-core' ); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Canvas -->
        <main class="ovr-dash-canvas">
            <?php
            // Each tab renders its own heading (overview shows the dashboard title);
            // no shared title here to avoid duplicate headings.
            $payload = get_defined_vars();
            switch ( $current_tab ) {
                case 'overview':     TemplateLoader::render( 'dashboard/tab-overview.php',     $payload ); break;
                case 'properties':   TemplateLoader::render( 'dashboard/tab-properties.php',   $payload ); break;
                case 'inquiries':    TemplateLoader::render( 'dashboard/tab-inquiries.php',    $payload ); break;
                case 'subscription': TemplateLoader::render( 'dashboard/tab-subscription.php', $payload ); break;
                case 'profile':      TemplateLoader::render( 'dashboard/tab-profile.php',      $payload ); break;
                case 'payments':     TemplateLoader::render( 'dashboard/tab-payments.php',     $payload ); break;
                case 'balance':      TemplateLoader::render( 'dashboard/tab-balance.php',      $payload ); break;
                case 'password':     TemplateLoader::render( 'dashboard/tab-password.php',     $payload ); break;
            }
            ?>
        </main>
    </div>
</div>
</div>
