/**
 * OVR Core — Public JavaScript
 * @package OVR
 */
(function () {
    'use strict';

    /* ──────────────────────────────────────
       Smooth scroll for anchor links
       ────────────────────────────────────── */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    /* ──────────────────────────────────────
       Favorite / Save Button
       ────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ovr-card-favorite');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var icon = btn.querySelector('.material-symbols-outlined');
        if (!icon) return;

        var isFilled = icon.classList.contains('fill');
        icon.classList.toggle('fill');
        icon.style.color = isFilled ? '' : 'var(--ovr-error)';
    });

    /* ──────────────────────────────────────
       Promo Code Apply
       ────────────────────────────────────── */
    var promoBtn = document.getElementById('ovr-promo-apply');
    if (promoBtn) {
        promoBtn.addEventListener('click', function () {
            var input = document.getElementById('ovr-promo-input');
            var msgEl = document.getElementById('ovr-promo-msg');
            var code = input ? input.value.trim() : '';

            if (!code) {
                if (msgEl) {
                    msgEl.textContent = 'Please enter a promo code.';
                    msgEl.style.color = 'var(--ovr-error)';
                }
                return;
            }

            if (msgEl) {
                msgEl.textContent = 'Checking...';
                msgEl.style.color = 'var(--ovr-on-surface-variant)';
            }

            var fd = new FormData();
            fd.append('action', 'ovr_apply_promo');
            fd.append('nonce', window.ovrData ? window.ovrData.nonce : '');
            fd.append('code', code);

            fetch(window.ovrData ? window.ovrData.ajaxUrl : '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (msgEl) {
                        msgEl.textContent = res.data ? res.data.message : 'Invalid code.';
                        msgEl.style.color = res.success ? 'var(--ovr-secondary)' : 'var(--ovr-error)';
                    }
                })
                .catch(function () {
                    if (msgEl) {
                        msgEl.textContent = 'Network error. Please try again.';
                        msgEl.style.color = 'var(--ovr-error)';
                    }
                });
        });
    }

    /* ──────────────────────────────────────
       Hero Slideshow (M3 F7)
       Rotates the admin-managed hero slides (and their per-slide captions),
       building clickable position dots. Single-slide heroes are left static,
       and auto-advance is suppressed when the user prefers reduced motion.
       ────────────────────────────────────── */
    (function initHeroSlideshows() {
        var heroes = document.querySelectorAll('.ovr-hero--slideshow');
        if (!heroes.length) return;

        var reduceMotion = window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        heroes.forEach(function (hero) {
            var stage    = hero.querySelector('.ovr-hero-slideshow');
            var slides   = stage ? stage.querySelectorAll('.ovr-hero-slide') : [];
            var captions = hero.querySelectorAll('.ovr-hero-caption');
            if (slides.length < 2) return;

            var interval = parseInt(stage.getAttribute('data-interval'), 10) || 6000;
            var current  = 0;
            var timer    = null;

            // Build position dots.
            var dotsWrap = document.createElement('div');
            dotsWrap.className = 'ovr-hero-dots';
            var dots = [];
            for (var i = 0; i < slides.length; i++) {
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.setAttribute('aria-label', 'Slide ' + (i + 1));
                if (i === 0) dot.className = 'is-active';
                (function (idx) {
                    dot.addEventListener('click', function () { go(idx); restart(); });
                })(i);
                dotsWrap.appendChild(dot);
                dots.push(dot);
            }
            hero.appendChild(dotsWrap);

            function setActive(list, idx) {
                for (var j = 0; j < list.length; j++) {
                    list[j].classList.toggle('is-active', j === idx);
                }
            }

            function go(idx) {
                current = (idx + slides.length) % slides.length;
                setActive(slides, current);
                if (captions.length) setActive(captions, current);
                setActive(dots, current);
            }

            function restart() {
                if (reduceMotion) return;
                if (timer) clearInterval(timer);
                timer = setInterval(function () { go(current + 1); }, interval);
            }

            restart();
        });
    })();

    /* ──────────────────────────────────────
       Mobile Nav Toggle (Phase 2 enhancement)
       ────────────────────────────────────── */

})();
