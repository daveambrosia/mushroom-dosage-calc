/**
 * IIFE Open — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

/**
 * Ambrosia Dosage Calculator v2.1
 * 
 * Psilocybin dosage calculator for mushrooms and edibles.
 * Supports lab-tested products, custom entries, QR code scanning, and sharing.
 * 
 * @package Ambrosia_Dosage_Calculator
 * @since 2.0.0
 */

(function() {
    'use strict';
/**
 * Constants — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // CONSTANTS
    // ============================================================================
    
    /**
     * Experience levels with dosage ranges (mcg psilocybin per lb body weight)
     */
    const EXPERIENCE_LEVELS = Object.freeze([
        { id: 'microdose', name: 'Microdose', icon: '○', mcgPerLbMin: 1, mcgPerLbMax: 10, description: 'Subtle effects without significant alterations in perception.' },
        { id: 'perceivable', name: 'Perceivable', icon: '◐', mcgPerLbMin: 10, mcgPerLbMax: 50, description: 'Noticeable changes in perception of environment and self.' },
        { id: 'intense', name: 'Intense', icon: '●', mcgPerLbMin: 50, mcgPerLbMax: 100, description: 'Stronger effects on perception and mood.' },
        { id: 'profound', name: 'Profound', icon: '◉', mcgPerLbMin: 100, mcgPerLbMax: 180, description: 'Spiritual or deeply introspective experiences.' },
        { id: 'breakthrough', name: 'Breakthrough', icon: '✦', mcgPerLbMin: 180, mcgPerLbMax: 200, description: 'Transformative spiritual visions and breakthrough experiences.' }
    ]);

    /**
     * LocalStorage keys
     */
    /**
     * localStorage schema version. Bump to invalidate all stored data on structure changes.
     */
    const STORAGE_VERSION = '1';
    const STORAGE_VERSION_KEY = 'adc-storage-version';
    
    const STORAGE_KEYS = Object.freeze({
        customStrains: 'adc-custom-strains',
        customEdibles: 'adc-custom-edibles',
        customStrain: 'adc-custom-strain-inline',
        customEdible: 'adc-custom-edible-inline',
        scannedStrains: 'adc-scanned-strains',
        scannedEdibles: 'adc-scanned-edibles',
        preferences: 'adc-preferences',
        dontkeep: 'adc-DONTKEEP'
    });

    /**
     * Compound names used throughout the calculator
     */
    const COMPOUNDS = Object.freeze(['psilocybin', 'psilocin', 'norpsilocin', 'baeocystin', 'norbaeocystin', 'aeruginascin']);
    
    const COMPOUND_NAMES = Object.freeze({
        psilocybin: 'Psilocybin',
        psilocin: 'Psilocin', 
        norpsilocin: 'Norpsilocin',
        baeocystin: 'Baeocystin',
        norbaeocystin: 'Norbaeocystin',
        aeruginascin: 'Aeruginascin'
    });

    /**
     * Check if custom values are empty (all zeros or unset)
     */
    const isCustomEmpty = (custom) => {
        return !custom || COMPOUNDS.every(c => !custom[c] || custom[c] === 0);
    };

    /**
     * Weight limits
     */
    const WEIGHT_LIMITS = Object.freeze({
        lbs: { min: 75, max: 600 },
        kg: { min: 34, max: 272 }
    });

    /**
     * Per-level collapse state
     */
    const collapsedLevels = { mushroom: new Set(), edible: new Set() };
    const LEVEL_COLLAPSE_KEY = 'adc_level_collapse_v1';
    const LEVEL_IDS = ['microdose', 'perceivable', 'intense', 'profound', 'breakthrough'];

    /**
     * Debug mode - set to true for verbose logging
     */
    const DEBUG = false;
/**
 * State — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // FOCUS TRAP UTILITY (F-002: Accessibility)
    // ============================================================================
    
    let previousFocusElement = null;
    let activeModalCleanup = null;

    // ============================================================================
    // STATE
    // ============================================================================
    
    const state = {
        activeTab: 'mushrooms',
        weightLbs: 150,
        displayUnit: 'lbs',
        daysSinceLastDose: 28,
        sensitivity: 100,
        strainId: '',
        customStrain: createEmptyCompounds(),
        edibleId: '',
        customEdible: { ...createEmptyCompounds(), piecesPerPackage: 0 },
        editingStrainId: null,
        editingEdibleId: null,
        strains: [],
        edibles: [],
        unitMap: {},
        customStrains: {},
        customEdibles: {},
        scannedStrains: {},
        scannedEdibles: {},
        storageConsent: false,
        config: {}
    };

    function createEmptyCompounds() {
        return { psilocybin: 0, psilocin: 0, norpsilocin: 0, baeocystin: 0, norbaeocystin: 0, aeruginascin: 0 };
    }
/**
 * Storage — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // STORAGE
    // ============================================================================
    
    function loadFromStorage(key, defaultValue = {}) {
        if (!state.storageConsent) return defaultValue;
        try {
            const data = localStorage.getItem(key);
            return data ? JSON.parse(data) : defaultValue;
        } catch (e) {
            console.warn('Storage load error:', key, e);
            return defaultValue;
        }
    }

    function saveToStorage(key, data) {
        if (!state.storageConsent || localStorage.getItem(STORAGE_KEYS.dontkeep) === 'true') return;
        try {
            localStorage.setItem(key, JSON.stringify(data));
        } catch (e) {
            console.warn('Storage save error:', key, e);
        }
    }

    function loadPreferences() {
        // Check storage version; clear stale data on version mismatch
        const storedVersion = localStorage.getItem(STORAGE_VERSION_KEY);
        if (storedVersion !== STORAGE_VERSION) {
            Object.values(STORAGE_KEYS).forEach(key => localStorage.removeItem(key));
            localStorage.setItem(STORAGE_VERSION_KEY, STORAGE_VERSION);
        }
        
        // Check if DONTKEEP is set - if so, user doesn't want storage
        const dontkeep = localStorage.getItem(STORAGE_KEYS.dontkeep);
        state.storageConsent = dontkeep !== 'true';
        const prefs = loadFromStorage(STORAGE_KEYS.preferences, {});
        state.weightLbs = prefs.weightLbs || 150;
        state.displayUnit = prefs.displayUnit || 'lbs';
        state.daysSinceLastDose = prefs.daysSinceLastDose != null ? prefs.daysSinceLastDose : 28;
        state.sensitivity = prefs.sensitivity || 100;
        state.strainId = prefs.lastStrain || '';
        state.edibleId = prefs.lastEdible || '';
        // activeTab is set from DOM in init(), not localStorage (URL params take precedence)
        state.customStrains = loadFromStorage(STORAGE_KEYS.customStrains, {});
        state.customEdibles = loadFromStorage(STORAGE_KEYS.customEdibles, {});
        // Load inline custom values
        const savedCustomStrain = loadFromStorage(STORAGE_KEYS.customStrain, null);
        const savedCustomEdible = loadFromStorage(STORAGE_KEYS.customEdible, null);
        if (savedCustomStrain) state.customStrain = { ...state.customStrain, ...savedCustomStrain };
        if (savedCustomEdible) state.customEdible = { ...state.customEdible, ...savedCustomEdible };
        state.scannedStrains = loadFromStorage(STORAGE_KEYS.scannedStrains, {});
        state.scannedEdibles = loadFromStorage(STORAGE_KEYS.scannedEdibles, {});
    }

    function savePreferences() {
        saveToStorage(STORAGE_KEYS.preferences, {
            weightLbs: state.weightLbs,
            displayUnit: state.displayUnit,
            daysSinceLastDose: state.daysSinceLastDose,
            sensitivity: state.sensitivity,
            lastStrain: state.strainId,
            lastEdible: state.edibleId
        });
    }

    function saveAllData() {
        // Save all current state to localStorage (used when "Remember settings" is checked)
        savePreferences();
        saveToStorage(STORAGE_KEYS.customStrains, state.customStrains);
        saveToStorage(STORAGE_KEYS.customEdibles, state.customEdibles);
        saveToStorage(STORAGE_KEYS.customStrain, state.customStrain);
        saveToStorage(STORAGE_KEYS.customEdible, state.customEdible);
        saveToStorage(STORAGE_KEYS.scannedStrains, state.scannedStrains);
        saveToStorage(STORAGE_KEYS.scannedEdibles, state.scannedEdibles);
    }

    // ============================================================================
    // PER-LEVEL COLLAPSE PERSISTENCE
    // ============================================================================

    function loadLevelCollapseState() {
        try {
            const raw = localStorage.getItem(LEVEL_COLLAPSE_KEY);
            if (!raw) {
                // No saved state — default: all levels collapsed
                ['mushroom', 'edible'].forEach(type => {
                    LEVEL_IDS.forEach(id => collapsedLevels[type].add(id));
                });
                return;
            }
            const saved = JSON.parse(raw);
            ['mushroom', 'edible'].forEach(type => {
                collapsedLevels[type].clear();
                (saved[type] || []).forEach(id => collapsedLevels[type].add(id));
            });
        } catch (e) {
            // On error, default to all collapsed
            ['mushroom', 'edible'].forEach(type => {
                LEVEL_IDS.forEach(id => collapsedLevels[type].add(id));
            });
        }
    }

    function saveLevelCollapseState() {
        const consent = document.getElementById('adc-storage-consent');
        if (!consent || !consent.checked) return;
        try {
            localStorage.setItem(LEVEL_COLLAPSE_KEY, JSON.stringify({
                mushroom: [...collapsedLevels.mushroom],
                edible:   [...collapsedLevels.edible],
            }));
        } catch (e) { /* ignore */ }
    }
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
/**
 * DOM Elements — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // DOM ELEMENTS
    // ============================================================================
    
    let elements = {};

    function cacheElements() {
        const $ = (sel) => document.querySelector(sel);
        const $$ = (sel) => document.querySelectorAll(sel);
        elements = {
            calculator: $('#adc-calculator'),
            tabMushrooms: $('#adc-tab-mushrooms'),
            tabEdibles: $('#adc-tab-edibles'),
            contentMushrooms: $('#adc-content-mushrooms'),
            contentEdibles: $('#adc-content-edibles'),
            weightInput: $('#adc-weight'),
            unitToggle: $$('.adc-unit-toggle button'),
            toleranceSelect: $('#adc-tolerance'),
            toleranceDisplay: $('#adc-tolerance-display'),
            sensitivitySlider: $('#adc-sensitivity-slider'),
            sensitivityInput: $('#adc-sensitivity-input'),
            strainSelect: $('#adc-strain-select'),
            strainPotency: $('#adc-strain-potency'),
            strainControls: $('#adc-strain-controls'),
            customStrainWrapper: $('#adc-custom-strain-wrapper'),
            edibleSelect: $('#adc-edible-select'),
            edibleInfo: $('#adc-edible-info'),
            edibleControls: $('#adc-edible-controls'),
            customEdibleWrapper: $('#adc-custom-edible-wrapper'),
            mushroomResults: $('#adc-mushroom-results'),
            edibleResults: $('#adc-edible-results'),
            mushroomSummary: $('#adc-mushroom-summary'),
            edibleSummary: $('#adc-edible-summary'),
            converterSection: $('#adc-converter-section'),
            converterStrain: $('#adc-converter-strain'),
            mcgInput: $('#adc-mcg-input'),
            gramsInput: $('#adc-grams-input'),
            converterBreakdown: $('#adc-converter-breakdown'),
            storageConsent: $('#adc-storage-consent'),
            strainModal: $('#adc-strain-modal'),
            edibleModal: $('#adc-edible-modal')
        };
    }
/**
 * UI Rendering — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // UI RENDERING
    // ============================================================================
    
    function updateWeightDisplay() {
        if (!elements.weightInput) return;
        elements.weightInput.value = state.displayUnit === 'lbs' ? Math.round(state.weightLbs) : Math.round(lbsToKg(state.weightLbs));
    }

    function updateToleranceDisplay() {
        if (!elements.toleranceDisplay) return;
        const tolMult = getToleranceMultiplier(state.daysSinceLastDose);
        const sensMult = state.sensitivity;
        // Combined: (tolerance% * sensitivity%) / 100
        const combined = Math.round((tolMult * sensMult) / 100);
        elements.toleranceDisplay.textContent = combined + '%';
        
        // Color based on the final combined percentage
        let level = 'low'; // 91% to 119% (Green)
        if (combined <= 90) {
            level = 'microdose'; // Under 90% (Blue)
        } else if (combined >= 150) {
            level = 'high'; // 150% and over (Red)
        } else if (combined >= 120) {
            level = 'medium'; // 120% to 149% (Yellow)
        }
        elements.toleranceDisplay.className = 'adc-tolerance-display ' + level;

        // Toggle red background on results grid when any adjustment is active
        updateResultsGridWarning();
    }

    function updateResultsGridWarning() {
        const grid = document.querySelector('.adc-results-grid');
        if (!grid) return;
        const adjusted = state.daysSinceLastDose < 28 || state.sensitivity !== 100;
        grid.classList.toggle('adc-tolerance-active', adjusted);
    }

    function updateSensitivityDisplay() {
        if (elements.sensitivitySlider) elements.sensitivitySlider.value = state.sensitivity;
        if (elements.sensitivityInput) elements.sensitivityInput.value = state.sensitivity;
    }

    function populateStrainSelect() {
        if (!elements.strainSelect) return;
        // Show loading state when data hasn't arrived yet
        if (state.strains.length === 0 && !state._strainsLoaded) {
            elements.strainSelect.innerHTML = '<option value="" disabled selected>Loading strains...</option>';
            return;
        }
        let html = '<option value="" disabled selected>Select a strain...</option>';
        if (state.config.allowCustom) html += '<option value="custom">✏️ Custom (Enter Values)</option>';
        if (state.strains.length > 0) {
            const grouped = groupBy(state.strains, s => s.category || 'uncategorized');
            for (const [cat, strains] of Object.entries(grouped)) {
                html += `<optgroup label="${formatCategoryLabel(cat)}">`;
                strains.forEach(s => { html += `<option value="${s.shortCode}">${escapeHtml(s.name)}</option>`; });
                html += '</optgroup>';
            }
        }
        const scannedIds = Object.keys(state.scannedStrains);
        if (scannedIds.length > 0) {
            html += '<optgroup label="Scanned Strains">';
            scannedIds.forEach(id => { html += `<option value="${id}">${escapeHtml(state.scannedStrains[id].name)}</option>`; });
            html += '</optgroup>';
        }
        const customIds = Object.keys(state.customStrains);
        if (customIds.length > 0) {
            html += '<optgroup label="My Strains">';
            customIds.forEach(id => { html += `<option value="${id}">${escapeHtml(state.customStrains[id].name)}</option>`; });
            html += '</optgroup>';
        }
        elements.strainSelect.innerHTML = html;
        if (state.strainId) {
            // If custom is selected but values are empty, revert to no selection
            if (state.strainId === 'custom' && isCustomEmpty(state.customStrain)) {
                state.strainId = '';
                elements.strainSelect.value = '';
                if (elements.customStrainWrapper) elements.customStrainWrapper.style.display = 'none';
            } else {
                elements.strainSelect.value = state.strainId;
                if (elements.customStrainWrapper) {
                    elements.customStrainWrapper.style.display = state.strainId === 'custom' ? '' : 'none';
                    if (state.strainId === 'custom') {
                        // Populate inputs with saved values
                        elements.customStrainWrapper.querySelectorAll('input[data-compound]').forEach(input => {
                            const c = input.dataset.compound;
                            input.value = state.customStrain[c] || '';
                        });
                    }
                }
            }
        }
    }

    function populateEdibleSelect() {
        if (!elements.edibleSelect) return;
        if (state.edibles.length === 0 && !state._ediblesLoaded) {
            elements.edibleSelect.innerHTML = '<option value="" disabled selected>Loading edibles...</option>';
            return;
        }
        let html = '<option value="" disabled selected>Select an edible...</option>';
        if (state.config.allowCustom) html += '<option value="custom">✏️ Custom (Enter Values)</option>';
        // Show helpful message when no preset edibles available
        if (state.edibles.length === 0 && Object.keys(state.scannedEdibles).length === 0 && Object.keys(state.customEdibles).length === 0) {
            html += '<option disabled>─ No preset edibles available ─</option>';
        }
        if (state.edibles.length > 0) {
            const grouped = groupBy(state.edibles, e => e.productType || 'other');
            for (const [type, edibles] of Object.entries(grouped)) {
                html += `<optgroup label="${formatCategoryLabel(type)}">`;
                edibles.forEach(e => {
                    const brandStr = e.brand ? ` (${escapeHtml(e.brand)})` : '';
                    html += `<option value="${e.shortCode}">${escapeHtml(e.name)}${brandStr}</option>`;
                });
                html += '</optgroup>';
            }
        }
        const scannedIds = Object.keys(state.scannedEdibles);
        if (scannedIds.length > 0) {
            html += '<optgroup label="Scanned Edibles">';
            scannedIds.forEach(id => { html += `<option value="${id}">${escapeHtml(state.scannedEdibles[id].name)}</option>`; });
            html += '</optgroup>';
        }
        const customIds = Object.keys(state.customEdibles);
        if (customIds.length > 0) {
            html += '<optgroup label="My Edibles">';
            customIds.forEach(id => { html += `<option value="${id}">${escapeHtml(state.customEdibles[id].name)}</option>`; });
            html += '</optgroup>';
        }
        elements.edibleSelect.innerHTML = html;
        if (state.edibleId) {
            // If custom is selected but values are empty, revert to no selection
            if (state.edibleId === 'custom' && isCustomEmpty(state.customEdible)) {
                state.edibleId = '';
                elements.edibleSelect.value = '';
                if (elements.customEdibleWrapper) elements.customEdibleWrapper.style.display = 'none';
            } else {
                elements.edibleSelect.value = state.edibleId;
                if (elements.customEdibleWrapper) {
                    elements.customEdibleWrapper.style.display = state.edibleId === 'custom' ? '' : 'none';
                    if (state.edibleId === 'custom') {
                        // Populate inputs with saved values
                        elements.customEdibleWrapper.querySelectorAll('input[data-compound]').forEach(input => {
                            const c = input.dataset.compound;
                            input.value = state.customEdible[c] || '';
                        });
                        const piecesInput = elements.customEdibleWrapper.querySelector('input[data-field="piecesPerPackage"]');
                        if (piecesInput && state.customEdible.piecesPerPackage) piecesInput.value = state.customEdible.piecesPerPackage;
                    }
                }
            }
        }
    }

    function updateStrainPotency() {
        if (!elements.strainPotency) return;
        const strain = getCurrentStrain();
        if (!strain) { elements.strainPotency.innerHTML = ''; elements.strainPotency.style.display = 'none'; return; }
        elements.strainPotency.style.display = '';
        const total = getTotalPsilocybin(strain);
        const compounds = [
            { name: 'Psilocybin', value: strain.psilocybin }, { name: 'Psilocin', value: strain.psilocin },
            { name: 'Norpsilocin', value: strain.norpsilocin }, { name: 'Baeocystin', value: strain.baeocystin },
            { name: 'Norbaeocystin', value: strain.norbaeocystin }, { name: 'Aeruginascin', value: strain.aeruginascin }
        ].filter(c => c.value > 0);
        // Compound rows — same structure as adc-tryptamine-breakdown
        const compoundRows = compounds.map(c =>
            `<div class="adc-tryptamine-row"><span class="adc-compound-name">${c.name}:</span><span class="adc-compound-value">${formatNumber(c.value)} mcg/g</span></div>`
        ).join('');
        // Total row using same pattern, with updated label
        const totalRow = `<div class="adc-tryptamine-row"><span class="adc-compound-name">Total (Psilocybin + Psilocin):</span><span class="adc-compound-value">${formatNumber(total)} mcg/g</span></div>`;
        // Wrap in adc-tryptamine-breakdown.visible so it uses the same 2-col grid layout
        elements.strainPotency.innerHTML = `<div class="adc-tryptamine-breakdown visible">${compoundRows}${totalRow}</div>`;
    }

    function updateEdibleInfo() {
        if (!elements.edibleInfo) return;
        const edible = getCurrentEdible();
        if (!edible) { elements.edibleInfo.innerHTML = ''; elements.edibleInfo.style.display = 'none'; return; }
        elements.edibleInfo.style.display = '';
        
        let html = '';
        if (edible.brand) html += `<div class="adc-edible-info-row"><span>Brand:</span><span>${escapeHtml(edible.brand)}</span></div>`;
        const unitLabel = getUnitName(false).charAt(0).toUpperCase() + getUnitName(false).slice(1);
        html += `<div class="adc-edible-info-row"><span>${unitLabel}/Pkg:</span><span>${edible.piecesPerPackage}</span></div>`;
        
        // Show individual compounds per piece
        if (edible.psilocybin) html += `<div class="adc-edible-info-row"><span>Psilocybin:</span><span>${formatNumber(edible.psilocybin)} mcg</span></div>`;
        if (edible.psilocin) html += `<div class="adc-edible-info-row"><span>Psilocin:</span><span>${formatNumber(edible.psilocin)} mcg</span></div>`;
        if (edible.norpsilocin) html += `<div class="adc-edible-info-row"><span>Norpsilocin:</span><span>${formatNumber(edible.norpsilocin)} mcg</span></div>`;
        if (edible.baeocystin) html += `<div class="adc-edible-info-row"><span>Baeocystin:</span><span>${formatNumber(edible.baeocystin)} mcg</span></div>`;
        if (edible.norbaeocystin) html += `<div class="adc-edible-info-row"><span>Norbaeocystin:</span><span>${formatNumber(edible.norbaeocystin)} mcg</span></div>`;
        if (edible.aeruginascin) html += `<div class="adc-edible-info-row"><span>Aeruginascin:</span><span>${formatNumber(edible.aeruginascin)} mcg</span></div>`;
        
        // Total Psilo = psilocybin + psilocin per piece
        const totalPsilo = (edible.psilocybin || 0) + (edible.psilocin || 0);
        html += `<div class="adc-edible-info-row total"><span>Total Psilo:</span><span>${formatNumber(totalPsilo)} mcg</span></div>`;
        
        elements.edibleInfo.innerHTML = html;
    }

    function renderResultCard(level, doseMin, doseMax, mcgMin, mcgMax, breakdown, shareData, prefix = '', doseLabel = '', doseLabelShort = '') {
        let breakdownHtml = '';
        if (breakdown.length && state.config.showCompoundBreakdown) {
            breakdownHtml = `<div class="adc-tryptamine-breakdown adc-breakdown-visible">${breakdown.map(b => `<div class="adc-tryptamine-row"><span class="adc-compound-name">${b.name}:</span><span class="adc-compound-value">${formatNumber(b.min)} – ${formatNumber(b.max)} mcg</span></div>`).join('')}</div>`;
        }
        return `<div class="adc-result-card ${level.id}" data-level-id="${level.id}">
            <div class="adc-card-main">
                <div class="adc-card-left">
                    <div class="adc-level-name">
                        <span class="adc-level-icon" aria-hidden="true">${level.icon}</span>
                        <span class="adc-level-name-text">${level.name}</span>
                        <span class="adc-level-mcg-inline" aria-hidden="true"><span class="adc-nowrap"><b>ACTIVE:</b> ${formatNumber(mcgMin)}–${formatNumber(mcgMax)} mcg</span> <span class="adc-collapse-sep">·</span> <span class="adc-nowrap">${doseLabelShort ? '<b>' + doseLabelShort + ':</b> ' : ''}${doseMin} – ${doseMax}</span></span>
                        <button type="button" class="adc-level-collapse-btn" aria-expanded="true" aria-label="Collapse ${level.name}">&#9662;</button>
                    </div>
                    <div class="adc-dose-info">
                        <div class="adc-dosage">${doseLabel ? '<span class="adc-dose-label"><b>' + doseLabel + ':</b></span> ' : ''}<span class="adc-nowrap">${doseMin} – ${doseMax}</span></div>
                        <div class="adc-psilocybin-range"><b>ACTIVE:</b> <span class="adc-nowrap">${formatNumber(mcgMin)} – ${formatNumber(mcgMax)} mcg</span></div>
                    </div>
                </div>
            </div>
            <div class="adc-description">${level.description}</div>
            ${breakdownHtml}
            <div class="adc-card-footer">
                <button type="button" class="adc-share-btn" data-share="${shareData}">📤&nbsp; Share</button>
            </div>
        </div>`;
    }
    function createShareData(type, productName, level, doseMin, doseMax, mcgMin, mcgMax, breakdown, productId, productData) {
        const isCustom = productId.startsWith('user-') || productId.startsWith('scan-') || productId.startsWith('shared-');
        const data = {
            type, product: String(productName), level: level.name,
            dose: `${doseMin} to ${doseMax}`, mcg: `${formatNumber(mcgMin)} to ${formatNumber(mcgMax)} mcg`,
            breakdown: breakdown.map(b => ({ name: b.name, amount: `${formatNumber(b.min)} to ${formatNumber(b.max)} mcg` })),
            [type === 'mushroom' ? 'strainId' : 'edibleId']: productId,
            [type === 'mushroom' ? 'strainData' : 'edibleData']: isCustom ? productData : null
        };
        return btoa(encodeURIComponent(JSON.stringify(data)));
    }

    function reapplyLevelCollapseState(type) {
        const containerId = type === 'mushroom' ? 'adc-mushroom-results' : 'adc-edible-results';
        const container = document.getElementById(containerId);
        if (!container) return;
        container.querySelectorAll('.adc-result-card').forEach(card => {
            const levelId = card.dataset.levelId;
            if (!levelId) return;
            const btn = card.querySelector('.adc-level-collapse-btn');
            if (collapsedLevels[type].has(levelId)) {
                card.classList.add('adc-level-collapsed');
                // Add tooltip with description + "Click to expand"
                const descEl = card.querySelector('.adc-description');
                if (descEl) {
                    const descText = descEl.textContent.trim();
                    card.setAttribute('title', descText + '\n\nClick to expand.');
                }
                if (btn) {
                    btn.innerHTML = '&#9652;'; // ▴
                    btn.setAttribute('aria-expanded', 'false');
                    btn.setAttribute('aria-label', 'Expand ' + (card.querySelector('.adc-level-name-text')?.textContent || ''));
                }
            } else {
                card.classList.remove('adc-level-collapsed');
                // Remove tooltip when expanded
                card.removeAttribute('title');
                if (btn) {
                    btn.innerHTML = '&#9662;'; // ▾
                    btn.setAttribute('aria-expanded', 'true');
                }
            }
        });
        // Check if collapsed inline text wraps; hide bullet separator when it does
        updateCollapsedBullets(container);
    }

    /**
     * Hide the · bullet in collapsed cards when the text wraps to two lines.
     * Compares the top offset of the first and last .adc-nowrap spans.
     */
    function updateCollapsedBullets(container) {
        if (!container) return;
        container.querySelectorAll('.adc-result-card.adc-level-collapsed').forEach(card => {
            const spans = card.querySelectorAll('.adc-level-mcg-inline .adc-nowrap');
            const sep = card.querySelector('.adc-collapse-sep');
            if (!sep || spans.length < 2) return;
            // If the two spans have different top positions, they're on different lines
            const firstTop = spans[0].getBoundingClientRect().top;
            const lastTop = spans[spans.length - 1].getBoundingClientRect().top;
            sep.style.display = (Math.abs(firstTop - lastTop) > 2) ? 'none' : '';
        });
    }

    // Re-check bullets on window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            ['adc-mushroom-results', 'adc-edible-results'].forEach(id => {
                updateCollapsedBullets(document.getElementById(id));
            });
        }, 100);
    });

    /**
     * Render tolerance/sensitivity adjustment summary into a target element.
     * Used by both mushroom and edible results.
     */
    function renderAdjustmentSummary(el) {
        if (!el) return;
        const tolMult = getToleranceMultiplier(state.daysSinceLastDose);
        const adj = [];
        if (tolMult !== 100) {
            const hint = tolMult < 100 ? ' (Need LESS than others)' : ' (Need MORE than others)';
            adj.push(`Tolerance: ${tolMult}%${hint}`);
        }
        if (state.sensitivity !== 100) {
            const hint = state.sensitivity < 100 ? ' (Need LESS than others)' : ' (Need MORE than others)';
            adj.push(`Sensitivity: ${state.sensitivity}%${hint}`);
        }
        el.innerHTML = adj.length > 0 ? adj.join(' &bull; ') : '';
        el.style.display = adj.length > 0 ? '' : 'none';
    }

    function updateMushroomResults() {
        if (!elements.mushroomResults) return;
        const strain = getCurrentStrain();
        const section = elements.mushroomResults.closest('.adc-results-section');
        if (!strain) {
            elements.mushroomResults.innerHTML = '';
            if (elements.mushroomSummary) elements.mushroomSummary.innerHTML = '';
            if (section) section.classList.add('adc-section-hidden');
            return;
        }
        if (section) section.classList.remove('adc-section-hidden');
        const tolMult = getToleranceMultiplier(state.daysSinceLastDose) / 100;
        const sensMult = state.sensitivity / 100;
        const totalMult = tolMult * sensMult;
        const html = EXPERIENCE_LEVELS.map(level => {
            const mcgMin = level.mcgPerLbMin * state.weightLbs * totalMult;
            const mcgMax = level.mcgPerLbMax * state.weightLbs * totalMult;
            const gramsMin = calculateMushroomDose(mcgMin, strain);
            const gramsMax = calculateMushroomDose(mcgMax, strain);
            const breakdown = getCompoundBreakdown(strain, gramsMin, gramsMax);
            const shareData = createShareData('mushroom', strain.name, level, formatGrams(gramsMin), formatGrams(gramsMax), mcgMin, mcgMax, breakdown, state.strainId, strain);
            return renderResultCard(level, formatGrams(gramsMin), formatGrams(gramsMax), mcgMin, mcgMax, breakdown, shareData, '', 'DRY WEIGHT 🍄', 'DW🍄');
        }).join('');
        elements.mushroomResults.innerHTML = html;
        // Summary
        renderAdjustmentSummary(elements.mushroomSummary);
        reapplyLevelCollapseState('mushroom');
    }

    function updateEdibleResults() {
        if (!elements.edibleResults) return;
        const edible = getCurrentEdible();
        const section = elements.edibleResults.closest('.adc-results-section');
        if (!edible) {
            elements.edibleResults.innerHTML = '';
            if (elements.edibleSummary) elements.edibleSummary.innerHTML = '';
            if (section) section.classList.add('adc-section-hidden');
            return;
        }
        if (section) section.classList.remove('adc-section-hidden');
        const tolMult = getToleranceMultiplier(state.daysSinceLastDose) / 100;
        const sensMult = state.sensitivity / 100;
        const totalMult = tolMult * sensMult;
        const html = EXPERIENCE_LEVELS.map(level => {
            const mcgMin = level.mcgPerLbMin * state.weightLbs * totalMult;
            const mcgMax = level.mcgPerLbMax * state.weightLbs * totalMult;
            const piecesMin = calculateEdibleDose(mcgMin, edible);
            const piecesMax = calculateEdibleDose(mcgMax, edible);
            const breakdown = getCompoundBreakdown(edible, piecesMin, piecesMax);
            const productName = edible.brand ? `${edible.brand} ${edible.name}` : edible.name;
            const shareData = createShareData('edible', productName, level, formatPieces(piecesMin), formatPieces(piecesMax), mcgMin, mcgMax, breakdown, state.edibleId, edible);
            return renderResultCard(level, formatPieces(piecesMin), formatPieces(piecesMax), mcgMin, mcgMax, breakdown, shareData, 'edible-', '');
        }).join('');
        elements.edibleResults.innerHTML = html;
        // Summary
        renderAdjustmentSummary(elements.edibleSummary);
        reapplyLevelCollapseState('edible');
    }

    function updateConverter() {
        if (!elements.converterStrain || !state.config.showQuickConverter) return;
        const strain = getCurrentStrain();
        if (!strain) { if (elements.converterSection) elements.converterSection.style.display = 'none'; return; }
        if (elements.converterSection) elements.converterSection.style.display = '';
        elements.converterStrain.textContent = `Using: ${strain.name} (${formatNumber(getTotalPsilocybin(strain))} mcg/g)`;
        
        // Recalculate conversion when strain changes (if there's a value entered)
        const mcgVal = parseFloat(elements.mcgInput?.value) || 0;
        const gramsVal = parseFloat(elements.gramsInput?.value) || 0;
        const total = getTotalPsilocybin(strain);
        
        if (mcgVal > 0) {
            // Recalculate grams from mcg
            const newGrams = mcgVal / total;
            if (elements.gramsInput) elements.gramsInput.value = newGrams > 0 ? newGrams.toFixed(3) : '';
            updateConverterBreakdown(newGrams);
        } else if (gramsVal > 0) {
            // Recalculate mcg from grams
            const newMcg = gramsVal * total;
            if (elements.mcgInput) elements.mcgInput.value = newMcg > 0 ? Math.round(newMcg) : '';
            updateConverterBreakdown(gramsVal);
        }
    }

    function updateStrainControls() {
        if (!elements.strainControls) return;
        const editBtn = elements.strainControls.querySelector('.adc-edit-btn');
        const deleteBtn = elements.strainControls.querySelector('.adc-delete-btn');
        const isCustom = state.strainId.startsWith('user-') || state.strainId.startsWith('shared-');
        const isScanned = state.strainId.startsWith('scan-');
        if (editBtn) editBtn.style.display = isCustom ? '' : 'none';
        if (deleteBtn) deleteBtn.style.display = (isCustom || isScanned) ? '' : 'none';
    }

    function updateEdibleControls() {
        if (!elements.edibleControls) return;
        const editBtn = elements.edibleControls.querySelector('.adc-edit-btn');
        const deleteBtn = elements.edibleControls.querySelector('.adc-delete-btn');
        const isCustom = state.edibleId.startsWith('user-') || state.edibleId.startsWith('shared-');
        const isScanned = state.edibleId.startsWith('scan-');
        if (editBtn) editBtn.style.display = isCustom ? '' : 'none';
        if (deleteBtn) deleteBtn.style.display = (isCustom || isScanned) ? '' : 'none';
    }

    function updateAll() {
        updateWeightDisplay();
        updateToleranceDisplay();
        updateSensitivityDisplay();
        updateStrainPotency();
        updateStrainControls();
        updateEdibleInfo();
        updateEdibleControls();
        if (state.activeTab === 'mushrooms') { updateMushroomResults(); updateConverter(); }
        else { updateEdibleResults(); }
    }
