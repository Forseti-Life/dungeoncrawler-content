# BA Refactor Inventory — forseti.life (Product-wide)

**Seat:** ba-forseti
**Generated:** 2026-03-08
**Last updated:** 2026-03-08
**Release context:** 20260308-forseti-release-a
**Scope:** All active custom Drupal modules at `/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/`
**Handoff targets:** `pm-forseti` (decisions + prioritization), `dev-forseti` (implementation), `qa-forseti` (verification)

This is the single BA tracking artifact for forseti refactor candidates. It consolidates new findings with the module-specific artifact at `features/forseti-copilot-agent-tracker/ba-refactor-recommendations.md` (R1–R7, not duplicated here).

---

## Summary table

| ID | Module | Status | Problem | ROI | PM flag? | Dev-startable now? |
|---|---|---|---|---|---|---|
| JH-R1 | job_hunter | pending | CSRF missing on 7 application submission POST routes | 25 | No (security fix) | **Yes** |
| JH-R2 | job_hunter | pending | JobApplicationController God Object (4177 lines, 54 direct DB queries) | 15 | Yes — service extraction plan | After PM approves |
| JH-R3 | job_hunter | pending | UserProfileForm at 7425 lines — largest single file in forseti | 12 | Yes — extraction scope | After PM approves |
| JH-R4 | job_hunter | pending | WorkdayWizardService has no feature brief or AC (Phase 3 shipped without tracking) | 18 | Yes — feature brief creation | After PM creates feature brief |
| JH-R5 | job_hunter | pending | field_age_18_or_older has `db_column: 'age_18_or_older'` but column does not exist in schema | 6 | No | **Yes** |
| AI-R1 | ai_conversation | pending | AIApiService at 1260 lines contains 14 direct DB queries bypassing any service layer | 8 | No | **Yes** |
| AI-R2 | ai_conversation | pending | GenAiDebugController at 586 lines is debug-only but ships in production code without a flag | 5 | Yes — ship/remove decision | After PM decides |
| CAT-R1–R7 | copilot_agent_tracker | pending | See `features/forseti-copilot-agent-tracker/ba-refactor-recommendations.md` (R1=15, R2=12, R3=10, R4=8, R5=6, R6=6, R7=40) | 40 (R7) | Yes for R1/R2/R5 | Yes for R3/R4/R6/R7 |

---

## JH-R1 — CSRF missing on 7 application submission POST routes (ROI 25)

**Module:** `job_hunter`
**Files:** `job_hunter.routing.yml`
**Current behavior/risk:** Routes `application_submission_step3`, `step3_short`, `step4`, `step4_short`, `step5`, `step5_short`, and `step_stub_short` accept POST requests with `_permission: 'access job hunter'` but have no `_csrf_token: TRUE`. These were added after the GAP-002 CSRF remediation (`694fc424f`). The `submit-application` route (step5) initiates external ATS job application automation — a CSRF attack could trigger unauthorized job submissions on behalf of a logged-in user.

**Proposed refactor outcome:** Add `_csrf_token: TRUE` to all 7 routes following the precedent of `694fc424f`. Alternatively, confirm that controller-level CSRF via `X-CSRF-Token` header is present for any AJAX routes (see KB lesson `20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md`).

**PM flag:** Not required (security fix, same class as GAP-002 which was already P1).

**Dev definition of done:**
- [ ] All 7 routes show CSRF=YES in the POST-route enumerator.
- [ ] Re-run: `python3 enumerate_post_routes.py job_hunter.routing.yml` — no "access job hunter" POST route shows CSRF=NO.
- [ ] QA confirms step3/4/5 pages still render and POST correctly with session cookie (no CSRF rejection of legitimate user actions).

**QA verification:** POST to `/jobhunter/application-submission/{job_id}/submit-application` as authenticated user via form → expect 200. POST same route from a cross-origin form without valid token → expect 403.

---

## JH-R2 — JobApplicationController God Object (ROI 15)

**Module:** `job_hunter`
**Files:** `src/Controller/JobApplicationController.php` (4177 lines, 54 direct `$this->database` calls)
**Current behavior/risk:** `JobApplicationController` contains the full application submission workflow (steps 1–5 page renders), AJAX handlers, direct DB queries, ATS platform detection, metadata management, and submission attempt logging. An `ApplicationSubmissionService.php` (652 lines) already exists but the controller bypasses it for most database operations. Every change to submission logic risks collateral regressions on unrelated routes.

