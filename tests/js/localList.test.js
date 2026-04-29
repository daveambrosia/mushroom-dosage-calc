import { describe, it, expect, beforeEach, vi } from 'vitest';
import path from 'node:path';

const MakerQR = require(path.resolve(__dirname, '../../public/js/modules/adc-maker-qr.js'));

describe('localStorage helpers', () => {
	beforeEach(() => {
		window.localStorage.clear();
	});

	it('saveToLocalList stores a record and returns its id', () => {
		const id = MakerQR.saveToLocalList({
			type: 'strain',
			fields: { name: 'GT', psilocybin: 5000 },
			url: 'https://x/?data=name:GT,psilocybin:5000'
		}, window);
		expect(typeof id).toBe('string');
		expect(id.length).toBeGreaterThan(8);

		const list = MakerQR.loadLocalList(window);
		expect(list).toHaveLength(1);
		expect(list[0].id).toBe(id);
		expect(list[0].status).toBe('local');
		expect(list[0].createdAt).toMatch(/^\d{4}-\d{2}-\d{2}T/);
	});

	it('loadLocalList returns [] when storage is empty', () => {
		expect(MakerQR.loadLocalList(window)).toEqual([]);
	});

	it('loadLocalList recovers from corrupt JSON', () => {
		window.localStorage.setItem('adc_maker_qr_v1', '{not valid json');
		expect(MakerQR.loadLocalList(window)).toEqual([]);
	});

	it('deleteFromLocalList removes a record by id', () => {
		const id = MakerQR.saveToLocalList({
			type: 'strain', fields: { name: 'A' }, url: 'u'
		}, window);
		MakerQR.saveToLocalList({
			type: 'strain', fields: { name: 'B' }, url: 'u'
		}, window);
		MakerQR.deleteFromLocalList(id, window);
		const list = MakerQR.loadLocalList(window);
		expect(list).toHaveLength(1);
		expect(list[0].fields.name).toBe('B');
	});

	it('markAsSubmitted flips status to submitted', () => {
		const id = MakerQR.saveToLocalList({
			type: 'strain', fields: { name: 'A' }, url: 'u'
		}, window);
		MakerQR.markAsSubmitted(id, window);
		const list = MakerQR.loadLocalList(window);
		expect(list[0].status).toBe('submitted');
	});

	it('saveToLocalList throws a friendly error on quota exceeded', () => {
		const spy = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
			const e = new Error('quota');
			e.name = 'QuotaExceededError';
			throw e;
		});
		try {
			expect(() =>
				MakerQR.saveToLocalList({
					type: 'strain', fields: { name: 'X' }, url: 'u'
				}, window)
			).toThrow(/storage is full/i);
		} finally {
			spy.mockRestore();
		}
	});
});
