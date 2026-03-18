# Ambrosia Dosage Calculator — Full Audit, Debug & Improvement Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all bugs, security vulnerabilities, logic errors, and code quality issues identified during a comprehensive audit of the entire plugin codebase (PHP, JS, CSS).

**Architecture:** The plugin follows a 3-layer pattern: data layer (includes/), REST API (includes/class-adc-rest-api.php), and frontend (shortcode + JS modules). Admin classes in admin/ handle CRUD UI. CSS themes use custom properties. JS is built from 10 modules concatenated into an IIFE. All changes must preserve this architecture and follow existing conventions (ADC_ class prefix, $wpdb->prepare(), esc_html() output escaping, etc.).

**Tech Stack:** PHP 8.0+ (8.3 on server), WordPress 6.0+, vanilla JS (IIFE modules), CSS custom properties, bash build scripts (terser + csso-cli).

**Before starting any task:** Create a backup:
```bash
zip -r /home/dave/backup/ambrosia-dosage-calculator-$(date +%Y%m%d-%H%M%S).zip /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator/
```

**After any JS module change:** Rebuild:
```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash public/js/build-js.sh && bash build-min.sh
```

**Live test URL:** https://localhost/wordpress (login: francis / password123)

---

## Phase 1: Critical Security & Data Integrity Fixes

These issues can cause data loss, security bypasses, or crashes. Fix first.

---

### Task 1: Fix `wp_die()` HTTP Status Code (Security Bypass)

**Files:**
- Modify: `ambrosia-dosage-calculator.php:222`

**Issue:** `wp_die('Unauthorized', 403)` passes 403 as the page title, not the HTTP status. The server returns HTTP 200 to unauthorized users.

- [ ] **Step 1: Fix the wp_die call**

```php
// Line 222 — change:
wp_die('Unauthorized', 403);
// To:
wp_die('Unauthorized', 'Unauthorized', array('response' => 403));
```

- [ ] **Step 2: Verify**

```bash
curl -sk -o /dev/null -w "%{http_code}" "https://localhost/wordpress/?adc_preview=1"
# Expected: 403 (not 200)
```

- [ ] **Step 3: Commit**

```bash
git add ambrosia-dosage-calculator.php
git commit -m "fix: wp_die sends HTTP 403 status instead of using it as title"
```

---

### Task 2: Fix `register_activation_hook` Placement (Activation Never Fires)

**Files:**
- Modify: `ambrosia-dosage-calculator.php:106-107, ~line 509`

**Issue:** `register_activation_hook()` and `register_deactivation_hook()` are called inside `init_hooks()`, which runs on `plugins_loaded`. WordPress requires these to be registered at file scope during plugin load — by the time `plugins_loaded` fires, the activation window has already passed.

- [ ] **Step 1: Move activation/deactivation hooks to file scope**

At the bottom of `ambrosia-dosage-calculator.php`, after the `add_action('plugins_loaded', 'adc_init')` line (~line 509), add:

```php
register_activation_hook(__FILE__, array('ADC_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('ADC_Activator', 'deactivate'));
```

- [ ] **Step 2: Remove the hooks from init_hooks()**

Delete lines 106-107 from `init_hooks()`:
```php
// Remove these two lines:
register_activation_hook(__FILE__, array('ADC_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('ADC_Activator', 'deactivate'));
```

- [ ] **Step 3: Test by deactivating and reactivating the plugin**

Go to https://localhost/wordpress/wp-admin/plugins.php, deactivate, then reactivate. Check for errors. Verify DB tables still exist:

```bash
wp --path=/var/www/html/wordpress db query "SHOW TABLES LIKE 'wp_adc_%';"
```

- [ ] **Step 4: Commit**

```bash
git add ambrosia-dosage-calculator.php
git commit -m "fix: move register_activation_hook to file scope so activation actually fires"
```

---

### Task 3: Fix `ADC_Activator::deactivate()` Fatal Error (Missing Class Check)

**Files:**
- Modify: `includes/class-adc-activator.php:55`

**Issue:** `ADC_Sheets_Importer::deactivate()` is called without checking if the class exists. If the Google Sheets classes aren't loaded, deactivation crashes with a fatal error.

- [ ] **Step 1: Add class_exists guard**

```php
// Line 55 — change:
ADC_Sheets_Importer::deactivate();
// To:
if (class_exists('ADC_Sheets_Importer')) {
    ADC_Sheets_Importer::deactivate();
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/class-adc-activator.php
git commit -m "fix: guard ADC_Sheets_Importer::deactivate with class_exists check"
```

---

### Task 4: Fix `total_mg` Calculation Bug in Edibles (Data Integrity)

**Files:**
- Modify: `includes/class-adc-edibles.php:309, 362`

