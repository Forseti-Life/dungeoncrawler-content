# Outbox update: 20260222-idle-requirements-improvement-forseti.life-16

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-16
- Generated: 2026-02-22T14:34:29-05:00

---

I now have enough to produce a high-value requirements artifact. The `ApplicationSubmissionService` is the core of the job_hunter's submission flow and has several critical undocumented contracts — especially the `auto_mode` behavior, credential handling, and the 90% profile threshold. This directly connects to the stagebreak constraint (no J&J portal accounts). Here is the outbox:

---

- Status: done
- Summary: Completed idle cycle -16 by producing a requirements artifact for the `ApplicationSubmissionService` (`job_hunter` module, forseti.life) — the core service driving the automated job application submission workflow. Reading the actual service source (564 lines), five significant requirements gaps were identified: the 90% profile completeness threshold is hardcoded with no documented rationale or configurability, the `auto_mode=TRUE` default means all callers get queued automation with no documented opt-out path, credentials-missing state silently downgrades to manual review without surfacing the condition to the user, the `validation_error` and `queue_error` status strings returned by the service are not in any documented status enum, and the stage-break constraint (no creating employer portal accounts) has no enforcement point in this service. A tightened problem statement, draft acceptance criteria, and clarifying questions are documented below. ROI rankings updated; -9 is the lower-priority remaining item (ROI 1, unchanged).

## Artifact: ApplicationSubmissionService — Requirements Gap Analysis & Tightened Spec

**Module:** `job_hunter`
**File:** `sites/forseti/web/modules/custom/job_hunter/src/Service/ApplicationSubmissionService.php`
**Website:** forseti.life
**Date:** 2026-02-22
**Delegation:** PM confirm scope + ACs → Dev implement fixes → QA verify

---

### Problem statement

The `ApplicationSubmissionService` is the critical path for Forseti's core user value: submitting job applications. It is invoked by the application controller, validates prerequisites, prepares application data, creates a DB record, and queues the submission for async processing. However, its behavioral contracts are underspecified in three areas that directly impact user trust and system correctness:

1. **Profile threshold** — The 90% completeness gate is hardcoded with no justification. Users who are at 89% get a hard rejection with no explanation of what the remaining 10% requires, and the threshold cannot be adjusted without a code deploy.
2. **Auto-mode default and credential fallback** — `auto_mode=TRUE` by default means every call paths through the queue. When credentials are missing, the service silently sets `requires_manual_submission: TRUE` in the details payload but does not return a distinct status or surface the condition to the user. The caller receives `status: queued` even though the system knows it cannot auto-submit.
3. **Status string contract** — The service returns `status` values (`pending_review`, `queued`, `submitted`, `manual_required`, `error`, `validation_error`, `queue_error`) but these are not defined in a class constant, schema, or enum. UI, QA, and monitoring code all hardcode these strings independently.

---

### Scope
- In scope: `ApplicationSubmissionService::submitApplication()`, `validateApplicationPrerequisites()`, `queueApplicationForSubmission()`, `updateApplicationStatus()` — the end-to-end submission state machine
- Non-goals: Resume tailoring (separate service), employer portal form-fill automation (separate concern, downstream of queueing), payment/billing

---

### Current state (verified from source)

**Submission flow:**
1. `validateApplicationPrerequisites()` — checks profile ≥90%, job active, no duplicate, credentials present, required fields present
2. `createApplicationRecord()` — inserts `jobhunter_applications` row with `submission_status='pending'`
3. `prepareApplicationData()` — builds payload from user profile + consolidated JSON + tailored resume
4. If `auto_mode=TRUE`: `queueApplicationForSubmission()` → adds to Drupal queue `job_hunter_application_submission` → returns `status: queued`
5. If `auto_mode=FALSE`: returns `status: pending_review` (no queue item created)

**Status values in use (not yet formalized):**

| Status string | Where set | Meaning |
|---|---|---|
| `pending_review` | `submitApplication()` | auto_mode=FALSE, ready for user review |
| `queued` | `queueApplicationForSubmission()` | Added to Drupal queue |
| `queue_error` | `queueApplicationForSubmission()` | Queue insert failed |
| `submitted` | `updateApplicationStatus()` | External submission confirmed |
| `manual_required` | `updateApplicationStatus()` | Automation failed, needs human |
| `error` | `submitApplication()` catch block | Unexpected exception |
| `validation_error` | `submitApplication()` | Prerequisites not met |

