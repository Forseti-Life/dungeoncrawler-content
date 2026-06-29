# Acceptance Criteria — dc-cr-low-light-vision

- Feature: Low-Light Vision Rule System
- Release target: 20260406-dungeoncrawler-release-c
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-06

## Scope

Implement Low-Light Vision as a shared sense flag on the character entity. Characters with this sense treat dim light as bright light for concealment checks. This is the elf default vision, analogous to darkvision for dwarves.

## Prerequisites satisfied

- dc-cr-darkvision: complete or in-progress (same sense-flag pattern; reuse the implementation contract)

## Knowledgebase check

None found specifically for low-light-vision. Pattern follows dc-cr-darkvision implementation.

## Happy Path

- [ ] `[NEW]` Character entity supports a `low_light_vision` boolean sense flag (stored alongside `darkvision`).
- [ ] `[NEW]` When `low_light_vision: true`, dim-light zones are treated as bright light for concealment resolution (the concealed condition from dim light does not apply).
- [ ] `[NEW]` Ancestry records (Elf and future ancestries) can reference `low_light_vision` as a granted sense.
- [ ] `[NEW]` Sense flag persists across save/reload.
- [ ] `[NEW]` A character can have `darkvision: true` OR `low_light_vision: true` — or neither — but Cavern Elf explicitly replaces low-light-vision with darkvision (the two are mutually exclusive in that case).

## Edge Cases

- [ ] `[NEW]` A character with both flags set (via data error) resolves to darkvision (stronger sense wins).
- [ ] `[NEW]` A character without any special vision sense resolves normal rules in dim light (concealed condition applies).
- [ ] `[TEST-ONLY]` Changing ancestry during character creation clears and re-applies the correct sense flag.

## Failure Modes

- [ ] `[TEST-ONLY]` Querying sense flags for a non-existent character returns structured error (not a 500).
- [ ] `[NEW]` Sense flags cannot be directly mutated by the client (server-side ancestry/heritage application only).

## Permissions / Access Control

- [ ] Sense flag data is readable by session participants (needed for targeting/encounter display).
- [ ] Sense assignment is server-side only; clients cannot POST/PATCH sense flags directly.

### Route permission expectations (required for qa-permissions.json)

| Route | HTTP method | Permission | anon | authenticated | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `/dungeoncrawler/character/{id}/senses` | `GET` | `access dungeoncrawler characters` | deny | allow | allow | allow | allow | allow |

## Security acceptance criteria

- [ ] GET `/dungeoncrawler/character/{id}/senses` does NOT require CSRF token (`_csrf_token: FALSE`).
- [ ] Any write path that modifies sense flags (heritage/ancestry assignment) MUST use `_csrf_request_header_mode: TRUE` on the POST route.
- [ ] Characters can only read sense flags for characters they have session access to (no cross-character data exposure).
- [ ] Sense flag write endpoint returns 403 for anonymous users.
