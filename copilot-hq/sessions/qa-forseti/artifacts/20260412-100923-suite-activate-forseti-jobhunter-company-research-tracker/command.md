- Status: done
- Completed: 2026-04-12T11:23:21Z

# Suite Activation: forseti-jobhunter-company-research-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-12T10:09:23+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-company-research-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-company-research-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-company-research-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-company-research-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-company-research-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-company-research-tracker-<route-slug>",
     "feature_id": "forseti-jobhunter-company-research-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-company-research-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-company-research-tracker

- Feature: forseti-jobhunter-company-research-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least 2 rows in `jobhunter_companies` (global catalog, admin-managed)
- `jobhunter_company_research` table exists: `drush sql:query "DESCRIBE jobhunter_company_research"`

## Test cases

### TC-1: /jobhunter/companies renders with tracked companies (smoke)

- **Type:** functional / smoke
- **When:** GET `/jobhunter/companies` as authenticated user with 1 tracked company
- **Then:** HTTP 200; page contains `company-research` markup; company name visible
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies | grep -c 'company-research'` → ≥ 1

---

### TC-2: Submit research form — all fields saved

- **Type:** functional / happy path
- **When:** POST research form for company_id 7 with score=8, notes="Good WLB", link="https://glassdoor.com/company7"
- **Then:** HTTP 200; DB row created; page shows success flash
- **Command:** `SELECT culture_fit_score, notes FROM jobhunter_company_research WHERE uid=<uid> AND company_id=7` → 8 | 'Good WLB'

---

### TC-3: Research form pre-populates on revisit

- **Type:** functional / state persistence
- **When:** GET company detail after saving research
- **Then:** form fields pre-filled with saved values
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies/<id>` | grep 'Good WLB' → match

---

### TC-4: Update existing research row (no duplicate)

- **Type:** functional / idempotency
- **When:** POST updated score=9 for company_id 7
- **Then:** row updated; `SELECT COUNT(*)... WHERE uid=<uid> AND company_id=7` → 1 (not 2)

---

### TC-5: Culture-fit score 11 rejected (boundary)

- **Type:** validation
- **When:** POST with `culture_fit_score=11`
- **Then:** HTTP 422; no DB row created/updated
- **Command:** check response status → 422

---

### TC-6: javascript: link rejected

- **Type:** security / URL validation
- **When:** POST with `research_links_json='["javascript:alert(1)"]'`
- **Then:** HTTP 422; DB has no javascript: URI
- **Command:** check response → 422; `SELECT research_links_json ... WHERE company_id=7` → no javascript: content

---

### TC-7: Unauthenticated GET returns 403

- **Type:** security / auth gate
- **When:** GET `/jobhunter/companies` with no session cookie
- **Then:** HTTP 403 or redirect to login
- **Command:** `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/jobhunter/companies` → 403 or 302

---

### TC-8: Cross-user isolation

- **Type:** security / data isolation
- **When:** user B loads `/jobhunter/companies` (has no research rows)
- **Then:** empty list; no data from user A visible
- **Command:** `drush sql:query "SELECT COUNT(*) FROM jobhunter_company_research WHERE uid=<uid_B>"` → 0; page shows empty-state

---

### TC-9: CSRF required on POST route

- **Type:** security / CSRF
- **When:** POST to research save endpoint without valid CSRF token
- **Then:** HTTP 403
- **Command:** POST without `X-CSRF-Token` header → 403

---

### TC-10: No research content in watchdog debug logs

- **Type:** observability / privacy
- **When:** save research notes; then check watchdog
- **Then:** `drush watchdog:show --count=10 --severity=debug` shows no notes content in log entries

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-company-research-tracker

- Feature: forseti-jobhunter-company-research-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Add per-user company research annotations on top of the existing global `jobhunter_companies` catalog. New table `jobhunter_company_research` keyed by `(uid, company_id)` stores: culture-fit score (0–10), research notes, research links (JSON array). A list view at `/jobhunter/companies` shows the user's tracked companies. The global catalog remains admin-managed; this feature adds the user-layer overlay only.

## Acceptance criteria

### AC-1: Company research list at /jobhunter/companies

Given an authenticated user has tracked at least one company, when they navigate to `/jobhunter/companies`, then a list renders showing: company name, industry (from global catalog), culture-fit score (user's), last research date, link to detail view. Companies not yet tracked by this user are absent.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies | grep -q 'company-research'`

---

### AC-2: Add research overlay — culture-fit score + notes + links saved

Given an authenticated user submits the research form for company_id 7 with score=8, notes="Great culture, remote-first", links=["https://example.com/glassdoor"], when the form is saved, then a row exists in `jobhunter_company_research`.

**Verify:**
```sql
SELECT culture_fit_score, notes, research_links_json
FROM jobhunter_company_research
WHERE uid=<uid> AND company_id=7;
-- Expected: 8 | 'Great culture...' | '["https://example.com/glassdoor"]'
```

---

### AC-3: Research overlay pre-populates on revisit

Given a saved research row for `(uid, company_id=7)`, when the user revisits the company detail page, then the form is pre-populated with saved score, notes, and links.

**Verify:** page HTML contains `value="8"` for score field; notes textarea contains saved text.

---

### AC-4: Culture-fit score bounded to 0–10

Given an authenticated user submits score=11, when the form is submitted, then the endpoint returns HTTP 422; no row created/updated.

**Verify:** POST with `culture_fit_score=11` → response status 422, body contains validation error.

---

### AC-5: Cross-user isolation

Given user A has a research row for company_id 7 and user B has no research rows, when user B visits `/jobhunter/companies`, then no data from user A is displayed.

**Verify:** `drush sql:query "SELECT COUNT(*) FROM jobhunter_company_research WHERE uid=<uid_B>"` → 0; user B's page shows empty-state.

---

### AC-6: Research link URL validation

Given a user submits a research link with value `javascript:alert(1)`, when the form is submitted, then the endpoint returns HTTP 422; the malicious URL is not stored.

**Verify:** POST with non-HTTP URL → 422; DB row has no javascript: URI in `research_links_json`.

---

### AC-7: DB schema — jobhunter_company_research table exists

**Verify:** `drush sql:query "DESCRIBE jobhunter_company_research"` → columns: id, uid, company_id, culture_fit_score, notes, research_links_json, created, changed. Unique key on (uid, company_id).

---

## Security acceptance criteria

### SEC-1: Authentication required
All routes require `_user_is_logged_in: 'TRUE'`. Unauthenticated requests → HTTP 403.

### SEC-2: CSRF on POST
POST save route has `_csrf_token: 'TRUE'` (split-route pattern; GET page has no CSRF gate).

**Verify:** `grep -A5 "company_research" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep "_csrf_token"` → present on POST route only.

### SEC-3: uid-scoped writes
Controller uses `currentUser()->id()` for all DB writes; no uid parameter accepted from URL/body.

### SEC-4: URL allowlist for research links
Links validated as HTTP or HTTPS only. `javascript:`, `data:`, and other schemes rejected with 422.

### SEC-5: Output escaping
Notes and company names rendered with Twig auto-escaping (no `|raw`). Links rendered with `rel="noopener noreferrer"`.

### SEC-6: No PII/sensitive data in logs
Notes must NOT appear in watchdog at debug severity. Log only `uid`, `company_id`, operation type.
