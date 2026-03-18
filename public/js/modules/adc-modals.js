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
            psilocin: parseInt(modal.querySelector('#adc-modal-edible-psilocin').value) || 0,
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
