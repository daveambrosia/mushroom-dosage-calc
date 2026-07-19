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

describe('buildCompactUrl', () => {
	const base = 'https://example.com/calculator/';

	it('builds a strain URL with t_s, n_, and compound abbreviations', () => {
		const url = MakerQR.buildCompactUrl('strain', {
			name: 'Test',
			psilocybin: 10000,
			psilocin: 1000,
			norpsilocin: 222,
			baeocystin: 333,
			norbaeocystin: 444,
			aeruginascin: 555
		}, base);
		expect(url).toBe(base + '?d=t_s~n_Test~pb_10000~pc_1000~npc_222~b_333~nb_444~a_555');
	});

	it('omits zero compounds and missing optional fields', () => {
		const url = MakerQR.buildCompactUrl('strain', {
			name: 'Plain',
			psilocybin: 5000
		}, base);
		expect(url).toBe(base + '?d=t_s~n_Plain~pb_5000');
	});

	it('includes brand, batch, lab when present', () => {
		const url = MakerQR.buildCompactUrl('strain', {
			name: 'Premium',
			psilocybin: 8000,
			brand: 'Acme',
			batch: 'B42',
			lab: 'Caligreen'
		}, base);
		expect(url).toContain('~br_Acme');
		expect(url).toContain('~bn_B42');
		expect(url).toContain('~lb_Caligreen');
	});

	it('preserves dashes and dots in names (they are no longer separators)', () => {
		const url = MakerQR.buildCompactUrl('strain', {
			name: 'Foo-Bar.Co',
			psilocybin: 5000
		}, base);
		expect(url).toContain('n_Foo-Bar.Co');
	});

	it('builds an edible URL with t_e, tm, pp, and per-piece compounds', () => {
		const url = MakerQR.buildCompactUrl('edible', {
			name: 'ChocoBar',
			total_mg: 20,
			pieces_per_package: 4,
			psilocybin: 5000,
			brand: 'Local',
			batch: 'B-42'
		}, base);
		expect(url).toBe(
			base + '?d=t_e~n_ChocoBar~tm_20~pp_4~pb_5000~br_Local~bn_B-42'
		);
	});

	it('throws on unknown type', () => {
		expect(() => MakerQR.buildCompactUrl('mystery', { name: 'X' }, base)).toThrow();
	});
});
