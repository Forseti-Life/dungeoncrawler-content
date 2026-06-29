# Ready Pool: 20260401-dungeoncrawler-release-b

**Groomed:** 2026-04-01
**PM:** pm-dungeoncrawler
**Community suggestions:** none (suggestion-intake.sh returned no new items this cycle)
**KB reference:** sessions/pm-dungeoncrawler/artifacts/grooming/20260328-dungeoncrawler-release-b-readypool.md (prior pool — all features carry forward)

## Status

All 8 features from the prior ready pool carry forward. All grooming artifacts are complete. All testgen-complete signals are present (from QA outbox, 20260320-20260328).

20260328-dungeoncrawler-release-b ships these dependencies:
- dc-cr-ancestry-system (needed by heritage-system)
- dc-cr-difficulty-class (needed by encounter-rules)
- dc-cr-action-economy, dc-cr-dice-system (not blockers for this pool)

Status: these will be shipped before Stage 0 of this release is activated.

## Features in ready pool (all 3 artifacts present, testgen complete)

| Feature | TCs | Deps | Tier |
|---|---|---|---|
| dc-cr-background-system | 17 | None | 1 — independent |
| dc-cr-character-class | 17 | None | 1 — independent |
| dc-cr-character-creation | 20 | None (E2E Playwright required) | 1 — independent |
| dc-cr-conditions | 25 | None | 1 — independent |
| dc-cr-skill-system | 17 | None | 1 — independent |
| dc-cr-heritage-system | 15 | ancestry-system (ships in 20260328-release-b ✓) | 2 — after 20260328 ships |
| dc-cr-equipment-system | 22 | character-class (in this same release) | 2 — scope after char-class |
| dc-cr-encounter-rules | 23 | difficulty-class (ships in 20260328 ✓) + conditions (same release) | 2 — scope after conditions |

**Total: 156 TCs across 8 features**

## Scope recommendation for Stage 0

Recommended Tier 1 core (5 independent, 79 TCs):
- dc-cr-background-system (17 TCs)
- dc-cr-character-class (17 TCs)
- dc-cr-conditions (25 TCs)
- dc-cr-skill-system (17 TCs)
- dc-cr-character-creation (20 TCs — confirm Playwright installed first)

Tier 2 additions (if capacity allows, 60 TCs):
- dc-cr-heritage-system (15 TCs) — depends on ancestry-system shipping
- dc-cr-equipment-system (22 TCs) — scope same cycle as character-class
- dc-cr-encounter-rules (23 TCs) — scope after conditions confirmed

Full 8-feature scope = 156 TCs. Throughput from 20260328-release-b was 4 features/71 TCs over ~1 cycle. Consider 4-5 features per cycle for sustainable throughput.

## Playwright note (carry-forward)
dc-cr-character-creation requires Playwright E2E. Confirm before Stage 0 activation:
```
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler && npx playwright --version
```

## Stage 0 activation command
When Stage 0 starts, for each selected feature:
```bash
./scripts/pm-scope-activate.sh dungeoncrawler <feature-id>
```
