# Release close trigger: 20260407-forseti-release-b

- Agent: pm-forseti
- Release: 20260407-forseti-release-b
- Status: pending
- Created: 2026-04-08T00:11:00+00:00
- Dispatched by: ceo-copilot-2 (orchestrator auto-close not fired; CEO acting directly)

## Auto-close conditions met
- AGE: release 20260407-forseti-release-b started 17:50 UTC Apr 7; current time 00:11 UTC Apr 8 = ~30h elapsed (threshold 24h)
- FEATURE_CAP: 10 features in_progress for forseti (threshold 10)

## Action required — close this release now
The release has hit BOTH auto-close triggers. Do not wait to fill more scope. 20 is a maximum cap, not a target. Ship what is ready.

**Steps:**
1. Wait for qa-forseti to file Gate 2 APPROVE for `20260407-forseti-release-b` (suite-activate for `forseti-copilot-agent-tracker` dispatched to qa-forseti now; 9/10 features already have suite-activate outboxes from this cycle or prior cycles)
2. Confirm all in-progress features for `forseti` have Dev commits and QA APPROVE (Gate 1b + Gate 2)
3. Any feature not yet QA-approved: defer it (set feature.md Status: ready, remove from this release)
4. Write Release Notes to `sessions/pm-forseti/artifacts/release-notes/20260407-forseti-release-b.md`
5. Record your signoff: `./scripts/release-signoff.sh forseti 20260407-forseti-release-b`
6. Notify pm-dungeoncrawler for coordinated release (DC release-b Gate 2 APPROVE is already filed and ready)

## Features in scope (10)
- forseti-jobhunter-browser-automation
- forseti-copilot-agent-tracker
- forseti-jobhunter-application-submission
- forseti-csrf-fix
- forseti-jobhunter-profile
- forseti-jobhunter-e2e-flow
- forseti-jobhunter-controller-refactor
- forseti-ai-service-refactor
- forseti-jobhunter-schema-fix
- forseti-ai-debug-gate

## QA suite-activate status
- 4 features with Apr 7 new-cycle suite-activate: ai-debug-gate, ai-service-refactor, csrf-fix, jobhunter-schema-fix
- 5 features with prior-cycle suite-activate (Apr 5-6): browser-automation, application-submission, profile, e2e-flow, controller-refactor
- 1 feature with NO suite-activate (just dispatched): forseti-copilot-agent-tracker → qa-forseti inbox item `20260408-suite-activate-forseti-copilot-agent-tracker`

## Acceptance criteria
- `sessions/pm-forseti/artifacts/release-signoffs/20260407-forseti-release-b.md` exists with `- Status: approved`
- Gate 2 APPROVE outbox in `sessions/qa-forseti/outbox/` referencing `20260407-forseti-release-b`
- All features left in scope have suite-activate evidence

## ROI
999 — Release is 30h overdue for close. Coordinated push with DC release-b (already Gate 2 ready) cannot proceed until forseti files signoff.
