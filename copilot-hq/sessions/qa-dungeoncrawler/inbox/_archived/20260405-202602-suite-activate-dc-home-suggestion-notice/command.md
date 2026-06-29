# Suite Activation: dc-home-suggestion-notice

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-05T20:26:02+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-home-suggestion-notice"`**  
   This links the test to the living requirements doc at `features/dc-home-suggestion-notice/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-home-suggestion-notice-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-home-suggestion-notice",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-home-suggestion-notice"`**  
   Example:
   ```json
   {
     "id": "dc-home-suggestion-notice-<route-slug>",
     "feature_id": "dc-home-suggestion-notice",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-home-suggestion-notice",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-home-suggestion-notice

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-05
**Feature:** Home page suggestion notice — visible static text block on Dungeoncrawler front page
**AC source:** `features/dc-home-suggestion-notice/01-acceptance-criteria.md`
**Implementation notes:** not yet written (grooming phase; Dev notes TBD)

## KB references
- KB: no prior lessons found for dungeoncrawler home page content blocks. General Drupal block patterns apply.
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after template/block changes; surface regressions immediately.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `role-url-audit` | `scripts/site-audit-run.sh` | Home page HTTP 200 for anon + authenticated roles; confirm no regression after block addition |
| `module-test-suite` (functional) | PHPUnit functional | Home page renders notice text; content visible in HTML response |

> **Note to PM:** TC-HSN-05 (no JS errors on page load) cannot be expressed as PHPUnit or curl automation — it requires a browser/Playwright check. This is documented as a manual verification step unless a Playwright suite is added at Stage 0. Risk is low (static content only, no JS components expected).

---

## Test cases

### TC-HSN-01 — Home page returns HTTP 200 for anonymous users
- **AC:** `[TEST]` Verify home page HTTP response is still 200 after change
- **Suite:** `role-url-audit`
- **Entry type:** role-url-audit rule — path `/home`, role `anon`, expect `allow` (HTTP 200)
- **Expected:** HTTP 200 for anonymous GET `/home`
- **Regression scope:** existing `public-pages` rule in `qa-permissions.json` covers `/home`; no new rule needed — verify it still passes post-implementation
- **Tags:** happy path, regression

### TC-HSN-02 — Home page returns HTTP 200 for authenticated users
- **AC:** `[TEST]` Verify notice renders correctly for authenticated users
- **Suite:** `role-url-audit`
- **Entry type:** role-url-audit rule — path `/home`, roles `authenticated`, `dc_playwright_player`, `dc_playwright_admin`, `administrator`, expect `allow`
- **Expected:** HTTP 200 for all authenticated roles
- **Tags:** happy path

### TC-HSN-03 — Notice text present in home page HTML (anonymous)
- **AC:** `[NEW]` Notice visible to anonymous users; `[CONTENT]` text "We are actively implementing player suggestions. Keep the ideas coming!"
- **Suite:** `module-test-suite` (functional)
- **Test method:** `testHomePageSuggestionNoticeAnon()` in a new or extended `HomePageTest.php`
- **Steps:** `drupalGet('/home')` as anonymous; assert response contains notice text (partial or full match)
- **Expected:** response body contains "We are actively implementing player suggestions"
- **Tags:** happy path, content

### TC-HSN-04 — Notice text present in home page HTML (authenticated)
- **AC:** `[NEW]` Notice visible to authenticated users
- **Suite:** `module-test-suite` (functional)
- **Test method:** `testHomePageSuggestionNoticeAuthenticated()` — log in as `dc_playwright_player`; `drupalGet('/home')`; assert notice text present
- **Expected:** response body contains notice text for authenticated user
- **Tags:** happy path, content

### TC-HSN-05 — Home page layout not broken (manual check — cannot automate)
- **AC:** `[NEW]` Notice does not break existing home page layout on desktop or mobile
- **Suite:** manual verification only
- **Steps:** Load `http://localhost:8080/home` and `https://dungeoncrawler.forseti.life/home` in a browser; visually confirm no layout overflow, text wrapping, or element displacement
- **Expected:** page renders cleanly, notice is above the fold or in a visible banner/info region
- **Note to PM:** This AC item cannot be expressed as curl or PHPUnit automation. Recommend marking as manual spot-check at Gate 2. Low risk — static content addition.
- **Tags:** manual, layout

### TC-HSN-06 — No JS errors on page load (manual check — cannot automate without Playwright)
- **AC:** `[TEST]` Verify no JS errors introduced on page load
- **Suite:** manual verification OR Playwright (if added at Stage 0)
- **Steps:** Open browser DevTools console; load `/home`; confirm no JS errors in console
- **Expected:** zero JS errors in console
- **Note to PM:** Requires Playwright or manual browser check. No automated coverage unless Playwright suite is extended at Stage 0.
- **Tags:** manual, regression

---

## ACL impact

- No new ACL rules required. `/home` already covered by `public-pages` rule (`anon: allow`).
- At Stage 0, verify `public-pages` rule still covers `/home` after implementation and no new routes are added.
- `qa-permissions.json` update: none expected at Stage 0 unless implementation introduces a new URL.

---

## Definition of done (Gate 2 checklist)

- [ ] TC-HSN-01: HTTP 200 anon role-url-audit PASS
- [ ] TC-HSN-02: HTTP 200 all auth roles role-url-audit PASS
- [ ] TC-HSN-03: notice text in HTML (anon) PHPUnit PASS
- [ ] TC-HSN-04: notice text in HTML (auth) PHPUnit PASS
- [ ] TC-HSN-05: layout manual spot-check PASS (or risk-accepted by PM)
- [ ] TC-HSN-06: no JS errors manual spot-check PASS (or risk-accepted by PM)

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)
# Feature: dc-home-suggestion-notice

## Gap analysis reference

Gap analysis: quick check for existing home page block or front-page content region in dungeoncrawler_content.

```bash
grep -rl "home\|front.page\|suggestion" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ 2>/dev/null | head -10
```

All criteria below are `[NEW]` or `[CONTENT]` — this is a content/UI addition only. No existing suggestion-notice block found. Dev builds a simple block or edits the front page template.

## KB references
- KB: no prior lessons for home page content blocks in dungeoncrawler. Reference `knowledgebase/lessons/` for general Drupal block patterns if needed.

## Happy Path

- [ ] `[NEW]` A short notice is displayed on the Dungeoncrawler home page visible to anonymous and authenticated users.
- [ ] `[CONTENT]` Notice text (or equivalent): "We are actively implementing player suggestions. Keep the ideas coming!"
- [ ] `[NEW]` The notice is positioned prominently on the home page (above the fold or in a banner/info region).
- [ ] `[NEW]` The notice is dismissible OR always visible (PM decision: always visible is acceptable for simplicity).
- [ ] `[NEW]` The notice does not break the existing home page layout on desktop or mobile.

## Edge Cases / Failure Modes

- [ ] `[TEST]` Verify notice renders correctly for anonymous users (no login required).
- [ ] `[TEST]` Verify notice renders correctly for authenticated users.
- [ ] `[TEST]` Verify home page HTTP response is still 200 after change.
- [ ] `[TEST]` Verify no JS errors introduced on page load.

## Definition of Done

- Notice text is visible on the dungeoncrawler home page at `http://localhost:8080/` (dev) and `https://dungeoncrawler.forseti.life/` (prod).
- All criteria above pass QA verification.
- `drush cr` run after any template/block changes.

## Rollback

- Revert the block config or template change.
- Run `drush cr`.
- No database schema changes expected.
- Agent: qa-dungeoncrawler
- Status: pending
