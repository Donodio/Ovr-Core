(function () {
    'use strict';

    const config = window.ovrFilterTable || {};
    if (!config.ajaxUrl || !config.action) {
        return;
    }

    let tableWrap = null;
    let filterForm = null;
    let tbody = null;
    let paginationTop = null;
    let paginationBottom = null;
    let debounceTimer = null;
    let currentRequest = null;
    let skipHistoryPush = false;

    const DEBOUNCE_MS = 350;

    document.addEventListener('DOMContentLoaded', init);
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) init();
    });

    function init() {
        tableWrap = document.getElementById('ovr-filter-table-wrap') ||
                    document.querySelector('.ovr-ft-wrap');
        if (!tableWrap) return;

        filterForm = tableWrap.querySelector('.ovr-ft-filters') ||
                     tableWrap.querySelector('.ovr-filters-row');
        if (!filterForm) return;

        tbody = tableWrap.querySelector('tbody');
        paginationTop = tableWrap.querySelector('.tablenav.top .tablenav-pages');
        paginationBottom = tableWrap.querySelector('.tablenav.bottom .tablenav-pages') ||
                           tableWrap.querySelector('.ovr-ft-nav .tablenav-pages');

        bindFilterInputs();
        bindPagination();
        bindSortableHeaders();
        bindClearButtons();
        bindBulkActions();
        bindSearchInput();

        window.addEventListener('popstate', handlePopState);

        restoreFromQueryString();
    }

    function collectFilters() {
        const data = {};

        filterForm.querySelectorAll('[data-filter-key]').forEach(function (el) {
            const key = el.getAttribute('data-filter-key');
            if (!key) return;

            if (el.matches('select')) {
                if (el.multiple) {
                    const selected = Array.from(el.selectedOptions).map(function (o) { return o.value; });
                    if (selected.length) data[key] = selected;
                } else {
                    if (el.value !== '') data[key] = el.value;
                }
            } else if (el.matches('input[type="text"]')) {
                if (el.value.trim() !== '') data[key] = el.value.trim();
            }
        });

        filterForm.querySelectorAll('.ovr-ft-numeric, .ovr-ft-date-group').forEach(function (group) {
            const key = group.getAttribute('data-filter-key');
            if (!key) return;

            if (group.classList.contains('ovr-ft-numeric')) {
                const opEl = group.querySelector('.ovr-ft-num-op');
                const valEl = group.querySelector('.ovr-ft-num-val');
                const val2El = group.querySelector('.ovr-ft-num-val2');
                const op = opEl ? opEl.value : '=';
                const val = valEl ? valEl.value : '';
                const val2 = val2El ? val2El.value : '';
                if (val !== '' || (op === 'bt' && val2 !== '')) {
                    data[key] = { op: op, val: val, val2: val2 };
                }
            } else if (group.classList.contains('ovr-ft-date-group')) {
                const onEl = group.querySelector('.ovr-ft-date-on');
                if (onEl) {
                    // Single-date mode.
                    if (onEl.value !== '') data[key] = { on: onEl.value };
                } else {
                    const fromEl = group.querySelector('.ovr-ft-date-from');
                    const toEl = group.querySelector('.ovr-ft-date-to');
                    const from = fromEl ? fromEl.value : '';
                    const to = toEl ? toEl.value : '';
                    if (from !== '' || to !== '') {
                        data[key] = { from: from, to: to };
                    }
                }
            }
        });

        return data;
    }

    function collectParams(page, orderby, order, search) {
        const params = new URLSearchParams();
        params.set('action', config.action);
        params.set('nonce', config.nonce || '');
        params.set('paged', String(page));

        if (orderby) params.set('orderby', orderby);
        if (order) params.set('order', order);

        if (search) {
            params.set('s', search);
        }

        const filters = collectFilters();
        if (Object.keys(filters).length) {
            params.set('ovr_filters', JSON.stringify(filters));
        }

        return params;
    }

    function fetchTable(page, orderby, order, search) {
        if (currentRequest) {
            currentRequest.abort();
        }
        currentRequest = new AbortController();

        const params = collectParams(page, orderby, order, search);

        tableWrap.classList.add('ovr-loading');

        fetch(config.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString(),
            signal: currentRequest.signal,
        })
            .then(function (r) { return r.json(); })
            .then(function (response) {
                currentRequest = null;
                tableWrap.classList.remove('ovr-loading');

                if (!response.success) {
                    console.error('OVR Filter Table: AJAX error', response);
                    return;
                }

                var data = response.data;
                // Presence checks, not truthiness: a filter that matches nothing
                // returns rows === '' (falsy), which previously skipped the update
                // and left the PREVIOUS, unfiltered rows on screen — the admin saw
                // the full dataset and no "no results" indication.
                if (tbody && typeof data.rows === 'string') {
                    tbody.innerHTML = data.rows;
                }
                if (paginationTop && typeof data.pagination === 'string') {
                    paginationTop.innerHTML = data.pagination;
                }
                if (paginationBottom && typeof data.pagination === 'string') {
                    paginationBottom.innerHTML = data.pagination;
                }

                bindPagination();
                bindSortableHeaders();

                if (!skipHistoryPush) {
                    updateHistory(page, orderby, order, search);
                }
                skipHistoryPush = false;
            })
            .catch(function (err) {
                currentRequest = null;
                tableWrap.classList.remove('ovr-loading');
                if (err.name !== 'AbortError') {
                    console.error('OVR Filter Table: fetch error', err);
                }
            });
    }

    function debouncedFetch(page, orderby, order, search) {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(function () {
            debounceTimer = null;
            fetchTable(page, orderby, order, search);
        }, DEBOUNCE_MS);
    }

    function bindFilterInputs() {
        filterForm.querySelectorAll('input[type="text"], select, input[type="number"]').forEach(function (el) {
            el.addEventListener('change', triggerFilter);
            if (el.matches('input[type="text"]')) {
                el.addEventListener('input', triggerDebouncedFilter);
            }
        });

        filterForm.querySelectorAll('input[type="date"]').forEach(function (el) {
            el.addEventListener('change', triggerFilter);
        });
    }

    function triggerFilter() {
        fetchTable(1, getCurrentOrderby(), getCurrentOrder(), getCurrentSearch());
    }

    function triggerDebouncedFilter() {
        debouncedFetch(1, getCurrentOrderby(), getCurrentOrder(), getCurrentSearch());
    }

    function bindClearButtons() {
        filterForm.addEventListener('click', function (e) {
            var btn = e.target.closest('.ovr-ft-clear');
            if (!btn) return;
            var key = btn.getAttribute('data-clear');
            if (!key) return;
            var input = filterForm.querySelector('[data-filter-key="' + key + '"]');
            if (input) {
                input.value = '';
                triggerFilter();
            }
        });
    }

    function bindPagination() {
        tableWrap.querySelectorAll('.pagination-links a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var page = parseInt(link.getAttribute('data-page'), 10);
                if (isNaN(page)) return;
                fetchTable(page, getCurrentOrderby(), getCurrentOrder(), getCurrentSearch());
            });
        });

        var pageInput = tableWrap.querySelector('.current-page');
        if (pageInput) {
            pageInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var page = parseInt(pageInput.value, 10);
                    if (isNaN(page) || page < 1) return;
                    var max = parseInt(pageInput.closest('.paging-input').querySelector('.total-pages').textContent, 10);
                    if (max && page > max) page = max;
                    fetchTable(page, getCurrentOrderby(), getCurrentOrder(), getCurrentSearch());
                }
            });
        }
    }

    function bindSortableHeaders() {
        tableWrap.querySelectorAll('.sortable a, .sorted a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var th = link.closest('th');
                if (!th) return;
                var orderby = th.getAttribute('data-orderby') || '';
                var currentOrder = th.classList.contains('asc') ? 'DESC' : (th.classList.contains('desc') ? 'ASC' : 'ASC');
                fetchTable(1, orderby, currentOrder, getCurrentSearch());
            });
        });
    }

    function bindBulkActions() {
        var checkAll = tableWrap.querySelector('.check-column input[type="checkbox"]');
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                tableWrap.querySelectorAll('tbody .check-column input[type="checkbox"]').forEach(function (cb) {
                    cb.checked = checkAll.checked;
                });
            });
        }

        tableWrap.querySelectorAll('.bulkactions .button.action').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var select = btn.closest('.bulkactions').querySelector('select');
                if (!select || !select.value) return;
                var ids = [];
                tableWrap.querySelectorAll('tbody .check-column input[type="checkbox"]:checked').forEach(function (cb) {
                    ids.push(cb.value);
                });
                if (!ids.length) {
                    alert('Please select items.');
                    return;
                }
                var event = new CustomEvent('ovr_bulk_action', {
                    detail: { action: select.value, ids: ids, screenId: config.screenId },
                });
                document.dispatchEvent(event);
            });
        });
    }

    function bindSearchInput() {
        var searchInput = tableWrap.querySelector('.ovr-table-search input[type="search"]');
        if (!searchInput) return;

        searchInput.addEventListener('input', function () {
            debouncedFetch(1, getCurrentOrderby(), getCurrentOrder(), searchInput.value);
        });

        var searchBtn = tableWrap.querySelector('.ovr-table-search .button');
        if (searchBtn) {
            searchBtn.addEventListener('click', function (e) {
                e.preventDefault();
                fetchTable(1, getCurrentOrderby(), getCurrentOrder(), searchInput.value);
            });
        }
    }

    function updateHistory(page, orderby, order, search) {
        var url = new URL(window.location.href);
        var params = url.searchParams;
        // Drop the params this filter table manages so the URL stays clean,
        // but KEEP identifying/navigation params (post_type, page, author, …).
        // Previously the URL was rebuilt from pathname + filters only, which
        // silently dropped the screen's `page`/`post_type` — so bookmarked or
        // shared URLs stopped opening the Properties screen.
        ['s', 'paged', 'orderby', 'order', 'ovr_filters'].forEach(function (k) {
            params.delete(k);
        });

        var filters = collectFilters();
        if (Object.keys(filters).length) {
            params.set('ovr_filters', JSON.stringify(filters));
        }
        if (page > 1) params.set('paged', String(page));
        if (orderby) params.set('orderby', orderby);
        if (order && order !== 'ASC') params.set('order', order);
        if (search) params.set('s', search);

        window.history.pushState({ ovrFilters: true }, '', url.pathname + '?' + params.toString());
    }

    function handlePopState() {
        skipHistoryPush = true;
        restoreFromQueryString();
    }

    function restoreFromQueryString() {
        var params = new URLSearchParams(window.location.search);
        var filtersRaw = params.get('ovr_filters');
        if (filtersRaw) {
            try {
                var filters = JSON.parse(filtersRaw);
                Object.keys(filters).forEach(function (key) {
                    var value = filters[key];
                    var el = filterForm.querySelector('[data-filter-key="' + key + '"]');
                    if (el) {
                        if (typeof value === 'object' && !Array.isArray(value)) {
                            var group = filterForm.querySelector('[data-filter-key="' + key + '"].ovr-ft-numeric, [data-filter-key="' + key + '"].ovr-ft-date-group');
                            if (value.on !== undefined) {
                                var onEl = filterForm.querySelector('[data-filter-key="' + key + '"] .ovr-ft-date-on');
                                if (onEl) onEl.value = value.on || '';
                            } else if (value.from !== undefined || value.to !== undefined) {
                                var fromEl = filterForm.querySelector('.ovr-ft-date-from');
                                var toEl = filterForm.querySelector('.ovr-ft-date-to');
                                if (fromEl) fromEl.value = value.from || '';
                                if (toEl) toEl.value = value.to || '';
                            } else if (value.op !== undefined) {
                                var opEl = filterForm.querySelector('.ovr-ft-num-op');
                                var valEl = filterForm.querySelector('.ovr-ft-num-val');
                                var val2El = filterForm.querySelector('.ovr-ft-num-val2');
                                if (opEl) opEl.value = value.op || '=';
                                if (valEl) valEl.value = value.val || '';
                                if (val2El) val2El.value = value.val2 || '';
                            }
                        } else {
                            el.value = value;
                        }
                    }
                });
            } catch (e) {
            }
        }

        var page = parseInt(params.get('paged'), 10) || 1;
        var orderby = params.get('orderby') || '';
        var order = params.get('order') || '';
        var search = params.get('s') || '';

        var searchInput = tableWrap.querySelector('.ovr-table-search input[type="search"]');
        if (searchInput && search) {
            searchInput.value = search;
        }

        fetchTable(page, orderby, order, search);
    }

    function getCurrentOrderby() {
        var sorted = tableWrap.querySelector('th.sorted');
        return sorted ? (sorted.getAttribute('data-orderby') || '') : '';
    }

    function getCurrentOrder() {
        var sorted = tableWrap.querySelector('th.sorted');
        if (!sorted) return 'ASC';
        return sorted.classList.contains('asc') ? 'ASC' : 'DESC';
    }

    function getCurrentSearch() {
        var input = tableWrap.querySelector('.ovr-table-search input[type="search"]');
        return input ? input.value : '';
    }
})();