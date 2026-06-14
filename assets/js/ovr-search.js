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
       Reads listing coordinates from .ovr-map-view[data-ovr-map] and plots a
       marker per listing, fitting the map to all of them. Leaflet is only on
       the page when ?view=map is active (enqueued in Assets.php).
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

        // Bright, playful basemap — standard OpenStreetMap raster tiles render
        // vivid greens for parks, blue water and colored roads (far livelier
        // than the muted CartoDB pastels).
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            subdomains: 'abc',
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Stylish branded teardrop pin (CSS divIcon).
        var ovrPin = window.L.divIcon({
            className: 'ovr-map-pin',
            html: '<span class="ovr-map-pin-pin"></span>',
            iconSize: [30, 38],
            iconAnchor: [15, 34],
            popupAnchor: [0, -32]
        });

        // Cluster when the markercluster plugin is available; otherwise plain.
        var useCluster = typeof window.L.markerClusterGroup === 'function';
        var layer = useCluster
            ? window.L.markerClusterGroup({
                showCoverageOnHover: false,
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                chunkedLoading: true
            })
            : window.L.layerGroup();

        var byId   = {};   // point id -> marker
        var bounds = [];

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
            // point (e.g. a longitude that lost its decimal) would otherwise
            // stretch fitBounds across the whole globe and shrink the map.
            if (isNaN(lat) || isNaN(lng)) return;
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return;
            var id = String(p.id);
            var marker = window.L.marker([lat, lng], { icon: ovrPin });
            marker.bindPopup(buildPopup(p, symbol));
            marker.on('click', function () { highlightCard(id); });
            byId[id] = marker;
            layer.addLayer(marker);
            bounds.push([lat, lng]);
        });

        map.addLayer(layer);

        if (bounds.length === 1) {
            map.setView(bounds[0], 14);
        } else if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [40, 40] });
        } else {
            map.setView([0, 0], 2); // markers all had invalid coords (defensive)
        }

        // Tiles can render at the wrong size if the container was measured
        // before layout settled; nudge Leaflet once things are stable.
        setTimeout(function () { map.invalidateSize(); }, 200);

        // Card → pin: hover highlights the pin, click focuses & opens it.
        function focusMarker(id) {
            var marker = byId[id];
            if (!marker) return;
            if (useCluster && typeof layer.zoomToShowLayer === 'function') {
                layer.zoomToShowLayer(marker, function () { marker.openPopup(); });
            } else {
                map.panTo(marker.getLatLng());
                marker.openPopup();
            }
        }

        if (listcol) {
            var wraps = listcol.querySelectorAll('.ovr-map-cardwrap');
            Array.prototype.forEach.call(wraps, function (w) {
                var id = w.getAttribute('data-ovr-card-id');
                w.addEventListener('mouseenter', function () {
                    var m = byId[id]; if (m && m._icon) m._icon.classList.add('is-hover');
                });
                w.addEventListener('mouseleave', function () {
                    var m = byId[id]; if (m && m._icon) m._icon.classList.remove('is-hover');
                });
                w.addEventListener('click', function (e) {
                    if (e.target.closest && e.target.closest('a')) return; // let card links work
                    focusMarker(id);
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

    function initSearchUI() {
        setupMobileDrawer();
        setupSearchShell();
        setupMap();
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
