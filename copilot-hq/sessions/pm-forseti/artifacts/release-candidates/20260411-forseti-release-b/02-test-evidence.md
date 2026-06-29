# Test Evidence (QA-owned)

## Summary
- Overall QA status: APPROVE

## Evidence
### Forseti
- Central test-case location: qa-suites/products/forseti/suite.json
- Default SoT: `qa-suites/products/forseti/suite.json`
- Automated suites run: curl-automatable endpoint checks (Gate 2 via qa-forseti)
- Result: PASS
- Evidence: sessions/qa-forseti/outbox/20260411-gate2-approve-20260411-forseti-release-b.md (commits 455b7cb16, c1af95b27)
- Notes / known gaps:
  - forseti-jobhunter-application-deadline-tracker: anon-403 on deadlines/job routes, 404 on non-integer job_id, ownership guard, date validation, blank→NULL, urgency CSS classes confirmed, boundary condition (today = amber "Due today") confirmed. Dev commit: 0f772acf0.
  - forseti-langgraph-console-release-panel: anon-403 live-verified, max-age: 60 cache header confirmed, is_readable() null-guards confirmed, graceful "No active release" fallback confirmed, no hardcoded paths. Dev commits: eb203f97f, c95346b3d.
  - Site audit: 0 violations, 0 config drift.
  - Playwright TCs (TC-4b, TC-5–TC-10, TC-14 for deadline-tracker; TC-4–TC-10 for release-panel) staged for post-release verification when admin auth session env vars are available.

#### Regression cycles (SoT)
- Cycle 1 (full regression): PASS

### Dungeoncrawler
- Not in scope for this release (co-sign only).

## Known gaps
- Playwright/browser TCs deferred to post-release Gate R5 (admin auth env vars not available in CI context).
