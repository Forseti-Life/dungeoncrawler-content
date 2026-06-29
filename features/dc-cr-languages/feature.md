# Feature Brief: Languages System

- Work item id: dc-cr-languages
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: shipped
- Release: 20260412-dungeoncrawler-release-u
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Track which languages a character can speak, read, and write. Ancestry and Intelligence modifier determine starting languages; additional languages can be gained through feats and class features. Languages gate certain skill checks, social interactions, and lore access in dungeoncrawler scenarios.

## Source reference

> "Details on languages also appear in this chapter." (Chapter 2: Ancestries & Backgrounds)

## Implementation hint

Field on the character entity: `languages[]` (list of string identifiers). Content type or taxonomy: `language` with fields for language name, script, and typical speakers. Ancestry data should include default languages. Intelligence bonus at character creation adds free language slots. Simple UI: multi-select from available languages list.

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: entity field types enforced at Drupal entity layer; mutations server-validated against allowed values
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type) only

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Latest updates

- 2026-05-05: Reconciled to shipped after coordinated push for 20260412-dungeoncrawler-release-u.
