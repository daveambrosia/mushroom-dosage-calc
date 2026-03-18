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
        // Check consent FIRST — before any localStorage writes
        const dontkeep = localStorage.getItem(STORAGE_KEYS.dontkeep);
        state.storageConsent = dontkeep !== 'true';

        // Only do version migration if consent is given
        if (state.storageConsent) {
            const storedVersion = localStorage.getItem(STORAGE_VERSION_KEY);
            if (storedVersion !== STORAGE_VERSION) {
                Object.values(STORAGE_KEYS).forEach(key => localStorage.removeItem(key));
                localStorage.setItem(STORAGE_VERSION_KEY, STORAGE_VERSION);
            }
        }
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
        if (!state.storageConsent) {
            // Default: all levels collapsed
            ['mushroom', 'edible'].forEach(type => {
                LEVEL_IDS.forEach(id => collapsedLevels[type].add(id));
            });
            return;
        }
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
