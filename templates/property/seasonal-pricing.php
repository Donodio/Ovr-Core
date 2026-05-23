<?php
/**
 * Rates / Pricing Table.
 *
 * Flexible seasonal rates (DESIGN.md §10) pulled from ovr_seasonal_pricing.
 * Shows nightly always; weekly/monthly columns appear only when any row has
 * those rates — rates are never converted between terms.
 *
 * @package OVR
 *
 * @var int   $post_id  Required. Property post ID.
 * @var array $pricing  Optional. Pre-fetched rows from SeasonalPricing::get_pricing().
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Property\SeasonalPricing;

$post_id = $post_id ?? 0;
$pricing = $pricing ?? SeasonalPricing::get_pricing( $post_id );

if ( empty( $pricing ) ) {
    return;
}

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';
$date_fmt = get_option( 'date_format' ) ?: 'M j, Y';

// Only show weekly / monthly columns when at least one row provides them.
$show_weekly  = false;
$show_monthly = false;
foreach ( $pricing as $season ) {
    if ( ! empty( $season['weekly_rate'] ) )  { $show_weekly = true; }
    if ( ! empty( $season['monthly_rate'] ) ) { $show_monthly = true; }
}

$money = static function ( $value ) use ( $symbol ) {
    return $value > 0 ? $symbol . number_format( (float) $value, 0 ) : '—';
};
?>
<section class="ovr-detail-section ovr-seasonal-pricing" data-purpose="rates-table">
    <div class="ovr-detail-card">
        <h2 class="ovr-detail-heading"><?php esc_html_e( 'Rates / Pricing', 'ovr-core' ); ?></h2>
        <div class="ovr-rates-wrap">
            <table class="ovr-rates-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Season', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Date Range', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Nightly', 'ovr-core' ); ?></th>
                        <?php if ( $show_weekly ) : ?>
                            <th><?php esc_html_e( 'Weekly', 'ovr-core' ); ?></th>
                        <?php endif; ?>
                        <?php if ( $show_monthly ) : ?>
                            <th><?php esc_html_e( 'Monthly', 'ovr-core' ); ?></th>
                        <?php endif; ?>
                        <th><?php esc_html_e( 'Min. Stay', 'ovr-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $pricing as $season ) :
                        $name     = $season['season_name'] ?? '';
                        $start    = $season['start_date']  ?? '';
                        $end      = $season['end_date']    ?? '';
                        $min_stay = absint( $season['min_stay'] ?? 1 );
                    ?>
                        <tr>
                            <td class="ovr-rates-season"><?php echo esc_html( $name ); ?></td>
                            <td>
                                <?php
                                if ( $start && $end ) {
                                    printf(
                                        '%s &mdash; %s',
                                        esc_html( wp_date( $date_fmt, strtotime( $start ) ) ),
                                        esc_html( wp_date( $date_fmt, strtotime( $end ) ) )
                                    );
                                }
                                ?>
                            </td>
                            <td class="ovr-rates-amount"><?php echo esc_html( $money( $season['nightly_rate'] ?? 0 ) ); ?></td>
                            <?php if ( $show_weekly ) : ?>
                                <td><?php echo esc_html( $money( $season['weekly_rate'] ?? 0 ) ); ?></td>
                            <?php endif; ?>
                            <?php if ( $show_monthly ) : ?>
                                <td><?php echo esc_html( $money( $season['monthly_rate'] ?? 0 ) ); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php
                                /* translators: %d: minimum nights */
                                printf( esc_html( _n( '%d night', '%d nights', $min_stay, 'ovr-core' ) ), $min_stay );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
