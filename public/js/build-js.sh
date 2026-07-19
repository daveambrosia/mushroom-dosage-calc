#!/bin/bash
# Concatenate public/js/modules/*.js into public/js/calculator.js.
#
# THE ORDER BELOW IS LOAD-BEARING. It is dependency order, not alphabetical:
# the IIFE wrapper opens first and closes last, and each module references
# symbols defined by the ones before it. A glob would put adc-iife-close.js
# in the middle and produce a syntax error.
#
# Every module header says "Built by: bash public/js/build-js.sh" — this is
# that script. Never edit calculator.js directly; edit a module and rebuild.
# (adc-maker-qr*.js are NOT part of calculator.js — they are standalone
# modules enqueued directly and are only minified, by build-min.sh.)

set -euo pipefail

JS_DIR="$(cd "$(dirname "$0")" && pwd)"
MODULES_DIR="$JS_DIR/modules"
OUT="$JS_DIR/calculator.js"

MODULES=(
    adc-iife-open.js
    adc-constants.js
    adc-state.js
    adc-storage.js
    adc-math.js
    adc-dom.js
    adc-render.js
    adc-modals.js
    adc-collapse.js
    adc-events.js
    adc-init.js
    adc-iife-close.js
)

for m in "${MODULES[@]}"; do
    if [ ! -f "$MODULES_DIR/$m" ]; then
        echo "ERROR: missing module $MODULES_DIR/$m" >&2
        exit 1
    fi
done

TMP="$JS_DIR/.calculator.build-tmp.js"
: > "$TMP"
for m in "${MODULES[@]}"; do
    cat "$MODULES_DIR/$m" >> "$TMP"
done

# The concatenation must parse as a single valid script before it replaces
# the committed bundle.
if command -v node >/dev/null 2>&1; then
    node --check "$TMP" || { echo "ERROR: concatenated bundle fails syntax check" >&2; rm -f "$TMP"; exit 1; }
fi

mv "$TMP" "$OUT"
echo "calculator.js rebuilt: $(wc -c < "$OUT") bytes from ${#MODULES[@]} modules"
