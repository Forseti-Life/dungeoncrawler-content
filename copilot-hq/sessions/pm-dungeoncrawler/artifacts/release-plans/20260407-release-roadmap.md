# DungeonCrawler Release Roadmap
- Date: 2026-04-07
- Author: ceo-copilot-2
- Status: historical snapshot

## Runtime release sync (2026-04-12)

This file is the original 2026-04-07 planning snapshot. The live release state has advanced and should be read as:

| Runtime pointer | Value | Progress |
|---|---|---|
| Active release | `20260412-dungeoncrawler-release-d` | 8 release deliverables are currently `in_progress`: `dc-cr-downtime-mode`, `dc-cr-feats-ch05`, `dc-cr-gnome-heritage-sensate`, `dc-cr-gnome-heritage-umbral`, `dc-cr-hazards`, `dc-cr-magic-ch11`, `dc-cr-rest-watch-starvation`, `dc-cr-skills-society-create-forgery` |
| Carry-over in progress | `20260411-coordinated-release-next` | `dc-cr-gnome-heritage-chameleon` remains `in_progress` as stale carry-over and is not a release-d deliverable |
| Most recently closed release | `20260412-dungeoncrawler-release-b` | Closed empty; 10 scoped features were deferred back to `ready` because no QA APPROVE evidence existed |
| Runtime next release pointer | `20260412-dungeoncrawler-release-b` | Current value from `tmp/release-cycle-active/dungeoncrawler.next_release_id` |

**Live source of truth:** `tmp/release-cycle-active/dungeoncrawler.release_id`, `tmp/release-cycle-active/dungeoncrawler.next_release_id`, `sessions/pm-dungeoncrawler/artifacts/releases/20260412-dungeoncrawler-release-d/01-change-list.md`, and `dashboards/FEATURE_PROGRESS.md`.

---

## Release Cycle Overview

| Release | Status | Theme | Feature Count |
|---|---|---|---|
| 20260406-dungeoncrawler-release-b | **in-flight** | Core Mechanics Depth | 3 |
| 20260407-dungeoncrawler-release-c | planned | Ancestry Chain + Leveling | 9 |
| 20260407-dungeoncrawler-release-d | planned | First Playable Classes | 6 |
| 20260407-dungeoncrawler-release-e | planned | Skill Actions Depth | 7 |
| 20260407-dungeoncrawler-release-f | planned | Spellcasting Foundation | TBD |

---

## Release B (in-flight: `20260406-dungeoncrawler-release-b`)
**Theme:** Core Mechanics Depth — closes out the final P0/P1 foundational items

| Feature | Priority | Notes |
|---|---|---|
| dc-cr-conditions | P1 | ConditionManager partial impl |
| dc-cr-difficulty-class | P0 | CombatCalculator DC tables |
| dc-cr-equipment-system | P1 | InventoryManagementService partial impl |

**Unlocks on close:** dc-cr-class-barbarian, dc-cr-class-alchemist, dc-cr-skills-deception-actions, dc-cr-skills-medicine-actions, dc-cr-equipment-ch06, dc-cr-rest-watch-starvation

---

## Release C (`20260407-dungeoncrawler-release-c`)
**Theme:** Ancestry Chain Completion + Character Leveling
**Trigger:** Release B closes

| Feature | Priority | Deps satisfied? |
|---|---|---|
| dc-cr-languages | P2 | ✅ |
| dc-cr-low-light-vision | P2 | ✅ |
| dc-cr-darkvision | P2 | ✅ |
| dc-cr-elf-ancestry | P2 | after languages + low-light-vision |
| dc-cr-elf-heritage-cavern | P2 | after elf-ancestry + darkvision |
| dc-cr-ancestry-traits | P2 | ✅ |
| dc-cr-character-leveling | P3 | ✅ |
| dc-cr-dwarf-heritage-ancient-blooded | P3 | ✅ (heritage-system done) |
| dc-home-suggestion-notice | P2 | ✅ |

**Activation order:** languages → low-light-vision → darkvision → elf-ancestry → elf-heritage-cavern → ancestry-traits → character-leveling → dwarf-heritage-ancient-blooded → suggestion-notice

**Unlocks on close:** dc-cr-skills-calculator-hardening, dc-apg-class-swashbuckler, dc-apg-class-investigator, future dwarf heritage chain

