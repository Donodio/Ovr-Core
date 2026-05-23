/**
 * OVR Core — Admin Property Edit JS
 *
 * Wires the tabbed meta box on the ovr_property edit screen:
 *
 *   1. Tab switching with deep-linking via URL hash
 *   2. wp.media gallery picker — multi-select, drag-reorder thumbnails,
 *      remove + "make primary" actions, hidden CSV input kept in sync
 *   3. Seasonal pricing repeater — add/remove rows, indexed input names
 *   4. Availability block repeater — same pattern, different fields
 *
 * @package OVR
 */
(function () {
    'use strict';

    var ovr = window.ovrAdmin || {};
    var i18n = ovr.i18n || {};

    /* ====================================================================
       1. TABS
       ==================================================================== */

    function initTabs() {
        var nav = document.querySelector('.ovr-meta-tabs__nav');
        if (!nav) return;

        var buttons = nav.querySelectorAll('.ovr-meta-tabs__btn');
        var panels  = document.querySelectorAll('.ovr-meta-tabs__panel');

        function activate(tabId, updateHash) {
            buttons.forEach(function (b) {
                b.classList.toggle('is-active', b.dataset.tab === tabId);
                b.setAttribute('aria-selected', b.dataset.tab === tabId ? 'true' : 'false');
            });
            panels.forEach(function (p) {
                p.classList.toggle('is-active', p.dataset.tab === tabId);
            });
            if (updateHash && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#ovr-tab-' + tabId);
            }
        }

        buttons.forEach(function (b) {
            b.addEventListener('click', function (e) {
                e.preventDefault();
                activate(b.dataset.tab, true);
            });
        });

        // Deep link via hash, e.g. #ovr-tab-pricing.
        var hash = (window.location.hash || '').replace(/^#ovr-tab-/, '');
        if (hash) {
            var match = nav.querySelector('[data-tab="' + hash + '"]');
            if (match) activate(hash, false);
        }
    }

    /* ====================================================================
       2. GALLERY PICKER (wp.media)
       ==================================================================== */

    function initGallery() {
        var picker = document.querySelector('[data-ovr-gallery-picker]');
        if (!picker) return;
        if (!window.wp || !window.wp.media) {
            console.warn('[OVR] wp.media not available — gallery picker disabled.');
            return;
        }

        var input  = picker.querySelector('[data-ovr-gallery-input]');
        var strip  = picker.querySelector('[data-ovr-gallery-strip]');
        var addBtn = picker.querySelector('[data-ovr-gallery-add]');

        // Defensive: every required node must exist before we wire anything.
        if (!input || !strip || !addBtn) {
            console.warn('[OVR] Gallery picker missing required nodes; skipping init.', {
                hasInput: !!input, hasStrip: !!strip, hasAddBtn: !!addBtn
            });
            return;
        }

        function setIds(arr) {
            input.value = arr.filter(function (n) { return n > 0; }).join(',');
            input.dispatchEvent(new Event('change'));
        }

        function tileHtml(att) {
            var url = '';
            if (att.sizes && att.sizes.thumbnail) url = att.sizes.thumbnail.url;
            else if (att.sizes && att.sizes.medium) url = att.sizes.medium.url;
            else url = att.url || '';

            return (
                '<div class="ovr-gallery-tile" data-id="' + att.id + '" draggable="true">' +
                    '<img src="' + url + '" alt="' + (att.alt || '') + '">' +
                    '<span class="ovr-gallery-tile__primary-badge" hidden>' + (i18n.primary || 'Primary') + '</span>' +
                    '<div class="ovr-gallery-tile__actions">' +
                        '<button type="button" class="ovr-gallery-tile__btn" data-action="primary" title="' + (i18n.makePrimary || 'Make primary') + '">' +
                            '<span class="material-symbols-outlined">star</span>' +
                        '</button>' +
                        '<button type="button" class="ovr-gallery-tile__btn" data-action="remove" title="' + (i18n.remove || 'Remove') + '">' +
                            '<span class="material-symbols-outlined">close</span>' +
                        '</button>' +
                    '</div>' +
                '</div>'
            );
        }

        function refreshPrimaryBadges() {
            var tiles = strip.querySelectorAll('.ovr-gallery-tile');
            tiles.forEach(function (t, idx) {
                t.classList.toggle('is-primary', idx === 0);
                var badge = t.querySelector('.ovr-gallery-tile__primary-badge');
                if (badge) badge.hidden = idx !== 0;
            });
        }

        function appendTile(att) {
            // Don't double-add.
            if (strip.querySelector('[data-id="' + att.id + '"]')) return;
            strip.insertAdjacentHTML('beforeend', tileHtml(att));
        }

        function syncFromDom() {
            var arr = [];
            strip.querySelectorAll('.ovr-gallery-tile').forEach(function (t) {
                arr.push(parseInt(t.dataset.id, 10));
            });
            setIds(arr);
            refreshPrimaryBadges();
        }

        // Open the media frame when "Add photos" clicked.
        var frame;
        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }

            frame = wp.media({
                title:    ovr.mediaTitle || 'Select Property Photos',
                button:   { text: ovr.mediaUse || 'Use these photos' },
                library:  { type: 'image' },
                multiple: 'add'
            });

            frame.on('select', function () {
                var selection = frame.state().get('selection');
                selection.each(function (att) {
                    appendTile(att.toJSON());
                });
                syncFromDom();
            });

            frame.open();
        });

        // Action delegation: remove & make primary.
        strip.addEventListener('click', function (e) {
            var btn = e.target.closest('.ovr-gallery-tile__btn');
            if (!btn) return;

            var tile = btn.closest('.ovr-gallery-tile');
            if (!tile) return;

            var action = btn.dataset.action;
            if ('remove' === action) {
                tile.remove();
                syncFromDom();
            } else if ('primary' === action) {
                strip.insertBefore(tile, strip.firstChild);
                syncFromDom();
            }
        });

        // Drag-reorder (HTML5 native).
        var dragged = null;
        strip.addEventListener('dragstart', function (e) {
            var t = e.target.closest('.ovr-gallery-tile');
            if (!t) return;
            dragged = t;
            t.style.opacity = '0.4';
            e.dataTransfer.effectAllowed = 'move';
        });
        strip.addEventListener('dragend', function () {
            if (dragged) dragged.style.opacity = '';
            dragged = null;
        });
        strip.addEventListener('dragover', function (e) {
            if (!dragged) return;
            e.preventDefault();
            var target = e.target.closest('.ovr-gallery-tile');
            if (!target || target === dragged) return;
            var bounding = target.getBoundingClientRect();
            var after = (e.clientX - bounding.left) > (bounding.width / 2);
            target.parentNode.insertBefore(dragged, after ? target.nextSibling : target);
        });
        strip.addEventListener('drop', function () { syncFromDom(); });

        // Initial badge state.
        refreshPrimaryBadges();
    }

    /* ====================================================================
       3. REPEATERS (seasonal pricing, availability)
       ==================================================================== */

    function initRepeaters() {
        document.querySelectorAll('[data-ovr-repeater]').forEach(function (rep) {
            var addBtn   = rep.querySelector('[data-ovr-repeater-add]');
            var template = rep.querySelector('template[data-ovr-repeater-tpl]');
            var rowsEl   = rep.querySelector('[data-ovr-repeater-rows]');
            var emptyEl  = rep.querySelector('[data-ovr-repeater-empty]');

            if (!addBtn || !template || !rowsEl) return;

            function refreshIndices() {
                var rows = rowsEl.querySelectorAll('[data-ovr-repeater-row]');
                rows.forEach(function (row, idx) {
                    row.querySelectorAll('[name*="__INDEX__"]').forEach(function (input) {
                        input.name = input.name.replace(/__INDEX__/g, idx);
                    });
                    // Subsequent renumbering: replace existing [n] with [idx].
                    row.querySelectorAll('[name]').forEach(function (input) {
                        input.name = input.name.replace(/\[(\d+)\]/, '[' + idx + ']');
                    });
                });
                if (emptyEl) emptyEl.style.display = rows.length ? 'none' : '';
            }

            addBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var clone = template.content.cloneNode(true);
                rowsEl.appendChild(clone);
                refreshIndices();
            });

            rowsEl.addEventListener('click', function (e) {
                var del = e.target.closest('[data-ovr-repeater-remove]');
                if (!del) return;
                e.preventDefault();
                var row = del.closest('[data-ovr-repeater-row]');
                if (row) row.remove();
                refreshIndices();
            });

            refreshIndices();
        });
    }

    /* ====================================================================
       4. BOOTSTRAP
       ==================================================================== */

    function init() {
        // Each step is wrapped so a single failure cannot block the others.
        try { initTabs();      } catch (e) { console.error('[OVR] initTabs failed:', e); }
        try { initGallery();   } catch (e) { console.error('[OVR] initGallery failed:', e); }
        try { initRepeaters(); } catch (e) { console.error('[OVR] initRepeaters failed:', e); }
        try { initIcalSync();  } catch (e) { console.error('[OVR] initIcalSync failed:', e); }
        try { initDocPicker(); } catch (e) { console.error('[OVR] initDocPicker failed:', e); }
    }

    /* ====================================================================
       6. DOCUMENT PICKER (capped at MAX_DOCS)
       ==================================================================== */

    function initDocPicker() {
        var picker = document.querySelector('[data-ovr-doc-picker]');
        if (!picker) return;
        if (!window.wp || !window.wp.media) return;

        var input  = picker.querySelector('[data-ovr-doc-input]');
        var list   = picker.querySelector('[data-ovr-doc-list]');
        var addBtn = picker.querySelector('[data-ovr-doc-add]');
        var max    = parseInt(picker.dataset.max || '3', 10);
        if (!input || !list || !addBtn) return;

        function setIds(arr) {
            input.value = arr.filter(Boolean).join(',');
            input.dispatchEvent(new Event('change'));
        }

        function currentIds() {
            return Array.prototype.map.call(
                list.querySelectorAll('.ovr-doc-item'),
                function (li) { return parseInt(li.dataset.id, 10); }
            ).filter(function (n) { return n > 0; });
        }

        function clearEmptyState() {
            var empty = list.querySelector('[data-ovr-doc-empty]');
            if (empty) empty.remove();
        }

        function maybeShowEmptyState() {
            if (!list.querySelector('.ovr-doc-item')) {
                list.innerHTML = '<li class="ovr-doc-empty" data-ovr-doc-empty style="color:var(--ovr-a-text-soft);font-size:13px;font-style:italic;text-align:center;padding:8px">' +
                    (i18n.noImages || 'No documents uploaded yet.') +
                    '</li>';
            }
        }

        function appendDoc(att) {
            if (list.querySelector('[data-id="' + att.id + '"]')) return;
            clearEmptyState();
            var url      = att.url || '';
            var filename = (att.filename || att.url || '').split('/').pop();
            var title    = att.title || filename;
            var html =
                '<li class="ovr-doc-item" data-id="' + att.id + '" ' +
                    'style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fff;border:1px solid var(--ovr-a-outline);border-radius:var(--ovr-a-radius-md)">' +
                    '<span class="material-symbols-outlined" style="color:var(--ovr-a-primary);flex-shrink:0">description</span>' +
                    '<div style="min-width:0;flex:1">' +
                        '<div style="font-size:14px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escapeHtml(title) + '</div>' +
                        '<div style="font-size:12px;color:var(--ovr-a-text-soft)">' + escapeHtml(filename) + '</div>' +
                    '</div>' +
                    '<a href="' + url + '" target="_blank" rel="noopener" class="ovr-btn-admin ovr-btn-admin--ghost" style="padding:5px 10px;font-size:12px">' +
                        '<span class="material-symbols-outlined" style="font-size:14px">open_in_new</span>' +
                    '</a>' +
                    '<button type="button" class="ovr-btn-admin ovr-btn-admin--danger" data-action="remove">' +
                        '<span class="material-symbols-outlined" style="font-size:14px">delete</span>' +
                    '</button>' +
                '</li>';
            list.insertAdjacentHTML('beforeend', html);
            setIds(currentIds());
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        var frame;
        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (currentIds().length >= max) {
                alert('Maximum ' + max + ' documents.');
                return;
            }
            if (frame) { frame.open(); return; }
            frame = wp.media({
                title: 'Select document',
                button: { text: 'Use this document' },
                multiple: false,
                library: {
                    type: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                }
            });
            frame.on('select', function () {
                frame.state().get('selection').each(function (att) {
                    if (currentIds().length < max) appendDoc(att.toJSON());
                });
            });
            frame.open();
        });

        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action="remove"]');
            if (!btn) return;
            e.preventDefault();
            var li = btn.closest('.ovr-doc-item');
            if (!li) return;
            li.remove();
            setIds(currentIds());
            maybeShowEmptyState();
        });
    }

    /* ====================================================================
       5. ICAL "SYNC NOW" BUTTON
       ==================================================================== */

    function initIcalSync() {
        var btn    = document.querySelector('[data-ovr-ical-sync]');
        var output = document.querySelector('[data-ovr-ical-result]');
        if (!btn) return;

        btn.addEventListener('click', function (e) {
            e.preventDefault();

            var postId = btn.dataset.postId;
            if (!postId) {
                if (output) output.innerHTML = '<span style="color:#ba1a1a">Save the property first to enable iCal sync.</span>';
                return;
            }

            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" style="animation:ovr-spin 1s linear infinite">progress_activity</span> Syncing…';
            if (output) output.textContent = '';

            var fd = new FormData();
            fd.append('action', 'ovr_ical_sync');
            fd.append('post_id', postId);
            fd.append('nonce', ovr.nonce || '');

            fetch(ovr.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (output) {
                        var ok = res && res.success;
                        var msg = (res && res.data && res.data.message) || (ok ? 'Synced.' : 'Failed.');
                        output.innerHTML = '<span style="color:' + (ok ? '#00714e' : '#ba1a1a') + '">' + msg + '</span>';
                    }
                })
                .catch(function () {
                    if (output) output.innerHTML = '<span style="color:#ba1a1a">Network error.</span>';
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                });
        });

        // Inject spin keyframes once.
        if (!document.getElementById('ovr-admin-spin')) {
            var s = document.createElement('style');
            s.id = 'ovr-admin-spin';
            s.textContent = '@keyframes ovr-spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}';
            document.head.appendChild(s);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
