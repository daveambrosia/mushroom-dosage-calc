# Pickr Color Picker Replacement — Design Spec

## Goal

Replace WordPress's built-in wp-color-picker (Iris) with Pickr v1.9.1 (Nano theme) in the template builder admin page, adding eyedropper, saved palettes, and color harmony suggestions.

## Motivation

The current wp-color-picker is hex-only, visually dated, and lacks precise controls. Users want:
- A modern, clean UI
- Precise color control with HSL format switching
- A cleaner "clear" experience (integrated into the picker, not a separate button)
- Saved color palettes, color harmony tools, and eyedropper support

## Architecture

### Core Replacement

Replace wp-color-picker with **Pickr v1.9.1** (Nano theme), loaded from CDN:
- JS: `https://cdn.jsdelivr.net/npm/@simonwep/pickr@1.9.1/dist/pickr.min.js` (23KB)
- CSS: `https://cdn.jsdelivr.net/npm/@simonwep/pickr@1.9.1/dist/themes/nano.min.css` (9KB)

Each color field gets a small color swatch `<div class="adc-pickr-trigger">` as the Pickr trigger element. The existing `<input type="text">` stays beside it for display and form submission — clicking the swatch opens Pickr, clicking the input allows manual hex typing. The input syncs bidirectionally: Pickr `save` writes to the input, and input `change` calls `pickrInstance.setColor()`.

**Two-step save flow:** Unlike wp-color-picker which wrote immediately, Pickr uses a save/clear button inside the popup. The `change` event fires during picking for live preview, but the input field value only updates on `save`. This means `formDirty` is set on `save`, not on `change`.

Configuration per instance:
- `theme: 'nano'`
- `lockOpacity: true` — no alpha slider, hex-only output
- `defaultRepresentation: 'HEX'`
- `comparison: true` — shows old vs new color
- Components: palette, preview, hue slider, input field, hex/HSL format toggle, save button, clear button
- Swatches: 6 core colors from the Minimal built-in theme as defaults

On `save` event: write hex value to the input field, call `debouncedPreview()` and `adcUpdateContrastCheck()`, set `formDirty = true`. When `color` is `null` (clear), write empty string. Note: clear fires `save` with `null` — always null-check.

On `change` event: call `debouncedPreview()` for live preview while picking (before save).

### Custom Feature 1: Saved Palettes (Recent Colors)

A "recent colors" row displayed as clickable swatch circles below each color card's Pickr trigger.

- Auto-populates as the user saves colors (last 12, deduplicated)
- Stored in `localStorage` under key `adc_template_palette`
- Clicking a recent swatch calls `pickrInstance.setColor(hex)` on the active field
- Shared across all 30 color fields (one global palette)

### Custom Feature 2: Color Harmony Suggestions

A small toolbar rendered below each color card with three buttons: Complementary, Analogous, Triadic.

When clicked:
1. Read the current hex from the field
2. Convert to HSL
3. Calculate harmonies by rotating hue:
   - Complementary: +180 degrees (1 suggestion)
   - Analogous: +30 and -30 degrees (2 suggestions)
   - Triadic: +120 and +240 degrees (2 suggestions)
4. Render suggested colors as clickable chips
5. Clicking a chip applies it to that field via `pickrInstance.setColor(hex)`

Implementation: ~40 lines of HSL rotation logic. Pure JS, no library.

### Custom Feature 3: Eyedropper

A small eyedropper icon button rendered next to each color input.

- Uses the browser `EyeDropper` API (`new EyeDropper().open()`)
- Supported in Chrome/Edge 95+. On unsupported browsers (Firefox, Safari), the button is not rendered — progressive enhancement via `if ('EyeDropper' in window)`.
- Result (sRGBHex string) feeds into the field's Pickr instance via `setColor()`.

## Files Changed

### `admin/class-adc-template-builder.php`

