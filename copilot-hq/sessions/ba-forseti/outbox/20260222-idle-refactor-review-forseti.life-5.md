# Outbox update: 20260222-idle-refactor-review-forseti.life-5

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-5
- Generated: 2026-02-22T14:38:24-05:00

---

Now I have enough. The key finding is a critical data-source inconsistency between `calculateProfileCompleteness` (uses DB table via `isJobSeekerFieldCompleted`) and `validateForJobApplicationFromProfile` (uses User entity via `isFieldCompleted`) — they can disagree on the same field. Also the review's Issue #7 ("method not implemented") is a false positive — `calculateApplicationReadinessScore` IS implemented at line 469.

Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-5.md`:

---

- Status: done
- Summary: Reviewed `CODE_REVIEW_UserProfileService.md` against the actual `UserProfileService.php` (532 lines) for the `job_hunter` module (forseti.life). The most critical finding — not surfaced in the existing code review — is a data-source inconsistency: `calculateProfileCompleteness()` computes the percentage against the `jobhunter_job_seeker` DB table (via `isJobSeekerFieldCompleted`), while `validateForJobApplicationFromProfile()` checks fields against the User entity (via `isFieldCompleted`). These can return different results for the same user, meaning the `completeness` score and the `errors`/`warnings` returned in a single `validateForJobApplicationFromProfile()` call are derived from different data sources. Additionally, the existing review's Issue #7 ("calculateApplicationReadinessScore not implemented") is a false positive — the method exists at line 469. The 50% threshold in `UserProfileService` contradicts the 90% threshold in `ApplicationSubmissionService`, creating a validation gap where a user can "pass" profile validation but still fail application submission. All downstream seats are at or above queue cap; follow-ups documented for deferred routing.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/src/Service/CODE_REVIEW_UserProfileService.md`
- Cross-referenced against: `UserProfileService.php` (532 lines)

## Requirements clarity improvements (7 found)

### 1. Data-source split: completeness and validation use different sources — UNDETECTED by prior review (CRITICAL)
`calculateProfileCompleteness()` uses `isJobSeekerFieldCompleted($jobSeekerData, $field)` which reads the `jobhunter_job_seeker` DB table. `validateForJobApplicationFromProfile()` uses `isFieldCompleted($user, $field)` which reads the Drupal User entity. A user's resume could be set in the DB (completeness = 80%) but cleared on the User entity (validation error: "Resume upload required") — producing a return value of `{completeness: 80, ready: false}` where the two values are internally inconsistent.
- This is the most important gap not identified by the existing code review.
- Diff direction: All field checks in `validateForJobApplicationFromProfile()` must use `isJobSeekerFieldCompleted($jobSeekerData, $field)` (same source as completeness). The method should load `$jobSeekerData` once at the top and pass it to all checks. AC: For any given user, `validateForJobApplicationFromProfile()['completeness']` and the field presence checks in the same return value must agree on whether each field is present.

### 2. 50% threshold in UserProfileService contradicts 90% in ApplicationSubmissionService (HIGH — gate inconsistency)
`validateForJobApplicationFromProfile()` sets a blocking error at `$completeness < 50` (line ~266). `ApplicationSubmissionService::validateApplicationPrerequisites()` independently blocks at `$profile_completion < 90`. A user at 60% completeness will pass UserProfileService validation (`ready: true`) but be blocked by ApplicationSubmissionService. The user receives no warning at the profile stage about the 90% requirement.
- This connects directly to the gap documented in the `-16` artifact (ApplicationSubmissionService spec).
- Diff direction: The threshold in `UserProfileService` must be the single source of truth. Either (a) raise it to match ApplicationSubmissionService's 90%, or (b) have ApplicationSubmissionService call `UserProfileService::validateForJobApplication()` rather than re-checking completeness independently with a different threshold. Option (b) is preferred — it removes duplication. AC: A user at 89% completeness sees a blocking error from the profile screen, not a surprise block only at submission time.

### 3. Code review Issue #7 is a false positive — `calculateApplicationReadinessScore` is implemented (REVIEW QUALITY)
The code review states: "Method called but not implemented." Verified against actual source: `calculateApplicationReadinessScore` is defined starting at line 454 of `UserProfileService.php`. The review-recommended implementation (penalty-based formula) partially matches the actual implementation (base score = completeness, minus error/warning penalties). The action item checkbox `[ ] Implement readiness score calculation method` is therefore already done and should be marked closed.
- Diff direction (code review file only): Mark Issue #7 as "✅ Already implemented (line 454). Verify formula against PM's scoring intent." Remove from open action items.

### 4. `isJobSeekerFieldCompleted` uses inline field_mapping array — not extracted (not fixed from review)
The code review recommended extracting the field mapping to a `getFieldMapping()` method. The actual code still has the mapping as an inline local array inside `isJobSeekerFieldCompleted()`. The action item remains open. Additionally: `field_salary_expectation_min` maps to the single `salary_expectation` DB column — it is unclear whether the DB stores min, max, or a combined value, making the completeness check for salary ambiguous.
- Diff direction: Extract to `private const FIELD_COLUMN_MAP` array at the class level (not a method call, to ensure it is a single definition referenceable without instantiation). Document the salary_expectation column format: `min|max` or separate columns? If combined format, update the key to `field_salary_expectation` (drop `_min` suffix to avoid implying only min is stored).