/**
 * Modals & Sharing — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // FOCUS TRAP UTILITY (F-002: Accessibility)
    // ============================================================================
    
    /**
     * Trap focus within a modal element
     * @param {HTMLElement} modal - The modal element to trap focus in
     * @returns {Function} Cleanup function to remove event listeners
     */
    function trapFocus(modal) {
        const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
        const focusableElements = modal.querySelectorAll(focusableSelector);
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];
        
        // Store previous focus to restore later
        previousFocusElement = document.activeElement;
        
        // Focus first element
        if (firstFocusable) {
            setTimeout(() => firstFocusable.focus(), 50);
        }
        
        // Handle keyboard navigation
        const handleKeydown = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                // Find and click the close button
                const closeBtn = modal.querySelector('.adc-modal-close');
                if (closeBtn) closeBtn.click();
                return;
            }
            
            if (e.key !== 'Tab') return;
            
            if (e.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    e.preventDefault();
                    lastFocusable.focus();
                }
            } else {
                if (document.activeElement === lastFocusable) {
                    e.preventDefault();
                    firstFocusable.focus();
                }
            }
        };
        
        modal.addEventListener('keydown', handleKeydown);
        
        // Set ARIA attributes
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('role', 'dialog');
        
        // Hide main content from screen readers
        const calculator = document.getElementById('adc-calculator');
        if (calculator) calculator.setAttribute('aria-hidden', 'true');
        
        // Return cleanup function
        return () => {
            modal.removeEventListener('keydown', handleKeydown);
            modal.removeAttribute('aria-modal');
            if (calculator) calculator.removeAttribute('aria-hidden');
            // Restore focus
            if (previousFocusElement && previousFocusElement.focus) {
                previousFocusElement.focus();
            }
            previousFocusElement = null;
        };
    }

    /**
     * F-007: Update sensitivity slider aria-valuetext
     */
    function updateSensitivityAria(value) {
        const slider = document.getElementById('adc-sensitivity-slider');
        if (!slider) return;
        let description;
        if (value < 50) description = 'Very sensitive (lower doses needed)';
        else if (value < 80) description = 'Sensitive';
        else if (value <= 120) description = 'Normal sensitivity';
        else if (value <= 180) description = 'Tolerant';
        else description = 'Very tolerant (higher doses needed)';
        slider.setAttribute('aria-valuetext', value + '% - ' + description);
    }

    /**
     * F-010: Enhanced share functionality with Web Share API
     */
    /**
     * Show a lightweight toast notification (auto-dismisses)
     */
    function showToast(message, duration = 2000) {
        const toast = document.createElement('div');
        toast.className = 'adc-toast';
        toast.textContent = message;
        (document.getElementById('adc-calculator') || document.body).appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('adc-toast-show'));
        setTimeout(() => {
            toast.classList.remove('adc-toast-show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    async function handleShareAction(data, encodedData) {
        const baseUrl = window.location.origin + window.location.pathname;
        const shareUrl = baseUrl + '?share=' + encodeURIComponent(encodedData);
        const shareText = `🍄 ${data.product || 'Mushroom'} Dose: ${data.dose} for ${data.level} experience (${data.mcg})`;
        
        // Create share modal
        const modal = document.createElement('div');
        modal.className = 'adc-share-modal-overlay';
        modal.innerHTML = `
            <div class="adc-share-modal">
                <div class="adc-share-modal-header">
                    <h3>📤 Share This Dose</h3>
                    <button type="button" class="adc-modal-close" aria-label="Close">X</button>
                </div>
                <div class="adc-share-modal-body">
                    <div class="adc-share-preview">
                        <div class="adc-share-product">${escapeHtml(data.product || 'Unknown')}</div>
                        <div class="adc-share-level">${escapeHtml(data.level)}</div>
                        <div class="adc-share-dose-display">${escapeHtml(data.dose)}</div>
                        <div class="adc-share-mcg">${escapeHtml(data.mcg)}</div>
                    </div>
                    <div class="adc-share-actions">
                        <button type="button" class="adc-btn adc-btn-primary adc-copy-text-btn">📋 Copy Text</button>
                        <button type="button" class="adc-btn adc-copy-link-btn">🔗 Copy Link</button>
                        ${navigator.share ? '<button type="button" class="adc-btn adc-native-share-btn">📤 Share...</button>' : ''}
                    </div>
                    <div class="adc-share-feedback" style="display:none;"></div>
                </div>
            </div>
        `;
        // Append to calculator container so @scope CSS applies
        (document.getElementById('adc-calculator') || document.body).appendChild(modal);
        
        // Set up focus trap
        const cleanup = trapFocus(modal);
        
        // Event handlers
        const closeModal = () => {
            cleanup();
            modal.remove();
        };
        
        modal.querySelector('.adc-modal-close').addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        
        modal.querySelector('.adc-copy-text-btn').addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(shareText);
                showToast('✓ Text copied!');
            } catch { showToast('Failed to copy'); }
        });
        
        modal.querySelector('.adc-copy-link-btn').addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(shareUrl);
                showToast('✓ Link copied!');
            } catch { showToast('Failed to copy'); }
        });
        
        const nativeBtn = modal.querySelector('.adc-native-share-btn');
        if (nativeBtn) {
            nativeBtn.addEventListener('click', async () => {
                try {
                    await navigator.share({
                        title: 'Mushroom Dose Calculator',
                        text: shareText,
                        url: shareUrl
                    });
                    closeModal();
                } catch (err) {
                    if (err.name !== 'AbortError') showToast('Share cancelled');
                }
            });
        }
    }

    // ============================================================================
    // MODALS
    // ============================================================================
    
    function openStrainModal(editId = null) {
        const modal = elements.strainModal;
        if (!modal) return;
        const title = modal.querySelector('#adc-strain-modal-title');
        const nameInput = modal.querySelector('#adc-modal-strain-name');
        const compounds = COMPOUNDS;
        if (editId && state.customStrains[editId]) {
            const strain = state.customStrains[editId];
            title.textContent = 'Edit Custom Strain';
            nameInput.value = strain.name || '';
            compounds.forEach(c => { const input = modal.querySelector(`#adc-modal-${c}`); if (input) input.value = strain[c] || ''; });
            state.editingStrainId = editId;
        } else if (state.strainId === 'custom' && (state.customStrain.psilocybin || state.customStrain.psilocin)) {
            // Copy values from inline custom form
            title.textContent = 'Add Custom Strain';
            nameInput.value = '';
            compounds.forEach(c => { const input = modal.querySelector(`#adc-modal-${c}`); if (input) input.value = state.customStrain[c] || ''; });
            state.editingStrainId = null;
        } else {
            title.textContent = 'Add Custom Strain';
            nameInput.value = '';
            compounds.forEach(c => { const input = modal.querySelector(`#adc-modal-${c}`); if (input) input.value = ''; });
            state.editingStrainId = null;
        }
        modal.classList.add('show');
        // F-002: Set up focus trap
        if (activeModalCleanup) activeModalCleanup();
        activeModalCleanup = trapFocus(modal);
    }

    function closeStrainModal() { 
        if (elements.strainModal) elements.strainModal.classList.remove('show');
        // F-002: Clean up focus trap and restore focus
        if (activeModalCleanup) { activeModalCleanup(); activeModalCleanup = null; }
    }

    function saveStrain() {
        const modal = elements.strainModal;
        if (!modal) return;
        const name = modal.querySelector('#adc-modal-strain-name').value.trim();
        if (!name) { adcError('Please enter a strain name'); return; }
        const strainData = {
            name,
            psilocybin: parseInt(modal.querySelector('#adc-modal-psilocybin').value) || 0,
            psilocin: parseInt(modal.querySelector('#adc-modal-psilocin').value) || 0,
            norpsilocin: parseInt(modal.querySelector('#adc-modal-norpsilocin').value) || 0,
            baeocystin: parseInt(modal.querySelector('#adc-modal-baeocystin').value) || 0,
            norbaeocystin: parseInt(modal.querySelector('#adc-modal-norbaeocystin').value) || 0,
            aeruginascin: parseInt(modal.querySelector('#adc-modal-aeruginascin').value) || 0
        };
        if (strainData.psilocybin === 0 && strainData.psilocin === 0) { adcError('Please enter at least Psilocybin or Psilocin values'); return; }
        const id = state.editingStrainId || ('user-' + Date.now());
        state.customStrains[id] = strainData;
        state.editingStrainId = null;
        saveToStorage(STORAGE_KEYS.customStrains, state.customStrains);
        populateStrainSelect();
        state.strainId = id;
        elements.strainSelect.value = id;
        handleStrainChange({ target: { value: id } });
        closeStrainModal();
    }

    async function deleteStrain() {
        const strainData = state.customStrains[state.strainId] || state.scannedStrains[state.strainId];
        if (!strainData) return;
        if (!await adcConfirm(`Delete "${strainData.name}"? This cannot be undone.`, { danger: true, title: "Delete Strain", confirmText: "Delete" })) return;
        if (state.strainId.startsWith('user-') || state.strainId.startsWith('shared-')) {
            delete state.customStrains[state.strainId];
            saveToStorage(STORAGE_KEYS.customStrains, state.customStrains);
        } else if (state.strainId.startsWith('scan-')) {
            delete state.scannedStrains[state.strainId];
            saveToStorage(STORAGE_KEYS.scannedStrains, state.scannedStrains);
        }
        state.strainId = '';
        populateStrainSelect();
        elements.strainSelect.value = '';
        updateStrainPotency();
        updateStrainControls();
        updateMushroomResults();
    }

    function openEdibleModal(editId = null) {
        const modal = elements.edibleModal;
        if (!modal) return;
        const title = modal.querySelector('#adc-edible-modal-title');
        const nameInput = modal.querySelector('#adc-modal-edible-name');
        const brandInput = modal.querySelector('#adc-modal-edible-brand');
        const piecesInput = modal.querySelector('#adc-modal-edible-pieces');
        const compounds = COMPOUNDS;
        const updateCalcDisplay = () => {
            const psi = parseInt(modal.querySelector('#adc-modal-edible-psilocybin').value) || 0;
            const psin = parseInt(modal.querySelector('#adc-modal-edible-psilocin').value) || 0;
            const pieces = parseInt(piecesInput.value) || 0;
            const display = modal.querySelector('#adc-modal-mcg-per-piece');
            if (pieces > 0 && (psi > 0 || psin > 0)) {
                if (display) display.textContent = formatNumber(Math.round((psi + psin) / pieces)) + ' mcg';
            } else { if (display) display.textContent = '—'; }
        };
        modal.querySelector('#adc-modal-edible-psilocybin').oninput = updateCalcDisplay;
        modal.querySelector('#adc-modal-edible-psilocin').oninput = updateCalcDisplay;
        piecesInput.oninput = updateCalcDisplay;
        if (editId && state.customEdibles[editId]) {
            const edible = state.customEdibles[editId];
            const pieces = edible.piecesPerPackage || 1;
            title.textContent = 'Edit Custom Edible';
            nameInput.value = edible.name || '';
            brandInput.value = edible.brand || '';
            piecesInput.value = pieces;
            compounds.forEach(c => { const input = modal.querySelector(`#adc-modal-edible-${c}`); if (input) input.value = edible[c] ? edible[c] * pieces : ''; });
            state.editingEdibleId = editId;
            updateCalcDisplay();
        } else if (state.edibleId === 'custom' && (state.customEdible.psilocybin || state.customEdible.psilocin)) {
            // Copy values from inline custom form
            title.textContent = 'Add Custom Edible';
            nameInput.value = '';
            brandInput.value = '';
            piecesInput.value = state.customEdible.piecesPerPackage || '';
            compounds.forEach(c => { const input = modal.querySelector(`#adc-modal-edible-${c}`); if (input) input.value = state.customEdible[c] || ''; });
            state.editingEdibleId = null;
            updateCalcDisplay();
        } else {
            title.textContent = 'Add Custom Edible';
            nameInput.value = '';
            brandInput.value = '';
            piecesInput.value = '';
            compounds.forEach(c => { const input = modal.querySelector(`#adc-modal-edible-${c}`); if (input) input.value = ''; });
            const display = modal.querySelector('#adc-modal-mcg-per-piece');
            if (display) display.textContent = '—';
            state.editingEdibleId = null;
        }
        modal.classList.add('show');
        // F-002: Set up focus trap
        if (activeModalCleanup) activeModalCleanup();
        activeModalCleanup = trapFocus(modal);
    }

    function closeEdibleModal() { 
        if (elements.edibleModal) elements.edibleModal.classList.remove('show');
        // F-002: Clean up focus trap and restore focus
        if (activeModalCleanup) { activeModalCleanup(); activeModalCleanup = null; }
    }

    function saveEdible() {
        const modal = elements.edibleModal;
        if (!modal) return;
        const name = modal.querySelector('#adc-modal-edible-name').value.trim();
        if (!name) { adcError('Please enter a product name'); return; }
        const pieces = parseInt(modal.querySelector('#adc-modal-edible-pieces').value);
        if (!pieces || pieces <= 0) { adcError('Please enter the number of pieces per package'); modal.querySelector('#adc-modal-edible-pieces').focus(); return; }
        const totalPsilocybin = parseInt(modal.querySelector('#adc-modal-edible-psilocybin').value) || 0;
        const totalPsilocin = parseInt(modal.querySelector('#adc-modal-edible-psilocin').value) || 0;
        if (totalPsilocybin <= 0 && totalPsilocin <= 0) { adcError('Please enter at least Psilocybin or Psilocin total mcg'); return; }
        const edibleData = {
            name,
            brand: modal.querySelector('#adc-modal-edible-brand').value.trim(),
            psilocybin: Math.round(totalPsilocybin / pieces),
            psilocin: Math.round(totalPsilocin / pieces),
            norpsilocin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-norpsilocin').value) || 0) / pieces),
            baeocystin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-baeocystin').value) || 0) / pieces),
            norbaeocystin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-norbaeocystin').value) || 0) / pieces),
            aeruginascin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-aeruginascin').value) || 0) / pieces),
            piecesPerPackage: pieces
        };
        const id = state.editingEdibleId || ('user-' + Date.now());
        state.customEdibles[id] = edibleData;
        state.editingEdibleId = null;
        saveToStorage(STORAGE_KEYS.customEdibles, state.customEdibles);
        populateEdibleSelect();
        state.edibleId = id;
        elements.edibleSelect.value = id;
        handleEdibleChange({ target: { value: id } });
        closeEdibleModal();
    }

    async function deleteEdible() {
        const edibleData = state.customEdibles[state.edibleId] || state.scannedEdibles[state.edibleId];
        if (!edibleData) return;
        if (!await adcConfirm(`Delete "${edibleData.name}"? This cannot be undone.`, { danger: true, title: "Delete Edible", confirmText: "Delete" })) return;
        if (state.edibleId.startsWith('user-') || state.edibleId.startsWith('shared-')) {
            delete state.customEdibles[state.edibleId];
            saveToStorage(STORAGE_KEYS.customEdibles, state.customEdibles);
        } else if (state.edibleId.startsWith('scan-')) {
            delete state.scannedEdibles[state.edibleId];
            saveToStorage(STORAGE_KEYS.scannedEdibles, state.scannedEdibles);
        }
        state.edibleId = '';
        populateEdibleSelect();
        elements.edibleSelect.value = '';
        updateEdibleInfo();
        updateEdibleControls();
        updateEdibleResults();
    }

    async function resetAllData() {
        if (!await adcConfirm('This will clear all your custom strains, edibles, and preferences. The page will reload. Continue?', { danger: true, title: 'Clear All Data', confirmText: 'Clear Everything' })) return;
        Object.values(STORAGE_KEYS).forEach(key => localStorage.removeItem(key));
        localStorage.removeItem(COLLAPSE_STORAGE_KEY);
        localStorage.removeItem(LEVEL_COLLAPSE_KEY);
        window.location.reload();
    }

    // ============================================================================
    // SHARING
    // ============================================================================
    
    function checkForSharedDose() {
        const params = new URLSearchParams(window.location.search);
        const shareData = params.get('share');
        if (!shareData) return;
        try {
            const data = JSON.parse(decodeURIComponent(atob(shareData)));
            showSharedDosePopup(data);
        } catch (e) { console.error('Invalid share data:', e); }
    }

    function showSharedDosePopup(data) {
        let breakdownHtml = '';
        if (data.breakdown && data.breakdown.length > 0) {
            breakdownHtml = '<div class="adc-shared-breakdown">';
            data.breakdown.forEach(b => { breakdownHtml += `<div class="adc-shared-row"><span>${escapeHtml(b.name)}:</span><span>${escapeHtml(b.amount)}</span></div>`; });
            breakdownHtml += '</div>';
        }
        const popup = document.createElement('div');
        popup.className = 'adc-share-popup-overlay';
        popup.innerHTML = `<div class="adc-share-popup"><div class="adc-share-popup-header"><h3>🍄 Shared Dose</h3></div><div class="adc-share-popup-body"><p class="adc-shared-intro">Someone shared their recommended dose with you:</p><div class="adc-shared-product">${escapeHtml(data.product || 'Unknown')}</div><div class="adc-shared-level">${escapeHtml(data.level)}</div><div class="adc-shared-dose">${escapeHtml(data.dose)}</div><div class="adc-shared-mcg">${escapeHtml(data.mcg)}</div>${breakdownHtml}<p class="adc-shared-note">Note: Dosage varies by body weight. Calculate your personal dose below!</p></div><div class="adc-share-popup-footer"><button type="button" class="adc-btn adc-btn-primary" id="adc-close-share-popup">Calculate My Dose</button></div></div>`;
        // Append to calculator container so @scope CSS applies
        (document.getElementById('adc-calculator') || document.body).appendChild(popup);
        window.adcSharedData = data;
        const cleanUrl = window.location.href.split('share=')[0].replace(/[&?]$/, '');
        history.replaceState(null, '', cleanUrl);
        popup.querySelector('#adc-close-share-popup').addEventListener('click', () => {
            popup.remove();
            applySharedData(window.adcSharedData);
            window.adcSharedData = null;
        });
    }

    function applySharedData(data) {
        if (!data) return;
        if (data.type === 'mushroom') {
            if (data.strainData && data.strainId) {
                const sharedId = 'shared-' + Date.now();
                state.customStrains[sharedId] = data.strainData;
                saveToStorage(STORAGE_KEYS.customStrains, state.customStrains);
                populateStrainSelect();
                state.strainId = sharedId;
                if (elements.strainSelect) elements.strainSelect.value = sharedId;
            } else if (data.strainId) {
                state.strainId = data.strainId;
                if (elements.strainSelect) elements.strainSelect.value = data.strainId;
            }
            handleTabChange('mushrooms');
        }
        if (data.type === 'edible') {
            if (data.edibleData && data.edibleId) {
                const sharedId = 'shared-' + Date.now();
                state.customEdibles[sharedId] = data.edibleData;
                saveToStorage(STORAGE_KEYS.customEdibles, state.customEdibles);
                populateEdibleSelect();
                state.edibleId = sharedId;
                if (elements.edibleSelect) elements.edibleSelect.value = sharedId;
            } else if (data.edibleId) {
                state.edibleId = data.edibleId;
                if (elements.edibleSelect) elements.edibleSelect.value = data.edibleId;
            }
            handleTabChange('edibles');
        }
        updateAll();
    }

    // ============================================================================
    // URL PARSING
    // ============================================================================
    
    function getTabFromUrl() {
        const hash = window.location.hash.toLowerCase();
        if (hash === '#edibles' || hash === '#e') return 'edibles';
        if (hash === '#mushrooms' || hash === '#m') return 'mushrooms';
        const params = new URLSearchParams(window.location.search);
        const tabParam = (params.get('tab') || params.get('t') || '').toLowerCase();
        if (tabParam === 'edibles' || tabParam === 'e') return 'edibles';
        if (tabParam === 'mushrooms' || tabParam === 'm') return 'mushrooms';
        return null;
    }

    function parseUrlParams() {
        const params = new URLSearchParams(window.location.search);
        const code = params.get('code');
        const type = params.get('type') || params.get('t');
        if (code && (type === 'strain' || type === 'm')) { state.strainId = code; state.activeTab = 'mushrooms'; }
        else if (code && (type === 'edible' || type === 'e')) { state.edibleId = code; state.activeTab = 'edibles'; }
        const data = params.get('data');
        if (data) {
            const parsed = parseLegacyData(data);
            if (parsed) addScannedStrain(parsed);
        }
    }

    function parseLegacyData(dataString) {
        const result = { name: 'Scanned Strain', ...createEmptyCompounds() };
        dataString.split(',').forEach(pair => {
            const [key, value] = pair.split(':');
            if (key && value !== undefined) {
                const cleanKey = key.trim().toLowerCase();
                const cleanValue = value.trim();
                if (cleanKey === 'name') result.name = cleanValue;
                else if (result.hasOwnProperty(cleanKey)) result[cleanKey] = parseInt(cleanValue) || 0;
            }
        });
        return (result.psilocybin > 0 || result.psilocin > 0) ? result : null;
    }

    function addScannedStrain(strainData) {
        const id = 'scan-' + Date.now();
        strainData.scannedAt = Date.now();
        state.scannedStrains[id] = strainData;
        saveToStorage(STORAGE_KEYS.scannedStrains, state.scannedStrains);
        state.strainId = id;
        populateStrainSelect();
        window.history.replaceState({}, document.title, window.location.origin + window.location.pathname);
    }

    // ============================================================================
    // SUBMISSION TO CHURCH (moved inside IIFE in v2.12.50)
    // ============================================================================

    function showSubmitModal(type, data) {
        let modal = document.getElementById('adc-submit-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'adc-submit-modal';
            modal.className = 'adc-modal-overlay';
            modal.innerHTML = `
                <div class="adc-modal adc-submit-modal">
                    <div class="adc-modal-header">
                        <h3>Submit to Church of Ambrosia</h3>
                        <button type="button" class="adc-modal-close" data-action="close-submit-modal">X</button>
                    </div>
                    <div class="adc-modal-body">
                        <p class="adc-submit-intro">Help grow our database! Submit your strain or edible data for review. Once approved, it will be available to everyone.</p>
                        <div class="adc-modal-field" style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;" aria-hidden="true">
                            <label for="adc-submit-website">Website</label>
                            <input type="text" id="adc-submit-website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="adc-modal-field">
                            <label for="adc-submit-name">Your Name *</label>
                            <input type="text" id="adc-submit-name" placeholder="e.g., John Doe">
                        </div>
                        <div class="adc-modal-field">
                            <label for="adc-submit-email">Your Email *</label>
                            <input type="email" id="adc-submit-email" placeholder="e.g., john@example.com">
                            <p class="adc-field-hint">We'll notify you when your submission is reviewed.</p>
                        </div>
                        <div class="adc-modal-field">
                            <label for="adc-submit-notes">Additional Notes</label>
                            <textarea id="adc-submit-notes" rows="3" placeholder="Testing lab, batch number, or any other relevant info..."></textarea>
                        </div>
                        <div class="adc-submit-preview">
                            <h4>Submission Preview</h4>
                            <div id="adc-submit-preview-content"></div>
                        </div>
                    </div>
                    <div class="adc-modal-footer">
                        <button type="button" class="adc-btn adc-btn-secondary" data-action="close-submit-modal">Cancel</button>
                        <button type="button" class="adc-btn adc-btn-primary" id="adc-submit-btn" data-action="do-submit">Submit for Review</button>
                    </div>
                </div>
            `;
            (document.getElementById('adc-calculator') || document.body).appendChild(modal);
        }
        modal.dataset.type = type;
        modal.dataset.submitData = JSON.stringify(data);
        const previewEl = document.getElementById('adc-submit-preview-content');
        let previewHtml = `<strong>${escapeHtml(data.name)}</strong><br>`;
        if (type === 'strain') {
            previewHtml += `Psilocybin: ${data.psilocybin || 0} mcg/g<br>`;
            previewHtml += `Psilocin: ${data.psilocin || 0} mcg/g`;
        } else {
            previewHtml += `Psilocybin: ${data.psilocybin || 0} mcg/piece<br>`;
            previewHtml += `Pieces per Package: ${data.piecesPerPackage || 1}`;
        }
        previewEl.innerHTML = previewHtml;
        modal.classList.add('show');
    }

    function closeSubmitModal() {
        const modal = document.getElementById('adc-submit-modal');
        if (modal) modal.classList.remove('show');
    }

    async function doSubmit() {
        const modal = document.getElementById('adc-submit-modal');
        if (!modal) return;
        // Honeypot check: bots fill in the hidden website field
        const honeypot = document.getElementById('adc-submit-website');
        if (honeypot && honeypot.value) { closeSubmitModal(); return; }
        
        const name = document.getElementById('adc-submit-name').value.trim();
        const email = document.getElementById('adc-submit-email').value.trim();
        const notes = document.getElementById('adc-submit-notes').value.trim();
        if (!name || !email) { adcError('Please enter your name and email.'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { adcError('Please enter a valid email address.'); return; }
        const type = modal.dataset.type;
        const data = JSON.parse(modal.dataset.submitData);
        const submitBtn = document.getElementById('adc-submit-btn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        try {
            const response = await fetch(adcData.restUrl + 'submit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, data, name, email, notes })
            });
            const result = await response.json();
            if (response.ok && result.success) {
                adcSuccess('Thank you! Your submission has been received and will be reviewed by our team.');
                closeSubmitModal();
                document.getElementById('adc-submit-name').value = '';
                document.getElementById('adc-submit-email').value = '';
                document.getElementById('adc-submit-notes').value = '';
            } else {
                adcError(result.message || 'Submission failed. Please try again.');
            }
        } catch (error) {
            console.error('Submission error:', error);
            adcError('Network error. Please check your connection and try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    function submitStrain() {
        const modal = elements.strainModal;
        if (!modal) return;
        const name = modal.querySelector('#adc-modal-strain-name').value.trim();
        if (!name) { adcError('Please enter a strain name first.'); return; }
        const data = {
            name,
            psilocybin: parseInt(modal.querySelector('#adc-modal-psilocybin').value) || 0,
            psilocin: parseInt(modal.querySelector('#adc-modal-psilocin').value) || 0,
            norpsilocin: parseInt(modal.querySelector('#adc-modal-norpsilocin').value) || 0,
            baeocystin: parseInt(modal.querySelector('#adc-modal-baeocystin').value) || 0,
            norbaeocystin: parseInt(modal.querySelector('#adc-modal-norbaeocystin').value) || 0,
            aeruginascin: parseInt(modal.querySelector('#adc-modal-aeruginascin').value) || 0
        };
        showSubmitModal('strain', data);
    }

    function submitEdible() {
        const modal = elements.edibleModal;
        if (!modal) return;
        const name = modal.querySelector('#adc-modal-edible-name').value.trim();
        if (!name) { adcError('Please enter a product name first.'); return; }
        const totalPsilocybin = parseInt(modal.querySelector('#adc-modal-edible-psilocybin').value) || 0;
        const pieces = parseInt(modal.querySelector('#adc-modal-edible-pieces').value) || 1;
        const data = {
            name,
            brand: modal.querySelector('#adc-modal-edible-brand').value.trim(),
            psilocybin: Math.round(totalPsilocybin / pieces),
            psilocin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-psilocin').value) || 0) / pieces),
            norpsilocin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-norpsilocin').value) || 0) / pieces),
            baeocystin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-baeocystin').value) || 0) / pieces),
            norbaeocystin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-norbaeocystin').value) || 0) / pieces),
            aeruginascin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-aeruginascin').value) || 0) / pieces),
            piecesPerPackage: pieces
        };
        showSubmitModal('edible', data);
    }

    function resetTolerance() {
        const select = document.getElementById('adc-tolerance');
        if (select) { select.value = '28'; select.dispatchEvent(new Event('change')); }
    }

    function resetSensitivity() {
        const slider = document.getElementById('adc-sensitivity-slider');
        const input = document.getElementById('adc-sensitivity-input');
        if (slider) { slider.value = 100; slider.dispatchEvent(new Event('input')); }
        if (input) { input.value = 100; }
    }

    // All action handlers are now dispatched via data-action delegation (no global exports needed)
/**
 * Collapsible Sections — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // COLLAPSIBLE SECTIONS
    // ============================================================================

    const COLLAPSE_STORAGE_KEY = 'adc_section_collapse_state';

    function getCollapseHeaderRow(section) {
        // Select-only mode: no dedicated header row — inject button into section directly
        if (section.dataset.collapseMode === 'select-only') {
            return section;
        }
        return section.querySelector('.adc-label-row')
            || section.querySelector('.adc-results-header')
            || section.querySelector('.adc-converter-header')
            || section.querySelector('h3');
    }

    function getCollapseHeaderLabel(section) {
        const el = section.querySelector('label, h2, h3');
        return el ? el.textContent.trim() : 'section';
    }

    function getCollapseSummary(section) {
        const name = section.dataset.section;
        if (name === 'adjustments') {
            const tolSel = document.getElementById('adc-tolerance');
            const sensInp = document.getElementById('adc-sensitivity-input');
            const tolText = tolSel ? tolSel.options[tolSel.selectedIndex]?.textContent || '' : '';
            const sensValue = sensInp ? parseInt(sensInp.value) : 100;
            let sensLabel = '';
            if (sensValue === 100) {
                sensLabel = '100% (Normal)';
            } else if (sensValue < 100) {
                sensLabel = sensValue + '% (Need LESS than others)';
            } else {
                sensLabel = sensValue + '% (Need MORE than others)';
            }
            // Two-line display with divider between them
            // sensLabel wraps as a unit after the label (never mid-phrase)
            return tolText && sensLabel ? '<span class="adc-adj-line"><strong>Days Since Last Dose:</strong> <span style="white-space:nowrap">' + tolText + '</span></span><hr class="adc-adj-divider"><span class="adc-adj-line"><strong>Personal Sensitivity:</strong> <span style="white-space:nowrap">' + sensLabel + '</span></span>' : '';
        }
        if (name === 'tolerance') {
            const sel = document.getElementById('adc-tolerance');
            return sel ? sel.options[sel.selectedIndex]?.textContent || '' : '';
        }
        if (name === 'sensitivity') {
            const inp = document.getElementById('adc-sensitivity-input');
            return inp ? inp.value + '%' : '';
        }
        if (name === 'mushroom-results') {
            const sel = document.getElementById('adc-strain-select');
            return sel && sel.value ? sel.options[sel.selectedIndex]?.textContent || '' : '';
        }
        if (name === 'edible-results') {
            const sel = document.getElementById('adc-edible-select');
            return sel && sel.value ? sel.options[sel.selectedIndex]?.textContent || '' : '';
        }
        return '';
    }

    function getSavedCollapseState() {
        try {
            const data = localStorage.getItem(COLLAPSE_STORAGE_KEY);
            return data ? JSON.parse(data) : {};
        } catch (e) {
            return {};
        }
    }

    function saveCollapseState() {
        const consent = document.getElementById('adc-storage-consent');
        if (!consent || !consent.checked) return;
        const sections = document.querySelectorAll('[data-collapsible]');
        const obj = {};
        sections.forEach(s => {
            obj[s.dataset.section] = s.classList.contains('adc-collapsed');
        });
        try {
            localStorage.setItem(COLLAPSE_STORAGE_KEY, JSON.stringify(obj));
        } catch (e) { /* ignore */ }
    }

    function collapseSection(section, btn, animate) {
        section.classList.add('adc-collapsed');
        btn.innerHTML = '&#9652;'; // ▴
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-label', 'Expand ' + getCollapseHeaderLabel(section));

        // No summary for select-only mode — there's no header row to append to
        if (section.dataset.collapseMode !== 'select-only') {
            const headerRow = getCollapseHeaderRow(section);
            if (headerRow) {
                removeSummary(headerRow);
                const summary = getCollapseSummary(section);
                if (summary) {
                    const span = document.createElement('span');
                    span.className = 'adc-collapse-summary';
                    span.innerHTML = summary;
                    // Adjustments: insert summary after the header row (block below title)
                    if (section.dataset.section === 'adjustments') {
                        headerRow.insertAdjacentElement('afterend', span);
                    } else {
                        headerRow.appendChild(span);
                    }
                }
            }
        }
    }

    function expandSection(section, btn) {
        section.classList.remove('adc-collapsed');
        btn.innerHTML = '&#9662;'; // ▾
        btn.setAttribute('aria-expanded', 'true');
        btn.setAttribute('aria-label', 'Collapse ' + getCollapseHeaderLabel(section));

        if (section.dataset.collapseMode !== 'select-only') {
            const headerRow = getCollapseHeaderRow(section);
            if (headerRow) removeSummary(headerRow);
        }
    }

    function removeSummary(headerRow) {
        // Remove from inside the header row
        const existing = headerRow.querySelector('.adc-collapse-summary');
        if (existing) existing.remove();
        // Also remove if placed after the header row (adjustments section)
        const sibling = headerRow.nextElementSibling;
        if (sibling && sibling.classList.contains('adc-collapse-summary')) sibling.remove();
    }

    function toggleSection(section, btn) {
        if (section.classList.contains('adc-collapsed')) {
            expandSection(section, btn);
        } else {
            collapseSection(section, btn, true);
        }
        saveCollapseState();
        if (section.dataset.section === 'adjustments') {
            updateTabStops();
        }
    }

    function updateTabStops() {
        const adjustmentsSection = document.querySelector('[data-section="adjustments"]');
        if (!adjustmentsSection) return;
        const isCollapsed = adjustmentsSection.classList.contains('adc-collapsed');
        
        const weightInput = document.getElementById('adc-weight');
        const strainSelect = document.getElementById('adc-strain-select');
        const converterInput = document.getElementById('adc-mcg-input');
        const gramsInput = document.getElementById('adc-grams-input');
        const toleranceInput = document.getElementById('adc-tolerance');
        const sensitivityInput = document.getElementById('adc-sensitivity-input');
        
        if (weightInput) weightInput.tabIndex = 1;
        
        if (isCollapsed) {
            if (toleranceInput) toleranceInput.tabIndex = -1;
            if (sensitivityInput) sensitivityInput.tabIndex = -1;
            if (strainSelect) strainSelect.tabIndex = 2;
            if (converterInput) converterInput.tabIndex = 3;
            if (gramsInput) gramsInput.tabIndex = 4;
        } else {
            if (toleranceInput) toleranceInput.tabIndex = 2;
            if (sensitivityInput) sensitivityInput.tabIndex = 3;
            if (strainSelect) strainSelect.tabIndex = 4;
            if (converterInput) converterInput.tabIndex = 5;
            if (gramsInput) gramsInput.tabIndex = 6;
        }
    }

    function initCollapsible() {
        const sections = document.querySelectorAll('[data-collapsible]');
        sections.forEach(section => {
            // Skip if already initialized
            if (section.querySelector('.adc-collapse-btn')) return;

            const sectionName = section.dataset.section;
            const headerRow = getCollapseHeaderRow(section);
            if (!headerRow) return;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'adc-collapse-btn';
            btn.setAttribute('aria-expanded', 'true');
            btn.setAttribute('aria-label', 'Collapse ' + getCollapseHeaderLabel(section));
            btn.innerHTML = '&#9662;'; // ▾

            // For h3 header (safety) or select-only mode, append to section itself
            if (headerRow.tagName === 'H3' || section.dataset.collapseMode === 'select-only') {
                section.appendChild(btn);
            } else {
                headerRow.appendChild(btn);
            }

            // Restore saved state (adjustments default to collapsed)
            const saved = getSavedCollapseState();
            if (saved[sectionName] === true || (saved[sectionName] === undefined && sectionName === 'adjustments')) {
                collapseSection(section, btn, false);
            }

            btn.addEventListener('click', () => toggleSection(section, btn));

            // Tap anywhere on a collapsed section to expand it;
            // tap the top 70px of an expanded section to collapse it.
            section.addEventListener('click', (e) => {
                const isCollapsed = section.classList.contains('adc-collapsed');
                // Don't intercept clicks on interactive elements
                if (e.target.closest('button, a, select, input, label, .adc-collapse-btn')) return;

                if (isCollapsed) {
                    expandSection(section, btn);
                } else {
                    // Only collapse when click is within the top 70px of the section
                    const rect = section.getBoundingClientRect();
                    if (e.clientY - rect.top > 70) return;
                    collapseSection(section, btn, true);
                }

                saveCollapseState();
                if (section.dataset.section === 'adjustments') {
                    updateTabStops();
                }
            });
        });
        
        updateTabStops();
    }
/**
 * Event Handlers — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // EVENT HANDLERS
    // ============================================================================
    
    function handleTabChange(tab) {
        if (elements.calculator) elements.calculator.classList.toggle('adc-tab-edibles', tab === 'edibles');
        state.activeTab = tab;
        const newHash = tab === 'edibles' ? '#edibles' : '#mushrooms';
        if (window.location.hash !== newHash) history.replaceState(null, '', newHash);
        savePreferences();
        elements.tabMushrooms?.classList.toggle('active', tab === 'mushrooms');
        elements.tabEdibles?.classList.toggle('active', tab === 'edibles');
        // F-001: Update ARIA states for accessibility
        elements.tabMushrooms?.setAttribute('aria-selected', tab === 'mushrooms' ? 'true' : 'false');
        elements.tabEdibles?.setAttribute('aria-selected', tab === 'edibles' ? 'true' : 'false');
        elements.tabMushrooms?.setAttribute('tabindex', tab === 'mushrooms' ? '0' : '-1');
        elements.tabEdibles?.setAttribute('tabindex', tab === 'edibles' ? '0' : '-1');
        if (elements.converterSection) elements.converterSection.style.display = tab === 'mushrooms' ? '' : 'none';
        updateAll();
        initCollapsible();
    }

    function handleWeightChange(e) {
        const value = parseFloat(e.target.value);
        const limits = WEIGHT_LIMITS[state.displayUnit];
        
        // Clear validation state on input
        clearInputError(e.target);
        
        // Handle empty/invalid input
        if (isNaN(value) || e.target.value.trim() === '') {
            // Don't update state for empty input, let blur handle it
            return;
        }
        
        // Bug fix: Reject negative values with visual feedback
        if (value < 0) {
            showInputError(e.target, 'Weight cannot be negative');
            e.target.value = '';
            return;
        }
        
        // Show warning if outside limits (but still allow typing)
        if (value < limits.min) {
            showInputWarning(e.target, `Minimum: ${limits.min} ${state.displayUnit}`);
        } else if (value > limits.max) {
            showInputWarning(e.target, `Maximum: ${limits.max} ${state.displayUnit}`);
        }
        
        state.weightLbs = state.displayUnit === 'lbs' ? value : kgToLbs(value);
        debouncedUpdateAll();
    }

    function handleWeightBlur(e) {
        const value = parseFloat(e.target.value);
        const limits = WEIGHT_LIMITS[state.displayUnit];
        
        // Handle empty input - restore last valid value
        if (isNaN(value) || e.target.value.trim() === '') {
            updateWeightDisplay();
            clearInputError(e.target);
            return;
        }
        
        // Clamp and show message if needed
        if (value < limits.min) {
            state.weightLbs = state.displayUnit === 'lbs' ? limits.min : kgToLbs(limits.min);
            showInputError(e.target, `Adjusted to minimum: ${limits.min} ${state.displayUnit}`);
            setTimeout(() => clearInputError(e.target), 3000);
        } else if (value > limits.max) {
            state.weightLbs = state.displayUnit === 'lbs' ? limits.max : kgToLbs(limits.max);
            showInputError(e.target, `Adjusted to maximum: ${limits.max} ${state.displayUnit}`);
            setTimeout(() => clearInputError(e.target), 3000);
        } else {
            clearInputError(e.target);
        }
        
        updateWeightDisplay();
        savePreferences();
        updateAll();
    }
    
    /**
     * Show error message on an input field
     */
    function showInputError(input, message) {
        input.classList.add('adc-input-error');
        input.classList.remove('adc-input-warning');
        let errorEl = input.parentElement.querySelector('.adc-input-error-msg');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'adc-input-error-msg';
            input.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }
    
    /**
     * Show warning message on an input field
     */
    function showInputWarning(input, message) {
        input.classList.add('adc-input-warning');
        input.classList.remove('adc-input-error');
        let errorEl = input.parentElement.querySelector('.adc-input-error-msg');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'adc-input-error-msg adc-input-warning-msg';
            input.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = message;
        errorEl.className = 'adc-input-error-msg adc-input-warning-msg';
        errorEl.style.display = 'block';
    }
    
    /**
     * Clear error/warning state from an input field
     */
    function clearInputError(input) {
        input.classList.remove('adc-input-error', 'adc-input-warning');
        const errorEl = input.parentElement.querySelector('.adc-input-error-msg');
        if (errorEl) errorEl.style.display = 'none';
    }

    function handleUnitToggle(e) {
        const unit = e.target.dataset.unit;
        if (unit === state.displayUnit) return;
        state.displayUnit = unit;
        elements.unitToggle.forEach(b => {
            const isActive = b.dataset.unit === unit;
            b.classList.toggle('active', isActive);
            b.setAttribute('aria-pressed', isActive ? 'true' : 'false'); // F-008: Update aria-pressed
        });
        updateWeightDisplay();
        savePreferences();
    }

    function handleToleranceChange(e) {
        state.daysSinceLastDose = parseInt(e.target.value);
        updateToleranceDisplay();
        updateAll();
        savePreferences();
    }

    function handleSensitivitySlider(e) {
        state.sensitivity = parseInt(e.target.value);
        if (elements.sensitivityInput) elements.sensitivityInput.value = state.sensitivity;
        updateAll();
        // F-007: Update aria-valuetext for accessibility
        updateSensitivityAria(state.sensitivity);
        savePreferences();
    }

    function handleSensitivityInput(e) {
        // Parse and validate input value
        let v = parseInt(e.target.value);
        if (isNaN(v)) v = 100;
        
        // Bug fix: Clamp to valid range (10-300)
        v = clamp(v, 10, 300);
        
        // Update state
        state.sensitivity = v;
        
        // Bug fix: Always sync slider and update input with clamped value
        if (elements.sensitivitySlider) elements.sensitivitySlider.value = v;
        e.target.value = v;
        
        updateAll();
        updateSensitivityAria(v);
        savePreferences();
    }

    function handleStrainChange(e) {
        state.strainId = e.target.value;
        if (elements.customStrainWrapper) elements.customStrainWrapper.style.display = state.strainId === 'custom' ? '' : 'none';
        // Auto-expand the strain section when Custom is selected
        if (state.strainId === 'custom') {
            const section = document.querySelector('[data-section="strain"]');
            if (section && section.classList.contains('adc-collapsed')) {
                const btn = section.querySelector('.adc-collapse-btn');
                if (btn) expandSection(section, btn);
            }
        }
        updateStrainControls();
        updateStrainPotency();
        updateMushroomResults();
        updateConverter();
        savePreferences();
    }

    function handleEdibleChange(e) {
        state.edibleId = e.target.value;
        if (elements.customEdibleWrapper) elements.customEdibleWrapper.style.display = state.edibleId === 'custom' ? '' : 'none';
        // Auto-expand the edible section when Custom is selected
        if (state.edibleId === 'custom') {
            const section = document.querySelector('[data-section="edible-product"]');
            if (section && section.classList.contains('adc-collapsed')) {
                const btn = section.querySelector('.adc-collapse-btn');
                if (btn) expandSection(section, btn);
            }
        }
        updateEdibleControls();
        updateEdibleInfo();
        updateEdibleResults();
        savePreferences();
    }

    function handleConverterMcg() {
        const strain = getCurrentStrain();
        if (!strain) return;
        const total = getTotalPsilocybin(strain);
        const mcgVal = parseFloat(elements.mcgInput.value) || 0;
        const gramsVal = mcgVal / total;
        elements.gramsInput.value = gramsVal > 0 ? gramsVal.toFixed(3) : '';
        updateConverterBreakdown(gramsVal);
    }

    function handleConverterGrams() {
        const strain = getCurrentStrain();
        if (!strain) return;
        const total = getTotalPsilocybin(strain);
        const gramsVal = parseFloat(elements.gramsInput.value) || 0;
        const mcgVal = gramsVal * total;
        elements.mcgInput.value = mcgVal > 0 ? Math.round(mcgVal) : '';
        updateConverterBreakdown(gramsVal);
    }

    function updateConverterBreakdown(grams) {
        if (!elements.converterBreakdown) return;
        const strain = getCurrentStrain();
        if (!strain || grams <= 0) { elements.converterBreakdown.innerHTML = ''; return; }
        const compounds = getCompoundBreakdown(strain, grams, grams);
        elements.converterBreakdown.innerHTML = compounds.map(c => `<div class="adc-converter-compound">${c.name}: ${formatNumber(c.min)} mcg</div>`).join('');
    }

    // ============================================================================
    // EVENT BINDING
    // ============================================================================
    
    function bindEvents() {
        // ---- Data-action delegation (replaces all inline onclick handlers) ----
        document.addEventListener('click', function(e) {
            const el = e.target.closest('[data-action]');
            if (!el) return;
            const actionMap = {
                'open-strain-modal': () => openStrainModal(),
                'close-strain-modal': closeStrainModal,
                'save-strain': saveStrain,
                'edit-strain': () => openStrainModal(state.strainId),
                'delete-strain': deleteStrain,
                'open-edible-modal': () => openEdibleModal(),
                'close-edible-modal': closeEdibleModal,
                'save-edible': saveEdible,
                'edit-edible': () => openEdibleModal(state.edibleId),
                'delete-edible': deleteEdible,
                'reset-data': resetAllData,
                'reset-tolerance': resetTolerance,
                'reset-sensitivity': resetSensitivity,
                'submit-strain': submitStrain,
                'submit-edible': submitEdible,
                'close-submit-modal': closeSubmitModal,
                'do-submit': doSubmit,
            };
            const handler = actionMap[el.dataset.action];
            if (handler) handler();
        });

        elements.tabMushrooms?.addEventListener('click', () => handleTabChange('mushrooms'));
        elements.tabEdibles?.addEventListener('click', () => handleTabChange('edibles'));
        // F-001: Keyboard navigation for tabs (WAI-ARIA pattern)
        const handleTabKeydown = (e) => {
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                e.preventDefault();
                const nextTab = state.activeTab === 'mushrooms' ? 'edibles' : 'mushrooms';
                handleTabChange(nextTab);
                (nextTab === 'mushrooms' ? elements.tabMushrooms : elements.tabEdibles)?.focus();
            }
        };
        elements.tabMushrooms?.addEventListener('keydown', handleTabKeydown);
        elements.tabEdibles?.addEventListener('keydown', handleTabKeydown);
        elements.weightInput?.addEventListener('input', handleWeightChange);
        elements.weightInput?.addEventListener('blur', handleWeightBlur);
        elements.unitToggle?.forEach(btn => btn.addEventListener('click', handleUnitToggle));
        elements.toleranceSelect?.addEventListener('change', handleToleranceChange);
        elements.sensitivitySlider?.addEventListener('input', handleSensitivitySlider);
        elements.sensitivityInput?.addEventListener('input', handleSensitivityInput);
        elements.sensitivityInput?.addEventListener('change', handleSensitivityInput);
        elements.sensitivityInput?.addEventListener('blur', handleSensitivityInput);
        elements.strainSelect?.addEventListener('change', handleStrainChange);
        elements.edibleSelect?.addEventListener('change', handleEdibleChange);
        elements.mcgInput?.addEventListener('input', handleConverterMcg);
        elements.gramsInput?.addEventListener('input', handleConverterGrams);
        elements.storageConsent?.addEventListener('change', function() {
            state.storageConsent = this.checked;
            if (this.checked) {
                // User wants to remember settings - remove DONTKEEP and save ALL current data
                localStorage.removeItem(STORAGE_KEYS.dontkeep);
                saveAllData();
            } else {
                // User doesn't want storage - wipe all data and set DONTKEEP
                Object.values(STORAGE_KEYS).forEach(key => {
                    if (key !== STORAGE_KEYS.dontkeep) localStorage.removeItem(key);
                });
                localStorage.setItem(STORAGE_KEYS.dontkeep, 'true');
            }
        });
        document.querySelectorAll('#adc-custom-strain-wrapper input').forEach(input => {
            input.addEventListener('input', function() {
                const key = this.dataset.compound;
                if (key) { state.customStrain[key] = Math.max(0, parseInt(this.value) || 0); if (parseInt(this.value) < 0) this.value = 0; saveToStorage(STORAGE_KEYS.customStrain, state.customStrain); updateStrainPotency(); updateMushroomResults(); updateConverter(); }
            });
        });
        document.querySelectorAll('#adc-custom-edible-wrapper input').forEach(input => {
            input.addEventListener('input', function() {
                const key = this.dataset.field || this.dataset.compound;
                if (key) { state.customEdible[key] = Math.max(0, parseInt(this.value) || 0); if (parseInt(this.value) < 0) this.value = 0; saveToStorage(STORAGE_KEYS.customEdible, state.customEdible); updateEdibleInfo(); updateEdibleResults(); }
            });
        });
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('adc-details-toggle')) {
                const levelId = e.target.dataset.level;
                const breakdown = document.getElementById('adc-breakdown-' + levelId);
                if (breakdown) { breakdown.classList.toggle('visible'); const isExpanded = breakdown.classList.contains('visible'); e.target.textContent = isExpanded ? '▲ Hide' : '▼ Details'; e.target.classList.toggle('expanded', isExpanded); }
            }
            const shareBtn = e.target.closest('.adc-share-btn');
            if (shareBtn) {
                e.preventDefault();
                e.stopPropagation();
                const shareDataRaw = shareBtn.dataset.share;
                if (!shareDataRaw) { 
                    adcError('Share data not found'); 
                    return; 
                }
                try {
                    // Decode: atob then decodeURIComponent (matches btoa(encodeURIComponent(...)))
                    const decoded = decodeURIComponent(atob(shareDataRaw));
                    const data = JSON.parse(decoded);
                    handleShareAction(data, shareDataRaw);
                } catch (err) {
                    console.error('Share data parse error:', err, shareDataRaw);
                    // Fallback to old behavior
                    const baseUrl = window.location.origin + window.location.pathname;
                    const shareUrl = baseUrl + '?share=' + encodeURIComponent(shareDataRaw);
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(shareUrl).then(() => {
                            adcSuccess('Link copied to clipboard!');
                        }).catch(() => {
                            adcPrompt('Copy this link:', { defaultValue: shareUrl, title: 'Share Link' });
                        });
                    } else {
                        adcPrompt('Copy this link:', { defaultValue: shareUrl, title: 'Share Link' });
                    }
                }
            }
        });
        document.addEventListener('focusin', function(e) { if (e.target.matches('input[type="number"]') && e.target.value === '0') e.target.value = ''; });
    }

    // ============================================================================
    // GLOBAL API (for inline onclick handlers)
    // ============================================================================
    
    // Legacy global API removed in v2.12.50 — all actions now use data-action delegation

    // ============================================================================
    // PER-LEVEL COLLAPSE EVENT DELEGATION
    // ============================================================================

    function initLevelCollapse() {
        ['mushroom', 'edible'].forEach(type => {
            const containerId = type === 'mushroom' ? 'adc-mushroom-results' : 'adc-edible-results';
            const container = document.getElementById(containerId);
            if (!container) return;

            container.addEventListener('click', (e) => {
                // Always handle the dedicated toggle button (expand or collapse)
                const btn = e.target.closest('.adc-level-collapse-btn');
                if (btn) {
                    e.stopPropagation();
                    const card = btn.closest('.adc-result-card');
                    const levelId = card?.dataset.levelId;
                    if (!levelId) return;
                    if (collapsedLevels[type].has(levelId)) {
                        collapsedLevels[type].delete(levelId);
                    } else {
                        collapsedLevels[type].add(levelId);
                    }
                    saveLevelCollapseState();
                    reapplyLevelCollapseState(type);
                    return;
                }

                // Click anywhere on a collapsed card to expand it;
                // click the top 70px of an expanded card to collapse it.
                const card = e.target.closest('.adc-result-card');
                if (!card) return;
                if (e.target.closest('button, a, input, select, textarea')) return;

                const levelId = card.dataset.levelId;
                if (!levelId) return;

                if (card.classList.contains('adc-level-collapsed')) {
                    collapsedLevels[type].delete(levelId);
                } else {
                    const rect = card.getBoundingClientRect();
                    if (e.clientY - rect.top > 70) return;
                    collapsedLevels[type].add(levelId);
                }

                saveLevelCollapseState();
                reapplyLevelCollapseState(type);
            });
        });
    }
