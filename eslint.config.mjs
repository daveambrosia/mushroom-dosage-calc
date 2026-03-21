/**
 * ESLint Configuration for Ambrosia Dosage Calculator
 *
 * @since 2.21.0
 */
export default [
	{
		files: ['public/js/**/*.js', '!public/js/**/*.min.js'],
		languageOptions: {
			ecmaVersion: 2020,
			sourceType: 'script',
			globals: {
				// Browser globals
				window: 'readonly',
				document: 'readonly',
				console: 'readonly',
				localStorage: 'readonly',
				location: 'readonly',
				history: 'readonly',
				setTimeout: 'readonly',
				clearTimeout: 'readonly',
				setInterval: 'readonly',
				clearInterval: 'readonly',
				fetch: 'readonly',
				Promise: 'readonly',
				FormData: 'readonly',
				Blob: 'readonly',
				URL: 'readonly',
				URLSearchParams: 'readonly',
				
				// WordPress globals
				wp: 'readonly',
				
				// jQuery (not used currently)
				jQuery: 'readonly',
				$: 'readonly',
				
				// Plugin-specific globals (from wp_localize_script)
				adcConfig: 'readonly',
				adcData: 'readonly',
			}
		},
		rules: {
			// Error prevention
			'no-undef': 'error',
			'no-unused-vars': 'warn',
			'no-console': 'warn',
			'no-debugger': 'error',
			
			// Best practices
			'eqeqeq': ['error', 'always'],
			'curly': ['error', 'all'],
			'no-eval': 'error',
			'no-implied-eval': 'error',
			'no-new-func': 'error',
			'no-param-reassign': 'warn',
			
			// Code style (relaxed for now)
			'indent': ['warn', 'tab', { 'SwitchCase': 1 }],
			'quotes': ['warn', 'single', { 'avoidEscape': true }],
			'semi': ['warn', 'always'],
			'comma-dangle': ['warn', 'never'],
			
			// Disabled rules (too strict for current codebase)
			'no-var': 'off',
			'prefer-const': 'off',
			'prefer-arrow-callback': 'off',
		}
	}
];
