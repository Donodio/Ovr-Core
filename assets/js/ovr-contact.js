(function () {
	var root = document.querySelector('[data-ovr-contact]');
	if (!root) { return; }
	var form = root.querySelector('form');
	var status = root.querySelector('.ovr-cform-status');
	var ajaxUrl = window.ovrData && window.ovrData.ajaxUrl; // Assets.php localizes 'ajaxUrl' under ovrData.
	if (!ajaxUrl) { return; }

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		status.textContent = 'Sending…';
		status.className = 'ovr-cform-status';

		var fd = new FormData(form);
		fd.append('action', 'ovr_contact');
		fd.append('nonce', root.getAttribute('data-nonce'));

		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (res) {
				var msg = (res.j && res.j.data && res.j.data.message) || 'Something went wrong.';
				status.textContent = msg;
				status.className = 'ovr-cform-status ' + (res.ok && res.j.success ? 'is-ok' : 'is-error');
				if (res.ok && res.j.success) { form.reset(); }
			})
			.catch(function () {
				status.textContent = 'Network error — please try again.';
				status.className = 'ovr-cform-status is-error';
			});
	});
})();
