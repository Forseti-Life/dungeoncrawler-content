# Implementation Notes: forseti-jobhunter-company-interest-tracker

- Feature: forseti-jobhunter-company-interest-tracker
- Author: ba-forseti / dev-forseti
- Date: 2026-04-14
- Status: confirmed live — no new code changes required

## KB references

None found. Pattern follows existing per-user overlay tables (interview_rounds, offers).

## Implementation state

Feature is **fully implemented** — verified on 2026-04-14 by reading the live code and running schema/route queries. No changes were made in this release cycle.

## Storage

Table `jobhunter_company_interest` — verified present in production DB:
- `id` (int unsigned, auto-increment PK)
- `uid` (int unsigned NOT NULL)
- `company_id` (int unsigned NOT NULL)
- `interest_level` (tinyint, default 1)
- `culture_fit_score` (tinyint, nullable)
- `status` (varchar 16, default 'researching')
- `research_links` (mediumtext, nullable)
- `notes` (mediumtext, nullable)
- `created` / `changed` (int)
- Unique key `uid_company` on `(uid, company_id)` — idempotent upsert enforced

## Routes

| Route key | Path | Method | CSRF |
|---|---|---|---|
| `job_hunter.company_watchlist` | `/jobhunter/companies/my-list` | GET | no |
| `job_hunter.company_interest_form` | `/jobhunter/companies/{company_id}/interest` | GET | no |
| `job_hunter.company_interest_save` | `/jobhunter/companies/{company_id}/interest/save` | POST | yes |

Split-route pattern applied: POST-only gets `_csrf_token: 'TRUE'`; GET pages have none. All three routes require `_user_is_logged_in: TRUE` and `_permission: access job hunter`.

## Controllers (CompanyController.php)

- `companyWatchlist()` (~line 3402): queries `jobhunter_company_interest` scoped to `uid`, joins companies for name, renders table with company name, interest level, status badge, and edit link. CSS class `company-watchlist` on container.
- `companyInterestForm($company_id)` (~line 3472): loads existing row and pre-populates fields (AC-3). Form fields: interest_level (1–5 select), culture_fit_score (1–5 optional), status (select), research_links (textarea), notes (textarea). CSRF token appended to POST URL.
- `companyInterestSave($company_id)` (~line 3565): validates company exists, sanitizes all inputs via `strip_tags()`, clamps numeric ranges, uid always from session (never from body — SEC-3). Upserts via existing_id check. Logs uid+company_id only (SEC-5). Redirects to watchlist on success.

## Constants

`COMPANY_INTEREST_STATUSES` in CompanyController — values: `['researching', 'interviewing', 'rejected', 'accepted']`.

## Security compliance

| Check | Status |
|---|---|
| SEC-1: `_user_is_logged_in: TRUE` on all three routes | ✅ |
| SEC-2: CSRF split-route `_csrf_token: TRUE` on POST save only | ✅ |
| SEC-3: uid always from `currentUser()->id()`, never from request body | ✅ |
| SEC-4: notes/research_links stored via `strip_tags()`; rendered with Twig auto-escaping | ✅ |
| SEC-5: Logger records uid+company_id only, never notes content | ✅ |

## Verification targets

```bash
# AC-4: schema
cd /var/www/html/forseti && vendor/bin/drush sql:query "DESCRIBE jobhunter_company_interest"

# AC-4: unique key
vendor/bin/drush sql:query "SHOW INDEX FROM jobhunter_company_interest"

# AC-1/AC-3: save and pre-populate (requires an authenticated session cookie)
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies/my-list

# AC-2: watchlist page
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies/my-list | grep -q "company-watchlist"

# AC-5: cross-user isolation
vendor/bin/drush sql:query "SELECT COUNT(*) FROM jobhunter_company_interest WHERE uid=<uid_B> AND company_id=<id>"
```

## Cross-site sync

N/A — `job_hunter` is forseti.life-only.


## Approach

Build a per-user watchlist overlay on top of the existing global
`jobhunter_companies` catalog. Keep the global catalog unchanged; store all
user-specific state in `jobhunter_company_interest`.

## Data model

Recommended table fields:
- `id`
- `uid`
- `company_id`
- `interest_level`
- `culture_fit_score`
- `status`
- `research_links`
- `notes`
- `created`
- `changed`

Use a unique key on `(uid, company_id)` so revisits update the same row.

## UI surfaces

- Company detail page:
  - `Track this company` form
  - pre-populate existing row for current user
- `/jobhunter/companies/my-list`:
  - sortable watchlist with interest stars, culture-fit score, status badge

## Logic notes

- Keep research links as plain text or a simple newline/comma-delimited field
  unless Dev prefers a JSON encoding; the brief allows a text field.
- Do not let this feature mutate global company catalog fields.
- This slice is adjacent to `company-research-tracker`; avoid duplicating forms
  if the two features converge into one user-company overlay UI.

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/companies/my-list
drush sql:query "DESCRIBE jobhunter_company_interest"
drush sql:query "SELECT interest_level, culture_fit_score, status FROM jobhunter_company_interest WHERE uid=<uid> AND company_id=<id>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
