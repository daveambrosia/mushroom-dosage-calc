# Pickr Color Picker Replacement — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace WordPress's wp-color-picker (Iris) with Pickr v1.9.1 (Nano theme) in the template builder, adding eyedropper, saved palettes, and color harmony tools.

**Architecture:** Swap enqueued assets (wp-color-picker to Pickr CDN), update PHP color card HTML to add a trigger swatch div, replace jQuery `.wpColorPicker()` init with vanilla `Pickr.create()` loop, then layer on three custom features (recent palette, harmony toolbar, eyedropper).

**Tech Stack:** Pickr v1.9.1 (CDN), vanilla JS, jQuery (existing), WordPress admin PHP

**Spec:** `docs/superpowers/specs/2026-03-18-pickr-color-picker-design.md`

**Plugin root:** `/var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator`

**Before any code change**, create a zip backup:
```bash
zip -r /home/dave/backup/ambrosia-dosage-calculator-$(date +%Y%m%d-%H%M%S).zip /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator/
```

---

## File Structure

| File | Action | Responsibility |
|------|--------|---------------|
| `admin/class-adc-template-builder.php` | Modify (lines 1065-1067, 1163-1170) | Swap enqueued assets, update color card HTML |
| `admin/js/adc-template-builder.js` | Modify (lines 1-10, 37-53, 319-335, 457-460) | Replace wpColorPicker init with Pickr, add features |
| `admin/css/adc-template-builder.css` | Modify (lines 126-151) | Remove wp-color-picker overrides, add Pickr/feature styles |

No new files created. All changes are modifications to existing files.

**Note:** Line numbers reference the original unmodified file. After each task inserts/deletes code, subsequent line numbers shift. Match on the code patterns shown, not line numbers.

---

### Task 1: Swap Enqueued Assets (PHP)

Replace wp-color-picker CSS/JS dependencies with Pickr CDN assets.

**Files:**
- Modify: `admin/class-adc-template-builder.php:1065-1067`

- [ ] **Step 1: Update the asset enqueue block**

Replace lines 1065-1067:

```php
// OLD:
wp_enqueue_style('wp-color-picker');
wp_enqueue_style('adc-template-builder', ADC_PLUGIN_URL . 'admin/css/adc-template-builder.css', array('wp-color-picker'), ADC_VERSION);
wp_enqueue_script('adc-template-builder', ADC_PLUGIN_URL . 'admin/js/adc-template-builder.js', array('jquery', 'wp-color-picker'), ADC_VERSION, true);
```

With:

```php
// NEW:
wp_enqueue_style('pickr-nano', 'https://cdn.jsdelivr.net/npm/@simonwep/pickr@1.9.1/dist/themes/nano.min.css', array(), '1.9.1');
wp_enqueue_style('adc-template-builder', ADC_PLUGIN_URL . 'admin/css/adc-template-builder.css', array('pickr-nano'), ADC_VERSION);
wp_enqueue_script('pickr', 'https://cdn.jsdelivr.net/npm/@simonwep/pickr@1.9.1/dist/pickr.min.js', array(), '1.9.1', true);
wp_enqueue_script('adc-template-builder', ADC_PLUGIN_URL . 'admin/js/adc-template-builder.js', array('jquery', 'pickr'), ADC_VERSION, true);
```

- [ ] **Step 2: Verify the page loads without JS errors**

Open the template builder edit page in browser:
```
https://members.tail5d8649.ts.net/wordpress/wp-admin/admin.php?page=dosage-calculator-template-builder&action=edit&slug=custom-daves-test
```

Check browser dev console. Pickr JS and CSS should load from CDN. The old wp-color-picker will no longer initialize (its JS is not loaded), so color fields will appear as plain text inputs. This is expected at this stage.

- [ ] **Step 3: Commit**

```bash
git add admin/class-adc-template-builder.php
git commit -m "refactor: swap wp-color-picker enqueue for Pickr CDN assets"
```

---

### Task 2: Update Color Card HTML (PHP)

Add a Pickr trigger swatch `<div>` to each color card. Remove wp-color-picker-specific `data-default-color` attribute.

**Files:**
- Modify: `admin/class-adc-template-builder.php:1163-1170`

