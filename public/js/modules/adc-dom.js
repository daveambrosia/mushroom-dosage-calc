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
