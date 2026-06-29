- Status: done
- Completed: 2026-04-11T02:52:01Z

# Test Plan Design: forseti-jobhunter-application-deadline-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-11T02:23:17+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-jobhunter-application-deadline-tracker/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-jobhunter-application-deadline-tracker "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/forseti/suite.json`
- Do NOT edit `org-chart/sites/forseti.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
