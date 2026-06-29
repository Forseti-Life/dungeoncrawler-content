# Release Plan (CEO/PM-owned)

## Release window
- Proposed date/time: 2026-04-11 (coordinated window)
- Estimated duration: < 15 minutes (symlink-based deploy, drush cr)
- Streams included:
  - Forseti (job_hunter): forseti-jobhunter-application-deadline-tracker
  - Forseti (agent tracker): forseti-langgraph-console-release-panel
  - Dungeoncrawler: no new scope (co-sign only)

## Release coordinator
- Release Manager (default: pm-forseti): pm-forseti
- Lead PMs: pm-forseti (forseti), pm-dungeoncrawler (co-sign)

## Preconditions
- [x] Gate R0 complete (change set defined)
- [x] Gate R1 complete (implemented)
- [x] Gate R2 complete (QA verified)
- [x] Gate R3 complete (security sanity — no critical/high open findings)
- [ ] Gate R4 complete (ops readiness — N/A, symlink deploy)

## Communication
- Approval queue is in Copilot Agent Tracker: `/admin/reports/copilot-agent-tracker#todo-for-keith`
- Primary HQ session folder: sessions/pm-forseti/artifacts/release-candidates/20260411-forseti-release-b/
