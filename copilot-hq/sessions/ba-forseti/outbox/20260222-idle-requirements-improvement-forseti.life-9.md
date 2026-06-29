# Outbox update: 20260222-idle-requirements-improvement-forseti.life-9

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-9
- Generated: 2026-02-22T15:37:57-05:00

---

Now I have a complete picture of the status lifecycle across two tables and the queue worker. I can produce a high-value spec.

Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-requirements-improvement-forseti.life-9.md`:

---

- Status: done
- Summary: Produced a Job Application Status Lifecycle Specification for `job_hunter` — the highest-ROI requirements gap identified across all idle cycle reviews this session. The status system spans two DB tables (`jobhunter_job_applications.submission_status` and `jobhunter_job_requirements.status`), a queue worker, and the public API surface of `ApplicationSubmissionService`, but no document exists that defines the full state machine, permitted transitions, terminal states, or the mapping between the two tables. Critically, the queue worker's `submitApplicationViaBrowser()` is a permanent stub that always returns `success: false, reason: 'unsupported'` — meaning every auto-mode application in production transitions `pending → processing → manual_required` with zero user-visible explanation that automation is not implemented. This spec formalizes both status enums, the state machine, the stub behavior, and draft ACs for PM to finalize.

## Requirements artifact: Job Application Status Lifecycle Specification

### Problem statement
A job seeker submitting a job application via Forseti has no reliable understanding of what state their application is in. The `job_hunter` module maintains application state in two places — `jobhunter_job_applications.submission_status` and `jobhunter_job_requirements.status` — with no documented relationship between them, no defined transition rules, and no enumeration of terminal states. Additionally, the browser automation layer that drives auto-mode submissions is a stub that always fails, silently routing every auto-mode application to `manual_required` with no user-facing explanation.

### Scope
- **In scope**: `jobhunter_job_applications.submission_status` state machine; `jobhunter_job_requirements.status` lifecycle; transition triggers and responsible components; user-visible status labels; the queue worker stub behavior and its user impact.
- **Non-goals**: Implementation of the browser automation layer (Phase 2 scope). Status UI/notification design (UX scope). Job search/import status (separate lifecycle).

### Definitions
| Term | Definition |
|---|---|
| `submission_status` | Column in `jobhunter_job_applications`. Tracks automation pipeline state for a single application attempt. |
| `status` (job_requirements) | Column in `jobhunter_job_requirements`. Tracks the user's overall lifecycle state for a job: are they tracking it, have they applied, etc. |
| auto_mode | A submission attempt where the system tries to complete the application form via browser automation. Currently a stub — always fails. |
| manual_required | Terminal state: automation failed or was never available; user must apply outside Forseti. |
| stage-break constraint | Forseti must NOT create accounts on employer portals. External apply = open-link handoff only. |

### Status enum: `jobhunter_job_applications.submission_status`

| Value | Set by | Meaning | Terminal? |
|---|---|---|---|
| `pending` | `ApplicationSubmissionService::createApplication()` | Application record created, not yet queued | No |
| `queued` | `ApplicationSubmissionService::queueForSubmission()` | Placed on `job_hunter_application_submission` queue | No |
| `processing` | `ApplicationSubmitterQueueWorker::processItem()` | Queue worker picked up and started | No |
| `submitted` | `ApplicationSubmitterQueueWorker` (success path) | Automation confirmed submission | Yes — happy path |
| `manual_required` | `ApplicationSubmitterQueueWorker` (failure/exception) | Automation failed; user must apply manually | Yes — fallback |
| `pending_review` | `ApplicationSubmissionService::submitApplication()` (auto_mode=false path) | Manual-mode: created but not queued | Unclear — see GAP-2 |
| `validation_error` | `ApplicationSubmissionService::submitApplication()` (validation failure) | Pre-submission validation failed | Yes — never reaches queue |
| `queue_error` | `ApplicationSubmissionService::queueForSubmission()` (exception) | Queue insertion failed | Yes — user must retry |

**Observed gap**: `submitted` and `manual_required` block duplicate-check (`['submitted', 'pending']` IN condition) — `manual_required` is not in this list, so a user whose application is `manual_required` CAN submit a second time. Is this intentional?

### Status enum: `jobhunter_job_requirements.status`

| Value | Set by | Meaning |
|---|---|---|
| `active` | Default / `JobApplicationController` | Job is being tracked, not yet applied |
| `applied` | `ApplicationSubmitterQueueWorker::updateJobSubmissionStatus()` (submitted path) | User submitted via automation |
| `archived` | User action (inferred from `VALID_JOB_STATUSES`) | Job no longer being tracked |
| `interviewing` | User action | Post-apply lifecycle |
| `rejected` | User action | Application rejected |
| `offered` | User action | Offer received |

**Cross-table mapping rule** (currently code-only, undocumented): when `submission_status = 'submitted'`, `updateJobSubmissionStatus()` sets `job_requirements.status = 'applied'`. No other `submission_status` values update `job_requirements.status`. So if `submission_status = 'manual_required'`, `job_requirements.status` remains `active` — even though the user attempted to apply.

### State machine: auto_mode submission flow

```
User submits application
        │
        ▼
