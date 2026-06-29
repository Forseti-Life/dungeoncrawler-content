# Feature Brief: Creature Identification (Recall Knowledge Routing)

- Work item id: dc-cr-creature-identification
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Creature Identification
- Depends on: dc-cr-skill-system, dc-cr-dc-rarity-spell-adjustment

## Goal

Implement creature identification via Recall Knowledge — resolving checks against creature-level DCs and returning structured reveal data (name, traits, key abilities, immunities, weaknesses) across four success tiers.

## Source reference

> "You can attempt a Recall Knowledge check against a creature's DC to identify it; on a critical success you recall every ability, on a success you recall most, on a failure you recall nothing or wrong information."

## Implementation hint

`CreatureIdentificationService` wraps `RecallKnowledgeService` with a creature-specific outcome resolver: crit success returns a full `CreatureStatBlock` reveal (all abilities, immunities, weaknesses, resistances); success returns a partial reveal (name, traits, 1–2 abilities); failure returns nothing or a `MisleadingInfo` object; crit failure always returns misleading info. Track per-(character, creature) identification attempts in a `CreatureIdAttempt` table with a `revealed_info_level` field; block re-attempts at same level until new information triggers a reset. The DC is `BASE_DC_BY_LEVEL[creature_level] + RARITY_ADJUSTMENT[creature_rarity]`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped read; full stat block reveals server-authoritative; GM always sees full stat block regardless of roll.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Creature entity ID must be a valid encounter entity; skill used must be appropriate to the creature knowledge_category; re-attempt blocking enforced server-side.
- PII/logging constraints: no PII logged; log character_id, creature_id, skill_used, outcome, revealed_info_level; no PII logged.

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2329, 2331
- See `runbooks/roadmap-audit.md` for audit process.
