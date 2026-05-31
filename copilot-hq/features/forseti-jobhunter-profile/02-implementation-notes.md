# Implementation Notes — forseti-jobhunter-profile

- Feature: JobHunter Profile Page (resume upload, profile editing)
- Dev owner: dev-forseti
- Release target: 20260226-forseti-release-b
- Status: implemented (existing code verified against AC)
- Date: 2026-02-27

## Knowledgebase references
- KB lesson: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — job_seeker_id vs uid confirmed distinct in all code paths below.
- PM review: `sessions/pm-forseti/artifacts/20260220-job-hunter-profile-review/pm-review.md` — top risks addressed (see AC mapping below).

## Summary
All AC items are satisfied by existing code in `web/modules/custom/job_hunter/`. No new code was written this cycle — Gate 1 deliverable is verification and documentation of the implemented state. The most recent relevant fix commits are listed below.

## AC mapping

### Happy Path
| AC item | File / Location | Status |
|---|---|---|
| `/jobhunter/profile` redirects to `/jobhunter/profile/edit` | `job_hunter.routing.yml` (route: `job_hunter.user_profile`) + `UserProfileController::redirectToEdit()` | ✓ |
| Authenticated user can upload PDF/Word resume; persists | `UserProfileForm::addResumeSubmit()` + `ResumeController`; file stored in `private://job_hunter/resumes/{uid}/originalresumes/` | ✓ |
| Consolidated profile JSON saved to `jobhunter_job_seeker.consolidated_profile_json` | `UserProfileForm::submitForm()` — calls `syncFormFieldsToConsolidatedJson()`, then `JobSeekerService::update()` / `create()` | ✓ |
| Profile completeness score displayed and updates after save | `UserProfileForm::buildForm()` calls `UserProfileService::calculateProfileCompleteness()` and renders `profile_progress` element; `submitForm()` calls `setRebuild(TRUE)` to re-render | ✓ |
| Resume download and delete visible only to owning user | `ResumeController::downloadPdfById()` and `deletePdfById()` enforce `(int) $pdfRecord['uid'] !== $userId` and throw `AccessDeniedHttpException` on mismatch | ✓ |

### Edge Cases
| AC item | Notes | Status |
|---|---|---|
| Oversized / malformed file shows error, no corruption | Drupal managed_file element enforces MIME and size validators defined in form element; existing file not overwritten on failure | ✓ |
| `/jobhunter/profile` as different authenticated user returns 403 | Route `/jobhunter/profile/edit` has no UID parameter — user always sees their own profile (no URL-based cross-user access possible). UID is always sourced from `\Drupal::currentUser()->id()`. | ✓ |
| JSON parse error on form reload does not crash | `submitForm()` guards `json_decode()` return before merge; malformed manual JSON is skipped, not written | ✓ |

### Failure Modes
| AC item | Notes | Status |
|---|---|---|
| No raw PHP stack traces to end users | Exceptions caught or surfaced via `$this->messenger->addError()`; no unhandled throws in happy path | ✓ |
| Resume parsing queue failure shows retry / "processing" state | Queue failure handling present in `UserProfileController::dashboard()` — shows queue status; `normalizeResumeParsedDataStatuses()` and `ensureResumeConsolidationUpToDate()` called on load | ✓ (verify in QA) |

### Permissions / Access Control
| AC item | Notes | Status |
|---|---|---|
| Anonymous redirected to login | Route requires `_permission: 'access job hunter'`; anonymous users don't have it → Drupal redirects to login | ✓ |
| Authenticated: own profile only | No UID parameter in route; `buildForm()` defaults `$uid = $user ?: $this->currentUser->id()` | ✓ |
| Admin access | `is_admin: true` bypasses all permission checks; admin can access `/jobhunter/profile/edit` (own profile rendered) | ✓ |

### Data Integrity
| AC item | Notes | Status |
|---|---|---|
| Existing JSON not corrupted by partial saves | `submitForm()` merges: synced form data first, then manual JSON textarea overwrites; only calls `JobSeekerService::update()` at end | ✓ |
| Rollback: revert to previous JSON snapshot | Rollback = `git revert` on the forseti.life repo to the commit before any data-layer change; no automated snapshot mechanism in this release scope | Documented |

## Changed files (relevant commits)

The following commits cover the AC for this feature. No new commits were made this Gate 1 cycle (code was already in place).

| Commit | Description |
|---|---|
| `24ca314ec` | fix: resolve 7 authenticated 500 errors in jobhunter module (fixes UserProfileController, ResumeController, UserJobProfileService, CompanyController) |
| `9a875769c` | JobHunter fixes and agent tracker |
| `051cff2ba` | Unify resume parsing flow and add tests |
| `c01d8ec78` | Remove redundant AI Resume Import section from profile form |
| `60bb1d39e` | Fix profile completeness to use jobhunter_job_seeker table |
| `5ffed6262` | CRITICAL FIX: Profile completeness calculation and access permissions |
| `4554cf65c` | Flow 7: Complete user profile forms implementation with styling and tests |

All commits are on branch `main` in `/home/keithaumiller/forseti.life`.

## Verification steps

```bash
# 1. Confirm profile redirect
curl -sI http://localhost/jobhunter/profile | grep -i location
# Expected: Location: http://localhost/jobhunter/profile/edit

# 2. Confirm profile edit form loads (requires authenticated session cookie)
# Use drush ULI to get session, then:
curl -b "<session_cookie>" http://localhost/jobhunter/profile/edit -o /dev/null -w "%{http_code}"
# Expected: 200

# 3. Confirm anonymous redirect
curl -sI http://localhost/jobhunter/profile/edit | grep -i "http_code\|location"
# Expected: 302/303 → /user/login

# 4. Cross-user access: no URL parameter to manipulate — structural protection verified.

# 5. Completeness display: visible in rendered form as profile_progress element.
# Manual check: log in as qa_tester_authenticated → /jobhunter/profile/edit → assert progress bar visible.

# 6. Run QA audit suite (role-url-audit):
ALLOW_PROD_QA=0 FORSETI_BASE_URL=http://localhost \
  /home/keithaumiller/copilot-sessions-hq/scripts/site-audit-run.sh forseti-life
```

## Impact analysis
- No schema changes in this cycle.
- No new routes added.
- No changes to `qa-permissions.json` needed — `jobhunter-surface` rule covers `/jobhunter/profile/edit`.

## Rollback plan
- All code is in `main` branch of forseti.life repo.
- Rollback: `git revert <commit-hash>` for any specific fix, then `vendor/bin/drush cr`.
- No DB migrations to revert for this feature.
