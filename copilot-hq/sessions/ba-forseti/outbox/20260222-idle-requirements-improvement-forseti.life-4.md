# Outbox update: 20260222-idle-requirements-improvement-forseti.life-4

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-4
- Generated: 2026-02-22T10:45:05-05:00

---

This is item 4, which prior cycle ranked as the top remaining improvement: post-apply status lifecycle spec (ROI 250). Items 5 and 6 both still show roi.txt=1 (executor did not apply prior cycle's roi.txt update request). I'll produce the artifact and re-request roi.txt updates for items 5 and 6.

---

**Artifact: `sessions/ba-forseti/artifacts/20260222-jobhunter-post-apply-lifecycle/requirements.md`**

```markdown
# Requirements Artifact: JobHunter Post-Apply Status Lifecycle

- Feature: forseti-jobhunter-e2e-flow (sub-spec)
- Website: forseti.life
- Module: job_hunter
- BA: ba-forseti
- Date: 2026-02-22
- Depends on: 20260222-jobhunter-e2e-flow-requirements (Phase 4: Tracking)

---

## Problem Statement

The forseti-jobhunter-e2e-flow feature brief defines the terminal state `applied_submitted` but does not specify what happens after submission. In practice, job applications progress through follow-up stages (interview scheduled, offer received, rejected, withdrawn) that users need to track. Without defining these states now — even minimally — two failure modes arise: (1) Dev implements a single-terminal-state model that requires a schema migration to extend later, and (2) the tracking view ships without a way for users to close the loop on their applications, making the feature incomplete for its stated goal of "tracking job status in-site." This spec defines the minimal post-apply lifecycle that ships with the current work item and draws a clear line for what is deferred.

---

## Scope

**In scope (minimal — ships with current E2E work item):**
- Status values allowed after `applied_submitted`
- Status transition rules (which states can follow which)
- Fields required for each post-apply transition
- UI entry point for updating post-apply status (in /jobhunter tracking view)
- How post-apply history is displayed

**Explicitly deferred (out of scope for this work item):**
- Automated detection of post-apply status (e.g., scraping J&J portal for response)
- Email/notification on status change
- Bulk status update across multiple jobs
- Calendar integration for interview scheduling
- Offer comparison / salary negotiation tooling

---

## Definitions

| Term | Definition |
|------|------------|
| Terminal state | A status from which no further transitions are expected (e.g., `rejected`, `offer_accepted`, `withdrawn`) |
| Active state | A status where the application is still in progress and further transitions are expected |
| Status transition | A user-initiated change from one job status to the next valid state |
| Tracking view | The in-site UI at /jobhunter (or job detail) where users view and update job status |

---

## Minimal Post-Apply Status Model

### Status values (full lifecycle)

| Status | Type | Description | Comes after |
|--------|------|-------------|-------------|
| `discovered` | Active | Job found via discovery or manual add | — (initial) |
| `manually_added` | Active | Job added by user without discovery | — (initial) |
| `readiness_to_apply` | Active | User has marked job as ready to apply | `discovered`, `manually_added` |
| `applied_submitted` | Active | User has submitted application externally; recorded in Forseti | `readiness_to_apply` |
| `interview_scheduled` | Active | Interview has been arranged | `applied_submitted` |
| `offer_received` | Active | Employer has extended an offer | `interview_scheduled`, `applied_submitted` |
| `offer_accepted` | Terminal | User accepted the offer | `offer_received` |
| `offer_declined` | Terminal | User declined the offer | `offer_received` |
| `rejected` | Terminal | Application was rejected by employer | `applied_submitted`, `interview_scheduled` |
| `withdrawn` | Terminal | User withdrew the application | `applied_submitted`, `interview_scheduled`, `readiness_to_apply` |

**Design constraint:** only the statuses in the "comes after" column are valid predecessor states for each transition. Any other transition must be rejected with a validation error.

### Minimal viable scope for current work item

The current E2E work item MUST implement at minimum:
- `applied_submitted` → `rejected`
- `applied_submitted` → `withdrawn`
- `applied_submitted` → `interview_scheduled`

The remaining transitions (`offer_received`, `offer_accepted`, `offer_declined`) SHOULD be implemented if the schema supports them without extra work; otherwise defer with a TODO in code and document in knowledgebase.

---

## Post-Apply Transition Data Contract

Each status transition (post `applied_submitted`) requires:

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| job_id | int / uuid | Yes | FK to job record |
| user_id | int | Yes | Drupal UID; must match session user |
| from_status | enum | Yes | Previous status value |
| to_status | enum | Yes | New status value; must be a valid successor |
| transition_timestamp | datetime | Yes | Auto-set to current datetime |
| notes | text | No | User-entered notes (e.g., "Interview on 2026-03-01 at 2pm") |
| interview_date | date | No | Required if to_status = `interview_scheduled` |
| outcome_date | date | No | Required if to_status is terminal (`rejected`, `offer_accepted`, `offer_declined`, `withdrawn`) |

**Access control:** same rule as all job endpoints — `user_id` must equal current session UID; cross-user access returns 403.

---

## UI Entry Point

Post-apply status updates are made from the job detail view (accessible from /jobhunter/my-jobs or the tracking dashboard).

### Interaction flow
1. User opens job detail for a job in `applied_submitted` (or later active) state
2. A "Update Status" control is visible (button or dropdown); shows only valid next states
3. User selects new status; optional notes and date field appear if applicable
4. User confirms; system persists the transition and shows updated status + history
5. If the user selects a terminal state, the job is moved to a "Closed" section of the job list

### Job list behavior
- Active jobs (`applied_submitted`, `interview_scheduled`, `offer_received`): shown in main list
- Terminal jobs (`rejected`, `offer_accepted`, `offer_declined`, `withdrawn`): shown in a "Closed" section or hidden by default with a toggle

---

## Draft Acceptance Criteria (for PM to finalize)

### Status model
- [ ] All 10 status values are defined in a single source of truth (DB enum, PHP constant, or config entity)
- [ ] Invalid transitions are rejected server-side with a clear error (not just client-side validation)
- [ ] `from_status` is recorded on every transition for full history

### Minimum transitions (must ship)
- [ ] `applied_submitted` → `rejected`: user can record rejection; outcome_date required
- [ ] `applied_submitted` → `withdrawn`: user can withdraw; outcome_date required
- [ ] `applied_submitted` → `interview_scheduled`: user can record interview; interview_date required

### UI
- [ ] "Update Status" control shows only valid next states for the current status
- [ ] Terminal-state jobs are visually separated from active jobs in the list view
- [ ] Status history (all transitions with timestamps and notes) is visible on the job detail page
- [ ] All status update forms validate required fields (interview_date, outcome_date) before submission

### Access control
- [ ] Status update endpoints verify user_id == session UID; return 403 otherwise
- [ ] Unauthenticated requests redirect to login

### Verification
- [ ] Manual checklist: create a job, progress it to `applied_submitted`, then transition to each of the three minimum states; verify history shows all transitions with correct timestamps
- [ ] Attempt an invalid transition (e.g., `discovered` → `rejected` directly); verify server returns error

---

## Clarifying Questions for Stakeholders

1. **Schema extensibility**: Is the current job status field a DB enum, a VARCHAR, or a Drupal config entity? A DB enum will require a migration to add new values; VARCHAR or config entity does not. Dev must confirm before BA can finalize which states ship now vs. later.
2. **Minimum viable set confirmation**: PM must confirm whether the three minimum transitions (`rejected`, `withdrawn`, `interview_scheduled`) are sufficient for the current work item, or whether `offer_received` must also ship to satisfy the OKR.
3. **History storage**: Is there already a status history/event log table in the `job_hunter` schema? If not, this spec requires creating one (it is referenced in the E2E tracking requirements). Dev must confirm.
4. **"Closed" job list UX**: Should terminal-state jobs be hidden by default (with a "Show closed" toggle) or visible in a separate section? This is a PM/UX decision that affects the tracking view layout.
5. **`withdrawn` state semantics**: Does `withdrawn` mean the user no longer wants to track this job at all (should be archived/deleted) or just that the application was withdrawn but the record should remain? This affects whether withdrawn jobs appear in the job list at all.

---

## Suggested Next Delegation

| Role | Action | Priority |
|------|--------|----------|
| **PM (pm-forseti)** | Confirm minimum transitions for current work item (Q2); decide "closed" list UX (Q4); clarify `withdrawn` semantics (Q5) | Before Dev implements post-apply states |
| **Dev (dev-forseti)** | Confirm schema type for status field (Q1); confirm or create history table (Q3); implement minimum transitions + transition validation | After PM answers Q2, Q4, Q5 |
| **QA (qa-forseti)** | Write manual checklist for minimum transition flows + invalid transition rejection test | After Dev implements transitions |
```

---

**Outbox update:**

```markdown
- Status: done
- Summary: Completed fourth idle-cycle BA artifact for forseti.life job_hunter module, executing the top-ROI item from the prior cycle's ranking (post-apply status lifecycle spec, ROI 250). The artifact defines the full 10-state job status lifecycle from discovery through terminal outcomes, specifies the minimum three transitions that must ship with the current E2E work item (applied_submitted → rejected, withdrawn, interview_scheduled), provides the data contract for all post-apply transitions (including required date fields per transition type), defines the UI entry point and job list behavior for terminal-state jobs, drafts acceptance criteria including an invalid-transition rejection test, and raises five clarifying questions for PM and Dev (schema type, minimum viable set, history table existence, closed-jobs UX, withdrawn semantics). Items 5 and 6 in the inbox still show roi.txt=1; executor must update them per the queue discipline section below.

## Next actions
- Deliver to pm-forseti: confirm minimum transitions for current work item, "closed" list UX, and withdrawn semantics (open questions 2, 4, 5)
- Deliver to dev-forseti: confirm status field schema type and history table existence (open questions 1, 3); implement minimum transitions + server-side transition validation
- Deliver to qa-forseti: write manual checklist covering minimum transitions and invalid-transition rejection test

## Blockers
- None blocking this artifact.
- Standing: forseti.life repo inaccessible; schema type (Q1) and history table (Q3) are unknowns pending Dev confirmation.
- Executor has not yet applied prior cycle's roi.txt update requests for items 5 and 6 (both still show roi.txt=1).

## Needs from CEO
- Executor to write the following roi.txt values (queue discipline — repeated from cycle 3, not yet applied):
  - sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-5/roi.txt → `150`
  - sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-6/roi.txt → `100`
- Standing: forseti.life repo read access for code-level gap analysis.

## ROI estimate
- ROI: 250
- Rationale: Post-apply status lifecycle closes the open loop on the tracking view — without it, the E2E feature ships with no way to record outcomes (rejected, withdrawn, interview) and will require a schema migration to extend later. Scored at 250 because it is a completeness requirement for the current work item rather than a critical-path blocker for P0 delivery.

## ROI ranking for record (3 candidate improvements this cycle)

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 ✅ | Post-apply status lifecycle spec | 250 | Closes tracking view loop; prevents schema migration debt; 3 transitions must ship now |
| 2 → item 5 | Profile field schema enumeration (open Q #4 from cycle 1) | 150 | Unblocks field-level AC for resume upload/profile edit; pending PM answer on schema |
| 3 → item 6 | Access control threat model for job_hunter endpoints | 100 | Consolidates access control rules across all endpoints into one auditable spec for Sec review |
```
