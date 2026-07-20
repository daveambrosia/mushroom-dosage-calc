# Ambrosia Dosage Calculator

![CI](https://github.com/daveambrosia/mushroom-dosage-calc/actions/workflows/ci.yml/badge.svg)

A WordPress plugin for the Church of Ambrosia that calculates psilocybin sacrament
dosages from lab-tested potency data. It factors in body weight, tolerance, personal
sensitivity, and the specific compound profile of each strain or edible.

For educational and spiritual purposes only.

## Screenshots

**Mushroom calculator** with per-strain potency and five experience levels:

![Mushroom calculator](docs/images/calculator-mushrooms.png)

**Edible calculator** in product-specific units (gummies, capsules, and so on):

![Edible calculator](docs/images/calculator-edibles.png)

**Maker QR generator**, where a producer creates a QR code from their own product data:

![Maker QR generator](docs/images/maker-qr.png)

## What it does

- Mushroom dosing in grams from per-strain potency (psilocybin plus psilocin, in
  micrograms per gram).
- Edible dosing in product-specific units: gummies, capsules, packets, bars, cups,
  droppers, rounded to eighths of a unit.
- Five experience levels from microdose to breakthrough, adjusted for tolerance (days
  since last dose) and personal sensitivity.
- QR codes: short URLs for church products, plus a public maker page where producers
  generate their own codes that open the calculator pre-filled.
- Admin management of strains and edibles, CSV and Google Sheets import, and a member
  submission queue.
- Self-updates from GitHub Releases.

## How dosing works

A target dose in micrograms is derived from body weight and the selected experience
level, then adjusted for tolerance and personal sensitivity:

```
mcg_needed = mcg_per_lb x weight_lbs x tolerance_multiplier x sensitivity_multiplier
```

- Mushrooms: `grams = mcg_needed / (psilocybin + psilocin per gram)`.
- Edibles: `units = mcg_needed / (psilocybin + psilocin per piece)`, rounded to eighths.

Tolerance ramps from 100% at 28 or more days since the last dose up to 200% at one day.
Psilocybin and psilocin drive the calculation; norpsilocin, baeocystin, norbaeocystin,
and aeruginascin are tracked for display.

## Shortcodes

There are two shortcodes. Put each one on its own page.

### `[dosage_calculator]`

The calculator itself. With no attributes it uses the defaults from the settings page:

```
[dosage_calculator]
```

All attributes are optional:

| Attribute | Default | Description |
| --- | --- | --- |
| `template` | `default` | Visual template: `default`, `brutal`, `minimal`, `dark`, `nature`, `glass`, `neon`, `paper`, `terminal`, `retro`, `flat`. Custom templates from the Template Builder can also be used by slug. |
| `default_tab` | `mushrooms` | Which tab opens first: `mushrooms` or `edibles`. |
| `default_strain` | (none) | Pre-select a strain by its short code (for example `ZD-GOL-001`). |
| `default_edible` | (none) | Pre-select an edible by its short code. |
| `show_mushrooms` | `true` | Show the mushrooms tab. |
| `show_edibles` | `true` | Show the edibles tab. |
| `show_quick_converter` | `true` | Show the mcg-to-grams quick converter. |
| `show_compound_breakdown` | `true` | Show the per-compound breakdown on each dose card. |
| `show_safety_warning` | `true` | Show the safety information panel. |
| `allow_custom` | `true` | Let visitors enter a custom strain or edible not in the database. |
| `allow_submit` | `true` | Let visitors submit a custom product to the review queue. |

Examples:

```
[dosage_calculator default_tab="edibles" template="dark"]
[dosage_calculator default_strain="ZD-GOL-001" show_quick_converter="false"]
```

The URL can also open a specific tab: add `?t=e` for edibles or `?t=m` for mushrooms.

### `[dosage_calculator_qr_maker]`

A public page where a product maker enters their own lab-tested potency and gets a QR
code that opens the calculator pre-filled with that product. Codes are saved in the
maker's browser, and they can optionally submit the product to the church for review.
Takes no attributes:

```
[dosage_calculator_qr_maker]
```

## Installation

1. Download the latest `ambrosia-dosage-calculator-vX.Y.Z.zip` from the
   [Releases](https://github.com/daveambrosia/mushroom-dosage-calc/releases) page.
2. In WordPress, go to **Plugins, Add New, Upload Plugin**, choose the zip, and activate.
3. Create a page, add `[dosage_calculator]` to it, and publish.

Once installed, the plugin checks GitHub Releases for new versions and offers updates on
the WordPress Plugins screen like any other plugin.

## Managing the calculator

Everything is under **Dosage Calc** in the WordPress admin menu.

- **Dashboard**: totals, quick actions, and the shortcode reference.
- **Strains** and **Edibles**: add, edit, activate, and deactivate products. Each has a
  short code (auto-generated: `ZD-GOL-001` for strains, `ZD-E-GUM-005` for edibles) used by QR codes and the
  `default_strain` / `default_edible` attributes. Mushroom potency is entered in
  micrograms per gram; edible potency is entered per piece.
- **Taxonomies**: strain categories and edible product types. Each product type carries
  the **unit name** shown in results (gummies, capsules, packets, bars, cups, droppers).
- **Template Builder**: create custom visual templates, usable by slug in the `template`
  attribute.
- **Import / Export**: bulk-import strains and edibles from a CSV file or a Google Sheet
  (with optional scheduled auto-sync), and export the database as CSV or JSON. For CSV
  and Sheets, edible compound values are the package total and are divided by the piece
  count on import; mushroom values are micrograms per gram.
- **Generate QR Codes**: print QR codes for church products. These use short URLs of the
  form `https://your-site/c/SHORT_CODE` (the `/c/` prefix is configurable in Settings).
- **Submissions**: review, approve, or reject products submitted by members and makers.
  Approved submissions become real strains or edibles.

### Settings

Under **Dosage Calc, Settings** you can set the calculator title, subtitle, disclaimer,
and safety text; toggle whether visitors may submit products; set a notification email
for new submissions; choose the short-URL path; and configure the default template and
the Google Sheets sync.

## Development

See [README-DEV.md](README-DEV.md) for the build, test, and release workflow. Every change
is gated by continuous integration: PHPUnit, PHPCS, PHPStan, ESLint, and a check that the
committed built assets match a fresh build. The version history is in
[CHANGELOG.md](CHANGELOG.md).

## License

GPL v2 or later.
