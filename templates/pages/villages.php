<?php
/**
 * Villages Archive Template.
 *
 * @var array<string, WP_Term[]> $groups  Group label => village terms.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\Pages;

?>
<?php
// Totals for the hero summary.
$total_villages = 0;
$total_homes    = 0;
foreach ( $groups as $g_villages ) {
    foreach ( $g_villages as $g_v ) {
        $total_villages++;
        $total_homes += (int) $g_v->count;
    }
}
?>
<div class="ovr-villages-page">

    <!-- Hero -->
    <header class="ovr-villages-hero">
        <div class="ovr-villages-hero-inner">
            <p class="ovr-villages-eyebrow"><?php esc_html_e( 'EXPLORE THE VILLAGES', 'ovr-core' ); ?></p>
            <h1 class="ovr-villages-title"><?php esc_html_e( 'Browse by Village', 'ovr-core' ); ?></h1>
            <p class="ovr-villages-lede">
                <?php esc_html_e( 'Find rentals across The Villages, organized by area. Pick a village to see every available home there.', 'ovr-core' ); ?>
            </p>
            <?php if ( $total_villages ) : ?>
                <div class="ovr-villages-stats">
                    <div class="ovr-villages-stat">
                        <span class="ovr-villages-stat-num"><?php echo esc_html( number_format_i18n( $total_villages ) ); ?></span>
                        <span class="ovr-villages-stat-label"><?php echo esc_html( _n( 'Village', 'Villages', $total_villages, 'ovr-core' ) ); ?></span>
                    </div>
                    <span class="ovr-villages-stat-sep" aria-hidden="true"></span>
                    <div class="ovr-villages-stat">
                        <span class="ovr-villages-stat-num"><?php echo esc_html( number_format_i18n( $total_homes ) ); ?></span>
                        <span class="ovr-villages-stat-label"><?php echo esc_html( _n( 'Home available', 'Homes available', $total_homes, 'ovr-core' ) ); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="ovr-container ovr-villages-body">
        <?php if ( empty( $groups ) ) : ?>
            <div class="ovr-villages-empty">
                <span class="material-symbols-outlined">holiday_village</span>
                <h3 class="ovr-h3"><?php esc_html_e( 'No Villages With Listings Yet', 'ovr-core' ); ?></h3>
                <p class="ovr-body-md"><?php esc_html_e( 'Check back soon, or be the first to list a property.', 'ovr-core' ); ?></p>
                <a href="<?php echo esc_url( Pages::get_page_url( 'ovr_page_register' ) ); ?>" class="ovr-btn ovr-btn-primary">
                    <?php esc_html_e( 'List Your Property', 'ovr-core' ); ?>
                </a>
            </div>
        <?php else : ?>
            <?php foreach ( $groups as $group_label => $villages ) : ?>
                <section class="ovr-villages-group">
                    <div class="ovr-villages-group-head">
                        <h2 class="ovr-villages-group-title"><?php echo esc_html( $group_label ); ?></h2>
                        <span class="ovr-villages-group-meta">
                            <?php
                            printf(
                                esc_html( _n( '%s village', '%s villages', count( $villages ), 'ovr-core' ) ),
                                esc_html( number_format_i18n( count( $villages ) ) )
                            );
                            ?>
                        </span>
                    </div>
                    <div class="ovr-villages-grid">
                        <?php foreach ( $villages as $village ) : ?>
                            <a href="<?php echo esc_url( get_term_link( $village ) ); ?>" class="ovr-village-card">
                                <span class="ovr-village-card-top">
                                    <span class="ovr-village-card-icon material-symbols-outlined">holiday_village</span>
                                    <span class="ovr-village-card-badge">
                                        <?php
                                        printf(
                                            esc_html( _n( '%s home', '%s homes', $village->count, 'ovr-core' ) ),
                                            esc_html( number_format_i18n( $village->count ) )
                                        );
                                        ?>
                                    </span>
                                </span>
                                <span class="ovr-village-card-body">
                                    <span class="ovr-village-card-name"><?php echo esc_html( $village->name ); ?></span>
                                    <span class="ovr-village-card-cta">
                                        <?php esc_html_e( 'View homes', 'ovr-core' ); ?>
                                        <span class="ovr-village-card-arrow material-symbols-outlined">arrow_forward</span>
                                    </span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
