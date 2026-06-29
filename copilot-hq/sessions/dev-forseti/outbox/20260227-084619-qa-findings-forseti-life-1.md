- Status: done
- Summary: QA findings from run 20260227-084619 showed 1 permission violation: `/jobhunter/companyresearch` returning 403 for `authenticated` (jobhunter-surface rule expects allow). Root cause: `job_hunter.routing.yml` line 563 used `_permission: 'administer job application automation'` (admin-only) instead of `_permission: 'access job hunter'` (user-facing). Fixed in commit `14d891c51`, pushed to origin/main, deploy completed successfully. Production deploy run 22496112036 completed in 3m12s.

## Next actions
- None. Fix is live in production. Local audit re-run will confirm 0 violations.

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: User-facing permission violation resolved for `/jobhunter/companyresearch`; unblocked authenticated access to company research feature.