**Issue:** `total_mg` is calculated as only `psilocybin + psilocin`, ignoring all other compounds. Edibles with only norpsilocin, baeocystin, or aeruginascin can never be saved because `total_mg` computes to 0 and fails validation.

- [ ] **Step 1: Fix the sanitizer to include all active compounds**

```php
// Line 309 — change:
'total_mg' => absint($data['psilocybin']) + absint($data['psilocin']),
// To:
'total_mg' => absint($data['psilocybin']) + absint($data['psilocin']) + absint($data['norpsilocin']) + absint($data['baeocystin']) + absint($data['norbaeocystin']) + absint($data['aeruginascin']),
```

- [ ] **Step 2: Fix the validator error message**

```php
// Line 362:
if ($data['total_mg'] <= 0) {
    return new WP_Error('validation_error', 'At least one compound value must be greater than 0');
}
```

- [ ] **Step 3: Test by creating an edible with only baeocystin via admin UI**

- [ ] **Step 4: Commit**

```bash
git add includes/class-adc-edibles.php
git commit -m "fix: total_mg includes all 6 compounds, not just psilocybin+psilocin"
```

---

### Task 5: Fix XSS via dialog message rendering (Both Public + Admin)

**Files:**
- Modify: `public/js/adc-dialogs.js:66`
- Modify: `admin/js/adc-dialogs.js:66`

**Issue:** Dialog messages are set using a method that interprets HTML markup. Server error messages flow through `adcError()` into the dialog, allowing injection.

- [ ] **Step 1: Change to textContent in both dialog files**

In both `public/js/adc-dialogs.js` and `admin/js/adc-dialogs.js`, find the line that sets the message element's content and change it to use `textContent` instead, so markup is displayed as plain text rather than interpreted.

- [ ] **Step 2: Search for callers that intentionally send HTML**

```bash
grep -rn 'adcAlert\|adcError\|adcConfirm' /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator/public/js/ /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator/admin/js/ | grep '<'
```

If any callers pass HTML intentionally, create a sanitizer function instead.

