import { describe, it, expect } from 'vitest';
import path from 'node:path';

const MakerQR = require(path.resolve(__dirname, '../../public/js/modules/adc-maker-qr.js'));

describe('buildLegacyUrl', () => {
	const base = 'https://example.com/calculator/';

	it('builds a strain URL with the data:key:value comma format', () => {
		const url = MakerQR.buildLegacyUrl('strain', {
			name: 'Golden Teacher',
			psilocybin: 7000,
			psilocin: 800
		}, base);
		expect(url).toBe(
			base + '?data=name%3AGolden%20Teacher%2Cpsilocybin%3A7000%2Cpsilocin%3A800'
		);
	});

	it('omits zero compounds from strain URL', () => {
		const url = MakerQR.buildLegacyUrl('strain', {
			name: 'Plain',
			psilocybin: 5000,
			psilocin: 0,
			norpsilocin: 0
		}, base);
		expect(url).toContain('psilocybin%3A5000');
		expect(url).not.toContain('psilocin%3A0');
	});

	it('builds an edible URL with type=edible and discrete params', () => {
		const url = MakerQR.buildLegacyUrl('edible', {
			name: 'Choco Bar',
			total_mg: 2000,
			pieces_per_package: 4,
			brand: 'Local',
			batch: 'B-42'
		}, base);
		const u = new URL(url);
		expect(u.searchParams.get('type')).toBe('edible');
		expect(u.searchParams.get('pname')).toBe('Choco Bar');
		expect(u.searchParams.get('name')).toBeNull();
		expect(u.searchParams.get('total_mg')).toBe('2000');
		expect(u.searchParams.get('pieces')).toBe('4');
		expect(u.searchParams.get('brand')).toBe('Local');
		expect(u.searchParams.get('batch')).toBe('B-42');
	});

	it('throws on unknown type', () => {
		expect(() => MakerQR.buildLegacyUrl('mystery', { name: 'X' }, base)).toThrow();
	});
});
