/**
 * OVR Core — Public JavaScript
 * @package OVR
 */
(function () {
    'use strict';

    /* ──────────────────────────────────────
       Smooth scroll for anchor links
       ────────────────────────────────────── */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    /* ──────────────────────────────────────
       Favorite / Save Button
       ────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ovr-card-favorite');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var icon = btn.querySelector('.material-symbols-outlined');
        if (!icon) return;

        var isFilled = icon.classList.contains('fill');
        icon.classList.toggle('fill');
        icon.style.color = isFilled ? '' : 'var(--ovr-error)';
    });

    /* ──────────────────────────────────────
       Promo Code Apply
       ────────────────────────────────────── */
    var promoBtn = document.getElementById('ovr-promo-apply');
    if (promoBtn) {
        promoBtn.addEventListener('click', function () {
            var input = document.getElementById('ovr-promo-input');
            var msgEl = document.getElementById('ovr-promo-msg');
            var code = input ? input.value.trim() : '';

            if (!code) {
                if (msgEl) {
                    msgEl.textContent = 'Please enter a promo code.';
                    msgEl.style.color = 'var(--ovr-error)';
                }
                return;
            }

            if (msgEl) {
                msgEl.textContent = 'Checking...';
                msgEl.style.color = 'var(--ovr-on-surface-variant)';
            }

            var fd = new FormData();
            fd.append('action', 'ovr_apply_promo');
            fd.append('nonce', window.ovrData ? window.ovrData.nonce : '');
            fd.append('code', code);

            fetch(window.ovrData ? window.ovrData.ajaxUrl : '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (msgEl) {
                        msgEl.textContent = res.data ? res.data.message : 'Invalid code.';
                        msgEl.style.color = res.success ? 'var(--ovr-secondary)' : 'var(--ovr-error)';
                    }
                })
                .catch(function () {
                    if (msgEl) {
                        msgEl.textContent = 'Network error. Please try again.';
                        msgEl.style.color = 'var(--ovr-error)';
                    }
                });
        });
    }

    /* ──────────────────────────────────────
       Mobile Nav Toggle (Phase 2 enhancement)
       ────────────────────────────────────── */

})();