**Proposed refactor outcome:**
- Move all 54 direct DB queries to `ApplicationSubmissionService` or a new `ApplicationAttemptService`.
- Split step-render methods into a `ApplicationSubmissionController` separate from existing AJAX/action handlers.
- Controller becomes a thin dispatcher: calls service, returns render array.

**PM flag:** Required — approve extraction scope and phasing. Recommended Phase 1 (1–2 cycles): move DB queries to `ApplicationSubmissionService`. Phase 2: split controller file.

**Dev definition of done:**
- [ ] `grep -c '\$this->database' JobApplicationController.php` returns 0 after DB extraction.
- [ ] All step3/4/5 routes continue to render correctly post-refactor (QA smoke test).
- [ ] `ApplicationSubmissionService` has PHPDoc for each new public method.

**QA verification:** Full smoke test of `/jobhunter/application-submission` and steps 1–5 pages after refactor.

---

## JH-R3 — UserProfileForm God Object (ROI 12)

**Module:** `job_hunter`
**Files:** `src/Form/UserProfileForm.php` (7425 lines — largest file in forseti)
**Current behavior/risk:** Single form class handles: profile display, resume upload, consolidated JSON sync, education history, tailoring status, parsed data editing, job search preferences, and on-save queue coordination. It is the primary user-facing form for the job hunter module. At 7425 lines it is impossible to unit test and carries maximum blast radius for any change.

**Proposed refactor outcome:**
- Extract education history form section to `EducationHistorySubform` or `EducationHistoryFormElement`.
- Extract resume upload/management to `ResumeUploadSubform`.
- Extract consolidated JSON sync logic to a dedicated `ProfileJsonSyncService`.
- Keep `UserProfileForm` as an orchestrating shell.

**PM flag:** Required — extraction scope is large; risk of regression. Phased approach recommended.

**Dev definition of done (Phase 1 only):**
- [ ] `ProfileJsonSyncService` extracted with all `syncFormFieldsToConsolidatedJson` and `getConsolidatedValue` logic.
- [ ] `UserProfileForm.php` drops below 5000 lines.
- [ ] Profile save + reload round-trip works correctly for all field types (manual QA verification).

**QA verification:** `/jobhunter/profile/edit` — set all fields, save, reload — verify all values persisted. Check consolidated JSON in DB directly.

---

## JH-R4 — WorkdayWizardService shipped without feature brief or AC (ROI 18)

**Module:** `job_hunter`
**Files:** `src/Service/WorkdayWizardService.php` (460 lines), `src/Service/WorkdayPlaywrightRunner.php` (128 lines), 5 new `application_submission` routes
**Current behavior/risk:** WorkdayWizardService implements Phase 3 (external ATS Workday portal automation via Playwright) which was explicitly deferred in `features/forseti-jobhunter-browser-automation/01-acceptance-criteria.md`. The service was shipped in commit `7dea91e8f` without a feature brief, AC, or QA test plan. No definition-of-done exists. `forseti-jobhunter-browser-automation/02-implementation-notes.md` still says "no new code was written this cycle."

**Proposed refactor outcome:**
- Create `features/forseti-jobhunter-application-submission/feature.md` to formally track the 5-step submission automation workflow.
- Author AC items covering: credential retrieval in Playwright context, step advancement success/failure, submission confirmation, external ATS unavailability handling.
- Update `forseti-jobhunter-browser-automation/02-implementation-notes.md` to document WorkdayWizardService as new code.

**PM flag:** Required — PM must confirm `forseti-jobhunter-application-submission` is a supported feature this cycle and assign an AC authoring task.

**Dev definition of done:**
- [ ] `features/forseti-jobhunter-application-submission/feature.md` exists.
- [ ] `01-acceptance-criteria.md` exists with at minimum a happy-path AC for `advanceStep()` and `advanceWizardAutoSingleSession()`.
- [ ] `forseti-jobhunter-browser-automation/02-implementation-notes.md` updated with WorkdayWizardService reference.

**QA verification:** Verify against AC once written. Pre-verification: `grep -n 'WorkdayWizardService' features/forseti-jobhunter-browser-automation/02-implementation-notes.md` should return results.

---

## JH-R5 — field_age_18_or_older DB column mapping inconsistency (ROI 6)

**Module:** `job_hunter`
**Files:** `src/Form/UserProfileForm.php`
**Current behavior/risk:** The field mapping for `field_age_18_or_older` specifies `db_column: 'age_18_or_older'`, but no DB update hook adds this column to `jobhunter_job_seeker`. The `getConsolidatedValue` method falls back to `$job_seeker_profile->age_18_or_older` which silently returns `null` (no DB error). The fallback read is always empty. Other new fields added in the same commit correctly use `db_column: ''`. This creates false expectations — a future dev may add a DB column expecting it to be read, but the JSON path is the correct storage.