/**
 * Initialization — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

    // ============================================================================
    // INITIALIZATION
    // ============================================================================
    
    async function init() {
        const calculator = document.getElementById('adc-calculator');
        if (!calculator) return;
        if (typeof adcConfig !== 'undefined') {
            state.config = adcConfig.settings || {};
            state.strains = adcConfig.strains || [];
            state.edibles = adcConfig.edibles || [];
            state.unitMap = adcConfig.unitMap || {};
        } else {
            state.config = { showEdibles: true, showMushrooms: true, showQuickConverter: true, showCompoundBreakdown: true, allowCustom: true, allowSubmit: true };
        }
        cacheElements();
        loadPreferences();
        loadLevelCollapseState();
        parseUrlParams();
        const urlTab = getTabFromUrl();
        if (urlTab) state.activeTab = urlTab;
        const searchParams = new URLSearchParams(window.location.search);
        const typeParam = searchParams.get('type') || searchParams.get('t');
        const urlSpecifiedTab = urlTab || typeParam === 'edible' || typeParam === 'strain' || typeParam === 'e' || typeParam === 'm';
        populateStrainSelect();
        populateEdibleSelect();
        // Always lazy-load from REST (data no longer inlined in page)
        await fetchStrains();
        await fetchEdibles();
        bindEvents();
        // Sync state.activeTab from DOM (PHP/inline script sets initial state, no flash)
        const domHasEdibles = elements.calculator?.classList.contains('adc-tab-edibles');
        // Only use DOM state if URL params didnt specify a tab
        if (!urlSpecifiedTab) state.activeTab = domHasEdibles ? 'edibles' : 'mushrooms';
        else handleTabChange(state.activeTab);
        // Set hash if not already correct
        const currentHash = window.location.hash;
        const expectedHash = state.activeTab === 'edibles' ? '#edibles' : '#mushrooms';
        if (currentHash !== expectedHash && currentHash !== '#edibles' && currentHash !== '#mushrooms') {
            history.replaceState(null, '', expectedHash);
        }
        if (state.strainId && elements.strainSelect) elements.strainSelect.value = state.strainId;
        if (state.edibleId && elements.edibleSelect) elements.edibleSelect.value = state.edibleId;
        if (elements.toleranceSelect) elements.toleranceSelect.value = String(state.daysSinceLastDose);
        elements.unitToggle?.forEach(b => { b.classList.toggle('active', b.dataset.unit === state.displayUnit); b.setAttribute('aria-pressed', b.dataset.unit === state.displayUnit ? 'true' : 'false'); });
        if (elements.storageConsent) {
            const dontkeep = localStorage.getItem(STORAGE_KEYS.dontkeep);
            elements.storageConsent.checked = dontkeep !== 'true';
            state.storageConsent = dontkeep !== 'true';
        }
        checkForSharedDose();
        updateAll();
        initCollapsible();
        initLevelCollapse();
        if (DEBUG) console.log('ADC Calculator v' + (typeof adcData !== 'undefined' ? adcData.version : '2.1') + ' initialized');
    }

    async function fetchStrains() {
        if (typeof adcData === 'undefined') return;
        try {
            const response = await fetch(adcData.restUrl + 'strains');
            const data = await response.json();
            state.strains = data.strains || [];
        } catch (e) {
            console.error('Error fetching strains:', e);
        }
        state._strainsLoaded = true;
        populateStrainSelect();
    }

    async function fetchEdibles() {
        if (typeof adcData === 'undefined') return;
        try {
            const response = await fetch(adcData.restUrl + 'edibles');
            const data = await response.json();
            state.edibles = data.edibles || [];
            state.unitMap = data.unitMap || {};
        } catch (e) {
            console.error('Error fetching edibles:', e);
        }
        state._ediblesLoaded = true;
        populateEdibleSelect();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
/**
 * IIFE Close — Part of calculator.js
 * Built by: bash public/js/build-js.sh
 * DO NOT edit calculator.js or calculator.min.js directly.
 * Edit the module files in public/js/modules/ then rebuild.
 */

})();
