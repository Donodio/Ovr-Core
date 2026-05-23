/**
 * OVR Core — Single Property JS
 *
 * Handles:
 *   1. Gallery lightbox (full-screen overlay, prev/next/keyboard/touch)
 *   2. Calendar click-to-select range → writes into inquiry form
 *   3. Inquiry form AJAX submit (with admin-post.php fallback when JS off)
 *
 * @package OVR
 */
(function () {
    'use strict';

    var ovr = window.ovrData || { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: '', i18n: {} };
    var i18n = ovr.i18n || {};

    /* ====================================================================
       1. GALLERY LIGHTBOX
       ==================================================================== */

    var lightbox = null;
    var lbState  = { images: [], index: 0, lastFocus: null };

    function collectGalleryImages(galleryEl) {
        var tiles = galleryEl.querySelectorAll('[data-ovr-gallery-open]');
        var imgs = [];
        tiles.forEach(function (tile) {
            var img = tile.querySelector('img');
            if (img) {
                imgs.push({
                    src: img.getAttribute('src'),
                    alt: img.getAttribute('alt') || ''
                });
            }
        });
        return imgs;
    }

    function buildLightbox() {
        if (lightbox) return lightbox;

        var el = document.createElement('div');
        el.className = 'ovr-lightbox';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', i18n.galleryLabel || 'Photo gallery');
        el.hidden = true;
        el.innerHTML =
            '<div class="ovr-lightbox-backdrop" data-ovr-lightbox-close></div>' +
            '<button type="button" class="ovr-lightbox-close" data-ovr-lightbox-close aria-label="' + (i18n.close || 'Close') + '">' +
                '<span class="material-symbols-outlined">close</span>' +
            '</button>' +
            '<button type="button" class="ovr-lightbox-prev" data-ovr-lightbox-prev aria-label="' + (i18n.previous || 'Previous photo') + '">' +
                '<span class="material-symbols-outlined">chevron_left</span>' +
            '</button>' +
            '<button type="button" class="ovr-lightbox-next" data-ovr-lightbox-next aria-label="' + (i18n.next || 'Next photo') + '">' +
                '<span class="material-symbols-outlined">chevron_right</span>' +
            '</button>' +
            '<figure class="ovr-lightbox-stage">' +
                '<img class="ovr-lightbox-img" alt="">' +
                '<figcaption class="ovr-lightbox-counter" aria-live="polite"></figcaption>' +
            '</figure>';

        document.body.appendChild(el);
        lightbox = el;
        return el;
    }

    function openLightbox(images, index) {
        if (!images.length) return;
        var lb = buildLightbox();
        lbState.images    = images;
        lbState.index     = Math.max(0, Math.min(index, images.length - 1));
        lbState.lastFocus = document.activeElement;

        renderLightbox();
        lb.hidden = false;
        document.body.style.overflow = 'hidden';
        // Focus the close button so keyboard users can hit Esc.
        var closeBtn = lb.querySelector('.ovr-lightbox-close');
        if (closeBtn) closeBtn.focus();
    }

    function closeLightbox() {
        if (!lightbox || lightbox.hidden) return;
        lightbox.hidden = true;
        document.body.style.overflow = '';
        if (lbState.lastFocus && typeof lbState.lastFocus.focus === 'function') {
            lbState.lastFocus.focus();
        }
    }

    function renderLightbox() {
        if (!lightbox) return;
        var img = lightbox.querySelector('.ovr-lightbox-img');
        var counter = lightbox.querySelector('.ovr-lightbox-counter');
        var current = lbState.images[lbState.index];
        if (img && current) {
            img.src = current.src;
            img.alt = current.alt;
        }
        if (counter) {
            counter.textContent = (lbState.index + 1) + ' / ' + lbState.images.length;
        }
        // Hide nav buttons at edges.
        var prev = lightbox.querySelector('.ovr-lightbox-prev');
        var next = lightbox.querySelector('.ovr-lightbox-next');
        if (prev) prev.style.visibility = lbState.index === 0 ? 'hidden' : 'visible';
        if (next) next.style.visibility = lbState.index === lbState.images.length - 1 ? 'hidden' : 'visible';
    }

    function navLightbox(delta) {
        var next = lbState.index + delta;
        if (next < 0 || next >= lbState.images.length) return;
        lbState.index = next;
        renderLightbox();
    }

    /* Open from gallery tile */
    document.addEventListener('click', function (e) {
        var tile = e.target.closest('[data-ovr-gallery-open]');
        if (!tile) return;
        e.preventDefault();
        var galleryEl = tile.closest('[data-ovr-gallery]');
        if (!galleryEl) return;
        var images = collectGalleryImages(galleryEl);
        var idx = parseInt(tile.getAttribute('data-ovr-gallery-open'), 10) || 0;
        openLightbox(images, idx);
    });

    /* Lightbox controls (delegated) */
    document.addEventListener('click', function (e) {
        if (!lightbox || lightbox.hidden) return;
        if (e.target.closest('[data-ovr-lightbox-close]')) {
            closeLightbox();
        } else if (e.target.closest('[data-ovr-lightbox-prev]')) {
            navLightbox(-1);
        } else if (e.target.closest('[data-ovr-lightbox-next]')) {
            navLightbox(1);
        }
    });

    /* Keyboard nav */
    document.addEventListener('keydown', function (e) {
        if (!lightbox || lightbox.hidden) return;
        if (e.key === 'Escape')      closeLightbox();
        else if (e.key === 'ArrowLeft')  navLightbox(-1);
        else if (e.key === 'ArrowRight') navLightbox(1);
    });

    /* Touch swipe */
    (function () {
        var touchStartX = null;
        document.addEventListener('touchstart', function (e) {
            if (!lightbox || lightbox.hidden) return;
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        document.addEventListener('touchend', function (e) {
            if (!lightbox || lightbox.hidden || touchStartX === null) return;
            var dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 50) navLightbox(dx < 0 ? 1 : -1);
            touchStartX = null;
        });
    })();

    /* ====================================================================
       2. CALENDAR — click-to-select range
       ==================================================================== */

    var calState = { start: null, end: null };

    function calIsBlocked(cell) {
        return cell.classList.contains('is-blocked') || cell.classList.contains('is-past');
    }

    function calClearSelection(rootEl) {
        rootEl.querySelectorAll('.ovr-cal-day').forEach(function (c) {
            c.classList.remove('is-range-start', 'is-range-end', 'is-range-mid');
        });
    }

    function calApplyRange(rootEl) {
        if (!calState.start) return;
        calClearSelection(rootEl);

        var startDate = calState.start;
        var endDate   = calState.end || calState.start;

        rootEl.querySelectorAll('.ovr-cal-day[data-date]').forEach(function (c) {
            var d = c.getAttribute('data-date');
            if (d === startDate) c.classList.add('is-range-start');
            if (d === endDate)   c.classList.add('is-range-end');
            if (d > startDate && d < endDate) c.classList.add('is-range-mid');
        });
    }

    function calSyncToInquiry(propertyId) {
        if (!propertyId) return;
        var checkin  = document.getElementById('ovr-checkin-' + propertyId);
        var checkout = document.getElementById('ovr-checkout-' + propertyId);
        if (checkin)  checkin.value  = calState.start || '';
        if (checkout) checkout.value = calState.end   || '';
    }

    document.addEventListener('click', function (e) {
        var cell = e.target.closest('.ovr-cal-day[data-date]');
        if (!cell) return;
        var rootEl = cell.closest('[data-ovr-calendar]');
        if (!rootEl) return;
        if (calIsBlocked(cell)) return;

        var date = cell.getAttribute('data-date');
        var propertyId = rootEl.getAttribute('data-property-id');

        // First click → set start, clear end.
        // Second click before/equal start → reset to new start.
        // Second click after start → set end.
        if (!calState.start || (calState.start && calState.end) || date < calState.start) {
            calState.start = date;
            calState.end   = null;
        } else if (date === calState.start) {
            calState.start = null;
            calState.end   = null;
        } else {
            calState.end = date;
        }

        calApplyRange(rootEl);
        calSyncToInquiry(propertyId);
    });

    /* ====================================================================
       3. INQUIRY FORM AJAX SUBMIT
       ==================================================================== */

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-ovr-inquiry-form]');
        if (!form) return;

        // Progressive enhancement: only intercept when fetch + JSON are available.
        if (!window.fetch) return;

        e.preventDefault();

        var responseEl = form.querySelector('[data-ovr-inquiry-response]');
        var submitBtn  = form.querySelector('button[type="submit"]');
        var origLabel  = submitBtn ? submitBtn.innerHTML : '';

        if (responseEl) responseEl.innerHTML = '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="material-symbols-outlined" style="vertical-align:middle">progress_activity</span> ' + (i18n.loading || 'Sending…');
        }

        var formData = new FormData(form);
        // Switch the action to the AJAX endpoint and add the public nonce.
        formData.set('action', 'ovr_submit_inquiry');
        formData.append('nonce', ovr.nonce || '');

        fetch(ovr.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var ok = res && res.success;
                var redirectUrl = res && res.data && res.data.redirect_url;
                var msg = (res && res.data && res.data.message) ||
                          (ok ? 'Sent.' : (i18n.error || 'Something went wrong.'));
                if (responseEl) {
                    responseEl.innerHTML =
                        '<div class="ovr-alert ' + (ok ? 'ovr-alert-success' : 'ovr-alert-error') + '" style="margin-top:16px">' +
                            '<span class="material-symbols-outlined">' + (ok ? 'check_circle' : 'error') + '</span>' +
                            '<span>' + msg + '</span>' +
                        '</div>';
                }
                if (ok && redirectUrl) {
                    window.location.href = redirectUrl;
                    return;
                }
                if (ok) form.reset();
            })
            .catch(function () {
                if (responseEl) {
                    responseEl.innerHTML =
                        '<div class="ovr-alert ovr-alert-error" style="margin-top:16px">' +
                            '<span class="material-symbols-outlined">error</span>' +
                            '<span>' + (i18n.error || 'Network error. Please try again.') + '</span>' +
                        '</div>';
                }
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origLabel;
                }
            });
    });

    /* ====================================================================
       4. REVIEWS SECTION
       ==================================================================== */
       
    // Toggle review form
    document.addEventListener('click', function(e) {
        var toggleBtn = e.target.closest('[data-ovr-review-toggle]');
        if (!toggleBtn) return;
        
        var form = document.getElementById('ovr-review-form');
        if (form) {
            form.hidden = !form.hidden;
            if (!form.hidden) {
                var firstInput = form.querySelector('input, textarea');
                if (firstInput) firstInput.focus();
            }
        }
    });

    // Handle review submission
    document.addEventListener('submit', function(e) {
        var form = e.target.closest('[data-ovr-review-form]');
        if (!form) return;
        if (!window.fetch) return;
        
        e.preventDefault();
        
        var responseEl = form.querySelector('[data-ovr-review-result]');
        var submitBtn  = form.querySelector('button[type="submit"]');
        var origLabel  = submitBtn ? submitBtn.innerHTML : '';
        
        if (responseEl) responseEl.innerHTML = '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="material-symbols-outlined" style="vertical-align:middle">progress_activity</span> ' + (i18n.loading || 'Submitting…');
        }
        
        var formData = new FormData(form);
        formData.set('action', 'ovr_submit_review');
        formData.append('nonce', ovr.nonce || '');
        formData.append('property_id', form.getAttribute('data-property-id'));
        
        fetch(ovr.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var ok = res && res.success;
            var msg = (res && res.data && res.data.message) || (ok ? 'Review submitted!' : 'Error submitting review.');
            
            if (responseEl) {
                responseEl.innerHTML =
                    '<div class="ovr-alert ' + (ok ? 'ovr-alert-success' : 'ovr-alert-error') + '" style="margin-top:16px;font-size:14px">' +
                        '<span class="material-symbols-outlined">' + (ok ? 'check_circle' : 'error') + '</span>' +
                        '<span>' + msg + '</span>' +
                    '</div>';
            }
            if (ok) {
                form.reset();
                setTimeout(function() { form.hidden = true; }, 3000);
            }
        })
        .catch(function() {
            if (responseEl) {
                responseEl.innerHTML =
                    '<div class="ovr-alert ovr-alert-error" style="margin-top:16px;font-size:14px">' +
                        '<span class="material-symbols-outlined">error</span>' +
                        '<span>Network error. Please try again.</span>' +
                    '</div>';
            }
        })
        .finally(function() {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origLabel;
            }
        });
    });

    /* ====================================================================
       5. TABS (General Description / Features / Reviews)
       ==================================================================== */

    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.ovr-tab[data-ovr-tab]');
        if (!tab) return;
        var tabs = tab.closest('[data-ovr-tabs]');
        if (!tabs) return;

        var key = tab.getAttribute('data-ovr-tab');

        tabs.querySelectorAll('.ovr-tab').forEach(function (t) {
            var on = t === tab;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        tabs.querySelectorAll('.ovr-tab-panel').forEach(function (p) {
            p.classList.toggle('is-active', p.getAttribute('data-ovr-panel') === key);
        });
    });

    /* ====================================================================
       6. INJECT MINIMAL LIGHTBOX + CALENDAR-RANGE STYLES
       ==================================================================== */

    var styleId = 'ovr-property-runtime-styles';
    if (!document.getElementById(styleId)) {
        var style = document.createElement('style');
        style.id = styleId;
        style.textContent =
            '.ovr-lightbox{position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center}' +
            '.ovr-lightbox[hidden]{display:none}' +
            '.ovr-lightbox-backdrop{position:absolute;inset:0;background:rgba(0,0,0,0.92);cursor:pointer}' +
            '.ovr-lightbox-stage{position:relative;max-width:92vw;max-height:88vh;display:flex;flex-direction:column;align-items:center;gap:12px;margin:0}' +
            '.ovr-lightbox-img{max-width:92vw;max-height:84vh;object-fit:contain;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,0.5)}' +
            '.ovr-lightbox-counter{color:#fff;font-size:14px;letter-spacing:0.05em;font-weight:500}' +
            '.ovr-lightbox-close,.ovr-lightbox-prev,.ovr-lightbox-next{position:absolute;width:48px;height:48px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(255,255,255,0.15);color:#fff;border:none;cursor:pointer;backdrop-filter:blur(4px);transition:background 200ms}' +
            '.ovr-lightbox-close:hover,.ovr-lightbox-prev:hover,.ovr-lightbox-next:hover{background:rgba(255,255,255,0.3)}' +
            '.ovr-lightbox-close{top:24px;right:24px}' +
            '.ovr-lightbox-prev{left:24px;top:50%;transform:translateY(-50%)}' +
            '.ovr-lightbox-next{right:24px;top:50%;transform:translateY(-50%)}' +
            '@media (max-width:768px){.ovr-lightbox-prev{left:8px}.ovr-lightbox-next{right:8px}}' +
            '.ovr-cal-day{cursor:pointer;transition:background 150ms,color 150ms}' +
            '.ovr-cal-day.is-past,.ovr-cal-day.is-blocked{cursor:not-allowed}' +
            '.ovr-cal-day:not(.is-past):not(.is-blocked):hover{background:var(--ovr-primary-fixed-dim);color:var(--ovr-on-primary-fixed)}' +
            '.ovr-cal-day.is-range-start,.ovr-cal-day.is-range-end{background:var(--ovr-primary)!important;color:var(--ovr-on-primary)!important;font-weight:700}' +
            '.ovr-cal-day.is-range-mid{background:var(--ovr-primary-container);color:var(--ovr-on-primary-container)}';
        document.head.appendChild(style);
    }

})();