### 5. Service locator `\Drupal::service()` inside `calculateProfileCompleteness` — not fixed from review (MEDIUM)
The code review flagged "Missing Error Handling" but the actual issue is `\Drupal::service('job_hunter.job_seeker_service')` called inline rather than injected — making the service untestable without bootstrapping Drupal. The review's fix recommendation added try-catch but kept the service locator pattern.
- Diff direction: Inject `JobSeekerService` via constructor: add `protected $jobSeekerService` property, add to `__construct()` and `create()`. Remove inline `\Drupal::service()` call. AC: `calculateProfileCompleteness()` can be called in a unit test with a mocked `JobSeekerService` without requiring a Drupal container.

### 6. `validateForJobApplication()` is a pass-through alias with no documented deprecation intent (LOW — maintenance)
Line 178: `validateForJobApplication()` calls `validateForJobApplicationFromProfile()` with no added behavior. The code review has `[ ] Clarify field mapping documentation` but doesn't address this. If `validateForJobApplication` is the stable public API, its relationship to `FromProfile` needs to be documented — is `FromProfile` the new canonical form and the other is deprecated?
- Diff direction: Add docblock to `validateForJobApplication()`: "Delegates to validateForJobApplicationFromProfile(). Both are currently equivalent. If a legacy user-entity-only path is needed, retain this method; otherwise, consider deprecating and removing in a future version."

### 7. Code review action items list has no owner or status — all 10 items are unchecked (LOW — process gap)
All 10 `[ ]` action items remain unchecked. There is no "Last updated" date, no assignee, and no sprint target on any item. The review was presumably done some time ago but has had zero documented follow-through.
- Diff direction: Update the code review file with: "Review date: [original date]. Status check date: 2026-02-22." Mark Issue #7 closed (already implemented). Assign owner (dev-forseti) and target sprint to Issues #1, #2, #3, #4, #5. Issues #6, #8, #9, #10 (caching, audit, salary bounds, nested logic) can be explicitly deferred with a revisit trigger.

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap: dev-forseti (3), qa-forseti (3), pm-forseti (28+). Route when capacity opens.

### FU-1 → dev-forseti: Fix data-source inconsistency in validateForJobApplicationFromProfile (ROI 60)
- File: `sites/forseti/web/modules/custom/job_hunter/src/Service/UserProfileService.php`
- Diff:
  1. At top of `validateForJobApplicationFromProfile()`, load: `$jobSeekerService = \Drupal::service('job_hunter.job_seeker_service'); $jobSeekerData = $jobSeekerService->loadByUserId($user->id());`
  2. Replace all `$this->isFieldCompleted($user, 'field_*')` calls inside this method with `$this->isJobSeekerFieldCompleted($jobSeekerData, 'field_*')`
  3. Retain `$user->getEmail()` check (email lives on User entity, not job_seeker table)
- Verification: Create a test user with resume set in `jobhunter_job_seeker` but `field_resume_file` empty on User entity. Call `validateForJobApplicationFromProfile()`. Confirm `ready: true` (no resume error) AND `completeness` reflects the DB-table value. Before fix, these would disagree. After fix, they agree.
- `roi.txt`: 60

### FU-2 → pm-forseti: Decision: unify profile completeness threshold (50% vs 90%) (ROI 50)
- Files: `UserProfileService.php` (line ~266), `ApplicationSubmissionService.php` (line ~237)
- Decision needed: Should the blocking threshold be 50% (UserProfileService) or 90% (ApplicationSubmissionService), and which service should own it?
- Recommendation: Raise UserProfileService threshold to 90% OR have ApplicationSubmissionService call `UserProfileService::validateForJobApplication()` as the single gating check. Eliminates surprise blocks at submission time.
- Once PM decides, dev-forseti implements the single-source threshold.
- `roi.txt`: 50

## Blockers
- None.

## Next actions
- Executor: route FU-1 to dev-forseti inbox when capacity opens (ROI 60 — highest priority).
- Executor: route FU-2 to pm-forseti as a decision request (ROI 50).
- Executor: update `CODE_REVIEW_UserProfileService.md` action item #7 to closed (already implemented) — recommend dev-forseti applies this as a housekeeping change.
- BA: no further action on this item.

## ROI estimate
- ROI: 45
- Rationale: The data-source inconsistency (GAP-1) means profile "ready" and "completeness" can contradict each other in the same API call — this is a live correctness bug that will cause misleading UI states for users. The 50% vs 90% threshold gap (GAP-2) creates a surprise hard block at submission time for users between 50–89% completeness. Both are directly actionable with targeted diffs and have no external dependencies.
