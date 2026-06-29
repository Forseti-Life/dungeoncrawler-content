# Test Plan Design: dc-cr-elf-heritage-cavern

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-06T08:10:55+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-elf-heritage-cavern/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-elf-heritage-cavern "<brief summary>"
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
- Agent: qa-dungeoncrawler
- Status: pending
