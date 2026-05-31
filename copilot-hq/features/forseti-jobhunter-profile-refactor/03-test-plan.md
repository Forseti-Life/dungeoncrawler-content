# Test Plan — forseti-jobhunter-profile-refactor

- Feature: forseti-jobhunter-profile-refactor
- Module: job_hunter
- QA owner: qa-forseti
- Status: groomed (next-release)
- AC source: 01-acceptance-criteria.md
- KB references: CSRF split-route pattern documented from forseti-jobhunter-profile release-c fix (job_hunter.delete_resume: methods:[POST] + _csrf_token:'TRUE'); forseti-ai-service-refactor extraction pattern

## Scope summary

Internal form refactor — extract `EducationHistorySubform` and `ResumeUploadSubform` from the 7425-line `UserProfileForm`. No new routes, permissions, or UI changes. Regression risk is behavioural: education save/load and resume upload/delete must be functionally identical post-refactor. Security risk: `job_hunter.delete_resume` CSRF must not be regressed.

## Pre-refactor baseline (current state)

- `UserProfileForm.php`: 7425 lines (pre-refactor baseline; AC-4 requires reduction below this)
- `src/Form/Subform/` directory: does not yet exist (expected post-implementation)
- `job_hunter.delete_resume` CSRF status: `methods:[POST]` + `_csrf_token:'TRUE'` (confirmed in release-c, commit 871cda11f)

## Test cases

### TC-01: EducationHistorySubform class exists (AC-1)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-profile-refactor-static` (unit/static) |
| Command | `ls src/Form/Subform/EducationHistorySubform.php` |
| Expected | File exists (exit 0) |
| Roles | N/A |
| Automation | Yes — bash static check |

### TC-02: ResumeUploadSubform class exists (AC-2)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-profile-refactor-static` (unit/static) |
| Command | `ls src/Form/Subform/ResumeUploadSubform.php` |
| Expected | File exists (exit 0) |
| Roles | N/A |
| Automation | Yes — bash static check |

### TC-03: UserProfileForm delegates to subforms (AC-1, AC-2)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-profile-refactor-static` (unit/static) |
| Command | `grep -c 'EducationHistorySubform\|ResumeUploadSubform' src/Form/UserProfileForm.php` |
| Expected | At least 2 references (one per subform) |
| Roles | N/A |
| Automation | Yes — bash static check |

### TC-04: CSRF intact on delete_resume route (AC-3)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-profile-refactor-static` (unit/static) |
| Command | `grep -A6 "job_hunter.delete_resume:" job_hunter.routing.yml` — must show `methods: [POST]` AND `_csrf_token: 'TRUE'` |
| Expected | Both lines present; no regression from refactor |
| Roles | N/A |
| Automation | Yes — bash static check |
| Note | This CSRF guard was added in release-c (commit 871cda11f). Regression here is a medium-severity security defect. |

### TC-05: UserProfileForm line count reduced (AC-4)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-profile-refactor-static` (unit/static) |
| Command | `wc -l src/Form/UserProfileForm.php` — result < 7425 |
| Expected | Line count below pre-refactor baseline of 7425 |
| Roles | N/A |
| Automation | Yes — bash static check |

### TC-06: PHP lint clean — all 3 files (AC-6)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-profile-refactor-static` (unit/static) |
| Command | `php -l UserProfileForm.php && php -l EducationHistorySubform.php && php -l ResumeUploadSubform.php` |
| Expected | All 3 exit 0 |
| Roles | N/A |
| Automation | Yes — bash static check |

### TC-07: Profile page renders without error (AC-5)

| Field | Value |
|---|---|
| Suite | `forseti-jobhunter-profile-refactor-functional` (functional/e2e) |
| Command | ALLOW_PROD_QA=1 curl authenticated GET `/jobhunter/my-profile` — HTTP 200, no PHP fatal |
| Expected | HTTP 200; no 500 error; Drupal watchdog clean |
| Roles | Authenticated (`access job hunter`) |
| Automation | Partial — curl smoke; full form interaction requires Playwright (deferred, Node absent) |

### TC-08: Regression — existing profile suite entries still pass (AC-5)

