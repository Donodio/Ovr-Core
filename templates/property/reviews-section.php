<?php
/**
 * Reviews section for the single property page.
 *
 * @package OVR
 *
 * @var int $post_id
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Property\Reviews;

$post_id    = (int) ( $post_id ?? 0 );
if ( ! $post_id ) return;

// Only 4-star-and-above reviews are surfaced publicly (matching the site-wide
// reputation rule). Reads the same min_display_rating setting the Testimonials
// carousel uses, defaulting to 4.
$ovr_rt_settings = get_option( 'ovr_settings', [] );
$min_rating      = isset( $ovr_rt_settings['min_display_rating'] ) ? max( 1, min( 5, (int) $ovr_rt_settings['min_display_rating'] ) ) : 4;

$reviews    = Reviews::get_for_property( $post_id, 20, $min_rating );
$rating_avg = (float) get_post_meta( $post_id, '_ovr_rating_avg',   true );
$rating_n   = (int)   get_post_meta( $post_id, '_ovr_rating_count', true );
?>
<section id="ovr-reviews" style="margin-top:48px;padding-top:32px;border-top:1px solid var(--ovr-outline-variant)">

    <header style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:16px;margin-bottom:24px">
        <div>
            <h2 class="ovr-h3" style="margin:0 0 4px;display:flex;align-items:center;gap:8px">
                <span class="material-symbols-outlined fill" style="color:#cca72f">star</span>
                <?php if ( $rating_n > 0 ) : ?>
                    <?php echo esc_html( number_format( $rating_avg, 1 ) ); ?>
                    <span style="font-weight:400;color:var(--ovr-on-surface-variant);font-size:18px">·</span>
                    <span style="font-weight:400;color:var(--ovr-on-surface-variant);font-size:18px">
                        <?php
                        /* translators: %d: testimonial count */
                        printf( esc_html( _n( '%d testimonial', '%d testimonials', $rating_n, 'ovr-core' ) ), $rating_n );
                        ?>
                    </span>
                <?php else : ?>
                    <?php esc_html_e( 'No testimonials yet', 'ovr-core' ); ?>
                <?php endif; ?>
            </h2>
            <p style="margin:0;font-size:14px;color:var(--ovr-on-surface-variant)">
                <?php esc_html_e( 'If you rented and had a great experience, please provide a testimonial.', 'ovr-core' ); ?>
            </p>
        </div>

        <button type="button" class="ovr-btn ovr-btn-primary ovr-btn-pill" data-ovr-review-toggle>
            <span class="material-symbols-outlined" style="font-size:18px">rate_review</span>
            <?php esc_html_e( 'Write a Testimonial', 'ovr-core' ); ?>
        </button>
    </header>

    <!-- Submission form (hidden by default) -->
    <form id="ovr-review-form"
          data-ovr-review-form
          data-property-id="<?php echo esc_attr( (string) $post_id ); ?>"
          data-nonce="<?php echo esc_attr( wp_create_nonce( 'ovr_public_nonce' ) ); ?>"
          hidden
          style="margin-bottom:32px;padding:24px;background:var(--ovr-surface-container-low);border:1px solid var(--ovr-outline-variant);border-radius:var(--ovr-radius-lg)">

        <h3 style="margin:0 0 16px;font-size:18px;font-weight:600"><?php esc_html_e( 'Your review', 'ovr-core' ); ?></h3>

        <div style="margin-bottom:16px">
            <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px">
                <?php esc_html_e( 'Rating', 'ovr-core' ); ?>
            </label>
            <div data-ovr-rating-input style="display:flex;gap:4px">
                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                    <button type="button"
                            data-rating="<?php echo esc_attr( (string) $i ); ?>"
                            style="background:none;border:none;cursor:pointer;padding:4px"
                            aria-label="<?php
                            /* translators: %d: rating value 1-5 */
                            echo esc_attr( sprintf( __( '%d star', 'ovr-core' ), $i ) );
                            ?>">
                        <span class="material-symbols-outlined" style="font-size:32px;color:var(--ovr-outline)">star</span>
                    </button>
                <?php endfor; ?>
                <input type="hidden" name="rating" value="0" required>
            </div>
        </div>

        <?php if ( ! is_user_logged_in() ) : ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:16px">
                <?php // Labels carried visible text but no `for`, and did not wrap the
                      // input — so both controls were unnamed to assistive tech
                      // (axe `label`). Ids are suffixed with the property id because
                      // this section can appear alongside the inquiry form. ?>
                <div>
                    <label for="ovr-rev-name-<?php echo esc_attr( (string) $post_id ); ?>" style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                        <?php esc_html_e( 'Your Name', 'ovr-core' ); ?>
                    </label>
                    <input type="text" id="ovr-rev-name-<?php echo esc_attr( (string) $post_id ); ?>" name="guest_name" class="ovr-form-input" required>
                </div>
                <div>
                    <label for="ovr-rev-email-<?php echo esc_attr( (string) $post_id ); ?>" style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                        <?php esc_html_e( 'Email (kept private)', 'ovr-core' ); ?>
                    </label>
                    <input type="email" id="ovr-rev-email-<?php echo esc_attr( (string) $post_id ); ?>" name="guest_email" class="ovr-form-input" required>
                </div>
            </div>
        <?php else :
            $u = wp_get_current_user();
        ?>
            <input type="hidden" name="guest_name"  value="<?php echo esc_attr( $u->display_name ); ?>">
            <input type="hidden" name="guest_email" value="<?php echo esc_attr( $u->user_email ); ?>">
        <?php endif; ?>

        <div style="margin-bottom:16px">
            <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                <?php esc_html_e( 'Title (optional)', 'ovr-core' ); ?>
            </label>
            <input type="text" name="title" class="ovr-form-input" placeholder="<?php esc_attr_e( 'A great stay…', 'ovr-core' ); ?>">
        </div>

        <div style="margin-bottom:16px">
            <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                <?php esc_html_e( 'Your review', 'ovr-core' ); ?>
            </label>
            <textarea name="body" rows="4" class="ovr-form-input" required></textarea>
        </div>

        <button type="submit" class="ovr-btn ovr-btn-primary ovr-btn-pill">
            <?php esc_html_e( 'Submit Review', 'ovr-core' ); ?>
        </button>

        <div data-ovr-review-result style="margin-top:12px;font-size:13px"></div>
    </form>

    <!-- Reviews list -->
    <?php if ( ! empty( $reviews ) ) : ?>
        <ul style="list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px">
            <?php foreach ( $reviews as $r ) : ?>
                <li style="padding:20px;background:var(--ovr-surface-container-low);border-radius:var(--ovr-radius-lg)">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                        <div style="width:40px;height:40px;border-radius:50%;background:var(--ovr-primary);color:var(--ovr-on-primary);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:15px;flex-shrink:0">
                            <?php echo esc_html( strtoupper( substr( $r['guest_name'], 0, 1 ) ) ); ?>
                        </div>
                        <div style="min-width:0">
                            <div style="font-weight:600;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?php echo esc_html( $r['guest_name'] ); ?>
                            </div>
                            <div style="font-size:12px;color:var(--ovr-on-surface-variant)">
                                <?php echo esc_html( mysql2date( get_option( 'date_format' ), $r['created_at'] ) ); ?>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:1px;margin-bottom:8px;color:#cca72f">
                        <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                            <span class="material-symbols-outlined<?php echo $i <= (int) $r['rating'] ? ' fill' : ''; ?>"
                                  style="font-size:16px;color:<?php echo $i <= (int) $r['rating'] ? '#cca72f' : 'var(--ovr-outline-variant)'; ?>">star</span>
                        <?php endfor; ?>
                    </div>

                    <?php if ( ! empty( $r['title'] ) ) : ?>
                        <h4 style="margin:0 0 4px;font-size:15px;font-weight:600">
                            <?php echo esc_html( $r['title'] ); ?>
                        </h4>
                    <?php endif; ?>

                    <p style="margin:0;font-size:14px;color:var(--ovr-on-surface);line-height:1.5;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;"><?php echo esc_html( $r['body'] ); ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php elseif ( $rating_n === 0 ) : ?>
        <div style="padding:32px;text-align:center;background:var(--ovr-surface-container-low);border-radius:var(--ovr-radius-lg);color:var(--ovr-on-surface-variant);font-size:14px">
            <?php esc_html_e( 'Be the first to share your experience.', 'ovr-core' ); ?>
        </div>
    <?php endif; ?>
