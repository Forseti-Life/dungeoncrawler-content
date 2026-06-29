# Feature Brief: Arcana — Borrow an Arcane Spell

- Work item id: dc-cr-skills-arcana-borrow-spell
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Arcana (Int)
- Depends on: dc-cr-skill-system, dc-cr-spellcasting

## Goal

Implement Arcana (Int) skill action handlers — Borrow an Arcane Spell, Decipher Writing, Identify Magic (arcane), Learn a Spell, and Recall Knowledge — supporting wizard spell acquisition workflows and arcane knowledge checks.

## Source reference

> "Borrow an Arcane Spell: If you're an arcane prepared caster, you can attempt to prepare a spell from another arcane caster's spellbook or scroll; the DC is 15 + the spell's rank."

## Implementation hint

`BorrowAnArcaneSpellAction` is a daily-prep-phase activity; validate that the character is an arcane prepared caster, then resolve Arcana vs (15 + spell_rank). On success, add the spell to available prepared slots for that day without adding it permanently to the spellbook. `LearnASpellAction` permanently adds a spell to the character's spellbook/repertoire; deduct `10 × spell_rank` gp in materials and require an Arcana check (DC = 15 + spell_rank); gold deduction is server-authoritative. `DecipherWritingArcana` is an exploration activity resolving vs the text's complexity DC, revealing the text content as a `RevealedContent` entity.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; arcane caster class type validated server-side before Borrow; gold deduction server-authoritative.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Spell ID must be from the arcane tradition list; spell rank must be ≤ character's max spell rank; source spellbook/scroll entity must be a valid accessible item.
- PII/logging constraints: no PII logged; log character_id, action_type, spell_id, dc_attempted, gp_spent; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1616, 1617, 1618
- See `runbooks/roadmap-audit.md` for audit process.
