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

	var STORAGE_KEY = 'adc_maker_qr_v1';

	function getWindow(winRef) {
		return winRef || (typeof window !== 'undefined' ? window : null);
	}

	function uuidv4() {
		if (typeof globalThis.crypto !== 'undefined' && globalThis.crypto.randomUUID) {
			return globalThis.crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = Math.random() * 16 | 0;
			var v = c === 'x' ? r : (r & 0x3 | 0x8);
			return v.toString(16);
		});
	}

	function readStore(winRef) {
		var w = getWindow(winRef);
		if (!w || !w.localStorage) { return { records: [] }; }
		try {
			var raw = w.localStorage.getItem(STORAGE_KEY);
			if (!raw) { return { records: [] }; }
			var parsed = JSON.parse(raw);
			if (!parsed || !Array.isArray(parsed.records)) { return { records: [] }; }
			return parsed;
		} catch (e) {
			return { records: [] };
		}
	}

	function writeStore(store, winRef) {
		var w = getWindow(winRef);
		if (!w || !w.localStorage) { return; }
		try {
			w.localStorage.setItem(STORAGE_KEY, JSON.stringify(store));
		} catch (e) {
			if (e && (e.name === 'QuotaExceededError' || e.code === 22)) {
				throw new Error('Browser storage is full. Please delete old saved QR codes.');
			}
			throw e;
		}
	}

	function loadLocalList(winRef) {
		return readStore(winRef).records;
	}

	function saveToLocalList(record, winRef) {
		var store = readStore(winRef);
		var id = uuidv4();
		store.records.push({
			id: id,
			type: record.type,
			fields: record.fields || {},
			url: record.url || '',
			createdAt: new Date().toISOString(),
			status: 'local'
		});
		writeStore(store, winRef);
		return id;
	}

	function deleteFromLocalList(id, winRef) {
		var store = readStore(winRef);
		store.records = store.records.filter(function (r) { return r.id !== id; });
		writeStore(store, winRef);
	}

	function markAsSubmitted(id, winRef) {
		var store = readStore(winRef);
		store.records = store.records.map(function (r) {
			if (r.id === id) { r.status = 'submitted'; }
			return r;
		});
		writeStore(store, winRef);
	}

	return {
		buildLegacyUrl: buildLegacyUrl,
		STRAIN_COMPOUNDS: STRAIN_COMPOUNDS,
		loadLocalList: loadLocalList,
		saveToLocalList: saveToLocalList,
		deleteFromLocalList: deleteFromLocalList,
		markAsSubmitted: markAsSubmitted
	};
});
