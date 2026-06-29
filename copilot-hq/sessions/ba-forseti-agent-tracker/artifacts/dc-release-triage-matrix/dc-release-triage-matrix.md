# DC Release: BA Triage Coverage Matrix

**Seat:** ba-forseti-agent-tracker
**Generated:** 2026-02-26 | **Last updated:** 2026-02-27
**Release context:** 2026-02-26 dungeoncrawler release cycle

## Purpose

Single-glance view of all 28 dungeoncrawler features showing BA requirements coverage status. Use this to identify which features need BA work before Dev can start implementation.

## How to use
- **PM:** Use this to set triage priority and identify which features need BA requirements before scheduling for Dev.
- **Dev:** Do not start a feature until its BA status is `ac-ready` or PM has explicitly approved proceeding without ACs.
- **BA:** This is the source of truth for what needs requirements work this cycle.

## Coverage legend

| BA Status | Meaning |
|---|---|
| `no-artifact` | No BA requirements artifact exists — Dev cannot start |
| `draft-in-progress` | BA working on ACs — not ready for Dev |
| `ac-ready` | ACs exist and are testable — ready for PM triage then Dev |
| `awaiting-pm` | ACs written, pending PM finalization |

---

## Feature triage coverage

| Feature ID | Feature status | Priority | BA artifact path | BA status | Notes |
|---|---|---|---|---|---|
| dc-cr-action-economy | pre-triage | unset | — | `no-artifact` | Core gameplay loop — high dependency risk |
| dc-cr-alchemical-items | pre-triage | unset | — | `no-artifact` | — |
| dc-cr-ancestry-system | pre-triage | unset | — | `no-artifact` | Dependency: character-creation |
| dc-cr-animal-companion | pre-triage | unset | — | `no-artifact` | — |
| dc-cr-background-system | pre-triage | unset | — | `no-artifact` | Dependency: character-creation |
| dc-cr-character-class | pre-triage | unset | — | `no-artifact` | Dependency: character-creation |
| dc-cr-character-creation | pre-triage | unset | — | `no-artifact` | **Onboarding critical path** — blocks 5+ other features |
| dc-cr-character-leveling | pre-triage | unset | — | `no-artifact` | Dependency: character-creation, xp-rewards |
| dc-cr-conditions | pre-triage | unset | — | `no-artifact` | Dependency: encounter-rules |
| dc-cr-crafting | pre-triage | unset | — | `no-artifact` | — |
| dc-cr-difficulty-class | pre-triage | unset | — | `no-artifact` | Dependency: skill-system |
| dc-cr-downtime-mode | pre-triage | unset | — | `no-artifact` | — |
| dc-cr-encounter-rules | pre-triage | unset | — | `no-artifact` | Core gameplay — high dependency risk |
| dc-cr-equipment-system | pre-triage | unset | — | `no-artifact` | Dependency: character-creation |
| dc-cr-exploration-mode | pre-triage | unset | — | `no-artifact` | — |
| dc-cr-familiar | pre-triage | unset | — | `no-artifact` | — |
| dc-cr-focus-spells | pre-triage | unset | — | `no-artifact` | Dependency: spellcasting |
| dc-cr-general-feats | pre-triage | unset | — | `no-artifact` | Dependency: character-creation |
| dc-cr-gm-tools | pre-triage | unset | — | `no-artifact` | — |
| dc-cr-hazards | pre-triage | unset | — | `no-artifact` | Dependency: encounter-rules |
| dc-cr-languages | pre-triage | unset | — | `no-artifact` | Dependency: ancestry-system |
| dc-cr-magic-items | pre-triage | unset | — | `no-artifact` | Dependency: equipment-system |
| dc-cr-multiclass-archetype | pre-triage | unset | — | `no-artifact` | Dependency: character-class |
| dc-cr-rituals | pre-triage | unset | — | `no-artifact` | Dependency: spellcasting |
| dc-cr-skill-feats | pre-triage | unset | — | `no-artifact` | Dependency: skill-system |
| dc-cr-skill-system | pre-triage | unset | — | `no-artifact` | Core skill resolution — high dependency risk |
| dc-cr-spellcasting | pre-triage | unset | — | `no-artifact` | Core magic system — high dependency risk |
| dc-cr-xp-rewards | pre-triage | unset | — | `no-artifact` | Dependency: encounter-rules |
| dc-cr-dice-system | pre-triage | unset | — | `no-artifact` | **Core engine** — blocks all dice-resolution features |
| dc-cr-gm-narrative-engine | pre-triage | unset | — | `no-artifact` | Dependency: gm-tools |
| dc-cr-npc-system | pre-triage | unset | — | `no-artifact` | Dependency: encounter-rules, gm-tools |
| dc-cr-session-structure | pre-triage | unset | — | `no-artifact` | — |
| dc-cr-tactical-grid | pre-triage | unset | — | `no-artifact` | Dependency: encounter-rules, action-economy |

---

## Summary

| BA status | Count |
|---|---|
| `no-artifact` | 33 |
| `draft-in-progress` | 0 |
| `ac-ready` | 0 |
| `awaiting-pm` | 0 |
| **Total features** | **33** |

**BA coverage gap:** 33/33 features have no requirements artifact (5 new features added 2026-02-27: dice-system, gm-narrative-engine, npc-system, session-structure, tactical-grid). Dev cannot start any feature until PM sets priorities and BA produces ACs for the prioritized set.

## Recommended BA start order (dependency-first heuristic)

PM should triage and set priorities, but if BA is asked to start immediately, the dependency-first order is:

1. `dc-cr-dice-system` — blocks ALL dice-resolution features (new — highest dependency risk)
2. `dc-cr-character-creation` — blocks 5+ downstream features
3. `dc-cr-encounter-rules` — blocks conditions, hazards, xp-rewards, npc-system, tactical-grid
4. `dc-cr-skill-system` — blocks difficulty-class, skill-feats
5. `dc-cr-action-economy` — core gameplay loop; blocks tactical-grid
6. `dc-cr-spellcasting` — blocks focus-spells, rituals
7. `dc-cr-ancestry-system` → `dc-cr-background-system` → `dc-cr-character-class` (character-creation sub-components)
8. `dc-cr-gm-tools` → `dc-cr-gm-narrative-engine` → `dc-cr-npc-system` (GM toolchain)

**Verification command:** `grep -c "no-artifact" sessions/ba-forseti-agent-tracker/artifacts/dc-release-triage-matrix/dc-release-triage-matrix.md`

**Verification:** `cat sessions/ba-forseti-agent-tracker/artifacts/dc-release-triage-matrix/dc-release-triage-matrix.md | grep -c "|"` returns ≥ 37 (table rows).
