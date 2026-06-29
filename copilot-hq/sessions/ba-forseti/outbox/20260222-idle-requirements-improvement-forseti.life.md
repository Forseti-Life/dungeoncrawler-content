# Outbox update: 20260222-idle-requirements-improvement-forseti.life

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life
- Generated: 2026-02-22T09:44:20-05:00

---

I have full context from the feature brief and stage-break clarification. The forseti.life repo is inaccessible (verified: permission denied on all paths), but the top-ROI idle artifact — E2E flow requirements with tracking state contracts — can be produced entirely from the feature brief without requiring code access.

---

**Artifact: `sessions/ba-forseti/artifacts/20260222-jobhunter-e2e-flow-requirements/requirements.md`**

```markdown
# Requirements Artifact: JobHunter E2E Flow – Job Discovery to Tracking

- Feature: forseti-jobhunter-e2e-flow
- Website: forseti.life
- Module: job_hunter
- BA: ba-forseti
- Date: 2026-02-22
- Target company: Johnson & Johnson (https://www.careers.jnj.com/en/)
- Target roles: any "data" role (Data Engineer, Data Analyst, Data Scientist)

---

## Problem Statement

The JobHunter module on forseti.life provides a step-based dashboard flow (/jobhunter) for discovering, saving, and tracking job applications, but the end-to-end path from job discovery through submission tracking is incompletely specified and partially implemented. Key gaps: (1) no confirmed data contract for the two tracking states needed at the stage-break boundary (readiness_to_apply, applied_submitted), (2) the handoff point between Forseti automation and the external J&J portal is not formally specified, and (3) acceptance criteria do not enumerate which dashboard steps are owned by automation vs. the user. Without these contracts, Dev cannot implement the tracking pipeline and QA cannot write a repeatable verification.

---

## Scope

**In scope:**
- The full /jobhunter dashboard step flow from discovery to tracking
- Job discovery: finding J&J data roles and saving them to the user's job list
- Manual job add fallback: user can add a job directly if discovery fails
- Stage-break: open external J&J application link as handoff; NO account creation on J&J portal
- Tracking state persistence: readiness_to_apply and applied_submitted in Forseti
- In-site status visibility: job list + detail + history views
- Automation/queue health: all steps must run without manual intervention

**Non-goals (confirmed):**
- Creating an account on the Johnson & Johnson portal
- Building a full external auto-apply bot
- Parsing or submitting application form fields on the external portal
- Any portal beyond J&J for this work item

---

## Definitions

| Term | Definition |
|------|------------|
| Stage break | The boundary where Forseti automation stops and the user takes over on an external site |
| readiness_to_apply | Internal Forseti state: the job record is complete and the user is ready to apply externally |
| applied_submitted | Internal Forseti state: the user has submitted the application externally and the date/link/status have been recorded in Forseti |
| Job discovery | Automated or semi-automated process of finding matching job postings from the J&J careers portal |
| Manual add | User-initiated creation of a job record without automated discovery |

---

## End-to-End Process Flow (Route-mapped)

### Phase 1: Discovery → /jobhunter/job-discovery
1. User (authenticated) lands on /jobhunter dashboard
2. User initiates job discovery for J&J data roles
3. System queries / scrapes J&J careers portal for matching roles
4. System presents discovered jobs to the user
5. User selects one or more jobs to save
6. **Fallback:** if discovery returns no results, user manually adds a job (title, URL, company, role type)
7. System validates and saves the job record to the user's list

**State after this phase:** job record exists with status = `discovered` or `manually_added`

### Phase 2: My Jobs → /jobhunter/my-jobs
1. User reviews saved job list
2. User selects a job to progress
3. User (or system) marks job as ready to apply → status transitions to `readiness_to_apply`
4. System persists: job ID, user ID, timestamp, external application URL

**State after this phase:** job record status = `readiness_to_apply`

### Phase 3: Application Submission → /jobhunter/application-submission
1. User views job detail; Forseti shows the external application link
2. User clicks link → browser opens J&J careers portal (external navigation only)
3. *** STAGE BREAK: Forseti automation stops here. No account creation, no form fill on J&J portal. ***
4. User completes application externally (outside Forseti scope)
5. User returns to Forseti and marks job as applied/submitted
6. System records: applied date, application URL, submission notes (optional), status = `applied_submitted`

**State after this phase:** job record status = `applied_submitted`

### Phase 4: Tracking → /jobhunter (dashboard / tracking views)
1. User views job list; applied jobs show `applied_submitted` status + date
2. User can view job detail: full history of status transitions + timestamps
3. User can update status (e.g., interview scheduled, offer received, rejected) — future scope unless already implemented
4. Automation/queues must not leave jobs in broken/stuck state

**State after this phase:** job status visible in list + detail; history persisted

---

## Tracking State Data Contracts

### State: readiness_to_apply
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| job_id | int / uuid | Yes | FK to job record |
| user_id | int | Yes | Drupal UID; must match session user |
| status | enum | Yes | Value: `readiness_to_apply` |
| application_url | string (URL) | Yes | External J&J application link |
| readiness_timestamp | datetime | Yes | When state was set |
| notes | text | No | Optional user notes |

### State: applied_submitted
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| job_id | int / uuid | Yes | FK to job record |
| user_id | int | Yes | Drupal UID; must match session user |
| status | enum | Yes | Value: `applied_submitted` |
| applied_date | date | Yes | User-entered or auto-set to current date |
| application_url | string (URL) | Yes | URL used to apply |
| confirmation_ref | string | No | Optional external confirmation number/text |
| submitted_timestamp | datetime | Yes | When state was recorded in Forseti |

**Access control rule (both states):** all read/write operations must verify `user_id == current session UID`; cross-user access returns 403.

---

## Draft Acceptance Criteria (for PM to finalize)

### Discovery
- [ ] User can trigger job discovery for J&J data roles from /jobhunter/job-discovery
- [ ] Discovered jobs display: title, company, role type, external URL, date discovered
- [ ] If discovery returns 0 results, manual-add form is accessible and functional
- [ ] Saved job appears in /jobhunter/my-jobs with status `discovered` or `manually_added`

### My Jobs
- [ ] User can view all saved jobs with current status
- [ ] User can transition a job to `readiness_to_apply`; timestamp and URL are recorded
- [ ] Status transition is persisted; visible on page reload

### Application Submission (Stage Break)
- [ ] External J&J application link opens in browser (new tab or redirect); no J&J account creation attempted
- [ ] User can mark job as `applied_submitted` after returning to Forseti
- [ ] `applied_date`, `application_url`, and `submitted_timestamp` are all persisted
- [ ] Forseti does NOT attempt to fill or submit any form on the J&J portal

### Tracking
- [ ] /jobhunter dashboard shows applied jobs with `applied_submitted` status + applied date
- [ ] Job detail view shows full status history with timestamps
- [ ] All automation/queue steps complete without manual intervention (no stuck jobs)

### Access Control
- [ ] A user can only view/edit their own jobs; cross-user access returns 403
- [ ] Unauthenticated requests redirect to login

### Verification (repeatable)
- [ ] Playwright script `testing/jobhunter-workflow-step1-6-data-engineer.mjs` adapted/parameterized for J&J data role
- [ ] OR: documented manual checklist in QA outbox covering all phases above

---

## Assumptions

1. The Drupal module already has custom tables for job records and status tracking; schema extensions (if needed for the two new states) are in Dev scope.
2. The /jobhunter dashboard is a step-based UI with distinct route per phase (confirmed by feature brief).
3. "Automation/queues" refers to Drupal Queue API or equivalent; the specific queue names are in ARCHITECTURE.md (inaccessible to BA; Dev must confirm).
4. The Playwright test script already exists and only needs parameterization, not a full rewrite.
5. Status history is stored as a log/event table, not just a single status field (assumption; Dev must confirm).

---

## Clarifying Questions for Stakeholders

1. **Status enum source of truth**: Where is the job status enum defined (database schema, PHP constant, config entity)? Are `readiness_to_apply` and `applied_submitted` new values or do they map to existing values?
2. **History table**: Is there a separate status history/event log table, or is history derived from a single `status` field? (Affects whether history view requires a new table.)
3. **Discovery mechanism**: Does job discovery call an external API, run a scraper queue, or require a manual trigger? Is there a fallback timeout / error state if J&J portal is unreachable?
4. **Playwright test scope**: Does `testing/jobhunter-workflow-step1-6-data-engineer.mjs` currently cover all 6 steps, or are some steps stubbed/skipped? QA needs to know before adapting it.
5. **Queue health definition**: What does "automation runs without manual babysitting" mean in terms of a verifiable signal — a Drupal watchdog log, a dashboard indicator, or a specific queue status check?

---

## Suggested Next Delegation

| Role | Action | Priority |
|------|--------|----------|
| **PM (pm-forseti)** | Finalize AC above; confirm status enum values and history table assumption; sign off on stage-break boundary | Immediate |
| **Dev (dev-forseti)** | Confirm schema for tracking states; extend tables if needed; implement readiness_to_apply and applied_submitted persistence; ensure access control on all endpoints | After PM AC sign-off |
| **QA (qa-forseti)** | Adapt Playwright script for J&J data role; write manual checklist as fallback; prioritize cross-user access control test | After Dev milestone on tracking persistence |
```

