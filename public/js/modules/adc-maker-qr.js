/**
 * Ambrosia Dosage Calculator — Maker QR shared module.
 *
 * Dual-mode: in the browser this attaches to window.ADC.MakerQR;
 * in Node (test runner) the module.exports object is the same API.
 */
(function (factory) {
	var api = factory();
	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}
	if (typeof window !== 'undefined') {
		window.ADC = window.ADC || {};
		window.ADC.MakerQR = api;
	}
})(function () {
	'use strict';

	var STRAIN_COMPOUNDS = [
		'psilocybin', 'psilocin', 'norpsilocin',
		'baeocystin', 'norbaeocystin', 'aeruginascin'
	];

	/**
	 * Build a legacy URL for a strain or edible record.
	 *
	 * @param {string} type    'strain' or 'edible'.
	 * @param {object} fields  Record fields.
	 * @param {string} baseUrl Calculator page URL.
	 * @returns {string} Full legacy URL.
	 */
	function buildLegacyUrl(type, fields, baseUrl) {
		if (type === 'strain') {
			var parts = ['name:' + (fields.name || 'Unknown')];
			STRAIN_COMPOUNDS.forEach(function (key) {
				var v = Number(fields[key] || 0);
				if (v > 0) {
					parts.push(key + ':' + v);
				}
			});
			return baseUrl + '?data=' + encodeURIComponent(parts.join(','));
		}

		if (type === 'edible') {
			var qs = new URLSearchParams();
			qs.set('type', 'edible');
			qs.set('name', fields.name || '');
			qs.set('total_mg', String(Number(fields.total_mg || 0)));
			qs.set('pieces', String(Number(fields.pieces_per_package || fields.pieces || 1)));
			if (fields.brand) { qs.set('brand', fields.brand); }
			if (fields.batch) { qs.set('batch', fields.batch); }
			return baseUrl + '?' + qs.toString();
		}

		throw new Error('Unknown maker QR type: ' + type);
	}

	return {
		buildLegacyUrl: buildLegacyUrl,
		STRAIN_COMPOUNDS: STRAIN_COMPOUNDS
	};
});
