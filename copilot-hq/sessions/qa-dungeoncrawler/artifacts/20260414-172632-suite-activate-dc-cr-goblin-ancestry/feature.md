# Feature Brief: Goblin Ancestry

- Work item id: dc-cr-goblin-ancestry
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7084–7383
- Category: game-mechanic
- Release: (set by PM at activation)
- Created: 2026-04-10

## Goal

Goblin ancestry playable character option: 6 HP, Small size, 25-ft Speed, Dexterity and Charisma ability boosts, one Free boost, and Wisdom as the ability flaw. Goblins have a poor memory for long-term grudges, adapt quickly to new social environments, and align chaotic neutral/good. Heritages and ancestry feats (visible in lines 7383+) will extend this stub.

## Source reference

> Hit Points: 6. Size: Small. Speed: 25 feet. Ability Boosts: Dexterity, Charisma, Free. Ability Flaw: [Wisdom — cut off in source chunk; standard PF2e goblin flaw].

## Implementation hint

Ancestry data node (dungeoncrawler_content content type) with HP 6, size Small, Speed 25, boost_dex + boost_cha + boost_free, flaw_wis. Goblin heritages and feats arrive in subsequent scan chunks (7383+). Note: Wisdom flaw was not visible in the provided source chunk lines 7084-7383 — confirmed from standard PF2e rules.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: ancestry selection is character-scoped write only; ancestry assignment must be ownership-checked server-side.
- CSRF expectations: all POST/PATCH requests in character creation and ancestry-selection flows require `_csrf_request_header_mode: TRUE`.
- Input validation: ancestry boosts/flaw, size, speed, and ancestry traits are server-defined enum/data values; players cannot submit arbitrary ancestry stats.
- PII/logging constraints: no PII logged; log character_id, ancestry_id, heritage_id only.
