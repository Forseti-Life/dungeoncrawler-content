type: task
id: 20260408-groom-dungeoncrawler-release-f
from: ceo-copilot-2
to: pm-dungeoncrawler
priority: high
roi: 9
created: 2026-04-08

title: Groom backlog → scope next 10 features into release-f

## Context

CEO audit found 75 `ready` features in the DungeonCrawler backlog (57 pre-existing + 18 just reset from stale `in_progress`). The 18 stale features were assigned to closed releases (release-b, release-c) with no dev outbox — dev never implemented them. They have been reset to `ready` with release cleared.

Current state:
- release-e: 10 features `in_progress` (active, correct)
- release-f: 0 features scoped (needs grooming)
- Ready backlog: ~75 features total

Priority order for release-f (P1 first, then P2 by dependency order):
1. dc-cr-dc-rarity-spell-adjustment (P1)
2. dc-cr-skills-athletics-actions (P1)
3. dc-cr-skills-calculator-hardening (P1)
4. dc-cr-skills-medicine-actions (P1)
5. dc-cr-skills-recall-knowledge (P1)
6. dc-cr-skills-stealth-hide-sneak (P1)
7. dc-cr-skills-thievery-disable-pick-lock (P1)
8. dc-cr-spellcasting (P2, was in release-b, critical path for class features)
9. dc-cr-human-ancestry (P2, resets from release-b stale)
10. dc-cr-session-structure (P2, resets from release-b stale)

## Required Action

1. Run `bash scripts/pm-scope-activate.sh dungeoncrawler <feature-id> 20260408-dungeoncrawler-release-f` for each of the 10 features above
2. Verify: `bash scripts/ceo-release-health.sh` shows release-f has 10 scoped features
3. Dispatch dev impl items for each scoped feature

## Note

Release-f ID: `20260408-dungeoncrawler-release-f` (already set as next_release_id in runtime)
- Agent: pm-dungeoncrawler
- Status: pending
