#!/bin/bash
# ADC Build Script: Minify CSS and JS for production
# Requires: terser (JS), csso-cli (CSS)
# Install: npm install -g terser csso-cli

set -e

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
CSS_DIR="$PLUGIN_DIR/public/css"
JS_DIR="$PLUGIN_DIR/public/js"

echo "=== ADC Minification Build ==="

# PHP lint check (BUG-001 prevention)
echo "Running PHP syntax check..."
LINT_ERRORS=$(find "$PLUGIN_DIR" -name "*.php" -not -path "*/vendor/*" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" || true)
if [ -n "$LINT_ERRORS" ]; then
    echo "ERROR: PHP syntax errors found:"
    echo "$LINT_ERRORS"
    exit 1
fi
echo "PHP syntax: OK"

# Check dependencies
if ! command -v terser &>/dev/null; then
    echo "ERROR: terser not found. Install with: npm install -g terser"
    exit 1
fi
if ! command -v csso &>/dev/null; then
    echo "ERROR: csso not found. Install with: npm install -g csso-cli"
    exit 1
fi

# JS
echo ""
echo "--- JavaScript ---"
BEFORE_JS=$(wc -c < "$JS_DIR/calculator.js")
terser "$JS_DIR/calculator.js" --compress --mangle -o "$JS_DIR/calculator.min.js"
AFTER_JS=$(wc -c < "$JS_DIR/calculator.min.js")
echo "calculator.js: ${BEFORE_JS}B → ${AFTER_JS}B ($(( (BEFORE_JS - AFTER_JS) * 100 / BEFORE_JS ))% smaller)"

BEFORE_DLG=$(wc -c < "$JS_DIR/adc-dialogs.js")
terser "$JS_DIR/adc-dialogs.js" --compress --mangle -o "$JS_DIR/adc-dialogs.min.js"
AFTER_DLG=$(wc -c < "$JS_DIR/adc-dialogs.min.js")
echo "adc-dialogs.js: ${BEFORE_DLG}B → ${AFTER_DLG}B ($(( (BEFORE_DLG - AFTER_DLG) * 100 / BEFORE_DLG ))% smaller)"

# CSS
echo ""
echo "--- CSS ---"
BEFORE_CSS=$(wc -c < "$CSS_DIR/calculator.css")
csso "$CSS_DIR/calculator.css" -o "$CSS_DIR/calculator.min.css"
AFTER_CSS=$(wc -c < "$CSS_DIR/calculator.min.css")
echo "calculator.css: ${BEFORE_CSS}B → ${AFTER_CSS}B ($(( (BEFORE_CSS - AFTER_CSS) * 100 / BEFORE_CSS ))% smaller)"

BEFORE_THEMES=$(wc -c < "$CSS_DIR/calculator-themes.css")
csso "$CSS_DIR/calculator-themes.css" -o "$CSS_DIR/calculator-themes.min.css"
AFTER_THEMES=$(wc -c < "$CSS_DIR/calculator-themes.min.css")
echo "calculator-themes.css: ${BEFORE_THEMES}B → ${AFTER_THEMES}B ($(( (BEFORE_THEMES - AFTER_THEMES) * 100 / BEFORE_THEMES ))% smaller)"

BEFORE_FONTS=$(wc -c < "$CSS_DIR/adc-fonts.css")
csso "$CSS_DIR/adc-fonts.css" -o "$CSS_DIR/adc-fonts.min.css"
AFTER_FONTS=$(wc -c < "$CSS_DIR/adc-fonts.min.css")
echo "adc-fonts.css: ${BEFORE_FONTS}B → ${AFTER_FONTS}B"

BEFORE_DCSS=$(wc -c < "$CSS_DIR/adc-dialogs.css")
csso "$CSS_DIR/adc-dialogs.css" -o "$CSS_DIR/adc-dialogs.min.css"
AFTER_DCSS=$(wc -c < "$CSS_DIR/adc-dialogs.min.css")
echo "adc-dialogs.css: ${BEFORE_DCSS}B → ${AFTER_DCSS}B"

TOTAL_BEFORE=$((BEFORE_JS + BEFORE_DLG + BEFORE_CSS + BEFORE_THEMES + BEFORE_FONTS + BEFORE_DCSS))
TOTAL_AFTER=$((AFTER_JS + AFTER_DLG + AFTER_CSS + AFTER_THEMES + AFTER_FONTS + AFTER_DCSS))
echo ""
echo "=== Total: ${TOTAL_BEFORE}B → ${TOTAL_AFTER}B ($(( (TOTAL_BEFORE - TOTAL_AFTER) * 100 / TOTAL_BEFORE ))% smaller) ==="
