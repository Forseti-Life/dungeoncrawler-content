# Acceptance Criteria — dc-cr-elf-ancestry

- Feature: Elf Ancestry
- Release target: 20260406-dungeoncrawler-release-c
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-06

## Scope

Implement the Elf ancestry stat block in dungeoncrawler_content: HP 6, Speed 30, Dex+Int boosts, one free boost, Constitution flaw, Low-Light Vision, languages Common + Elven + Int-modifier additional languages from a defined list.

## Prerequisites satisfied

- dc-cr-ancestry-system: in_progress (release-next) — must ship before release-c activates
- dc-cr-heritage-system: in_progress (release-b) — must ship before release-c activates
- dc-cr-low-light-vision: planned (release-c, same cycle) — must be implemented first or in same release

## Knowledgebase check

None found for elf ancestry specifically. Pattern follows dwarf ancestry implementation.

## Happy Path

- [ ] `[NEW]` Elf ancestry record exists in the ancestry catalog: HP 6, Speed 30, Size Medium.
- [ ] `[NEW]` Elf grants two fixed ability boosts (Dexterity, Intelligence) + one free boost at character creation.
- [ ] `[NEW]` Elf applies a Constitution flaw (–2) at character creation.
- [ ] `[NEW]` Elf grants Low-Light Vision sense (`low_light_vision: true`).
- [ ] `[NEW]` Elf grants languages: Common, Elven. Additional languages (up to Intelligence modifier, if positive) selectable from: Celestial, Draconic, Gnoll, Gnomish, Goblin, Orcish, Sylvan.
- [ ] `[NEW]` Elf ancestry traits: `["Elf", "Humanoid"]` applied automatically at creation.
- [ ] `[NEW]` Character creation step allowing Elf selection produces a valid character with all of the above.

## Edge Cases

- [ ] `[NEW]` A character with 0 or negative Intelligence modifier gets no additional language selections (language slot count = max(0, Int mod)).
- [ ] `[NEW]` Selecting a language not on the permitted list is rejected with a clear error.
- [ ] `[NEW]` Selecting a language already granted (Common or Elven) as an additional language is rejected (no duplicates).
- [ ] `[NEW]` Free boost cannot be applied to Dexterity or Intelligence (already boosted) — error if attempted.
- [ ] `[TEST-ONLY]` Elf character persists correctly across save/reload (all boosts, flaw, traits, senses, languages).

## Failure Modes

- [ ] `[NEW]` Character creation with Elf ancestry and invalid language selection returns a validation error, not a 500.
- [ ] `[NEW]` Missing required fields (e.g., no free boost selection) blocks character save until resolved.

## Permissions / Access Control

- [ ] Elf ancestry selection available to any authenticated user during character creation.
- [ ] Ancestry data (stat block) is read-only from client; only the server applies it.
- [ ] Anonymous users cannot access character creation endpoints (403/redirect).

### Route permission expectations (required for qa-permissions.json)

| Route | HTTP method | Permission | anon | authenticated | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `/dungeoncrawler/character/create` | `GET` | `access dungeoncrawler characters` | deny | allow | allow | allow | allow | allow |
| `/dungeoncrawler/character/create` | `POST` | `access dungeoncrawler characters` | deny | allow | allow | allow | allow | allow |

## Security acceptance criteria

- [ ] POST `/dungeoncrawler/character/create` MUST have `_csrf_request_header_mode: TRUE` (same as dc-cr-character-creation).
- [ ] Ancestry selection POST is validated server-side; client-supplied ancestry ID is checked against the catalog before application.
- [ ] No elf ancestry data (boosts, flaw, traits) can be applied by anonymous users — 403 on all write paths.
- [ ] Additional language selection is validated server-side; arbitrary language strings from client are rejected.