- [ ] **Step 3: Rebuild JS**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash public/js/build-js.sh && bash build-min.sh
```

- [ ] **Step 4: Commit**

```bash
git add public/js/adc-dialogs.js admin/js/adc-dialogs.js public/js/calculator.js public/js/calculator.min.js
git commit -m "security: use textContent for dialog messages to prevent markup injection"
```

---

### Task 6: Fix XSS in Google Sheets Preview Table

**Files:**
- Modify: `admin/js/google-sheets-admin.js:49,54,63,70-72`

**Issue:** Column headers and cell values from Google Sheets are inserted into the DOM without escaping. A sheet cell containing script tags would execute in the admin browser.

- [ ] **Step 1: Add an escapeHtml helper at the top of the file**

```js
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
```

- [ ] **Step 2: Wrap all dynamic values**

Every instance of `+ h +`, `+ dbCol +`, `+ sheetCol +`, `+ (row[h] || '') +`, and `+ data.total_count +` must be wrapped with `escapeHtml()`.

- [ ] **Step 3: Commit**

```bash
git add admin/js/google-sheets-admin.js
git commit -m "security: escape all Google Sheets data before DOM insertion"
```

---

### Task 7: Fix XSS in Admin Submissions Page (PHP)

**Files:**
- Modify: `admin/class-adc-admin-submissions.php:258,332`

**Issue:** User-submitted JSON data (`piecesPerPackage`) is echoed without escaping in the submissions review page.

- [ ] **Step 1: Escape the piecesPerPackage output**

```php
// Line 258 — change:
echo $data['piecesPerPackage'] ?? $data['pieces_per_package'] ?? 1;
// To:
echo intval($data['piecesPerPackage'] ?? $data['pieces_per_package'] ?? 1);
```

Repeat for line 332.

- [ ] **Step 2: Audit all other unescaped $data echo instances in the file**

```bash
grep -n 'echo.*\$data\[' admin/class-adc-admin-submissions.php
```

Wrap every instance with `esc_html()` or `intval()` as appropriate.

- [ ] **Step 3: Commit**

```bash
git add admin/class-adc-admin-submissions.php
git commit -m "security: escape user-submitted data in admin submissions page"
```

---

### Task 8: Fix XSS in Admin JS Details Modal

**Files:**
- Modify: `admin/js/admin.js:455,479-483`

**Issue:** Submission details (id, type, status, piecesPerPackage) are inserted into the DOM without escaping.

- [ ] **Step 1: Use the existing escapeHtml function**

The file already has an `escapeHtml` function. Wrap all dynamic values in the `showSubmissionDetails` function: `sub.id`, `sub.type`, `sub.status`, `sub.created_at`, `data.piecesPerPackage`, `data.brand`, etc.

- [ ] **Step 2: Commit**

```bash
git add admin/js/admin.js
git commit -m "security: escape all submission detail values before DOM insertion"
```

---

### Task 9: Fix Broken `adcConfirmSync` Function

**Files:**
- Modify: `public/js/adc-dialogs.js:199-210`
- Modify: `admin/js/adc-dialogs.js:199-210`

**Issue:** `adcConfirmSync` references the implicit global `event` inside strict mode, which throws a `ReferenceError`. The function is completely broken.

- [ ] **Step 1: Fix the function signature to accept event as a parameter**

```js
// Change:
window.adcConfirmSync = function(message, options) {
    event.preventDefault();
    var href = event.currentTarget.href;
// To:
window.adcConfirmSync = function(event, message, options) {
    event.preventDefault();
    var href = event.currentTarget.href;
```

- [ ] **Step 2: Search for call sites and update them**

```bash
grep -rn 'adcConfirmSync' /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator/
```

Update all call sites to pass the event as the first argument.

- [ ] **Step 3: Rebuild and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash public/js/build-js.sh && bash build-min.sh
git add public/js/adc-dialogs.js admin/js/adc-dialogs.js
git commit -m "fix: adcConfirmSync accepts event parameter instead of relying on implicit global"
```

---

### Task 10: Fix Dialog Race Condition (Orphaned Promises)

**Files:**
- Modify: `public/js/adc-dialogs.js` (resolveCallback handling)
- Modify: `admin/js/adc-dialogs.js` (same)

**Issue:** `resolveCallback` is a single module-level variable. If a second dialog opens before the first resolves, the first promise is permanently orphaned.

- [ ] **Step 1: Resolve pending promise before opening a new dialog**

In both files, in the `showDialog` function, before setting the new `resolveCallback`, resolve any pending one:

```js
if (resolveCallback) {
    resolveCallback(false); // Resolve pending dialog as "cancelled"
}
resolveCallback = resolve;
```

- [ ] **Step 2: Rebuild and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash public/js/build-js.sh && bash build-min.sh
git add public/js/adc-dialogs.js admin/js/adc-dialogs.js
git commit -m "fix: resolve pending dialog promise before opening new dialog"
```

---

### Task 11: Fix Version Mismatch (readme.txt vs ADC_VERSION)

**Files:**
- Modify: `readme.txt:7`

**Issue:** `readme.txt` says `Stable tag: 2.0.0` but `ADC_VERSION` is `2.17.20`.

- [ ] **Step 1: Update the stable tag**

```
Stable tag: 2.17.20
```

- [ ] **Step 2: Commit**

```bash
git add readme.txt
git commit -m "fix: sync readme.txt stable tag with ADC_VERSION 2.17.20"
```

---

## Phase 2: Important Security & Logic Fixes

---

### Task 12: Fix ORDER BY SQL Injection Risk in Edibles

**Files:**
- Modify: `includes/class-adc-edibles.php:98`
- Modify: `includes/class-adc-strains.php` (if same pattern)

**Issue:** `esc_sql()` is not sufficient to sanitize ORDER BY column names. It only escapes quotes, not SQL keywords.

- [ ] **Step 1: Replace esc_sql with an allowlist**

```php
$allowed_orderby = array('name', 'id', 'psilocybin', 'psilocin', 'sort_order', 'created_at', 'short_code', 'product_type');
$allowed_order = array('ASC', 'DESC');
$orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'sort_order';
$order = in_array(strtoupper($args['order']), $allowed_order, true) ? strtoupper($args['order']) : 'ASC';
$order_sql = sprintf('%s %s', $orderby, $order);
```

- [ ] **Step 2: Apply the same fix to class-adc-strains.php if applicable**

- [ ] **Step 3: Commit**

```bash
git add includes/class-adc-edibles.php includes/class-adc-strains.php
git commit -m "security: use allowlist for ORDER BY columns instead of esc_sql"
```

---

### Task 13: Fix CSS Injection via Template Slug

**Files:**
- Modify: `includes/class-adc-template-css.php:76-77,94`

**Issue:** `esc_attr()` is the wrong escaping context for CSS selectors. CSS property values are only stripped of HTML tags, not validated as CSS.

- [ ] **Step 1: Validate slug against strict pattern**

```php
$slug = sanitize_key($template['slug']);
if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    continue;
}
```

- [ ] **Step 2: Sanitize CSS variable values by stripping braces**

```php
$sanitized_value = preg_replace('/[{}]/', '', wp_strip_all_tags($value));
$css .= '    --adc-' . $safe_key . ': ' . $sanitized_value . ';' . "\n";
```

- [ ] **Step 3: Commit**

```bash
git add includes/class-adc-template-css.php
git commit -m "security: validate template slug and sanitize CSS values to prevent injection"
```

---

### Task 14: Fix Missing wp_unslash() on $_POST/$_GET Reads

**Files:**
- Modify: `admin/class-adc-admin-strains.php:110-112`
- Modify: `admin/class-adc-admin-edibles.php:112-115`
- Modify: `includes/class-adc-qr-handler.php:146,157`

**Issue:** WordPress adds backslashes to all superglobals. Without `wp_unslash()`, data containing quotes gets corrupted (e.g., "O'Reilly" becomes "O\'Reilly").

- [ ] **Step 1: Wrap all $_POST reads with wp_unslash()**

In `class-adc-admin-strains.php`:
```php
'short_code'   => sanitize_text_field(wp_unslash($_POST['short_code'])),
'batch_number' => sanitize_text_field(wp_unslash($_POST['batch_number'])),
'category'     => sanitize_key(wp_unslash($_POST['category'])),
```

Apply same pattern in `class-adc-admin-edibles.php`.

- [ ] **Step 2: Fix $_GET reads in QR handler**

```php
$parsed = self::parse_legacy_url(wp_unslash($_GET));
```

- [ ] **Step 3: Commit**

```bash
git add admin/class-adc-admin-strains.php admin/class-adc-admin-edibles.php includes/class-adc-qr-handler.php
git commit -m "fix: add wp_unslash to all POST/GET reads to prevent backslash corruption"
```

---

### Task 15: Fix wpdb->prepare() Extra Argument Warning

**Files:**
- Modify: `includes/class-adc-strains.php:342-346`
- Modify: `includes/class-adc-edibles.php:355`

**Issue:** When `$exclude_id` is null (during create), `$wpdb->prepare()` receives an extra null argument. WordPress 5.9+ logs a `_doing_it_wrong()` notice.

- [ ] **Step 1: Conditionally pass the argument**

```php
$sql = "SELECT id FROM $table WHERE short_code = %s";
$args = array($data['short_code']);
if ($exclude_id) {
    $sql .= " AND id != %d";
    $args[] = $exclude_id;
}
$existing = $wpdb->get_var($wpdb->prepare($sql, ...$args));
```

Apply in both files.

- [ ] **Step 2: Commit**

```bash
git add includes/class-adc-strains.php includes/class-adc-edibles.php
git commit -m "fix: only pass exclude_id to wpdb->prepare when present"
```

---

### Task 16: Fix Google Sheets Importer Compound Division Bug

**Files:**
- Modify: `admin/class-adc-sheets-importer.php:333-343`

**Issue:** The Sheets importer divides compound values by `pieces_per_package`, but the DB schema stores values per-package (matching the CSV importer and admin forms). This causes systematically incorrect values.

- [ ] **Step 1: Remove the division logic (lines 333-343)**

Delete the entire `if ($type === 'edible')` block that divides compound values.

- [ ] **Step 2: Verify existing data consistency**

```bash
wp --path=/var/www/html/wordpress db query "SELECT name, psilocybin, pieces_per_package FROM wp_adc_edibles WHERE pieces_per_package > 1 LIMIT 10;"
```

- [ ] **Step 3: Commit**

```bash
git add admin/class-adc-sheets-importer.php
git commit -m "fix: remove incorrect per-piece division in Google Sheets importer"
```

---

### Task 17: Fix submitEdible Psilocin Calculation Bug

**Files:**
- Modify: `public/js/modules/adc-modals.js:653`

**Issue:** `submitEdible()` passes raw package-total psilocin instead of dividing by pieces like `saveEdible()` does. Submissions have inflated compound values.

- [ ] **Step 1: Apply per-piece division to all compounds in submitEdible**

```js
const pieces = parseInt(modal.querySelector('#adc-modal-edible-pieces').value) || 1;
// For each compound:
psilocin: Math.round((parseInt(modal.querySelector('#adc-modal-edible-psilocin').value) || 0) / pieces),
```

Apply to all 6 compounds.

- [ ] **Step 2: Rebuild JS and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash public/js/build-js.sh && bash build-min.sh
git add public/js/modules/adc-modals.js public/js/calculator.js public/js/calculator.min.js
git commit -m "fix: submitEdible divides compounds by pieces_per_package like saveEdible"
```

---

### Task 18: Fix Missing HTTP Cache Headers on REST API

**Files:**
- Modify: `includes/class-adc-http-cache.php`
- Modify: `ambrosia-dosage-calculator.php:112`

**Issue:** Live testing confirmed no `Cache-Control` or `ETag` headers on REST responses. `ADC_HTTP_Cache::init` is hooked to `template_redirect` which does not fire for REST API requests.

- [ ] **Step 1: Investigate the current hook registration**

```bash
grep -n 'template_redirect\|rest_api_init\|rest_pre_serve_request\|rest_post_dispatch' includes/class-adc-http-cache.php
```

- [ ] **Step 2: Wire cache headers into REST response pipeline**

The correct hook is `rest_post_dispatch` or adding headers directly in REST callbacks via `$response->header()`.

- [ ] **Step 3: Fix ETag multi-value comparison**

`If-None-Match` can contain comma-separated ETags. Split and check each:

```php
$client_etags = array_map(function($e) {
    return trim(trim($e), ' "');
}, explode(',', $if_none_match));
if (in_array($server_etag, $client_etags, true) || in_array('*', $client_etags, true)) {
    return new WP_REST_Response(null, 304);
}
```

- [ ] **Step 4: Verify headers appear**

```bash
curl -skI https://localhost/wordpress/wp-json/adc/v1/strains | grep -i 'cache-control\|etag'
```

- [ ] **Step 5: Commit**

```bash
git add includes/class-adc-http-cache.php ambrosia-dosage-calculator.php
git commit -m "fix: wire HTTP cache headers into REST API pipeline, fix ETag comparison"
```

---

### Task 19: Fix check_db_update Infinite Retry on Failure

**Files:**
- Modify: `ambrosia-dosage-calculator.php:140-145`

**Issue:** If `ADC_Activator::activate()` fails, it runs on every page load indefinitely (including `flush_rewrite_rules()`).

- [ ] **Step 1: Add failure guard with transient and admin notice**

```php
public function check_db_update() {
    $current_db_version = get_option('adc_db_version', '1.0.0');
    if (version_compare($current_db_version, ADC_DB_VERSION, '<')) {
        $last_attempt = get_transient('adc_db_update_attempt');
        if ($last_attempt) {
            return;
        }
        set_transient('adc_db_update_attempt', time(), HOUR_IN_SECONDS);
        ADC_Activator::activate();

        $updated_version = get_option('adc_db_version', '1.0.0');
        if (version_compare($updated_version, ADC_DB_VERSION, '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Ambrosia Dosage Calculator:</strong> Database update failed. Please deactivate and reactivate the plugin.</p></div>';
            });
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add ambrosia-dosage-calculator.php
git commit -m "fix: add failure guard and admin notice to check_db_update"
```

---

## Phase 3: Important Code Quality & UX Fixes

---

### Task 20: Fix date() Timezone Issues in Admin

**Files:**
- Modify: `admin/class-adc-admin-submissions.php:152,272,351`

**Issue:** PHP `date()` uses server timezone instead of the WordPress-configured timezone.

- [ ] **Step 1: Replace all date() calls with wp_date()**

```php
echo esc_html(wp_date('M j, Y', strtotime($entry['created_at'])));
```

Apply to all 3 lines.

- [ ] **Step 2: Commit**

```bash
git add admin/class-adc-admin-submissions.php
git commit -m "fix: use wp_date instead of date for correct timezone in admin UI"
```

---

### Task 21: Fix Missing esc_url() and esc_attr() in Admin HTML Output

**Files:**
- Modify: `admin/class-adc-admin.php:198,203,217-222`
- Modify: `admin/class-adc-admin-strains.php:82`
- Modify: `admin/class-adc-admin-edibles.php:74,84`
- Modify: `admin/class-adc-admin-submissions.php:307,356`

**Issue:** `admin_url()` not wrapped in `esc_url()`. Integer values and status class names not escaped at output.

- [ ] **Step 1: Add esc_url() to all admin_url() outputs**
- [ ] **Step 2: Add intval() to integer outputs and esc_attr() to class names**
- [ ] **Step 3: Commit**

```bash
git add admin/class-adc-admin.php admin/class-adc-admin-strains.php admin/class-adc-admin-edibles.php admin/class-adc-admin-submissions.php
git commit -m "security: add esc_url, esc_attr, intval to all admin HTML output"
```

---

### Task 22: Fix Parallel API Loading (Performance)

**Files:**
- Modify: `public/js/modules/adc-init.js:35-36`

**Issue:** `fetchStrains()` and `fetchEdibles()` are sequential `await` calls. They're independent and can run in parallel, cutting load time in half.

- [ ] **Step 1: Run fetches in parallel**

```js
// Change:
await fetchStrains();
await fetchEdibles();
// To:
await Promise.all([fetchStrains(), fetchEdibles()]);
```

- [ ] **Step 2: Rebuild JS and verify both tabs populate**
- [ ] **Step 3: Commit**

```bash
git add public/js/modules/adc-init.js public/js/calculator.js public/js/calculator.min.js
git commit -m "perf: fetch strains and edibles in parallel with Promise.all"
```

---

### Task 23: Fix localStorage GDPR Consent Issue

**Files:**
- Modify: `public/js/modules/adc-storage.js:34-38,88-109`

**Issue:** `loadPreferences()` writes to localStorage before consent is established. `loadLevelCollapseState()` reads localStorage without any consent check.

- [ ] **Step 1: Defer version key write until after consent check**

Move the `dontkeep` check and `state.storageConsent` assignment to run *before* the version key read/write.

- [ ] **Step 2: Add consent check to loadLevelCollapseState**

```js
function loadLevelCollapseState() {
    if (!state.storageConsent) return {};
    // ... rest of function
}
```

- [ ] **Step 3: Rebuild and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash public/js/build-js.sh && bash build-min.sh
git add public/js/modules/adc-storage.js public/js/calculator.js public/js/calculator.min.js
git commit -m "fix: respect storage consent before any localStorage read/write"
```

---

### Task 24: Fix Google Sheets Rate Limit Mismatch

**Files:**
- Modify: `admin/class-adc-google-sheets.php:207-221`

**Issue:** Docblock says "once per 5 minutes" but actual cooldown is 10 seconds. Allows hammering the Google Sheets API.

- [ ] **Step 1: Change cooldown to 300 seconds (5 minutes) to match documentation**

```php
$cooldown = 300;
```

- [ ] **Step 2: Commit**

```bash
git add admin/class-adc-google-sheets.php
git commit -m "fix: set rate limit cooldown to 5 minutes as documented"
```

---

### Task 25: Fix Redundant strpos Check in Admin Asset Loading

**Files:**
- Modify: `ambrosia-dosage-calculator.php:467`

**Issue:** Inner strpos check is always true because the function already returned early if false.

- [ ] **Step 1: Remove the redundant if wrapper, keep only the wp_enqueue_script call**
- [ ] **Step 2: Commit**

```bash
git add ambrosia-dosage-calculator.php
git commit -m "cleanup: remove redundant strpos check in admin asset loading"
```

---

## Phase 4: CSS Fixes

---

### Task 26: Remove Global :root Variables (Scope Pollution)

**Files:**
- Modify: `public/css/calculator.css:34-74`

**Issue:** `:root` block declares `--adc-*` globally, potentially conflicting with other plugins/themes. Same variables already declared on `#adc-calculator`.

- [ ] **Step 1: Delete the entire :root block (lines 34-74)**
- [ ] **Step 2: Rebuild CSS and verify calculator renders correctly**
- [ ] **Step 3: Commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash build-min.sh
git add public/css/calculator.css public/css/calculator.min.css
git commit -m "fix: remove :root CSS variables that polluted global scope"
```

---

### Task 27: Fix Focus Ring Accessibility (WCAG 2.4.7)

**Files:**
- Modify: `public/css/calculator-themes.css:34,362`
- Modify: `public/css/calculator.css` (input focus styles)

**Issue:** Brutal and retro themes set `--adc-focus-ring: none`, eliminating all visible focus indicators. Base styles set `outline: none` on inputs, relying entirely on the variable. Violates WCAG 2.4.7 (Level AA).

- [ ] **Step 1: Replace none with visible focus rings**

```css
/* Brutal — change --adc-focus-ring: none to: */
--adc-focus-ring: 0 0 0 3px rgba(0, 0, 0, 0.4);

/* Retro — same: */
--adc-focus-ring: 0 0 0 3px rgba(0, 0, 0, 0.3);
```

- [ ] **Step 2: Add focus-visible fallback to base styles**

```css
#adc-calculator .adc-weight-input:focus-visible,
#adc-calculator .adc-select:focus-visible,
#adc-calculator .adc-sensitivity-input:focus-visible {
    outline: 2px solid var(--adc-accent-blue, #2271b1);
    outline-offset: 2px;
}
```

- [ ] **Step 3: Rebuild CSS and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash build-min.sh
git add public/css/calculator.css public/css/calculator-themes.css public/css/calculator.min.css public/css/calculator-themes.min.css
git commit -m "a11y: ensure visible focus indicators on all themes (WCAG 2.4.7)"
```

---

### Task 28: Fix Extra Closing Brace and @charset Position

**Files:**
- Modify: `public/css/calculator.css:28,2813`

**Issue:** Orphaned `}` at line 2813 may cause parse errors. `@charset` at line 28 is silently ignored (must be first token in file).

- [ ] **Step 1: Audit brace balance**

```bash
grep -c '{' /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator/public/css/calculator.css
grep -c '}' /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator/public/css/calculator.css
```

- [ ] **Step 2: Remove the orphaned brace if counts don't match**
- [ ] **Step 3: Remove the @charset declaration (server sends UTF-8 header)**
- [ ] **Step 4: Rebuild and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash build-min.sh
git add public/css/calculator.css public/css/calculator.min.css
git commit -m "fix: remove orphaned closing brace and unused @charset declaration"
```

---

### Task 29: Fix Hard-Coded Colors Bypassing Theme System

**Files:**
- Modify: `public/css/calculator.css:897-919`

**Issue:** `.adc-results-summary` and `.adc-tolerance-active` hard-code `#dc2626` (red) instead of using theme variables. Breaks dark/glass/themed experiences.

- [ ] **Step 1: Replace hard-coded color with variable**

```css
background: var(--adc-color-profound, #dc2626);
```

- [ ] **Step 2: Rebuild and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash build-min.sh
git add public/css/calculator.css public/css/calculator.min.css
git commit -m "fix: use theme variable for tolerance-active background"
```

---

### Task 30: Fix Conflicting Status Class Definitions in Admin CSS

**Files:**
- Modify: `admin/css/admin.css:298-312,829-833,1166-1169`

**Issue:** Status classes defined 3 times with conflicting values. Last definition wins globally.

- [ ] **Step 1: Remove duplicate definitions at lines 829-833**
- [ ] **Step 2: Scope badge-style definitions to submissions context**

```css
#adc-submissions-form .adc-status-pending { ... }
```

- [ ] **Step 3: Commit**

```bash
git add admin/css/admin.css
git commit -m "fix: consolidate conflicting status class definitions"
```

---

### Task 31: Fix Mobile Touch Target Sizes and Remove Empty Section

**Files:**
- Modify: `public/css/calculator.css:2287-2328`

**Issue:** Section 21 "MOBILE UX PASS" is all comment stubs with zero rules. Collapse button touch targets (36px) are below 44px iOS HIG minimum.

- [ ] **Step 1: Remove empty comment stubs or implement the rules**

```css
@media (max-width: 768px) {
    #adc-calculator .adc-collapse-btn {
        min-width: 44px;
        min-height: 44px;
    }
}
```

- [ ] **Step 2: Rebuild and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash build-min.sh
git add public/css/calculator.css public/css/calculator.min.css
git commit -m "fix: ensure 44px minimum touch targets for collapse buttons on mobile"
```

