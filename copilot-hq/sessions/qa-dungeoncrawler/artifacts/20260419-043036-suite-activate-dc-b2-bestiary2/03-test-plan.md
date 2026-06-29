# Test Plan: dc-b2-bestiary2

## Coverage summary
- AC items: 12 (creature import, schema compatibility, encounter integration, GM-only mutation routes, idempotent re-import handling)
- Test cases: 6 (TC-B2-01-06)
- Suites: phpunit import/service coverage + Playwright encounter/browser smoke coverage
- Security: GM-only import/override paths; player read-only creature-library access

---

## TC-B2-01 — Bestiary 2 import populates shared creature schema
- Description: Import a representative Bestiary 2 data set and confirm created records use the existing `creature` model from Bestiary 1.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: records persist with level, rarity, traits, senses, attacks, abilities, and defenses mapped into the shared schema.
- AC: Creature Library Expansion 1-2, Data Import and Idempotency 1

## TC-B2-02 — New creature families are filterable in encounter tooling
- Description: Bestiary 2 families such as aberrations, aquatic creatures, constructs, fey, and fiends appear through the shared encounter/browser filters.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: level, trait, rarity, and tactical-role filters surface Bestiary 2 creatures without a separate Bestiary 2 code path.
- AC: Creature Library Expansion 3-4, Encounter Tooling Integration 1-2

## TC-B2-03 — Re-import is deterministic
- Description: Re-run the same Bestiary 2 import payload.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: matching records update in place; duplicate creatures, duplicate slugs, and duplicate filter rows are not created.
- AC: Data Import and Idempotency 2, Failure Modes 2

## TC-B2-04 — Optional or sparse stat blocks import cleanly
- Description: Import creatures that omit optional sections such as languages, weaknesses, or special senses.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: records save successfully without junk placeholder values or malformed arrays.
- AC: Edge Cases 1-3

## TC-B2-05 — Player access remains read-only
- Description: Exercise creature-library reads as a player and mutation/import endpoints as a non-GM.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: read paths succeed, but import/override routes return 403 for non-GM users.
- AC: Access and Content Integrity 1-2, Failure Modes 3

## TC-B2-06 — Malformed import payload fails explicitly
- Description: Submit incomplete or invalid Bestiary 2 import data.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: import fails with explicit error handling and does not partially persist corrupted records.
- AC: Access and Content Integrity 3, Failure Modes 1
