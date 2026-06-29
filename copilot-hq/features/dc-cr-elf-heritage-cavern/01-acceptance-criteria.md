# Acceptance Criteria — dc-cr-elf-heritage-cavern

- Feature: Cavern Elf Heritage
- Release target: 20260406-dungeoncrawler-release-c
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-06

## Scope

When an elf character selects the Cavern Elf heritage, replace their default Low-Light Vision with Darkvision. The two senses are mutually exclusive — Cavern Elf upgrades rather than adds.

## Prerequisites satisfied

- dc-cr-elf-ancestry: planned (release-c, same cycle) — must be implemented first or in same release
- dc-cr-heritage-system: in_progress (release-b) — must ship before release-c activates
- dc-cr-darkvision: ready — already implemented

## Knowledgebase check

None found. Pattern follows dc-cr-darkvision sense-flag assignment.

## Happy Path

- [ ] `[NEW]` Cavern Elf heritage record exists for the Elf ancestry.
- [ ] `[NEW]` When Cavern Elf is selected, character's `low_light_vision` flag is set to `false` and `darkvision` flag is set to `true`.
- [ ] `[NEW]` Sense replacement is applied at heritage selection time (character creation or heritage update).
- [ ] `[EXTEND]` Darkvision behavior is identical to what was implemented in dc-cr-darkvision (no new darkvision logic needed).
- [ ] `[NEW]` Selecting Cavern Elf heritage is only available to Elf-ancestry characters.

## Edge Cases

- [ ] `[NEW]` A non-Elf character cannot select Cavern Elf heritage (validation error).
- [ ] `[NEW]` Cavern Elf heritage cannot be combined with other elf heritages (one heritage per character per ancestry).
- [ ] `[TEST-ONLY]` After selecting Cavern Elf, character persists `darkvision: true, low_light_vision: false` across save/reload.
- [ ] `[NEW]` If Cavern Elf is removed (e.g., character rebuild), `darkvision` reverts to `false` and `low_light_vision` reverts to the elf default (`true`).

## Failure Modes

- [ ] `[NEW]` Heritage selection for invalid ancestry returns a validation error (not a 500).
- [ ] `[NEW]` Selecting a second heritage when one is already set is rejected.

## Permissions / Access Control

- [ ] Heritage selection available to authenticated users owning the character.
- [ ] Anonymous users cannot access heritage selection endpoints (403).

### Route permission expectations (required for qa-permissions.json)

| Route | HTTP method | Permission | anon | authenticated | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `/dungeoncrawler/character/{id}/heritage` | `POST` | `access dungeoncrawler characters` | deny | allow | allow | allow | allow | allow |

## Security acceptance criteria

- [ ] POST `/dungeoncrawler/character/{id}/heritage` MUST have `_csrf_request_header_mode: TRUE`.
- [ ] Heritage POST validates that the authenticated user owns the character (no cross-character heritage mutation).
- [ ] Heritage ID is validated server-side against the ancestry's permitted heritage list before application.
- [ ] Anonymous heritage POST returns 403.