</section>

<script>
(function () {
    var toggle = document.querySelector('[data-ovr-review-toggle]');
    var form   = document.querySelector('[data-ovr-review-form]');
    if (!toggle || !form) return;

    toggle.addEventListener('click', function () {
        form.hidden = !form.hidden;
        if (!form.hidden) form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // Star picker
    var starsBox = form.querySelector('[data-ovr-rating-input]');
    var hidden   = starsBox.querySelector('input[name="rating"]');
    var stars    = starsBox.querySelectorAll('button');
    function paint(value) {
        stars.forEach(function (b, i) {
            var on = (i + 1) <= value;
            var icon = b.querySelector('.material-symbols-outlined');
            icon.classList.toggle('fill', on);
            icon.style.color = on ? '#cca72f' : 'var(--ovr-outline)';
        });
    }
    stars.forEach(function (b) {
        b.addEventListener('mouseover', function () { paint(parseInt(b.dataset.rating, 10)); });
        b.addEventListener('click',     function () {
            hidden.value = b.dataset.rating;
            paint(parseInt(b.dataset.rating, 10));
        });
    });
    starsBox.addEventListener('mouseleave', function () {
        paint(parseInt(hidden.value, 10) || 0);
    });

    // Submit via AJAX (same proven path as the inquiry form: admin-ajax + the
    // public nonce printed on every page as ovrData.nonce).
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var output = form.querySelector('[data-ovr-review-result]');
        var btn    = form.querySelector('button[type="submit"]');
        var orig   = btn.innerHTML;

        if (parseInt(hidden.value, 10) < 1) {
            output.innerHTML = '<span style="color:#ba1a1a">Please pick a rating.</span>';
            return;
        }

        btn.disabled  = true;
        btn.innerHTML = 'Sending…';
        output.textContent = '';

        var fd = new FormData(form);
        fd.append('property_id', form.dataset.propertyId);
        fd.append('action', 'ovr_submit_review');
        // Prefer the nonce stamped on the form; fall back to the global (some
        // themes strip the localized script, which previously 403'd every submit).
        fd.append('nonce', form.dataset.nonce || (window.ovrData && window.ovrData.nonce) || '');

        fetch((window.ovrData && window.ovrData.ajaxUrl) || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success) {
                output.innerHTML = '<span style="color:#00714e">' + (res.data.message || 'Thanks!') + '</span>';
                form.reset();
                paint(0);
                form.hidden = true;
            } else {
                output.innerHTML = '<span style="color:#ba1a1a">' + ((res && res.data && res.data.message) || 'Failed.') + '</span>';
            }
        })
        .catch(function () {
            output.innerHTML = '<span style="color:#ba1a1a">Network error.</span>';
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = orig;
        });
    });
})();
</script>
