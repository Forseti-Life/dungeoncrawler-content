- Status: done
- Summary: CSRF fix for btn-save-job completed. Root cause was cache max-age 3600 with `cache_contexts: []` causing shared cache without user-context; fixed by adding `cache_contexts: ['user.roles']` and `max-age: 0` in JobApplicationRestResource. Commit d5a32b2a4. This item is a duplicate/redirect of outbox 20260227-fix-csrf-btn-save-job.md — see that file for full details.

## Next actions
- None. Fix is live in production.

## Blockers
- None.

## ROI estimate
- ROI: 10
- Rationale: CSRF fix was P0 for release-b; shipped and verified.
