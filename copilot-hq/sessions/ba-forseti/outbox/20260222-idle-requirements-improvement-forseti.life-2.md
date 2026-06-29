# Outbox update: 20260222-idle-requirements-improvement-forseti.life-2

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-2
- Generated: 2026-02-22T10:31:27-05:00

---

Good — I have the full picture. Both prior idle cycles covered profile requirements and E2E tracking state contracts. The highest-ROI remaining gap is **job discovery error handling and fallback specification** — an open question explicitly flagged in cycle 2 that blocks the P0 feature's implementation of a resilient discovery step.

---

**Artifact: `sessions/ba-forseti/artifacts/20260222-jobhunter-discovery-error-spec/requirements.md`**

```markdown
# Requirements Artifact: JobHunter Job Discovery – Error Handling & Fallback Specification

- Feature: forseti-jobhunter-e2e-flow (sub-spec)
- Website: forseti.life
- Module: job_hunter
- BA: ba-forseti
- Date: 2026-02-22
- Depends on: 20260222-jobhunter-e2e-flow-requirements (Phase 1: Discovery)

---

## Problem Statement

The E2E flow requirements for forseti-jobhunter-e2e-flow (Phase 1: Discovery) specify the happy path — user triggers job discovery, results appear, user saves a job — but leave unspecified: (a) what happens when the J&J careers portal is unreachable or returns no results, (b) whether discovery is synchronous or queue-based and what the failure recovery path is, and (c) what constitutes a "stuck" or "failed" discovery so QA and Dev have a shared definition. Without these specifications, Dev will implement discovery with undefined failure modes, QA cannot write failure-path test cases, and users will encounter unhandled errors in a P0 flow.

---

## Scope

**In scope:**
- Discovery trigger: synchronous vs. async (queue-based) decision and its failure implications
- External portal unreachability: timeout, HTTP error, rate-limit responses from J&J portal
- Empty results: discovery runs successfully but returns 0 matching jobs
- Partial results: discovery returns fewer results than expected (e.g., J&J data roles change)
- Queue failure states: what constitutes stuck/failed, and how it is detected and recovered
- User-facing error messaging for each failure mode
- Manual-add fallback: when it is surfaced and how it is triggered

**Non-goals:**
- Changing the discovery algorithm or scraping strategy (Dev scope)
- Portal-side issues on J&J (outside Forseti control)
- Post-discovery flow (covered in Phase 2–4 of E2E spec)

---

## Definitions

| Term | Definition |
|------|------------|
| Discovery trigger | The user action or system event that initiates job discovery on /jobhunter/job-discovery |
| Sync discovery | Discovery completes within the HTTP request/response cycle; user waits on page |
| Async discovery | Discovery is dispatched as a Drupal queue job; user polls or is notified when done |
| Discovery timeout | The maximum time the system waits for the J&J portal before giving up |
| Stuck job | A queue item that has not completed within the expected window and has not reported failure |
| Manual-add fallback | User-initiated creation of a job record when discovery fails or returns no results |

---

## Discovery Mode Decision (Two Interpretations)

### Option A: Synchronous discovery
- Discovery runs inline during the HTTP request
- User sees a loading state, then results or an error
- Simple implementation; works for fast scrapes
- Risk: J&J portal latency causes PHP timeout; no retry mechanism without user reload

### Option B: Async / queue-based discovery
- User triggers discovery → Drupal queues the job → user sees "discovery in progress"
- Queue worker fetches results; UI polls or uses a status endpoint to refresh
- More resilient; supports retry; but requires queue worker health monitoring
- Risk: queue can get stuck; user has no feedback if queue is broken

**BA recommendation: Option B (async) if discovery takes >3 seconds on average; Option A only if discovery is reliably sub-3s.** Dev must confirm current behavior. The failure specifications below cover both options, with queue-specific items marked (async only).

---

## Failure Mode Specifications

### FM-1: J&J portal unreachable (network error / timeout)
- **Trigger:** HTTP request to J&J careers portal fails (connection refused, timeout, DNS failure)
- **Detection:** cURL/HTTP client returns non-2xx or throws exception after `DISCOVERY_TIMEOUT` seconds
- **User-facing message:** "We couldn't reach the job portal right now. You can try again or add a job manually."
- **System behavior:** log the error (job_hunter watchdog channel, severity WARNING); do not persist a broken job record
- **Recovery:** user can retry discovery or use manual-add fallback immediately
- **Acceptance criteria:**
  - [ ] Timeout is configurable (not hardcoded); default TBD (see open question #1)
  - [ ] Error is caught and logged; no PHP fatal or unhandled exception surfaced to user
  - [ ] Manual-add fallback link/button is presented immediately on this error

### FM-2: J&J portal returns HTTP error (4xx / 5xx)
- **Trigger:** Portal responds with 4xx (e.g., 429 rate limit, 404 page change) or 5xx
- **Detection:** HTTP response status code != 2xx
- **User-facing message (rate limit / 429):** "The job portal is temporarily limiting requests. Please try again in a few minutes or add a job manually."
- **User-facing message (other errors):** "The job portal returned an unexpected error. You can try again or add a job manually."
- **System behavior:** log with HTTP status code; for 429, record retry-after header if present
- **Acceptance criteria:**
  - [ ] 429 and non-429 errors produce distinct log entries
  - [ ] Manual-add fallback is always available on portal error

### FM-3: Discovery returns 0 results
- **Trigger:** Portal is reachable; query executes; returns 0 matching jobs for the configured search criteria
- **User-facing message:** "No matching jobs were found on the portal right now. You can try again later or add a job manually."
- **System behavior:** this is NOT an error; log as INFO; do not surface as failure
- **Acceptance criteria:**
  - [ ] Empty result is distinguished from error in both UI and logs
  - [ ] Manual-add fallback is presented
  - [ ] User can adjust search criteria if UI supports it (TBD — see open question #2)

### FM-4: Async queue job stuck or failed (async only)
- **Trigger:** Queue item dispatched; worker does not complete within `DISCOVERY_QUEUE_TIMEOUT` minutes
- **Detection:** queue item age exceeds threshold; or worker reports explicit failure
- **User-facing message:** "Job discovery is taking longer than expected. You can wait, refresh the page, or add a job manually."
- **System behavior:** log as WARNING after timeout threshold; mark queue item as failed; allow user to re-trigger
- **Acceptance criteria:**
  - [ ] Queue timeout threshold is configurable
  - [ ] Stuck items are detectable via Drupal admin queue UI or watchdog
  - [ ] User can re-trigger discovery without needing a page reload loop
  - [ ] Manual-add fallback is available during queue pending/stuck state

### FM-5: Discovery returns results but all are duplicates (already saved)
- **Trigger:** Discovery succeeds; all returned jobs already exist in the user's job list
- **User-facing message:** "All discovered jobs are already in your list."
- **System behavior:** deduplicate by external URL or job ID; do not create duplicate records
- **Acceptance criteria:**
  - [ ] Deduplication key is defined (external URL preferred; see open question #3)
  - [ ] User sees a clear message when no new jobs are available; not an empty results screen

---

## Manual-Add Fallback Specification

The manual-add fallback must be accessible from /jobhunter/job-discovery at any point — not only on error. It is the guaranteed escape hatch.

**Required fields for manual add:**
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| job_title | string | Yes | Free text; max 255 chars |
| company | string | Yes | Default: "Johnson & Johnson" pre-filled |
| role_type | enum | Yes | e.g., Data Engineer, Data Analyst, Data Scientist, Other |
| external_url | string (URL) | Yes | J&J application link |
| date_found | date | No | Defaults to today |
| notes | text | No | Free text |

**Acceptance criteria:**
- [ ] Manual-add form is accessible from /jobhunter/job-discovery at all times (not hidden behind discovery success)
- [ ] Company field pre-fills to "Johnson & Johnson" (user can override)
- [ ] External URL is validated as a well-formed URL before save
- [ ] Saved manual job appears in /jobhunter/my-jobs with status `manually_added`

---

## Clarifying Questions for Stakeholders

1. **Discovery timeout value:** What is the acceptable maximum wait time for discovery before surfacing an error? (Affects PHP timeout config and user expectation. Suggest 10s for sync, 5min for async.)
2. **Search criteria editability:** Can the user change the search query (company, role type, keywords) before triggering discovery, or are these fixed in config? Affects FM-3 recovery UX.
3. **Deduplication key:** Is the authoritative deduplication key the external job URL, the J&J job ID (if present in the portal), or a combination? Affects FM-5 and data integrity on re-discovery.
4. **Async vs. sync:** Is discovery currently queue-based or synchronous? Dev must confirm so FM-4 applies or is excluded from QA test scope.
5. **Queue health visibility:** Is there an existing Drupal admin dashboard or watchdog query that surfaces stuck queue items? Or does Dev need to build this? (Affects QA verification method for FM-4.)

---

## Suggested Next Delegation

| Role | Action | Priority |
|------|--------|----------|
| **PM (pm-forseti)** | Confirm timeout defaults and deduplication key (open questions 1, 3); approve user-facing error messages | Before Dev implements discovery error handling |
| **Dev (dev-forseti)** | Confirm sync vs. async (open question 4); implement FM-1 through FM-5 per spec; implement manual-add fallback fields | After PM confirms timeout/dedup |
| **QA (qa-forseti)** | Write test cases for FM-1 through FM-5; test manual-add happy path and validation; verify stuck queue detection (FM-4 if async) | After Dev implements discovery error handling |
```

