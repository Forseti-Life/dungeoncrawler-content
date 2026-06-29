# Feature Brief: Decipher Writing, Identify Magic, Learn a Spell

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: ready
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-dc-rarity-spell-adjustment, dc-cr-spellcasting

## Goal

Implement three related skill actions — Decipher Writing, Identify Magic, and Learn a Spell — as a unified knowledge-acquisition subsystem covering mundane text comprehension, magical property identification, and permanent spell acquisition.

## Source reference

> "Identify Magic: Once you discover a spell or magic item, you can attempt a check to determine its properties; the DC is usually 15 + the spell or item's rank."

## Implementation hint

Implement a shared `KnowledgeAcquisitionService` with three distinct action handlers. `DecipherWritingAction` accepts a `TextEntity` (mundane or magical tag) and routes to the appropriate skill (Society for mundane, tradition-appropriate for magical). `IdentifyMagicAction` is a 10-minute activity resolving vs (15 + item/spell rank) using the tradition-appropriate skill; on success populate the `item.identified_properties` field. `LearnASpellAction` requires a successful Identify Magic first, then deducts `10 × spell_rank` gp and adds the spell to the character's repertoire or spellbook. These three actions share a DC calculation utility and should use consistent error messages.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; gold deduction for Learn a Spell is server-authoritative; identified properties exposed only after successful Identify Magic check.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Text/item/spell entity IDs must be valid; skill used must match the item/spell tradition; gold deduction validated against character wealth.
- PII/logging constraints: no PII logged; log character_id, action_type, target_id, dc, gp_spent, outcome; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1574, 1575, 1576, 1577, 1578, 1583, 1584, 1585, 1586, 1587, 1588, 1589, 1590
- See `runbooks/roadmap-audit.md` for audit process.
