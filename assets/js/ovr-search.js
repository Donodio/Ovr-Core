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

        // Submit immediately on checkbox/radio change (no debounce — instant feel).
        form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function (input) {
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupMobileDrawer);
    } else {
        setupMobileDrawer();
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
