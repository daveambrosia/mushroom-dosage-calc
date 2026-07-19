import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

// adc-math.js is an IIFE fragment of calculator.js, not a module, so build a
// harness: evaluate constants + a state stub + the math module inside a
// Function scope and return the pure functions under test. getUnitName reads
// state/getCurrentEdible, so the stub keeps edible selection empty (unit
// falls back to 'piece'/'pieces').
const MODULES = path.resolve(__dirname, '../../public/js/modules');
const src = [
	fs.readFileSync(path.join(MODULES, 'adc-constants.js'), 'utf8'),
	'const state = { unitMap: {}, edibleId: null, strainId: null };',
	fs.readFileSync(path.join(MODULES, 'adc-math.js'), 'utf8'),
	`return { getToleranceMultiplier, formatPieces, formatGrams,
		getTotalPsilocybin, calculateMushroomDose, calculateEdibleDose,
		EXPERIENCE_LEVELS, escapeHtml, clamp };`
].join('\n');
// eslint-disable-next-line no-new-func
const M = new Function(src)();

describe('getToleranceMultiplier', () => {
	// The published contract (readme.txt): 28+ days = 100%, 1 day = 200%,
	// linear in between. These are the exact shipped values.
	it('pins the documented endpoints', () => {
		expect(M.getToleranceMultiplier(28)).toBe(100);
		expect(M.getToleranceMultiplier(100)).toBe(100);
		expect(M.getToleranceMultiplier(1)).toBe(200);
		expect(M.getToleranceMultiplier(0)).toBe(200);
	});
	it('pins the linear interior values', () => {
		expect(M.getToleranceMultiplier(27)).toBe(101);
		expect(M.getToleranceMultiplier(14)).toBe(151);
		expect(M.getToleranceMultiplier(7)).toBe(177);
		expect(M.getToleranceMultiplier(2)).toBe(196);
	});
	it('never returns below 100 or above 200', () => {
		for (let d = 0; d <= 60; d++) {
			const m = M.getToleranceMultiplier(d);
			expect(m).toBeGreaterThanOrEqual(100);
			expect(m).toBeLessThanOrEqual(200);
		}
	});
	it('is monotonically non-increasing over days', () => {
		for (let d = 1; d < 40; d++) {
			expect(M.getToleranceMultiplier(d + 1)).toBeLessThanOrEqual(M.getToleranceMultiplier(d));
		}
	});
});

describe('dose math', () => {
	it('mushroom dose = target mcg / (psilocybin + psilocin) per gram', () => {
		// 150 lb at Intense minimum (50 mcg/lb) = 7500 mcg target.
		// 6200 psilocybin + 800 psilocin = 7000 mcg/g -> 1.0714... g
		const strain = { psilocybin: 6200, psilocin: 800 };
		expect(M.calculateMushroomDose(7500, strain)).toBeCloseTo(7500 / 7000, 10);
	});
	it('guards divide-by-zero: zero-potency product yields 0, not Infinity', () => {
		expect(M.calculateMushroomDose(7500, { psilocybin: 0, psilocin: 0 })).toBe(0);
		expect(M.calculateEdibleDose(7500, {})).toBe(0);
	});
	it('edible dose = target mcg / per-piece total', () => {
		// 5000 mcg/piece: 7500 mcg target -> 1.5 pieces
		expect(M.calculateEdibleDose(7500, { psilocybin: 5000 })).toBeCloseTo(1.5, 10);
	});
	it('getTotalPsilocybin sums only psilocybin and psilocin', () => {
		expect(M.getTotalPsilocybin({ psilocybin: 6000, psilocin: 500, baeocystin: 999 })).toBe(6500);
	});
});

describe('formatPieces (eighth-based rounding)', () => {
	it('formats halves and eighths', () => {
		expect(M.formatPieces(1.5)).toBe('1½ pieces');
		expect(M.formatPieces(0.125)).toBe('⅛ pieces');
		expect(M.formatPieces(2)).toBe('2 pieces');
		expect(M.formatPieces(1)).toBe('1 piece');
	});
	it('rounds to the nearest eighth', () => {
		expect(M.formatPieces(0.24)).toBe('¼ pieces');
		expect(M.formatPieces(1.06)).toBe('1 piece');
		expect(M.formatPieces(1.07)).toBe('1⅛ pieces');
	});
	it('reports below one eighth explicitly', () => {
		expect(M.formatPieces(0.05)).toBe('< ⅛ piece');
	});
});

describe('formatGrams (precision by magnitude)', () => {
	it('uses the documented display bands', () => {
		expect(M.formatGrams(0.005)).toBe('< 0.01g');
		expect(M.formatGrams(0.0523)).toBe('0.052g');
		expect(M.formatGrams(0.15)).toBe('0.15g');
		expect(M.formatGrams(1.234)).toBe('1.2g');
		expect(M.formatGrams(12.6)).toBe('13g');
	});
});

describe('experience level table', () => {
	it('pins the published mcg-per-lb ranges', () => {
		const byId = Object.fromEntries(M.EXPERIENCE_LEVELS.map(l => [l.id, l]));
		expect(byId.microdose.mcgPerLbMin).toBe(1);
		expect(byId.microdose.mcgPerLbMax).toBe(10);
		expect(byId.perceivable.mcgPerLbMax).toBe(50);
		expect(byId.intense.mcgPerLbMin).toBe(50);
		expect(byId.intense.mcgPerLbMax).toBe(100);
		expect(byId.profound.mcgPerLbMax).toBe(180);
		expect(byId.breakthrough.mcgPerLbMax).toBe(200);
	});
	it('levels are contiguous: each min equals the previous max', () => {
		const L = M.EXPERIENCE_LEVELS;
		for (let i = 1; i < L.length; i++) {
			expect(L[i].mcgPerLbMin).toBe(L[i - 1].mcgPerLbMax);
		}
	});
});

describe('escapeHtml', () => {
	it('escapes all HTML-significant characters', () => {
		expect(M.escapeHtml('<b>&"\'`')).toBe('&lt;b&gt;&amp;&quot;&#039;&#96;');
		expect(M.escapeHtml('')).toBe('');
		expect(M.escapeHtml(null)).toBe('');
	});
});
