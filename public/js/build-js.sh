#!/bin/bash
# Build calculator.js from module files
# Run from plugin root: bash public/js/build-js.sh
# Requires: terser (npm install -g terser)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODULES_DIR="$SCRIPT_DIR/modules"
OUTPUT="$SCRIPT_DIR/calculator.js"
OUTPUT_MIN="$SCRIPT_DIR/calculator.min.js"

echo "Building calculator.js..."

cat \
    "$MODULES_DIR/adc-iife-open.js" \
    "$MODULES_DIR/adc-constants.js" \
    "$MODULES_DIR/adc-state.js" \
    "$MODULES_DIR/adc-storage.js" \
    "$MODULES_DIR/adc-math.js" \
    "$MODULES_DIR/adc-dom.js" \
    "$MODULES_DIR/adc-render.js" \
    "$MODULES_DIR/adc-modals.js" \
    "$MODULES_DIR/adc-collapse.js" \
    "$MODULES_DIR/adc-events.js" \
    "$MODULES_DIR/adc-init.js" \
    "$MODULES_DIR/adc-iife-close.js" \
    > "$OUTPUT"

LINE_COUNT=$(wc -l < "$OUTPUT")
echo "Built calculator.js ($LINE_COUNT lines)"

if command -v terser &>/dev/null; then
    terser "$OUTPUT" -o "$OUTPUT_MIN" --compress --mangle
    echo "Minified → calculator.min.js"
else
    echo "WARNING: terser not found. Run: npm install -g terser"
    echo "Copying unminified as .min.js (NOT for production)"
    cp "$OUTPUT" "$OUTPUT_MIN"
fi

echo "Done."
