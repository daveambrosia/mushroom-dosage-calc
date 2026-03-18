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
        if (!elements.mcgInput || !elements.gramsInput) return;
        const strain = getCurrentStrain();
        if (!strain) return;
        const total = getTotalPsilocybin(strain);
        const mcgVal = parseFloat(elements.mcgInput.value) || 0;
        const gramsVal = mcgVal / total;
        elements.gramsInput.value = gramsVal > 0 ? gramsVal.toFixed(3) : '';
        updateConverterBreakdown(gramsVal);
    }

    function handleConverterGrams() {
        if (!elements.mcgInput || !elements.gramsInput) return;
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
