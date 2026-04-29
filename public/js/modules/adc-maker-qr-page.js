/**
 * Ambrosia Dosage Calculator — Maker QR dedicated-page controller.
 */
(function (window, document) {
	'use strict';

	var MakerQR = (window.ADC && window.ADC.MakerQR) || null;
	var cfg     = window.adcMakerQr || {};

	if (!MakerQR) { return; }

	var STRAIN_FIELDS = [
		{ key: 'name',          label: 'Product name',  required: true,  type: 'text'   },
		{ key: 'brand',         label: 'Brand',         required: false, type: 'text'   },
		{ key: 'batch',         label: 'Batch number',  required: false, type: 'text'   },
		{ key: 'lab',           label: 'Testing lab',   required: false, type: 'text'   },
		{ key: 'psilocybin',    label: 'Psilocybin (mcg/g)',    required: true,  type: 'number' },
		{ key: 'psilocin',      label: 'Psilocin (mcg/g)',      required: false, type: 'number' },
		{ key: 'norpsilocin',   label: 'Norpsilocin (mcg/g)',   required: false, type: 'number' },
		{ key: 'baeocystin',    label: 'Baeocystin (mcg/g)',    required: false, type: 'number' },
		{ key: 'norbaeocystin', label: 'Norbaeocystin (mcg/g)', required: false, type: 'number' },
		{ key: 'aeruginascin',  label: 'Aeruginascin (mcg/g)',  required: false, type: 'number' }
	];

	var EDIBLE_FIELDS = [
		{ key: 'name',               label: 'Product name',         required: true,  type: 'text'   },
		{ key: 'brand',              label: 'Brand',                required: false, type: 'text'   },
		{ key: 'batch',              label: 'Batch number',         required: false, type: 'text'   },
		{ key: 'lab',                label: 'Testing lab',          required: false, type: 'text'   },
		{ key: 'pieces_per_package', label: 'Pieces per package',   required: true,  type: 'number' },
		{ key: 'total_mg',           label: 'Total mg per package', required: true,  type: 'number' },
		{ key: 'psilocybin',         label: 'Psilocybin (mcg/piece)',    required: true,  type: 'number' },
		{ key: 'psilocin',           label: 'Psilocin (mcg/piece)',      required: false, type: 'number' },
		{ key: 'norpsilocin',        label: 'Norpsilocin (mcg/piece)',   required: false, type: 'number' },
		{ key: 'baeocystin',         label: 'Baeocystin (mcg/piece)',    required: false, type: 'number' },
		{ key: 'norbaeocystin',      label: 'Norbaeocystin (mcg/piece)', required: false, type: 'number' },
		{ key: 'aeruginascin',       label: 'Aeruginascin (mcg/piece)',  required: false, type: 'number' }
	];

	document.addEventListener('DOMContentLoaded', function () {
		var root = document.querySelector('[data-adc-maker-qr]');
		if (!root) { return; }

		var form         = root.querySelector('[data-adc-maker-form]');
		var fieldsEl     = root.querySelector('[data-adc-maker-fields]');
		var feedbackEl   = root.querySelector('[data-adc-maker-feedback]');
		var canvas       = root.querySelector('[data-adc-maker-canvas]');
		var placeholder  = root.querySelector('[data-adc-maker-placeholder]');
		var urlRow       = root.querySelector('[data-adc-maker-url-row]');
		var urlInput     = root.querySelector('[data-adc-maker-url]');
		var copyBtn      = root.querySelector('[data-adc-maker-copy]');
		var downloadBtn  = root.querySelector('[data-adc-maker-download]');
		var submitBtn    = root.querySelector('[data-adc-maker-submit]');
		var savedListEl  = root.querySelector('[data-adc-maker-saved-list]');
		var savedEmptyEl = root.querySelector('[data-adc-maker-saved-empty]');

		var currentRecordId = null;

		function setFeedback(text, kind) {
			feedbackEl.replaceChildren();
			if (!text) { return; }
			feedbackEl.textContent = text;
			feedbackEl.className = 'adc-maker-qr__feedback adc-maker-qr__feedback--' + (kind || 'info');
		}

		function getCurrentType() {
			var checked = form.querySelector('input[name="type"]:checked');
			return checked ? checked.value : 'strain';
		}

		function buildField(field) {
			var wrapper = document.createElement('label');
			wrapper.className = 'adc-maker-qr__field';
			wrapper.dataset.field = field.key;

			var labelText = document.createElement('span');
			labelText.className = 'adc-maker-qr__field-label';
			labelText.textContent = field.label + (field.required ? ' *' : '');
			wrapper.appendChild(labelText);

			var input = document.createElement('input');
			input.type = field.type;
			input.name = field.key;
			input.dataset.field = field.key;
			if (field.type === 'number') {
				input.min = '0';
				input.step = field.key === 'pieces_per_package' ? '1' : 'any';
			}
			wrapper.appendChild(input);

			var err = document.createElement('span');
			err.className = 'adc-maker-qr__field-error';
			err.dataset.errorFor = field.key;
			wrapper.appendChild(err);

			return wrapper;
		}

		function renderFields() {
			var defs = getCurrentType() === 'edible' ? EDIBLE_FIELDS : STRAIN_FIELDS;
			fieldsEl.replaceChildren();
			defs.forEach(function (def) { fieldsEl.appendChild(buildField(def)); });
		}

		function readFields() {
			var data = {};
			fieldsEl.querySelectorAll('input[data-field]').forEach(function (input) {
				var key = input.dataset.field;
				if (input.type === 'number') {
					data[key] = input.value === '' ? 0 : Number(input.value);
				} else {
					data[key] = input.value.trim();
				}
			});
			return data;
		}

		function clearFieldErrors() {
			fieldsEl.querySelectorAll('[data-error-for]').forEach(function (el) {
				el.textContent = '';
			});
		}

		function showFieldErrors(errors) {
			clearFieldErrors();
			Object.keys(errors).forEach(function (key) {
				var slot = fieldsEl.querySelector('[data-error-for="' + key + '"]');
				if (slot) { slot.textContent = errors[key]; }
			});
		}

		function summaryFor(record) {
			var f = record.fields || {};
			var parts = [f.name || '(no name)'];
			if (f.brand) { parts.push(f.brand); }
			parts.push(record.type === 'edible' ? 'edible' : 'strain');
			return parts.join(' · ');
		}

		function slug(s) {
			return String(s || 'qr').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'qr';
		}

		function renderSavedList() {
			var records = MakerQR.loadLocalList();
			savedListEl.replaceChildren();
			if (records.length === 0) {
				savedEmptyEl.style.display = '';
				return;
			}
			savedEmptyEl.style.display = 'none';
			records.forEach(function (record) {
				var li = document.createElement('li');
				li.className = 'adc-maker-qr__saved-item';
				li.dataset.id = record.id;

				var title = document.createElement('span');
				title.className = 'adc-maker-qr__saved-title';
				title.textContent = summaryFor(record);
				li.appendChild(title);

				var badge = document.createElement('span');
				badge.className = 'adc-maker-qr__badge adc-maker-qr__badge--' + record.status;
				badge.textContent = record.status === 'submitted' ? 'Submitted' : 'Local only';
				li.appendChild(badge);

				var showBtn = document.createElement('button');
				showBtn.type = 'button';
				showBtn.className = 'button';
				showBtn.textContent = 'Show QR';
				showBtn.addEventListener('click', function () { showSavedRecord(record); });
				li.appendChild(showBtn);

				var dlBtn = document.createElement('button');
				dlBtn.type = 'button';
				dlBtn.className = 'button';
				dlBtn.textContent = 'Download PNG';
				dlBtn.addEventListener('click', function () { downloadSavedRecord(record); });
				li.appendChild(dlBtn);

				var delBtn = document.createElement('button');
				delBtn.type = 'button';
				delBtn.className = 'button button-link-delete';
				delBtn.textContent = 'Delete';
				delBtn.addEventListener('click', function () {
					MakerQR.deleteFromLocalList(record.id);
					renderSavedList();
				});
				li.appendChild(delBtn);

				savedListEl.appendChild(li);
			});
		}

		function showPreview(url) {
			placeholder.style.display = 'none';
			canvas.style.display = '';
			urlRow.style.display = '';
			urlInput.value = url;
			return MakerQR.renderQR(canvas, url, 256);
		}

		function showSavedRecord(record) {
			currentRecordId = record.id;
			showPreview(record.url).then(function () {
				downloadBtn.style.display = '';
				submitBtn.style.display = record.status === 'submitted' ? 'none' : '';
				setFeedback('', 'info');
			}).catch(function (err) {
				setFeedback('Could not render QR: ' + err.message, 'error');
			});
		}

		function downloadSavedRecord(record) {
			MakerQR.renderQR(canvas, record.url, 1024).then(function () {
				MakerQR.downloadCanvasPng(canvas, 'qr-' + slug(record.fields.name) + '.png');
				MakerQR.renderQR(canvas, record.url, 256);
			});
		}

		form.addEventListener('change', function (e) {
			if (e.target && e.target.name === 'type') {
				renderFields();
				clearFieldErrors();
			}
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var type   = getCurrentType();
			var fields = readFields();

			// Honeypot check (silent block).
			var honeypot = form.querySelector('input[name="website"]');
			if (honeypot && honeypot.value) {
				setFeedback('Submission blocked.', 'error');
				return;
			}

			var result = MakerQR.validate(type, fields);
			if (!result.valid) {
				showFieldErrors(result.errors);
				setFeedback('Please correct the highlighted fields.', 'error');
				return;
			}
			clearFieldErrors();

			var url = MakerQR.buildCompactUrl(type, fields, cfg.calculatorUrl || '/');
			showPreview(url).then(function () {
				try {
					currentRecordId = MakerQR.saveToLocalList({ type: type, fields: fields, url: url });
					renderSavedList();
					downloadBtn.style.display = '';
					submitBtn.style.display = '';
					setFeedback('QR generated and saved to your list.', 'success');
				} catch (err) {
					setFeedback(err.message, 'error');
				}
			}).catch(function (err) {
				setFeedback('Could not render QR: ' + err.message, 'error');
			});
		});

		downloadBtn.addEventListener('click', function () {
			var fields = readFields();
			var url = urlInput.value;
			MakerQR.renderQR(canvas, url, 1024).then(function () {
				MakerQR.downloadCanvasPng(canvas, 'qr-' + slug(fields.name) + '.png');
				MakerQR.renderQR(canvas, url, 256);
			});
		});

		copyBtn.addEventListener('click', function () {
			urlInput.select();
			if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
				window.navigator.clipboard.writeText(urlInput.value);
			} else {
				document.execCommand('copy');
			}
			setFeedback('URL copied.', 'info');
		});

		submitBtn.addEventListener('click', function () {
			var fields = readFields();
			var type   = getCurrentType();
			var email  = window.prompt('Your email (optional, used only to follow up on your submission):') || '';

			var record = {
				type: type,
				fields: fields,
				makerEmail: email,
				lab: fields.lab || '',
				website: ''
			};
			submitBtn.disabled = true;
			setFeedback('Submitting...', 'info');
			MakerQR.submitToChurch(record).then(function (res) {
				submitBtn.disabled = false;
				if (res.success) {
					if (currentRecordId) { MakerQR.markAsSubmitted(currentRecordId); }
					renderSavedList();
					submitBtn.style.display = 'none';
					setFeedback('Submitted! Thank you.', 'success');
					return;
				}
				if (res.error === 'rate_limited') {
					setFeedback('You have submitted recently. Please try again later.', 'error');
				} else if (res.error === 'payload_too_large') {
					setFeedback('Submission too large. Please shorten your notes.', 'error');
				} else if (res.error === 'spam_detected') {
					setFeedback('Submission blocked.', 'error');
				} else if (res.error === 'network_error') {
					setFeedback('Could not reach the church server. Saved locally; try again later.', 'error');
				} else {
					setFeedback('Server error. Try again later.', 'error');
				}
			});
		});

		renderFields();
		renderSavedList();
	});

})(window, document);
