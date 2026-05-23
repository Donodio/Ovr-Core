/**
 * OVR Core — Auth Form Validation
 * @package OVR
 */
(function () {
    'use strict';

    /* Password visibility toggle */
    document.querySelectorAll('.ovr-password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = this.closest('.ovr-input-icon-wrap');
            if (!wrap) return;
            var input = wrap.querySelector('input');
            if (!input) return;
            var icon = this.querySelector('.material-symbols-outlined');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                if (icon) icon.textContent = 'visibility';
            }
        });
    });

    /* Registration password match check */
    var regForm = document.getElementById('ovr-register-form');
    if (regForm) {
        regForm.addEventListener('submit', function (e) {
            var pw = document.getElementById('ovr-reg-password');
            var confirm = document.getElementById('ovr-confirm');
            if (pw && confirm && pw.value !== confirm.value) {
                e.preventDefault();
                alert('Passwords do not match.');
                confirm.focus();
            }
        });
    }

})();
