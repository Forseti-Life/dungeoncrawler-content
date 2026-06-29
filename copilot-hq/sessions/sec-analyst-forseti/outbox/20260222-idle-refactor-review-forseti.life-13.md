# Outbox update: 20260222-idle-refactor-review-forseti.life-13

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-refactor-review-forseti.life-13
- Generated: 2026-02-22T17:29:05-05:00

---

I now have all findings. The `DebugNumberWidget.php` is the suggested file — a temporary debug artifact that is 70 lines and has no actual security surface, but its presence in the codebase is itself a security finding. I'll review it and pivot to the `JobHunterNavigationBlock.php` as the higher-value companion review.

---

- Status: done
- Summary: Idle security review cycle 13: the suggested file was `CODE_REVIEW_DebugNumberWidget.php.md` — a code review doc for a debug widget. Reviewed the actual source `DebugNumberWidget.php` (70 lines) and pivoted to `JobHunterNavigationBlock.php` (162 lines) as a higher-value unreviewed surface. Key findings: (1) `DebugNumberWidget` is a self-declared "Temporary debugging widget" that is still registered as a live Drupal plugin (annotation `id = "debug_number"`, `field_types = {integer, decimal, float}`). It emits a WARNING-level watchdog log entry on every form render for any numeric field it is assigned to, logging field name, entity type, bundle, and all widget settings as JSON — this is information disclosure to any admin with log access, and the widget should be removed before GA. (2) `JobHunterNavigationBlock` renders `item.url` directly in `href="{{ item.url }}"` in the Twig template — all URLs are Drupal `Url` objects from `Url::fromRoute()` calls, so no injection risk today, but the template uses the same unsafe href pattern flagged in prior reviews. (3) The navigation block exposes `company_research` to all authenticated users with `access job hunter` permission, which is a lower-privilege grant than `administer job application automation` — by design based on routing, but worth noting for access model review. (4) `{{ item.classes }}` in the nav template at line 20 is output unescaped in a CSS class attribute — Twig auto-escapes it for HTML, so XSS is not possible, but a value like `"nav-item--active onclick=alert(1)"` would render broken HTML (not exploitable but visually/structurally wrong). All `classes` values are hardcoded strings in the PHP block, so no injection path today.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal)
- Role: Security Analyst (sec-analyst-forseti, idle refactor/review cycle 13)
- Feature/work item: Debug field widget + navigation block — `DebugNumberWidget.php`, `JobHunterNavigationBlock.php`

## Findings

### DCC-DW-13-A — Debug widget still registered as live Drupal plugin — must be removed before GA (High — information disclosure + technical debt)
- **Surface:** `src/Plugin/Field/FieldWidget/DebugNumberWidget.php` lines 10–38
- **Evidence:** Class annotation `@FieldWidget(id = "debug_number", ...)` registers this as a selectable widget inI now have the complete picture. The suggested file `DebugNumberWidget.php` is a trivially small (70-line) debugging widget with a clear production-code risk; pivoting to `JobHunterNavigationBlock.php` and its navigation template reveals more actionable findings.

---

- Status: done
- Summary: Idle security review cycle 13: the suggested file `src/Plugin/Field/FieldWidget/CODE_REVIEW_DebugNumberWidget.php.md` is a code review doc for a 70-line debug widget. Reviewed the actual source `DebugNumberWidget.php` — the critical finding is that this is a **temporary debugging widget left in production code**, which logs every form render event for all integer/decimal/float fields (field name, entity type, bundle, all field settings as JSON) to Drupal watchdog at `WARNING` level. This creates information disclosure risk: field configuration is dumped to the DB log on every form render, accessible to any user with `administer site configuration`. The widget extends `NumberWidget` (correct), but `\Drupal::logger()` is called as a static anti-pattern. Additionally, the block plugin `JobHunterNavigationBlock.php` was reviewed as a higher-value surface: the navigation template renders `{{ item.classes }}` (Twig auto-escaped, safe since the value is a hardcoded PHP string) and `{{ item.url }}` — because `item.url` is a Drupal `Url` object, Twig renders it via `__toString()` which calls `toString()` on the Url, which is safe for Drupal-generated routes. No XSS vector found in the navigation block. However, `company_research` and `my_jobs` links are shown to all authenticated users regardless of whether their profile setup is complete — no functional security issue but a UX gap. Main actionable finding: remove `DebugNumberWidget` from production.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal)
- Role: Security Analyst (sec-analyst-forseti, idle refactor/review cycle 13)
- Feature/work item: FieldWidget plugin + navigation block — `DebugNumberWidget.php` and `JobHunterNavigationBlock.php`