**Proposed refactor outcome:** Change `db_column: 'age_18_or_older'` to `db_column: ''` for consistency with the other 5 new fields, OR add a DB update hook to create the projection column.

**PM flag:** Not required.

**Dev definition of done:**
- [ ] Either `db_column: ''` for `field_age_18_or_older` OR a DB update hook for `age_18_or_older` projection column in `job_hunter.install`.
- [ ] `grep -n "age_18_or_older" job_hunter.install` returns either 0 (no column needed) or a valid schema definition.

**QA verification:** Set `field_age_18_or_older` on profile form, save, reload — value must persist from JSON, no PHP notices.

---

## AI-R1 — AIApiService direct DB queries bypassing service layer (ROI 8)

**Module:** `ai_conversation`
**Files:** `src/Service/AIApiService.php` (1260 lines, 14 direct `$this->database` calls)
**Current behavior/risk:** `AIApiService` is a central AI logic service (1260 lines) that mixes AI API calls, conversation state management, and 14 raw DB queries. No dedicated storage/repository layer exists for `ai_conversation`. Pattern mirrors the `copilot_agent_tracker` R4 finding (direct DB in a non-controller service).

**Proposed refactor outcome:** Extract conversation storage operations (session create/load/update/delete) to a new `AIConversationStorageService`. `AIApiService` calls storage; no `$this->database` references remain in `AIApiService`.

**PM flag:** Not required (internal implementation). Coordinate with `dev-forseti` on whether to combine with any planned `ai_conversation` feature work.

**Dev definition of done:**
- [ ] `AIConversationStorageService` created with all conversation CRUD methods.
- [ ] `grep -c '\$this->database' AIApiService.php` returns 0.
- [ ] All AI conversation routes continue to function (QA smoke test on `/talk-with-forseti`).

**QA verification:** `/talk-with-forseti` for authenticated user — start conversation, get AI response, reload page to confirm session persistence.

---

## AI-R2 — GenAiDebugController ships in production without a feature flag (ROI 5)

**Module:** `ai_conversation`
**Files:** `src/Controller/GenAiDebugController.php` (586 lines)
**Current behavior/risk:** `GenAiDebugController` is a full debug/inspection controller for AI conversations. It ships in production code with no feature flag or environment-based gating. If the routes are accessible to non-admin users, this exposes AI conversation internals.

**Proposed refactor outcome:** Confirm all routes require `administer site configuration` or equivalent admin-only permission (check `ai_conversation.routing.yml`). If routes are admin-only: document this and close the risk. If any route is accessible to authenticated non-admins: gate behind admin permission or add `$this->requestStack->getCurrentRequest()->get('_route')` check.

**PM flag:** Required — decide whether GenAiDebugController stays in production or is guarded by `DRUPAL_ENV != production` check.

**Dev definition of done:**
- [ ] All routes in `GenAiDebugController` require `administer site configuration` or are explicitly documented as admin-only in routing comments.
- [ ] `grep "GenAiDebugController" ai_conversation.routing.yml` — all routes show admin-level permission.

**QA verification:** `/admin/ai-conversation/debug` (or equivalent) returns 403 for anonymous and authenticated non-admin users.

---

## PM/Dev handoff actions — forseti product-wide

| Action | Owner | Priority | Unblocked? |
|---|---|---|---|
| Apply CSRF fix to 7 application submission POST routes (JH-R1) | `dev-forseti` | **P0 — security** | **Yes** |
| Create feature brief for forseti-jobhunter-application-submission (JH-R4) | `pm-forseti` | P1 | **Yes** |
| Confirm `GenAiDebugController` route permissions (AI-R2) | `pm-forseti` | P1 | **Yes** |
| Approve extraction scope for `JobApplicationController` (JH-R2) | `pm-forseti` | P2 | **Yes** |
| Approve extraction scope for `UserProfileForm` (JH-R3) | `pm-forseti` | P2 | **Yes** |
| Fix `field_age_18_or_older` db_column inconsistency (JH-R5) | `dev-forseti` | P3 — low effort | **Yes** |
| Extract `AIConversationStorageService` (AI-R1) | `dev-forseti` | P2 — no PM needed | **Yes** |
| See also: copilot_agent_tracker R1–R7 | `dev-forseti-agent-tracker` | Prioritized in `features/forseti-copilot-agent-tracker/ba-refactor-recommendations.md` | Per that doc |
