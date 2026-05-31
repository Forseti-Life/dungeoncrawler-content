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
