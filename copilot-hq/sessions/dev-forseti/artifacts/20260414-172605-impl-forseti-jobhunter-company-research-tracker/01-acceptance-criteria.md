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
