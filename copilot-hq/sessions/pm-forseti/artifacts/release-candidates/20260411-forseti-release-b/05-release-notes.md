# Release Notes (PM-owned, Dev + QA + Security inputs)

## Metadata
- Release id: 20260411-forseti-release-b
- Release candidate path: sessions/pm-forseti/artifacts/release-candidates/20260411-forseti-release-b/
- Release window: 2026-04-11 (coordinated)
- Release coordinator: pm-forseti
- Streams included: Forseti (job_hunter, forseti_agent_tracker); Dungeoncrawler (co-sign only)

## Summary
- Application Deadline Tracker shipped: job applications now support deadline and follow-up date fields with urgency indicators (overdue, due-soon, on-track) on the dashboard.
- LangGraph Console Release Panel wired to live state: panel now reads real release ID, started_at, feature count, and SLA status from `tmp/release-cycle-active/` instead of static placeholders.

## Change Log by Stream
### Forseti
- Feature: forseti-jobhunter-application-deadline-tracker
  - Work item: features/forseti-jobhunter-application-deadline-tracker/feature.md
  - Commit(s): 0f772acf0
  - Owner: dev-forseti

- Feature: forseti-langgraph-console-release-panel
  - Work item: features/forseti-langgraph-console-release-panel/feature.md
  - Commit(s): eb203f97f, c95346b3d
  - Owner: dev-forseti-agent-tracker

### Dungeoncrawler
- No new scope in this release window. pm-dungeoncrawler co-signed as coordinated PM.

## User-visible changes
- Job Hunter: deadline and follow-up date fields on job application forms; urgency badges on dashboard (overdue = red, due soon = amber, on-track = green).
- Agent Tracker LangGraph Console: Release Control Panel now shows live release data (release ID, elapsed time, feature count, SLA status).

## Admin / operational changes
- No DB schema migrations required.
- Run `drush cr` on forseti production after deploy (routing/template changes in both modules).
- No config export/import needed.

## Verification evidence
- QA report path(s): sessions/qa-forseti/outbox/20260411-gate2-approve-20260411-forseti-release-b.md
- Automated suite run(s): curl-automatable Gate 2 checks — PASS (commits 455b7cb16, c1af95b27)
- Post-release audit path(s) (fill after push): sessions/qa-forseti/outbox/20260411-post-release-forseti-release-b.md (pending)

## Risk / caveats
- Playwright TCs deferred to post-release Gate R5 (admin auth env vars unavailable in CI).
- No critical/high security findings shipped.

## Rollback
- Primary rollback steps: `git revert 0f772acf0 eb203f97f c95346b3d && git push origin main && drush cr`
- Rollback owner: pm-forseti / dev-forseti

## Known issues / follow-ups
- Playwright browser test suite for deadline-tracker TCs (TC-4b, TC-5–TC-10, TC-14) and release-panel TCs (TC-4–TC-10) to run when admin auth session env vars are available.
- Next release (release-c) will pick up any remaining ready features.

## Signoffs
- PM signoff status: pm-forseti APPROVED (sessions/pm-forseti/artifacts/release-signoffs/20260411-forseti-release-b.md); pm-dungeoncrawler CO-SIGNED (sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260411-forseti-release-b.md)
- QA signoff status: qa-forseti APPROVED (sessions/qa-forseti/outbox/20260411-gate2-approve-20260411-forseti-release-b.md)
- Security note status: no critical/high open findings; no security analyst escalation required
