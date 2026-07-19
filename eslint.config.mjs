/**
 * ESLint Configuration for Ambrosia Dosage Calculator
 *
 * Lints the BUILT bundles (calculator.js, adc-dialogs.js) plus the
 * standalone maker-QR modules. The other files in public/js/modules/ are
 * fragments of a single IIFE (see build-js.sh) and cannot be parsed
 * individually — they are linted via the bundle they produce.
 *
 * Style rules (indent/quotes/semi) are OFF for the generated bundle: it
 * concatenates modules with differing historical styles, and style noise is
 * what kept this lint at ~2000 findings and therefore ignored. Bug-catching
 * rules are errors and CI enforces them.
 *
 * @since 2.21.0
 */
export default [
	{
		// Negated patterns in `files` do nothing in flat config — exclusions
		// belong here in `ignores`. The old config linted the .min.js build
		// artifacts by mistake (~1300 meaningless findings).
		ignores: [
			'public/js/**/*.min.js',
			// IIFE fragments — parseable only as the concatenated bundle,
			// which IS linted (public/js/calculator.js). The standalone
			// maker-qr modules are complete scripts and are linted directly.
			'public/js/modules/adc-iife-*.js',
			'public/js/modules/adc-constants.js',
			'public/js/modules/adc-state.js',
			'public/js/modules/adc-storage.js',
			'public/js/modules/adc-math.js',
			'public/js/modules/adc-dom.js',
			'public/js/modules/adc-render.js',
			'public/js/modules/adc-modals.js',
			'public/js/modules/adc-collapse.js',
			'public/js/modules/adc-events.js',
			'public/js/modules/adc-init.js',
			'admin/js/**',
			'node_modules/**',
			'vendor/**',
			'tests/**',
		],
	},
	{
		files: [
			'public/js/calculator.js',
			'public/js/adc-dialogs.js',
			'public/js/modules/adc-maker-qr.js',
			'public/js/modules/adc-maker-qr-page.js',
			'public/js/modules/adc-maker-qr-modal.js',
		],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
			globals: {
				// Browser globals
				window: 'readonly',
				document: 'readonly',
				console: 'readonly',
				localStorage: 'readonly',
				location: 'readonly',
				history: 'readonly',
				navigator: 'readonly',
				setTimeout: 'readonly',
				clearTimeout: 'readonly',
				setInterval: 'readonly',
				clearInterval: 'readonly',
				requestAnimationFrame: 'readonly',
				fetch: 'readonly',
				Promise: 'readonly',
				FormData: 'readonly',
				Blob: 'readonly',
				URL: 'readonly',
				URLSearchParams: 'readonly',
				Event: 'readonly',
				atob: 'readonly',
				btoa: 'readonly',
				globalThis: 'readonly',

				// WordPress globals
				wp: 'readonly',

				// jQuery (not used currently)
				jQuery: 'readonly',
				$: 'readonly',

				// Plugin globals (wp_localize_script / wp_add_inline_script)
				adcConfig: 'readonly',
				adcData: 'readonly',
				adcMakerQr: 'readonly',

				// Cross-file globals: dialog API from adc-dialogs.js,
				// shared namespace from adc-maker-qr.js, vendor QR lib.
				adcAlert: 'readonly',
				adcError: 'readonly',
				adcSuccess: 'readonly',
				adcConfirm: 'readonly',
				adcPrompt: 'readonly',
				ADC: 'readonly',
				QRCode: 'readonly',
			},
		},
		rules: {
			// Error prevention — enforced in CI
			'no-undef': 'error',
			'no-unused-vars': ['warn', { args: 'none' }],
			'no-console': 'off',
			'no-debugger': 'error',
			'no-eval': 'error',
			'no-implied-eval': 'error',
			'no-new-func': 'error',

			// `!= null` (null-and-undefined check) is a deliberate idiom here.
			'eqeqeq': ['error', 'always', { null: 'ignore' }],

			// Style rules off: the bundle concatenates modules with mixed
			// historical styles; enforcing style on generated output is noise.
			'curly': 'off',
			'indent': 'off',
			'quotes': 'off',
			'semi': 'off',
			'comma-dangle': 'off',
			'no-var': 'off',
			'prefer-const': 'off',
			'prefer-arrow-callback': 'off',
			'no-param-reassign': 'off',
		},
	},
];
