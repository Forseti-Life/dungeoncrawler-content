# Feature Brief: First World Adept (Gnome Ancestry Feat 9)

- Work item id: dc-cr-first-world-adept
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6784–7083
- Category: game-mechanic
- Created: 2026-04-09

## Goal

Gnome Feat 9 (requires at least one primal innate spell) that significantly expands the gnome's fey magic repertoire. Grants faerie fire and invisibility as 2nd-level primal innate spells, each castable once per day. A major power spike for gnome builds that have invested in innate spellcasting (fey-touched or wellspring heritages, first-world-magic feat).

## Source reference

> Prerequisites at least one primal innate spell. Over time your fey magic has grown stronger. You gain faerie fire and invisibility as 2nd-level primal innate spells. You can cast each of these primal innate spells once per day.

## Implementation hint

Ancestry feat (level 9) that adds faerie fire (2nd level, 1/day) and invisibility (2nd level, 1/day) to the character's innate spell list with primal tradition. Requires the spellcasting system to support once-per-day innate spell resets and multi-spell grants from a single feat. Prerequisite check: character must have at least one primal innate spell already (from heritage or lower-level feat).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: ancestry feat assignment is character-scoped write only; prerequisite validation for an existing primal innate spell must be server-enforced.
- CSRF expectations: all POST/PATCH requests in feat-selection and spellcasting flows require `_csrf_request_header_mode: TRUE`.
- Input validation: only `faerie fire` and `invisibility` are granted; both are stored as 2nd-level primal innate spells with a once-per-day use counter managed server-side.
- PII/logging constraints: no PII logged; log character_id, feat_id, spell_ids_granted only.