---

**Outbox update: `sessions/ba-forseti/outbox/20260222-idle-requirements-improvement-forseti.life.md`**

```markdown
- Status: done
- Summary: Completed idle-cycle BA artifact for forseti.life job_hunter module (work item: forseti-jobhunter-e2e-flow). Produced a tightened problem statement, a four-phase route-mapped E2E process flow (/jobhunter/job-discovery → /jobhunter/my-jobs → /jobhunter/application-submission → tracking), precise data contracts for both tracking states (readiness_to_apply and applied_submitted including all required fields and the access control rule), draft acceptance criteria across discovery/my-jobs/submission/tracking/access-control/verification phases, five clarifying questions for Dev, and a prioritized next-delegation table for PM/Dev/QA. The forseti.life repo remained inaccessible (verified: permission denied on all paths), so code-level gap classification (implemented/partial/missing per controller) was omitted and flagged as a Dev responsibility once AC is signed off. The stage-break constraint (no J&J account creation; external apply = open-link handoff only) is formally incorporated into the flow and AC.

## Next actions
- Deliver artifact to pm-forseti for AC finalization (especially clarifying questions 1–2 on status enum and history table)
- Once PM signs off on AC: delegate to dev-forseti with tracking state data contracts as the primary input
- Delegate to qa-forseti to begin adapting Playwright script (does not require PM sign-off to start)
- If forseti.life repo access is granted: return to this artifact and add code-level gap classification (implemented/partial/missing per route/controller)

## Blockers
- None blocking this artifact. forseti.life repo inaccessible (permission denied verified), but this artifact was scoped to requirements only; code-level gap analysis is flagged as Dev responsibility.
- The E2E gap analysis (Dev task list with file/controller) from work item 20260221-jobhunter-e2e-flow is still blocked on repo access — that is tracked separately.

## Needs from CEO
- None for this artifact.
- Separate standing need: forseti.life repo access for the code-level gap analysis (tracked in 20260221-jobhunter-e2e-flow outbox).

## ROI estimate
- ROI: 800
- Rationale: This is a direct requirements input for the P0 ROI-1000 feature (forseti-jobhunter-e2e-flow). The tracking state data contracts and stage-break boundary specification are the highest-leverage BA output right now — without them, Dev cannot implement the persistence layer and QA cannot write a repeatable verification. Scored slightly below 1000 because the code-level gap analysis (also needed) remains blocked.
```
