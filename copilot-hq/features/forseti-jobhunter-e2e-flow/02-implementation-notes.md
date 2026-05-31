# Implementation Notes — forseti-jobhunter-e2e-flow

- Feature: JobHunter End-to-End Workflow (Playwright E2E test suite)
- Dev owner: dev-forseti
- Release target: 20260226-forseti-release-b
- Status: implemented (test script and suite wiring verified against AC)
- Date: 2026-02-27

## Knowledgebase references
- KB lesson: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — uid vs jobhunter_job_seeker.id distinction used in profile lookup during test flow.

## Summary
The E2E Playwright test script and its QA suite wiring are already implemented. No new code was written this cycle. Last successful run: 2026-02-19 (`submission.success: true`, all 6 steps reached correct URLs). Post-that-date fixes (`24ca314ec`, 2026-02-25) resolved 7 authenticated 500 errors that would have caused test failures. Gate 1 deliverable is verification and documentation of implemented state.

## Test script location
```
/home/keithaumiller/forseti.life/testing/jobhunter-workflow-step1-6-data-engineer.mjs
```
Playwright-based E2E test for a Data Engineer job seeker persona — steps 1–6 of the jobhunter onboarding/application workflow.

## Suite.json wiring
File: `qa-suites/products/forseti/suite.json`
Suite ID: `jobhunter-e2e`
Required for release: `true`

Full command (auto-provisions QA user, acquires ULI, runs Playwright):
```bash
bash -c 'set -e;
  DRUSH=/home/keithaumiller/forseti.life/sites/forseti/vendor/bin/drush
  $DRUSH --uri=http://localhost jhtr:qa-users-ensure --roles=authenticated 2>/dev/null
  QA_UID=$($DRUSH --uri=http://localhost user:information qa_tester_authenticated --format=json 2>/dev/null \
    | python3 -c "import json,sys; d=json.load(sys.stdin); print(list(d.keys())[0])")
  ULI=$($DRUSH --uri=http://localhost user:login --uid=$QA_UID --no-browser 2>/dev/null | tr -d "\n")
  cd /home/keithaumiller/forseti.life
  ULI_URL=$ULI BASE_URL=http://localhost \
    ARTIFACTS_DIR=sessions/qa-forseti/artifacts/jobhunter-e2e-latest \
    node testing/jobhunter-workflow-step1-6-data-engineer.mjs'
```

## AC mapping

### Core flow
| AC item | Implementation | Status |
|---|---|---|
| Authenticated user can complete steps 1–6 of the jobhunter workflow | Playwright script drives 6 steps: profile → job search → job select → tailor resume → review → submit | ✓ |
| `submission.success: true` at end of flow | Script emits JSON report with `submission.success` field; last run 2026-02-19 returned `true` | ✓ (re-verify) |
| QA user provisioned automatically | `jhtr:qa-users-ensure --roles=authenticated` creates `qa_tester_authenticated` if absent | ✓ |
| No external accounts created (stage break constraint) | Script runs entirely against `BASE_URL=http://localhost`; no calls to external job boards or account services in the flow | ✓ |
| QA session authentication via ULI (not password) | `drush user:login --uid=$QA_UID` returns a one-time login URL; script loads ULI first to establish session | ✓ |

### Exit codes (per run_notes)
| Code | Meaning |
|---|---|
| 0 | Pass |
| 2 | `submission.success=false` |
| 3 | No matching job found in Philadelphia |

### Post-2026-02-19 changes that affect test reliability
| Commit | Change | Impact on E2E |
|---|---|---|
| `24ca314ec` (2026-02-25) | fix: resolve 7 authenticated 500 errors in jobhunter module | Removes potential 500s on `/jobhunter/profile/edit`, resume operations, and company routes that would cause test failures |

## Changed files (relevant commits)
No new commits were made in this Gate 1 cycle for this feature. The following prior commits cover the implemented state:

| Commit | Description |
|---|---|
| `24ca314ec` | fix: resolve 7 authenticated 500 errors in jobhunter module |
| `9a875769c` | JobHunter fixes and agent tracker |
| `051cff2ba` | Unify resume parsing flow and add tests |

