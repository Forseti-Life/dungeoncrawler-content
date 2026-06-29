# Acceptance Criteria — dc-cr-ancestry-traits

- Feature: Ancestry Traits System
- Release target: 20260308-dungeoncrawler-release-b
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-03-08

## Scope

Implement ancestry-granted creature traits (descriptors/tags) that attach to a character at character creation and govern how spells, effects, and abilities interact with them. This system is a prerequisite for correct spell and ability targeting logic across the whole game.

## Prerequisites satisfied

- dc-cr-ancestry-system: complete (ancestry selection grants the foundation for trait assignment)

## Knowledgebase check

None found for trait system specifically. Prior PM decision (commit `576262c5`) on ancestry traits case-sensitivity: trait matching is case-sensitive with canonical strings (e.g., `CharacterManager::ANCESTRIES`). Same contract applies to trait constants.

## Happy Path

- [ ] `[NEW]` Each ancestry in the catalog defines a set of creature traits (e.g., Dwarf ancestry → `["Dwarf", "Humanoid"]`; Human ancestry → `["Human", "Humanoid"]`).
- [ ] `[NEW]` When a character selects an ancestry at creation, their ancestry traits are automatically applied to the character entity (no additional player action required).
- [ ] `[NEW]` A character entity has a `traits[]` array that persists across save/reload.
- [ ] `[VERIFY]` Trait strings are canonical (exact-case match) and validated against a defined constant catalog before storage.
- [ ] `[NEW]` A check function `hasTraits(character_id, trait_list)` returns true if the character has all specified traits, false otherwise.
- [ ] `[NEW]` The API supports querying a character's current traits (read-only endpoint or field in character state response).

## Edge Cases

- [ ] `[NEW]` A character with a mixed-heritage ancestry (e.g., Half-Elf) receives the union of both parent ancestry traits.
- [ ] `[NEW]` Adding a duplicate trait (same canonical string already present) is idempotent — no duplication in the traits array.
- [ ] `[NEW]` An unknown/non-catalog trait string in a request is rejected with a clear error ("Unknown trait: X").
- [ ] `[VERIFY]` Trait comparison is case-sensitive; "humanoid" does not match "Humanoid".

## Failure Modes

- [ ] `[NEW]` Querying traits for a non-existent character returns a structured error.
- [ ] `[NEW]` Assigning an invalid trait (not in canonical catalog) is rejected at storage, not silently ignored.

## Permissions / Access Control

- [ ] Trait data is readable by session participants (needed for targeting logic display).
- [ ] Trait assignment is server-side only (character creation flow and heritage system); clients cannot directly mutate the traits array.

### Route permission expectations (required for qa-permissions.json)

| Route | HTTP method | Permission | anon | authenticated | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `/dungeoncrawler/traits` | `[GET]` | `access dungeoncrawler characters` | deny | allow | allow | allow | allow | allow |
| `/api/character/{id}/traits` | `[GET]` | `_character_access: TRUE` (own-character access check) | deny | allow (own) | allow (own) | allow | allow | allow |
| `/api/character/{id}/traits/check` | `[GET]` | `_character_access: TRUE` (own-character access check) | deny | allow (own) | allow (own) | allow | allow | allow |

Notes:
- `content_editor` inherits the `authenticated` role; since `access dungeoncrawler characters` is granted to `authenticated`, `content_editor` is `allow` on all read-only traits endpoints.
- `_character_access: TRUE` means the access check further restricts by character ownership; non-admin roles can only access their own character's traits.
- No CSRF token required — all traits routes are `[GET]` only.

## Gameplay-rule alignment

- PF2e trait system (Core Rulebook, Chapter 2): traits are descriptors that affect targeting and certain ability interactions. Traits have no inherent passive mechanical benefit; they are referenced by spells/abilities.
- Trait case-sensitivity contract: canonical strings match `CharacterManager::ANCESTRIES` pattern (Title Case, enforced).

## Test path guidance (for QA)

| Requirement | Test path |
|---|---|
| Trait assignment at creation | Create dwarf character; verify traits include "Dwarf" and "Humanoid" |
| Trait persistence | Create character; reload session; verify traits unchanged |
| hasTraits check | Call hasTraits(dwarf_char, ["Humanoid"]); verify true. Call hasTraits(dwarf_char, ["Elf"]); verify false |
| Unknown trait rejection | POST unknown trait to character; verify error response |
| Case sensitivity | Query hasTraits(char, ["humanoid"]); verify false (case mismatch) |
| Duplicate idempotency | Assign "Humanoid" twice; verify traits array has one "Humanoid" entry |