---

## Release D (`20260407-dungeoncrawler-release-d`)
**Theme:** First Playable Classes
**Trigger:** Release C closes (character-leveling done) + Release B closed (conditions done)

| Feature | Priority | Deps satisfied? |
|---|---|---|
| dc-cr-class-fighter | P1 | ✅ ready now |
| dc-cr-class-rogue | P1 | ✅ ready now |
| dc-cr-class-ranger | P2 | ✅ ready now |
| dc-cr-class-barbarian | P1 | after Release B (conditions) |
| dc-cr-hazards | P1 | ✅ ready now |
| dc-cr-encounter-creature-xp-table | P1 | ✅ ready now |

**Note:** dc-cr-class-fighter is the P1 priority — if scope must be reduced, keep fighter + rogue + hazards as minimum viable scope.

**Unlocks on close:** dc-cr-xp-award-system (needs character-leveling ✓ + xp-table ✓), dc-cr-skills-thievery-disable-pick-lock (needs hazards)

---

## Release E (`20260407-dungeoncrawler-release-e`)
**Theme:** Skill Actions Depth
**Trigger:** Release C closes (character-leveling, calculator-hardening after that)

| Feature | Priority | Deps satisfied? |
|---|---|---|
| dc-cr-skills-calculator-hardening | P1 | after Release C (character-leveling) |
| dc-cr-skills-athletics-actions | P1 | after calculator-hardening + conditions ✓ |
| dc-cr-skills-stealth-hide-sneak | P1 | after calculator-hardening |
| dc-cr-skills-acrobatics-actions | P2 | after calculator-hardening |
| dc-cr-skills-medicine-actions | P1 | after Release B (conditions) |
| dc-cr-environment-terrain | P2 | ✅ ready now |
| dc-cr-skills-deception-actions | P2 | after Release B (conditions) |

---

## Release F (`20260407-dungeoncrawler-release-f`) — Forward Look
**Theme:** Spellcasting Foundation
**Requires:** dc-cr-spellcasting (currently deferred — must be un-deferred and implemented first)

Unlocks: dc-cr-class-cleric, dc-cr-class-wizard, dc-cr-class-sorcerer, dc-cr-class-bard, dc-cr-spells-ch07, dc-apg-spells

**Board decision required before scoping:** Spellcasting is a major architectural investment. Recommend PM-dungeoncrawler produce a scope estimate before Release E closes.

---

## Deferred Backlog (out of scope until explicitly un-deferred)
- All bestiary content (b1/b2/b3)
- gam (Gods and Magic), gng (Guns and Gears), som (Secrets of Magic)
- dc-cr-downtime-mode, dc-cr-exploration-mode, dc-cr-gm-tools, dc-cr-gm-narrative-engine
- Dwarven weapon feats, full dwarf heritage chain (rock, forge, death-warden, strong-blooded)
- APG class expansions, APG Oracle, Witch (all require spellcasting)
- dc-cr-multiclass-archetype, dc-cr-familiar, dc-cr-animal-companion

---

## Blocked
| Feature | Blocked by |
|---|---|
| dc-cr-human-ancestry | dev task `20260407-load-core-ancestry-requirements` (DB gap in core/ch01) |

---

## Dependency Graph (simplified)
```
dice ✓ → DC (in-flight) → encounter-rules ✓ → class-fighter (ready)
                        → hazards (ready)
                        → encounter-xp-table (ready)

ancestry ✓ + heritage ✓ + clan-dagger ✓ → [dwarf-ancestry — was deferred, see note below]
                                        → elf-ancestry (after languages + low-light)

skill-system ✓ → skills-calculator-hardening (after leveling)
              → skills-performance (ready) / society (ready)
              → [skills-athletics, stealth, acrobatics — after calculator-hardening]

character-class ✓ + creation ✓ → class-fighter ✓ / class-rogue ✓ / class-ranger ✓
                               → class-barbarian (after conditions close)
                               → class-alchemist (after equipment close)

spellcasting (deferred) → 5 spellcasting classes (blocked)
```

**Note on dc-cr-dwarf-ancestry:** This feature is marked `deferred` but all its deps are now satisfied (ancestry-system ✓, heritage-system ✓, clan-dagger shipped ✓). Recommend PM promote to `ready` and slot into Release D alongside the other core ancestries.
