import { describe, it, expect, beforeEach, vi } from 'vitest';
import path from 'node:path';

const MakerQR = require(path.resolve(__dirname, '../../public/js/modules/adc-maker-qr.js'));

describe('validate', () => {
	it('passes a minimal valid strain', () => {
		const r = MakerQR.validate('strain', {
			name: 'GT', psilocybin: 5000
		});
		expect(r.valid).toBe(true);
		expect(r.errors).toEqual({});
	});

	it('requires product name', () => {
		const r = MakerQR.validate('strain', {
			name: '', psilocybin: 5000
		});
		expect(r.valid).toBe(false);
		expect(r.errors.name).toBeTruthy();
	});

	it('requires psilocybin > 0 for strains', () => {
		const r = MakerQR.validate('strain', {
			name: 'GT', psilocybin: 0
		});
		expect(r.valid).toBe(false);
		expect(r.errors.psilocybin).toBeTruthy();
	});

	it('caps strain compound range at 50000', () => {
		const r = MakerQR.validate('strain', {
			name: 'X', psilocybin: 999999
		});
		expect(r.valid).toBe(false);
		expect(r.errors.psilocybin).toBeTruthy();
	});

	it('requires pieces 1-500 and total_mg > 0 for edibles', () => {
		const ok = MakerQR.validate('edible', {
			name: 'Bar', total_mg: 2000, pieces_per_package: 4, psilocybin: 500
		});
		expect(ok.valid).toBe(true);

		const badPieces = MakerQR.validate('edible', {
			name: 'Bar', total_mg: 2000, pieces_per_package: 0, psilocybin: 500
		});
		expect(badPieces.valid).toBe(false);
		expect(badPieces.errors.pieces_per_package).toBeTruthy();

		const tooManyPieces = MakerQR.validate('edible', {
			name: 'Bar', total_mg: 2000, pieces_per_package: 5000, psilocybin: 500
		});
		expect(tooManyPieces.valid).toBe(false);
	});

	it('caps name length at 100', () => {
		const longName = 'a'.repeat(101);
		const r = MakerQR.validate('strain', {
			name: longName, psilocybin: 5000
		});
		expect(r.valid).toBe(false);
		expect(r.errors.name).toBeTruthy();
	});

	it('rejects ~ and _ in name, brand, batch, lab', () => {
		const tilde = MakerQR.validate('strain', { name: 'Foo~Bar', psilocybin: 5000 });
		expect(tilde.valid).toBe(false);
		expect(tilde.errors.name).toBeTruthy();

		const under = MakerQR.validate('strain', { name: 'Foo_Bar', psilocybin: 5000 });
		expect(under.valid).toBe(false);
		expect(under.errors.name).toBeTruthy();

		const brand = MakerQR.validate('strain', {
			name: 'OK', psilocybin: 5000, brand: 'A_B'
		});
		expect(brand.valid).toBe(false);
		expect(brand.errors.brand).toBeTruthy();

		const batch = MakerQR.validate('strain', {
			name: 'OK', psilocybin: 5000, batch: 'B~1'
		});
		expect(batch.valid).toBe(false);
		expect(batch.errors.batch).toBeTruthy();

		const lab = MakerQR.validate('strain', {
			name: 'OK', psilocybin: 5000, lab: 'Lab_X'
		});
		expect(lab.valid).toBe(false);
		expect(lab.errors.lab).toBeTruthy();
	});

	it('allows dashes and dots in text fields', () => {
		const r = MakerQR.validate('strain', {
			name: 'Foo-Bar.Co', psilocybin: 5000,
			brand: 'A.C.M.E', batch: 'B-42', lab: 'Cal-Green.Co'
		});
		expect(r.valid).toBe(true);
	});
});

