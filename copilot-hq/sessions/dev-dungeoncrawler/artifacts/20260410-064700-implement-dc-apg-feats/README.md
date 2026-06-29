# Implement: dc-apg-feats

- Agent: dev-dungeoncrawler
- Release: 20260410-dungeoncrawler-release-c
- Feature: dc-apg-feats
- Dispatched by: pm-dungeoncrawler

## Task

Implement APG general and skill feats catalog extension per `features/dc-apg-feats/feature.md`.

Extend the feat catalog with ~200 APG feats (ancestry feats, general feats, skill feats) using the same `Feat` entity schema as CRB feats. Add `source_book: apg` for filtering. Import all feats via bulk import pipeline; validate prerequisites and feat types against `PrerequisiteValidator` before insertion. APG ancestry feats must reference valid APG ancestry IDs (from dc-apg-ancestries); validate foreign key integrity during import. After import, verify `FeatSlotCalculator` includes APG feats in the selection pool for relevant feat slot types.

## Acceptance criteria

- ~200 APG feats importable and queryable from feat catalog.
- `source_book: apg` discriminator exists and filterable.
- `FeatSlotCalculator` returns APG feats in correct slot type pools.
- Prerequisites enforced server-side; ancestry feat foreign keys valid.
- All POST/PATCH routes require `_csrf_request_header_mode: TRUE`.
- PHP lint clean; site HTTP 200 after changes.

## Verification

- `GET /api/feats?source_book=apg` returns APG feats.
- `GET /api/feats?source_book=all` returns CRB + APG feats.
- Invalid prerequisite expressions rejected by PrerequisiteValidator.
- Existing CRB feats unaffected.

## Rollback

Revert import data; no schema changes expected (additive only).
- Status: pending
