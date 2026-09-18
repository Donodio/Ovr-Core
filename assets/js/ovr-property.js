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
        if (videoModal && !videoModal.hidden) closeVideoViewer();
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
       1b. VIDEO VIEWER — an uploaded MP4 / MOV or a YouTube/Vimeo link plays
       in a closable overlay, leaving the photo gallery and slideshow intact.
       ==================================================================== */

    var videoModal = null;
    var vState     = { lastFocus: null };

    function buildVideoModal() {
        if (videoModal) return videoModal;

        var el = document.createElement('div');
        el.className = 'ovr-video-modal';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', i18n.videoTitle || 'Property video tour');
        el.hidden = true;
        el.innerHTML =
            '<div class="ovr-video-backdrop" data-ovr-video-close></div>' +
            '<button type="button" class="ovr-video-close" data-ovr-video-close aria-label="' + (i18n.close || 'Close') + '">' +
                '<span class="material-symbols-outlined">close</span>' +
            '</button>' +
            '<figure class="ovr-video-stage"></figure>';

        document.body.appendChild(el);
        videoModal = el;
        return el;
    }

    function openVideoViewer(galleryEl) {
        var src   = galleryEl.getAttribute('data-ovr-video-src') || '';
        var embed = galleryEl.getAttribute('data-ovr-video-embed') || '';
        if (!src && !embed) return;

        closeLightbox();

        var modal = buildVideoModal();
        var stage = modal.querySelector('.ovr-video-stage');
        stage.innerHTML = '';
        var webOk = galleryEl.getAttribute('data-ovr-video-web') !== '0';

        if (embed) {
            var autoplay = (embed.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
            var frame = document.createElement('iframe');
            frame.src = embed + autoplay;
            frame.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
            frame.setAttribute('allowfullscreen', '');
            frame.setAttribute('title', i18n.videoTitle || 'Property video tour');
            stage.appendChild(frame);
        } else if (src) {
            var video = document.createElement('video');
            video.controls = true;
            video.autoplay = true;
            video.preload = 'metadata';
            video.setAttribute('playsinline', '');
            video.setAttribute('src', src);
            var type = galleryEl.getAttribute('data-ovr-video-type') || '';
            if (type) video.setAttribute('type', type);
            // Poster = the main photo the viewer was opened from.
            var heroImg = galleryEl.querySelector('.ovr-gallery-imgbtn img');
            if (heroImg && heroImg.src) video.setAttribute('poster', heroImg.src);
            stage.appendChild(video);
            if (!webOk) {
                var note = document.createElement('p');
                note.className = 'ovr-video-note';
                note.textContent = i18n.codecNote ||
                    'This video uses a codec (HEVC/H.265) that your browser may not support. Please contact the owner for an alternative format.';
                stage.appendChild(note);
            }
        }

        vState.lastFocus = document.activeElement;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        var closeBtn = modal.querySelector('.ovr-video-close');
        if (closeBtn) closeBtn.focus();
    }

    function closeVideoViewer() {
        if (!videoModal || videoModal.hidden) return;
        var stage = videoModal.querySelector('.ovr-video-stage');
        if (stage) { stage.innerHTML = ''; } // stops any playing <video>
        videoModal.hidden = true;
        document.body.style.overflow = '';
        if (vState.lastFocus && typeof vState.lastFocus.focus === 'function') {
            vState.lastFocus.focus();
        }
    }

    /* Open from the play button on the main photo. */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ovr-video-open]');
        if (!btn) return;
        e.preventDefault();
        var galleryEl = btn.closest('[data-ovr-gallery]');
        if (!galleryEl) return;

        var src   = galleryEl.getAttribute('data-ovr-video-src') || '';
        var embed = galleryEl.getAttribute('data-ovr-video-embed') || '';

        if (embed) {
            openVideoViewer(galleryEl);
        } else if (src) {
            openVideoViewer(galleryEl);
        }
    });

    /* Video-viewer controls (delegated). */
    document.addEventListener('click', function (e) {
        if (!videoModal || videoModal.hidden) return;
        if (e.target.closest('[data-ovr-video-close]')) {
            closeVideoViewer();
        }
    });

    /* Esc closes the video viewer. */
    document.addEventListener('keydown', function (e) {
        if (!videoModal || videoModal.hidden) return;
        if (e.key === 'Escape') closeVideoViewer();
    });

    /* Panorama / Virtual Tour viewer (reuses the video-modal shell for a simple
       image/iframe lightbox). */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ovr-panorama-open]');
        if (!btn) return;
        e.preventDefault();
        var url = btn.getAttribute('data-ovr-panorama-url') || '';
        var src = btn.getAttribute('data-ovr-panorama-src') || '';
        if (!url && !src) return;

        var modal = buildVideoModal();
        var stage = modal.querySelector('.ovr-video-stage');
        stage.innerHTML = '';
        stage.removeAttribute('data-ovr-video-src');
        stage.removeAttribute('data-ovr-video-embed');

        if (src) {
            var img = document.createElement('img');
            img.src = src;
            img.alt = i18n.panoramaAlt || '360° panorama';
            img.style.maxWidth = '94vw';
            img.style.maxHeight = '88vh';
            img.style.objectFit = 'contain';
            img.style.borderRadius = '8px';
            stage.appendChild(img);
        } else if (url) {
            if (url.match(/\.(mp4|webm|mov|m4v)(\?.*)?$/i)) {
                var video = document.createElement('video');
                video.controls = true;
                video.autoplay = true;
                video.preload = 'metadata';
                video.setAttribute('playsinline', '');
                video.setAttribute('src', url);
                stage.appendChild(video);
            } else {
                var frame = document.createElement('iframe');
                frame.src = url;
                frame.setAttribute('allowfullscreen', '');
                frame.setAttribute('title', i18n.virtualTourTitle || 'Virtual Tour');
                frame.style.width = 'min(94vw,1100px)';
                frame.style.aspectRatio = '16/9';
                frame.style.border = '0';
                frame.style.borderRadius = '8px';
                frame.style.background = '#000';
                stage.appendChild(frame);
            }
        }

        vState.lastFocus = document.activeElement;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        var closeBtn = modal.querySelector('[data-ovr-video-close]');
        if (closeBtn) closeBtn.focus();
    });

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
            '.ovr-video-modal{position:fixed;inset:0;z-index:10001;display:flex;align-items:center;justify-content:center}' +
            '.ovr-video-modal[hidden]{display:none}' +
            '.ovr-video-backdrop{position:absolute;inset:0;background:rgba(0,0,0,0.92);cursor:pointer}' +
            '.ovr-video-stage{position:relative;max-width:94vw;max-height:88vh;display:flex;flex-direction:column;align-items:center;gap:12px;margin:0;padding:0}' +
            '.ovr-video-stage video{width:auto;max-width:94vw;max-height:78vh;background:#000;border:0;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,0.5)}' +
            '.ovr-video-stage iframe{width:min(94vw,1100px);aspect-ratio:16/9;border:0;border-radius:8px;background:#000;box-shadow:0 20px 60px rgba(0,0,0,0.5)}' +
            '.ovr-video-close{position:absolute;top:24px;right:24px;width:48px;height:48px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(255,255,255,0.15);color:#fff;border:none;cursor:pointer;backdrop-filter:blur(4px);z-index:5;transition:background 200ms}' +
            '.ovr-video-close:hover{background:rgba(255,255,255,0.3)}' +
            '.ovr-video-note{margin:12px 0 0;padding:10px 16px;font-size:13px;line-height:1.5;color:#6b4e00;background:#fff6d9;border:1px solid #e7cf7e;border-radius:8px;max-width:94vw}' +
            '@media (max-width:768px){.ovr-video-stage iframe{width:94vw}.ovr-video-close{top:12px;right:12px}}' +
            '.ovr-cal-day{cursor:pointer;transition:background 150ms,color 150ms}' +
            '.ovr-cal-day.is-past,.ovr-cal-day.is-blocked{cursor:not-allowed}' +
            '.ovr-cal-day:not(.is-past):not(.is-blocked):hover{background:var(--ovr-primary-fixed-dim);color:var(--ovr-on-primary-fixed)}' +
            '.ovr-cal-day.is-range-start,.ovr-cal-day.is-range-end{background:var(--ovr-primary)!important;color:var(--ovr-on-primary)!important;font-weight:700}' +
            '.ovr-cal-day.is-range-mid{background:var(--ovr-primary-container);color:var(--ovr-on-primary-container)}';
        document.head.appendChild(style);
    }

    /* ====================================================================
       7. SINGLE-PROPERTY MAP (Leaflet thumb-tack; approximate location)
       ==================================================================== */

    function initSingleMap() {
        var el = document.getElementById('ovr-detail-map');
        if (!el) return;
        if (!window.L) {
            // Leaflet may still be loading (async/late enqueue). Retry briefly.
            setTimeout(initSingleMap, 300);
            return;
        }
        if (el.dataset.ovrMapReady) return;
        el.dataset.ovrMapReady = '1';

        var lat = parseFloat(el.getAttribute('data-lat'));
        var lng = parseFloat(el.getAttribute('data-lng'));
        var radius = Math.max(0, parseInt(el.getAttribute('data-radius') || '0', 10) || 0);
        if (isNaN(lat) || isNaN(lng)) return;

        var mapEl = document.createElement('div');
        mapEl.className = 'ovr-detail-map-canvas';
        el.appendChild(mapEl);

        // ONE deterministic initialization, and only once the container has
        // real dimensions. Creating Leaflet (and requesting tiles) against a
        // zero-size container lays tiles out for a postage stamp; later
        // growth then leaves a tiny top-left fragment plus gray void.
        // Privacy sphere uses the server-provided approximate center +
        // authoritative radius (see .ovr-privacy-circle in ovr-public.css).
        function createMap() {
            var map = window.L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lng], 16);
            var tiles = window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(map);

            // Never leave a blank box: if the tile server is unreachable
            // (blocked / offline), show a graceful note instead of emptiness.
            var tileErrors = 0;
            tiles.on('tileerror', function () {
                tileErrors++;
                if (tileErrors >= 3 && !mapEl.dataset.ovrMapFallback) {
                    mapEl.dataset.ovrMapFallback = '1';
                    var note = document.createElement('div');
                    note.className = 'ovr-detail-map-fallback';
                    note.textContent = 'Map unavailable at the moment. Please see the location details below.';
                    mapEl.appendChild(note);
                }
            });

            var circle = null;
            if (radius > 0) {
                circle = window.L.circle([lat, lng], {
                    radius: radius,
                    className: 'ovr-privacy-circle',
                    color: '#5f6368',
                    fillColor: '#5f6368',
                    fillOpacity: 0.2,
                    weight: 2,
                    opacity: 0.85
                }).addTo(map);
                try {
                    // Small pad: the privacy circle fills a good share of
                    // the viewport height while surrounding streets stay
                    // visible. Larger radii automatically fit farther out.
                    map.fitBounds(circle.getBounds().pad(0.15), { animate: false });
                } catch (fitErr) { /* keep default view */ }
            }

            requestAnimationFrame(function () {
                map.invalidateSize({ animate: false });
            });

            // Resize only invalidates (never refits): no loops, and manual
            // user zoom/pan is never overridden.
            window.addEventListener('resize', function () { map.invalidateSize({ animate: false }); });
        }

        function waitForMapLayout(attempts) {
            var rect = mapEl.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                createMap();
                return;
            }
            if ((attempts || 0) < 40) {
                setTimeout(function () { waitForMapLayout((attempts || 0) + 1); }, 100);
            } else {
                // Layout never appeared; create anyway rather than a blank box.
                createMap();
            }
        }
        waitForMapLayout(0);
    }

    initSingleMap();

})();
