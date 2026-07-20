#!/bin/bash
# Release packaging for Ambrosia Dosage Calculator.
# Usage: ./build-zip.sh [version]
# With no argument, increments the patch version.
#
# Builds assets first (build-min.sh, which rebuilds calculator.js from the
# modules and minifies everything), stamps the version into every file that
# carries one, stages production files into a clean directory, verifies that
# every asset the plugin enqueues actually exists in the package, and zips.
#
# The zip is named ambrosia-dosage-calculator-v<version>.zip — the GitHub
# updater looks for exactly this asset name on a release and refuses to fall
# back to the source zipball, so a release without this file simply does not
# update anyone.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_FILE="$PLUGIN_DIR/ambrosia-dosage-calculator.php"
OUTPUT_DIR="${OUTPUT_DIR:-$PLUGIN_DIR/build}"
SLUG="ambrosia-dosage-calculator"

CURRENT_VERSION=$(grep "define.*ADC_VERSION" "$PLUGIN_FILE" | sed "s/.*'\([0-9.]*\)'.*/\1/")

if [ -n "${1:-}" ]; then
    NEW_VERSION="$1"
else
    MAJOR=$(echo "$CURRENT_VERSION" | cut -d. -f1)
    MINOR=$(echo "$CURRENT_VERSION" | cut -d. -f2)
    PATCH=$(echo "$CURRENT_VERSION" | cut -d. -f3)
    PATCH=$((PATCH + 1))
    NEW_VERSION="$MAJOR.$MINOR.$PATCH"
fi

echo "Building Ambrosia Dosage Calculator v$NEW_VERSION"
echo "================================================"

# Rebuild all assets from source. build-min.sh also runs the PHP syntax
# check; a build that fails aborts the release (set -e).
bash "$PLUGIN_DIR/build-min.sh"

# Run the JS test suite when the toolchain is present.
if [ -d "$PLUGIN_DIR/node_modules" ]; then
    echo "Running JS tests..."
    ( cd "$PLUGIN_DIR" && npx vitest run --reporter=dot )
else
    echo "WARNING: node_modules missing — JS tests skipped (npm ci to enable)."
fi

# Run the PHP test suite when the WordPress test library is available.
if [ -n "${WP_TESTS_DIR:-}" ] && [ -f "$PLUGIN_DIR/vendor/bin/phpunit" ]; then
    echo "Running PHP tests..."
    ( cd "$PLUGIN_DIR" && WP_TESTS_DIR="$WP_TESTS_DIR" ./vendor/bin/phpunit )
else
    echo "WARNING: WP_TESTS_DIR not set or phpunit missing — PHP tests skipped."
fi

# ---- Version stamping (portable sed: -i.bak then remove) ----
sed -i.bak "s/Version: [0-9.]*/Version: $NEW_VERSION/" "$PLUGIN_FILE"
sed -i.bak "s/define( 'ADC_VERSION', '[0-9.]*' );/define( 'ADC_VERSION', '$NEW_VERSION' );/" "$PLUGIN_FILE"
rm -f "$PLUGIN_FILE.bak"

sed -i.bak "s/\"version\": \"[0-9.]*\"/\"version\": \"$NEW_VERSION\"/" "$PLUGIN_DIR/package.json"
rm -f "$PLUGIN_DIR/package.json.bak"

# Verify every version carrier agrees before packaging. Three carriers since
# readme.txt was dropped in favor of README.md (which is not versioned).
HEADER_VER=$(grep "Version:" "$PLUGIN_FILE" | head -1 | sed 's/.*Version: //' | tr -d ' *')
CONST_VER=$(grep "define.*ADC_VERSION" "$PLUGIN_FILE" | sed "s/.*'\([0-9.]*\)'.*/\1/")
PKG_VER=$(grep '"version"' "$PLUGIN_DIR/package.json" | head -1 | sed 's/.*"version": "\([0-9.]*\)".*/\1/')

