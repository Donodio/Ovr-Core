<?php
/**
 * Testimonials Carousel.
 *
 * Lightweight horizontal-scroll testimonials section. JS-free fallback —
 * uses CSS scroll-snap. Optional JS arrows can hook into [data-ovr-testimonial-track].
 *
 * @package OVR
 *
 * @var array $testimonials Optional. Array of testimonials with keys: name, role, quote, rating, avatar.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$testimonials = $testimonials ?? [
    [
        'name'   => __( 'Sarah Mitchell', 'ovr-core' ),
        'role'   => __( 'Property Owner', 'ovr-core' ),
        'quote'  => __( 'Listing my villa here was effortless. The platform attracted exactly the calibre of guests I was hoping for.', 'ovr-core' ),
        'rating' => 5,
        'avatar' => '',
    ],
    [
        'name'   => __( 'David Chen', 'ovr-core' ),
        'role'   => __( 'Frequent Traveler', 'ovr-core' ),
        'quote'  => __( 'Every village I have stayed at through this site has been more charming than the last. The curation is incredible.', 'ovr-core' ),
        'rating' => 5,
        'avatar' => '',
    ],
    [
        'name'   => __( 'Maria Rodriguez', 'ovr-core' ),
        'role'   => __( 'Property Manager', 'ovr-core' ),
        'quote'  => __( 'The dashboard makes managing 12 properties feel like managing one. Inquiries, calendars, payouts — all in one place.', 'ovr-core' ),
        'rating' => 5,
        'avatar' => '',
    ],
];
?>
<section class="ovr-section" style="background:var(--ovr-surface-container-low)">
    <div class="ovr-container">
        <div style="text-align:center;max-width:600px;margin:0 auto 48px">
            <p class="ovr-label-caps" style="color:var(--ovr-secondary);margin-bottom:8px">
                <?php esc_html_e( 'TESTIMONIALS', 'ovr-core' ); ?>
            </p>
            <h2 class="ovr-h2"><?php esc_html_e( 'Trusted by Travelers & Hosts Alike', 'ovr-core' ); ?></h2>
        </div>

        <div class="ovr-testimonial-track"
             data-ovr-testimonial-track
             style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:var(--ovr-gutter)">

            <?php foreach ( $testimonials as $t ) :
                $name   = $t['name']   ?? '';
                $role   = $t['role']   ?? '';
                $quote  = $t['quote']  ?? '';
                $rating = absint( $t['rating'] ?? 5 );
                $avatar = $t['avatar'] ?? '';
            ?>
                <article class="ovr-card" style="padding:32px;display:flex;flex-direction:column;gap:16px">
                    <!-- Stars -->
                    <div style="display:flex;gap:2px;color:var(--ovr-tertiary-container)" aria-label="<?php
                        printf( esc_attr__( '%d out of 5 stars', 'ovr-core' ), $rating ); ?>">
                        <?php for ( $i = 0; $i < 5; $i++ ) : ?>
                            <span class="material-symbols-outlined fill" style="font-size:18px;<?php echo $i >= $rating ? 'color:var(--ovr-outline-variant)' : ''; ?>">
                                star
                            </span>
                        <?php endfor; ?>
                    </div>

                    <!-- Quote -->
                    <blockquote style="margin:0;font-size:16px;line-height:1.6;color:var(--ovr-on-surface);font-style:normal">
                        &ldquo;<?php echo esc_html( $quote ); ?>&rdquo;
                    </blockquote>

                    <!-- Author -->
                    <div style="display:flex;align-items:center;gap:12px;margin-top:8px;padding-top:16px;border-top:1px solid var(--ovr-outline-variant)">
                        <?php if ( $avatar ) : ?>
                            <img src="<?php echo esc_url( $avatar ); ?>"
                                 alt="<?php echo esc_attr( $name ); ?>"
                                 width="44" height="44"
                                 style="border-radius:50%;object-fit:cover">
                        <?php else : ?>
                            <div style="width:44px;height:44px;border-radius:50%;background:var(--ovr-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0">
                                <?php echo esc_html( mb_substr( $name, 0, 1 ) ); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight:600;color:var(--ovr-on-surface);font-size:14px">
                                <?php echo esc_html( $name ); ?>
                            </div>
                            <?php if ( $role ) : ?>
                                <div style="font-size:13px;color:var(--ovr-on-surface-variant)">
                                    <?php echo esc_html( $role ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
