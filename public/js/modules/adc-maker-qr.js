/**
 * Ambrosia Dosage Calculator — Maker QR shared module.
 *
 * Dual-mode: in the browser this attaches to window.ADC.MakerQR;
 * in Node (test runner) the module.exports object is the same API.
 */
(function (factory) {
	var api = factory();
	// eslint-disable-next-line no-undef -- CommonJS export for Node test runner; gated by typeof check.
	if (typeof module === 'object' && module.exports) {
		// eslint-disable-next-line no-undef
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

	// Compact URL field abbreviations. Keys are joined to values with `_`
	// and fields are separated by `~`. Both characters are forbidden in
	// text fields (see validate + FORBIDDEN_CHARS) so they cannot appear
	// inside a value and collide with the separator grammar.
	var COMPACT_KEYS = {
		type:               't',
		name:               'n',
		psilocybin:         'pb',
		psilocin:           'pc',
		norpsilocin:        'npc',
		baeocystin:         'b',
		norbaeocystin:      'nb',
		aeruginascin:       'a',
		total_mg:           'tm',
		pieces_per_package: 'pp',
		brand:              'br',
		batch:              'bn',
		lab:                'lb'
	};

	// `~` and `_` are forbidden in text fields by validate(), so values are
	// safe to drop into the URL with only the standard URL encoding pass.
	function encodeCompactValue(value) {
		return encodeURIComponent(String(value));
	}

	var FORBIDDEN_CHARS = /[~_]/;

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
			// 'pname' (not 'name') because 'name' is a reserved WordPress public
			// query var (post slug) and would 404 the calculator page.
			var qs = new URLSearchParams();
			qs.set('type', 'edible');
			qs.set('pname', fields.name || '');
			qs.set('total_mg', String(Number(fields.total_mg || 0)));
			qs.set('pieces', String(Number(fields.pieces_per_package || fields.pieces || 1)));
			if (fields.brand) { qs.set('brand', fields.brand); }
			if (fields.batch) { qs.set('batch', fields.batch); }
			return baseUrl + '?' + qs.toString();
		}

		throw new Error('Unknown maker QR type: ' + type);
	}

	/**
	 * Build a compact URL for a strain or edible record.
	 *
	 * Format: `?d=t_s~n_Name~pb_10000~pc_500~br_Brand~bn_Batch~lb_Lab`
	 * Strain compound values are mcg/g; edible compound values are mcg/piece
	 * and the URL also carries `tm` (total mg) and `pp` (pieces) for context.
	 */
	function buildCompactUrl(type, fields, baseUrl) {
		if (type !== 'strain' && type !== 'edible') {
			throw new Error('Unknown maker QR type: ' + type);
		}
		var parts = [COMPACT_KEYS.type + '_' + (type === 'edible' ? 'e' : 's')];
		if (fields.name) {
			parts.push(COMPACT_KEYS.name + '_' + encodeCompactValue(fields.name));
		}
		if (type === 'edible') {
			var totalMg = Number(fields.total_mg || 0);
			var pieces  = Number(fields.pieces_per_package || fields.pieces || 0);
			if (totalMg > 0) { parts.push(COMPACT_KEYS.total_mg + '_' + totalMg); }
			if (pieces > 0)  { parts.push(COMPACT_KEYS.pieces_per_package + '_' + pieces); }
		}
		STRAIN_COMPOUNDS.forEach(function (key) {
			var v = Number(fields[key] || 0);
			if (v > 0) { parts.push(COMPACT_KEYS[key] + '_' + v); }
		});
		['brand', 'batch', 'lab'].forEach(function (key) {
			if (fields[key]) {
				parts.push(COMPACT_KEYS[key] + '_' + encodeCompactValue(fields[key]));
			}
		});
		return baseUrl + '?d=' + parts.join('~');
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

	var STRAIN_MAX = 50000;     // mcg per gram
	var EDIBLE_MAX = 500000;    // mcg per piece
	var PIECES_MAX = 500;
	var NAME_MAX   = 100;
	var BATCH_MAX  = 50;

	/**
	 * Validate maker form input.
	 */
	function validate(type, fields) {
		var errors = {};
		var name = String(fields.name || '').trim();
		if (!name) {
			errors.name = 'Product name is required.';
		} else if (name.length > NAME_MAX) {
			errors.name = 'Name must be 100 characters or fewer.';
		} else if (FORBIDDEN_CHARS.test(name)) {
			errors.name = 'Name must not contain ~ or _.';
		}

		if (fields.brand) {
			if (String(fields.brand).length > NAME_MAX) {
				errors.brand = 'Brand must be 100 characters or fewer.';
			} else if (FORBIDDEN_CHARS.test(String(fields.brand))) {
				errors.brand = 'Brand must not contain ~ or _.';
			}
		}
		if (fields.batch) {
			if (String(fields.batch).length > BATCH_MAX) {
				errors.batch = 'Batch must be 50 characters or fewer.';
			} else if (FORBIDDEN_CHARS.test(String(fields.batch))) {
				errors.batch = 'Batch must not contain ~ or _.';
			}
		}
		if (fields.lab) {
			if (String(fields.lab).length > NAME_MAX) {
				errors.lab = 'Lab must be 100 characters or fewer.';
			} else if (FORBIDDEN_CHARS.test(String(fields.lab))) {
				errors.lab = 'Lab must not contain ~ or _.';
			}
		}

		var psilocybin = Number(fields.psilocybin || 0);
		if (!(psilocybin > 0)) {
			errors.psilocybin = 'Psilocybin must be greater than 0.';
		}

		if (type === 'strain') {
			STRAIN_COMPOUNDS.forEach(function (key) {
				var v = Number(fields[key] || 0);
				if (v < 0 || v > STRAIN_MAX) {
					errors[key] = key + ' must be 0–' + STRAIN_MAX + ' mcg/g.';
				}
			});
		} else if (type === 'edible') {
			STRAIN_COMPOUNDS.forEach(function (key) {
				var v = Number(fields[key] || 0);
				if (v < 0 || v > EDIBLE_MAX) {
					errors[key] = key + ' must be 0–' + EDIBLE_MAX + ' mcg/piece.';
				}
			});
			var pieces = Number(fields.pieces_per_package || fields.pieces || 0);
			if (!(pieces >= 1 && pieces <= PIECES_MAX)) {
				errors.pieces_per_package = 'Pieces per package must be 1–' + PIECES_MAX + '.';
			}
			var totalMg = Number(fields.total_mg || 0);
			if (!(totalMg > 0)) {
				errors.total_mg = 'Total mg must be greater than 0.';
			}
		} else {
			errors.type = 'Unknown type.';
		}

		return {
			valid: Object.keys(errors).length === 0,
			errors: errors
		};
	}

	/**
	 * Submit a record to the church via /adc/v1/submit.
	 */
	function submitToChurch(record, winRef) {
		var w = getWindow(winRef);
		if (!w) {
			return Promise.resolve({ success: false, error: 'no_window' });
		}
		var cfg = w.adcData || {};
		var url = (cfg.restUrl || '/wp-json/adc/v1/') + 'submit';
		var payload = {
			type: record.type,
			data: record.fields || {},
			name: record.makerName || '',
			email: record.makerEmail || '',
			notes: record.notes || '',
			lab: record.lab || '',
			website: record.website || ''
		};
		return w.fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce || ''
			},
			body: JSON.stringify(payload)
		}).then(function (res) {
			if (res.ok) {
				return res.json().then(function (j) {
					return { success: true, id: j.id };
				});
			}
			if (res.status === 429) { return { success: false, error: 'rate_limited' }; }
			if (res.status === 413) { return { success: false, error: 'payload_too_large' }; }
			if (res.status === 400) { return { success: false, error: 'spam_detected' }; }
			return { success: false, error: 'server_error' };
		}).catch(function () {
			return { success: false, error: 'network_error' };
		});
	}

	/**
	 * Render a URL into a <canvas> as a QR code.
	 */
	function renderQR(canvasEl, url, sizePx, winRef) {
		var w = getWindow(winRef);
		return new Promise(function (resolve, reject) {
			if (!w || typeof w.QRCode === 'undefined' || !w.QRCode.toCanvas) {
				reject(new Error('QRCode library not loaded.'));
				return;
			}
			w.QRCode.toCanvas(canvasEl, url, { width: sizePx || 256 }, function (err) {
				if (err) { reject(err); } else { resolve(); }
			});
		});
	}

	/**
	 * Trigger a PNG download of a canvas element.
	 */
	function downloadCanvasPng(canvasEl, filename) {
		var doc = (typeof document !== 'undefined') ? document : null;
		if (!doc) { return; }
		var link = doc.createElement('a');
		link.download = filename || 'qr-code.png';
		link.href = canvasEl.toDataURL('image/png');
		doc.body.appendChild(link);
		link.click();
		doc.body.removeChild(link);
	}

	return {
		buildLegacyUrl: buildLegacyUrl,
		buildCompactUrl: buildCompactUrl,
		STRAIN_COMPOUNDS: STRAIN_COMPOUNDS,
		loadLocalList: loadLocalList,
		saveToLocalList: saveToLocalList,
		deleteFromLocalList: deleteFromLocalList,
		markAsSubmitted: markAsSubmitted,
		validate: validate,
		submitToChurch: submitToChurch,
		renderQR: renderQR,
		downloadCanvasPng: downloadCanvasPng
	};
});
