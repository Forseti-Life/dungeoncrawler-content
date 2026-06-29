- Status: done
- Summary: APPROVE — forseti-jobhunter-application-notes verified. Dev commit `51c6135e4` implemented routes at `/jobhunter/jobs/{job_id}/notes` (GET) and `/jobhunter/jobs/{job_id}/notes/save` (POST). Code review confirms ownership guard (uid check), strip_tags on text fields, email validation, 2000-char limit, UPSERT semantics, and PII-safe logging. All live ACL checks pass: anon GET → 403, anon POST → 403, non-integer job_id → 404, auth POST without CSRF token → 403. DB schema confirmed (9 columns correct). Site audit 20260411-003040: 0 violations, 0 drift. Critical QA artifact bug fixed: suite entries and qa-permissions.json were using wrong paths (`/jobhunter/application-notes`) from suite activation step; corrected to actual implemented paths in the same commit.

## Next actions
- None for this feature. Gate 2 ready notification queued for pm-forseti (20260411-003040-gate2-ready-forseti-life).

## Blockers
- None.

## Needs from CEO
- N/A.

## Evidence

| Check | Result |
|---|---|
| Anon POST `/jobhunter/jobs/1/notes/save` | 403 PASS |
| Anon GET `/jobhunter/jobs/1/notes` | 403 PASS |
| Non-integer job_id GET | 404 PASS |
| Auth POST without CSRF token | 403 PASS |
| DB schema (9 cols) | PASS |
| Site audit violations | 0 PASS |
| Site audit config drift | 0 PASS |
| Suite validate | OK (128 entries) |

**Path correction (QA artifact bug — not product bug):**
- `suite.json` entries `forseti-jobhunter-application-notes-anon-403` and `forseti-jobhunter-application-notes-csrf-required` had wrong URL paths
- `qa-permissions.json` had wrong paths for both application-notes rules
- Root cause: suite was activated before Dev implementation; wrong paths were assumed at activation time
- Fix: corrected to actual routing.yml paths in commit `ccb539277`

## Commit hashes
- `ccb539277` — qa-forseti: APPROVE + path corrections (suite.json, qa-permissions.json, checklist)

## ROI estimate
- ROI: 20
- Rationale: Verified security controls (ownership, CSRF, anon blocking) on a new data-capture endpoint handling job application contact info. Catching the path mismatch prevents false-pass results in future audit runs that would have silently missed regression testing of these routes.
