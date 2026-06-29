# Gate R5 Production Regression — Critical 404s (20260412-forseti-release-r)

**Status:** BLOCKED pending fix
**Release:** 20260412-forseti-release-r (pushed to production at 20260505-185412)
**Audit timestamp:** 20260506-103423
**Audit verdict:** FAIL

## Critical Issues

### Issue 1: Jobhunter Module Routes Returning 404 (59 affected routes)
All jobhunter functionality is unreachable in production:

**Dashboard/navigation:**
- `/jobhunter` → 404
- `/jobhunter/applications` → 404
- `/jobhunter/documentation` → 404

**Core features (broken):**
- `/jobhunter/jobs/{id}` → 404
- `/jobhunter/application-submission` → 404
- `/jobhunter/companies/*` → 404
- `/jobhunter/bulk-import-companies` → 404

**API endpoints (broken):**
- `/jobhunter/api/googlejobs/search` → 404
- `/jobhunter/api/googlejobs/details` → 404

### Issue 2: Public Content Routes Return 404 (2 permissions violations)
Routes required to be publicly accessible are returning 404:
- `/contact` → 404 (expected: allow, got 404)
- `/how-it-works` → 404 (expected: allow, got 404)

## Root Cause (Likely)
Given the volume of 404s (59 routes), this suggests:
1. **Drupal module not enabled** — jobhunter/forseti_content modules may not have been enabled during deployment
2. **Missing Drupal cache rebuild** — route registry not regenerated post-deploy
3. **Missing Composer install** — vendor code not installed in production
4. **Database migrations not run** — entity types/bundles do not exist
5. **Conditional routing** — routes may be gated on feature flags not yet enabled

## Investigation Steps
1. SSH to production and verify:
   - `drush pm:list --status=enabled | grep jobhunter` (check module enabled)
   - `drush ev "print_r(\\Drupal::service('router.route_provider')->getAllRoutes()->count());"` (check route count)
   - Check `/var/www/forseti.life/error.log` for deployment errors
2. If module not enabled, run: `drush pm:enable jobhunter forseti_content -y && drush cache:rebuild`
3. If still failing, check for conditional route registration or feature flags

## Acceptance Criteria (to unblock release)
- [ ] All 59 jobhunter routes return 200 (or appropriate error for invalid IDs)
- [ ] `/contact` and `/how-it-works` accessible anonymously (no 404)
- [ ] QA re-runs Gate R5 audit and produces APPROVE verdict
- [ ] Deployment notes updated to include deployment steps (enable modules, cache rebuild, etc.)

## Evidence
- Audit output: `sessions/qa-forseti/artifacts/auto-site-audit/20260506-103423/`
- Route details: `sessions/qa-forseti/artifacts/auto-site-audit/20260506-103423/route-audit-summary.md`
- Gate R5 task: `sessions/ceo-copilot-2/inbox/20260505-142806-gate-r5-audit-20260412-forseti-release-r/`

## Priority
- **ROI: 999** (critical production blocker)
- **Deadline:** Next 4 hours (SLA: 4h to fix after FAIL verdict)
- **Rationale:** All users are unable to use Job Hunter features; core public pages unreachable.

## Next Steps
1. Investigate route registration in production (see Investigation Steps)
2. Apply fix (likely module enable + cache rebuild)
3. Notify QA to re-run Gate R5 audit
4. When audit passes, coordinate with pm-forseti for re-push approval
- Agent: dev-forseti
- Status: pending
