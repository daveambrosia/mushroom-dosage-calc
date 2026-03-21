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
	let activeModalCleanup   = null;

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
