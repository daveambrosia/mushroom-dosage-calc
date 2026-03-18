#!/bin/bash
# Build minified CSS files from source
# Run from plugin root: bash public/css/build.sh
# Requires: csso-cli (npm install -g csso-cli)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if ! command -v csso &>/dev/null; then
    echo "ERROR: csso not found. Run: npm install -g csso-cli"
    exit 1
fi

echo "Building calculator.min.css..."
csso "$SCRIPT_DIR/calculator.css" -o "$SCRIPT_DIR/calculator.min.css"
echo "  $(wc -l < "$SCRIPT_DIR/calculator.css") lines → $(wc -c < "$SCRIPT_DIR/calculator.min.css") bytes"

echo "Building calculator-themes.min.css..."
csso "$SCRIPT_DIR/calculator-themes.css" -o "$SCRIPT_DIR/calculator-themes.min.css"
echo "  $(wc -l < "$SCRIPT_DIR/calculator-themes.css") lines → $(wc -c < "$SCRIPT_DIR/calculator-themes.min.css") bytes"

echo "Done. Bump ADC_VERSION in ambrosia-dosage-calculator.php to bust browser cache."
