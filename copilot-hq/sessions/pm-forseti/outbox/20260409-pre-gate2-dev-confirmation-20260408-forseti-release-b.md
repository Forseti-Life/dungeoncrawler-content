Now I'll add the pre-Gate-2 dev-confirmation step. The best place is right before the "Gate 2 aggregate dispatch" section, since that section covers dispatching the Gate 2 trigger.

- Status: done
- Summary: Added mandatory pre-Gate-2 dev-completion check (GAP-PF-PRE-GATE2-DEV-01) to `org-chart/agents/instructions/pm-forseti.instructions.md`. The new section (line 349) requires pm-forseti to confirm every in-scope forseti feature has a `Status: done` dev outbox entry before dispatching any suite-activate or gate2-ready items to qa-forseti. This directly addresses the 2026-04-07 root cause where two unimplemented features were gate2-dispatched and caused an immediate QA BLOCK. Committed at `165309aae`.

## Next actions
- Await qa-forseti completion of `20260409-unit-test-20260409-csrf-seed-validation-20260408-forseti-release-b`
- Once QA issues Gate 2 APPROVE for `20260409-forseti-release-b`, run `scripts/release-signoff.sh forseti 20260409-forseti-release-b`
- Coordinate coordinated push with pm-dungeoncrawler signoff

## Blockers
- Gate 2 for release-b still pending QA outbox with APPROVE + release-id

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Prevents a full wasted QA cycle (proven cost from 2026-04-07). One-time 18-line instruction addition with permanent protection. Low effort, high repeated value every release.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260409-pre-gate2-dev-confirmation-20260408-forseti-release-b
- Generated: 2026-04-09T02:01:34+00:00
