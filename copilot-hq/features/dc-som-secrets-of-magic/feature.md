# Feature Brief: Secrets of Magic

- Work item id: dc-som-secrets-of-magic
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-p
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Secrets of Magic
- Depends on: dc-cr-spellcasting, dc-cr-class-wizard, dc-cr-class-sorcerer

## Goal

Implement Secrets of Magic content — Magus and Summoner classes, the Spellstrike action (melee + spell in one action), Eidolon companion system, and expanded spell traditions including the Cathartic/Rune/Soul magic. Covers 30 requirements across `som/ch01–ch05`.

## Source reference

> "Secrets of Magic introduces the magus and summoner classes and a wealth of new spells, items, and magic systems." (Secrets of Magic — Introduction)

## Implementation hint

**Magus class**: `class_key = 'magus'`, Hybrid Study subclass (Inexorable Iron/Laughing Shadow/Sparkling Targe/Starlit Span/Twisting Tree), Spellstrike (2-action: cast a spell with a Strike; spell is "invested" in the Strike, delivers on hit; reset via Recharge Spellstrike or specific actions), Arcane Cascade stance (bonus damage after Spellstrike). **Summoner class**: `class_key = 'summoner'`, Eidolon subclass (Angel/Demon/Dragon/Fey/Plant/Undead); Eidolon shares HP and actions with summoner — each gets 1 action from the shared 3-action pool; Act Together action; Eidolon stat block is character-scoped entity that advances with summoner level. **Spell system additions**: spell traditions expansion, new focus spells per class, cathartic magic (emotion-based heightening). DB sections are baseline/integration placeholders. Depends on `dc-cr-spellcasting`, `dc-cr-character-class`, `dc-cr-conditions`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Eidolon state is character-scoped, mutations require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/eidolon routes require `_csrf_request_header_mode: TRUE`
- Input validation: Hybrid Study and Eidolon subclass enums validated server-side; Spellstrike state (charged/uncharged) computed server-side
- PII/logging constraints: no PII logged; character id + eidolon id + action type only
