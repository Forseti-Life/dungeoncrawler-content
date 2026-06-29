# Job Hunter Flow + Release Audit

- Status: done
- Summary: Loaded the architect seat, mapped the Job Hunter process flow, audited the active Job Hunter feature backlog, and produced a concrete implementation queue for the Forseti PM/Dev release process.

## Current Job Hunter flow state

- **Step 1 — Resume upload / profile cleanup:** implemented
- **Step 2 — Target companies:** partial
- **Step 3 — AI job discovery:** planned in docs
- **Step 4 — Application submission:** planned in docs
- **Step 5 — Interview / follow-up tracking:** planned in docs
- **Step 6 — Analytics:** planned in docs

Supporting doc signals:
- `sites/forseti/web/modules/custom/job_hunter/docs/SUBMISSION_PROCESS.md`
  still summarizes the product as Step 1 implemented, Step 2 partial, Steps 3-6
  planned.
- `sites/forseti/web/modules/custom/job_hunter/docs/PROCESS_FLOW.md` still marks
  company management, error queue management, user profile review, job
  discovery/scraping, and async processing as planned/future.

## Backlog audit

### Ready features missing release-gate artifacts

These are marked `ready` but currently lack `01-acceptance-criteria.md`,
`02-implementation-notes.md`, and `03-test-plan.md`:

1. `forseti-jobhunter-application-analytics`
2. `forseti-jobhunter-contact-referral-tracker`
3. `forseti-jobhunter-follow-up-reminders`
4. `forseti-jobhunter-interview-outcome-tracker`
5. `forseti-jobhunter-offer-tracker`
6. `forseti-jobhunter-resume-version-tracker`

### In-progress features missing implementation notes

These are already `in_progress` but currently lack `02-implementation-notes.md`:

1. `forseti-jobhunter-company-interest-tracker`
2. `forseti-jobhunter-company-research-tracker`
3. `forseti-jobhunter-contact-tracker`
4. `forseti-jobhunter-job-board-preferences`
5. `forseti-jobhunter-resume-version-labeling`

### Metadata drift to correct

- `forseti-jobhunter-interview-outcome-tracker` is still marked `ready` in HQ,
  but architect session state records it as implemented live on 2026-04-12.
  PM should reconcile feature state, artifacts, and release evidence before any
  further release scoping.

## Recommended implementation sequence

### PM queue

1. Correct stale status for `forseti-jobhunter-interview-outcome-tracker`.
2. Backfill missing release-gate artifacts for all current `ready` / `in_progress`
   Job Hunter features.
3. Keep scope aligned to the real product funnel instead of opening new late-stage
   features before Step-2 foundation work is stable.

### Dev queue

1. Finish **Step 2 foundation work**:
   - `forseti-jobhunter-company-interest-tracker`
   - `forseti-jobhunter-company-research-tracker`
   - `forseti-jobhunter-contact-tracker`
   - `forseti-jobhunter-job-board-preferences`
   - `forseti-jobhunter-resume-version-labeling`
2. Finish **Step 5 tracking work**:
   - normalize `forseti-jobhunter-interview-outcome-tracker` into shipped state
   - implement `forseti-jobhunter-follow-up-reminders`
   - implement `forseti-jobhunter-offer-tracker`
3. Finish **Step 6 reporting**:
   - implement `forseti-jobhunter-application-analytics`

## Why this order

- Analytics depends on reliable saved-job, interview, follow-up, and offer data.
- Offer/follow-up/interview work is more valuable once company tracking and user
  preference surfaces are stable.
- This sequence matches the documented user journey instead of building reporting
  ahead of the operational workflow it reports on.

## Blockers

- No code blocker found in the architect audit itself.
- The main blocker is release-process hygiene: missing gate artifacts and stale
  HQ status metadata.

## ROI estimate

- ROI: 9
- Rationale: Aligning backlog state to the real Job Hunter funnel reduces release
  thrash, prevents PM from activating under-specified features, and gives Dev a
  dependency-aware sequence for the next Forseti release slices.
