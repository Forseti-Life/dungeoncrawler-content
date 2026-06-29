# Feature Brief: Bestiary 2

- Work item id: dc-b2-bestiary2
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Category: creature-library
- Created: 2026-04-07
- Source: PF2E Bestiary 2
- DB sections: b2/s01/Baseline Requirements, b2/s01/Integration Notes, b2/s02/Baseline Requirements, b2/s02/Integration Notes
- Depends on: dc-cr-encounter-rules, dc-cr-npc-system, dc-b1-bestiary1

## Goal

Implement the Bestiary 2 creature library expansion: additional creature stat blocks including aberrations, aquatic creatures, constructs, fey, fiends, and other monster types not covered in Bestiary 1. Extends the `creature` content type and encounter tooling with ~350+ additional entries. Covers 12 requirements across `b2/s01–s02`.

## Source reference

> "The Bestiary 2 expands the monster roster with hundreds of new creatures, including new creature families, templates, and variants." (Bestiary 2 — Introduction)

## Implementation hint

Extension of `dc-b1-bestiary1` schema — no new content type fields needed; this feature is a data load. Requires Bestiary 1 creature schema to be fully implemented before creature data is meaningful. Import pipeline: drush batch import from structured JSON/YAML source files. Encounter tooling filter coverage expands automatically as creature content type entries grow. DB sections contain baseline data-model and integration-note placeholders; actual creature data loaded via import once B1 schema is live.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: creature library is read-only for players; GM-only mutation routes require `_campaign_gm_access: TRUE`
- CSRF expectations: any POST/PATCH creature import or override routes require `_csrf_request_header_mode: TRUE`
- Input validation: stat block fields validated against defined types and ranges; import pipeline sanitizes all text fields
- PII/logging constraints: no PII logged; creature id + encounter id + action type only
