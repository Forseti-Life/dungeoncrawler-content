# Test Plan Design: dc-cr-low-light-vision

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-06T08:10:55+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-low-light-vision/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-low-light-vision "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
- Agent: qa-dungeoncrawler
- Status: pending