---

## Phase 5: Additional JS Improvements

---

### Task 32: Fix Null Checks for Quick Converter Elements

**Files:**
- Modify: `public/js/modules/adc-events.js:216-234`

**Issue:** `handleConverterMcg` and `handleConverterGrams` throw TypeError when the quick converter is disabled because `elements.mcgInput` / `elements.gramsInput` are null.

- [ ] **Step 1: Add null guards at top of both functions**

```js
function handleConverterMcg() {
    if (!elements.mcgInput || !elements.gramsInput) return;
    // ... rest
}
```

- [ ] **Step 2: Rebuild and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash public/js/build-js.sh && bash build-min.sh
git add public/js/modules/adc-events.js public/js/calculator.js public/js/calculator.min.js
git commit -m "fix: guard converter handlers against null elements"
```

---

### Task 33: Move Resize Listener Inside init()

**Files:**
- Modify: `public/js/modules/adc-render.js:303-311`
- Modify: `public/js/modules/adc-init.js` (call site)

**Issue:** `window.addEventListener('resize', ...)` runs at module evaluation time, even on pages without the calculator. Duplicates if multiple instances exist.

- [ ] **Step 1: Wrap in an exported function and call from init()**
- [ ] **Step 2: Rebuild and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash public/js/build-js.sh && bash build-min.sh
git add public/js/modules/adc-render.js public/js/modules/adc-init.js public/js/calculator.js public/js/calculator.min.js
git commit -m "fix: defer resize listener until calculator is initialized"
```

