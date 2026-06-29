# Feature Brief: Human Ancestry

- Work item id: dc-cr-human-ancestry
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-languages, dc-cr-ancestry-feat-schedule
- Source: PF2E Core Rulebook (Fourth Printing), core/ch01
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Created: 2026-04-07
- DB sections: (none — core/ch01 ancestry requirements not yet loaded into dc_requirements; see DB gap note below)

## DB Gap Note

`dc_requirements` has **zero rows** for `book_id='core'` and `chapter_key IN ('ch01','ch02')`. The human ancestry (and all other core ancestry/heritage requirements) were never loaded. Before this feature can be fully audited, a dev/PM task must load the core ch01–ch02 requirement rows into the DB.

**Action required:** Dispatch a DB-load task to populate `dc_requirements` with core/ch01 (Ancestries) and core/ch02 (Heritages) sections.

## Goal

Implement the Human ancestry stat block: HP 8, Speed 25 ft, Medium size, two free ability boosts, no ability flaw, languages Common plus one additional language of the player's choice plus additional languages equal to Intelligence modifier. Humans also receive an additional trained skill and an additional skill feat at 1st level. This is the most versatile core ancestry and the most commonly selected — it should be treated as the default path for new character creation testing.

## Source reference

> Human — Hit Points: 8. Size: Medium. Speed: 25 feet. Ability Boosts: Two Free. Ability Flaw: None. Languages: Common; one additional language of your choice; additional languages equal to Intelligence modifier (if positive) from any Common or Uncommon language in the setting. Traits: Human, Humanoid. Special: At 1st level gain one additional trained skill and one additional skill feat.

## Implementation hint

Create the human ancestry record using the same ancestry data model as dwarf/elf. Key differences: two free boosts (not fixed), no flaw, HP 8, Speed 25. The "additional skill + skill feat at 1st level" bonus requires a hook into the character leveling system — verify `dc-cr-character-leveling` already supports ancestry-granted skill training grants before dev begins. Human heritage variants (Skilled Heritage, Versatile Heritage) are tracked separately.

## Dependencies

- `dc-cr-ancestry-system` — base ancestry data model
- `dc-cr-heritage-system` — human heritages (Skilled, Versatile, etc.)
- `dc-cr-languages` — language selection at character creation
- `dc-cr-ancestry-feat-schedule` — additional skill feat grant at 1st level

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- All write endpoints (POST/PATCH ancestry/heritage assignment) require `_csrf_request_header_mode: TRUE`.
- All read endpoints (GET ancestry data) use `_csrf_token: FALSE`.
- Anonymous users receive 403 on all character write paths.
- Character data is scoped to the owning user's session; no cross-character data exposure.
