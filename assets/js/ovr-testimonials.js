/**
 * OVR — Testimonials / Property Carousel engine
 *
 * Drives the `OVR Testimonials Carousel` and `OVR Property Carousel` Elementor
 * widgets. Self-contained (no external slider dependency). The widget prefix
 * (e.g. `ovr-tc`, `ovr-pc`) is read from `data-ovr-prefix` so this single
 * engine serves every carousel; it falls back to `ovr-tc`. Reads per-view from
 * the CSS custom property `--<prefix>-per` (Elementor responsive control sets
 * it per breakpoint), so the number of visible cards is fully responsive.
 *
 * Markup contract:
 *   .<prefix>[data-ovr-carousel]              root  (data-autoplay, data-interval, data-loop)
 *     .<prefix>-viewport > .<prefix>-track    track holding .<prefix>-card slides
 *     .<prefix>-prev / .<prefix>-next         arrow buttons (optional)
 *     .<prefix>-dots                          dots container (optional)
 *
 * @package OVR
 */
(function () {
    'use strict';

    function init(root) {
        if (!root) return;
        var prefix = root.getAttribute('data-ovr-prefix') || 'ovr-tc';
        if (root.getAttribute('data-' + prefix + '-ready') === '1') return;

        var track = root.querySelector('.' + prefix + '-track');
        if (!track) return;
        var slides = Array.prototype.slice.call(track.querySelectorAll('.' + prefix + '-card'));
        if (!slides.length) { root.setAttribute('data-' + prefix + '-ready', '1'); return; }

        root.setAttribute('data-' + prefix + '-ready', '1');

        var prevBtn = root.querySelector('.' + prefix + '-prev');
        var nextBtn = root.querySelector('.' + prefix + '-next');
        var dotsBox = root.querySelector('.' + prefix + '-dots');

        var autoplay = root.getAttribute('data-autoplay') === '1';
        var interval = parseInt(root.getAttribute('data-interval'), 10) || 5000;
        var loop     = root.getAttribute('data-loop') === '1';

        var index = 0;
        var perView = 1;
        var maxIndex = 0;
        var timer = null;

        function readPerView() {
            var cssVal = parseFloat(getComputedStyle(root).getPropertyValue('--' + prefix + '-per'));
            if (isNaN(cssVal) || cssVal < 1) cssVal = 1;
            return Math.min(Math.round(cssVal), slides.length);
        }

        function gapPx() {
            var cs = getComputedStyle(track);
            var g = parseFloat(cs.columnGap || cs.gap || '0');
            return isNaN(g) ? 0 : g;
        }

        function measure() {
            perView = readPerView();
            maxIndex = Math.max(0, slides.length - perView);
            if (index > maxIndex) index = maxIndex;
            buildDots();
            apply();
        }

        function apply() {
            var step = slides[0].getBoundingClientRect().width + gapPx();
            track.style.transform = 'translate3d(' + (-index * step) + 'px,0,0)';

            if (prevBtn) prevBtn.disabled = (!loop && index <= 0);
            if (nextBtn) nextBtn.disabled = (!loop && index >= maxIndex);

            if (dotsBox) {
                var dots = dotsBox.children;
                for (var i = 0; i < dots.length; i++) {
                    dots[i].classList.toggle('is-active', i === index);
                    dots[i].setAttribute('aria-selected', i === index ? 'true' : 'false');
                }
            }
        }

        function go(i) {
            if (i < 0) i = loop ? maxIndex : 0;
            if (i > maxIndex) i = loop ? 0 : maxIndex;
            index = i;
            apply();
        }

        function next() { go(index + 1); }
        function prev() { go(index - 1); }

        function buildDots() {
            if (!dotsBox) return;
            dotsBox.innerHTML = '';
            for (var i = 0; i <= maxIndex; i++) {
                (function (i) {
                    var dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = prefix + '-dot';
                    dot.setAttribute('role', 'tab');
                    dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
                    dot.addEventListener('click', function () { go(i); restart(); });
                    dotsBox.appendChild(dot);
                })(i);
            }
        }

        /* Autoplay -------------------------------------------------------- */
        function start() {
            if (!autoplay || maxIndex === 0) return;
            stop();
            timer = window.setInterval(next, interval);
        }
        function stop() { if (timer) { window.clearInterval(timer); timer = null; } }
        function restart() { stop(); start(); }

        if (autoplay) {
            root.addEventListener('mouseenter', stop);
            root.addEventListener('mouseleave', start);
            root.addEventListener('focusin', stop);
            root.addEventListener('focusout', start);
        }

        /* Arrows ---------------------------------------------------------- */
        if (prevBtn) prevBtn.addEventListener('click', function () { prev(); restart(); });
        if (nextBtn) nextBtn.addEventListener('click', function () { next(); restart(); });

        /* Keyboard -------------------------------------------------------- */
        root.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { prev(); restart(); }
            else if (e.key === 'ArrowRight') { next(); restart(); }
        });

        /* Swipe / drag ---------------------------------------------------- */
        var startX = 0, dragging = false;
        track.addEventListener('pointerdown', function (e) {
            dragging = true; startX = e.clientX; stop();
        });
        window.addEventListener('pointerup', function (e) {
            if (!dragging) return;
            dragging = false;
            var dx = e.clientX - startX;
            if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); }
            start();
        });

        /* Resize ---------------------------------------------------------- */
        var rt;
        window.addEventListener('resize', function () {
            window.clearTimeout(rt);
            rt = window.setTimeout(measure, 150);
        });

        measure();
        start();
        setupReadMore(root);
    }

    /* Reveal a "Read more" toggle only when a quote is actually clamped. */
    function setupReadMore(root) {
        var qPrefix = root.getAttribute('data-ovr-prefix') || 'ovr-tc';
        var btns = root.querySelectorAll('.' + qPrefix + '-readmore');
        Array.prototype.forEach.call(btns, function (btn) {
            var card = btn.closest ? btn.closest('.' + qPrefix + '-card') : null;
            var quote = card ? card.querySelector('.' + qPrefix + '-quote') : null;
            if (!quote) return;

            function evaluate() {
                if (quote.classList.contains('is-expanded')) return;
                btn.hidden = (quote.scrollHeight - quote.clientHeight) <= 2;
            }
            evaluate();
            window.addEventListener('resize', evaluate);
            window.addEventListener('load', evaluate);

            btn.addEventListener('click', function () {
                var expanded = quote.classList.toggle('is-expanded');
                quote.classList.toggle('is-clamped', !expanded);
                btn.textContent = expanded
                    ? (btn.getAttribute('data-less') || 'Read less')
                    : (btn.getAttribute('data-more') || 'Read more');
            });
        });
    }

    function initAll(scope) {
        var root = scope && scope.querySelectorAll ? scope : document;
        Array.prototype.slice.call(root.querySelectorAll('[data-ovr-carousel]')).forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(document); });
    } else {
        initAll(document);
    }
    window.addEventListener('load', function () { initAll(document); });

    // Elementor editor / frontend re-init when widgets render in the preview.
    if (window.jQuery) {
        window.jQuery(window).on('elementor/frontend/init', function () {
            if (window.elementorFrontend && window.elementorFrontend.hooks) {
                window.elementorFrontend.hooks.addAction(
                    'frontend/element_ready/ovr_testimonials_carousel.default',
                    function ($scope) { init($scope[0].querySelector('.ovr-tc[data-ovr-carousel]')); }
                );
                window.elementorFrontend.hooks.addAction(
                    'frontend/element_ready/ovr_property_carousel.default',
                    function ($scope) { init($scope[0].querySelector('.ovr-pc[data-ovr-carousel]')); }
                );
            }
        });
    }

    window.ovrInitTestimonials = initAll;
})();