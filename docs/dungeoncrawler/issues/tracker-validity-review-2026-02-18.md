# Tracker Validity Review (2026-02-18)

## Scope

This review validates active DCC tracker rows in repository-root `Issues.md` against current `dungeoncrawler_content` code and identifies semantic duplication/overlap.

## Summary

- Total active DCC rows: **107**
- Specific actionable rows: **2**
- Generic batch rows (`Review/refactor: <path>`): **105**
- Missing-file references in generic rows: **0**

Interpretation:
- The backlog is structurally valid (paths exist), but semantically over-fragmented.
- Most rows are likely supersedable into umbrella items.

## Specific Issues (Keep Open)

1. **DCC-0330** — Keep open
   - Reason: still reproducible.
   - Evidence: `CharacterStateController::hasCharacterAccess()` hardcodes `campaign_id = 0`, while endpoints accept campaign context (`campaignId`/`instanceId`).

2. **DCC-0224** — Keep open
   - Reason: blueprint/documentation artifact not yet present as described.
   - Current state: no complete encounter-AI integration blueprint page in `dungeoncrawler_content` documentation/routes.

## Generic Review/Refactor Backlog (Semantic Overlap)

All 105 rows share essentially the same note:

> Review schema dependencies for unified table + JSON structures.

They are best managed as umbrella issues by area instead of one issue per file.

### Recommended Umbrella Structure

- **SRC Umbrella**: keep `DCC-0225` and expand notes to explicitly cover `DCC-0226..DCC-0297`.
- **JS Umbrella**: keep `DCC-0298` and expand notes to explicitly cover `DCC-0299..DCC-0329`.

### Supersede Candidates (Top 10 examples)

- DCC-0226
- DCC-0228
- DCC-0235
- DCC-0247
- DCC-0274
- DCC-0299
- DCC-0301
- DCC-0310
- DCC-0327
- DCC-0329

(These are representative; same supersede logic applies to remaining per-file generic rows in those ranges.)

## Proposed Cleanup Actions

1. Keep open: `DCC-0330`, `DCC-0224`, `DCC-0225`, `DCC-0298`.
2. Mark remaining generic per-file rows as superseded by umbrella IDs.
3. Add explicit acceptance checklists to umbrella rows:
   - schema/hot-column consistency
   - campaign/library/runtime boundary checks
   - API payload compatibility checks
   - migration/backward-compat checks

## Notes on Process Constraints

Per repository Copilot policy in `.github/copilot-instructions.md`, issue-work agents should not directly mutate repository-root `Issues.md` as part of issue execution workflow. This document is provided as a review-ready consolidation plan for manual tracker mutation.
