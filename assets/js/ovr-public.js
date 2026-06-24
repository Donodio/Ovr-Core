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
        btn.setAttribute('aria-pressed', isFilled ? 'false' : 'true');
    });

    /* ──────────────────────────────────────
       Accessibility layer (M3)
       Marks decorative Material-Symbols icons as aria-hidden, labels
       icon-only controls from their title attribute, gives empty alt to
       image elements missing one, and primes favorite buttons' pressed state.
       Covers plugin- and Elementor-rendered markup alike.
       ────────────────────────────────────── */
    (function initA11y() {
        // Decorative icon fonts should not be announced.
        document.querySelectorAll('.material-symbols-outlined').forEach(function (icon) {
            if (!icon.hasAttribute('aria-label') && !icon.hasAttribute('aria-hidden')) {
                icon.setAttribute('aria-hidden', 'true');
            }
        });

        // Icon-only buttons / links: borrow the title as an accessible name.
        // Material-Symbols icons render their label as ligature text, so that
        // text must be excluded when deciding whether a control is icon-only.
        document.querySelectorAll('.ovr-wrap button, .ovr-wrap a').forEach(function (el) {
            if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby')) return;
            var text = el.textContent || '';
            el.querySelectorAll('.material-symbols-outlined').forEach(function (ic) {
                text = text.replace(ic.textContent || '', '');
            });
            if (text.replace(/\s+/g, '').length) return; // has real visible text
            var title = el.getAttribute('title');
            if (title) el.setAttribute('aria-label', title);
        });

        // Images without an alt attribute are treated as decorative.
        document.querySelectorAll('.ovr-wrap img:not([alt])').forEach(function (img) {
            img.setAttribute('alt', '');
        });

        // Favorite buttons start unpressed unless already filled.
        document.querySelectorAll('.ovr-card-favorite').forEach(function (btn) {
            if (!btn.hasAttribute('aria-pressed')) {
                var ic = btn.querySelector('.material-symbols-outlined');
                btn.setAttribute('aria-pressed', ic && ic.classList.contains('fill') ? 'true' : 'false');
            }
        });
    })();

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
       Mobile Nav Toggle
       Opens/closes the site-header drawer. The button carries
       data-ovr-action="mobile-menu"; the drawer + its open state live
       on the .ovr-topnav element (.is-open).
       ────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-ovr-action="mobile-menu"]');
        if (!toggle) return;
        e.preventDefault();

        var nav = toggle.closest('.ovr-topnav');
        if (!nav) return;

        var isOpen = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        var drawer = nav.querySelector('[data-ovr-mobile-drawer]');
        if (drawer) {
            drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }

        // Lock background scroll while the drawer is open.
        document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    // Close the drawer when a link inside it is tapped (so navigating to an
    // in-page anchor or the same page doesn't leave the drawer open).
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.ovr-mobile-link');
        if (!link) return;
        var nav = link.closest('.ovr-topnav');
        if (nav && nav.classList.contains('is-open')) {
            nav.classList.remove('is-open');
            document.body.style.overflow = '';
            var toggle = nav.querySelector('[data-ovr-action="mobile-menu"]');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        }
    });

    /* ──────────────────────────────────────
       Homepage Hero Slider (one property per slide)
       Transform-based track. Next/prev step exactly one slide and wrap around.
       Markup: .ovr-hps-slider > .ovr-hps-viewport > .ovr-hps-track > .ovr-hps-slide
       ────────────────────────────────────── */
    document.querySelectorAll('.ovr-hps-slider').forEach(function (root) {
        var track  = root.querySelector('.ovr-hps-track');
        if (!track) return;

        var slides = track.querySelectorAll('.ovr-hps-slide');
        var total  = slides.length;
        if (total <= 1) return; // nothing to slide

        var prev = root.querySelector('.ovr-hps-prev');
        var next = root.querySelector('.ovr-hps-next');
        var dots = root.querySelectorAll('.ovr-hps-dot');
        var index = 0;

        function show(i) {
            index = (i + total) % total; // wrap both directions
            track.style.transform = 'translateX(' + (-index * 100) + '%)';
            dots.forEach(function (d, di) { d.classList.toggle('is-active', di === index); });
        }

        if (next) next.addEventListener('click', function () { show(index + 1); reset(); });
        if (prev) prev.addEventListener('click', function () { show(index - 1); reset(); });
        dots.forEach(function (d) {
            d.addEventListener('click', function () { show(parseInt(d.dataset.index, 10) || 0); reset(); });
        });

        // Keyboard support when the slider is focused.
        root.setAttribute('tabindex', '0');
        root.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { show(index + 1); reset(); }
            else if (e.key === 'ArrowLeft') { show(index - 1); reset(); }
        });

        // Autoplay (pauses on hover / focus / touch).
        var timer = null;
        var autoplay = root.dataset.autoplay === '1';
        var interval = parseInt(root.dataset.interval, 10) || 6000;
        function start() { if (autoplay && !timer) { timer = setInterval(function () { show(index + 1); }, interval); } }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function reset() { stop(); start(); }
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);

        // Touch swipe (mobile): horizontal drag advances one slide.
        var touchX = null, touchY = null;
        root.addEventListener('touchstart', function (e) {
            stop();
            touchX = e.touches[0].clientX;
            touchY = e.touches[0].clientY;
        }, { passive: true });
        root.addEventListener('touchend', function (e) {
            if (touchX === null) { start(); return; }
            var dx = e.changedTouches[0].clientX - touchX;
            var dy = e.changedTouches[0].clientY - touchY;
            // Only treat mostly-horizontal drags past a threshold as swipes.
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                show(dx < 0 ? index + 1 : index - 1);
            }
            touchX = touchY = null;
            reset();
        }, { passive: true });

        show(0);
        start();
    });

})();