- [ ] **Step 1: Update the color card rendering loop**

Find lines 1163-1170 (inside the `foreach ($group_vars as $key => $def)` loop for color fields):

```php
// OLD:
echo '<div class="adc-color-card">';
echo '<label for="' . esc_attr($post_key) . '" class="adc-color-label">' . esc_html($label) . '</label>';
echo '<input type="text" id="' . esc_attr($post_key) . '" name="' . esc_attr($post_key) . '" value="' . esc_attr($current_val) . '" class="adc-color-picker-input" data-key="' . esc_attr($key) . '" data-default-color="">';
echo '<label for="' . esc_attr($post_key) . '" class="screen-reader-text">' . esc_html($label) . '</label>';
if ($desc) {
    echo '<p class="description">' . esc_html($desc) . '</p>';
}
echo '</div>';
```

Replace with:

```php
// NEW:
echo '<div class="adc-color-card">';
echo '<label for="' . esc_attr($post_key) . '" class="adc-color-label">' . esc_html($label) . '</label>';
echo '<div class="adc-pickr-row">';
echo '<div class="adc-pickr-trigger" data-for="' . esc_attr($post_key) . '"></div>';
echo '<input type="text" id="' . esc_attr($post_key) . '" name="' . esc_attr($post_key) . '" value="' . esc_attr($current_val) . '" class="adc-color-picker-input" data-key="' . esc_attr($key) . '" placeholder="#000000">';
echo '</div>';
echo '<div class="adc-harmony-toolbar" data-for="' . esc_attr($post_key) . '"></div>';
if ($desc) {
    echo '<p class="description">' . esc_html($desc) . '</p>';
}
echo '</div>';
```

- [ ] **Step 2: Verify the HTML renders correctly**

Reload the template builder edit page. Each color card should now show a small empty `<div class="adc-pickr-trigger">` next to a plain text input. The harmony toolbar div will be empty (populated by JS later).

- [ ] **Step 3: Commit**

```bash
git add admin/class-adc-template-builder.php
git commit -m "refactor: update color card HTML for Pickr trigger swatch"
```

---

### Task 3: Replace wpColorPicker Init with Pickr (JS)

Remove the wpColorPicker initialization block and replace it with a Pickr initialization loop. Also update `adcLoadBuiltinTemplate()` to use Pickr API.

**Files:**
- Modify: `admin/js/adc-template-builder.js`

- [ ] **Step 1: Update the file header comment**

Replace line 3:
```js
// OLD:
 * Handles: wp-color-picker init, accordion toggle, live preview,
```
With:
```js
// NEW:
 * Handles: Pickr color-picker init, accordion toggle, live preview,
```

- [ ] **Step 2: Add Pickr instance map and initialization function after the debounce helper (after line 22)**

Insert after line 22 (after the `adcDebounce` function closing brace):

