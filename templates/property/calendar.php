<?php
/**
 * Property Availability Calendar.
 *
 * Up to six months in a responsive grid (DESIGN.md §10). Available days are
 * green, booked days red. Pulls blocked dates from ovr_availability. Keeps the
 * data-* hooks and .ovr-cal-day markup used by the range-picker in
 * assets/js/ovr-property.js.
 *
 * @package OVR
 *
 * @var int $post_id      Required. Property post ID.
 * @var int $months_ahead Optional. How many months to render. Default 6.
 * @var int $min_stay     Optional. Min nights to display. Default 1.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$post_id      = $post_id ?? 0;
$months_ahead = max( 1, min( 12, absint( $months_ahead ?? 6 ) ) );
$min_stay     = max( 1, absint( $min_stay ?? 1 ) );

// Pull blocked date ranges (with 12-month cache horizon for perf).
global $wpdb;
$table = $wpdb->prefix . 'ovr_availability';

$cache_key = 'ovr_avail_' . $post_id;
$blocks    = wp_cache_get( $cache_key, 'ovr' );

if ( false === $blocks ) {
    $blocks = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT start_date, end_date, show_as_available FROM {$table}
             WHERE property_id = %d
             AND end_date >= CURDATE()
             ORDER BY start_date ASC",
            $post_id
        ),
        ARRAY_A
    );
    wp_cache_set( $cache_key, $blocks, 'ovr', HOUR_IN_SECONDS );
}

// Build a quick-lookup set of blocked Y-m-d dates.
$blocked_dates = [];
if ( ! empty( $blocks ) ) {
    foreach ( $blocks as $b ) {
        // Honor "show as available" override.
        if ( ! empty( $b['show_as_available'] ) ) {
            continue;
        }
        $cursor = strtotime( $b['start_date'] );
        $end    = strtotime( $b['end_date'] );
        while ( $cursor <= $end ) {
            $blocked_dates[ wp_date( 'Y-m-d', $cursor ) ] = true;
            $cursor = strtotime( '+1 day', $cursor );
        }
    }
}

$today = current_time( 'Y-m-d' );

// Helper: render a single month.
$render_month = function( int $year, int $month ) use ( $blocked_dates, $today ) {
    $first_dow      = (int) wp_date( 'w', mktime( 0, 0, 0, $month, 1, $year ) );
    $days_in_month  = (int) wp_date( 't', mktime( 0, 0, 0, $month, 1, $year ) );
    $month_label    = wp_date( 'M Y', mktime( 0, 0, 0, $month, 1, $year ) );
    ?>
    <div class="ovr-cal-month">
        <div class="ovr-cal-month-label"><?php echo esc_html( $month_label ); ?></div>

        <div class="ovr-cal-dow-row">
            <?php foreach ( [ 'S', 'M', 'T', 'W', 'T', 'F', 'S' ] as $dow ) : ?>
                <div class="ovr-cal-dow"><?php echo esc_html( $dow ); ?></div>
            <?php endforeach; ?>
        </div>

        <div class="ovr-cal-grid">
            <?php
            // Leading empties.
            for ( $i = 0; $i < $first_dow; $i++ ) :
                echo '<div class="ovr-cal-day ovr-cal-empty" aria-hidden="true"></div>';
            endfor;

            // Days.
            for ( $d = 1; $d <= $days_in_month; $d++ ) :
                $date_str   = sprintf( '%04d-%02d-%02d', $year, $month, $d );
                $is_past    = $date_str < $today;
                $is_blocked = ! empty( $blocked_dates[ $date_str ] );
                $is_today   = $date_str === $today;

                $cls = 'ovr-cal-day';
                if ( $is_past )    { $cls .= ' is-past'; }
                if ( $is_blocked ) { $cls .= ' is-blocked'; }
                if ( ! $is_past && ! $is_blocked ) { $cls .= ' is-available'; }
                if ( $is_today )   { $cls .= ' is-today'; }
                ?>
                <div class="<?php echo esc_attr( $cls ); ?>"
                     data-date="<?php echo esc_attr( $date_str ); ?>"
                     <?php if ( $is_blocked ) echo 'aria-label="' . esc_attr__( 'Unavailable', 'ovr-core' ) . '"'; ?>>
                    <?php echo esc_html( (string) $d ); ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php
};
?>
<section class="ovr-detail-section ovr-availability" data-purpose="availability">
    <div class="ovr-detail-card">
        <h2 class="ovr-detail-heading">
            <?php esc_html_e( 'Availability', 'ovr-core' ); ?>
            <span class="ovr-detail-legend">
                <span><i class="ovr-legend-avail"></i><?php esc_html_e( 'Available', 'ovr-core' ); ?></span>
                <span><i class="ovr-legend-booked"></i><?php esc_html_e( 'Booked', 'ovr-core' ); ?></span>
            </span>
        </h2>
        <p class="ovr-body-md" style="color:var(--ovr-on-surface-variant);margin:-8px 0 20px">
            <?php
            /* translators: %d: minimum nights */
            printf( esc_html( _n( 'Minimum stay: %d night. Tap dates to start an inquiry.', 'Minimum stay: %d nights. Tap dates to start an inquiry.', $min_stay, 'ovr-core' ) ), $min_stay );
            ?>
        </p>

        <div class="ovr-cal-months" data-ovr-calendar data-property-id="<?php echo esc_attr( $post_id ); ?>">
            <?php
            $current_year  = (int) wp_date( 'Y' );
            $current_month = (int) wp_date( 'n' );

            for ( $i = 0; $i < $months_ahead; $i++ ) {
                $m = $current_month + $i;
                $y = $current_year;
                while ( $m > 12 ) { $m -= 12; $y++; }
                $render_month( $y, $m );
            }
            ?>
        </div>
    </div>
</section>

<style>
    .ovr-cal-months {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 20px 16px;
    }
    .ovr-cal-month {
        border: 1px solid var(--ovr-border-gray);
        border-radius: var(--ovr-radius-md);
        padding: 12px;
    }
    .ovr-cal-month-label {
        text-align: center;
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--ovr-primary);
        margin-bottom: 10px;
    }
    .ovr-cal-dow-row,
    .ovr-cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
    }
    .ovr-cal-dow {
        text-align: center;
        font-size: 11px;
        font-weight: 600;
        color: var(--ovr-on-surface-variant);
        padding-bottom: 4px;
    }
    .ovr-cal-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        border-radius: var(--ovr-radius-sm);
        color: var(--ovr-on-surface);
    }
    .ovr-cal-empty { background: transparent; }
    .ovr-cal-day.is-available {
        background: var(--ovr-success-container);
        color: var(--ovr-on-success-container);
    }
    .ovr-cal-day.is-past {
        color: rgba(27, 27, 32, 0.28);
        background: transparent;
    }
    .ovr-cal-day.is-blocked {
        background: var(--ovr-error-container);
        color: var(--ovr-on-error-container);
        text-decoration: line-through;
        text-decoration-color: rgba(147, 0, 10, 0.5);
    }
    .ovr-cal-day.is-today {
        outline: 2px solid var(--ovr-primary);
        font-weight: 700;
    }
</style>
