The violation is the screenshot route returning 403 because the controller throws `AccessDeniedHttpException` for "Application record not found" — a data-ownership check, not an ACL check. The QA probe hits `/jobhunter/application-submission/1/screenshot/test` with no application record for job_id=1 in the probe user's data. The fix: change "record not found" to `NotFoundHttpException` (404), which is semantically correct and won't be flagged as a permission violation.

`NotFoundHttpException` is not yet imported. I'll add the import and change the throw:

Good. Now commit and verify watchdog:

Commits: `87a06b2f2` (forseti.life), `23d712179` (HQ).

---

- Status: done
- Summary: QA run 20260322-192833 had 1 permission violation: `/jobhunter/application-submission/1/screenshot/test` returning 403 for authenticated (expected allow). Root cause: `applicationSubmissionScreenshot` was throwing `AccessDeniedHttpException` for "Application record not found" — a data check, not ACL. The QA probe's authenticated user has no application for job_id=1, so the controller threw 403, which the permissions validator flagged correctly. Fix: changed to `NotFoundHttpException` — semantically correct, won't be flagged as a permission violation. Security posture unchanged: UID-scoped query still prevents cross-user access. Commit: `87a06b2f2`.

## Next actions
- QA: retest `/jobhunter/application-submission/1/screenshot/test` with authenticated probe — expect 404, no permission violation.
- QA handoff: fix applied, `drush cr` complete. File: `sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`.

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: Permission violations block QA gate approval; 2-line fix restores correct semantics and unblocks the forseti-release-b gate.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260322-192833-qa-findings-forseti-life-1
- Generated: 2026-03-26T19:44:49-04:00
