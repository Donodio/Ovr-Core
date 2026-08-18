/**
 * Property Media — Media Library helpers.
 *
 * Makes the wp-admin Media Library filterable by property (the "Property ID →
 * Photos" mental model):
 *
 *  - List view (upload.php?mode=list): the server-rendered dropdown from
 *    render_library_filter() + apply_library_filter() already filter rows.
 *  - Grid view (upload.php default): this script injects a "Filter by property"
 *    dropdown into the native #media-attachment-filters toolbar. Changing it
 *    reloads the grid with ?ovr_property_id=..., which the server-side
 *    ajax_query_attachments_args filter turns into a _ovr_property_id meta
 *    query.
 *
 * @package OVR
 */
(function () {
    'use strict';

    var data = window.ovrPropertyMedia || null;

    // wp_localize_script serializes the PHP associative array (id => title) as a
    // plain JS object, so normalise it to an array of { id, title }.
    function propertyList() {
        if (!data || !data.properties) { return []; }
        var out = [];
        Object.keys(data.properties).forEach(function (k) {
            out.push({ id: parseInt(k, 10), title: data.properties[k] });
        });
        return out;
    }

    function buildOptions(selected) {
        var html = '<option value="0">All properties</option>';
        propertyList().forEach(function (p) {
            var label = 'Property #' + p.id + (p.title ? ' — ' + p.title : '');
            html += '<option value="' + p.id + '"' + (String(p.id) === String(selected) ? ' selected' : '') + '>' + label + '</option>';
        });
        return html;
    }

    function currentPidFromUrl() {
        var m = window.location.search.match(/ovr_property_id=(\d+)/);
        return m ? parseInt(m[1], 10) : 0;
    }

    function injectGridFilter() {
        var toolbar = document.getElementById('media-attachment-filters');
        if (!toolbar || document.getElementById('ovr-modal-property-filter')) { return false; }

        var sel = document.createElement('select');
        sel.id = 'ovr-modal-property-filter';
        sel.innerHTML = buildOptions(currentPidFromUrl());
        toolbar.appendChild(sel);

        sel.addEventListener('change', function () {
            var val = parseInt(sel.value, 10);
            var url = new URL(window.location.href);
            if (val > 0) { url.searchParams.set('ovr_property_id', String(val)); }
            else { url.searchParams.delete('ovr_property_id'); }
            window.location.href = url.toString();
        });
        return true;
    }

    // The grid toolbar is rendered by wp.media after the page scripts run, so
    // poll briefly until it appears.
    document.addEventListener('DOMContentLoaded', function () {
        var attempts = 0;
        var timer = setInterval(function () {
            attempts++;
            if (injectGridFilter() || attempts > 30) { clearInterval(timer); }
        }, 400);
    });
})();
