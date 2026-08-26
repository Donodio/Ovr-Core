/*
 * Online Villages ID Request — client-side PDF builder.
 *
 * Reads window.OVR_ID_FORM.templateUrl ('' = built-in mode). Fill mode loads
 * the admin-supplied AcroForm PDF and sets text fields; built-in mode draws a
 * printable letter-size sheet. Nothing is posted to the server.
 * Requires pdf-lib (global PDFLib), enqueued by Assets.php.
 */
(function () {
	'use strict';

	var root = document.querySelector('.ovr-idreq');
	if (!root) { return; }
	var form = root.querySelector('form.ovr-idreq-form');
	var status = root.querySelector('.ovr-idreq-status');
	var printBtn = root.querySelector('[data-ovr-print]');
	if (!form || !status || !window.PDFLib) {
		return;
	}

	var templateUrl = (window.OVR_ID_FORM && window.OVR_ID_FORM.templateUrl)
		? String(window.OVR_ID_FORM.templateUrl)
		: '';
	var FILE_NAME = 'villages-id-request.pdf';

	function setStatus(message, kind) {
		status.textContent = message;
		status.className = 'ovr-idreq-status' + (kind ? ' is-' + kind : '');
	}

	function fieldMeta() {
		var out = [];
		var inputs = form.querySelectorAll('input[data-pdf-field]');
		for (var i = 0; i < inputs.length; i++) {
			out.push({
				input: inputs[i],
				name: inputs[i].name,
				label: inputs[i].getAttribute('data-label') || inputs[i].name,
				pdfField: inputs[i].getAttribute('data-pdf-field') || '',
				value: String(inputs[i].value || '')
			});
		}
		return out;
	}

	function clearErrors() {
		var errs = form.querySelectorAll('.ovr-idreq-err');
		for (var i = 0; i < errs.length; i++) { errs[i].parentNode.removeChild(errs[i]); }
		var bad = form.querySelectorAll('input.is-invalid');
		for (var j = 0; j < bad.length; j++) { bad[j].classList.remove('is-invalid'); }
	}

	function validate() {
		clearErrors();
		var ok = true;
		var required = form.querySelectorAll('input[required]');
		for (var i = 0; i < required.length; i++) {
			var input = required[i];
			if (String(input.value || '').replace(/^\s+|\s+$/g, '') !== '') { continue; }
			ok = false;
			input.classList.add('is-invalid');
			var msg = document.createElement('span');
			msg.className = 'ovr-idreq-err';
			msg.textContent = 'This field is required.';
			input.parentNode.appendChild(msg);
		}
		return ok;
	}

	function wrapText(text, maxChars) {
		var words = String(text).split(/\s+/);
		var lines = [];
		var line = '';
		for (var i = 0; i < words.length; i++) {
			var candidate = line ? line + ' ' + words[i] : words[i];
			if (candidate.length > maxChars && line) {
				lines.push(line);
				line = words[i];
			} else {
				line = candidate;
			}
		}
		if (line) { lines.push(line); }
		return lines.length ? lines : [''];
	}

	function buildFilled(fields) {
		return fetch(templateUrl, { credentials: 'same-origin' })
			.then(function (response) {
				if (!response.ok) { throw new Error('Template fetch failed (' + response.status + ')'); }
				return response.arrayBuffer();
			})
			.then(function (bytes) {
				return PDFLib.PDFDocument.load(bytes);
			})
			.then(function (doc) {
				var formApi = doc.getForm();
				for (var i = 0; i < fields.length; i++) {
					var field = fields[i];
					if (!field.pdfField || '' === field.value.replace(/^\s+|\s+$/g, '')) { continue; }
					try {
						var textField = formApi.getTextField(field.pdfField);
						textField.setText(field.value);
					} catch (err) {
						// Field name not present in this template — skip silently.
					}
				}
				return doc.save();
			});
	}

	function drawBuiltin() {
		return PDFLib.PDFDocument.create().then(function (doc) {
			return Promise.all([
				doc.embedFont(PDFLib.StandardFonts.Helvetica),
				doc.embedFont(PDFLib.StandardFonts.HelveticaBold)
			]).then(function (fonts) {
				var regular = fonts[0];
				var bold = fonts[1];
				var page = doc.addPage([612, 792]);
				var margin = 56;
				var bottom = 64;
				var y = 792 - margin;
				var lineHeight = 16;

				function newPageIfNeeded(needed) {
					if (y - needed >= bottom) { return; }
					page = doc.addPage([612, 792]);
					y = 792 - margin;
				}

				y -= 18;
				page.drawText('Villages Lifestyle ID Request', { x: margin, y: y, size: 18, font: bold });
				y -= lineHeight;
				page.drawText('Prepared ' + new Date().toLocaleDateString(), { x: margin, y: y, size: 11, font: regular });

				var sections = form.querySelectorAll('fieldset.ovr-idreq-section');
				for (var s = 0; s < sections.length; s++) {
					newPageIfNeeded(lineHeight * 3);
					y -= lineHeight * 2;
					page.drawText(String(sections[s].querySelector('legend').textContent), {
						x: margin, y: y, size: 13, font: bold
					});

					var rows = sections[s].querySelectorAll('input[data-pdf-field]');
					for (var r = 0; r < rows.length; r++) {
						var label = rows[r].getAttribute('data-label') || rows[r].name;
						var value = String(rows[r].value || '');
						var lines = value ? wrapText(value, 90) : [''];
						for (var l = 0; l < lines.length; l++) {
							newPageIfNeeded(lineHeight);
							y -= lineHeight;
							if (0 === l) {
								page.drawText(label + ':', { x: margin + 12, y: y, size: 10, font: bold });
							}
							page.drawText(lines[l], { x: margin + 150, y: y, size: 10, font: regular });
						}
					}
				}
				return doc.save();
			});
		});
	}

	function toBlob(bytes) {
		return new Blob([bytes], { type: 'application/pdf' });
	}

	function download(blob) {
		var url = URL.createObjectURL(blob);
		var link = document.createElement('a');
		link.href = url;
		link.download = FILE_NAME;
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		window.setTimeout(function () { URL.revokeObjectURL(url); }, 5000);
	}

	function generate() {
		if (!validate()) {
			setStatus('Please complete the highlighted required fields.', 'error');
			return Promise.reject(new Error('validation'));
		}
		var meta = fieldMeta();
		var job = templateUrl ? buildFilled(meta) : drawBuiltin();
		return job.then(toBlob);
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		setStatus(templateUrl ? 'Preparing your PDF…' : 'Building your PDF…', null);
		generate()
			.then(download)
			.then(function () {
				setStatus('Your request was saved as ' + FILE_NAME + '. Print it and bring it to a Villages ID center.', 'ok');
			})
			.catch(function (err) {
				if (err && 'validation' === err.message) { return; }
				setStatus('Sorry — the PDF could not be created. Please try again.', 'error');
				console.error('[ovr-id-request]', err);
			});
	});

	if (printBtn) {
		printBtn.addEventListener('click', function () {
			setStatus('Preparing your PDF…', null);
			generate()
				.then(function (blob) {
					window.open(URL.createObjectURL(blob));
					setStatus('', null);
				})
				.catch(function (err) {
					if (err && 'validation' === err.message) { return; }
					setStatus('Sorry — the PDF could not be created. Please try again.', 'error');
					console.error('[ovr-id-request] print', err);
				});
		});
	}
})();