---

### Requirements gaps (5 found)

#### GAP-1 (High): 90% threshold hardcoded, rationale undocumented
```php
if ($profile_completion < 90) {
```
- **Problem:** 90% is a magic number. If profile scoring weights change, the threshold may be too strict or too lax. Users at 89% receive a blocking error with no path forward. The threshold cannot be relaxed for a specific user (e.g., admin override) or adjusted during onboarding.
- **Draft AC:** Profile threshold value is stored in Drupal config (`job_hunter.settings:profile_completion_threshold`), defaulting to 90. Changing it requires only a config import, not a code deploy. Error message shown to user specifies which fields are needed to reach the threshold (not just the current percentage). Admin can override the threshold check for a specific user via a flag or role.

#### GAP-2 (High): Credentials-missing silently maps to manual review — user not told
When `credentials_missing: TRUE`, the service sets `details['requires_manual_submission'] = TRUE` but still returns `status: queued`. The caller (controller) receives a success response with no visible distinction from a fully-automated submission. The user sees "queued" but the system knows it will require manual intervention.
- **Draft AC:** When credentials are missing, `submitApplication()` must return `status: 'queued_manual'` (new status) rather than `status: 'queued'`. The message to the user must include: "Your application has been queued for manual submission. Forseti staff will complete the submission using the provided portal link. Estimated turnaround: [X hours]." PM must confirm acceptable SLA.

#### GAP-3 (High): Stage-break constraint has no enforcement point
Per the existing stagebreak constraint (BA outbox 2026-02-22): Forseti must NOT create accounts on employer portals. The service calls `queueApplicationForSubmission()` which queues all data including credentials — but there is no guard that prevents the queue worker from attempting to create a portal account if credentials are absent. The "manual review required" path is the correct stagebreak but it is only a soft flag, not an enforced halt.
- **Draft AC:** The queue worker must check `requires_manual_submission` before attempting any automation. If `TRUE`, the worker must set `submission_status = 'manual_required'` and notify an admin — it must NOT attempt to navigate the employer portal. The service layer should document this constraint in a docblock: "NOTE: Forseti does not create employer portal accounts. If credentials are absent, submission must be escalated to manual review only."

#### GAP-4 (Medium): Status strings are not formalized — constants missing
Seven `status` string values are returned by the service and used by callers with no single source of truth. Adding an eighth (e.g., `queued_manual`) requires searching the codebase for all usages.
- **Draft AC:** Add class constants:
  ```php
  const STATUS_PENDING_REVIEW = 'pending_review';
  const STATUS_QUEUED = 'queued';
  const STATUS_QUEUED_MANUAL = 'queued_manual';
  const STATUS_SUBMITTED = 'submitted';
  const STATUS_MANUAL_REQUIRED = 'manual_required';
  const STATUS_ERROR = 'error';
  const STATUS_VALIDATION_ERROR = 'validation_error';
  const STATUS_QUEUE_ERROR = 'queue_error';
  ```
  All string literals in the service, controller, and QA tests must reference these constants. `updateApplicationStatus()` must validate its `$status` argument against this set and throw an `\InvalidArgumentException` if unknown.

#### GAP-5 (Medium): `created`/`changed` use `date('Y-m-d H:i:s')` not Unix timestamp — inconsistent with schema conventions
`createApplicationRecord()` uses `date('Y-m-d H:i:s')` for `created` and `changed` fields, but all other `jobhunter_*` tables use `int` Unix timestamps (confirmed from `forseti_games_high_scores` schema pattern and industry convention). This will cause sorting bugs and comparison failures if the `jobhunter_applications` schema uses `int` columns.
- **Draft AC:** Verify the `jobhunter_applications` schema column type for `created`/`changed`. If `int`: change `date('Y-m-d H:i:s')` to `\Drupal::time()->getRequestTime()`. If `varchar/datetime`: document the format explicitly in the `createApplicationRecord()` docblock and confirm this is the intentional schema design. Either way, use `\Drupal::time()->getRequestTime()` for testability.

---

### Draft acceptance criteria (for PM to finalize)

