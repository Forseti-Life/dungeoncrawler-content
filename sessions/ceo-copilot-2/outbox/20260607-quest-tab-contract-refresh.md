# Outbox — Quest tab contract refresh + detail recovery

Date: 2026-06-07  
Seat: ceo-copilot-2  
Repo: `dungeoncrawler-content`

## Trigger
User reported reduced quest-detail visibility in the character-sheet quest tab and requested a contract/regression review.

## Findings
- Quest summary contract is still conformant server-side (`quest-summary-v2` payload contains objective guidance detail).
- Live quest rows still carry `next_step` + `completion_criteria` detail in `generated_objectives`/`objective_states`.
- UI detail loss path was stale/partial quest state in the character quest-tab flow (refresh timing + module cache line).

## Fix shipped
- Refresh canonical quest journal on:
  - top-level Character tab activation,
  - sidebar Quests tab activation (click + programmatic activation).
- Added explicit QuestPanel import cache-bust version in `GameShell`.
- Rolled `hexmap-v2` entry/library version to force module refresh.

## Code shipped
- Commit: `4cc20a1`
- Branch: `main`
- Push: completed to `origin/main`
