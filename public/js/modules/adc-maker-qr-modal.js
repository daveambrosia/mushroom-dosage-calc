/**
 * Ambrosia Dosage Calculator — Maker QR modal extension.
 *
 * Wires the Generate-QR button inside Add Custom Strain / Edible modals to
 * the shared MakerQR module.
 */
(function (window, document) {
	'use strict';

	var MakerQR = (window.ADC && window.ADC.MakerQR) || null;
	var cfg     = window.adcMakerQr || {};
	if (!MakerQR) { return; }

	// Right-hand strings are the actual <input id="…"> in class-adc-shortcode.php.
	// Strain compounds use bare IDs (e.g. `adc-modal-psilocybin`); edibles
	// use `adc-modal-edible-{compound}`.
	var STRAIN_FIELDS = {
		name:          'adc-modal-strain-name',
		brand:         'adc-modal-strain-brand',
		batch:         'adc-modal-strain-batch',
		lab:           'adc-modal-strain-lab',
		psilocybin:    'adc-modal-psilocybin',
		psilocin:      'adc-modal-psilocin',
		norpsilocin:   'adc-modal-norpsilocin',
		baeocystin:    'adc-modal-baeocystin',
		norbaeocystin: 'adc-modal-norbaeocystin',
		aeruginascin:  'adc-modal-aeruginascin'
	};

	// The edible modal collects total mcg per compound for the whole package
	// (not per-piece). There is no separate `total_mg` input; we derive it
	// from psilocybin total mcg by /1000 so buildLegacyUrl receives milligrams.
	var EDIBLE_FIELDS = {
		name:               'adc-modal-edible-name',
		brand:              'adc-modal-edible-brand',
		batch:              'adc-modal-edible-batch',
		lab:                'adc-modal-edible-lab',
		pieces_per_package: 'adc-modal-edible-pieces',
		psilocybin:         'adc-modal-edible-psilocybin',
		psilocin:           'adc-modal-edible-psilocin',
		norpsilocin:        'adc-modal-edible-norpsilocin',
		baeocystin:         'adc-modal-edible-baeocystin',
		norbaeocystin:      'adc-modal-edible-norbaeocystin',
		aeruginascin:       'adc-modal-edible-aeruginascin'
	};

	var EDIBLE_COMPOUNDS = ['psilocybin', 'psilocin', 'norpsilocin', 'baeocystin', 'norbaeocystin', 'aeruginascin'];

	function readFields(idMap, type) {
		var out = {};
		Object.keys(idMap).forEach(function (key) {
			var el = document.getElementById(idMap[key]);
			if (!el) { return; }
			if (el.type === 'number') {
				out[key] = el.value === '' ? 0 : Number(el.value);
			} else {
				out[key] = el.value.trim();
			}
		});
		if (type === 'edible') {
			// Modal collects total mcg per package per compound. Compact URL
			// stores compounds as mcg/piece, plus total_mg (mg) for context.
			var pieces = Number(out.pieces_per_package) || 1;
			out.total_mg = (Number(out.psilocybin) || 0) / 1000;
			EDIBLE_COMPOUNDS.forEach(function (k) {
				out[k] = Math.round((Number(out[k]) || 0) / pieces);
			});
		}
		return out;
	}

	function bindGenerator(type) {
		var btn = document.querySelector('[data-adc-modal-generate-qr="' + type + '"]');
		if (!btn) { return; }
		var panel    = document.querySelector('[data-adc-modal-qr-panel="' + type + '"]');
		if (!panel) { return; }
		var canvas   = panel.querySelector('[data-adc-modal-qr-canvas]');
		var urlInput = panel.querySelector('[data-adc-modal-qr-url]');
		var dlBtn    = panel.querySelector('[data-adc-modal-qr-download]');
		var closeBtn = panel.querySelector('[data-adc-modal-qr-close]');
		var idMap    = type === 'edible' ? EDIBLE_FIELDS : STRAIN_FIELDS;

		btn.addEventListener('click', function () {
			var fields = readFields(idMap, type);
			var v = MakerQR.validate(type, fields);
			if (!v.valid) {
				window.alert(Object.keys(v.errors).map(function (k) { return v.errors[k]; }).join('\n'));
				return;
			}
			var url = MakerQR.buildCompactUrl(type, fields, cfg.calculatorUrl || '/');
			urlInput.value = url;
			panel.hidden = false;
			MakerQR.renderQR(canvas, url, 256).catch(function (err) {
				window.alert('QR render failed: ' + err.message);
			});
		});

		if (dlBtn) {
			dlBtn.addEventListener('click', function () {
				var fields = readFields(idMap, type);
				var url    = urlInput.value;
				MakerQR.renderQR(canvas, url, 1024).then(function () {
					var slug = String(fields.name || 'qr').toLowerCase()
						.replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'qr';
					MakerQR.downloadCanvasPng(canvas, 'qr-' + slug + '.png');
					MakerQR.renderQR(canvas, url, 256);
				});
			});
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				panel.hidden = true;
			});
		}
	}

	function init() {
		bindGenerator('strain');
		bindGenerator('edible');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})(window, document);
