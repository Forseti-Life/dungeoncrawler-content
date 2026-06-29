# PF2E BA System of Record

This document defines the **canonical tracking stack** for Business Analyst work on
PF2E source ingestion, requirements extraction, feature derivation, and release-cycle
handoff for DungeonCrawler.

## Canonical tracking surfaces

| Surface | Scope | Owner | Purpose |
|---|---|---|---|
| `docs/dungeoncrawler/PF2requirements/source-ledger.json` | Per source document | BA | **System of record** for downstream traceability from a book/section set to requirements, issues, features, and release handoff |
| `docs/dungeoncrawler/PF2requirements/EXTRACTION_TRACKER.md` | Per chapter/section | BA | Canonical **source-object tracker** for chapter/section completion status |
| `docs/dungeoncrawler/PF2requirements/audit/*.md` | Per heading / subheading | BA | Working-paper completeness checklist proving the paragraph/section pass was exhaustive |
| `docs/dungeoncrawler/PF2requirements/references/*.md` | Per chapter/section | BA | Paragraph-by-paragraph extracted requirements artifact |
| `docs/dungeoncrawler/issues/*.md` + `docs/dungeoncrawler/issues/README.md` | Per logical requirement group | BA / PM | Downstream grouped issues derived from the references docs |
| `features/dc-*/feature.md` + `features/dc-feature-index.md` | Per release-ready feature stub | BA / PM | Implementable backlog items submitted into release-cycle grooming |
| `copilot-hq/tmp/ba-scan-progress/dungeoncrawler.json` | Per active scan cursor | BA | **Execution cursor only** for release-cycle chunk scanning; not the source of truth for requirements completeness |

## Required state model

Every source document must carry four downstream states in `source-ledger.json`:

1. `requirements_status`
   - `pending` → not yet analyzed
   - `in_progress` → analysis active
   - `complete` → chapter/section requirements artifacts complete
   - `skipped` → intentionally skipped with rationale

2. `issue_mapping_status`
   - `unmapped` → requirements exist but no controlled issue grouping yet
   - `partial` → some issue grouping exists, not complete
   - `mapped` → issues/README fully linked

3. `feature_mapping_status`
   - `unmapped` → no controlled source→feature linkage yet
   - `partial` → some derived `dc-*` stubs exist
   - `mapped` → derived stubs are linked and indexed

4. `release_handoff_status`
   - `pending` → nothing submitted to PM for a release
   - `submitted` → BA has handed features/issues to PM
   - `triaged` → PM has triaged the downstream work
   - `activated` → work has entered a release
   - `released` → shipped / completed

## Tight ingestion workflow

### A. Source ingestion

Before work starts on a new source document:
1. Add or confirm the source-document entry in `source-ledger.json`.
2. Add or confirm the chapter/section rows in `EXTRACTION_TRACKER.md`.
3. Add or confirm the detailed audit worksheet under `audit/*.md`.

### B. Source-object analysis

For each chapter/section:
1. Use the matching `audit/*.md` file as the exhaustive checklist.
2. Produce the paragraph-by-paragraph requirements doc in `references/*.md`.
3. Mark the object complete in `EXTRACTION_TRACKER.md`.
4. Update `source-ledger.json` summary state for that source document.

### C. Requirements to issue mapping

When a source object yields grouped implementation work:
1. Create or update `docs/dungeoncrawler/issues/issue-*.md`.
2. Update `docs/dungeoncrawler/issues/README.md`.
3. Advance `issue_mapping_status` in `source-ledger.json`.
4. If no issue is needed, record the rationale in the requirements doc and ledger gap notes.

### D. Requirements to feature mapping

When the source yields release-trackable build work:
1. Create `features/dc-*/feature.md` stubs with a precise `Source:` field.
2. Update `features/dc-feature-index.md`.
3. Advance `feature_mapping_status` in `source-ledger.json`.
4. Keep the release-cycle scan cursor in `tmp/ba-scan-progress/dungeoncrawler.json` in sync for chunk-based scans.

### E. Release-cycle handoff

When BA submits work into the release cycle:
1. Hand off issues/features to PM using the normal inbox/outbox process.
2. Advance `release_handoff_status` in `source-ledger.json`.
3. PM owns the transition from `submitted` → `triaged` → `activated` → `released`.

## Non-negotiable rules

- `source-ledger.json` is the **single answer** to “what is the state of this source document?”
- `EXTRACTION_TRACKER.md` is the **single answer** to “which chapter/section objects are complete?”
- `audit/*.md` is the **single answer** to “did we actually inspect the internal headings/paragraph groups?”
- `tmp/ba-scan-progress/dungeoncrawler.json` must never be used as proof of requirements completeness.
- A requirements doc without tracker and ledger updates is **incomplete work**.
- A feature stub without a traceable `Source:` back to a source object is **weakly controlled work**.

## Immediate cleanup targets

The current corpus shows these integrity gaps:
- many `references/*.md` artifacts exist, but downstream issue mapping is incomplete outside Core Chapter 1
- feature derivations exist, but source→feature traceability is only partially controlled
- the chunk-scan cursor and the extraction tracker drifted apart

This system fixes that by separating:
- **execution cursor** (`tmp/ba-scan-progress/...`)
- **source-object completeness** (`EXTRACTION_TRACKER.md`, `audit/*.md`)
- **downstream handoff traceability** (`source-ledger.json`)