```js
    // ---- Pickr instance management ----
    var pickrInstances = {};

    // Default swatches: core colors from the Minimal built-in theme
    var defaultSwatches = [
        '#ffffff', '#1a1a2e', '#e94560',
        '#f5f5f5', '#eaeaea', '#cccccc'
    ];

    // Stub — replaced by full implementation in Task 5
    function adcAddRecentColor() {}

    function initPickrInstance(triggerEl, inputEl) {
        var key = inputEl.dataset.key;
        var currentVal = inputEl.value || null;
        var savedVal = currentVal; // Track value before picker opens
        var didSave = false;

        var pickr = Pickr.create({
            el: triggerEl,
            theme: 'nano',
            default: currentVal,
            lockOpacity: true,
            defaultRepresentation: 'HEX',
            comparison: true,
            closeWithKey: 'Escape',
            swatches: defaultSwatches,
            components: {
                palette: true,
                preview: true,
                opacity: false,
                hue: true,
                interaction: {
                    hex: true,
                    rgba: false,
                    hsla: true,
                    hsva: false,
                    cmyk: false,
                    input: true,
                    clear: true,
                    save: true,
                    cancel: false
                }
            }
        });

        pickr.on('show', function() {
            savedVal = inputEl.value;
            didSave = false;
        });

        pickr.on('save', function(color, instance) {
            didSave = true;
            if (color) {
                var hex = color.toHEXA().toString();
                if (hex.length === 9 && hex.endsWith('FF')) {
                    hex = hex.slice(0, 7); // Strip opaque alpha: #RRGGBBFF to #RRGGBB
                }
                inputEl.value = hex;
                savedVal = hex;
                adcAddRecentColor(hex);
            } else {
                inputEl.value = '';
                savedVal = '';
            }
            instance.hide();
            debouncedPreview();
            adcUpdateContrastCheck();
            formDirty = true;
        });

        pickr.on('change', function(color) {
            // Live preview while picking (before save).
            // Temporarily set input value for preview; restored on hide if no save.
            var hex = color.toHEXA().toString();
            if (hex.length === 9 && hex.endsWith('FF')) {
                hex = hex.slice(0, 7);
            }
            inputEl.value = hex;
            debouncedPreview();
        });

        pickr.on('hide', function() {
            // Restore original value if picker closed without saving
            if (!didSave) {
                inputEl.value = savedVal;
                debouncedPreview();
            }
        });

        // Sync: if user types hex directly in input
        inputEl.addEventListener('change', function() {
            var val = inputEl.value.trim();
            if (/^#[0-9a-fA-F]{3,8}$/.test(val)) {
                pickr.setColor(val, true);
            } else if (val === '') {
                pickr.setColor(null, true);
            }
        });

        pickrInstances[key] = pickr;
        return pickr;
    }
```

Note: Pickr's `.pcr-button` automatically displays the current color — no manual `triggerEl.style.backgroundColor` is needed.

- [ ] **Step 3: Remove the old wpColorPicker init block**

Delete lines 319-335 (the `if ($.fn.wpColorPicker)` block):

```js
// DELETE THIS ENTIRE BLOCK:
        // ---- Initialize wp-color-picker ----
        if ($.fn.wpColorPicker) {
            $('.adc-color-picker-input').wpColorPicker({
                change: function(event, ui) {
                    setTimeout(function() {
                        debouncedPreview();
                        adcUpdateContrastCheck();
                        formDirty = true;
                    }, 10);
                },
                clear: function() {
                    debouncedPreview();
                    adcUpdateContrastCheck();
                    formDirty = true;
                }
            });
        }
```

- [ ] **Step 4: Add Pickr initialization in its place**

Insert where the deleted block was (still inside `$(document).ready`):

```js
        // ---- Initialize Pickr color pickers ----
        if (typeof Pickr !== 'undefined') {
            document.querySelectorAll('.adc-pickr-trigger').forEach(function(triggerEl) {
                var inputId = triggerEl.dataset.for;
                var inputEl = document.getElementById(inputId);
                if (inputEl) {
                    initPickrInstance(triggerEl, inputEl);
                }
            });
        }
```

- [ ] **Step 5: Update `adcLoadBuiltinTemplate()` to use Pickr API**

In the `adcLoadBuiltinTemplate` function, replace the clear-all block (lines 50-53):

```js
// OLD:
        $('.adc-color-picker-input').each(function() {
            $(this).val('');
            try { $(this).wpColorPicker('color', ''); } catch(e) {}
        });
```

With:

```js
// NEW:
        $('.adc-color-picker-input').each(function() {
            $(this).val('');
            var key = $(this).data('key');
            if (pickrInstances[key]) {
                pickrInstances[key].setColor(null, true);
            }
        });
```

And replace the color-set line inside the fill loop (lines 62-64):

```js
// OLD:
                if (/^#[0-9a-fA-F]{3,8}$/.test(vars[key])) {
                    try { $input.wpColorPicker('color', vars[key]); } catch(e) {}
                }
```

With:

```js
// NEW:
                if (/^#[0-9a-fA-F]{3,8}$/.test(vars[key])) {
                    var dataKey = $input.data('key');
                    if (pickrInstances[dataKey]) {
                        pickrInstances[dataKey].setColor(vars[key], true);
                    }
                }
```

- [ ] **Step 6: Verify Pickr works**

Reload the template builder edit page. Each color card should show a small Pickr color swatch button. Clicking it should open the Nano-themed picker popup with palette, hue slider, hex/HSL input, save/clear buttons. Picking a color and clicking Save should update the text input and the live preview iframe.

