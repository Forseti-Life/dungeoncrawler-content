# Implementation Notes: forseti-jobhunter-company-research-tracker

- Feature: forseti-jobhunter-company-research-tracker
- Author: ba-forseti
- Date: 2026-04-12
- Status: implemented (dev-forseti, 2026-04-14)

## Approach

Implement a per-user research overlay keyed by `(uid, company_id)` and expose it
through `/jobhunter/companies` plus a company-detail tracking form.

## Data model

Primary table: `jobhunter_company_research`
- `id`
- `uid`
- `company_id`
- `culture_fit_score`
- `notes`
- `research_links_json`
- `created`
- `changed`

Unique key on `(uid, company_id)`.

## UI surfaces

- `/jobhunter/companies`:
  - list only companies the current user has tracked
  - show culture-fit score and last research date
- company detail or company-related saved-job view:
  - add/edit research overlay
  - pre-populate existing user data

## Validation notes

- Restrict `culture_fit_score` to 0–10.
- Validate each research link as HTTP/HTTPS only.
- Store links in `research_links_json` to support multiple values cleanly.

## Cross-feature note

- This feature and `company-interest-tracker` both describe per-user overlays on
  companies. Dev should factor shared query/render helpers if both continue.
- Research-tracker focuses on notes/links/score; interest-tracker adds watchlist
  status and interest stars.

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies
drush sql:query "DESCRIBE jobhunter_company_research"
drush sql:query "SELECT culture_fit_score, notes FROM jobhunter_company_research WHERE uid=<uid> AND company_id=<id>"
```

## Implementation completed (dev-forseti 2026-04-14)

### What was already in place (prior release cycle)
- `jobhunter_company_research` table — created by `hook_update_9049` with unique key `(uid, company_id)`, columns: id, uid, company_id, culture_fit_score, notes, research_links_json, created, changed.
- Routes in `job_hunter.routing.yml`:
  - GET `/jobhunter/companies` → `companyResearchList()`
  - GET `/jobhunter/companies/{company_id}/research` → `companyResearchForm()`
  - POST `/jobhunter/companies/{company_id}/research/save` → `companyResearchSave()` (CSRF split-route)
- All three controller methods in `CompanyController.php`

### Fix applied this cycle (commit 9966ef715)
- Removed a dead/duplicate SELECT before the proper JOIN query in `companyResearchList()`.
- Added `rel="noopener noreferrer"` to all outbound links in the list view (SEC-5).

### AC coverage
| AC | Status |
|---|---|
| AC-1: list at /jobhunter/companies | ✅ |
| AC-2: save score/notes/links | ✅ |
| AC-3: pre-populate on revisit | ✅ |
| AC-4: 0–10 score validation (422) | ✅ |
| AC-5: cross-user isolation (uid-scoped queries) | ✅ |
| AC-6: research link URL validation (http/https only, 422) | ✅ |
| AC-7: schema DESCRIBE verified | ✅ |
| SEC-1: _user_is_logged_in: TRUE on all routes | ✅ |
| SEC-2: _csrf_token: TRUE on POST only | ✅ |
| SEC-3: uid from currentUser()->id() only | ✅ |
| SEC-4: URL allowlist | ✅ |
| SEC-5: rel=noopener noreferrer + htmlspecialchars | ✅ |
| SEC-6: logs uid+company_id only | ✅ |