---

### Task 34: Fix Template Builder Using Native confirm()

**Files:**
- Modify: `admin/js/adc-template-builder.js:39`

**Issue:** Uses browser native `confirm()` while the rest of the admin uses `adcConfirm()`.

- [ ] **Step 1: Replace with async adcConfirm()**

Make the containing function async and use `await adcConfirm(...)`.

- [ ] **Step 2: Commit**

```bash
git add admin/js/adc-template-builder.js
git commit -m "fix: use adcConfirm instead of native confirm in template builder"
```

---

### Task 35: Add Shared Data Schema Validation

**Files:**
- Modify: `public/js/modules/adc-modals.js:432-435`

**Issue:** `applySharedData` stores URL-decoded strain data without schema validation. Crafted URLs can inject arbitrary keys into localStorage.

- [ ] **Step 1: Add validation function**

```js
function validateStrainData(data) {
    if (!data || typeof data !== 'object') return null;
    var allowed = ['name', 'psilocybin', 'psilocin', 'norpsilocin', 'baeocystin', 'norbaeocystin', 'aeruginascin'];
    var clean = {};
    allowed.forEach(function(key) {
        if (key === 'name') {
            clean[key] = typeof data[key] === 'string' ? data[key].substring(0, 200) : 'Shared Strain';
        } else {
            clean[key] = typeof data[key] === 'number' ? Math.max(0, Math.min(data[key], 100000)) : 0;
        }
    });
    return clean;
}
```

