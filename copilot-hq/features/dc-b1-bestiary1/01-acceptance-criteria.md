# Acceptance Criteria: dc-b1-bestiary1

## Gap analysis reference
- DB sections: b1/s01–s03 (Baseline Requirements + Integration Notes, 18 REQs)
- Depends on: dc-cr-encounter-rules ✓, dc-cr-npc-system ✓

---

## Happy Path

### Creature Content Type
- [ ] `[NEW]` A `creature` content type exists in `dungeoncrawler_content` with all required stat block fields.
- [ ] `[NEW]` Required fields: `level` (int), `rarity` (enum: common/uncommon/rare/unique), `traits[]` (term refs), `perception` (int), `languages[]` (term refs), `skills{}` (skill name → modifier map), `senses[]` (type + range), `ac` (int), `saves{fort, ref, will}` (ints), `hp` (int), `immunities[]`, `weaknesses[]` (name + value), `resistances[]` (name + value), `speeds{}` (land/fly/swim/burrow), `attacks[]`, `abilities[]`.
- [ ] `[NEW]` Each `attacks[]` entry encodes: `name`, `traits[]`, `damage_dice`, `damage_type`, `reach` (ft).
- [ ] `[NEW]` Each `abilities[]` entry encodes: `name`, `action_cost` (enum: free/reaction/1/2/3), `trigger`, `frequency`, `traits[]`, `save_dc` (int or null), `effect` (text).

### Encounter Filtering
- [ ] `[NEW]` Encounter tooling provides a filtering interface to select creatures by: level range, trait, and tactical role (brute/skirmisher/controller/support/spellcaster).
- [ ] `[NEW]` Tactical role is stored on each creature as an enum field.

### Data Import
- [ ] `[NEW]` An import pipeline loads creature data from the Bestiary 1 source file via `drush` import once the stat block schema is confirmed.
- [ ] `[NEW]` Import pipeline sanitizes all text fields against free-text rule injection.

### GM vs. Player Access
- [ ] `[NEW]` Creature library is read-only for players.
- [ ] `[NEW]` GM-only routes for creature mutation (import, override) require `_campaign_gm_access: TRUE`.

---

## Edge Cases
- [ ] `[NEW]` A creature may have multiple attacks and multiple abilities — the arrays must accept arbitrary length (no hardcoded max).
- [ ] `[NEW]` Speed zero values (e.g., no fly speed) are omitted from the speeds map, not stored as 0.

## Failure Modes
- [ ] `[TEST-ONLY]` Encounter filter by level range must be inclusive on both ends (e.g., levels 1–3 includes level 1, 2, and 3).
- [ ] `[TEST-ONLY]` Player-role users must receive 403 on any creature mutation route.

## Security acceptance criteria
- Authentication: player read-only; GM-only mutation routes require `_campaign_gm_access: TRUE`
- CSRF: all POST/PATCH creature import or override routes require `_csrf_request_header_mode: TRUE`
- Input validation: stat block fields validated against defined types and ranges; no free-text rule injection; import pipeline sanitizes all text fields
- PII/logging: no PII logged; creature id + encounter id + action type only in logs