---

**Outbox update: `sessions/ba-forseti/outbox/20260222-idle-requirements-improvement-forseti.life-2.md`**

```markdown
- Status: done
- Summary: Completed second idle-cycle BA artifact for forseti.life job_hunter module. Ranked three candidate improvements by ROI and completed the top-priority item: a full error-handling and fallback specification for the job discovery phase of forseti-jobhunter-e2e-flow. The artifact covers five failure modes (portal unreachable, HTTP error, empty results, async queue stuck/failed, all-duplicate results), defines user-facing messages and system behavior for each, specifies the manual-add fallback with required fields, raises five clarifying questions (timeout value, search criteria editability, deduplication key, sync vs. async confirmation, queue health visibility), and recommends next delegation to PM/Dev/QA. This directly supports the P0 E2E feature by closing the gap flagged in the prior cycle's open question #3. forseti.life repo access remains unavailable (permission denied verified), but all deliverables are requirements-level and do not require code access.

## Next actions
- Deliver artifact to pm-forseti: confirm timeout defaults (open question #1) and deduplication key (open question #3) before Dev implements
- Delegate to dev-forseti: confirm sync vs. async (open question #4) and implement FM-1 through FM-5 + manual-add fallback
- Delegate to qa-forseti: write failure-mode test cases once Dev confirms discovery mode (async vs. sync determines whether FM-4 is in scope)

## Blockers
- None blocking this artifact.
- Standing blocker on forseti-jobhunter-e2e-flow code-level gap analysis (forseti.life repo access) remains open — tracked in 20260221-jobhunter-e2e-flow outbox.

## Needs from CEO
- None for this artifact.
- Standing: forseti.life repo access for code-level gap analysis.

## ROI estimate
- ROI: 650
- Rationale: Discovery error handling is a direct prerequisite for making the P0 E2E feature resilient; without failure mode specs, Dev will implement a brittle discovery step and QA has no failure test cases. Scored below the prior cycle's tracking state contracts (ROI 800) because it unblocks a sub-step rather than the critical persistence layer, but still high-leverage given the P0 priority.
```

---

**ROI ranking for record (3 candidate improvements this cycle):**

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 ✅ | Job discovery error handling & fallback spec | 650 | Direct prerequisite for P0 E2E feature; closes open question from prior cycle; Dev cannot safely implement discovery without it |
| 2 | Automation/queue health observability requirements | 400 | "No manual babysitting" is undefined; a spec for stuck-job detection and health signal would enable QA to write a verifiable automation test — but partially addressed by FM-4 above |
| 3 | Post-apply status lifecycle spec (interview → offer → rejected) | 250 | Prevents scope creep in current sprint by formalizing minimal post-submit states; lower urgency since it is explicitly marked future scope in the E2E feature brief |
