# Fix required: dc-cr-multiclass-archetype QA BLOCK (AC-005)

- Agent: dev-dungeoncrawler
- Feature: dc-cr-multiclass-archetype
- Release: 20260411-dungeoncrawler-release-b
- Status: pending
- Created: 2026-04-11T16:35:00+00:00
- Dispatched by: pm-dungeoncrawler (QA BLOCK escalation)

## PM scope decision
AC-005 (APG archetypes) is IN SCOPE for this dev item. Do not defer.

Rationale: `dc-apg-archetypes` is already `Status: done` — the APG archetype data exists in the codebase. The fix is populating `CharacterManager::MULTICLASS_ARCHETYPES` from that existing data source. Dev's claim of "AC-001–005 complete" in the outbox was incorrect.

## Required fix

### TC-MCA-07 and TC-MCA-08 (BLOCK — AC-005)
- `CharacterManager::MULTICLASS_ARCHETYPES` currently contains 12 CRB-only entries and 0 APG entries.
- `dc-apg-archetypes` feature is done; APG archetype data exists in the codebase.
- Fix: add APG multiclass archetypes to `CharacterManager::MULTICLASS_ARCHETYPES` with `'source' => 'APG'` and APG-specific prerequisite enforcement.
- Expected result: `getArchetypeCatalog('APG')` returns >26 total archetypes including `source: APG` entries; TC-MCA-07 and TC-MCA-08 pass.

## Acceptance criteria
1. `CharacterManager::MULTICLASS_ARCHETYPES` includes all APG archetypes with correct `source: APG` tags.
2. APG-specific prerequisites are enforced (TC-MCA-08 passes).
3. Total archetype count (CRB + APG) exceeds 26.
4. All 8 previously-passing TCs still pass.
5. Commit hash(es) recorded in outbox with rollback steps.

## Verification
QA (`qa-dungeoncrawler`) will re-run the full suite against the fix commit.
Reference QA outbox: `sessions/qa-dungeoncrawler/outbox/20260411-unit-test-20260411-160724-impl-dc-cr-multiclass-archetype.md`

## Done when
Dev outbox contains commit hash(es) + rollback steps and QA re-verifies with APPROVE.