Test:
1. Pick a color, Save, check input value updates, preview updates
2. Clear a color, check input empties, preview updates
3. Type a hex directly in the input, check Pickr swatch updates
4. Use "Start from..." dropdown to load a built-in template, all pickers should update

- [ ] **Step 7: Commit**

```bash
git add admin/js/adc-template-builder.js
git commit -m "feat: replace wpColorPicker with Pickr initialization"
```

---

### Task 4: Update CSS (Remove wp-color-picker Overrides, Add Pickr Styles)

**Files:**
- Modify: `admin/css/adc-template-builder.css:126-151`

- [ ] **Step 1: Update CSS file header comment**

Replace line 4:
```css
// OLD:
 * Accordion layout, color grid, live preview, wp-color-picker overrides.
```
With:
```css
// NEW:
 * Accordion layout, color grid, live preview, Pickr color picker styles.
```

- [ ] **Step 2: Replace wp-color-picker CSS overrides with Pickr styles**

Remove lines 148-151 (the wp-color-picker overrides):
```css
/* DELETE: */
/* wp-color-picker overrides to fit in grid card */
.adc-color-card .wp-picker-container { display: block; }
.adc-color-card .wp-picker-input-wrap { display: flex; align-items: center; gap: 4px; }
.adc-color-card .wp-picker-input-wrap input[type="text"] { width: 90px !important; }
```

Add in their place:

```css
/* Pickr trigger swatch + input row */
.adc-pickr-row {
    display: flex;
    align-items: center;
    gap: 6px;
}
.adc-pickr-trigger {
    width: 28px;
    height: 28px;
    border-radius: 4px;
    border: 2px solid #ccc;
    cursor: pointer;
    flex-shrink: 0;
    background: repeating-conic-gradient(#eee 0% 25%, #fff 0% 50%) 50% / 12px 12px;
}
.adc-pickr-trigger .pcr-button {
    width: 100% !important;
    height: 100% !important;
    border-radius: 2px !important;
}
.adc-color-card .adc-color-picker-input {
    width: 90px;
    font-size: 12px;
    font-family: monospace;
    padding: 4px 6px;
    border: 1px solid #ccc;
    border-radius: 3px;
}

/* Eyedropper button */
.adc-eyedropper-btn {
    background: none;
    border: 1px solid #ccc;
    border-radius: 3px;
    cursor: pointer;
    padding: 3px 5px;
    font-size: 14px;
    line-height: 1;
    color: #555;
    flex-shrink: 0;
}
.adc-eyedropper-btn:hover {
    background: #f0f0f0;
    border-color: #999;
}

/* Color harmony toolbar */
.adc-harmony-toolbar {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    min-height: 0;
}
.adc-harmony-toolbar:empty {
    display: none;
}
.adc-harmony-btn {
    font-size: 10px;
    padding: 1px 5px;
    border: 1px solid #ddd;
    border-radius: 3px;
    background: #fafafa;
    cursor: pointer;
    color: #555;
}
.adc-harmony-btn:hover {
    background: #eee;
    border-color: #bbb;
}
.adc-harmony-chips {
    display: flex;
    gap: 3px;
    align-items: center;
}
.adc-harmony-chip {
    width: 18px;
    height: 18px;
    border-radius: 3px;
    border: 1px solid #ccc;
    cursor: pointer;
    display: inline-block;
}
.adc-harmony-chip:hover {
    border-color: #333;
    transform: scale(1.15);
}

/* Recent palette row */
.adc-recent-palette {
    display: flex;
    gap: 3px;
    flex-wrap: wrap;
    margin-top: 2px;
}
.adc-recent-palette:empty {
    display: none;
}
.adc-recent-swatch {
    width: 16px;
    height: 16px;
    border-radius: 2px;
    border: 1px solid #ccc;
    cursor: pointer;
    display: inline-block;
}
.adc-recent-swatch:hover {
    border-color: #333;
    transform: scale(1.2);
}

/* Pickr popup z-index (above WP admin) */
.pcr-app {
    z-index: 100100 !important;
}
```

- [ ] **Step 3: Verify styles look correct**

