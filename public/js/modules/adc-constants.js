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
