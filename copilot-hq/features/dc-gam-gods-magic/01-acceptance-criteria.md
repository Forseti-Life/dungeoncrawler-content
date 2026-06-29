# Acceptance Criteria: dc-gam-gods-magic

## Gap analysis reference
- DB sections: gam/s01–s06 (Baseline Requirements + Integration Notes, 36 REQs)
- Depends on: dc-cr-spellcasting ✓, dc-cr-class-cleric ✓, dc-cr-class-champion ✓

---

## Happy Path

### Deity Content Type
- [ ] `[NEW]` A `deity` content type exists in `dungeoncrawler_content` with all required fields.
- [ ] `[NEW]` Required fields: `name` (string), `alignment` (enum), `edicts[]` (text array), `anathema[]` (text array), `domains[]` (primary + alternate domain refs), `divine_font` (enum: heal/harm/both), `divine_skill` (skill ref), `favored_weapon` (equipment FK), `cleric_spells{}` (level 1–9 → spell mapping), `divine_ability[]` (enum: STR/DEX/CON/INT/WIS/CHA — two options per deity).

### Character Integration — Cleric
- [ ] `[NEW]` Cleric character sheet holds a deity FK; selected deity's domains, divine font, favored weapon, and cleric spells are automatically applied at character creation/level-up.
- [ ] `[NEW]` Divine font (heal/harm) determines which spell a Cleric can cast with their divine font slots.
- [ ] `[NEW]` Favored weapon grants trained proficiency to the Cleric at level 1 (if not already proficient).

### Character Integration — Champion
- [ ] `[NEW]` Champion character sheet holds a deity FK; selected deity's alignment and edicts/anathema are enforced as in-character constraints.
- [ ] `[NEW]` Champion domain selections at level-up are restricted to the deity's permitted domain list.

### Domain Feats
- [ ] `[NEW]` Domain feats in the feat catalog reference a domain tag; they are available to characters whose deity has that domain (primary or alternate).

### Channel Smite
- [ ] `[NEW]` Channel Smite is a Cleric action that expends a divine font spell slot to add the font's damage to a Strike.
- [ ] `[NEW]` Channel Smite damage type matches the divine font type: heal font → positive energy; harm font → negative energy.

### Holy Symbol
- [ ] `[NEW]` Holy Symbol exists as an equipment item with a `deity_affiliation` FK field.
- [ ] `[NEW]` A Cleric or Champion whose deity matches the symbol's affiliation gains the Implement of the Faith passive.

### Data Import
- [ ] `[NEW]` Deity catalog loaded via `drush` import once schema is defined.
- [ ] `[NEW]` Domain FK validated against allowed domain catalog entries at import time.

### Access Control
- [ ] `[NEW]` Deity catalog is read-only public data — accessible without authentication.
- [ ] `[NEW]` Character deity selection routes require character ownership (`_character_access: TRUE`).

---

## Edge Cases
- [ ] `[NEW]` Deity with `divine_font: both` allows the Cleric to choose either heal or harm at character creation (choice is fixed thereafter).
- [ ] `[NEW]` A Champion must select a deity with alignment compatible with their own alignment (NG/LG/CG for holy Champions, LE/NE/CE for unholy — enforced at character creation).

## Failure Modes
- [ ] `[TEST-ONLY]` Favored weapon proficiency is granted at level 1 only if the character is not already trained or better in that weapon.
- [ ] `[TEST-ONLY]` Domain FK validation rejects domains not on the deity's permitted domain list at feat selection.

## Security acceptance criteria
- Authentication: deity catalog read-only public; character deity selection requires `_character_access: TRUE`
- CSRF: POST/PATCH character deity-selection routes require `_csrf_request_header_mode: TRUE`
- Input validation: deity FK validated against allowed catalog; domain selections validated against deity's permitted domains
- PII/logging: no PII logged; character id + deity id + domain selection only in logs
