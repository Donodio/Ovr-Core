<?php
/**
 * Rates / Pricing Table.
 *
 * Flexible pricing pulled from ovr_seasonal_pricing — Period · Price · Minimum
 * Stay. No nightly-rate assumption; supports weekly, monthly, seasonal, and
 * fixed-term rentals. When the table is empty, shows "See Description For
 * Pricing" instead.
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

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';

// "Check Description For Pricing" (Phase 4A) hides the table even when rows exist.
$hidden    = SeasonalPricing::is_hidden( $post_id );
$date_fmt  = get_option( 'date_format' ) ?: 'M j, Y';
?>
<section class="ovr-detail-section ovr-seasonal-pricing" data-purpose="rates-table">
    <div class="ovr-detail-card">
        <h2 class="ovr-detail-heading"><?php esc_html_e( 'Rates / Pricing', 'ovr-core' ); ?></h2>

        <?php if ( $hidden || empty( $pricing ) ) : ?>
            <p class="ovr-rates-empty"><?php esc_html_e( 'See Description For Pricing', 'ovr-core' ); ?></p>
        <?php else : ?>
            <div class="ovr-rates-wrap">
                <table class="ovr-rates-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Month or Season', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Price', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Minimum Term', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'From', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'To', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $pricing as $season ) :
                            $d = SeasonalPricing::row_display( $season );
                            // "$2,675 month" (price + period merged) — or just the
                            // amount for a flat rate.
                            $price_txt = '—';
                            if ( $d['price'] > 0 ) {
                                $price_txt = $symbol . number_format( $d['price'], 0 );
                                if ( '' !== $d['per'] ) {
                                    $price_txt .= ' ' . $d['per'];
                                }
                            }
                            $from_txt = '' !== $d['from'] ? mysql2date( $date_fmt, $d['from'] ) : '—';
                            $to_txt   = '' !== $d['to']   ? mysql2date( $date_fmt, $d['to'] )   : '—';
                        ?>
                            <tr>
                                <td class="ovr-rates-season"><?php echo esc_html( $d['period'] ); ?></td>
                                <td class="ovr-rates-amount"><?php echo esc_html( $price_txt ); ?></td>
                                <td><?php echo $d['min'] !== '' ? esc_html( $d['min'] ) : '—'; ?></td>
                                <td><?php echo esc_html( $from_txt ); ?></td>
                                <td><?php echo esc_html( $to_txt ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
