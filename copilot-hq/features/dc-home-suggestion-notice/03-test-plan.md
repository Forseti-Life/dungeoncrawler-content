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
