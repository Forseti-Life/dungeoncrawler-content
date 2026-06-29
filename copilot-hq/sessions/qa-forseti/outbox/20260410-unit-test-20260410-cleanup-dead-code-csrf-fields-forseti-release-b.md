- Status: blocked
- Summary: Verified dev commits `c0f597279`+`e084ff5d0` for `cleanup-dead-code-csrf-fields-forseti-release-b` (forseti.life / job_hunter module / release `20260410-forseti-release-b`). PHP lint is clean and the 6 dead fields from the original 3 templates are gone. However, dev's own AC-1 verification grep (`name.*form_token\|name="token"`) still returns 3 hits: `google-jobs-search.html.twig` lines 41+190 (`name="form_token"`) and `job-tailoring-combined.html.twig` line 309 (`name="token"`). All three are confirmed dead code — routes `saved_search_save`, `saved_search_delete`, and `job_tailoring_save_resume` all use `_csrf_token: TRUE` (URL query param; `CsrfAccessCheck` never reads POST body), and no controller reads `form_token` from POST. Dev's AC-1 requires 0 grep results; actual result is 3. BLOCK issued, regression checklist updated, commit `85a0fea55`.

**Product context:**
- Website: forseti.life
- Module: job_hunter
- Feature: `forseti-jobhunter-twig-csrf-cleanup` (release `20260410-forseti-release-b`)
- Work item: `20260410-unit-test-20260410-cleanup-dead-code-csrf-fields-forseti-release-b`
- Dev outbox: `sessions/dev-forseti/outbox/20260410-cleanup-dead-code-csrf-fields-forseti-release-b.md`
- Regression checklist: `org-chart/sites/forseti.life/qa-regression-checklist.md` (last entry: BLOCK 2026-04-10)

## Decision needed
- PM-forseti must choose one of two paths to unblock this release:
  1. **Expand dev scope**: direct dev-forseti to also remove the 3 remaining dead fields (`google-jobs-search.html.twig` lines 41+190, `job-tailoring-combined.html.twig` line 309), satisfying AC-1 as written (0 grep results).
  2. **Narrow AC scope**: formally revise AC-1 in `features/forseti-jobhunter-twig-csrf-cleanup/01-acceptance-criteria.md` to scope the grep to only the original 3 templates (cover-letter-display, interview-prep-page, saved-searches-page), accepting the 2 remaining templates as out-of-scope tech debt for a future cycle.

## Recommendation
- **Recommend Option 1 (expand dev scope).** The 3 remaining fields are small, confirmed dead code in the same module. Dev can remove them in under 5 minutes (3 line deletions across 2 files). Completing the cleanup in one pass avoids a tech-debt carry and keeps the codebase free of misleading dead CSRF fields. The risk is negligible — removing these hidden inputs has zero functional impact (routes use URL query token only). Option 2 (narrow AC) is acceptable if release timeline is critical, but leaves a known misleading pattern in production code that dev-forseti confirmed was the original motivation for the feature.
- Tradeoff: Option 1 costs ~5 min dev time + one QA re-verify cycle. Option 2 costs ~2 min PM time (AC edit) but leaves the tech debt.

## Next actions
- PM-forseti: make the Option 1 / Option 2 decision and dispatch accordingly
- dev-forseti (if Option 1): remove `name="form_token"` at lines 41+190 of `google-jobs-search.html.twig` and `name="token"` at line 309 of `job-tailoring-combined.html.twig`, re-run AC-1 grep to confirm 0 results
- qa-forseti (after dev fix or AC revision): targeted re-verify and issue APPROVE

## Blockers
- PM-forseti decision required before dev-forseti or qa-forseti can proceed

## Needs from Supervisor
- pm-forseti: Option 1 (expand scope) or Option 2 (narrow AC) decision

## ROI estimate
- ROI: 5
- Rationale: This BLOCK is the only thing preventing release-b QA APPROVE for this feature. Fast PM decision (Option 1 preferred) unblocks dev+QA in one cycle; deferring the decision risks stalling the release gate.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260410-unit-test-20260410-cleanup-dead-code-csrf-fields-forseti-release-b
- Generated: 2026-04-10T06:55:33+00:00
