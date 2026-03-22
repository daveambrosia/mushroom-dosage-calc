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

        // Border on adjustments box: red when over 100%, blue when under
        const adjSection = document.querySelector('[data-section="adjustments"]');
        if (adjSection) {
            adjSection.classList.remove('adc-adj-over', 'adc-adj-under');
            if (combined > 100) {
                adjSection.classList.add('adc-adj-over');
            } else if (combined < 100) {
                adjSection.classList.add('adc-adj-under');
            }
        }

        // Toggle red background on results grid when any adjustment is active
        updateResultsGridWarning();
    }

    function updateResultsGridWarning() {
        const adjusted = state.daysSinceLastDose < 28 || state.sensitivity !== 100;
        document.querySelectorAll('.adc-results-grid').forEach(grid => {
            grid.classList.toggle('adc-tolerance-active', adjusted);
        });
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

    // Re-check bullets on window resize (called from init)
    function initResizeListener() {
        if (!document.getElementById('adc-calculator')) return;
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                ['adc-mushroom-results', 'adc-edible-results'].forEach(id => {
                    updateCollapsedBullets(document.getElementById(id));
                });
            }, 100);
        });
    }

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