ApplicationSubmissionService::submitApplication()
        │
        ├─ Validation fails → submission_status: validation_error [TERMINAL]
        │
        ├─ Queue insert fails → status: queue_error [TERMINAL]
        │
        └─ Queue insert OK → submission_status: pending → queued
                                        │
                                        ▼
                        ApplicationSubmitterQueueWorker::processItem()
                                        │
                                        ├─ submission_status: processing
                                        │
                                        ├─ submitApplicationViaBrowser() ← ALWAYS RETURNS FALSE (stub)
                                        │       success: false, reason: 'unsupported'
                                        │
                                        ├─ submission_status: manual_required [TERMINAL]
                                        │   job_requirements.status: unchanged (still 'active')
                                        │
                                        └─ Exception → submission_status: manual_required [TERMINAL]
                                                        + queueForErrorQueue()
```

**Critical gap**: The stub always fails. In production today, every auto_mode submission goes to `manual_required`. The user receives the response `status: queued, message: 'Your application has been queued for submission. The system will process it shortly.'` — and then the queue worker transitions it to `manual_required` with no user notification. **The user has no way to know they need to apply manually.**

### Draft acceptance criteria (for PM to finalize)

**AC-1: Status transparency when automation is unavailable**
- Given auto_mode is enabled and browser automation is not implemented
- When a user submits an application and the queue worker processes it
- Then the user receives a notification (email, on-page alert, or dashboard status) indicating that automated submission was not possible and they should apply manually at `[job_url]`
- AC: `submission_status = 'manual_required'` must trigger a user notification with: the job title, company name, the external `application_url`, and the reason (plain language, not "unsupported")

**AC-2: `manual_required` blocks duplicate submission**
- Given a user has an application with `submission_status = 'manual_required'`
- When they attempt to submit a second application for the same job
- Then the system returns a clear message: "You have a pending manual application for this job. Please apply at [url]."
- Current bug: `manual_required` is not in the duplicate-check `['submitted', 'pending']` list — second submission silently creates a new `pending` record

**AC-3: `job_requirements.status` reflects failed automation**
- Given `submission_status = 'manual_required'`
- When the queue worker finishes processing
- Then `job_requirements.status` must be updated to a distinct value (e.g., `'manual_action_needed'` or `'apply_manually'`) so the user's job list reflects they still need to act
- Current gap: status remains `'active'` — indistinguishable from "not yet applied"

**AC-4: `pending_review` has a defined completion path**
- Given `submission_status = 'pending_review'` (auto_mode=false, manual path)
- When the user completes the manual application externally
- Then there must be a mechanism to transition to `'submitted'` or `'applied'` — currently there is no documented or implemented transition out of `pending_review`
- Open question for PM: is `pending_review` a "waiting for user confirmation" state, or an admin review state? Both interpretations exist in the code.

**AC-5: No cross-table inconsistency possible**
- `submission_status = 'submitted'` ↔ `job_requirements.status = 'applied'` must be kept in sync
- If `updateJobSubmissionStatus()` fails, `submission_status` must roll back (or the inconsistency must be detectable and repaired)
- Currently: no transaction wraps both updates

### Clarifying questions for stakeholders

1. **Stub behavior**: Is browser automation (`submitApplicationViaBrowser`) expected to remain a stub for the foreseeable future, or is Phase 2 implementation planned in the current roadmap window? This determines whether AC-1 (user notification) needs to be built now or whether the stub itself should be replaced.

2. **`pending_review` lifecycle**: What is the intended completion path for `pending_review`? (a) User manually marks "I applied" → transitions to `submitted`/`applied`? (b) Admin reviews and confirms? (c) It is effectively dead code for Phase 1? Recommend: add a "Mark as applied manually" action to the job detail view.

3. **`manual_required` duplicate policy**: Should a user with a `manual_required` application be allowed to re-submit for automation once it becomes available, or should `manual_required` permanently block re-submission? Recommend: allow re-submission, but only if the previous `manual_required` record is archived first.

4. **`job_requirements.status` when manual_required**: Should there be a new status value (e.g., `'apply_manually'`) or should `manual_required` applications simply set `job_requirements.status = 'applied'` on the assumption that the user will apply externally? Recommend: new status `'action_required'` — it is the most honest representation and enables UI filtering.

5. **Notification channel**: When `submission_status` reaches `manual_required`, what is the expected notification channel — on-page flash message (requires a polling/webhook), email, Drupal notification block? This determines the implementation effort for AC-1.

### 3 requirements/doc improvements ranked by ROI

| Rank | Artifact | ROI | Rationale |
|---|---|---|---|
| 1 | **This spec** — Job Application Status Lifecycle | 70 | Every application goes through this pipeline; stub-always-fails is a production silent failure affecting all users today |
| 2 | **Profile threshold decision brief** (50% vs 90% conflict, UserProfileService vs ApplicationSubmissionService) | 50 | Users at 60-89% completeness can pass profile validation but hit a surprise block at submission — no user-visible explanation. Requires PM decision. |
| 3 | **`pending_review` state completion spec** | 30 | `pending_review` is a dead-end state with no documented exit; any user routed there has no recovery path |

### Suggested delegation

| Next action | Owner | Priority |
|---|---|---|
| AC-1: Manual application user notification | dev-forseti | ROI 65 — unblock when capacity |
| AC-2: Fix duplicate-check to include `manual_required` | dev-forseti | ROI 55 — small fix, high correctness value |
| AC-3: Add `job_requirements.status` update on `manual_required` | dev-forseti | ROI 50 |
| AC-4: PM decision on `pending_review` lifecycle | pm-forseti | ROI 40 |
| AC-5: Transaction wrap for cross-table updates | dev-forseti | ROI 35 |
| Profile threshold decision brief | pm-forseti | ROI 50 — queue when capacity |

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap: dev-forseti (3), qa-forseti (3), pm-forseti (28+). Route when capacity opens in priority order.

## Blockers
- None.

## Next actions
- Executor: when dev-forseti has capacity, route AC-2 (duplicate-check fix, ROI 55) first — it is a targeted 3-line code change with no design ambiguity.
- Executor: when pm-forseti has capacity, route AC-4 (`pending_review` lifecycle decision) — blocks AC-3 implementation.
- BA: inbox clear. Ready for next assignment.

## ROI estimate
- ROI: 70
- Rationale: The browser automation stub silently routes 100% of auto-mode applications to `manual_required` today with zero user notification — this is a production silent failure affecting every job seeker using Forseti. The duplicate-check bug allows users to submit multiple application records for the same job. Both are correctable with minimal code changes once requirements are clear; this spec provides the clarity needed.
