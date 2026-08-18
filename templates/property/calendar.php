<?php
/**
 * Property Availability Calendar.
 *
 * A 15-month availability window shown as a compact sliding window: five months
 * at a time on desktop, three on small screens, navigated with prev/next arrows
 * (disabled at the ends). Available days are green, booked days red. Pulls
 * blocked dates from ovr_availability. Keeps the data-* hooks and .ovr-cal-day
 * markup used by the range-picker in assets/js/ovr-property.js — hidden months
 * stay in the DOM (display:none) so date selection still works across the whole
 * 15-month range without a page reload.
 *
 * @package OVR
 *
 * @var int $post_id      Required. Property post ID.
 * @var int $months_ahead Optional. How many months the range spans. Default 15.
 * @var int $min_stay     Optional. Min nights to display. Default 1.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$post_id      = $post_id ?? 0;
$months_ahead = max( 1, min( 15, absint( $months_ahead ?? 15 ) ) );
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
            <?php esc_html_e( 'Select a range of available dates to check your stay.', 'ovr-core' ); ?>
        </p>

        <div class="ovr-cal-rail">
            <button type="button"
                    class="ovr-cal-nav"
                    data-cal-prev
                    aria-label="<?php esc_attr_e( 'Previous months', 'ovr-core' ); ?>"
                    aria-controls="ovr-cal-months"
                    disabled>
                <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
            </button>
            <div class="ovr-cal-months" id="ovr-cal-months" data-ovr-calendar data-property-id="<?php echo esc_attr( $post_id ); ?>">
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
            <button type="button"
                    class="ovr-cal-nav"
                    data-cal-next
                    aria-label="<?php esc_attr_e( 'Next months', 'ovr-core' ); ?>"
                    aria-controls="ovr-cal-months"
                    <?php echo $months_ahead <= 5 ? 'disabled' : ''; ?>>
                <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            </button>
        </div>
    </div>
</section>

<style>
    .ovr-cal-rail {
        display: flex;
        align-items: stretch;
        gap: 10px;
        position: relative;
    }
    .ovr-cal-nav {
        flex: 0 0 auto;
        align-self: center;
        width: 38px;
        height: 38px;
        border-radius: 9999px;
        border: 1px solid var(--ovr-outline-variant);
        background: var(--ovr-surface, #fff);
        color: var(--ovr-primary);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0,0,0,.08);
        transition: background .15s, border-color .15s;
    }
    .ovr-cal-nav:hover:not(:disabled) { background: var(--ovr-surface-container); border-color: var(--ovr-primary); }
    .ovr-cal-nav:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(0,108,74,.35); }
    .ovr-cal-nav:disabled { opacity: .35; cursor: default; }
    .ovr-cal-nav .material-symbols-outlined { font-size: 22px; }
    .ovr-cal-months {
        flex: 1 1 100%;
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 16px;
        padding: 4px 2px 10px;
        box-sizing: border-box;
    }
    .ovr-cal-month {
        min-width: 0;
        max-width: 100%;
        border: 1px solid var(--ovr-border-gray);
        border-radius: var(--ovr-radius-md);
        padding: 10px;
        box-sizing: border-box;
    }
    /* Months outside the current window are hidden from layout but stay in the
       DOM so the range picker can still read every date across 15 months. */
    .ovr-cal-month.is-hidden { display: none; }
    .ovr-cal-month-label {
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--ovr-primary);
        margin-bottom: 8px;
    }
    .ovr-cal-dow-row,
    .ovr-cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
    }
    .ovr-cal-dow {
        text-align: center;
        font-size: 10px;
        font-weight: 600;
        color: var(--ovr-on-surface-variant);
        padding-bottom: 2px;
    }
    .ovr-cal-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
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
    /* Small screens: three months per window, nav arrows stay usable. */
    @media (max-width: 640px) {
        .ovr-cal-months { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .ovr-cal-month { padding: 8px; }
    }
    @media (max-width: 420px) {
        .ovr-cal-rail { gap: 4px; }
        .ovr-cal-nav { width: 32px; height: 32px; }
        .ovr-cal-months { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
    }
</style>
<script>
(function(){
    var container = document.querySelector('[data-ovr-calendar]');
    if (!container) { return; }
    var months = Array.prototype.slice.call(container.querySelectorAll('.ovr-cal-month'));
    var total  = months.length;
    if (total === 0) { return; }

    var prev = document.querySelector('[data-cal-prev]');
    var next = document.querySelector('[data-cal-next]');

    // Visible window size: 5 months desktop, 3 on small screens.
    function visibleCount(){
        if (window.matchMedia && window.matchMedia('(max-width: 640px)').matches) { return 3; }
        return 5;
    }

    var start = 0;
    var lastStart = 0;

    function render(){
        var vis = visibleCount();
        lastStart = Math.max(0, total - vis);
        start = Math.max(0, Math.min(start, lastStart));
        months.forEach(function(m, i){
            m.classList.toggle('is-hidden', i < start || i >= start + vis);
        });
        if (prev) { prev.disabled = (start <= 0); }
        if (next) { next.disabled = (start >= lastStart); }
    }

    function step(dir){
        start = Math.max(0, Math.min(lastStart, start + dir));
        render();
        var firstVisible = months[start];
        if (firstVisible && firstVisible.scrollIntoView) {
            firstVisible.scrollIntoView({ block: 'nearest', inline: 'start' });
        }
    }

    if (prev) { prev.addEventListener('click', function(){ step(-1); }); }
    if (next) { next.addEventListener('click', function(){ step(1); }); }
    if (window.addEventListener) { window.addEventListener('resize', render); }

    render();
})();
</script>