describe('submitToChurch', () => {
	beforeEach(() => {
		window.adcMakerQr = { restUrl: 'https://example.com/wp-json/adc/v1/', nonce: 'NONCE' };
	});

	it('POSTs to /submit and returns success on 200', async () => {
		window.fetch = vi.fn(() => Promise.resolve({
			ok: true, status: 200,
			json: () => Promise.resolve({ success: true, id: 17 })
		}));
		const result = await MakerQR.submitToChurch({
			type: 'strain',
			fields: { name: 'GT', psilocybin: 5000 },
			makerName: 'Local',
			makerEmail: 'maker@example.com',
			lab: 'TestLab'
		}, window);
		expect(result.success).toBe(true);
		expect(window.fetch).toHaveBeenCalledOnce();
		const [url, opts] = window.fetch.mock.calls[0];
		expect(url).toContain('/submit');
		expect(opts.method).toBe('POST');
		expect(opts.headers['X-WP-Nonce']).toBe('NONCE');
		const body = JSON.parse(opts.body);
		expect(body.type).toBe('strain');
		expect(body.name).toBe('Local');
		expect(body.email).toBe('maker@example.com');
		expect(body.website).toBe('');
		expect(body.data.psilocybin).toBe(5000);
	});

	it('returns rate_limited on 429', async () => {
		window.fetch = vi.fn(() => Promise.resolve({
			ok: false, status: 429,
			json: () => Promise.resolve({ code: 'rest_too_many' })
		}));
		const result = await MakerQR.submitToChurch({
			type: 'strain', fields: { name: 'GT', psilocybin: 5000 },
			makerName: 'M', makerEmail: 'm@x.com'
		}, window);
		expect(result.success).toBe(false);
		expect(result.error).toBe('rate_limited');
	});

	it('returns network_error on fetch rejection', async () => {
		window.fetch = vi.fn(() => Promise.reject(new Error('network down')));
		const result = await MakerQR.submitToChurch({
			type: 'strain', fields: { name: 'GT', psilocybin: 5000 },
			makerName: 'M', makerEmail: 'm@x.com'
		}, window);
		expect(result.success).toBe(false);
		expect(result.error).toBe('network_error');
	});
});

describe('submitToChurch field normalization', () => {
	beforeEach(() => {
		window.adcMakerQr = { restUrl: 'https://example.com/wp-json/adc/v1/', nonce: 'NONCE' };
	});

	it('renames batch to batch_number in the submitted data', async () => {
		window.fetch = vi.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 1 }) }));
		await MakerQR.submitToChurch({
			type: 'edible',
			fields: { name: 'Gummy', psilocybin: 4000, pieces_per_package: 10, batch: 'B-77' }
		}, window);
		const body = JSON.parse(window.fetch.mock.calls[0][1].body);
		expect(body.data.batch_number).toBe('B-77');
		expect(body.data.batch).toBeUndefined();
	});

	it('folds strain brand and lab into notes (no DB columns for them)', async () => {
		window.fetch = vi.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 2 }) }));
		await MakerQR.submitToChurch({
			type: 'strain',
			fields: { name: 'GT', psilocybin: 6000, brand: 'Sacred Farms', lab: 'TestLab' }
		}, window);
		const body = JSON.parse(window.fetch.mock.calls[0][1].body);
		expect(body.data.brand).toBeUndefined();
		expect(body.data.lab).toBeUndefined();
		expect(body.data.notes).toContain('Brand: Sacred Farms');
		expect(body.data.notes).toContain('Lab: TestLab');
	});

	it('keeps edible brand as a real field (edibles table has a brand column)', async () => {
		window.fetch = vi.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 3 }) }));
		await MakerQR.submitToChurch({
			type: 'edible',
			fields: { name: 'Gummy', psilocybin: 4000, pieces_per_package: 10, brand: 'Sacred Farms' }
		}, window);
		const body = JSON.parse(window.fetch.mock.calls[0][1].body);
		expect(body.data.brand).toBe('Sacred Farms');
	});
});

describe('encodeCompactValue grammar self-defense', () => {
	it('strips separator characters from values even without validate()', () => {
		const url = MakerQR.buildCompactUrl('strain', { name: 'Gold_Teacher~X', psilocybin: 6000 }, 'https://x.test/calc/');
		const d = new URL(url).searchParams.get('d');
		const parts = Object.fromEntries(d.split('~').map(p => [p.slice(0, p.indexOf('_')), p.slice(p.indexOf('_') + 1)]));
		expect(decodeURIComponent(parts.n)).toBe('GoldTeacherX');
		expect(parts.pb).toBe('6000');
	});

	it('round-trips awkward names through the single URL decode the parser relies on', () => {
		// searchParams.get('d') applies the one-and-only percent-decode; the
		// calculator's parser must NOT decode again (it corrupted %-escapes).
		const name = 'B.plus v2.0 100% Pure & Lot%41B';
		const url = MakerQR.buildCompactUrl('strain', { name, psilocybin: 6000 }, 'https://x.test/calc/');
		const d = new URL(url).searchParams.get('d');
		const n = d.split('~').find(p => p.startsWith('n_')).slice(2);
		expect(n).toBe(name);
	});
});
