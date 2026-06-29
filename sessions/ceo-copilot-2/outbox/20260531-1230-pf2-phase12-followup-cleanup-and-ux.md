# CEO Outbox — Phase 12 follow-up: campaign subject cleanup + richer review form UX

**Date:** 2026-05-31  
**Session:** c483fc8d  
**Inbox item:** `20260527-pf2-social-relationship-loyalty`  
**DC commit:** `fa1a5b0`

---

## What was done

Two remaining gaps from Phase 12 of the PF2e social relationship / faction system were closed.

### 1. Campaign subject cleanup on reject/merge (data integrity)

When an operator resolves a generated faction review as `reject_faction` or `merge_with_existing`, the campaign subjects that were instantiated at faction-generation time are now handled automatically:

- **`reject_faction`** → all `active` campaign subjects with `source_asset_type = 'library_faction'` and `source_asset_id = canonical_slug` are set to `status = 'orphaned'`
- **`merge_with_existing`** → same subjects have `source_asset_id` rewritten to `target_identifier` (the confirmed canonical faction they should map to); subjects remain active
- **`approve_faction`** → no campaign subject changes (subjects are already correctly bound)

New protected helpers in `InstitutionReviewApplicationService`: `orphanGeneratedFactionSubjects()`, `rebindGeneratedFactionSubjects()`.

### 2. Richer operator UX in InstitutionReviewResolutionForm

The review resolution form now shows faction-specific context for generated faction review rows, rather than the generic raw-JSON inspector:

- **Side-by-side draft view**: canonical label, slug, public/hidden face, ideology/method tags, story role
- **Near-match candidates**: slug + shared tokens for each near-match that triggered the review
- **Faction-specific action options**: `approve_faction`, `reject_faction`, `merge_with_existing` appear in the action dropdown only for generated faction rows
- **Details fieldset auto-opens** for generated faction rows so operators don't miss the context
- `merge_with_existing` now requires `target_identifier` (validation parity with `map_existing`)
- `createNearMatchReviewItem` stores structured `{draft, near_match_candidates}` instead of flat `{canonical_label, near_matches}`

### 3. Test coverage

New file: `tests/src/Unit/Service/InstitutionReviewApplicationServiceTest.php` — 5 tests covering approve/reject/merge subject lifecycle paths.  
All 52/52 social unit tests pass.

---

## Board decision needed

None. All changes are at CEO authority level — data-integrity cleanup with full test coverage, no new external dependencies.

---

## What's left (optional follow-on, no blocking risk)

1. Admin bulk-approval UI for large faction generation sessions  
2. Dedicated "faction inbox" surfacing only `pending_review` factions separate from the generic institution review browser

Both are quality-of-life improvements, not data safety issues. The system is now fully functional end-to-end for the social relationship workstream.