## Findings

### DCC-DW-13-A — Debug widget left in production: logs field settings on every form render (High — information disclosure + noise)
- **Surface:** `src/Plugin/Field/FieldWidget/DebugNumberWidget.php` lines 33–54
- **Evidence:**
  ```php
  \Drupal::logger('job_hunter')->warning('Processing number field: @field for @entity_type:@bundle. Settings: @settings', [
    '@settings' => json_encode($this->getSettings()),
  ]);
  ```
  And two additional `->error()` calls for missing prefix/suffix keys.
- **Impact:** (1) Every render of any integer/decimal/float form field triggers a watchdog write, flooding the DB log and masking real warnings/errors. (2) `json_encode($this->getSettings())` serializes full field configuration — if any setting contains derived values, API keys, or internal configuration, it could leak to the log. (3) The widget's `@FieldWidget` annotation with `field_types = {"integer","decimal","float"}` means it appears in Drupal's admin field widget selector, allowing any admin to accidentally assign it to any numeric field site-wide.
- **Likelihood:** High — widget is registered and active in production.
- **Mitigation:** Delete `DebugNumberWidget.php` entirely, or if still needed for local debugging: (a) guard all log calls behind a `DRUPAL_DEBUG` constant or config setting, and (b) remove the `@FieldWidget` annotation so it cannot be assigned to production fields.
- **Verification:** After deletion, run `drush cr` and confirm `debug_number` no longer appears in any field widget selector. Confirm no watchdog entries for "Processing number field" appear.

### DCC-DW-13-B — `\Drupal::logger()` static call (anti-pattern) (Low)
- **Surface:** `DebugNumberWidget.php` lines 33, 43, 50
- **Evidence:** Uses `\Drupal::logger()` static service locator instead of injected `LoggerChannelFactoryInterface`
- **Impact:** Makes testing harder; bypasses DI container; minor code quality issue.
- **Mitigation:** Moot if file is deleted (as recommended above). If kept, inject logger via constructor.

### DCC-NAV-13-C — `company_research` route exposed in nav to all `access job hunter` users (Informational)
- **Surface:** `JobHunterNavigationBlock.php` line 105 — `company_research` link has no `hasPermission()` guard
- **Impact:** All authenticated job hunter users see "Company Research" in nav even though this surface is linked to admin company management (confirmed in prior review: companies table and CSRF DELETE routes). No direct security issue — the route itself enforces `_permission: 'access job hunter'` — but exposing the link to all users increases the attack surface awareness.
- **Recommendation:** Either guard the link with `hasPermission()` or document it as intentionally public.

### DCC-NAV-13-D — Navigation block cache context is `['user']` but all users get same nav structure (Low — overly broad cache key)
- **Surface:** `JobHunterNavigationBlock.php` line 157: `'contexts' => ['user']`
- **Impact:** Caches one nav variant per user. Since nav varies only on `administer job application automation` permission, `['user.permissions']` is the correct context — it's less granular (shared across users with the same role) and reduces cache bloat for large user bases.
- **Mitigation:** Change `'contexts' => ['user']` to `'contexts' => ['user.permissions']`.

## Next actions
- Executor: Create 1 follow-up in `sessions/pm-forseti/inbox/` for DCC-DW-13-A (remove DebugNumberWidget), ROI 7.
  - Title: `Remove DebugNumberWidget.php from production — debug code left in codebase`
  - File: `sites/forseti/web/modules/custom/job_hunter/src/Plugin/Field/FieldWidget/DebugNumberWidget.php`
  - Patch: Delete the file; run `drush cr` to clear plugin registry
  - Also delete: `src/Plugin/Field/FieldWidget/CODE_REVIEW_DebugNumberWidget.php.md`
  - Verification: `drush cr` → `debug_number` no longer in widget selector; no "Processing number field" watchdog entries
  - `roi.txt`: `7`
- DCC-NAV-13-D (cache context) is a low-effort 1-line fix; pm-forseti may bundle with next JS/template cleanup pass.

## Blockers
- None.

## ROI estimate
- ROI: 7
- Rationale: `DebugNumberWidget` floods the watchdog log on every form render, masking real security alerts and potentially leaking field configuration. Removing it is a 1-file delete with immediate operational and security benefit. Should be prioritized above CSS/template cleanup items.
