# Implementation Notes: forseti-jobhunter-interview-outcome-tracker

- Feature: forseti-jobhunter-interview-outcome-tracker
- Author: ba-forseti / dev-forseti
- Date: 2026-04-13
- Status: confirmed live — no new code changes required

## Implementation state

Feature is **fully implemented** — verified on 2026-04-13 by reading the live code and running schema queries. No changes were made in this release cycle. This file records the confirmed implementation shape for QA Gate 2 reference.

## Storage

Table `jobhunter_interview_rounds` — verified present in production DB:
- `id` (int unsigned, auto-increment PK)
- `uid` (int unsigned NOT NULL)
- `saved_job_id` (int unsigned NOT NULL)
- `round_type` (varchar(32), default `phone-screen`)
- `outcome` (varchar(16), default `pending`)
- `conducted_date` (varchar(10), NOT NULL, indexed)
- `notes` (mediumtext, nullable)
- `created` / `changed` (int)

Schema created by `_job_hunter_create_interview_rounds_table()` called in `hook_install` and in `hook_update_N` (idempotent guard on `tableExists`).

## Routes

`job_hunter.interview_round_save` — POST `/jobhunter/interview-rounds/{job_id}/save`
- Controller: `CompanyController::interviewRoundSave()`
- `methods: [POST]`, `_user_is_logged_in: TRUE`, `_csrf_token: TRUE` (split-route pattern)

## UI on saved-job detail view

Rendered in `viewJob()` when `jobhunter_interview_rounds` table exists and `$saved_job` is non-null:
- Add/Edit form: round_type select, outcome select, date input (required), notes textarea (max 4000 chars)
- Chronological log: sorted `ASC conducted_date, id`; color-coded outcome badges (pending=grey, passed=green, failed=red, withdrawn=amber)
- Edit button populates form via `data-*` attributes; `save_token` appended as `?token=` to AJAX POST URL (CSRF split-route)
- Status message div updates on success/error without page reload

## Constants (CompanyController.php line 41/46)

```
INTERVIEW_ROUND_TYPES  = ['phone-screen', 'technical', 'behavioral', 'final', 'other']
INTERVIEW_ROUND_OUTCOMES = ['pending', 'passed', 'failed', 'withdrawn']
```

## Security compliance

| Check | Status |
|---|---|
| SEC-1: `_user_is_logged_in: TRUE` on route | ✅ |
| SEC-2: CSRF via split-route `_csrf_token: TRUE` on POST | ✅ |
| SEC-3: `loadOwnedSavedJob()` ownership check before insert/update | ✅ |
| SEC-4: Notes stored as plain text; `htmlspecialchars()` on display | ✅ |
| SEC-5: Notes never logged to watchdog | ✅ |

## Verification targets

```bash
# Schema (AC-4)
cd /var/www/html/forseti && vendor/bin/drush sql:query "DESCRIBE jobhunter_interview_rounds"

# Chronological log (AC-2)
vendor/bin/drush sql:query "SELECT round_type, outcome, conducted_date FROM jobhunter_interview_rounds WHERE uid=<uid> AND saved_job_id=<id> ORDER BY conducted_date ASC"

# No-duplicate update (AC-3)
vendor/bin/drush sql:query "SELECT COUNT(*) FROM jobhunter_interview_rounds WHERE saved_job_id=<id> AND uid=<uid> AND round_type='technical'"

# Cross-user isolation (AC-5)
vendor/bin/drush sql:query "SELECT COUNT(*) FROM jobhunter_interview_rounds WHERE uid=<uid_B>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
