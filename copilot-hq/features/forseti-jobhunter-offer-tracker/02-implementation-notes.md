# Implementation Notes: forseti-jobhunter-offer-tracker

- Feature: forseti-jobhunter-offer-tracker
- Author: dev-forseti
- Date: 2026-04-13
- Status: implemented

## KB references

- None found for offer comparison pages. Patterns follow existing interview_rounds and application_notes sections.

## What was built

### Schema — hook_update_9058

`jobhunter_offers` created with:
- `id` (serial, PK)
- `uid` (int unsigned)
- `saved_job_id` (int unsigned)
- `base_salary` (int nullable)
- `equity_summary` (mediumtext nullable)
- `benefits_summary` (mediumtext nullable)
- `response_deadline` (varchar 10 nullable, YYYY-MM-DD)
- `notes` (mediumtext nullable)
- `created`, `changed` (int)
- Unique key on `(uid, saved_job_id)` — enforces one offer row per user per saved job (idempotent upsert).

Hook is idempotent — guarded with `tableExists()`.

### Routes added (job_hunter.routing.yml)

| Route key | Path | Method | CSRF |
|---|---|---|---|
| `job_hunter.offers` | `/jobhunter/offers` | GET | no |
| `job_hunter.offer_save` | `/jobhunter/jobs/{job_id}/offer/save` | POST | yes |

Both require `_user_is_logged_in: TRUE` and `_permission: access job hunter`. Split-route pattern applied correctly (POST-only gets `_csrf_token: TRUE`; GET page has none).

### AC-1: Offer Details form on saved-job detail view (CompanyController::viewJob)

Rendered when both:
- `$saved_job` is non-null (job is in user's saved list)
- `$job->status === 'offered'`
- `jobhunter_offers` table exists (graceful degradation otherwise)

Loads existing offer row and pre-populates fields. Form fields: base salary (number input, 0–9,999,999), equity summary (text), benefits summary (text), response deadline (date), notes (textarea, max 2000 chars). AJAX POST with CSRF token appended as `?token=`. Inline CSS and JS injected via `html_head` (same pattern as interview_rounds section).

### AC-2: Offer comparison page (ApplicationSubmissionController::offersPage)

- Queries `jobhunter_offers` for all rows by `uid` ordered by `response_deadline ASC`.
- Joins `jobhunter_saved_jobs → jobhunter_job_requirements → jobhunter_companies` to resolve company name and job title.
- Company name field resolved via inline schema check (`fieldExists('jobhunter_companies','name') ? 'name' : 'company_name'`).
- Empty state: "You currently have 0 active offers" + explanation.
- Single offer: "You currently have 1 active offer. Add another to compare." + table.
- 2+ offers: count + comparison table (company, role, base salary, equity, deadline, link).
- Comparison table built by private `buildOffersTable()` helper.

### AC-3: User scoping

Both `offerSave()` and `offersPage()` scope all queries to `currentUser()->id()`. `offerSave()` additionally calls `loadOwnedSavedJob(uid, job_id)` which SELECTs `jobhunter_saved_jobs WHERE uid=? AND job_id=?` — returns null if not owned, triggering a 403 before any write.

### Security compliance

| Check | Status |
|---|---|
| SEC-1: `_user_is_logged_in: TRUE` on both routes | ✅ |
| SEC-2: CSRF split-route on offer_save (POST-only) | ✅ |
| SEC-3: `loadOwnedSavedJob()` ownership check before insert/update | ✅ |
| SEC-4: All free-text fields stored plain text; rendered with `htmlspecialchars()` | ✅ |
| SEC-5: Logger records only uid and saved_job_id, never salary/notes | ✅ |

## Verification commands

```bash
# AC-4: schema
cd /var/www/html/forseti && vendor/bin/drush sql:query "DESCRIBE jobhunter_offers"

# AC-2: offers page loads
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/offers | grep -q "My Offers"

# AC-3: user isolation
vendor/bin/drush sql:query "SELECT COUNT(*) FROM jobhunter_offers WHERE uid=<uid_B>"

# AC-1: offer-details form in view-job page (requires a job with status='offered')
# Set job status: vendor/bin/drush sql:query "UPDATE jobhunter_job_requirements SET status='offered' WHERE id=<id>"
# Then: curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/job/<id> | grep -q 'offer-details'
```

## Rollback plan

Revert commits. Table `jobhunter_offers` will remain but is inert without the routes/controller code. Table can be dropped with `drush sql:query "DROP TABLE jobhunter_offers"` if full rollback is required.

## Cross-site sync

N/A — `job_hunter` is forseti.life-only.
