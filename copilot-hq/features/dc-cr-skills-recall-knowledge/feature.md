# Feature Brief: Recall Knowledge Skill Action

- Work item id: dc-cr-skills-recall-knowledge
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260408-dungeoncrawler-release-f
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Occultism (Int), core/ch04/Religion (Wis)
- Depends on: dc-cr-skill-system, dc-cr-creature-identification, dc-cr-dc-rarity-spell-adjustment

## Goal

Implement the generic Recall Knowledge action resolving across all relevant skills (Arcana/Nature/Occultism/Religion/Society/Crafting/Lore) based on creature or item type, with degree-of-success outcome tables revealing traits, immunities, weaknesses, and abilities.

## Source reference

> "Recall Knowledge: You attempt to recall a bit of knowledge about the subject. The GM sets the DC, which is typically based on the subject's rarity and level; on a success you recall accurate information."

## Implementation hint

`RecallKnowledgeService` accepts a target entity (creature/item/location) and derives the applicable skill list from the target's `knowledge_category` field (e.g., dragon → Arcana, undead → Religion, construct → Crafting). The DC is computed from `BASE_DC_BY_LEVEL[target_level] + RARITY_ADJUSTMENT[target_rarity]`. On success, return a `KnowledgeReveal` object containing traits and a subset of abilities; on crit success return all abilities; on failure return false info or nothing; on crit failure return misleading info. Track per-character attempts to block re-tries until new information is discovered.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped read; knowledge reveals are server-authoritative and must not be client-controlled.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Target entity ID must be a valid creature, item, or location; skill used must be a valid skill for the target type; re-attempt blocking validated server-side.
- PII/logging constraints: no PII logged; log character_id, target_entity_id, skill_used, outcome; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1591, 1592, 1593, 1594, 2329
- See `runbooks/roadmap-audit.md` for audit process.
