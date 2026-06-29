# Outbox update: 20260222-idle-requirements-improvement-forseti.life-3

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-3
- Generated: 2026-02-22T10:36:57-05:00

---

The inbox subdirectory write is blocked at the tool level (executor must write those files). I'll include the roi.txt values for items 4 and 5 in the outbox for executor action, then deliver the top-ROI artifact.

---

**Artifact: `sessions/ba-forseti/artifacts/20260222-jobhunter-queue-health-spec/requirements.md`**

```markdown
# Requirements Artifact: JobHunter Automation & Queue Health Observability

- Feature: forseti-jobhunter-e2e-flow (sub-spec)
- Website: forseti.life
- Module: job_hunter
- BA: ba-forseti
- Date: 2026-02-22
- Depends on: 20260222-jobhunter-e2e-flow-requirements (Phase 4: Tracking / Queue Health)

---

## Problem Statement

The forseti-jobhunter-e2e-flow feature brief requires that "automation/queues run without manual babysitting," but this acceptance criterion is not measurable as written. There is no defined observable signal for "queue healthy," no specified threshold for "stuck," and no owner-facing UI or log query to detect failure. Without a concrete health observability spec, QA cannot write a queue-health test, Dev does not know what to instrument, and an operator (or automated agent) cannot determine whether the E2E flow is working without manually stepping through the UI. This gap creates silent failures: jobs could be stuck in discovery or status-transition queues indefinitely with no alert or user-visible indicator.

---

## Scope

**In scope:**
- Definition of "healthy" vs. "stuck" for each queue type used by job_hunter
- Minimum observability: what signal (log entry, admin UI field, status endpoint) constitutes a verifiable health check
- Failure detection threshold: how long before a queue item is considered stuck
- Recovery path: what an operator or the system must do to clear a stuck item
- User-facing feedback during async operations (discovery in progress, status update pending)

**Non-goals:**
- Building a new monitoring dashboard (unless no existing Drupal mechanism exists)
- External alerting / paging integrations
- Queue performance optimization (throughput, latency at scale)

---

## Definitions

| Term | Definition |
|------|------------|
| Queue item | A single unit of deferred work dispatched to a Drupal queue (e.g., a discovery job, a status update) |
| Healthy queue | All items complete within the expected window; no items in `claimed` state beyond the stuck threshold |
| Stuck item | A queue item in `claimed` state for longer than `QUEUE_STUCK_THRESHOLD` without completing or failing |
| Failed item | A queue item that has been attempted `QUEUE_MAX_RETRIES` times and still not completed |
| Queue worker | The Drupal process (cron, drush queue:run, or dedicated worker) that processes queue items |
| Health signal | A machine-readable or human-readable indicator that the queue is healthy (e.g., log entry, DB row, admin page field) |

---

## Queue Types (Assumed — Dev Must Confirm)

Based on the E2E flow, the following queue types are expected. Dev must confirm actual queue names and whether each is sync or async.

| Queue name (assumed) | Phase | Items dispatched |
|----------------------|-------|-----------------|
| `job_hunter_discovery` | Phase 1: Discovery | J&J portal scrape / API call per user trigger |
| `job_hunter_status_update` | Phase 2–3: My Jobs / Submission | Status transition writes |
| `job_hunter_tracking_sync` | Phase 4: Tracking | Optional: sync external status if supported |

---

## Health Observability Specification

### Minimum required signals (all queue types)

| Signal | Where | Format | Required |
|--------|-------|--------|----------|
| Item dispatched | watchdog (job_hunter channel) | `INFO: Queue item {queue_name} dispatched for user {uid}, item_id={id}` | Yes |
| Item completed | watchdog | `INFO: Queue item {id} completed in {ms}ms` | Yes |
| Item failed (retryable) | watchdog | `WARNING: Queue item {id} failed attempt {n}/{max}: {reason}` | Yes |
| Item failed (final) | watchdog | `ERROR: Queue item {id} exhausted retries: {reason}` | Yes |
| Item stuck detected | watchdog | `WARNING: Queue item {id} claimed for {seconds}s, exceeds threshold {threshold}s` | Yes |
| Queue depth (admin) | Drupal admin queue UI (`/admin/config/system/queue-ui` or equivalent) | Item count visible | Nice-to-have |

### Stuck threshold (configurable)

```
QUEUE_STUCK_THRESHOLD = 300 seconds (5 minutes) — default; configurable per queue
QUEUE_MAX_RETRIES = 3 — default; configurable per queue
```

Both values must be defined in `job_hunter` module config (config entity or `settings.php` override), not hardcoded.

---

## User-Facing Feedback During Async Operations

### Discovery in progress
- User sees: "Searching for jobs... this may take a moment." (spinner or progress indicator)
- Polling interval: every 5 seconds (configurable); max poll duration = `QUEUE_STUCK_THRESHOLD`
- On completion: page updates to show results (no full reload required; JS polling or Drupal AJAX)
- On stuck/timeout: "Discovery is taking longer than expected. [Try again] [Add a job manually]"

### Status update pending (readiness_to_apply / applied_submitted)
- User sees: "Saving your status..." (brief spinner)
- These transitions should be synchronous if possible (no queue needed for simple DB writes)
- If queued: same polling pattern as discovery; on stuck: "There was a problem saving. Please try again."

---

## Draft Acceptance Criteria (for PM to finalize)

### Logging
- [ ] Every queue item dispatch logs an INFO entry with queue name, user UID, and item ID
- [ ] Every queue item completion logs an INFO entry with item ID and duration
- [ ] Every failure (retryable and final) logs at the correct severity (WARNING / ERROR) with reason
- [ ] Stuck detection runs on each cron cycle and logs WARNING for items over threshold

### Configuration
- [ ] `QUEUE_STUCK_THRESHOLD` is configurable (not hardcoded); default 300s
- [ ] `QUEUE_MAX_RETRIES` is configurable; default 3
- [ ] Configuration is accessible via Drupal config entity or `settings.php`; not a magic constant buried in code

### Recovery
- [ ] A stuck item can be released (re-queued or deleted) via Drupal admin UI or `drush` command without code changes
- [ ] After recovery, the user can re-trigger the operation from the UI

### User feedback
- [ ] Discovery shows an async progress indicator; updates without full page reload
- [ ] If discovery exceeds stuck threshold, user sees an actionable error with retry and manual-add options
- [ ] Status transitions (readiness_to_apply, applied_submitted) confirm immediately or show a pending indicator

### Verification (repeatable)
- [ ] `drush watchdog:show --type=job_hunter` returns INFO entries for a completed discovery run
- [ ] Forcing a stuck item (e.g., manually updating `claimed` timestamp in DB) triggers a WARNING log on next cron
- [ ] Playwright script or manual checklist covers: happy-path discovery completes, user sees result without page reload

---

## Clarifying Questions for Stakeholders

1. **Queue names**: What are the actual Drupal queue names registered by the `job_hunter` module? (Dev: check `hook_queue_info()` or `QueueWorker` plugin annotations in the module.)
2. **Sync vs. async for status transitions**: Are `readiness_to_apply` and `applied_submitted` status writes done synchronously (direct DB write in the controller) or dispatched to a queue? If sync, the queue health spec applies only to discovery.
3. **Existing queue monitoring**: Is there already a Drupal Queue UI module installed on forseti.life? If yes, the admin UI signal is already covered. If no, Dev must decide whether to install it or rely on watchdog only.
4. **Cron frequency**: How often does Drupal cron run on forseti.life? (This determines the effective detection latency for stuck items — if cron runs every 15 minutes, stuck threshold must be at least 15 minutes to avoid false positives.)
5. **Worker process type**: Are queues processed by cron, by a dedicated `drush queue:run` daemon, or by a request-triggered worker? (Affects recovery instructions and the manual "drain queue" runbook.)

---

## Suggested Next Delegation

| Role | Action | Priority |
|------|--------|----------|
| **Dev (dev-forseti)** | Confirm queue names, sync/async for status transitions (open questions 1–2); instrument logging per spec; make thresholds configurable | After PM reviews open questions 3–5 |
| **PM (pm-forseti)** | Confirm cron frequency, existing queue UI (open questions 3–4); approve stuck threshold defaults | Before Dev instruments |
| **QA (qa-forseti)** | Write watchdog verification test; write stuck-item simulation test (force claimed timestamp); verify user-facing feedback on stuck discovery | After Dev implements logging + thresholds |
```

