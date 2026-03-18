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