Use before storing: `state.customStrains[sharedId] = validateStrainData(data.strainData);`

- [ ] **Step 2: Rebuild and commit**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ambrosia-dosage-calculator && bash public/js/build-js.sh && bash build-min.sh
git add public/js/modules/adc-modals.js public/js/calculator.js public/js/calculator.min.js
git commit -m "security: validate shared strain data schema before localStorage storage"
```

---

## Phase 6: REST API & Export Fixes

---

### Task 36: Fix Export Endpoints Using exit Instead of WP_REST_Response

**Files:**
- Modify: `includes/class-adc-rest-api.php:919-927,940-948`

**Issue:** Export endpoints call `header()` directly and then `exit`, bypassing WordPress REST response pipeline. Can cause "headers already sent" warnings.

- [ ] **Step 1: Return proper WP_REST_Response for CSV exports**

Build the CSV string in memory using `php://temp`, return it as a `WP_REST_Response` with appropriate headers set via `$response->header()`.

- [ ] **Step 2: Commit**

```bash
git add includes/class-adc-rest-api.php
git commit -m "fix: return WP_REST_Response from export endpoints instead of calling exit"
```

---

### Task 37: Add Submission Data Size Limit

**Files:**
- Modify: `includes/class-adc-rest-api.php:569-597`

