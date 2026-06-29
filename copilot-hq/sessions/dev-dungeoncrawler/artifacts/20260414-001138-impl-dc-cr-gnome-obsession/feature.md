# Feature Brief: Gnome Obsession (Gnome Ancestry Feat 1)

- Work item id: dc-cr-gnome-obsession
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 20260412-dungeoncrawler-release-h
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6784–7083
- Category: game-mechanic
- Created: 2026-04-09

## Goal

Gnome ancestry feat that grants rapid scaling proficiency in a chosen Lore skill. At level 1, the character gains trained proficiency in any Lore; at 2nd they gain expert in that Lore AND the background Lore (if any); master at 7th; legendary at 15th. Provides a compelling skill-investment payoff for gnome knowledge-focused characters.

## Source reference

> You might have a flighty nature, but when a topic captures your attention, you dive into it headfirst. Pick a Lore skill. You gain the trained proficiency rank in that skill. At 2nd level, you gain expert proficiency in the chosen Lore as well as the Lore granted by your background, if any. At 7th level you gain master proficiency in these Lore skills, and at 15th level you gain legendary proficiency in them.

## Implementation hint

Ancestry feat that grants a free Lore skill selection at level 1 plus auto-advancement triggers at levels 2, 7, and 15. Requires the character-leveling and skills systems to support "flag this skill for auto-upgrade at milestone levels." Background Lore linkage requires reading background data at the time the feat is resolved.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: lore selection is character-scoped write at feat selection; proficiency progression is server-calculated on level-up.
- CSRF expectations: all POST/PATCH requests in feat selection and level-up flows require `_csrf_request_header_mode: TRUE`.
- Input validation: chosen Lore skill must be a valid Lore specialization; milestone upgrades apply only at levels 2, 7, and 15.
- PII/logging constraints: no PII logged; log character_id, chosen_lore, proficiency_rank_changes only.
