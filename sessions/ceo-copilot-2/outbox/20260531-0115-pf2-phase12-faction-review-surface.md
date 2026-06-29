# CEO Outbox: PF2e Social Relationship System - Phase 12 Complete

**Date**: 2026-05-31T01:15:00+00:00
**Session**: CEO continuation - inbox work
**Status**: Delivered

## Executive Summary

Completed Phase 12 of the PF2e social relationship system: generated faction
near-match detection and the full review/approval surface. Generated factions
now enter a `pending_review` gate when token-overlap near-matches exist, and
operators can approve, reject, or merge via the existing review resolution
workflow instead of seeing a read-only manifest viewer.

## Work Completed

### FactionGenerationService — near-match detection + pending_review gate

- Scans existing `dc_library_institution_manifest` rows for generated factions
  sharing meaningful slug tokens (4+ char tokens) with the inbound request
- When near-matches exist: writes manifest as `pending_review` and creates a
  `dc_library_institution_review` row with `review_reason = near_match_detected`
  containing the candidate list in `details_json`
- When no near-matches: writes manifest as `normalized` directly (no change
  to the fast-path for clearly distinct factions)
- Campaign subject is instantiated in both paths so the wizard session proceeds
- All constants made public for downstream consumers

### InstitutionReviewDecisionService — faction decision actions

- Added `approve_faction`, `reject_faction`, `merge_with_existing` to resolved
  allowed actions
- `merge_with_existing` enforces `target_identifier` (same contract as `map_existing`)

### InstitutionReviewApplicationService — generated faction decision handler

- Detects generated-faction review rows by virtual source file path
  (`__generated__/factions`) and routes to a dedicated handler
- `approve_faction` → manifest status `normalized`
- `reject_faction` → manifest status `rejected`
- `merge_with_existing` → manifest status `merged`
- No file-system reads required (bypasses the JSON source file path used for
  library backfill reviews)

### InstitutionReviewBrowserController — active decision surface

- Left-joins open near-match review rows into the generated faction query
- Per-row "Review" action link for `pending_review` rows via the existing
  resolution route
- Near-match candidates shown in a collapsible cell with shared tokens labeled
- Removed "Classification" column (low signal); added "Near Matches" + "Action"

## Test Coverage

- 47/47 social-group unit tests pass
- 6 new test cases added across FactionGenerationServiceTest and
  InstitutionReviewDecisionServiceTest

## Commit

- `7ba074d` — Phase 12: generated faction near-match detection and review/approval surface

## Remaining Gaps After Phase 12

- GM-facing faction inspector UX: preview full draft + near-match candidates
  in a richer detail view during review decision
- Admin bulk-approval path for sessions generating many factions at once
- Downstream cascade on rejection: campaign subject cleanup or rebind to the
  confirmed target faction

## CEO Next Actions

1. Phase 12 closes the highest-priority gap in the faction generation surface
2. Immediate follow-on options (in priority order):
   a. Richer inspector UX for the review decision form (show draft characteristics
      and near-match candidates side-by-side before committing)
   b. Campaign subject cleanup on `reject_faction` (rebind or remove orphaned
      campaign subject when operator rejects a generated faction)
   c. Close the inbox item and shift to next priority if Board is satisfied
3. All 47 social-group unit tests pass; no regressions