Reload the template builder. Color cards should show a checkerboard-pattern swatch next to a compact hex input. The grid layout should remain intact.

- [ ] **Step 4: Commit**

```bash
git add admin/css/adc-template-builder.css
git commit -m "style: replace wp-color-picker CSS with Pickr and feature styles"
```

---

### Task 5: Add Saved Palettes (Recent Colors)

Add a "recent colors" row that auto-populates as the user picks colors.

**Files:**
- Modify: `admin/js/adc-template-builder.js`

- [ ] **Step 1: Add recent palette management functions**

Insert after the `initPickrInstance` function (before the `// ---- DOM Ready ----` comment):

```js
    // ---- Recent Color Palette ----
    var RECENT_PALETTE_KEY = 'adc_template_palette';
    var MAX_RECENT_COLORS = 12;

    function adcGetRecentColors() {
        try {
            var stored = localStorage.getItem(RECENT_PALETTE_KEY);
            return stored ? JSON.parse(stored) : [];
        } catch(e) {
            return [];
        }
    }

    function adcAddRecentColor(hex) {
        if (!hex || !/^#[0-9a-fA-F]{3,8}$/.test(hex)) return;
        hex = hex.toUpperCase();
        var colors = adcGetRecentColors().filter(function(c) { return c !== hex; });
        colors.unshift(hex);
        if (colors.length > MAX_RECENT_COLORS) colors = colors.slice(0, MAX_RECENT_COLORS);
        try {
            localStorage.setItem(RECENT_PALETTE_KEY, JSON.stringify(colors));
        } catch(e) {}
        adcRenderRecentPalettes();
    }

    function adcRenderRecentPalettes() {
        var colors = adcGetRecentColors();
        document.querySelectorAll('.adc-recent-palette').forEach(function(container) {
            // Clear existing swatches
            while (container.firstChild) {
                container.removeChild(container.firstChild);
            }
            var inputId = container.dataset.for;
            colors.forEach(function(hex) {
                var swatch = document.createElement('span');
                swatch.className = 'adc-recent-swatch';
                swatch.style.backgroundColor = hex;
                swatch.title = hex;
                swatch.addEventListener('click', function() {
                    var inputEl = document.getElementById(inputId);
                    if (inputEl) {
                        inputEl.value = hex;
                        var key = inputEl.dataset.key;
                        if (pickrInstances[key]) {
                            pickrInstances[key].setColor(hex, true);
                        }
                        debouncedPreview();
                        adcUpdateContrastCheck();
                        formDirty = true;
                    }
                });
                container.appendChild(swatch);
            });
        });
    }
```

- [ ] **Step 2: Add recent palette container to each color card via JS init**

Add inside the Pickr initialization `if` block, after the `.forEach` loop that creates Pickr instances:

```js
            // Add recent palette containers
            document.querySelectorAll('.adc-color-card').forEach(function(card) {
                var input = card.querySelector('.adc-color-picker-input');
                if (!input) return;
                var palette = document.createElement('div');
                palette.className = 'adc-recent-palette';
                palette.dataset.for = input.id;
                card.appendChild(palette);
            });
            adcRenderRecentPalettes();
```

- [ ] **Step 3: Verify recent palette works**

1. Pick a color and save: a small swatch should appear below the color cards
2. Pick more colors: swatches accumulate (max 12)
3. Click a recent swatch: color applies to that field
4. Reload the page: recent colors persist from localStorage

- [ ] **Step 4: Commit**

```bash
git add admin/js/adc-template-builder.js
git commit -m "feat: add recent colors palette with localStorage persistence"
```

---

### Task 6: Add Color Harmony Suggestions

Add Complementary, Analogous, and Triadic harmony buttons below each color card.

**Files:**
- Modify: `admin/js/adc-template-builder.js`

- [ ] **Step 1: Add HSL conversion and harmony calculation functions**

Insert after the recent palette functions:

