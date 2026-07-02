# Automated Testing Process Flow

Canonical process-flow reference for `dungeoncrawler_tester` automation.

## Purpose

This document provides a timeline-first analysis of:
- Cron-triggered subprocesses
- Queue scheduling and execution cadence
- Sync vs async boundaries
- Blocking and pause points
- Timing budgets and worst-case windows

## Methodology Used

This analysis follows the required state-machine methodology:
1. Core components: States, Events, Transitions, Actions
2. Async risk analysis: race conditions, out-of-order events, retries/recovery
3. Transition table for concrete process behavior
4. Analysis workflow: happy path, edge cases, illegal transitions
5. Deterministic vs non-deterministic classification

## Runtime Components

- `dungeoncrawler_tester_cron()`
  - Calls issue synchronization (`StageIssueSyncService::syncIssues(TRUE, TRUE)`)
  - Calls stage auto-enqueue (`StageAutoEnqueueService::enqueueDueStages(3600)`)
- `StageAutoEnqueueService`
  - Evaluates eligibility and enqueues jobs in `dungeoncrawler_tester_runs`
- `TesterRunQueueWorker`
  - Claims queue items, executes test commands via Symfony Process, and writes failing cases to local `Issues.md`
- `StageIssueSyncService`
  - Polls linked local `Issues.md` rows and auto-resumes closed issues
- `QueueManagementController` and Drush command
  - Manual queue processing paths (`runQueueAjax`, `dungeoncrawler_tester:run-queue`)

## 1) Core Components

### States

- `INACTIVE`
- `READY`
- `PENDING`
- `RUNNING`
- `SUCCEEDED`
- `FAILED`
- `ISSUE_OPEN`
- `RESUMED`

### Events (Triggers)

- `CronTick`
- `IssueSyncClosedDetected`
- `EnqueueEligibilityPassed`
- `WorkerClaimedItem`
- `CommandSucceeded`
- `CommandFailed`
- `LocalIssueCreateOrReuseSucceeded`
- `LocalIssueCreateFailed`
- `ManualQueueRunRequested`
- `TimeoutOccurred`

### Transitions

- `READY -> PENDING` on enqueue eligibility
- `PENDING -> RUNNING` on worker claim
- `RUNNING -> SUCCEEDED` on successful command
- `RUNNING -> FAILED` on command failure
- `FAILED -> ISSUE_OPEN/INACTIVE` when local issue row create/reuse succeeds
- `FAILED -> INACTIVE` when local issue row creation fails
- `ISSUE_OPEN -> RESUMED -> READY` when issue closes and sync applies

### Actions (Side Effects)

- Queue item creation
- Run metadata persistence
- Command execution with timeout
- Local issue row create/reuse attempts in repository-root `Issues.md`
- Stage pause/reactivation and failure metadata updates

## 2) Async Reliability: Why It Is Critical

### Race conditions

- Pending/running gate prevents duplicate enqueue of same stage.
- Queue claim semantics ensure one worker owns one item at a time.
- Drush queue runner lock prevents duplicate runner execution.

### Out-of-order events

- Cron runs issue sync before enqueue, reducing stale-open-state progression.
- Open issue gate blocks enqueue until correct stage state is reached.
- Invalid payloads do not advance state.

### Reliability and retries

- Persistent state keys (`runs`, `stage_state`, `auto_enqueue_last`) define restart point.
- Failure paths preserve forensic data (output excerpts, timestamps, exit codes).
- Timeout budgets cap hanging subprocesses/network calls.

## 3) State Transition Table (Tester Automation)

| Current State | Event (Trigger) | New State | Action Performed |
|---|---|---|---|
| READY | EnqueueEligibilityPassed | PENDING | Create queue item and persist pending metadata |
| PENDING | WorkerClaimedItem | RUNNING | Mark run as running and start command process |
| RUNNING | CommandSucceeded | SUCCEEDED | Persist success result and clear failure metadata |
| RUNNING | CommandFailed | FAILED | Persist failure output and enter failure branch |
| FAILED | LocalIssueCreateOrReuseSucceeded | ISSUE_OPEN / INACTIVE | Link local issue and pause stage |
| FAILED | LocalIssueCreateFailed | INACTIVE | Pause stage without issue linkage |
| ISSUE_OPEN | IssueSyncClosedDetected | RESUMED -> READY | Reactivate stage and clear failure state |

## Cadence Inputs

### Cron cadence

- `automated_cron.settings.yml`: `interval: 10800` seconds (3 hours)
- Effective cron invocation can be earlier/later depending on site traffic or external cron setup

### Stage enqueue cadence

- `enqueueDueStages(3600)`: each stage must wait at least 3600 seconds before re-enqueue

### Queue-worker timing

- Queue worker annotation: `cron = {"time" = 60}`
- Per-item test command timeout: 1800 seconds

## Timeline View

### Lane A: Scheduler/cron lane (asynchronous)

T+0: cron tick enters `dungeoncrawler_tester_cron()`

1. `syncIssues(TRUE, TRUE)` runs first
   - If linked issue is closed: stage is re-enabled (`active=TRUE`), failure metadata cleared, linkage optionally removed
2. `enqueueDueStages(3600)` runs second
   - Eligible stages are enqueued and marked `pending`

T+Δ: cron exits