**Issue:** The `/submit` endpoint accepts arbitrarily large JSON with no size validation.

- [ ] **Step 1: Add size validation**

```php
$json_data = wp_json_encode($body['data']);
if (strlen($json_data) > 65536) {
    return new WP_Error('payload_too_large', 'Submission data exceeds maximum size', array('status' => 413));
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/class-adc-rest-api.php
git commit -m "security: limit submission data payload to 64KB"
```

---

### Task 38: Add Template Export Cache Headers

**Files:**
- Modify: `admin/class-adc-template-builder.php:741-744`

**Issue:** Template export missing cache-control headers (unlike tools export which has them).

- [ ] **Step 1: Add cache headers before JSON output**

```php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
```

- [ ] **Step 2: Commit**

```bash
git add admin/class-adc-template-builder.php
git commit -m "fix: add cache-control headers to template export"
```

---

## Phase 7: Settings & Capability Hardening

---

### Task 39: Add Explicit Capability Checks to Template Builder Methods

**Files:**
- Modify: `admin/class-adc-template-builder.php:431,719`

**Issue:** `handle_save()` and `handle_export()` verify nonces but don't explicitly check `manage_options`.

- [ ] **Step 1: Add capability check at top of each method**

```php
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized', 'Unauthorized', array('response' => 403));
}
```

