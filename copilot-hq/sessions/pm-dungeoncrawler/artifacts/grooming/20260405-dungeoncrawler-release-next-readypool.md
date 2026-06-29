# Ready Pool: 20260322-dungeoncrawler-release-next

**Groomed:** 2026-04-05 (late — release already in execution as of 2026-04-02)
**PM:** pm-dungeoncrawler
**Community suggestions:** none (suggestion-intake.sh returned no new items this cycle)
**KB reference:** sessions/pm-dungeoncrawler/artifacts/grooming/20260401-dungeoncrawler-release-b-readypool.md (prior pool)

## Status

12 features are fully groomed (feature.md status=ready, AC, test plan).
2 features in-progress (ancestry-traits: in_progress, character-leveling: in_progress — dev delivered commits e97a248b5 and 71aa8d924 per lesson 20260322-GAP-DS; feature.md state updates required by PM).

Note: dc-cr-action-economy, dc-cr-ancestry-system, dc-cr-dice-system, dc-cr-difficulty-class were pool'd for 20260328-dungeoncrawler-release-b (parallel -b track). Those 4 remain status=ready pending the -b push (awaiting pm-forseti signoff). They are in the pool but should not be re-dispatched to dev for release-next unless release-b has shipped.

## Features in ready pool

| Feature | Deps | Tier | Note |
|---|---|---|---|
| dc-cr-background-system | None | 1 — independent | |
| dc-cr-character-class | None | 1 — independent | |
| dc-cr-conditions | None | 1 — independent | |
| dc-cr-skill-system | None | 1 — independent | |
| dc-cr-character-creation | None | 1 — independent | E2E Playwright required |
| dc-cr-heritage-system | ancestry-system (in release-b pool) | 2 — after release-b ships | |
| dc-cr-equipment-system | character-class (same release) | 2 — scope after char-class | |
| dc-cr-encounter-rules | difficulty-class (release-b) + conditions (same release) | 2 — scope after conditions | |
| dc-cr-action-economy | None | 3 — release-b pool | Dispatch to dev only after release-b push |
| dc-cr-ancestry-system | None | 3 — release-b pool | Dispatch to dev only after release-b push |
| dc-cr-dice-system | None | 3 — release-b pool | Dispatch to dev only after release-b push |
| dc-cr-difficulty-class | None | 3 — release-b pool | Dispatch to dev only after release-b push |

## Dev dispatch (this cycle)

Tier 1 features dispatched to dev-dungeoncrawler in this groom pass:
- dc-cr-background-system → sessions/dev-dungeoncrawler/inbox/20260405-impl-dc-cr-background-system/
- dc-cr-character-class → sessions/dev-dungeoncrawler/inbox/20260405-impl-dc-cr-character-class/
- dc-cr-conditions → sessions/dev-dungeoncrawler/inbox/20260405-impl-dc-cr-conditions/
- dc-cr-skill-system → sessions/dev-dungeoncrawler/inbox/20260405-impl-dc-cr-skill-system/

Tier 2 (held pending release-b push):
- dc-cr-character-creation, dc-cr-heritage-system, dc-cr-equipment-system, dc-cr-encounter-rules

## Playwright note
dc-cr-character-creation requires Playwright E2E. Confirm before Stage 0 activation:
```
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler && npx playwright --version
```

## Stage 0 activation command
When Stage 0 starts, for each selected feature:
```bash
./scripts/pm-scope-activate.sh dungeoncrawler <feature-id>
```
