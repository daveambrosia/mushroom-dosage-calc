/**
 * Math & Calculations — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // UTILITIES
    // ============================================================================
    
    const lbsToKg = (lbs) => lbs * 0.453592;
    const kgToLbs = (kg) => kg / 0.453592;
    const clamp = (val, min, max) => Math.min(Math.max(val, min), max);
    const formatNumber = (num) => Math.round(num).toLocaleString();
    
    /** Debounce utility: delays fn until ms after last call */
    function debounce(fn, ms) {
        let timer;
        return function(...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), ms);
        };
    }
    const debouncedUpdateAll = debounce(() => updateAll(), 150);
    
    function formatGrams(g) {
        if (g < 0.01) return '< 0.01g';
        if (g < 0.1) return g.toFixed(3) + 'g';
        if (g < 1) return g.toFixed(2) + 'g';
        if (g < 10) return g.toFixed(1) + 'g';
        return Math.round(g) + 'g';
    }
    
    function getUnitName(singular) {
        const edible = getCurrentEdible();
        if (!edible || !edible.productType) return singular ? 'piece' : 'pieces';
        const unit = (state.unitMap || {})[edible.productType];
        if (!unit) return singular ? 'piece' : 'pieces';
        if (singular) return unit.endsWith('s') ? unit.slice(0, -1) : unit;
        return unit;
    }

    function formatPieces(p) {
        if (p < 0.125) return '< ⅛ ' + getUnitName(true);
        const rounded = Math.round(p * 8) / 8;
        const fractions = { 0: '', 0.125: '⅛', 0.25: '¼', 0.375: '⅜', 0.5: '½', 0.625: '⅝', 0.75: '¾', 0.875: '⅞' };
        const whole = Math.floor(rounded);
        const frac = rounded - whole;
        const fracStr = fractions[frac] || '';
        let result = whole === 0 ? fracStr : (fracStr === '' ? whole.toString() : whole + fracStr);
        return result + ' ' + (rounded === 1 ? getUnitName(true) : getUnitName(false));
    }

    function getToleranceMultiplier(days) {
        // Linear: day 1 = 200%, day 27 = 101%, day 28+ = 100%
        if (days >= 28) return 100;
        if (days <= 1) return 200;
        // 99% drop over 26 steps (day 1 to day 27): 99/26 ≈ 3.808 per day
        return Math.max(100, Math.round(200 - ((days - 1) * (99 / 26))));
    }

    function groupBy(arr, keyFn) {
        return arr.reduce((acc, item) => {
            const key = keyFn(item);
            (acc[key] = acc[key] || []).push(item);
            return acc;
        }, {});
    }

    function formatCategoryLabel(slug) {
        return slug.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/`/g, '&#96;');
    }

    // ============================================================================
    // CALCULATIONS
    // ============================================================================
    
    function getCurrentStrain() {
        if (!state.strainId) return null;
        if (state.strainId === 'custom') return { name: 'Custom', ...state.customStrain };
        if (state.strainId.startsWith('user-') || state.strainId.startsWith('shared-')) return state.customStrains[state.strainId] || null;
        if (state.strainId.startsWith('scan-')) return state.scannedStrains[state.strainId] || null;
        const strain = state.strains.find(s => s.shortCode === state.strainId);
        if (strain) {
            return {
                name: strain.name,
                psilocybin: strain.compounds.psilocybin,
                psilocin: strain.compounds.psilocin,
                norpsilocin: strain.compounds.norpsilocin,
                baeocystin: strain.compounds.baeocystin,
                norbaeocystin: strain.compounds.norbaeocystin,
                aeruginascin: strain.compounds.aeruginascin,
                batchNumber: strain.batchNumber
            };
        }
        return null;
    }

    function getCurrentEdible() {
        if (!state.edibleId) return null;
        if (state.edibleId === 'custom') { const p = state.customEdible.piecesPerPackage || 1; return { name: 'Custom', psilocybin: Math.round(state.customEdible.psilocybin / p), psilocin: Math.round(state.customEdible.psilocin / p), norpsilocin: Math.round(state.customEdible.norpsilocin / p), baeocystin: Math.round(state.customEdible.baeocystin / p), norbaeocystin: Math.round(state.customEdible.norbaeocystin / p), aeruginascin: Math.round(state.customEdible.aeruginascin / p), piecesPerPackage: p }; }
        if (state.edibleId.startsWith('user-') || state.edibleId.startsWith('shared-')) return state.customEdibles[state.edibleId] || null;
        if (state.edibleId.startsWith('scan-')) return state.scannedEdibles[state.edibleId] || null;
        const edible = state.edibles.find(e => e.shortCode === state.edibleId);
        if (edible) {
            return {
                name: edible.name, brand: edible.brand, productType: edible.productType,
                psilocybin: edible.psilocybin || 0, psilocin: edible.psilocin || 0,
                norpsilocin: edible.norpsilocin || 0, baeocystin: edible.baeocystin || 0,
                norbaeocystin: edible.norbaeocystin || 0, aeruginascin: edible.aeruginascin || 0,
                piecesPerPackage: edible.piecesPerPackage || 1
            };
        }
        return null;
    }

    const getTotalPsilocybin = (p) => (p.psilocybin || 0) + (p.psilocin || 0);
    const calculateMushroomDose = (mcg, strain) => { const t = getTotalPsilocybin(strain); return t > 0 ? mcg / t : 0; };
    const calculateEdibleDose = (mcg, edible) => { const t = getTotalPsilocybin(edible); return t > 0 ? mcg / t : 0; };

    function getCompoundBreakdown(product, amountMin, amountMax) {
        const compounds = COMPOUNDS;
        const names = COMPOUND_NAMES;
        return compounds.filter(c => product[c] > 0).map(c => ({ name: names[c], key: c, perUnit: product[c], min: product[c] * amountMin, max: product[c] * amountMax }));
    }
