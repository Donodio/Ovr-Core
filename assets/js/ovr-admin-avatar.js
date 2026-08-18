/**
 * Admin user profile — avatar upload / replace / remove (UsersAdmin).
 *
 * Lets an administrator manage another user's profile photo from the WordPress
 * user-edit screen: upload/replace via the `ovr_upload_avatar` AJAX handler
 * (which honours the `user_id` param for admins), and remove via the new
 * `ovr_remove_avatar` handler (restores the default placeholder). The target
 * user is the `user_id` in the page URL.
 *
 * @package OVR
 */
(function () {
    'use strict';

    var wrap   = document.getElementById('ovr-admin-avatar-wrap');
    if (!wrap) { return; }

    var fileInput = document.getElementById('ovr-admin-avatar-file');
    var uploadBtn = document.getElementById('ovr-admin-avatar-upload');
    var removeBtn = document.getElementById('ovr-admin-avatar-remove');
    var preview   = document.getElementById('ovr-admin-avatar-preview');
    var status    = document.getElementById('ovr-admin-avatar-status');

    var targetId = parseInt((window.location.pathname.match(/user-edit\.php\?user_id=(\d+)/) || [])[1] || 0, 10)
        || parseInt((window.location.search.match(/user_id=(\d+)/) || [])[1] || 0, 10);

    var ajax = window.ajaxurl || '/wp-admin/admin-ajax.php';
    var nonce = window.ovrAdminAvatar ? window.ovrAdminAvatar.nonce : '';

    function flash(msg, ok) {
        if (!status) { return; }
        status.textContent = msg;
        status.style.color = ok ? '#1e8e3e' : '#d93025';
    }

    function setPreview(url) {
        if (!preview) { return; }
        preview.src = url + (url.indexOf('?') > -1 ? '&' : '?') + 'v=' + Date.now();
        preview.style.display = '';
        if (removeBtn) { removeBtn.disabled = false; }
    }

    function post(data) {
        return fetch(ajax, { method: 'POST', credentials: 'same-origin', body: data }).then(function (r) { return r.json(); });
    }

    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', function () {
            var file = fileInput.files[0];
            if (!file) { flash('Choose an image first.', false); return; }
            uploadBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'ovr_upload_avatar');
            fd.append('nonce', nonce);
            fd.append('avatar', file, file.name);
            if (targetId) { fd.append('user_id', String(targetId)); }
            post(fd)
                .then(function (res) {
                    if (res && res.success && res.data && res.data.url) {
                        setPreview(res.data.url);
                        flash(res.data.message || 'Profile photo updated.', true);
                    } else {
                        flash((res && res.data && res.data.message) || 'Upload failed. Please try again.', false);
                    }
                })
                .catch(function () { flash('Upload failed. Please try again.', false); })
                .then(function () { uploadBtn.disabled = false; });
        });
    }

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            if (!confirm('Remove this profile photo and restore the default placeholder?')) { return; }
            removeBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'ovr_remove_avatar');
            fd.append('nonce', nonce);
            if (targetId) { fd.append('user_id', String(targetId)); }
            post(fd)
                .then(function (res) {
                    if (res && res.success) {
                        if (preview) { preview.style.display = 'none'; }
                        removeBtn.disabled = true;
                        if (fileInput) { fileInput.value = ''; }
                        flash(res.data.message || 'Profile photo removed.', true);
                    } else {
                        removeBtn.disabled = false;
                        flash((res && res.data && res.data.message) || 'Could not remove photo.', false);
                    }
                })
                .catch(function () { removeBtn.disabled = false; flash('Could not remove photo.', false); });
        });
    }
})();
