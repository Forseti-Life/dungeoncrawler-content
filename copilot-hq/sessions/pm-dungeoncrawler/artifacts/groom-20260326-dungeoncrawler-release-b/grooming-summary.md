# Release Grooming Artifact — 20260326-dungeoncrawler-release-b

- Release ID: 20260326-dungeoncrawler-release-b
- Groomed by: pm-dungeoncrawler
- Date: 2026-03-26
- Current release (in execution): 20260322-dungeoncrawler-release-b

## Suggestion intake
- Script run: `scripts/suggestion-intake.sh dungeoncrawler`
- Result: 0 new community suggestions. Nothing to triage.

## Feature inventory

### Stage 0 eligible (fully groomed: status=ready, AC=Y, test-plan=Y)
| Feature | Status | AC | Test Plan | Notes |
|---------|--------|----|-----------|-------|
| dc-cr-clan-dagger | ready | Y | Y | Fully groomed. Stage 0 eligible immediately. |

### Partially groomed — pending QA testgen output (status=ready, AC=Y, test-plan=N)
| Feature | Status | AC | Test Plan | QA Testgen Item |
|---------|--------|----|-----------|-----------------|
| dc-cr-action-economy | ready | Y | N | 20260320-124457-testgen-dc-cr-action-economy (queued, no output) |
| dc-cr-ancestry-system | ready | Y | N | 20260320-124457-testgen-dc-cr-ancestry-system (queued, no output) |
| dc-cr-dice-system | ready | Y | N | 20260320-124457-testgen-dc-cr-dice-system (queued, no output) |
| dc-cr-difficulty-class | ready | Y | N | 20260320-124457-testgen-dc-cr-difficulty-class (queued, no output) |

Note: All 4 testgen items were queued 2026-03-20. Re-running pm-qa-handoff.sh would create duplicates — NOT done.
These will become Stage 0 eligible once QA testgen throughput is restored (CEO escalation GAP-DC-01 active).

### In progress (current release — not available for next release scoping)
- dc-cr-ancestry-traits, dc-cr-character-leveling, dc-cr-background-system, dc-cr-character-class,
  dc-cr-character-creation, dc-cr-conditions, dc-cr-encounter-rules, dc-cr-equipment-system,
  dc-cr-heritage-system, dc-cr-skill-system

### Deferred (ineligible — dependencies not met)
- dc-cr-dwarf-heritage-ancient-blooded: deferred; depends on dc-cr-heritage-system (in_progress) + dc-cr-dwarf-ancestry (deferred)
- 23 other deferred features — no change this cycle

## Grooming decision
- `dc-cr-clan-dagger` is the only feature fully groomed for Stage 0 selection into 20260326-dungeoncrawler-release-b.
- 4 additional `ready` features can enter Stage 0 once QA testgen delivers test plans (pending CEO throughput resolution).
- No new QA handoffs created (would be duplicates).

## Primary constraint
- QA testgen throughput (GAP-DC-01, CEO escalation since 2026-03-22): 12 items queued, 0 test plans returned. This is the ceiling on grooming completion. If resolved before Stage 0 start, 4 more features become eligible.
