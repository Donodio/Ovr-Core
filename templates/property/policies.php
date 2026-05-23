<?php
/**
 * Policies & Payment section.
 *
 * Owner-direct rental terms (DESIGN.md §10). Driven entirely by real data:
 * always shows the facts we have (minimum stay, pets, booking type) and adds
 * cancellation / house-rules / payment / check-in details only when the owner
 * has filled the matching meta fields — nothing is fabricated.
 *
 * @package OVR
 *
 * @var int   $post_id  Required. Property post ID.
 * @var array $meta      PropertyMeta::get_all() output.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$post_id = (int) ( $post_id ?? get_the_ID() );
$meta    = is_array( $meta ?? null ) ? $meta : [];

$min_stay     = max( 1, (int) ( $meta['min_stay'] ?? 1 ) );
$pets         = ! empty( $meta['pets_allowed'] );
$booking_mode = (string) ( $meta['booking_mode'] ?? 'inquiry' );

// Optional owner-supplied terms (rendered only when present).
$cancellation = trim( (string) get_post_meta( $post_id, '_ovr_cancellation_policy', true ) );
$house_rules  = trim( (string) get_post_meta( $post_id, '_ovr_house_rules', true ) );
$payment      = trim( (string) get_post_meta( $post_id, '_ovr_payment_methods', true ) );
$checkin      = trim( (string) get_post_meta( $post_id, '_ovr_checkin_time', true ) );
$checkout     = trim( (string) get_post_meta( $post_id, '_ovr_checkout_time', true ) );

// Assemble the policy items in display order.
$items = [];

if ( $checkin || $checkout ) {
    $times = trim( implode( ' – ', array_filter( [ $checkin, $checkout ] ) ) );
    $items[] = [
        'icon'  => 'schedule',
        'title' => __( 'Check-in / Check-out', 'ovr-core' ),
        'body'  => $times,
    ];
}

$items[] = [
    'icon'  => 'event_available',
    'title' => __( 'Minimum Stay', 'ovr-core' ),
    /* translators: %d: minimum nights */
    'body'  => sprintf( _n( '%d night minimum.', '%d nights minimum.', $min_stay, 'ovr-core' ), $min_stay ),
];

$items[] = [
    'icon'  => $pets ? 'pets' : 'block',
    'title' => __( 'Pets', 'ovr-core' ),
    'body'  => $pets ? __( 'Pets are welcome at this property.', 'ovr-core' ) : __( 'Sorry, pets are not permitted.', 'ovr-core' ),
];

$items[] = [
    'icon'  => 'how_to_reg',
    'title' => __( 'How to Book', 'ovr-core' ),
    'body'  => 'direct' === $booking_mode
        ? __( 'Instant booking with secure online payment.', 'ovr-core' )
        : __( 'Send an inquiry to the owner to confirm dates and terms.', 'ovr-core' ),
];

if ( $cancellation ) {
    $items[] = [ 'icon' => 'event_busy', 'title' => __( 'Cancellation Policy', 'ovr-core' ), 'body' => $cancellation ];
}
if ( $payment ) {
    $items[] = [ 'icon' => 'credit_card', 'title' => __( 'Payment Methods', 'ovr-core' ), 'body' => $payment ];
}
if ( $house_rules ) {
    $items[] = [ 'icon' => 'gavel', 'title' => __( 'House Rules', 'ovr-core' ), 'body' => $house_rules ];
}
?>
<section class="ovr-detail-section" data-purpose="policies">
    <div class="ovr-detail-card">
        <h2 class="ovr-detail-heading"><?php esc_html_e( 'Policies & Payment', 'ovr-core' ); ?></h2>
        <div class="ovr-policies-grid">
            <?php foreach ( $items as $item ) : ?>
                <div class="ovr-policy">
                    <span class="material-symbols-outlined"><?php echo esc_html( $item['icon'] ); ?></span>
                    <div>
                        <h4><?php echo esc_html( $item['title'] ); ?></h4>
                        <p><?php echo nl2br( esc_html( $item['body'] ) ); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