- [ ] **Step 2: Commit**

```bash
git add admin/class-adc-template-builder.php
git commit -m "security: add explicit manage_options check to template save and export"
```

---

### Task 40: Sanitize $_POST Before Passing to Sheets Importer

**Files:**
- Modify: `admin/class-adc-sheets-admin-page.php:134`

**Issue:** Entire unsanitized `$_POST` passed to `save_settings()`.

- [ ] **Step 1: Pass only expected fields**

```php
$settings_fields = array(
    'strains_url', 'strains_gid', 'edibles_url', 'edibles_gid',
    'import_mode', 'auto_sync', 'sync_frequency', 'notify_admin'
);
$settings = array();
foreach ($settings_fields as $field) {
    if (isset($_POST[$field])) {
        $settings[$field] = wp_unslash($_POST[$field]);
    }
}
ADC_Sheets_Importer::save_settings($settings);
```

- [ ] **Step 2: Commit**

```bash
git add admin/class-adc-sheets-admin-page.php
git commit -m "security: only pass expected fields to sheets importer save_settings"
```

---

## Summary

| Phase | Tasks | Focus |
|-------|-------|-------|
| **Phase 1** | 1-11 | Critical security & data integrity |
| **Phase 2** | 12-19 | Important security & logic fixes |
| **Phase 3** | 20-25 | Code quality & UX |
| **Phase 4** | 26-31 | CSS fixes |
| **Phase 5** | 32-35 | JS improvements |
| **Phase 6** | 36-38 | REST API & export fixes |
| **Phase 7** | 39-40 | Settings hardening |

**Total: 40 tasks across 7 phases.**

Tasks within a phase can be parallelized. Phases should be executed in order. After each phase, verify the calculator works at https://localhost/wordpress.
