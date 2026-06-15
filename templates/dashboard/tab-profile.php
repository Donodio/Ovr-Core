<?php
/**
 * Profile tab ("My Information") — personal info form + photo upload +
 * host profile preview. Scoped under `.ovr-ld`; the dashboard shell supplies
 * the surrounding nav, so the mockup's "Account" sub-sidebar is intentionally
 * omitted (its destinations live in the main sidebar). No payout/financial
 * fields — this site stores no landlord financial information.
 *
 * @package OVR
 * @var \WP_User $user
 * @var string   $phone
 * @var string   $address
 * @var bool     $saved
 * @var string   $base_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$phone   = $phone ?? '';
$address = $address ?? '';
$bio     = (string) $user->description;
$saved   = ! empty( $saved );

$avatar       = get_avatar_url( $user->ID, [ 'size' => 256 ] );
$member_year  = $user->user_registered ? gmdate( 'Y', strtotime( $user->user_registered ) ) : '';
$avatar_ajax  = admin_url( 'admin-ajax.php' );
$avatar_nonce = wp_create_nonce( 'ovr_avatar_action' );
$bio_max      = 500;
?>

<?php if ( $saved ) : ?>
    <div class="ld-pf-alert">
        <span class="material-symbols-outlined">check_circle</span>
        <span><?php esc_html_e( 'Your information has been saved.', 'ovr-core' ); ?></span>
    </div>
<?php endif; ?>

<div class="ld-pf">

    <!-- Form column -->
    <div class="ld-pf-main">

        <!-- Personal Information -->
        <section class="ld-pf-card ld-pf-card--accent">
            <div class="ld-pf-head">
                <h1 class="ld-pf-h1"><?php esc_html_e( 'Personal Information', 'ovr-core' ); ?></h1>
                <p class="ld-pf-lede"><?php esc_html_e( 'Manage your basic profile details and how others see you on the platform.', 'ovr-core' ); ?></p>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ld-pf-form">
                <input type="hidden" name="action" value="ovr_update_profile">
                <?php wp_nonce_field( 'ovr_profile_action', 'ovr_profile_nonce' ); ?>

                <!-- Avatar: simple direct upload -->
                <div class="ld-pf-avatar-row" data-ovr-avatar
                     data-ajax="<?php echo esc_url( $avatar_ajax ); ?>"
                     data-nonce="<?php echo esc_attr( $avatar_nonce ); ?>">
                    <div class="ld-pf-avatar-wrap">
                        <img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>" class="ld-pf-avatar" data-ovr-avatar-img>
                        <button type="button" class="ld-pf-avatar-cam" data-ovr-avatar-trigger title="<?php esc_attr_e( 'Upload a new photo', 'ovr-core' ); ?>">
                            <span class="material-symbols-outlined">photo_camera</span>
                        </button>
                    </div>
                    <div>
                        <button type="button" class="ld-pf-uploadlink" data-ovr-avatar-trigger><?php esc_html_e( 'Upload New Photo', 'ovr-core' ); ?></button>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-ovr-avatar-input hidden>
                        <p class="ld-pf-hint" data-ovr-avatar-status><?php esc_html_e( 'JPG, PNG, WebP or GIF, up to 5MB. You can crop it before saving.', 'ovr-core' ); ?></p>
                    </div>
                </div>

                <!-- Crop modal: Choose File → (optional) Crop → Save. Fully local,
                     dependency-free; the cropped square uploads to this site's
                     media library via AJAX. Hoisted to <body> by JS so it overlays
                     correctly regardless of any transformed dashboard ancestor. -->
                <div class="ld-pf-crop" id="ld-pf-crop" hidden aria-hidden="true">
                    <div class="ld-pf-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="ld-pf-crop-title">
                        <h3 class="ld-pf-crop-title" id="ld-pf-crop-title"><?php esc_html_e( 'Adjust your photo', 'ovr-core' ); ?></h3>
                        <div class="ld-pf-crop-stage" id="ld-pf-crop-stage">
                            <img id="ld-pf-crop-img" alt="">
                        </div>
                        <label class="ld-pf-crop-zoom"><?php esc_html_e( 'Zoom', 'ovr-core' ); ?>
                            <input type="range" id="ld-pf-crop-zoom" min="1" max="3" step="0.01" value="1"
                                   aria-label="<?php esc_attr_e( 'Zoom', 'ovr-core' ); ?>">
                        </label>
                        <p class="ld-pf-crop-hint"><?php esc_html_e( 'Drag to reposition. Cropping is optional — Save uses what you see.', 'ovr-core' ); ?></p>
                        <div class="ld-pf-crop-actions">
                            <button type="button" class="ld-pf-crop-btn ld-pf-crop-btn--ghost" id="ld-pf-crop-cancel"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></button>
                            <button type="button" class="ld-pf-crop-btn ld-pf-crop-btn--primary" id="ld-pf-crop-save"><?php esc_html_e( 'Save Photo', 'ovr-core' ); ?></button>
                        </div>
                    </div>
                </div>

                <div class="ld-pf-grid">
                    <div class="ld-pf-field ld-pf-field--full">
                        <label class="ld-pf-label" for="ld-pf-name"><?php esc_html_e( 'Full Name', 'ovr-core' ); ?></label>
                        <input class="ld-pf-input" id="ld-pf-name" type="text" name="full_name" value="<?php echo esc_attr( $user->display_name ); ?>">
                    </div>

                    <div class="ld-pf-field">
                        <label class="ld-pf-label" for="ld-pf-email"><?php esc_html_e( 'Email Address', 'ovr-core' ); ?></label>
                        <input class="ld-pf-input" id="ld-pf-email" type="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" required>
                    </div>

                    <div class="ld-pf-field">
                        <label class="ld-pf-label" for="ld-pf-phone"><?php esc_html_e( 'Phone Number', 'ovr-core' ); ?></label>
                        <input class="ld-pf-input" id="ld-pf-phone" type="tel" name="phone" value="<?php echo esc_attr( $phone ); ?>">
                    </div>

                    <div class="ld-pf-field ld-pf-field--full">
                        <label class="ld-pf-label" for="ld-pf-address"><?php esc_html_e( 'Primary Address', 'ovr-core' ); ?></label>
                        <input class="ld-pf-input" id="ld-pf-address" type="text" name="address" value="<?php echo esc_attr( $address ); ?>">
                    </div>

                    <div class="ld-pf-field ld-pf-field--full">
                        <label class="ld-pf-label" for="ld-pf-bio"><?php esc_html_e( 'About Me / Bio', 'ovr-core' ); ?></label>
                        <textarea class="ld-pf-input ld-pf-textarea" id="ld-pf-bio" name="bio" rows="4" maxlength="<?php echo (int) $bio_max; ?>"><?php echo esc_textarea( $bio ); ?></textarea>
                        <p class="ld-pf-counter"><span id="ld-pf-bio-count"><?php echo (int) mb_strlen( $bio ); ?></span> / <?php echo (int) $bio_max; ?> <?php esc_html_e( 'characters', 'ovr-core' ); ?></p>
                    </div>
                </div>

                <div class="ld-pf-actions">
                    <button type="submit" class="ld-pf-btn ld-pf-btn--primary"><?php esc_html_e( 'Save Changes', 'ovr-core' ); ?></button>
                </div>
            </form>
        </section>
    </div>

    <!-- Host profile preview -->
    <aside class="ld-pf-side">
        <div class="ld-pf-preview">
            <div class="ld-pf-preview-head">
                <h3><?php esc_html_e( 'Host Profile Preview', 'ovr-core' ); ?></h3>
                <span class="material-symbols-outlined">visibility</span>
            </div>
            <div class="ld-pf-preview-body">
                <div class="ld-pf-preview-id">
                    <img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>" class="ld-pf-preview-av">
                    <h2 class="ld-pf-preview-name"><?php echo esc_html( $user->display_name ); ?></h2>
                    <span class="ld-pf-preview-badge">
                        <span class="material-symbols-outlined fill">verified</span><?php esc_html_e( 'Verified Host', 'ovr-core' ); ?>
                    </span>
                </div>

                <ul class="ld-pf-preview-meta">
                    <?php if ( $address ) : ?>
                        <li><span class="material-symbols-outlined">location_on</span><span><?php echo esc_html( $address ); ?></span></li>
                    <?php endif; ?>
                    <?php if ( $member_year ) : ?>
                        <li><span class="material-symbols-outlined">calendar_month</span><span><?php printf( esc_html__( 'Member since %s', 'ovr-core' ), esc_html( $member_year ) ); ?></span></li>
                    <?php endif; ?>
                    <li><span class="material-symbols-outlined">mail</span><span><?php echo esc_html( $user->user_email ); ?></span></li>
                </ul>

                <?php if ( $bio ) : ?>
                    <hr class="ld-pf-preview-rule">
                    <div>
                        <h4 class="ld-pf-preview-abouth"><?php printf( esc_html__( 'About %s', 'ovr-core' ), esc_html( $user->first_name ?: $user->display_name ) ); ?></h4>
                        <p class="ld-pf-preview-about"><?php echo esc_html( $bio ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<style>
    .ovr-ld .ld-pf-alert{display:flex;align-items:center;gap:10px;background:rgba(0,108,74,.1);color:var(--sec);border:1px solid rgba(0,108,74,.3);border-radius:12px;padding:14px 18px;font-size:14px;font-weight:600}
    .ovr-ld .ld-pf-alert .material-symbols-outlined{font-size:20px}

    .ovr-ld .ld-pf{display:grid;grid-template-columns:2fr 1fr;gap:32px;align-items:start}
    .ovr-ld .ld-pf-main{display:flex;flex-direction:column;gap:32px;min-width:0}
    .ovr-ld .ld-pf-card{background:var(--surf);border:1px solid var(--ov);border-radius:16px;padding:28px;box-shadow:0 4px 24px rgba(0,0,0,.04);position:relative;overflow:hidden}
    .ovr-ld .ld-pf-card--accent::before{content:"";position:absolute;top:0;right:0;width:128px;height:128px;background:rgba(0,102,102,.05);border-radius:0 0 0 100%;pointer-events:none}

    .ovr-ld .ld-pf-head{border-bottom:1px solid var(--ov);padding-bottom:22px;margin-bottom:26px;position:relative;z-index:1}
    .ovr-ld .ld-pf-h1{font-size:28px;font-weight:700;letter-spacing:-.01em;color:var(--on);margin:0}
    .ovr-ld .ld-pf-h2{font-size:22px;font-weight:600;color:var(--on);margin:0}
    .ovr-ld .ld-pf-lede{font-size:14px;color:var(--sv);margin:8px 0 0;line-height:1.6}

    .ovr-ld .ld-pf-avatar-row{display:flex;align-items:center;gap:24px;margin-bottom:28px}
    .ovr-ld .ld-pf-avatar-wrap{position:relative;flex-shrink:0}
    .ovr-ld .ld-pf-avatar{width:96px;height:96px;border-radius:50%;object-fit:cover;border:4px solid var(--surf);box-shadow:0 2px 8px rgba(0,0,0,.1)}
    .ovr-ld .ld-pf-avatar-cam{position:absolute;bottom:0;right:0;width:32px;height:32px;border:none;border-radius:50%;background:var(--pc);color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.2);text-decoration:none;cursor:pointer;transition:background .2s}
    .ovr-ld .ld-pf-avatar-cam:hover{background:var(--p)}
    .ovr-ld .ld-pf-avatar-cam .material-symbols-outlined{font-size:17px}
    .ovr-ld .ld-pf-uploadlink{display:inline-block;padding:0;background:none;border:none;cursor:pointer;font-family:inherit;font-size:12px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--pc);text-decoration:none}
    .ovr-ld .ld-pf-uploadlink:hover{color:var(--p);text-decoration:underline}
    .ovr-ld .ld-pf-hint{font-size:13px;color:var(--sv);margin:4px 0 0}

    .ovr-ld .ld-pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}
    .ovr-ld .ld-pf-field{display:flex;flex-direction:column}
    .ovr-ld .ld-pf-field--full{grid-column:1 / -1}
    .ovr-ld .ld-pf-label{font-size:12px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--on);margin-bottom:8px}
    .ovr-ld .ld-pf-input{width:100%;background:#fff;border:1px solid var(--ov);border-radius:10px;padding:12px 14px;font-family:inherit;font-size:15px;color:var(--on);outline:none;transition:border-color .15s,box-shadow .15s;box-shadow:0 1px 2px rgba(0,0,0,.03)}
    .ovr-ld .ld-pf-input:focus{border-color:var(--pc);box-shadow:0 0 0 3px rgba(0,102,102,.15)}
    .ovr-ld .ld-pf-textarea{resize:vertical;min-height:104px;line-height:1.6}
    .ovr-ld .ld-pf-counter{font-size:12px;color:var(--sv);margin:8px 0 0;text-align:right}

    .ovr-ld .ld-pf-actions{display:flex;justify-content:flex-end;margin-top:26px}
    .ovr-ld .ld-pf-btn{font-family:inherit;font-size:14px;font-weight:700;padding:13px 30px;border-radius:10px;border:1px solid transparent;cursor:pointer;transition:background .18s,box-shadow .18s}
    .ovr-ld .ld-pf-btn--primary{background:var(--pc);color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.12)}
    .ovr-ld .ld-pf-btn--primary:hover{background:var(--p);box-shadow:0 4px 12px rgba(0,76,76,.25)}

    /* Preview */
    .ovr-ld .ld-pf-preview{position:sticky;top:24px;background:var(--surf);border:1px solid var(--ov);border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.06)}
    .ovr-ld .ld-pf-preview-head{display:flex;justify-content:space-between;align-items:center;padding:16px 22px;background:var(--sclow);border-bottom:1px solid var(--ov)}
    .ovr-ld .ld-pf-preview-head h3{font-size:14px;font-weight:600;color:var(--on);margin:0}
    .ovr-ld .ld-pf-preview-head .material-symbols-outlined{font-size:18px;color:var(--sv)}
    .ovr-ld .ld-pf-preview-body{padding:24px}
    .ovr-ld .ld-pf-preview-id{display:flex;flex-direction:column;align-items:center;text-align:center;margin-bottom:22px}
    .ovr-ld .ld-pf-preview-av{width:120px;height:120px;border-radius:50%;object-fit:cover;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:14px}
    .ovr-ld .ld-pf-preview-name{font-size:22px;font-weight:700;color:var(--on);margin:0}
    .ovr-ld .ld-pf-preview-badge{display:inline-flex;align-items:center;gap:4px;color:var(--p);font-size:12px;font-weight:600;letter-spacing:.04em;margin-top:6px}
    .ovr-ld .ld-pf-preview-badge .material-symbols-outlined{font-size:16px}
    .ovr-ld .ld-pf-preview-meta{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:14px}
    .ovr-ld .ld-pf-preview-meta li{display:flex;align-items:flex-start;gap:12px;font-size:14px;color:var(--on)}
    .ovr-ld .ld-pf-preview-meta .material-symbols-outlined{font-size:20px;color:var(--sv);flex-shrink:0}
    .ovr-ld .ld-pf-preview-meta li span:last-child{overflow-wrap:anywhere}
    .ovr-ld .ld-pf-preview-rule{border:none;border-top:1px solid var(--ov);margin:22px 0}
    .ovr-ld .ld-pf-preview-abouth{font-size:14px;font-weight:600;color:var(--on);margin:0 0 8px}
    .ovr-ld .ld-pf-preview-about{font-size:14px;color:var(--sv);line-height:1.6;margin:0}

    @media (max-width:1100px){
        .ovr-ld .ld-pf{grid-template-columns:1fr;gap:28px}
        .ovr-ld .ld-pf-preview{position:static}
    }
    @media (max-width:600px){
        .ovr-ld .ld-pf-grid{grid-template-columns:1fr}
        .ovr-ld .ld-pf-card{padding:22px}
        .ovr-ld .ld-pf-h1{font-size:24px}
        .ovr-ld .ld-pf-actions .ld-pf-btn{width:100%}
    }

    /* Crop modal — unscoped (hoisted to <body>, so NOT under .ovr-ld). */
    .ld-pf-crop{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,9,20,.55);padding:20px;font-family:'Inter',system-ui,-apple-system,sans-serif}
    .ld-pf-crop[hidden]{display:none}
    .ld-pf-crop-dialog{background:#fff;border-radius:16px;box-shadow:0 16px 48px rgba(0,0,0,.3);width:100%;max-width:360px;padding:24px}
    .ld-pf-crop-title{font-size:18px;font-weight:700;color:#0a1f2c;margin:0 0 16px;text-align:center}
    .ld-pf-crop-stage{position:relative;width:260px;height:260px;max-width:100%;margin:0 auto;border-radius:50%;overflow:hidden;background:#eef1f0;touch-action:none;cursor:grab;user-select:none}
    .ld-pf-crop-stage:active{cursor:grabbing}
    .ld-pf-crop-stage img{position:absolute;top:0;left:0;will-change:transform;pointer-events:none;max-width:none;user-select:none}
    .ld-pf-crop-zoom{display:flex;align-items:center;gap:12px;font-size:13px;color:#41555f;margin:18px 0 4px;font-weight:600}
    .ld-pf-crop-zoom input{flex:1}
    .ld-pf-crop-hint{font-size:12px;color:#6b7b84;margin:0 0 18px;text-align:center}
    .ld-pf-crop-actions{display:flex;gap:12px;justify-content:flex-end}
    .ld-pf-crop-btn{font-family:inherit;font-size:14px;font-weight:700;padding:11px 22px;border-radius:10px;border:1px solid transparent;cursor:pointer;transition:background .18s}
    .ld-pf-crop-btn--ghost{background:#fff;border-color:#d4dde0;color:#0a1f2c}
    .ld-pf-crop-btn--ghost:hover{background:#f1f5f6}
    .ld-pf-crop-btn--primary{background:#006666;color:#fff}
    .ld-pf-crop-btn--primary:hover{background:#004c4c}
    .ld-pf-crop-btn[disabled]{opacity:.6;cursor:default}
</style>

<script>
(function(){
    var t = document.getElementById('ld-pf-bio'),
        c = document.getElementById('ld-pf-bio-count');
    if (t && c) {
        t.addEventListener('input', function(){ c.textContent = t.value.length; });
    }

    // ── Profile photo: Choose File → (optional) Crop → Save → upload ──
    // 100% local & dependency-free: the cropped square is drawn on a <canvas>
    // and POSTed to this site's media library via AJAX. No redirect, no
    // third-party service, no Gravatar, no external account linking.
    var wrap  = document.querySelector('[data-ovr-avatar]');
    var modal = document.getElementById('ld-pf-crop');
    if (wrap && modal) {
        document.body.appendChild(modal); // hoist for viewport-relative overlay

        var input   = wrap.querySelector('[data-ovr-avatar-input]'),
            mainImg = wrap.querySelector('[data-ovr-avatar-img]'),
            status  = wrap.querySelector('[data-ovr-avatar-status]'),
            preview = document.querySelector('.ld-pf-preview-av'),
            ajax    = wrap.getAttribute('data-ajax'),
            nonce   = wrap.getAttribute('data-nonce');

        var stage   = modal.querySelector('#ld-pf-crop-stage'),
            cropImg = modal.querySelector('#ld-pf-crop-img'),
            zoom    = modal.querySelector('#ld-pf-crop-zoom'),
            saveBtn = modal.querySelector('#ld-pf-crop-save'),
            cancel  = modal.querySelector('#ld-pf-crop-cancel');

        var nw = 0, nh = 0, F = 260, minScale = 1, scale = 1, tx = 0, ty = 0, objURL = null;

        wrap.querySelectorAll('[data-ovr-avatar-trigger]').forEach(function(btn){
            btn.addEventListener('click', function(){ input.click(); });
        });

        input.addEventListener('change', function(){
            var file = input.files && input.files[0];
            if (!file) { return; }
            if (file.type.indexOf('image/') !== 0) {
                status.textContent = '<?php echo esc_js( __( 'Please choose a JPG, PNG, WebP, or GIF image.', 'ovr-core' ) ); ?>';
                input.value = ''; return;
            }
            if (file.size > 5 * 1024 * 1024) {
                status.textContent = '<?php echo esc_js( __( 'Image must be 5MB or smaller.', 'ovr-core' ) ); ?>';
                input.value = ''; return;
            }
            if (objURL) { URL.revokeObjectURL(objURL); }
            objURL = URL.createObjectURL(file);
            var probe = new Image();
            probe.onload = function(){ nw = probe.naturalWidth; nh = probe.naturalHeight; cropImg.src = objURL; openModal(); };
            probe.onerror = function(){ status.textContent = '<?php echo esc_js( __( 'That image could not be read. Try another file.', 'ovr-core' ) ); ?>'; input.value = ''; };
            probe.src = objURL;
        });

        function openModal(){
            F = stage.clientWidth || 260;
            minScale = Math.max(F / nw, F / nh); // smaller side fills the circle
            zoom.value = 1; scale = minScale;
            tx = (F - nw * scale) / 2; ty = (F - nh * scale) / 2; // centred
            render();
            modal.hidden = false; modal.setAttribute('aria-hidden', 'false');
        }
        function closeModal(){
            modal.hidden = true; modal.setAttribute('aria-hidden', 'true');
            if (objURL) { URL.revokeObjectURL(objURL); objURL = null; }
            input.value = '';
        }
        function render(){
            var dw = nw * scale, dh = nh * scale;
            if (tx > 0) { tx = 0; } if (tx < F - dw) { tx = F - dw; }
            if (ty > 0) { ty = 0; } if (ty < F - dh) { ty = F - dh; }
            cropImg.style.width = dw + 'px'; cropImg.style.height = dh + 'px';
            cropImg.style.transform = 'translate(' + tx + 'px,' + ty + 'px)';
        }

        zoom.addEventListener('input', function(){
            var z = parseFloat(zoom.value) || 1;
            var cx = (F / 2 - tx) / scale, cy = (F / 2 - ty) / scale; // keep centre fixed
            scale = minScale * z;
            tx = F / 2 - cx * scale; ty = F / 2 - cy * scale;
            render();
        });

        var dragging = false, px = 0, py = 0;
        stage.addEventListener('pointerdown', function(e){ dragging = true; px = e.clientX; py = e.clientY; stage.setPointerCapture(e.pointerId); });
        stage.addEventListener('pointermove', function(e){ if (!dragging) { return; } tx += e.clientX - px; ty += e.clientY - py; px = e.clientX; py = e.clientY; render(); });
        stage.addEventListener('pointerup',   function(){ dragging = false; });
        stage.addEventListener('pointercancel', function(){ dragging = false; });

        cancel.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e){ if (e.target === modal) { closeModal(); } });

        saveBtn.addEventListener('click', function(){
            var out = 512;
            var canvas = document.createElement('canvas');
            canvas.width = out; canvas.height = out;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, out, out); // white behind transparency
            var srcX = (-tx) / scale, srcY = (-ty) / scale, srcSize = F / scale;
            ctx.drawImage(cropImg, srcX, srcY, srcSize, srcSize, 0, 0, out, out);

            saveBtn.disabled = true; cancel.disabled = true;
            status.textContent = '<?php echo esc_js( __( 'Uploading…', 'ovr-core' ) ); ?>';

            canvas.toBlob(function(blob){
                if (!blob) { done(); return; }
                var data = new FormData();
                data.append('action', 'ovr_upload_avatar');
                data.append('nonce', nonce);
                data.append('avatar', blob, 'avatar.jpg');
                fetch(ajax, { method: 'POST', credentials: 'same-origin', body: data })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if (res && res.success && res.data && res.data.url) {
                            var bust = res.data.url + (res.data.url.indexOf('?') > -1 ? '&' : '?') + 'v=' + Date.now();
                            if (mainImg) { mainImg.src = bust; }
                            if (preview) { preview.src = bust; }
                            status.textContent = res.data.message || '<?php echo esc_js( __( 'Profile photo updated.', 'ovr-core' ) ); ?>';
                            closeModal();
                        } else {
                            status.textContent = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Upload failed. Please try again.', 'ovr-core' ) ); ?>';
                        }
                    })
                    .catch(function(){ status.textContent = '<?php echo esc_js( __( 'Upload failed. Please try again.', 'ovr-core' ) ); ?>'; })
                    .then(done);
            }, 'image/jpeg', 0.9);

            function done(){ saveBtn.disabled = false; cancel.disabled = false; }
        });
    }
})();
</script>
