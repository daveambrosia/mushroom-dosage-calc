# Ambrosia Dosage Calculator - Visual Themes

The calculator supports 5 distinct visual themes that can be applied via the shortcode `theme` attribute.

## Usage

```
[dosage_calculator theme="minimal"]
[dosage_calculator theme="dark"]
[dosage_calculator theme="nature"]
[dosage_calculator theme="glass"]
[dosage_calculator theme="bold"]
```

You can combine with other attributes:
```
[dosage_calculator theme="dark" default_tab="edibles"]
```

---

## Available Themes

### 1. Minimal (`theme="minimal"`)

**Style:** Clean, modern, lots of whitespace

**Characteristics:**
- Subtle 1px borders with rounded corners (12px)
- Soft shadows instead of hard box-shadows
- Muted pastel accent colors
- Inter font family (system fallback)
- No uppercase transforms - sentence case throughout
- Hover effects use subtle lift animations

**Best for:** Modern, professional websites, minimalist aesthetics

---

### 2. Dark (`theme="dark"`)

**Style:** Dark mode with neon accents

**Characteristics:**
- Deep purple/navy backgrounds (#1a1a2e)
- Gradient headers with purple/pink tones
- Neon accent colors with glow effects
- JetBrains Mono / Fira Code font for that tech feel
- Box shadows glow with accent colors on hover
- Result cards use vibrant gradients

**Best for:** Tech-focused sites, night-time browsing, psychedelic aesthetics

---

### 3. Nature (`theme="nature"`)

**Style:** Earthy, organic, mushroom-inspired

**Characteristics:**
- Warm cream/tan backgrounds (#f5f0e6)
- Earth-tone palette: sage green, terracotta, dusty blue
- Organic shapes with asymmetric border-radius
- Subtle cross-hatch pattern background
- Playfair Display + Lora serif fonts
- 2px borders with earthy brown color

**Best for:** Wellness sites, natural/organic branding, spiritual communities

---

### 4. Glass (`theme="glass"`)

**Style:** Glassmorphism with blur effects

**Characteristics:**
- Vibrant gradient background (purple → pink)
- Frosted glass panels with backdrop-filter blur
- Translucent white overlays
- Soft borders with white opacity
- Poppins font family
- Smooth hover animations

**Best for:** Modern, trendy designs, creative sites, premium feel

**Note:** Requires modern browser support for backdrop-filter. Falls back gracefully.

---

### 5. Bold (`theme="bold"`)

**Style:** Pop art, brutalist, high contrast

**Characteristics:**
- Pure white background with pure black borders
- 4px thick borders throughout
- Large 8px box-shadow offsets
- Neon accent colors (cyan, magenta, yellow)
- Archivo Black impact font
- Chunky buttons with press animations
- Maximum visual impact

**Best for:** Attention-grabbing pages, younger audiences, fun/playful brands

---

## Technical Details

### CSS Custom Properties

All themes override these CSS custom properties:

```css
--adc-surface          /* Main background */
--adc-surface-alt      /* Secondary background */
--adc-text             /* Text color */
--adc-border           /* Border color */
--adc-border-width     /* Border thickness */
--adc-shadow-offset    /* Box shadow offset */

/* Accent colors */
--adc-accent-red
--adc-accent-yellow
--adc-accent-green
--adc-accent-blue
--adc-accent-purple

/* Experience level colors */
--adc-color-microdose
--adc-color-perceivable
--adc-color-intense
--adc-color-profound
--adc-color-breakthrough

/* Typography */
--adc-font-heading
--adc-font-body
--adc-font-mono
```

### File Location

Theme styles are in:
`/public/css/calculator-themes.css`

### Class Structure

Themes add a wrapper class:
- `.adc-theme-minimal`
- `.adc-theme-dark`
- `.adc-theme-nature`
- `.adc-theme-glass`
- `.adc-theme-bold`

### Customization

To customize a theme, you can:

1. Use the admin Custom CSS setting
2. Add styles in your theme's CSS that target the theme class:

```css
/* Make dark theme even darker */
.adc-theme-dark .adc-calculator {
    background: #0a0a15;
}
```

---

## Browser Support

| Theme | Chrome | Firefox | Safari | Edge |
|-------|--------|---------|--------|------|
| Minimal | ✅ | ✅ | ✅ | ✅ |
| Dark | ✅ | ✅ | ✅ | ✅ |
| Nature | ✅ | ✅ | ✅ | ✅ |
| Glass | ✅ | ✅ | ✅* | ✅ |
| Bold | ✅ | ✅ | ✅ | ✅ |

*Glass theme requires Safari 9+ for backdrop-filter support

---

## Migration Notes

- Default (no theme): Uses the original "brutal" style
- Themes are additive - they layer on top of base styles
- All themes maintain full accessibility features
- Themes work with all shortcode options (show_edibles, etc.)
