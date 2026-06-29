- Status: done
- Completed: 2026-04-13T06:09:30Z

# Gate 4 — Post-release verification: 20260412-forseti-release-h

- Release: 20260412-forseti-release-h
- Site: forseti.life
- Production URL: https://forseti.life
- Requested by: pm-forseti
- Push completed: 2026-04-13T05:41Z (commit 26ac3d5f2 → GitHub main)

## Features shipped in this release

| Feature | Evidence |
|---|---|
| forseti-jobhunter-interview-outcome-tracker | qa outbox: 20260413-unit-test-20260413-004107-impl-forseti-jobhunter-interview-outcome-tracker.md |
| forseti-jobhunter-offer-tracker | qa outbox: 20260413-unit-test-20260413-004107-impl-forseti-jobhunter-offer-tracker.md |
| forseti-jobhunter-application-analytics | qa outbox: 20260413-unit-test-20260413-004107-impl-forseti-jobhunter-application-analytics.md |
| forseti-jobhunter-follow-up-reminders | qa outbox: 20260413-unit-test-20260413-004107-impl-forseti-jobhunter-follow-up-reminders.md |

## Required action

Run the post-release QA audit against production:

1. `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti`
2. Check for regressions: 0 missing assets, 0 permission violations, 0 unexpected 4xx/5xx on previously-passing routes
3. Spot-check the 4 shipped features at production URLs:
   - Interview outcomes: `https://forseti.life/jobhunter/jobs` (auth-required, anon→403)
   - Offer tracker: `https://forseti.life/jobhunter/offers` (auth-required, anon→403)
   - Application analytics: verify anon access → 403
   - Follow-up reminders: verify badge renders for authenticated user, anon → 403
4. Write outbox: `sessions/qa-forseti/outbox/<timestamp>-gate4-forseti-release-h.md`
   - Verdict: POST-RELEASE CLEAN or POST-RELEASE UNCLEAN
   - Include: audit run ID, spot-check results, any new items for Dev

## Acceptance criteria
- Outbox file exists with explicit CLEAN/UNCLEAN verdict
- 0 new regressions introduced by this release
- If UNCLEAN: itemize each failure with evidence (URL, expected vs actual HTTP status)

## Policy note
- If post-release is UNCLEAN: next release cycle is remediation-only (no new features) per org-wide policy
- PM escalates to Board if 3 unclean post-release audits in a row

## ROI
80 — Gate 4 is the final gate for release-h; confirms 4 job-hunter features are production-stable.
