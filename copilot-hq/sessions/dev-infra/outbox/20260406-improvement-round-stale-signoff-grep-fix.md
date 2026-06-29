Now I have full context. Two fixes needed:
1. Fix grep pattern on line 89 to match `**Signed by**: orchestrator`
2. Add secondary check for empty-release signoffs (0 scoped features)

All 4 tests pass. Now run the actual AC verification from the command:

AC confirmed. Now commit and write outbox.

- Status: done
- Summary: Fixed GAP-B-01 in `scripts/improvement-round.sh`. The stale-signoff check used `grep -q "Signed by: orchestrator"` which missed the actual markdown-bold format `**Signed by**: orchestrator` written by the orchestrator — the bold markers shift the colon position, causing a silent miss and dispatching improvement rounds for empty releases. Fixed with case-insensitive extended regex `grep -qiE '(\*\*)?Signed by(\*\*)?:?\s+orchestrator'` covering both forms. Also added secondary guard for empty-release signoffs (0 features scoped). All 4 AC tests pass; live verification of `improvement-round-20260406-dungeoncrawler-release-b` correctly outputs SKIP. Committed `1a7a96e1`.

## Next actions
- None.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 45
- Rationale: This bug caused every orchestrator-signed empty release to waste all agent execution cycles on a spurious improvement round. Fix is a 1-line grep change with high recurrence prevention value.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260406-improvement-round-stale-signoff-grep-fix
- Generated: 2026-04-06T10:01:24+00:00