Blocking notes:
- No long-running stage commands here, but linked local row lookups can add latency for heavily-linked stages.

### Lane B: Queue execution lane (asynchronous worker with synchronous internals)

T+Q0: queue item claimed

1. Validate payload (`stage_id`, `job_id`, command args)
2. Mark run `running`
3. Execute command (`Process::run()`) with 1800s timeout
4. Persist run outcome (`succeeded` / `failed`, output excerpt, duration)
5. On failure, attempt local issue row create/reuse in `Issues.md`
6. Update stage state
   - Failure path: `active=FALSE`, failure reason/excerpt, optional issue linkage
   - Success path: clear failure metadata

T+Q1: item deleted or released depending on result

Blocking notes:
- Worker is blocked for full command runtime.
- Local tracker file writes add synchronous tail latency on failure path.

### Lane C: Operator/manual lane (synchronous entry points)

Entry points:
- Dashboard AJAX: `/dungeoncrawler/testing/queue/run`
- Drush: `dungeoncrawler_tester:run-queue --limit=N`

Behavior:
- Request starts sync path, but processed items still use queue worker flow per item.
- Manual run can accelerate processing between cron ticks.

Blocking notes:
- Controller `processQueue()` timeout guard is 60 seconds for AJAX processing loop.
- Drush runner uses lock `dungeoncrawler_tester.queue_runner` to prevent concurrent runners.

## Gate-by-Gate Blocking Map

### Gate G1: Enqueue eligibility gate

A stage is skipped unless all are true:
- `active != FALSE`
- no open issue (`issue_number` absent or `issue_status != open`)
- last run status not `pending`/`running`
- now - `auto_enqueue_last[stage]` >= 3600

Effect:
- Prevents duplicate jobs and enforces cooldown cadence.

### Gate G2: Queue payload validity gate

Required:
- `stage_id`, `job_id`, command args

Effect:
- Invalid item is logged and dropped.

### Gate G3: Failure pause gate

Condition:
- command exit code != 0

Effect:
- Stage forced inactive and progression blocked until issue lifecycle/remediation clears conditions.

### Gate G4: Issue-linked re-entry gate

Condition:
- stage has open linked issue

Effect:
- Auto-enqueue path blocks stage until closure sync or manual state cleanup.

## Timing Matrix (Timeline-Oriented)

| Phase | Lane | Type | Trigger | Duration / Budget | Blocking Scope |
|---|---|---|---|---|---|
| Cron issue sync | Scheduler | Async trigger, sync internals | cron tick | per issue call up to 8s | cron execution thread |
| Cron auto-enqueue | Scheduler | Async trigger, sync internals | cron tick | usually sub-second to seconds | cron execution thread |
| Stage cooldown check | Scheduler | Sync check | per stage | 3600s eligibility window | stage remains queued/skipped |
| Queue claim + execute | Queue | Async worker, sync command | queue item available | up to 1800s per item | worker slot/thread |
| Failure local issue write | Queue | Sync file operation | failed item | usually sub-second | worker slot/thread |
| Dashboard manual run loop | Operator | Sync request | user action | up to 60s processing loop | HTTP request thread |

## Worst-Case Latency Windows

### Detection-to-enqueue window

If relying only on automated cron:
- Up to cron interval (10800s) to next cron invocation
- plus stage cooldown remainder (up to 3600s)

Practical upper window (coarse):
- up to ~4 hours in low-traffic or delayed-cron conditions

### Enqueue-to-result window

- Queue wait time (depends on backlog)
- plus up to 1800s command runtime
- plus failure-path local tracker write tail

## Observability Points

- Queue run status in `dungeoncrawler_tester.runs`
- Stage gate state in `dungeoncrawler_tester.stage_state`
- Auto-enqueue timestamps in `dungeoncrawler_tester.auto_enqueue_last`
- Watchdog channel: `dungeoncrawler_tester`

## Update Protocol

Update this file whenever any of the following changes:
- Cron interval assumptions or scheduler path
- Enqueue cadence (`enqueueDueStages` interval)
- Queue timeouts, lock behavior, processing limits
- Failure/issue sync logic and stage gating rules
- Local issue automation behavior and import handoff assumptions

## 4) How Analysis Was Conducted

1. Enumerated effective statuses from state keys and queue lifecycle.
2. Mapped happy path from READY to SUCCEEDED.
3. Mapped edge cases for failures, issue-open locks, and timeouts.
4. Declared illegal transitions explicitly.
5. Classified deterministic vs non-deterministic segments.

## Illegal Transitions

- `SUCCEEDED -> RUNNING` without a new enqueue event.
- `INACTIVE/ISSUE_OPEN -> PENDING` while open issue lock remains.
- `PENDING -> SUCCEEDED` without `RUNNING` execution.
- `RUNNING -> READY` without terminal command outcome.

## 5) Deterministic vs Non-Deterministic

### Deterministic

- Core queue lifecycle order (`READY -> PENDING -> RUNNING -> terminal state`).
- Enqueue gate checks and lock checks.

### Non-deterministic

- External cron invocation timing.
- Filesystem write/read behavior for local tracker updates.
- Runtime latency and timeout branches.

Testing implication:
- Deterministic paths are good candidates for strict transition tests.
- Non-deterministic paths require richer logging/assertion envelopes and timeout-branch tests.
