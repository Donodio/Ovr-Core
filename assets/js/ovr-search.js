/**
 * OVR Core — Search & Filter JS
 *
 * Wires the filters sidebar (templates/search/filters-sidebar.php) and the
 * results page interactions:
 *
 *   1. Auto-submit filter form on checkbox/radio/select change (debounced)
 *   2. Auto-submit on price-range numeric input (debounced)
 *   3. Mobile: filter drawer open/close
 *   4. Sort select already auto-redirects via inline onchange in results.php
 *
 * @package OVR
 */
(function () {
    'use strict';

    var FORM_ID  = 'ovr-filters-form';
    var DEBOUNCE = 600;

    var debounceTimers = {};
    function debounce(key, fn, wait) {
        clearTimeout(debounceTimers[key]);
        debounceTimers[key] = setTimeout(fn, wait);
    }

    var form = document.getElementById(FORM_ID);

    if (form) {
        // Reset paged param on filter change so users go back to page 1.
        function resetPaged() {
            var existing = form.querySelector('input[name="paged"]');
            if (existing) existing.value = '1';
        }

        // Submit immediately on checkbox/radio change (no debounce — instant feel),
        // EXCEPT the multi-select facet checkboxes (.ovr-mf-check): those let the
        // user tick several values and submit once via the "Apply Filters" button.
        form.querySelectorAll('input[type="checkbox"]:not(.ovr-mf-check), input[type="radio"]').forEach(function (input) {
            input.addEventListener('change', function () {
                resetPaged();
                form.submit();
            });
        });

        // Submit any <select> changes inside the filter form.
        form.querySelectorAll('select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                resetPaged();
                form.submit();
            });
        });

        // Numeric inputs (price min/max) — debounce so users can finish typing.
        form.querySelectorAll('input[type="number"]').forEach(function (input) {
            input.addEventListener('input', function () {
                debounce('price-' + input.name, function () {
                    resetPaged();
                    form.submit();
                }, DEBOUNCE);
            });
            // Also submit on blur for keyboard/tab users.
            input.addEventListener('change', function () {
                resetPaged();
                form.submit();
            });
        });
    }

    /* ====================================================================
       MOBILE FILTER DRAWER
       ==================================================================== */

    // Inject a mobile "Filters" trigger button at the top of the results layout
    // when the screen is narrow. Sidebar slides in from the left.
    function setupMobileDrawer() {
        var sidebar = document.querySelector('.ovr-filters-sidebar');
        var layout  = document.querySelector('.ovr-search-layout');
        if (!sidebar || !layout) return;

        // Avoid double-injection on re-runs.
        if (document.querySelector('[data-ovr-mobile-filter-trigger]')) return;

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'ovr-btn ovr-btn-outline ovr-mobile-filter-trigger';
        trigger.setAttribute('data-ovr-mobile-filter-trigger', '');
        trigger.innerHTML = '<span class="material-symbols-outlined">tune</span> Filters';
        trigger.style.cssText = 'display:none;margin-bottom:16px;width:100%';

        // Insert before the results column (second child in the grid layout).
        var resultsCol = sidebar.nextElementSibling;
        if (resultsCol) {
            resultsCol.insertBefore(trigger, resultsCol.firstChild);
        }

        trigger.addEventListener('click', function () {
            sidebar.classList.toggle('is-mobile-open');
            document.body.style.overflow = sidebar.classList.contains('is-mobile-open') ? 'hidden' : '';
        });

        // Close on backdrop tap (overlay generated below).
        sidebar.addEventListener('click', function (e) {
            if (e.target === sidebar && sidebar.classList.contains('is-mobile-open')) {
                sidebar.classList.remove('is-mobile-open');
                document.body.style.overflow = '';
            }
        });

        // Inject mobile-only styles once.
        var styleId = 'ovr-search-mobile-styles';
        if (!document.getElementById(styleId)) {
            var style = document.createElement('style');
            style.id = styleId;
            style.textContent =
                '@media (max-width:1024px){' +
                    '.ovr-mobile-filter-trigger{display:flex!important}' +
                    '.ovr-filters-sidebar{position:fixed!important;top:0;left:0;height:100vh;width:340px;max-width:90vw;z-index:1100;transform:translateX(-100%);transition:transform 250ms ease;overflow-y:auto;border-radius:0;box-shadow:0 0 40px rgba(0,0,0,0.2)}' +
                    '.ovr-filters-sidebar.is-mobile-open{transform:translateX(0)}' +
                    '.ovr-filters-sidebar.is-mobile-open::before{content:"";position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:-1;left:340px}' +
                '}';
            document.head.appendChild(style);
        }
    }

    /* ====================================================================
       SEARCH "APP-SHELL" SCROLL (desktop ≥1025px)
       The CSS pins .ovr-search-page to fill the viewport once the navy
       Featured-cities bar scrolls away, then each column scrolls on its own.
       Here we just measure the height to leave at the top — the sticky site
       header (and the WP admin bar, if present) — and expose it to the CSS as
       --ss-shell-top so the pinned area sits flush beneath the header.
       ==================================================================== */
    function setupSearchShell() {
        var root = document.querySelector('.ovr-search-stitch');
        if (!root) return;
        var mq = window.matchMedia('(min-width: 1025px)');

        function update() {
            if (!mq.matches) {
                root.style.removeProperty('--ss-shell-top');
                return;
            }
            var top = 0;
            // Theme header is `position: sticky; top: 0` (Tailwind "sticky" class).
            var header = document.querySelector('header.sticky');
            if (header) top += header.offsetHeight;
            // Logged-in admin bar is fixed at the top and overlays content.
            var adminBar = document.getElementById('wpadminbar');
            if (adminBar && window.getComputedStyle(adminBar).position === 'fixed') {
                top += adminBar.offsetHeight;
            }
            root.style.setProperty('--ss-shell-top', top + 'px');
        }

        update();
        window.addEventListener('resize', update);
        window.addEventListener('load', update);
        if (mq.addEventListener) {
            mq.addEventListener('change', update);
        } else if (mq.addListener) {
            mq.addListener(update); // older Safari
        }
    }

    /* ====================================================================
       MAP VIEW (Leaflet / OpenStreetMap)
       Reads listing data from .ovr-map-view[data-ovr-map] and plots each
       listing as a PRIVACY-SAFE approximate-area circle (Chunk 1 §27-§35) —
       never an exact house pin. The server has already replaced exact
       coordinates with a deterministic circle center + radius; this file only
       ever sees the approximation. Leaflet is only on the page when ?view=map
       is active (enqueued in Assets.php).
       ==================================================================== */
    function setupMap() {
        var el = document.querySelector('.ovr-map-view');
        if (!el || typeof window.L === 'undefined') return;

        var points;
        try {
            points = JSON.parse(el.getAttribute('data-ovr-map') || '[]');
        } catch (e) {
            points = [];
        }
        // No mapped listings → leave the CSS empty-state message in place,
        // but still wire the mobile Map/List switch.
        if (!points.length) { setupMapSwitch(); return; }

        var symbol = el.getAttribute('data-symbol') || '$';

        var map = window.L.map(el, { scrollWheelZoom: true, zoomControl: true });
        // Remember the instance so an AJAX region swap can remove it cleanly
        // before the DOM it lives on is replaced (otherwise Leaflet leaks its
        // tile/event bindings on the orphaned node).
        el.__ovrMap = map;

        // Bright, playful basemap — standard OpenStreetMap raster tiles render
        // vivid greens for parks, blue water and colored roads (far livelier
        // than the muted CartoDB pastels).
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            subdomains: 'abc',
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Approximate-area circle per listing. Styling mirrors the old pin
        // palette via per-property-type classes; .is-featured adds a gold ring,
        // .is-booked dims listings unavailable tonight.
        function circleFor(p) {
            var type = (p.type || 'default').toString().replace(/[^a-z0-9_-]/gi, '');
            var classes = 'ovr-map-circle ovr-map-circle--type-' + type;
            if (p.featured) classes += ' is-featured';
            if (p.avail === 'booked') classes += ' is-booked';
            return window.L.circle([parseFloat(p.lat), parseFloat(p.lng)], {
                radius: Math.max(50, parseInt(p.radius, 10) || 150),
                className: classes,
                bubblingMouseEvents: false
            });
        }

        var layer = window.L.layerGroup();

        var byId   = {};   // point id -> circle
        var bounds = null; // running union of every circle's bounds

        var listcol = document.querySelector('.ovr-map-listcol');

        function highlightCard(id) {
            if (!listcol) return;
            var cards = listcol.querySelectorAll('.ovr-map-cardwrap');
            Array.prototype.forEach.call(cards, function (c) {
                c.classList.toggle('is-active', c.getAttribute('data-ovr-card-id') === id);
            });
            var active = listcol.querySelector('.ovr-map-cardwrap[data-ovr-card-id="' + id + '"]');
            if (active) active.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        points.forEach(function (p) {
            var lat = parseFloat(p.lat);
            var lng = parseFloat(p.lng);
            // Guard against missing or out-of-range coordinates. A single bad
            // point would otherwise stretch fitBounds across the whole globe.
            if (isNaN(lat) || isNaN(lng)) return;
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return;
            var id = String(p.id);
            var area = circleFor(p);
            area.bindPopup(buildPopup(p, symbol));
            area.on('click', function () { highlightCard(id); trackMap('marker_click'); });
            area.on('popupopen', function () { trackMap('popup_view'); });
            byId[id] = area;
            area.addTo(layer);
            var b = area.getBounds();
            bounds = bounds ? bounds.extend(b) : b;
        });

        map.addLayer(layer);
        trackMap('map_view');
        addLegend(map);

        if (bounds && bounds.isValid()) {
            map.fitBounds(bounds, { padding: [40, 40] });
        } else {
            map.setView([28.85, -81.95], 11); // nothing plottable (defensive): The Villages area
        }

        // Tiles can render at the wrong size if the container was measured
        // before layout settled; nudge Leaflet once things are stable.
        setTimeout(function () { map.invalidateSize(); }, 200);

        // Card → circle: hover highlights the area, click focuses & opens it.
        function focusArea(id) {
            var area = byId[id];
            if (!area) return;
            map.fitBounds(area.getBounds().pad(4), { maxZoom: 17 });
            setTimeout(function () { area.openPopup(); }, 220);
        }

        if (listcol) {
            var wraps = listcol.querySelectorAll('.ovr-map-cardwrap');
            Array.prototype.forEach.call(wraps, function (w) {
                var id = w.getAttribute('data-ovr-card-id');
                w.addEventListener('mouseenter', function () {
                    var m = byId[id];
                    if (m && m._path) m._path.classList.add('is-hover');
                });
                w.addEventListener('mouseleave', function () {
                    var m = byId[id];
                    if (m && m._path) m._path.classList.remove('is-hover');
                });
                w.addEventListener('click', function (e) {
                    if (e.target.closest && e.target.closest('a')) return; // let card links work
                    focusArea(id);
                });
            });
        }

        setupMapSwitch(function () { map.invalidateSize(); });
    }

    /* Mobile-only Map/List toggle. On desktop both panes show and the switch
       is hidden via CSS; on small screens it flips between them. */
    function setupMapSwitch(onShowMap) {
        var split = document.querySelector('[data-ovr-map-split]');
        if (!split || split.getAttribute('data-switch-ready') === '1') return;
        var btns = split.querySelectorAll('.ovr-map-switch-btn');
        if (!btns.length) return;
        split.setAttribute('data-switch-ready', '1');

        Array.prototype.forEach.call(btns, function (btn) {
            btn.addEventListener('click', function () {
                var show = btn.getAttribute('data-show');
                split.classList.toggle('show-map', show === 'map');
                split.classList.toggle('show-list', show === 'list');
                Array.prototype.forEach.call(btns, function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                if (show === 'map' && typeof onShowMap === 'function') {
                    setTimeout(onShowMap, 60);
                }
            });
        });
    }

    function buildPopup(p, symbol) {
        var price = p.price > 0
            ? symbol + Number(p.price).toLocaleString() + ' / night'
            : 'Seasonal Rates';
        var specs = [];
        if (p.beds)  specs.push(p.beds + ' bd');
        if (p.baths) specs.push(p.baths + ' ba');

        var html = '<div class="ovr-map-popup">';
        if (p.thumb) {
            html += '<img src="' + encodeURI(p.thumb) + '" alt="">';
        }
        html += '<div class="ovr-map-popup-body">';
        html += '<div class="ovr-map-popup-title">' + escapeHtml(p.title) + '</div>';
        if (specs.length) {
            html += '<div class="ovr-map-popup-specs">' + escapeHtml(specs.join(' · ')) + '</div>';
        }
        html += '<div class="ovr-map-popup-price">' + escapeHtml(price) + '</div>';
        html += '<a class="ovr-map-popup-link" href="' + encodeURI(p.url) + '">View listing →</a>';
        html += '</div></div>';
        return html;
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* Map interaction analytics (M3 F10). Fires a fire-and-forget beacon to the
       ovr_map_track AJAX endpoint, which increments per-action counters. Each
       action is counted at most once per page view for views, but every marker
       interaction is sent. Silently no-ops when ovrData is unavailable. */
    var trackedOnce = {};
    function trackMap(action) {
        if (typeof window.ovrData === 'undefined' || !window.ovrData.ajaxUrl) return;
        if (action === 'map_view') {
            if (trackedOnce[action]) return;
            trackedOnce[action] = true;
        }
        var body = 'action=ovr_map_track&event=' + encodeURIComponent(action) +
            '&nonce=' + encodeURIComponent(window.ovrData.nonce || '');
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(window.ovrData.ajaxUrl, new Blob([body], { type: 'application/x-www-form-urlencoded' }));
            } else {
                fetch(window.ovrData.ajaxUrl, { method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, keepalive: true });
            }
        } catch (e) { /* analytics must never break the map */ }
    }

    /* A compact legend explaining the area colours / states. */
    function addLegend(map) {
        if (!window.L || !window.L.control) return;
        var legend = window.L.control({ position: 'bottomright' });
        legend.onAdd = function () {
            var div = window.L.DomUtil.create('div', 'ovr-map-legend');
            div.innerHTML =
                '<span class="ovr-map-legend-item"><i class="ovr-map-legend-dot is-featured"></i>Featured</span>' +
                '<span class="ovr-map-legend-item"><i class="ovr-map-legend-dot is-area"></i>Approximate area</span>' +
                '<span class="ovr-map-legend-item"><i class="ovr-map-legend-dot is-booked"></i>Booked tonight</span>';
            return div;
        };
        legend.addTo(map);
    }

    function initSearchUI() {
        setupMobileDrawer();
        setupSearchShell();
        setupMap();
        setupChipAjax();
    }

    /* ====================================================================
       VILLAGE CHIPS (AJAX, no reload)
       The "Village Section" strip at the top of the search page historically
       navigated via full page loads — every click flashed a white reload.
       Now a delegated click handler intercepts the chips and swaps in the
       freshly rendered results column (matching the chip's URL) with
       replaceState so the address bar stays shareable without reloading.
       The chips strip, the results column AND the filters sidebar are
       replaced — the sidebar is swapped in place (innerHTML) so its
       container-bound listeners survive.
       Falls back to normal navigation when ovrData (the localized Ajax
       config) is absent.
       ==================================================================== */
    function setupChipAjax() {
        var strip = document.querySelector('.ovr-ss-villages');
        if (!strip) return;
        if (typeof window.ovrData === 'undefined' || !window.ovrData.ajaxUrl) return;
        if (strip.getAttribute('data-ovr-chips-ready') === '1') return;
        strip.setAttribute('data-ovr-chips-ready', '1');

        var pending = null;

        function applyRegion(data) {
            // Parse the re-rendered region in a detached node so we can pull
            // out exactly the two panels we want to swap.
            var tmp = document.createElement('div');
            tmp.innerHTML = data.html;

            var freshStrip = tmp.querySelector('.ovr-ss-villages');
            if (freshStrip && strip) {
                strip.innerHTML = freshStrip.innerHTML;
            }

            var freshMain = tmp.querySelector('.ovr-search-main');
            var liveMain  = document.querySelector('.ovr-search-main');
            if (freshMain && liveMain) {
                // Detach any live Leaflet map before its DOM is replaced.
                var oldMapView = liveMain.querySelector('.ovr-map-view');
                if (oldMapView && oldMapView.__ovrMap && typeof oldMapView.__ovrMap.remove === 'function') {
                    oldMapView.__ovrMap.remove();
                }
                liveMain.innerHTML = freshMain.innerHTML;
            }

            // Sync the filters sidebar to the clean URL's server-rendered
            // state. Chip links intentionally drop every other filter, so the
            // fresh response contains an all-clear sidebar (checkboxes
            // unchecked, count badges zeroed, dropdown panels closed). Swap
            // IN PLACE (innerHTML, not replaceWith): the original container
            // node must survive because the mobile-drawer trigger/backdrop
            // listeners are bound to it, and document-delegated listeners
            // (golf-cart dropdown) don't care which nodes live inside.
            var freshSidebar = tmp.querySelector('.ovr-filters-sidebar');
            var liveSidebar  = document.querySelector('.ovr-filters-sidebar');
            if (freshSidebar && liveSidebar) {
                liveSidebar.innerHTML = freshSidebar.innerHTML;
            }

            if (data.url && 'function' === typeof window.history.replaceState) {
                window.history.replaceState(null, '', data.url);
            }

            // Re-run the one-time interactions that may matter again
            // (map, mobile drawer, shell measurement).
            setupMap();
            setupSearchShell();
        }

        function onChipClick(e) {
            var chip = e.target.closest('a.ovr-ss-village');
            if (!chip) return;

            var href = chip.getAttribute('href') || '';
            if (!href) return;
            // Honour open-in-new-tab / browser-extended clicks.
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

            e.preventDefault();

            // Visual "loading" feedback so the click feels instant.
            if (pending) pending.style.opacity = '';
            chip.classList.add('is-loading');
            pending = chip;

            var qs = href.indexOf('?') > -1 ? href.slice(href.indexOf('?') + 1) : '';
            // Preserve the view currently active (grid / list / map) — the chip
            // links omit it, and without it the AJAX render would jump back to
            // grid while the rest of the page stays in place.
            if (!/(^|&)view=/.test(qs)) {
                var curView = (location.search || '').match(/(?:^|&)view=([^&]+)/);
                if (curView) qs = qs.length ? qs + '&view=' + encodeURIComponent(curView[1]) : 'view=' + encodeURIComponent(curView[1]);
            }
            var body =
                'action=ovr_search_chips' +
                '&nonce=' + encodeURIComponent(window.ovrData.nonce || '') +
                '&qs=' + encodeURIComponent(qs);

            fetch(window.ovrData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success || !data.data || !data.data.html) {
                        throw new Error('bad response');
                    }
                    applyRegion(data.data);
                })
                .catch(function () {
                    // On any failure, let the original navigation happen.
                    window.location.href = href;
                })
                .then(function () {
                    if (pending) pending.classList.remove('is-loading');
                    pending = null;
                });
        }

        strip.addEventListener('click', onChipClick);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearchUI);
    } else {
        initSearchUI();
    }

    /* ====================================================================
       LEGACY: keep old #ovr-filter-form selector working for any
       callers still using the original markup.
       ==================================================================== */
    var legacyForm = document.getElementById('ovr-filter-form');
    if (legacyForm && legacyForm !== form) {
        legacyForm.querySelectorAll('select').forEach(function (sel) {
            sel.addEventListener('change', function () { legacyForm.submit(); });
        });
    }

})();