echo "Header: $HEADER_VER | Constant: $CONST_VER | package.json: $PKG_VER"
if [ "$HEADER_VER" != "$NEW_VERSION" ] || [ "$CONST_VER" != "$NEW_VERSION" ] || \
   [ "$PKG_VER" != "$NEW_VERSION" ]; then
    echo "ERROR: version mismatch across files — aborting." >&2
    exit 1
fi

# ---- Stage production files ----
STAGING="$(mktemp -d)/$SLUG"
mkdir -p "$STAGING"
rsync -a "$PLUGIN_DIR/" "$STAGING/" \
    --exclude ".git" \
    --exclude ".github" \
    --exclude ".claude" \
    --exclude "/node_modules" \
    --exclude "/vendor" \
    --exclude "/tests" \
    --exclude "/bin" \
    --exclude "/docs" \
    --exclude "/build" \
    --exclude "build-min.sh" \
    --exclude "build-zip.sh" \
    --exclude "*.zip" \
    --exclude "*.bak" \
    --exclude "*~" \
    --exclude ".DS_Store" \
    --exclude "phpcs.xml" \
    --exclude "phpcs.xml.dist" \
    --exclude "phpstan.neon" \
    --exclude "phpunit.xml.dist" \
    --exclude ".phpunit.result.cache" \
    --exclude ".phpunit.cache" \
    --exclude "eslint.config.mjs" \
    --exclude "vitest.config.mjs" \
    --exclude "package.json" \
    --exclude "package-lock.json" \
    --exclude "composer.json" \
    --exclude "composer.lock" \
    --exclude "README-DEV.md" \
    --exclude "README.md" \
    --exclude "CHANGELOG.md" \
    --exclude "REMEDIATION-PLAN.md" \
    --exclude "public/js/build-js.sh" \
    --exclude "public/js/modules/*.js"

# The runtime needs the minified maker-QR modules even though module SOURCES
# are excluded — the plugin enqueues these three directly (this exact
# exclusion once shipped a release with the whole maker QR feature missing).
mkdir -p "$STAGING/public/js/modules"
cp "$PLUGIN_DIR"/public/js/modules/*.min.js "$STAGING/public/js/modules/"

# ---- Verify every enqueued asset exists in the staging tree ----
echo "Verifying enqueued assets exist in package..."
MISSING=0
while IFS= read -r asset; do
    # Try the path as-is plus its .min variant (the enqueue code picks
    # min/non-min at runtime via $min suffix logic).
    base="${asset%.js}"; base="${base%.css}"; base="${base%.min}"
    ext="${asset##*.}"
    if [ ! -f "$STAGING/$asset" ] && [ ! -f "$STAGING/$base.$ext" ] && [ ! -f "$STAGING/$base.min.$ext" ]; then
        echo "ERROR: enqueued asset missing from package: $asset" >&2
        MISSING=1
    fi
done < <(grep -rhoE "'public/[A-Za-z0-9/._-]+\.(js|css)'" "$PLUGIN_DIR"/*.php "$PLUGIN_DIR"/includes "$PLUGIN_DIR"/admin 2>/dev/null | tr -d "'" | sort -u)
if [ "$MISSING" -ne 0 ]; then
    echo "ERROR: package would 404 at runtime — aborting." >&2
    exit 1
fi
echo "All enqueued assets present."

# ---- Zip ----
mkdir -p "$OUTPUT_DIR"
ZIP_NAME="$SLUG-v$NEW_VERSION.zip"
rm -f "$OUTPUT_DIR/$ZIP_NAME"
( cd "$(dirname "$STAGING")" && zip -rq "$OUTPUT_DIR/$ZIP_NAME" "$SLUG" )
rm -rf "$(dirname "$STAGING")"

echo ""
echo "Built: $OUTPUT_DIR/$ZIP_NAME ($(du -h "$OUTPUT_DIR/$ZIP_NAME" | cut -f1))"
echo "Upload this file as a release asset named exactly $ZIP_NAME"
