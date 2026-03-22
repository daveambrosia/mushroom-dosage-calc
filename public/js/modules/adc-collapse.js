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

    function getToleranceLabel(days) {
        var labels = {
            28: '28+ days (No tolerance)',
            21: '21 days (3 weeks)',
            14: '14 days (2 weeks)',
            7:  '7 days (1 week)',
            1:  '1 day (yesterday)'
        };
        return labels[days] || days + ' days';
    }

    function getCollapseSummary(section) {
        const name = section.dataset.section;
        if (name === 'adjustments') {
            // Read from state directly to avoid DOM/state mismatch (BUG-005 fix)
            const days = state.daysSinceLastDose != null ? state.daysSinceLastDose : 28;
            const tolText = getToleranceLabel(days);
            const sensValue = state.sensitivity != null ? state.sensitivity : 100;
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
            const days = state.daysSinceLastDose != null ? state.daysSinceLastDose : 28;
            return getToleranceLabel(days);
        }
        if (name === 'sensitivity') {
            const val = state.sensitivity != null ? state.sensitivity : 100;
            return val + '%';
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

        // Hide collapsed children from assistive tech (BUG-006 fix)
        setCollapsedAria(section, true);

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

        // Restore assistive tech visibility (BUG-006 fix)
        setCollapsedAria(section, false);

        if (section.dataset.collapseMode !== 'select-only') {
            const headerRow = getCollapseHeaderRow(section);
            if (headerRow) removeSummary(headerRow);
        }
    }

    /**
     * Set aria-hidden on children that are visually hidden when collapsed.
     * Prevents button titles/text from leaking into the accessibility tree.
     */
    function setCollapsedAria(section, hidden) {
        const headerRow = getCollapseHeaderRow(section);
        Array.from(section.children).forEach(child => {
            // Skip the header row, collapse button, and collapse summary
            if (child === headerRow) return;
            if (child.classList && (child.classList.contains('adc-collapse-btn') || child.classList.contains('adc-collapse-summary'))) return;
            if (hidden) {
                child.setAttribute('aria-hidden', 'true');
            } else {
                child.removeAttribute('aria-hidden');
            }
        });
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

    /**
     * Refresh the collapsed summary for the adjustments section.
     * Called when tolerance or sensitivity changes so the summary stays current
     * even when the panel is collapsed (BUG-005 fix).
     */
    function refreshAdjustmentsSummary() {
        const section = document.querySelector('[data-section="adjustments"]');
        if (!section || !section.classList.contains('adc-collapsed')) return;
        const btn = section.querySelector('.adc-collapse-btn');
        if (!btn) return;
        const headerRow = getCollapseHeaderRow(section);
        if (!headerRow) return;
        removeSummary(headerRow);
        const summary = getCollapseSummary(section);
        if (summary) {
            const span = document.createElement('span');
            span.className = 'adc-collapse-summary';
            span.innerHTML = summary;
            headerRow.insertAdjacentElement('afterend', span);
        }
    }

    function updateTabStops() {
        // Use tabIndex=0 (natural DOM order) for reachable inputs,
        // tabIndex=-1 for inputs hidden inside collapsed sections.
        // WCAG 2.4.3: positive tabindex values are prohibited.
        const adjustmentsSection = document.querySelector('[data-section="adjustments"]');
        if (!adjustmentsSection) return;
        const isCollapsed = adjustmentsSection.classList.contains('adc-collapsed');
        
        const weightInput = document.getElementById('adc-weight');
        const strainSelect = document.getElementById('adc-strain-select');
        const converterInput = document.getElementById('adc-mcg-input');
        const gramsInput = document.getElementById('adc-grams-input');
        const toleranceInput = document.getElementById('adc-tolerance');
        const sensitivityInput = document.getElementById('adc-sensitivity-input');
        
        if (weightInput) weightInput.tabIndex = 0;
        
        if (isCollapsed) {
            if (toleranceInput) toleranceInput.tabIndex = -1;
            if (sensitivityInput) sensitivityInput.tabIndex = -1;
            if (strainSelect) strainSelect.tabIndex = 0;
            if (converterInput) converterInput.tabIndex = 0;
            if (gramsInput) gramsInput.tabIndex = 0;
        } else {
            if (toleranceInput) toleranceInput.tabIndex = 0;
            if (sensitivityInput) sensitivityInput.tabIndex = 0;
            if (strainSelect) strainSelect.tabIndex = 0;
            if (converterInput) converterInput.tabIndex = 0;
            if (gramsInput) gramsInput.tabIndex = 0;
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
