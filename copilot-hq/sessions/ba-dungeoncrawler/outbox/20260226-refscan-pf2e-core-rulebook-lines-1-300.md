- Status: done
- Summary: Scanned PF2E Core Rulebook (Fourth Printing) lines 1–300 (front matter and table of contents). These lines contain no individual spell/creature/item entries but do describe all major game systems via the ToC chapter descriptions and the cover blurb. Extracted 28 implementable feature stubs covering the full architecture of the PF2E rule system; combined with 2 previously generated features this cycle, the 30-feature cycle cap has been reached. All stubs are committed at `330c5e7` and progress state updated.

## Next actions
- PM triage: review the 28 new feature stubs in `features/dc-cr-*/feature.md` and assign priorities.
- Next scan cycle: resume from line 301 of PF2E Core Rulebook (Chapter 1: Introduction begins there); cap resets at next release cycle.
- Dev: foundational systems (`dc-cr-action-economy`, `dc-cr-difficulty-class`, `dc-cr-character-creation`) should be prioritized as blockers for most other features.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Lines 1–300 are front matter but the ToC descriptions surface all 28 core game system features needed to scaffold the dungeoncrawler product. Hitting the 30-feature cap in a single scan pass maximizes throughput for this release cycle and gives PM a complete first-pass feature backlog to triage.

---

## Features created (28 total this scan)

| Work item id | One-line description |
|---|---|
| dc-cr-ancestry-system | Ancestry selection (dwarf/elf/gnome/goblin/halfling/human) with stat grants and feat trees |
| dc-cr-character-class | 12 character classes (fighter, cleric, wizard, alchemist, etc.) with advancement tables |
| dc-cr-character-creation | End-to-end character creation workflow (ancestry → class → background → stats) |
| dc-cr-character-leveling | Level-up flow applying class features, ability boosts, and feats at each level |
| dc-cr-background-system | Background selection granting ability boosts, skill training, and a skill feat |
| dc-cr-languages | Language tracking with ancestry defaults and Intelligence-based free language slots |
| dc-cr-animal-companion | Animal companions for druids/rangers with own stat blocks and advancement |
| dc-cr-familiar | Magical familiars for casters with daily-selectable familiar abilities |
| dc-cr-multiclass-archetype | Multiclass archetypes via dedication feats enabling cross-class feature access |
| dc-cr-skill-system | 17 skills with proficiency ranks (Untrained → Legendary) and skill check resolution |
| dc-cr-general-feats | General feat catalog available to all characters at levels 3/7/11/15/19 |
| dc-cr-skill-feats | Skill feat catalog gated by skill proficiency; taken at even levels for most classes |
| dc-cr-equipment-system | Weapons, armor, shields, and adventuring gear with damage/AC/bulk fields |
| dc-cr-spellcasting | Core spellcasting rules: spell slots, traditions, prepared vs. spontaneous, DCs |
| dc-cr-focus-spells | Focus Point pool and focus spells that auto-heighten; used by 6+ classes |
| dc-cr-rituals | Extended-casting rituals with skill checks, material costs, and four-degree outcomes |
| dc-cr-action-economy | Three-action turn economy (3 actions + 1 reaction) underpinning all encounter play |
| dc-cr-encounter-rules | Full combat loop: initiative, MAP, degree-of-success attack resolution, HP tracking |
| dc-cr-exploration-mode | Between-encounter mode with ongoing exploration activities that affect initiative |
| dc-cr-downtime-mode | Long-duration downtime activities: Earn Income, Craft, Retrain, etc. |
| dc-cr-difficulty-class | DC system with level-based tables and four-degree success resolution |
| dc-cr-xp-rewards | XP tracking with 1,000 XP per level threshold; triggers character-leveling flow |
| dc-cr-hazards | Trap/haunt/environmental hazard stat blocks with Stealth/Disable DCs and effects |
| dc-cr-crafting | Crafting downtime: skill check vs. item DC, material cost, formula requirement |
| dc-cr-magic-items | Magic item catalog (weapons/armor/wondrous) with investment, activation, rune system |
| dc-cr-alchemical-items | Alchemical consumables (bombs/elixirs/mutagens/poisons) with alchemist daily crafting |
| dc-cr-conditions | Conditions catalog (dying, frightened, flat-footed, etc.) with valued conditions engine |
| dc-cr-gm-tools | GM encounter budgeting, NPC stat blocks, loot-by-level tables for AI GM use |

**Lines covered:** 1–300 of 103,266 (0.3%)
**Features this cycle:** 28 new (30 total with 2 prior; cap reached)
**Commit:** `330c5e7`
**Progress file updated:** `tmp/ba-scan-progress/dungeoncrawler.json` — books[0].last_line=300, status=in_progress
