# Acceptance Criteria: forseti-jobhunter-application-deadline-tracker

- Feature: forseti-jobhunter-application-deadline-tracker
- PM owner: pm-forseti
- KB reference: none found (new feature pattern; follows forseti-jobhunter-application-notes for POST form pattern)

## Happy Path

- [ ] `[NEW]` AC-1: Authenticated user with `access job hunter` can GET `/jobhunter/job/{job_id}` and see a date form with `deadline_date` and `follow_up_date` fields.
- [ ] `[NEW]` AC-2: Submitting valid dates via POST saves to DB; confirmation message shown; re-GET shows the saved dates.
- [ ] `[NEW]` AC-3: Dashboard at `/jobhunter/status` shows urgency indicator: overdue jobs display red indicator, jobs due within 3 days display amber, others display default.
- [ ] `[NEW]` AC-4: Route `/jobhunter/deadlines` returns 200 for authenticated user; jobs with `deadline_date` set are listed sorted by date ascending.
- [ ] `[EXTEND]` AC-5: Submitting blank date fields saves NULL; existing records with no dates are unaffected.

## Edge Cases

- [ ] `[NEW]` AC-6: CSRF token missing on POST date save → 403 (not a server error).
- [ ] `[NEW]` AC-7: Cross-user attempt to mutate another user's job dates → 403.
- [ ] `[NEW]` Non-integer `{job_id}` in URL → 404.
- [ ] `[NEW]` Invalid date string (e.g., "not-a-date") submitted → form error shown, no DB write.
- [ ] `[NEW]` `/jobhunter/deadlines` with no dates set → empty state message shown (not a blank page).

## Failure Modes

- [ ] `[TEST-ONLY]` AC-1: Anonymous GET `/jobhunter/job/{job_id}` → 403.
- [ ] `[TEST-ONLY]` Anonymous GET `/jobhunter/deadlines` → 403.
- [ ] `[TEST-ONLY]` Anonymous POST date save → 403.

## Security

- [ ] `[TEST-ONLY]` POST date save route requires valid CSRF token; test with missing/invalid token → 403.
- [ ] `[TEST-ONLY]` Ownership guard: user A cannot save dates to user B's job (cross-uid attempt → 403).
- [ ] `[TEST-ONLY]` Date values do not appear in watchdog log entries.