```js
    // ---- Color Harmony ----
    function adcHexToHsl(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(function(c){ return c+c; }).join('');
        var r = parseInt(hex.substr(0,2),16)/255;
        var g = parseInt(hex.substr(2,2),16)/255;
        var b = parseInt(hex.substr(4,2),16)/255;
        var max = Math.max(r,g,b), min = Math.min(r,g,b);
        var h, s, l = (max+min)/2;
        if (max === min) {
            h = s = 0;
        } else {
            var d = max - min;
            s = l > 0.5 ? d/(2-max-min) : d/(max+min);
            switch(max) {
                case r: h = ((g-b)/d + (g<b?6:0))/6; break;
                case g: h = ((b-r)/d + 2)/6; break;
                case b: h = ((r-g)/d + 4)/6; break;
            }
        }
        return { h: h*360, s: s*100, l: l*100 };
    }

    function adcHslToHex(h, s, l) {
        h = ((h % 360) + 360) % 360;
        s /= 100; l /= 100;
        var c = (1 - Math.abs(2*l-1)) * s;
        var x = c * (1 - Math.abs((h/60)%2 - 1));
        var m = l - c/2;
        var r, g, b;
        if (h<60)       { r=c; g=x; b=0; }
        else if (h<120) { r=x; g=c; b=0; }
        else if (h<180) { r=0; g=c; b=x; }
        else if (h<240) { r=0; g=x; b=c; }
        else if (h<300) { r=x; g=0; b=c; }
        else            { r=c; g=0; b=x; }
        var toHex = function(v) { var hx = Math.round((v+m)*255).toString(16); return hx.length===1 ? '0'+hx : hx; };
        return '#' + toHex(r) + toHex(g) + toHex(b);
    }

    function adcGetHarmonies(hex, type) {
        var hsl = adcHexToHsl(hex);
        switch(type) {
            case 'complementary': return [adcHslToHex(hsl.h+180, hsl.s, hsl.l)];
            case 'analogous': return [adcHslToHex(hsl.h-30, hsl.s, hsl.l), adcHslToHex(hsl.h+30, hsl.s, hsl.l)];
            case 'triadic': return [adcHslToHex(hsl.h+120, hsl.s, hsl.l), adcHslToHex(hsl.h+240, hsl.s, hsl.l)];
            default: return [];
        }
    }
```

- [ ] **Step 2: Add harmony toolbar rendering**

Insert after the harmony calculation functions:

```js
    function adcInitHarmonyToolbars() {
        document.querySelectorAll('.adc-harmony-toolbar').forEach(function(toolbar) {
            var inputId = toolbar.dataset.for;
            var inputEl = document.getElementById(inputId);
            if (!inputEl) return;

            ['complementary', 'analogous', 'triadic'].forEach(function(type) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'adc-harmony-btn';
                btn.textContent = type.charAt(0).toUpperCase() + type.slice(1);
                btn.addEventListener('click', function() {
                    var hex = inputEl.value;
                    if (!hex || !/^#[0-9a-fA-F]{3,8}$/.test(hex)) return;

                    // Remove existing chips from this toolbar
                    var oldChips = toolbar.querySelectorAll('.adc-harmony-chips');
                    for (var i = 0; i < oldChips.length; i++) {
                        toolbar.removeChild(oldChips[i]);
                    }

                    var harmonies = adcGetHarmonies(hex, type);
                    var chipsContainer = document.createElement('span');
                    chipsContainer.className = 'adc-harmony-chips';
                    harmonies.forEach(function(h) {
                        var chip = document.createElement('span');
                        chip.className = 'adc-harmony-chip';
                        chip.style.backgroundColor = h;
                        chip.title = h;
                        chip.addEventListener('click', function() {
                            inputEl.value = h;
                            var key = inputEl.dataset.key;
                            if (pickrInstances[key]) {
                                pickrInstances[key].setColor(h, true);
                            }
                            adcAddRecentColor(h);
                            debouncedPreview();
                            adcUpdateContrastCheck();
                            formDirty = true;
                        });
                        chipsContainer.appendChild(chip);
                    });
                    toolbar.appendChild(chipsContainer);
                });
                toolbar.appendChild(btn);
            });
        });
    }
```

- [ ] **Step 3: Call `adcInitHarmonyToolbars()` during Pickr init**

Inside the `if (typeof Pickr !== 'undefined')` block, after the recent palette init, add:

```js
            adcInitHarmonyToolbars();
```

- [ ] **Step 4: Verify harmony tools work**

