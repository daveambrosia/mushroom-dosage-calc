import { describe, it, expect, beforeEach, vi } from 'vitest';
import path from 'node:path';

const MakerQR = require(path.resolve(__dirname, '../../public/js/modules/adc-maker-qr.js'));

describe('validate', () => {
	it('passes a minimal valid strain', () => {
		const r = MakerQR.validate('strain', {
			name: 'GT', makerName: 'Local', psilocybin: 5000
		});
		expect(r.valid).toBe(true);
		expect(r.errors).toEqual({});
	});

	it('requires product name', () => {
		const r = MakerQR.validate('strain', {
			name: '', makerName: 'Local', psilocybin: 5000
		});
		expect(r.valid).toBe(false);
		expect(r.errors.name).toBeTruthy();
	});

	it('requires maker name when requireMaker is true', () => {
		const r = MakerQR.validate('strain', {
			name: 'GT', makerName: '', psilocybin: 5000
		}, { requireMaker: true });
		expect(r.valid).toBe(false);
		expect(r.errors.makerName).toBeTruthy();
	});

	it('requires psilocybin > 0 for strains', () => {
		const r = MakerQR.validate('strain', {
			name: 'GT', makerName: 'Local', psilocybin: 0
		});
		expect(r.valid).toBe(false);
		expect(r.errors.psilocybin).toBeTruthy();
	});

	it('caps strain compound range at 50000', () => {
		const r = MakerQR.validate('strain', {
			name: 'X', makerName: 'M', psilocybin: 999999
		});
		expect(r.valid).toBe(false);
		expect(r.errors.psilocybin).toBeTruthy();
	});

	it('requires pieces 1-500 and total_mg > 0 for edibles', () => {
		const ok = MakerQR.validate('edible', {
			name: 'Bar', makerName: 'M', total_mg: 2000, pieces_per_package: 4, psilocybin: 500
		});
		expect(ok.valid).toBe(true);

		const badPieces = MakerQR.validate('edible', {
			name: 'Bar', makerName: 'M', total_mg: 2000, pieces_per_package: 0, psilocybin: 500
		});
		expect(badPieces.valid).toBe(false);
		expect(badPieces.errors.pieces_per_package).toBeTruthy();

		const tooManyPieces = MakerQR.validate('edible', {
			name: 'Bar', makerName: 'M', total_mg: 2000, pieces_per_package: 5000, psilocybin: 500
		});
		expect(tooManyPieces.valid).toBe(false);
	});

	it('caps name length at 100', () => {
		const longName = 'a'.repeat(101);
		const r = MakerQR.validate('strain', {
			name: longName, makerName: 'M', psilocybin: 5000
		});
		expect(r.valid).toBe(false);
		expect(r.errors.name).toBeTruthy();
	});
});

describe('submitToChurch', () => {
	beforeEach(() => {
		window.adcData = { restUrl: 'https://example.com/wp-json/adc/v1/', nonce: 'NONCE' };
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
