# Test Plan Design: dc-cr-languages

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T00:09:19+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-languages/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-languages "<brief summary>"
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

# Acceptance Criteria: dc-cr-languages

- Feature: Languages System
- Work item id: dc-cr-languages
- PM owner: pm-dungeoncrawler
- Written: 2026-04-07

## Gap analysis reference

- `[NEW]` — language taxonomy content type (or config entity) does not exist yet.
- `[NEW]` — `languages[]` field on character entity does not exist yet.
- `[NEW]` — ancestry default-language assignment at character creation does not exist yet.
- `[NEW]` — INT-modifier bonus language slots at character creation do not exist yet.
- `[EXTEND]` — character creation flow needs language selection step.

## Happy Path

- [ ] `[NEW]` A `language` taxonomy (or config entity) exists with at minimum: Common, Elvish, Dwarvish, Gnomish, Halfling, Orcish, Sylvan, Undercommon, Draconic, Jotun. Each entry has: language name, typical speakers (description), and script name.
- [ ] `[NEW]` Character entity has a `languages[]` multi-value field storing language identifiers.
- [ ] `[NEW]` At character creation, the ancestry auto-assigns its default language(s) (e.g., Elvish characters start with Common + Elvish). Default languages are defined in ancestry data.
- [ ] `[NEW]` Characters with INT modifier ≥ 1 receive INT-modifier bonus free language selection slots at creation. Player selects from available languages (excluding already-granted defaults).
- [ ] `[NEW]` `GET /characters/{id}` includes `languages: [...]` in the response body.
- [ ] `[EXTEND]` Character creation API accepts `languages: [...]` in the request body; validates against known language IDs; rejects unknown IDs with HTTP 400.
- [ ] `[NEW]` `GET /languages` returns the full language catalog (anonymous, HTTP 200).

## Edge Cases

- [ ] `[NEW]` INT modifier 0 or negative: no bonus language slots are added; character receives only ancestry defaults.
- [ ] `[NEW]` INT modifier ≥ 4: up to 4 bonus languages can be selected; cap is enforced at the INT modifier value, not a fixed number.
- [ ] `[NEW]` Duplicate language submission (ancestry default + player selection of same language): deduplicated silently; character holds each language once.
- [ ] `[NEW]` Language submitted at creation that is not in the catalog: HTTP 400 with `"unknown language id: <id>"` message.
- [ ] `[NEW]` Character with no ancestry assigned yet: `languages` field defaults to empty array; no error.

## Failure Modes

- [ ] `[TEST-ONLY]` Error messages are clear and actionable.
- [ ] `[NEW]` Invalid language ID rejected with HTTP 400 and explicit field-level message.

## Permissions / Access Control

- [ ] Anonymous user behavior: `GET /languages` catalog is public (HTTP 200).
- [ ] Authenticated user behavior: character language field is readable/writable by character owner.
- [ ] Admin behavior: language taxonomy manageable via Drupal admin UI (add/edit/delete entries).

## Data Integrity

- [ ] No data loss on character update: patching other character fields does not clear `languages[]`.
- [ ] Rollback path: language field can be removed from character entity via schema update without data loss on other fields.

## Knowledgebase check

- KB search: none found (no prior language system lessons in `knowledgebase/lessons/`).
- Note: elf-ancestry and elf-heritage-cavern are blocked on this feature being shipped. Expedite.
- Agent: qa-dungeoncrawler
- Status: pending
