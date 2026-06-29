# CEO Outbox: Remove CEO from All Agent Escalation Targets (Second Pass)

**Status: done**
**Date:** 2026-04-05
**Commit:** 3de779a3

## Decision

CEO responsibility is now exclusively: **audit and fix agent profiles**.

All operational escalations previously targeting `ceo-copilot` now route to:
- `Board` — for cross-cutting environment/access blockers, systemic seat failures, high-risk ops decisions
- `PM supervisor` — for work-level escalations within a team's domain

## Files Updated (11)

| File | Change |
|---|---|
| `pm-infra.instructions.md` | 3 `ceo-copilot` escalation targets → `Board` |
| `agent-code-review.instructions.md` | `supervisor (ceo-copilot)` → `PM supervisor` |
| `dev-forseti.instructions.md` | executor SQL escalation → `PM` |
| `dev-dungeoncrawler.instructions.md` | systemctl escalation → `Board` |
| `ba-forseti.instructions.md` | 3rd-failure systemic escalation → `Board`; malformed-release fix → `PM/dev-infra` |
| `ba-forseti-agent-tracker.instructions.md` | HQ content review escalation → `Board` |
| `pm-forseti-agent-tracker.instructions.md` | 3 escalation targets → `Board`; stale-blocker dedup text updated |
| `pm-theoryofconspiracies.instructions.md` | blocker escalation → `Board` |
| `pm-stlouisintegration.instructions.md` | blocker escalation → `Board` |
| `pm-thetruthperspective.instructions.md` | blocker escalation → `Board` |
| `agent-explore-infra.instructions.md` | ghost-inbox-item fix owner → `Board` |

## Combined With Prior Pass (commit 918dd06d + 7325c2af)

- `release-signoff.sh` — `--empty-release` PM self-cert flag
- `runbooks/shipping-gates.md` — CEO removed from all gates
- `runbooks/release-cycle-process-flow.md` — CEO removed from Stages 1/2/8/9
- `org-chart/DECISION_OWNERSHIP_MATRIX.md` — CEO removed from all release gate rows
- `pm-dungeoncrawler.instructions.md` — empty-release bypass; drush/repo-path → Board
- `pm-forseti.instructions.md` — Gate R5 added as PM responsibility
- `ceo-copilot-2.instructions.md` — Gate R5 CEO-only note removed

## Rationale

The CEO's value is in cross-team coordination and profile quality. All agents that can
self-manage should; only true Board-level blockers should surface to the human.
