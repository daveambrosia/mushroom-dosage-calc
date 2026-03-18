=== Ambrosia Dosage Calculator ===
Contributors: churchofambrosia
Tags: psilocybin, dosage, calculator, mushrooms, edibles, qr-code
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 2.17.20
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive psilocybin dosage calculator with strain & edible management, QR codes, and customizable templates.

== Description ==

The Ambrosia Dosage Calculator is a full-featured WordPress plugin for calculating psychedelic sacrament dosages based on:

* Body weight (lbs or kg)
* Tolerance (days since last dose)
* Personal sensitivity
* Specific compound profiles (psilocybin, psilocin, etc.)

**Features:**

* **Mushroom Calculator** - Dosage in grams based on lab-tested strain data
* **Edible Calculator** - Dosage in pieces (¼, ½, ¾, 1, etc.) for chocolates, gummies, capsules
* **Admin Dashboard** - Full CRUD for strains and edibles
* **QR Code System** - Generate short URL QR codes, legacy URL support
* **CSV Import** - Bulk import with smart header detection
* **Community Submissions** - Users can submit custom strains for review
* **5 Template Presets** - Brutal, Minimal, Dark, Clinical, Mystic
* **localStorage Preferences** - Remembers user settings

**Shortcode:**
`[dosage_calculator]`

**Attributes:**
* `template` - brutal, minimal, dark, clinical, mystic
* `default_tab` - mushrooms or edibles
* `default_strain` - Pre-select by short code
* `show_edibles` - true/false
* `allow_custom` - true/false

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ambrosia-dosage-calculator/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings > Dosage Calculator screen to configure
4. Add `[dosage_calculator]` to any page or post

== Changelog ==

= 2.0.0 =
* Complete rewrite with tabbed interface
* Added edibles support with piece-based dosing
* QR code generator in admin
* CSV import with fuzzy header matching
* Tolerance system based on days since last dose
* 5 preset templates
* Community submission system with review queue
* Short URL support for smaller QR codes
* Legacy URL support for external producers
* localStorage for user preferences
* REST API for all data

= 1.0.0 =
* Initial release
* Basic mushroom calculator

== Frequently Asked Questions ==

= How are doses calculated? =

Doses are calculated using: `mcg_needed = mcg_per_lb × weight_lbs × tolerance_multiplier × sensitivity_multiplier`

For mushrooms, grams = mcg_needed / (psilocybin + psilocin)
For edibles, pieces = mg_needed / mg_per_piece

= What compounds are tracked? =

* Psilocybin (used in calculation)
* Psilocin (used in calculation)
* Norpsilocin (display only)
* Baeocystin (display only)
* Norbaeocystin (display only)
* Aeruginascin (display only)

= How does tolerance work? =

Tolerance is based on days since last dose:
* 28+ days = 100% (baseline)
* 14 days = ~152%
* 7 days = ~178%
* 1 day = 200% (double dose needed)

= Can external producers use QR codes? =

Yes! They can generate legacy URLs with compound data embedded. When scanned, the data is auto-submitted to your review queue if not recognized.

== Screenshots ==

1. Calculator interface with mushroom tab
2. Calculator interface with edibles tab
3. Admin strain management
4. QR code generator
5. CSV import with header mapping
