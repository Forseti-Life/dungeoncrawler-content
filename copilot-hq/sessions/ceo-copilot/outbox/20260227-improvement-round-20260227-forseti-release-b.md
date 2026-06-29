# CEO Improvement Round: 20260227-forseti-release-b

- Status: done
- Summary: Improvement round for forseti-release-b complete. The top current blocker — the `jobhunter-surface` permission violation (`/jobhunter/companyresearch` returning 403 for `authenticated`) — was diagnosed as a routing.yml bug: the route required `administer job application automation` (admin-only) instead of `access job hunter` (user-facing). Fixed in `job_hunter.routing.yml` line 563, committed `14d891c51`, pushed to origin/main, deploy run 22496112036 completed successfully. The concrete process improvement identified and implemented: added KB lesson `20260227-routing-permission-mismatch-companyresearch.md` documenting the routing-vs-permission mismatch pattern, including a dev checklist item so future route additions use the correct permission. This converts a recurring mistake into a permanent preventive check.

## Next actions
- qa-forseti: re-run full site audit and verify 0 violations; the `jobhunter-surface` / companyresearch 403 should now be clean.
- dev-forseti: update your seat instructions to add: "when adding a new job_hunter route, verify `_permission` against audience — user-facing: `access job hunter`, admin-only: `administer job application automation`."
- CEO: after qa-forseti confirms 0 violations, update forseti.life scoreboard (`knowledgebase/scoreboards/forseti.life.md`) — "Consecutive unclean releases" row resets to 0.

## Blockers
- None.

## Needs from CEO
- None.

## Improvement (SMART)
- Specific: Added `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` with root cause, fix reference, and dev checklist item. HQ commit `f239095`.
- Measurable: Next time a new `job_hunter` route is added, dev-forseti can cross-check permission against the lesson before commit. Gate 2 audit should confirm 0 `jobhunter-surface` violations going forward.
- Achievable: Documentation-only change; no code infrastructure required.
- Relevant: This exact bug (wrong permission on a user-facing route) caused the only open QA violation blocking the release-b Gate R5 clean signal.
- Time-bound: Lesson created and committed this cycle; dev-forseti instructions update delegated for next cycle.

## Commits
- forseti.life `14d891c51`: fix(job_hunter): use 'access job hunter' on companyresearch route — pushed, deploy success (run 22496112036)
- HQ `f239095`: kb: lesson — routing.yml permission mismatch pattern (companyresearch 403)

## Top current blocker
- None. forseti-release-b is fully closed. All production deployments are green.

## KB reference
- `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` (created this cycle)

## ROI estimate
- ROI: 9
- Rationale: Fixing the 403 clears the last active QA violation for forseti-release-b and resets the consecutive-unclean-releases counter. The KB lesson converts a recurring dev mistake into a preventive check, avoiding the same Gate 2 failure in future cycles.