| Field | Value |
|---|---|
| Suite | Re-run existing `forseti-jobhunter-profile-e2e` suite entry |
| Command | Existing suite command (see qa-suites/products/forseti/suite.json, feature_id=forseti-jobhunter-profile) |
| Expected | Same PASS results as before refactor |
| Roles | Authenticated |
| Automation | Yes — re-run existing suite entry |

### TC-09: Regression — resume delete CSRF audit (AC-3, AC-5)

| Field | Value |
|---|---|
| Suite | Re-run relevant CSRF static check / site-audit-run |
| Command | `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh` |
| Expected | 0 violations, 0 CSRF regressions on delete_resume route |
| Roles | N/A |
| Automation | Yes — existing site-audit-run |

## Suite entry to activate at Stage 0

One new suite entry to add at Stage 0 (DO NOT add now):

```json
{
  "id": "forseti-jobhunter-profile-refactor-static",
  "label": "Profile refactor: subform files exist, UserProfileForm delegates, CSRF intact, line count reduced, PHP lint clean",
  "type": "unit",
  "feature_id": "forseti-jobhunter-profile-refactor",
  "command": "bash -c 'set -e; BASE=/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter; echo \"TC-01 EducationHistorySubform exists:\"; ls \"$BASE/src/Form/Subform/EducationHistorySubform.php\" && echo PASS || (echo FAIL && exit 1); echo \"TC-02 ResumeUploadSubform exists:\"; ls \"$BASE/src/Form/Subform/ResumeUploadSubform.php\" && echo PASS || (echo FAIL && exit 1); echo \"TC-03 delegation refs:\"; COUNT=$(grep -c \"EducationHistorySubform\\|ResumeUploadSubform\" \"$BASE/src/Form/UserProfileForm.php\"); [ \"$COUNT\" -ge 2 ] && echo \"PASS: $COUNT refs\" || (echo \"FAIL: only $COUNT refs\" && exit 1); echo \"TC-04 CSRF intact:\"; grep -A6 \"job_hunter.delete_resume:\" \"$BASE/job_hunter.routing.yml\" | grep -q \"methods: \\[POST\\]\" && grep -A6 \"job_hunter.delete_resume:\" \"$BASE/job_hunter.routing.yml\" | grep -q \"_csrf_token.*TRUE\" && echo PASS || (echo \"FAIL: CSRF regressed\" && exit 1); echo \"TC-05 line count reduced:\"; LC=$(wc -l < \"$BASE/src/Form/UserProfileForm.php\"); [ \"$LC\" -lt 7425 ] && echo \"PASS: $LC lines\" || (echo \"FAIL: $LC lines (not reduced)\" && exit 1); echo \"TC-06 lint:\"; php -l \"$BASE/src/Form/UserProfileForm.php\" && php -l \"$BASE/src/Form/Subform/EducationHistorySubform.php\" && php -l \"$BASE/src/Form/Subform/ResumeUploadSubform.php\"'",
  "artifacts": [
    "sessions/qa-forseti/artifacts/jobhunter-profile-refactor-static-latest/"
  ],
  "run_notes": [
    "TC-07 (profile page render) and TC-08 (resume upload E2E) require Playwright — deferred, Node absent. Flag for PM risk-acceptance at ship time.",
    "TC-09 regression: re-run site-audit-run.sh and existing forseti-jobhunter-profile-e2e suite entry."
  ]
}
```

## Non-automatable items (note to PM)

- **TC-07 full form interaction** (education add/edit/delete, resume upload/delete via UI): requires authenticated browser session or Playwright. Deferred — Node/Playwright absent on host. Recommend risk-acceptance at ship time or blocking on Playwright install.
- Education history and resume data integrity (no data loss post-refactor) cannot be verified statically; requires functional testing with real profile data.

## Regression risk areas

1. **CSRF regression on delete_resume** — highest risk; mitigated by TC-04 static check. Any refactor touching routing.yml or the form's delete handler must re-verify.
2. **Education history data loss** — if `EducationHistorySubform::submitForm()` has a different field-save path, data silently dropped. Mitigated by TC-08 E2E regression.
3. **Resume upload handler rebind** — `deleteResumeFileSubmit` must remain bound to `ResumeUploadSubform` in `UserProfileForm`. Mitigated by TC-03 delegation ref check.
4. **Form build order / AJAX regression** — profile form uses AJAX callbacks; subform extraction could break callback targets. Not fully coverable by static checks — flag to Dev.
