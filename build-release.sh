#!/bin/bash
#
# Ambrosia Dosage Calculator - Release Builder
# Creates a distributable ZIP file for WordPress installation
#

set -e

# Get script directory (plugin root)
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_NAME="ambrosia-dosage-calculator"

# Extract version from main plugin file
VERSION=$(grep "Version:" "$PLUGIN_DIR/ambrosia-dosage-calculator.php" | head -1 | sed 's/.*Version: *//' | tr -d ' \r')

if [ -z "$VERSION" ]; then
    echo "❌ Could not extract version from plugin file"
    exit 1
fi

# Output settings
OUTPUT_DIR="${1:-$HOME}"
ZIP_NAME="${PLUGIN_NAME}-v${VERSION}.zip"
TEMP_DIR=$(mktemp -d)
BUILD_DIR="$TEMP_DIR/$PLUGIN_NAME"

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║      Ambrosia Dosage Calculator - Release Builder           ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "  Version:    $VERSION"
echo "  Output:     $OUTPUT_DIR/$ZIP_NAME"
echo ""

# Create build directory
mkdir -p "$BUILD_DIR"

echo "📁 Copying plugin files..."

# Copy all files except excluded ones
rsync -a "$PLUGIN_DIR/" "$BUILD_DIR/" \
    --exclude='*.bak' \
    --exclude='*.bak.*' \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='node_modules' \
    --exclude='package-lock.json' \
    --exclude='composer.lock' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='*.log' \
    --exclude='*.map' \
    --exclude='build-release.sh' \
    --exclude='*.zip' \
    --exclude='tests/' \
    --exclude='phpunit.xml' \
    --exclude='.phpcs.xml' \
    --exclude='*.orig' \
    --exclude='*~' \
    --exclude='.idea/' \
    --exclude='.vscode/' \
    2>/dev/null

# Count files
FILE_COUNT=$(find "$BUILD_DIR" -type f | wc -l)
echo "  Copied $FILE_COUNT files"

echo ""
echo "📦 Creating ZIP archive..."

# Create ZIP file
cd "$TEMP_DIR"
zip -rq "$OUTPUT_DIR/$ZIP_NAME" "$PLUGIN_NAME"

# Cleanup
rm -rf "$TEMP_DIR"

# Get file size
SIZE=$(du -h "$OUTPUT_DIR/$ZIP_NAME" | cut -f1)

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                    BUILD COMPLETE ✅                         ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "  📦 $ZIP_NAME ($SIZE)"
echo "  📍 $OUTPUT_DIR/$ZIP_NAME"
echo ""
echo "  To install: WordPress Admin → Plugins → Add New → Upload Plugin"
echo ""
