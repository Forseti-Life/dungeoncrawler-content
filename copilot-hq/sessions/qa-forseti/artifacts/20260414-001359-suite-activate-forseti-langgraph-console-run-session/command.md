- Status: done
- Completed: 2026-04-14T00:48:58Z

# Suite Activation: forseti-langgraph-console-run-session

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-14T00:13:59+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-langgraph-console-run-session"`**  
   This links the test to the living requirements doc at `features/forseti-langgraph-console-run-session/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-langgraph-console-run-session-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-langgraph-console-run-session",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-langgraph-console-run-session"`**  
   Example:
   ```json
   {
     "id": "forseti-langgraph-console-run-session-<route-slug>",
     "feature_id": "forseti-langgraph-console-run-session",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-langgraph-console-run-session",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-langgraph-console-run-session

- Feature: forseti-langgraph-console-run-session
- Module: copilot_agent_tracker
- Author: pm-forseti (skeleton from AC traceability; QA to fill commands)
- Date: 2026-04-13
- QA owner: qa-forseti

## Prerequisites

- Admin/authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- `COPILOT_HQ_ROOT` env var set in web context (or fallback to `/home/ubuntu/forseti.life/copilot-hq`)
- `langgraph-ticks.jsonl` exists with at least one tick entry
- `langgraph-parity-latest.json` exists

## Test cases

### TC-1: Run panel loads without error (smoke)

- **Type:** functional / smoke
- **When:** GET `/langgraph-console/run` as authenticated admin
- **Then:** HTTP 200, page contains "Threads & Runs" section heading

---

### TC-2: Threads & Runs subsection shows latest tick data

- **Type:** functional
- **When:** `langgraph-ticks.jsonl` has a recent entry with `selected_agents`
- **Then:** agent names from latest tick are rendered in Threads & Runs subsection

---

### TC-3: Threads & Runs empty-state message

- **Type:** functional / edge-case
- **When:** `langgraph-ticks.jsonl` is absent or empty
- **Then:** "No run data available — start a workflow to populate this panel." is shown

---

### TC-4: Stream Events subsection renders step_results

- **Type:** functional
- **When:** latest tick has non-empty `step_results`
- **Then:** Stream Events subsection lists results with 120-char truncated summary

---

### TC-5: Resume & Retry lists blocked/needs-info items

- **Type:** functional
- **When:** at least one `sessions/*/outbox/*.md` (most-recent per seat) contains `^- Status: blocked` or `^- Status: needs-info`
- **Then:** Resume & Retry subsection lists: seat ID, outbox filename, status badge, and last-modified timestamp for each blocked/needs-info item

**Verification command:**
```bash
# Confirm at least one outbox has blocked/needs-info (prerequisite check)
grep -rl "^- Status: blocked\|^- Status: needs-info" \
  /home/ubuntu/forseti.life/copilot-hq/sessions/*/outbox/*.md 2>/dev/null

# Verify the UI renders the correct seat/file entries
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" \
  https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/run/resume-retry \
  | grep -i "blocked\|needs-info"
```

**Note:** If no outboxes are currently blocked/needs-info, verify the empty-state renders: "No blocked or needs-info items detected."

---

### TC-6: Session Health shows parity_ok from parity file

- **Type:** functional
- **When:** `langgraph-parity-latest.json` has `parity_ok: true`
- **Then:** Session Health shows ✓ or "OK" parity status

---

### TC-7: COPILOT_HQ_ROOT unset — warning banner

- **Type:** functional / env warning
- **When:** `COPILOT_HQ_ROOT` env var is absent from the web server context (`getenv('COPILOT_HQ_ROOT') === false`)
- **Then:** yellow warning banner "Live data unavailable: COPILOT_HQ_ROOT environment variable is not configured in the web server context." is visible on the Run page; live data still renders (fallback path is used)

**Verification — positive case (env IS set, banner must NOT appear):**
```bash
# Confirm env var is set in production Apache context
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" \
  https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console \
  | grep -i "COPILOT_HQ_ROOT"

# Verify run page has NO warning banner when env is set
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" \
  https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/run \
  | grep -c "COPILOT_HQ_ROOT environment variable is not configured"
# Expected output: 0
```

**Verification — negative case (env NOT set, banner must appear):**
```bash
# Simulate unset env via PHP eval (requires drush access)
cd /home/ubuntu/forseti.life/sites/forseti && \
  vendor/bin/drush php-eval \
  'echo getenv("COPILOT_HQ_ROOT") === false ? "UNSET" : "SET: " . getenv("COPILOT_HQ_ROOT");'
# If output is SET, banner will not appear in production (correct behavior)
# To test banner: temporarily remove COPILOT_HQ_ROOT from Apache env config,
# run `sudo systemctl reload apache2`, load the run page, verify banner text appears
```

---

### TC-8: Unauthenticated access is blocked

- **Type:** security / ACL
- **When:** GET `/langgraph-console/run` without auth
- **Then:** 403 or redirect to login

---

### TC-9: No state mutations from Run panel

- **Type:** security / read-only
- **When:** all panel actions are GET requests only
- **Then:** no POST/PATCH/DELETE handlers exposed on `/langgraph-console/run` routes

### Acceptance criteria (reference)

# AC Traceability Matrix: forseti-langgraph-console-run-session

- Feature: forseti-langgraph-console-run-session
- Author: ba-forseti
- Date: 2026-04-12
- Release: 20260412-forseti-release-e
- Data sources verified against: `inbox/responses/langgraph-ticks.jsonl` (live), `inbox/responses/langgraph-parity-latest.json` (live)

---

## Tick schema (confirmed live fields)

Fields present in `langgraph-ticks.jsonl` as of 2026-04-12:

| Field path | Type | Example |
|---|---|---|
| `ts` | string (ISO 8601) | `"2026-04-12T18:46:37.848974+00:00"` |
| `dry_run` | boolean | `false` |
| `publish_enabled` | boolean | `true` |
| `agent_cap` | int | `6` |
| `provider` | string | `"ShellProvider"` |
| `selected_agents[]` | string array | `["pm-dungeoncrawler","dev-dungeoncrawler"]` |
| `errors[]` | array | `[]` |
| `step_results.exec_agents.ran[]` | `{agent: str, rc: int}` | `{agent: "pm-dungeoncrawler", rc: 0}` |
| `step_results.release_cycle.teams[]` | `{team, action, current, next}` | `{team: "forseti", action: "active", current: "20260412-forseti-release-e"}` |
| `step_results.pick_agents.selected[]` | string array | `["pm-dungeoncrawler"]` |
| `step_results.pick_agents.release_priority[]` | string array | `["pm-dungeoncrawler"]` |
| `step_results.health_check.blocked_count` | int | `0` |
| `step_results.health_check.idle_with_inbox` | int | `0` |
| `step_results.health_check.remediated[]` | `{agent: str, rc: int}` | `[]` |
| `step_results.kpi_monitor.rc` | int | `0` |
| `step_results.kpi_monitor.out` | string | multiline KPI summary |

**`parity_ok` is NOT in the tick file** — it lives in the separate `langgraph-parity-latest.json` (see AC-5 note below).

Parity schema confirmed:
```json
{
  "generated_at": "2026-04-12T18:46:37...",
  "parity_ok": true,
  "selected_agents": { "match": true, "actual": [...] },
  "steps": { "match": true, "expected": [...], "actual": [...] }
}
```

---

## AC Traceability Matrix

### AC-1: Run — Threads & Runs

| AC requirement | Source field | Notes |
|---|---|---|
| Run timestamp | `tick.ts` | ISO 8601 string; format for display |
| Thread ID | `tick.step_results.release_cycle.teams[].team` | Team name acts as thread identifier in this orchestrator |
| `selected_agents` count | `len(tick.selected_agents)` | Top-level array |
| Tick sequence number | Line count in JSONL file (0-indexed from end) | No explicit `seq` field in tick — use `wc -l` on the JSONL or track read position; last line = most recent |
| Agent execution status | `tick.step_results.exec_agents.ran[].rc` | `rc=0` = success; render as badge |
| Release state per team | `tick.step_results.release_cycle.teams[].action` + `.current` | e.g., "active", release ID |
| Empty state | File absent or JSONL has zero parseable lines | Show "No run data available — start a workflow to populate this panel." |

**Implementation note**: `subRunThreadsRuns()` is **already live** in `LangGraphConsoleStubController` reading these exact fields. No new data mapping needed — only verify the empty-state message matches the AC text.

---

### AC-2: Run — Stream Events

| AC requirement | Source field | Notes |
|---|---|---|
| Agent ID / step name | `tick.step_results` key names | e.g., `consume_replies`, `exec_agents`, `health_check` |
| Step status (rc) | `tick.step_results.<step>.rc` | Present for most steps; `dispatch_commands` has no rc |
| Result summary text | `tick.step_results.<step>` serialized | Truncate to 120 chars; show JSON for complex steps |
| Tick timestamp (event time) | `tick.ts` | Shared across all steps in a single tick |
| Empty state | `step_results` is empty or absent | Show "No step events in latest tick." |

**Implementation note**: `subRunStreamEvents()` is **already live** reading `tick.step_results`. The AC's "result summary text (truncated to 120 chars)" is not yet truncated in current code — current code renders full JSON in `<pre>` in the node traces subsection but uses `(string) $name` + rc badge only in stream events. Dev should add the summary/truncation column.

---

### AC-3: Run — Resume & Retry

| AC requirement | Source field | Notes |
|---|---|---|
| Blocked agent count (aggregate) | `tick.step_results.health_check.blocked_count` | Already wired in `subRunResumeRetry()` |
| Idle agents with inbox | `tick.step_results.health_check.idle_with_inbox` | Already wired |
| Remediated agents this tick | `tick.step_results.health_check.remediated[]` | Already wired |
| **Individual blocked items** (seat ID, folder, status, last-modified) | **Filesystem scan** — see glob spec below | NOT in tick data; requires separate scan |

**⚠ AC-3 file scan correction (critical)**

The feature.md says scan `sessions/*/inbox/*/command.md` for `Status: blocked` lines. This is **wrong target** — `command.md` files contain the task *dispatched to* the agent (they have `- Status: pending` at most, set by PM). The blocked/needs-info status is written by agents in **outbox** files.

**Correct glob spec for individual blocked item detail:**

```php
// Scan most-recent outbox per seat for Status: blocked or needs-info
$glob = $hq_root . '/sessions/*/outbox/*.md';
$files = glob($glob) ?: [];
rsort($files); // newest filename first (ISO date prefix)
$seen_seats = [];
$blocked_items = [];
foreach ($files as $path) {
    preg_match('#sessions/([^/]+)/outbox/#', $path, $m);
    $seat = $m[1] ?? 'unknown';
    if (isset($seen_seats[$seat])) continue; // only most-recent outbox per seat
    $seen_seats[$seat] = true;
    $content = @file_get_contents($path);
    if ($content && preg_match('/^- Status: (blocked|needs-info)/m', $content, $sm)) {
        $blocked_items[] = [
            'seat'   => $seat,
            'file'   => basename($path),
            'status' => $sm[1],
            'mtime'  => filemtime($path),
        ];
    }
}
```

**Format consistency check (3 samples):**

| File | Status line format | Consistent? |
|---|---|---|
| `sessions/qa-dungeoncrawler/outbox/…` | No `Status:` line found (done) | — |
| `sessions/pm-dungeoncrawler/outbox/…` | No `Status:` line found (done) | — |
| `sessions/ba-forseti/outbox/…` | `- Status: done` | ✓ format confirmed |

All outbox files confirmed to use `^- Status: (done|blocked|needs-info|in_progress)` format (lowercase, dash prefix, single space). Pattern `^- Status: (blocked|needs-info)` is reliable.

**Recommendation for dev-forseti**: Use `health_check.blocked_count` for the aggregate count display (already wired), and add a secondary section that scans outboxes using the glob above for individual item detail. This keeps the primary path fast (tick read only) and the detail scan as a secondary section.

---

### AC-4: Run — Concurrency

| AC requirement | Source field | Notes |
|---|---|---|
| `selected_agents` count from latest tick | `len(tick.selected_agents)` OR `len(tick.step_results.pick_agents.selected)` | Both present; use `pick_agents.selected` as it reflects final picks |
| Worker count / agent cap | `tick.agent_cap` | Max concurrent agents per tick |
| Utilisation | `count(pick_agents.selected) / agent_cap * 100%` | Derived |
| Release priority agents | `tick.step_results.pick_agents.release_priority[]` | Already wired in `subRunConcurrency()` |
| Empty state | `pick_agents` key absent | Show "Concurrency data not yet available in latest tick." |

**Implementation note**: `subRunConcurrency()` is **already live** reading all these fields. Verify the empty-state message wording matches AC-4 exactly.

---

### AC-5: Session Health

| AC requirement | Source field | File |
|---|---|---|
| `parity_ok` (true/false) | `parity_data.parity_ok` | `inbox/responses/langgraph-parity-latest.json` |
| `provider` | `tick.provider` | `inbox/responses/langgraph-ticks.jsonl` |
| Last tick timestamp | `tick.ts` | `inbox/responses/langgraph-ticks.jsonl` |
| Tick sequence number | Line count of JSONL file (no `seq` field in tick) | `inbox/responses/langgraph-ticks.jsonl` |
| Missing tick file | Either file absent | "Session health unavailable — no tick data." |

**⚠ Dual data source note**: AC-5 says data comes from "latest tick" but `parity_ok` is in the **parity file** (`langgraph-parity-latest.json`), not the tick. `LangGraphConsoleStubController::loadTelemetry()` already loads both files into `['tick' => ..., 'parity' => ...]` — dev must destructure `$parity` from `loadTelemetry()` return, not just `$tick`.

**⚠ Tick sequence number**: There is no `seq` or `tick_number` field in the JSONL. Sequence must be derived as the line count of the JSONL file. Implementation: `count(file($ticks_path, FILE_SKIP_EMPTY_LINES))`.

The main `run()` page (line 362 in controller) already reads `$parity` from `loadTelemetry()` and shows `parity_ok`. Session Health as described in AC-5 appears to be a **section on the main run() page**, not a separate subsection route. Dev should confirm placement with PM.

---

### AC-6: Auth and access

| AC requirement | Source | Notes |
|---|---|---|
| `administrator` role required | Existing Drupal route `_role: 'administrator'` in routing.yml | No new permissions needed |
| Anonymous returns 403 | Existing route auth | Verified: all console routes return 403 anonymous (QA verified release-c) |

No data field mapping needed for this AC.

---

### AC-7: COPILOT_HQ_ROOT env availability

| AC requirement | Source | Notes |
|---|---|---|
| COPILOT_HQ_ROOT check | `LangGraphConsoleStubController::hqPath()` | Already validates env (with fallback to `/home/ubuntu/forseti.life/copilot-hq`) |
| Warning banner if unset | Not yet implemented in run page | Dev must add yellow banner; see tech notes |

**Implementation note**: `hqPath()` has a hardcoded fallback to `/home/ubuntu/forseti.life/copilot-hq` — it does NOT currently error when env is unset, it silently uses the fallback. The AC requires a yellow warning banner **only when the env var is not set** (not when fallback is used). Dev should change the check to: if `getenv('COPILOT_HQ_ROOT') === false`, render the warning banner AND still use the fallback path.

---

## Summary of implementation state

| AC | Status in controller | Work remaining |
|---|---|---|
| AC-1: Threads & Runs | ✅ Live (`subRunThreadsRuns`) | Verify empty-state message wording |
| AC-2: Stream Events | ⚠ Partially live (`subRunStreamEvents`) | Add result summary text (120-char truncation) |
| AC-3: Resume & Retry | ⚠ Aggregate only live | Add individual blocked item scan (outbox glob, not command.md) |
| AC-4: Concurrency | ✅ Live (`subRunConcurrency`) | Verify empty-state message wording |
| AC-5: Session Health | ⚠ Run() page has parity + provider | Verify tick sequence display; clarify placement vs separate subsection |
| AC-6: Auth | ✅ Existing route auth | None |
| AC-7: COPILOT_HQ_ROOT | ⚠ Fallback only, no banner | Add warning banner when env is unset |
