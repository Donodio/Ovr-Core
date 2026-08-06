(function () {
    'use strict';

    var pls = window.ovrPls || {};
    if (!pls.ajaxUrl || !pls.nonce) return;

    var doc = document;
    var modalVisible = false;

    /* ---- Duplicate Listing (row action) ----
     * Duplicates the property server-side via the admin action the duplicate
     * nonce was created for, then reloads the list so the new copy shows up.
     */
    doc.addEventListener('click', function (e) {
        var btn = e.target.closest('.ovr-pls-act--dup');
        if (!btn) return;
        e.preventDefault();
        if (!confirm('Duplicate this listing?')) return;

        var pid = btn.getAttribute('data-pid');
        var nonce = btn.getAttribute('data-nonce');
        if (!pid || !nonce) return;

        btn.disabled = true;

        fetch(pls.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'ovr_admin_duplicate_property',
                nonce: nonce,
                listing_id: pid,
            }).toString(),
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                btn.disabled = false;
                if (resp.success) {
                    refreshTable();
                    if (resp.data && resp.data.message) alert(resp.data.message);
                } else {
                    alert(resp.data && resp.data.message ? resp.data.message : 'Could not duplicate this listing.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                alert('Network error.');
            });
    });

    /* ---- Copy Property ID ---- */
    doc.addEventListener('click', function (e) {
        var btn = e.target.closest('.ovr-pls-copy-id');
        if (!btn) return;

        var idEl = btn.parentElement.querySelector('.ovr-pls-pid');
        if (!idEl) return;

        var text = idEl.textContent.trim();
        if (!text) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(function () {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }

        var orig = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px">check</span>';
        setTimeout(function () { btn.innerHTML = orig; }, 1500);
    });

    function fallbackCopy(text) {
        var ta = doc.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        doc.body.appendChild(ta);
        ta.select();
        try { doc.execCommand('copy'); } catch (err) {}
        doc.body.removeChild(ta);
    }

    /* ---- Service Modal ---- */
    doc.addEventListener('click', function (e) {
        var btn = e.target.closest('.ovr-pls-svc-add');
        if (!btn) return;
        e.preventDefault();
        var listingId = btn.getAttribute('data-listing-id');
        if (listingId) openServiceModal(listingId);
    });

    function openServiceModal(listingId) {
        var el = doc.getElementById('ovr-pls-service-modal');
        if (!el) return;

        el.style.display = 'block';
        modalVisible = true;

        var pidInput = doc.getElementById('ovr-pls-svc-listing-id');
        if (pidInput) pidInput.value = listingId;

        var select = doc.getElementById('ovr-pls-svc-select');
        if (select) select.value = '';

        var start = doc.getElementById('ovr-pls-svc-start');
        if (start) start.value = pls.today || '';

        var end = doc.getElementById('ovr-pls-svc-end');
        if (end) end.value = '';

        var notes = doc.getElementById('ovr-pls-svc-notes');
        if (notes) notes.value = '';
    }

    function closeServiceModal() {
        var el = doc.getElementById('ovr-pls-service-modal');
        if (el) el.style.display = 'none';
        modalVisible = false;
    }

    doc.addEventListener('click', function (e) {
        if (e.target.closest('.ovr-pls-modal-close') || e.target.closest('.ovr-pls-modal-backdrop')) {
            closeServiceModal();
        }
    });

    doc.addEventListener('click', function (e) {
        var btn = e.target.closest('#ovr-pls-svc-save');
        if (!btn) return;

        var listingId = doc.getElementById('ovr-pls-svc-listing-id');
        var serviceId = doc.getElementById('ovr-pls-svc-select');
        var startDate = doc.getElementById('ovr-pls-svc-start');
        var endDate   = doc.getElementById('ovr-pls-svc-end');
        var notes     = doc.getElementById('ovr-pls-svc-notes');

        if (!listingId || !serviceId || !serviceId.value) {
            alert('Please select a service.');
            return;
        }

        var data = {
            action: 'ovr_admin_add_listing_service',
            nonce: pls.nonce,
            listing_id: listingId.value,
            service_id: serviceId.value,
            start_date: startDate ? startDate.value : '',
            end_date: endDate ? endDate.value : '',
            notes: notes ? notes.value : '',
        };

        btn.disabled = true;

        fetch(pls.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data).toString(),
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                btn.disabled = false;
                if (resp.success) {
                    closeServiceModal();
                    refreshTable();
                } else {
                    alert(resp.data && resp.data.message ? resp.data.message : 'Error adding service.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                alert('Network error.');
            });
    });

    /* ---- Bulk Actions ----
     * THIS is the authoritative bulk handler for the "All Properties" screen
     * (admin.php?page=ovr-properties, rendered by PropertyListScreen + FilterTable).
     * The similar inline script in templates/admin/property-list.php belongs to a
     * DIFFERENT screen and is not enqueued here — verified in the browser: on this
     * page only ovr-pls.js is loaded. Do not remove this block; doing so leaves the
     * Apply button completely inert (0 confirms, 0 requests).
     */
    doc.addEventListener('click', function (e) {
        var btn = e.target.closest('#ovr-pls-bulk-apply');
        if (!btn) return;

        var select = doc.getElementById('ovr-pls-bulk-action');
        if (!select || !select.value) return;

        // Only real row checkboxes: a select-all toggle carries value "on", which
        // must never be posted as a listing id.
        var ids = Array.from(doc.querySelectorAll('.ovr-pls-cb:checked'))
            .map(function (cb) { return cb.value; })
            .filter(function (v) { return /^\d+$/.test(v); });

        if (!ids.length) {
            alert('Please select properties.');
            return;
        }

        // 'delete' calls wp_trash_post() server-side, so it is reversible.
        if (select.value === 'delete' && !confirm('Move selected listings to trash?')) {
            return;
        }

        // listing_ids must be posted as a repeated listing_ids[] array; passing the
        // array through URLSearchParams once flattened it to "1,2,3".
        var body = new URLSearchParams();
        body.set('action', 'ovr_admin_bulk_action');
        body.set('nonce', pls.nonce);
        body.set('bulk_action', select.value);
        ids.forEach(function (id) { body.append('listing_ids[]', id); });

        btn.disabled = true;

        fetch(pls.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                btn.disabled = false;
                if (resp.success) {
                    refreshTable();
                    if (resp.data && resp.data.message) alert(resp.data.message);
                } else {
                    alert(resp.data && resp.data.message ? resp.data.message : 'Bulk action failed.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                alert('Network error.');
            });
    });

    /* ---- Check All ---- */
    doc.addEventListener('change', function (e) {
        var cb = e.target.closest('#ovr-pls-cb-all');
        if (!cb) return;
        doc.querySelectorAll('.ovr-pls-cb').forEach(function (c) { c.checked = cb.checked; });
    });

    /* ---- Reset Filters ---- */
    doc.addEventListener('click', function (e) {
        var btn = e.target.closest('#ovr-pls-reset-filters');
        if (!btn) return;
        e.preventDefault();
        window.location.href = window.location.pathname + '?page=ovr-properties';
    });

    /* ---- Refresh Table After External Change ---- */
    function refreshTable() {
        var wrap = doc.querySelector('.ovr-ft-wrap') || doc.getElementById('ovr-filter-table-wrap');
        if (!wrap) { window.location.reload(); return; }
        var filterRow = wrap.querySelector('.ovr-ft-filters, .ovr-filters-row');
        if (!filterRow) { window.location.reload(); return; }
        var firstFilter = filterRow.querySelector('input[type="text"], select, input[type="number"]');
        if (firstFilter) {
            firstFilter.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            window.location.reload();
        }
    }

    /* ---- Keyboard: Escape closes modal ---- */
    doc.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modalVisible) closeServiceModal();
    });
})();