1. Set a color on a field (e.g., `#e94560`)
2. Click "Complementary": one chip appears with the opposite hue
3. Click "Analogous": two chips appear (plus/minus 30 degrees)
4. Click "Triadic": two chips appear (plus/minus 120 degrees)
5. Click a chip: color applies to the field, preview updates

- [ ] **Step 5: Commit**

```bash
git add admin/js/adc-template-builder.js
git commit -m "feat: add color harmony suggestions (complementary, analogous, triadic)"
```

---

### Task 7: Add Eyedropper Button

Add an eyedropper icon button using the browser EyeDropper API (progressive enhancement).

**Files:**
- Modify: `admin/js/adc-template-builder.js`

- [ ] **Step 1: Add eyedropper init function**

Insert after the harmony toolbar functions:

```js
    // ---- Eyedropper (progressive enhancement) ----
    function adcInitEyedroppers() {
        if (!('EyeDropper' in window)) return; // Not supported: skip silently

        document.querySelectorAll('.adc-pickr-row').forEach(function(row) {
            var inputEl = row.querySelector('.adc-color-picker-input');
            if (!inputEl) return;

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'adc-eyedropper-btn';
            btn.title = 'Pick color from screen';
            btn.setAttribute('aria-label', 'Pick color from screen');
            btn.textContent = '\uD83D\uDC41'; // eye emoji as simple fallback
            btn.addEventListener('click', function() {
                var dropper = new EyeDropper();
                dropper.open().then(function(result) {
                    var hex = result.sRGBHex;
                    inputEl.value = hex;
                    var key = inputEl.dataset.key;
                    if (pickrInstances[key]) {
                        pickrInstances[key].setColor(hex, true);
                    }
                    adcAddRecentColor(hex);
                    debouncedPreview();
                    adcUpdateContrastCheck();
                    formDirty = true;
                }).catch(function() {
                    // User cancelled: do nothing
                });
            });
            row.appendChild(btn);
        });
    }
```

- [ ] **Step 2: Call `adcInitEyedroppers()` during Pickr init**

Inside the `if (typeof Pickr !== 'undefined')` block, add:

```js
            adcInitEyedroppers();
```

- [ ] **Step 3: Verify eyedropper works**

In Chrome/Edge: each color card should show an eye icon button. Clicking it opens the browser's color sampling tool. Pick a color from the screen and the value applies to the field.

In Firefox/Safari: the button should not appear at all (progressive enhancement).

- [ ] **Step 4: Commit**

```bash
git add admin/js/adc-template-builder.js
git commit -m "feat: add eyedropper button using browser EyeDropper API"
```

---

### Task 8: Final Cleanup and Verification

Remove any remaining wp-color-picker references and do end-to-end testing.

**Files:**
- Modify: `admin/js/adc-template-builder.js` (if any stale references remain)

- [ ] **Step 1: Search for any remaining wpColorPicker references**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator
grep -rn 'wpColorPicker\|wp-color-picker\|wp_color_picker' admin/
```

Remove any remaining references. The only acceptable mention is in CSS comments or git history.

- [ ] **Step 2: End-to-end testing**

Test the full workflow on the template builder:

1. **Create new template**: Go to template builder, Create New, pick "Start from Minimal", all colors should load into Pickr instances
2. **Edit colors**: Click swatch, Pickr popup opens, pick color, Save, preview updates, input shows hex
3. **Clear a color**: Open Pickr, click Clear, input empties, preview updates
4. **Type hex manually**: Type `#ff6600` in an input, press Enter/Tab, Pickr swatch updates
5. **Harmony tools**: Set a color, click Complementary/Analogous/Triadic, chips appear, click chip, color applies
6. **Eyedropper** (Chrome/Edge only): Click eye icon, pick from screen, color applies
7. **Recent palette**: Pick several colors, recent swatches appear below cards, click one, applies
8. **Save template**: Click "Save Template", page reloads, all colors preserved correctly
9. **WCAG contrast checker**: Set text + bg colors, contrast table updates correctly
10. **Import/Export**: Export template as JSON, reimport, all colors correct

- [ ] **Step 3: Commit final cleanup**

```bash
git add -A admin/
git commit -m "chore: remove remaining wp-color-picker references, final cleanup"
```
