# Feature Brief: DC Rarity and Spell-Level Adjustments

- Website: dungeoncrawler
- Type: extend
- Module: dungeoncrawler_content
- Priority: P1
- Status: ready
- Release: none
- Dependencies: dc-cr-encounter-rules, dc-cr-spellcasting

## Goal

Implement DC rarity and spell-rank adjustment modifiers — applying Common/Uncommon/Rare/Unique and spell-rank-above-caster adjustments on top of the base DC-by-level table — as a centralized DC calculation utility consumed by Identify Magic, Recall Knowledge, and other skill actions.

## Source reference

> "When setting a DC, apply a rarity adjustment: Common +0, Uncommon +2, Rare +5, Unique +10; and for spells, add +2 for each rank above the standard level."

## Implementation hint

Implement `DcAdjustmentService.compute(base_dc, rarity, spell_rank_delta)` as a pure function: `base_dc + RARITY_ADJUSTMENT[rarity] + max(0, spell_rank_delta × 2)`. The `RARITY_ADJUSTMENT` map is: Common=0, Uncommon=2, Rare=5, Unique=10. Integrate this service into `RecallKnowledgeService`, `IdentifyMagicService`, and `LearnASpellService` as a dependency; remove any inline DC calculations in those services. Add a unit test fixture for each rarity tier and for each rank delta to ensure the service is consistently applied.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: DC calculations are server-side only; no client-submitted DC values accepted.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Rarity must be a valid rarity enum; spell_rank_delta must be a non-negative integer; base_dc must be a positive integer from the level DC table.
- PII/logging constraints: no PII logged; log action_type, entity_id, computed_dc, rarity, rank_delta; no PII logged; no character_id needed as this is a pure utility.

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2320, 2321, 2322, 2328
- See `runbooks/roadmap-audit.md` for audit process.