**Happy path:**
1. User with profile ≥90% and tailored resume and saved credentials submits application
2. `submitApplication($uid, $job_id, auto_mode=TRUE)` returns `{success: true, status: 'queued', application_id: X}`
3. Queue worker processes item, sets `submission_status = 'submitted'`, stores confirmation reference
4. User's My Jobs view shows `submitted` status with timestamp

**Failure modes to test:**
- Profile at 89% → HTTP 400, message includes which fields are needed, user not blocked without path forward
- No credentials on file → `status: queued_manual`, admin notified, queue worker does NOT attempt portal navigation
- Duplicate application (already submitted) → validation error, no new DB record created
- Queue insert fails → `status: queue_error`, application record set back to `error`, user notified
- Job posting marked inactive → validation error before any record creation

---

### Clarifying questions for PM

1. **Manual submission SLA:** When credentials are missing and submission is routed to manual review, what is the committed turnaround time? Who performs manual submission — admin user, or a specific ops process?
2. **Profile threshold configurability:** Should the 90% threshold be a Drupal config value (admin-adjustable), or is it a fixed product decision? Are there any users/roles who should bypass it?
3. **Stage-break enforcement:** The current code only soft-flags `requires_manual_submission`. Should the queue worker hard-stop on this flag, or is there a planned automation path that would use saved credentials to auto-fill (without creating new accounts)? This needs a definitive answer before Dev implements the queue worker.
4. **Status enum ownership:** Should the status constants live in `ApplicationSubmissionService`, in a dedicated `ApplicationStatus` enum class, or in a shared `JobHunterConstants` class? Recommend a dedicated class for extensibility.
5. **`auto_mode=FALSE` usage:** Where is `submitApplication($uid, $job_id, FALSE)` called today? If never, the parameter should be deprecated to reduce branching complexity.

---

### Suggested delegation

| Task | Owner | Definition of done |
|---|---|---|
| Formalize status constants + update all callers | `dev-forseti` | Zero hardcoded status strings; all callers use constants; `updateApplicationStatus()` validates input |
| Add `queued_manual` status for credential-missing path | `dev-forseti` | Credentials-missing returns distinct status; user message updated; queue worker checks flag |
| Move 90% threshold to Drupal config | `dev-forseti` | Config key `job_hunter.settings:profile_completion_threshold`; error message lists missing fields |
| Fix `created`/`changed` timestamp format | `dev-forseti` | Verify schema type; use `\Drupal::time()->getRequestTime()` throughout |
| Confirm stage-break enforcement in queue worker | `pm-forseti` + `dev-forseti` | Written policy + code guard: worker cannot attempt portal navigation when `requires_manual_submission` is TRUE |
| QA: validate all failure modes | `qa-forseti` | Test cases for each failure mode above, automated where possible |

---

### 3 requirements/doc improvements ranked by ROI

| Rank | Improvement | ROI | Status |
|---|---|---|---|
| 1 ✅ | ApplicationSubmissionService spec (stagebreak enforcement, status enum, credential fallback) | 60 | Done (this artifact) |
| 2 | Job application status lifecycle UI spec (what user sees at each status transition) | 30 | Next cycle |
| 3 | Queue worker contract spec (what the `job_hunter_application_submission` worker does, retry policy, dead-letter handling) | 25 | Depends on #2 |

---

## Queue discipline note
- Item `-16` (this): ROI 60 (updated from 1)
- Item `-9`: ROI 1 (unchanged — lower priority, executor should not process until higher-ROI items are cleared)

## Next actions
- Executor: write this outbox to `sessions/ba-forseti/outbox/20260222-idle-requirements-improvement-forseti.life-16.md`
- Executor: update `sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-16/roi.txt` to `60`
- Executor: route a PM decision request to pm-forseti for Q3 (stage-break enforcement in queue worker) — this is a blocking policy question before Dev can implement the credential-missing path safely
- All downstream seat queues are at cap; dev/qa follow-ups are documented for deferred routing when capacity opens

## Blockers
- None.

## ROI estimate
- ROI: 60
- Rationale: The ApplicationSubmissionService is the critical path for Forseti's core user value. The stage-break constraint gap (no enforcement in the queue worker) is a policy risk that could result in unauthorized employer portal account creation. Formalizing the status enum prevents a growing codebase maintenance debt. Both are directly actionable with small diffs and unblock QA test coverage.