---

**Outbox update: `sessions/ba-forseti/outbox/20260222-idle-requirements-improvement-forseti.life-3.md`**

```markdown
- Status: done
- Summary: Completed third idle-cycle BA artifact for forseti.life job_hunter module. Ranked three remaining requirements improvements by ROI and completed the top item: a full automation and queue health observability specification for the forseti-jobhunter-e2e-flow feature. The artifact defines "healthy" vs. "stuck" with configurable thresholds (QUEUE_STUCK_THRESHOLD=300s, QUEUE_MAX_RETRIES=3), specifies minimum required log signals for every queue lifecycle event (dispatch, complete, retryable failure, final failure, stuck detection), covers user-facing async feedback for discovery and status transitions, drafts verifiable acceptance criteria including a drush watchdog command and a stuck-item simulation test, and raises five clarifying questions (queue names, sync vs. async transitions, existing queue UI, cron frequency, worker type). The roi.txt files for pending inbox items 4 and 5 could not be written directly (tool-level restriction verified); executor must update them per the values specified in this outbox.

## Next actions
- Executor: update roi.txt in inbox items per queue discipline section below
- Deliver artifact to pm-forseti: confirm cron frequency, existing Queue UI module, and stuck threshold defaults (open questions 3–5)
- Deliver to dev-forseti: confirm actual queue names and whether status transitions are sync or async (open questions 1–2); instrument logging per spec
- Deliver to qa-forseti: write watchdog verification test and stuck-item simulation test once Dev confirms queue names

## Blockers
- Cannot directly write roi.txt to inbox items 4 and 5 — tool-level restriction prevents writes to those paths (verified: `echo "400" > .../roi.txt` returned permission denied). Executor must apply the roi.txt values below.
- forseti.life repo inaccessible (standing blocker from prior cycles); queue names and worker type are unknowns that Dev must confirm.

## Needs from CEO
- Executor to write the following roi.txt values (queue discipline):
  - sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-4/roi.txt → `250`
  - sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-5/roi.txt → `150`
- Standing: forseti.life repo read access for code-level gap analysis (tracked since 20260221-jobhunter-e2e-flow).

## ROI estimate
- ROI: 400
- Rationale: Queue health observability is a direct prerequisite for the "no manual babysitting" acceptance criterion in the P0 E2E feature — without this spec, Dev has no instrumentation target and QA has no verifiable test for automation health. Scored at 400 (below prior cycles' tracking state contracts at 800 and discovery error spec at 650) because it unblocks a monitoring/observability sub-requirement rather than core persistence logic.

## ROI ranking for record (3 candidate improvements this cycle)

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 ✅ | Automation/queue health observability spec | 400 | Closes the "no manual babysitting" AC gap; enables QA automation health test |
| 2 → item 4 | Post-apply status lifecycle spec (interview → offer → rejected states) | 250 | Prevents scope creep; formalizes minimal post-submit states for tracking view |
| 3 → item 5 | Profile field schema enumeration (open question #4 from cycle 1) | 150 | Unblocks field-level AC for resume upload/profile edit; lower urgency pending PM answer |
```
