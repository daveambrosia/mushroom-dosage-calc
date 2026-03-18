#!/bin/bash
# Build script for Ambrosia Dosage Calculator
# Usage: ./build-zip.sh [version]
# If no version provided, increments patch version

PLUGIN_DIR="/var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator"
PLUGIN_FILE="$PLUGIN_DIR/ambrosia-dosage-calculator.php"
OUTPUT_DIR="/var/www/html/results"

# Get current version from the constant
CURRENT_VERSION=$(grep "define('ADC_VERSION'" "$PLUGIN_FILE" | sed "s/.*'\([0-9.]*\)'.*/\1/")

if [ -n "$1" ]; then
    NEW_VERSION="$1"
else
    # Auto-increment patch version
    MAJOR=$(echo $CURRENT_VERSION | cut -d. -f1)
    MINOR=$(echo $CURRENT_VERSION | cut -d. -f2)
    PATCH=$(echo $CURRENT_VERSION | cut -d. -f3)
    PATCH=$((PATCH + 1))
    NEW_VERSION="$MAJOR.$MINOR.$PATCH"
fi

echo "Building Ambrosia Dosage Calculator v$NEW_VERSION"
echo "================================================"

# Update version in plugin header
sed -i "s/Version: [0-9.]*/Version: $NEW_VERSION/" "$PLUGIN_FILE"

# Update version constant
sed -i "s/define('ADC_VERSION', '[0-9.]*');/define('ADC_VERSION', '$NEW_VERSION');/" "$PLUGIN_FILE"

# Verify both updated
HEADER_VER=$(grep "Version:" "$PLUGIN_FILE" | head -1 | sed 's/.*Version: //' | tr -d ' *')
CONST_VER=$(grep "define('ADC_VERSION'" "$PLUGIN_FILE" | sed "s/.*'\([0-9.]*\)'.*/\1/")

echo "Header version: $HEADER_VER"
echo "Constant version: $CONST_VER"

if [ "$HEADER_VER" != "$CONST_VER" ]; then
    echo "ERROR: Version mismatch!"
    exit 1
fi

# Create zip
ZIP_NAME="ambrosia-dosage-calculator-v$NEW_VERSION.zip"
cd /var/www/html/wordpress/wp-content/plugins
zip -r "$OUTPUT_DIR/$ZIP_NAME" ambrosia-dosage-calculator -x "*.git*" -x "*.bak" -x "*.backup" -x "*~"

echo ""
echo "✅ Built: $OUTPUT_DIR/$ZIP_NAME"
echo "📦 Download: http://members.tail5d8649.ts.net/results/$ZIP_NAME"
