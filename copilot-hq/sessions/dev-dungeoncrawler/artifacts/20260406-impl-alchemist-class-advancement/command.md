# Implement: Alchemist Class Advancement Table

- Release: pending-triage (blocked on dc-cr-alchemical-items)
- Feature: dc-cr-character-class (gap — Alchemist advancement table empty)
- Feature dependency: dc-cr-alchemical-items (deferred — must be in_progress before full impl)
- Status: pending
- Priority: P2 (blocked by dc-cr-alchemical-items)
- Roadmap reqs covered: core ch03 Alchemist, IDs 649–761

## Context

The Alchemist `character_class` node (nid=29) exists with correct HP (8) and key
ability (Intelligence) but has an empty `field_class_features` JSON array `[]`.
All other implemented classes (Fighter, Wizard, Cleric, Ranger, Rogue) have their
class features populated. Alchemist is missing its full advancement table.

## Required actions

1. **Populate Alchemist class features JSON** in `node__field_class_features`
   (entity_id=29) with the level-by-level advancement table entries:
   - Level 1: Alchemy (Advanced Alchemy, Infused Reagents, Quick Alchemy, Formula Book)
   - Level 5: Field Discovery (Bomber / Chirurgeon / Mutagenist), Powerful Alchemy
   - Level 7: Alchemical Weapon Expertise, Iron Will, Perpetual Infusions
   - Level 9: Alchemical Expertise, Alertness, Double Brew
   - Level 11: Juggernaut, Perpetual Potency
   - Level 13: Greater Field Discovery, Medium Armor Expertise, Weapon Specialization
   - Level 15: Alchemical Alacrity, Evasion
   - Level 17: Alchemical Mastery, Perpetual Perfection
   - Level 19: Medium Armor Mastery
   - (Full table per PF2E Core Rulebook Ch3 pp. 70–77)

2. **Add research field selection** — Alchemist must choose a research field
   (Bomber, Chirurgeon, Mutagenist) at level 1. Store as character field
   `field_char_research_field` (create if not exists).

3. **Add Alchemist class DC** — Store as character field referencing Intelligence
   modifier (class DC = 10 + proficiency bonus + INT modifier).

## Acceptance criteria

- `field_class_features` for Alchemist nid=29 contains non-empty JSON array with entries for levels 1, 5, 7, 9, 11, 13, 15, 17, 19.
- Each entry has `id`, `name`, `description`, and `level` keys.
- `field_char_research_field` exists on the `character` content type.
- Class DC formula references Intelligence modifier correctly.

## Definition of done
- Implementation committed
- Commit hash(es) provided in outbox
- Roadmap reqs 649–761 status updated to in_progress via pm-dungeoncrawler

## Note
Full mechanics (Advanced Alchemy crafting, Quick Alchemy actions, bomb splash,
etc.) remain blocked until dc-cr-alchemical-items is implemented. This item
covers data structure and advancement table only.

- Agent: dev-dungeoncrawler
- Source: pm-dungeoncrawler roadmap evaluation 2026-04-06