- **Remove**: `wp_enqueue_style('wp-color-picker')` and the `wp-color-picker` script dependency from `wp_enqueue_script`
- **Add**: Pickr CDN CSS (`nano.min.css`) via `wp_enqueue_style('pickr-nano', ...)` and Pickr CDN JS (`pickr.min.js`) via `wp_enqueue_script('pickr', ...)`
- **Update**: `adc-template-builder` CSS dependency array from `array('wp-color-picker')` to `array('pickr-nano')`
- **Update**: `adc-template-builder` JS dependency array from `array('jquery', 'wp-color-picker')` to `array('jquery', 'pickr')`
- **Update color card HTML**: Remove `data-default-color` attribute (wp-color-picker specific). Add `<div class="adc-pickr-trigger">` beside each input as the Pickr anchor. Add container `<div>` for eyedropper button and harmony toolbar.

### `admin/js/adc-template-builder.js`

- **Remove**: `.wpColorPicker()` initialization block (the `if ($.fn.wpColorPicker)` guard and its contents)
- **Add**: Pickr initialization loop guarded by `if (typeof Pickr !== 'undefined')` — iterate all `.adc-color-picker-input` elements, create a `Pickr.create()` instance per field, store instances in a `Map` keyed by `data-key`
- **Add**: Eyedropper button creation and click handler (progressive enhancement)
- **Add**: Color harmony toolbar creation and HSL rotation logic
- **Add**: Recent palette management (localStorage read/write, swatch rendering)
- **Update**: `adcSendPreviewVars()` — still reads hex from input `.value`, no change needed
- **Update**: `adcLoadBuiltinTemplate()` — instead of `.wpColorPicker('color', value)`, call `pickrInstance.setColor(value)` per field. For clearing all fields, call `pickrInstance.setColor(null)` to reset Pickr's visual state (not just the input value).

Note: The template picker modal navigates via `window.location.href` — it does not call wpColorPicker and needs no Pickr changes.

### `admin/css/adc-template-builder.css`

- **Remove**: `.wp-picker-container`, `.wp-picker-input-wrap` overrides
- **Add**: `.adc-pickr-trigger` swatch styling and Pickr Nano positioning within `.adc-color-card` grid cards
- **Add**: Styles for harmony toolbar (small button row, chip swatches)
- **Add**: Styles for eyedropper button (icon, hover state)
- **Add**: Styles for recent palette row (small swatch circles)

## What Does NOT Change

- **PHP `handle_save()`** — still receives hex via POST, validates with `sanitize_hex_color()`
- **`generate_template_css()`** — no change, still outputs `--adc-*` custom properties
- **Live preview postMessage flow** — `adcSendPreviewVars()` collects input `.value` hex strings, same as before
- **WCAG contrast checker** — reads hex from inputs, luminance/ratio math unchanged
- **Template import/export** — JSON format unchanged, same hex values
- **Server-side sanitization** — `sanitize_hex_color()` unchanged since Pickr with `lockOpacity` outputs hex
- **jQuery dependency** — the template builder JS still uses jQuery for accordion, range sliders, dirty state, etc. Only `wp-color-picker` is removed from the dependency chain.
- **`build-min.sh`** — admin JS/CSS files are served unminified; no build script changes needed

## Backwards Compatibility

Existing saved templates store hex values. Pickr reads and writes hex. No migration needed.

## Graceful Degradation

If Pickr CDN is unreachable, the `if (typeof Pickr !== 'undefined')` guard prevents runtime errors. The raw `<input type="text">` fields remain functional for manual hex entry. Eyedropper, harmony, and palette features (all rendered by JS that depends on Pickr) silently do not render.

## Browser Support

- Pickr Nano theme requires CSS Grid — all modern browsers. No IE11 (already not supported by WordPress 6.0+).
- EyeDropper API: Chrome/Edge 95+. Progressively enhanced — hidden on unsupported browsers.

## Dependencies

- **Pickr v1.9.1** (MIT license, GPL-compatible) — loaded from jsdelivr CDN
- No npm packages, no build step changes