All commits are on branch `main` in `/home/keithaumiller/forseti.life`.

## Verification steps

```bash
# Run the full E2E suite via suite.json command:
bash -c 'set -e;
  DRUSH=/home/keithaumiller/forseti.life/sites/forseti/vendor/bin/drush
  $DRUSH --uri=http://localhost jhtr:qa-users-ensure --roles=authenticated 2>/dev/null
  QA_UID=$($DRUSH --uri=http://localhost user:information qa_tester_authenticated --format=json 2>/dev/null \
    | python3 -c "import json,sys; d=json.load(sys.stdin); print(list(d.keys())[0])")
  ULI=$($DRUSH --uri=http://localhost user:login --uid=$QA_UID --no-browser 2>/dev/null | tr -d "\n")
  cd /home/keithaumiller/forseti.life
  ULI_URL=$ULI BASE_URL=http://localhost \
    ARTIFACTS_DIR=sessions/qa-forseti/artifacts/jobhunter-e2e-latest \
    node testing/jobhunter-workflow-step1-6-data-engineer.mjs'

# Pass criteria: exit code 0, artifacts at sessions/qa-forseti/artifacts/jobhunter-e2e-latest/
# Check submission.success field:
cat sessions/qa-forseti/artifacts/jobhunter-e2e-latest/*.json | python3 -c \
  "import json,sys; d=json.load(sys.stdin); print('PASS' if d.get('submission',{}).get('success') else 'FAIL')"
```

## Manual / future test coverage gaps (from run_notes)
- TC-11 and TC-16 (dual-user isolation) require multi-user QA setup not yet in `jhtr:qa-users-ensure`. Currently flagged for manual execution or future tooling support.
- These are not blocking for Gate 1 / Gate 2 of this release cycle.

## Impact analysis
- No schema changes.
- No new routes added.
- No changes to `qa-permissions.json` needed — jobhunter-surface rule covers all routes exercised by E2E script.
- Stage break constraint satisfied: all requests go to `BASE_URL=http://localhost` only.

## Rollback plan
- E2E test script is non-destructive: reads and submits through the UI, does not directly modify the DB.
- If the test reveals a regression: `git revert <commit>` on forseti.life, then `vendor/bin/drush cr`.
- QA user cleanup: `drush --uri=http://localhost user:cancel qa_tester_authenticated` if needed.

---

## Update — 2026-04-08 (release-c implementation, dev-forseti)

### Gap fixed: applied-toggle-form missing from my-jobs template

**Problem:** The Playwright E2E test (`markAppliedDataEngineer`) expects `form.applied-toggle-form` with `input[name="have_applied"]`, `input[name="applied_on_date"]`, and `button[type="submit"]` in each job table row. The controller and POST route (`/jobhunter/my-jobs/{job_id}/applied`) were already implemented (commit `24ca314ec`), but the Twig template never rendered the form.

**Fix:** Added `form.applied-toggle-form` to all rows in `my-jobs.html.twig` as a secondary operation. Form action: `/jobhunter/my-jobs/{{ job.id }}/applied?token={{ job.apply_csrf_token }}`. The form shows the current applied state (checkbox pre-checked for `status == 'applied'`) and applies to all workflow states so users can record manual external applications at any stage.

**Commit:** `1f84c8539`

**Status after fix:** REQ-04.2 (mark applied, persist date/status) is now addressable by the Playwright E2E test. QA Gate 2 can proceed.

### AC coverage summary (post-fix)
| REQ | Description | Status |
|---|---|---|
| REQ-04.1 | Step flow 1–6 navigable | ✓ Playwright script + phase-buttons in dashboard |
| REQ-02.6 | Job save to list | ✓ Existing save flow |
| REQ-04.2 | Mark applied, persist date/status | ✓ Fixed (this commit) |
| REQ-06.1 | Queues run without intervention | ✓ Existing queue infrastructure |
| REQ-08.5 | Stage break — no external account creation | ✓ No external POSTs in flow |
| REQ-02.7 | Duplicate job handling | ✓ Existing deduplication |
| REQ-08.7 | User-friendly errors | ✓ Drupal messenger pattern used throughout |
