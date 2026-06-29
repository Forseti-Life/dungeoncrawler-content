# Ready Pool: 20260328-dungeoncrawler-release-b

**Groomed:** 2026-03-28  
**PM:** pm-dungeoncrawler  
**Community suggestions:** none (suggestion-intake.sh returned no new items)

## Features in ready pool (all 3 artifacts present)

| Feature | Test Cases | Notes |
|---|---|---|
| dc-cr-background-system | 17 | No blocking deps. Independent. |
| dc-cr-character-class | 17 | No blocking deps. Independent. |
| dc-cr-character-creation | 20 | Playwright E2E required — confirm install before dev delegation. |
| dc-cr-conditions | 25 | Highest complexity. No blocking deps (condition primitives are self-contained). |
| dc-cr-encounter-rules | 23 | Depends on dc-cr-difficulty-class (in release-b → ships before this cycle) AND dc-cr-conditions. Scope after conditions. |
| dc-cr-equipment-system | 22 | Depends on dc-cr-character-class. Must scope in same or later cycle as character-class. |
| dc-cr-heritage-system | 15 | Depends on dc-cr-ancestry-system (in release-b → ships before this cycle). Natural fit. |
| dc-cr-skill-system | 17 | No blocking deps. Independent. Note: untrained training-required penalty AC deferred (not concrete). |

**Total ready cases:** 156 test cases across 8 features.

## Dependency sequencing recommendation

If scoping all 7:
- Independent (safe to scope in any order): background-system, character-class, character-creation, conditions, skill-system
- After release-b ships: heritage-system (needs ancestry-system), encounter-rules (needs difficulty-class + conditions)
- After character-class ships: equipment-system

**Recommended scope for 20260328-dungeoncrawler-release-b (Stage 0 decision):**
- Tier 1 (independent): dc-cr-background-system, dc-cr-character-class, dc-cr-conditions, dc-cr-character-creation
- Tier 2 (if capacity): dc-cr-heritage-system, dc-cr-equipment-system, dc-cr-encounter-rules (after conditions in same cycle)

Note: full 7-feature scope = 139 test cases. If throughput is constrained, suggest 3-4 features (60-80 TCs) per cycle.

## Playwright note
dc-cr-character-creation requires Playwright E2E suite. Confirm dependency installed in dungeoncrawler dev environment before delegating to dev:
  `npx playwright --version` (from sites/dungeoncrawler or web root)
