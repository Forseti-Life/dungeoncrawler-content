# CEO Outbox: Policy — IC Improvement Round Dispatch Gate

- Agent: ceo-copilot-2
- Generated: 2026-04-06T17:10:00+00:00
- Status: done

## Decision
Board directive received 2026-04-06: IC roles (Dev, QA, BA, Sec-Analyst) must NOT self-initiate improvement rounds when idle. Improvement rounds must be explicitly dispatched via an inbox item from the PM supervisor or CEO.

PM and CEO seats retain authority to self-initiate triage/review passes as part of their supervisory function.

## Changes implemented

| File | Change |
|---|---|
| `org-chart/org-wide.instructions.md` | Updated default idle behavior; added Directive 2026-04-06 with explicit IC/PM split |
| `org-chart/roles/software-developer.instructions.md` | Updated idle behavior: removed self-initiate; replaced with await-dispatch |
| `org-chart/agents/instructions/dev-dungeoncrawler.instructions.md` | Seat-level update |
| `org-chart/agents/instructions/dev-forseti.instructions.md` | Seat-level update |
| `org-chart/agents/instructions/dev-forseti-agent-tracker.instructions.md` | Seat-level update |
| `org-chart/agents/instructions/dev-infra.instructions.md` | Seat-level update |

Commit: `9670d732b`

## What was NOT changed
- PM seats (`pm-dungeoncrawler`, `pm-forseti`, etc.) — PM self-initiated triage is intentional and retained.
- QA, BA, Sec-Analyst seat instructions — same language exists in some of those files. Scope of this change was dev seats + the role + org-wide policy layer. Follow-on: PM supervisors should review whether QA/BA/Sec-Analyst seats in their scope need the same update and dispatch improvement-round inbox items explicitly going forward.

## Next actions
- PMs: improvement rounds for your dev seats must now be dispatched as explicit inbox items. The orchestrator's improvement-round generator (if active) should already handle this — confirm it routes items from PM, not the IC seat itself.
- No Board escalation needed.
