# Technical Notes: forseti-langgraph-console-run-session

- Feature: forseti-langgraph-console-run-session
- Author: ba-forseti
- Date: 2026-04-12
- Release: 20260412-forseti-release-e

---

## 1. COPILOT_HQ_ROOT / langgraphPath() resolution

**Finding:** `LangGraphConsoleStubController::hqPath()` (line 58) resolves the HQ root via:

```php
$root = rtrim((string) (getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'), '/');
```

This is functionally equivalent to `DashboardController::langgraphPath()` — both have the same COPILOT_HQ_ROOT-aware logic with the same fallback. **No extension is needed** for reading `langgraph-ticks.jsonl` — `$this->hqPath(self::TICKS_RELATIVE)` already resolves to the correct path.

**Ticks file path discrepancy:** The feature.md description says `$COPILOT_HQ_ROOT/tmp/langgraph-ticks.jsonl`. This is **incorrect**. The actual path constant is:

```php
const TICKS_RELATIVE = 'inbox/responses/langgraph-ticks.jsonl';
// Resolves to: /home/ubuntu/forseti.life/copilot-hq/inbox/responses/langgraph-ticks.jsonl
```

The feature.md text should be updated by pm-forseti to reference `inbox/responses/langgraph-ticks.jsonl`. This does NOT block implementation — dev should use the constant, not the feature description text.

**AC-7 gap:** When `COPILOT_HQ_ROOT` is not set, `hqPath()` silently uses the fallback — no warning is displayed. AC-7 requires a yellow warning banner in the run page when the env var is explicitly absent (not set). Required change:

```php
if (getenv('COPILOT_HQ_ROOT') === false) {
    $build['env_warning'] = [
        '#markup' => '<div class="messages messages--warning">'
            . $this->t('Live data unavailable: COPILOT_HQ_ROOT environment variable is not configured in the web server context.')
            . '</div>',
    ];
}
// Then proceed with hqPath() fallback as normal
```

---

## 2. parity_ok data source — AC-5

`parity_ok` is **not in the tick file**. It comes from:

```
inbox/responses/langgraph-parity-latest.json
```

```php
const PARITY_RELATIVE = 'inbox/responses/langgraph-parity-latest.json';
```

`loadTelemetry()` already loads both files:

```php
return [
    'tick'   => $this->readLastJsonl($this->hqPath(self::TICKS_RELATIVE)),
    'parity' => $this->readJson($this->hqPath(self::PARITY_RELATIVE)),
];
```

**Dev must** destructure both keys:

```php
['tick' => $tick, 'parity' => $parity] = $this->loadTelemetry();
$parity_ok = isset($parity['parity_ok']) ? (bool) $parity['parity_ok'] : NULL;
$provider  = (string) ($tick['provider'] ?? '—');
$ts        = (string) ($tick['ts'] ?? '');
```

**Tick sequence number**: No `seq` field exists in the tick. Derive as:

```php
$ticks_path = $this->hqPath(self::TICKS_RELATIVE);
$seq = is_readable($ticks_path)
    ? count(file($ticks_path, FILE_SKIP_EMPTY_LINES))
    : '—';
```

---

## 3. AC-3 — blocked/needs-info inbox scan (corrected glob)

**Original spec (feature.md):** scan `sessions/*/inbox/*/command.md` for `Status: blocked` or `Status: needs-info`.

**Problem:** `command.md` files are the task specs dispatched TO agents (they contain `- Status: pending` or no Status line). The blocked/needs-info status is written by agents in **outbox** files at `sessions/<seat>/outbox/<timestamp>-<description>.md`.

**Correct approach for individual item detail (supplement to `health_check.blocked_count`):**

```php
// Primary count: from tick (already implemented in subRunResumeRetry)
$blocked_count = (int) ($hc['blocked_count'] ?? 0);

// Secondary: individual item detail from outboxes
$outbox_glob = $this->hqPath('sessions/*/outbox/*.md');
$files = glob($outbox_glob) ?: [];
rsort($files); // newest file first (ISO date prefix names)
$seen = [];
$blocked_detail = [];
foreach ($files as $path) {
    if (!preg_match('#sessions/([^/]+)/outbox/#', $path, $m)) continue;
    $seat = $m[1];
    if (isset($seen[$seat])) continue; // only most-recent outbox per seat
    $seen[$seat] = true;
    $content = @file_get_contents($path);
    if ($content && preg_match('/^- Status: (blocked|needs-info)/m', $content, $sm)) {
        $blocked_detail[] = [
            'seat'   => $seat,
            'file'   => basename($path),
            'status' => $sm[1],
            'mtime'  => date('Y-m-d H:i', (int) filemtime($path)),
        ];
    }
}
```

**Status line format confirmed** (5 outbox samples checked):
- Pattern: `^- Status: (done|blocked|needs-info|in_progress)` (always lowercase, dash prefix)
- This is consistent across all seats and is enforced by outbox format rules in org-wide instructions

---

## 4. Route disambiguation — Run vs Session Health

**Finding from controller inspection:**

The console has **separate subsection routes** under `/langgraph-console/run`:
- `run/threads-runs` → `subRunThreadsRuns()` — LIVE
- `run/stream-events` → `subRunStreamEvents()` — LIVE (partial, needs truncation)
- `run/resume-retry` → `subRunResumeRetry()` — LIVE (aggregate only; needs individual detail)
- `run/concurrency` → `subRunConcurrency()` — LIVE

**Session Health is NOT a separate subsection route.** There is no `run/session-health` entry in `sectionMap()`. Session Health data (`parity_ok`, `provider`, `ts`) currently appears on the **main `run()` page** (line 362–440 in controller) in the "Health & Resume" table. The `run()` page renders a summary table plus nav links to the 4 subsections.

**AC-5 maps to the main `run()` page**, not a subsection. Dev should extend the existing "Health & Resume" table in `run()` to add the required fields (parity_ok badge, provider, ts formatted, sequence number).

**PM clarification flag**: If PM wants Session Health as a dedicated subsection, a new entry must be added to `sectionMap()` and a new `subRunSessionHealth()` method created. This is a scope decision for pm-forseti, not a dev implementation decision.

---

## 5. Pre-implementation status of run subsections

All 4 run subsections are **already live** (not stubs) as of prior releases:

| Subsection | Method | Already reads tick data | Gap vs AC |
|---|---|---|---|
| Threads & Runs | `subRunThreadsRuns()` | ✅ `exec_agents.ran`, `release_cycle.teams` | Empty-state message wording |
| Stream Events | `subRunStreamEvents()` | ✅ `step_results.*` | Needs 120-char result summary truncation |
| Resume & Retry | `subRunResumeRetry()` | ✅ `health_check.*` | Needs individual blocked item detail (outbox scan) |
| Concurrency | `subRunConcurrency()` | ✅ `pick_agents.*`, `agent_cap` | Empty-state message wording |

The feature's remaining "wiring" work is gap-filling, not new wiring. Dev effort is lighter than the feature brief implies.

---

## BA Confirmation (2026-04-13) — all 4 open questions resolved against live code

### Q1 — AC-3 Resume & Retry: correct glob pattern and parsing approach

**Confirmed:** Scan `sessions/*/outbox/*.md` — NOT `sessions/*/inbox/*/command.md`.

`command.md` files are task specs dispatched TO agents by PM. They have `- Status: pending` at most and never contain `Status: blocked` or `Status: needs-info`. Blocked/needs-info status is always written by agents into their **outbox** files.

**Exact glob spec for dev:**
```php
$glob  = $this->hqPath('sessions/*/outbox/*.md');
$files = glob($glob) ?: [];
rsort($files); // newest filename first (ISO date prefix)
$seen  = [];
$blocked_detail = [];
foreach ($files as $path) {
    if (!preg_match('#sessions/([^/]+)/outbox/#', $path, $m)) continue;
    $seat = $m[1];
    if (isset($seen[$seat])) continue; // only most-recent outbox per seat
    $seen[$seat] = true;
    $content = @file_get_contents($path);
    if ($content && preg_match('/^- Status: (blocked|needs-info)/m', $content, $sm)) {
        $blocked_detail[] = [
            'seat'   => $seat,
            'file'   => basename($path),
            'status' => $sm[1],
            'mtime'  => date('Y-m-d H:i', (int) filemtime($path)),
        ];
    }
}
```

Status line format confirmed live: `^- Status: <value>` (lowercase, dash prefix, single space). Pattern is reliable across all seats. This scan supplements `health_check.blocked_count` from the tick: primary count (tick-read, fast) → aggregate row; secondary (outbox glob) → individual item detail table.

---

### Q2 — AC-2 result summary truncation: field and HTML placement

**Confirmed via live `subRunStreamEvents()` inspection (lines 971–995):**

Current method renders 4 columns: `Seq | Step | RC | Tick timestamp`. There is **no result summary column** — it must be added as a **5th column after RC**.

**Which field to truncate:** For each step in `step_results`, take the full `$data` array, remove the `rc` key, JSON-encode the rest. Truncate to 120 chars with `…` suffix. Steps with no data beyond `rc` show `—`.

```php
$step_data_clean = $data;
unset($step_data_clean['rc']);
$summary = !empty($step_data_clean)
    ? json_encode($step_data_clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : '—';
if (strlen($summary) > 120) {
    $summary = substr($summary, 0, 117) . '…';
}
```

Updated header: `Seq | Step | RC | Summary | Tick timestamp`. The 120-char truncation pattern already exists at line 1435 of this controller (outbox scanner) — follow the same approach.

---

### Q3 — AC-7 COPILOT_HQ_ROOT warning: banner semantics

**Confirmed:** Banner must appear when `getenv('COPILOT_HQ_ROOT') === false`, **even when the fallback resolves and data loads successfully**. This is an operational signal ("running without explicit env config"), not an error.

Current `run()` (line 364) has no env check. Required addition before returning `$build`:

```php
if (getenv('COPILOT_HQ_ROOT') === false) {
    $build = ['env_warning' => [
        '#markup' => '<div class="messages messages--warning">'
            . $this->t('Live data unavailable: COPILOT_HQ_ROOT environment variable is not configured in the web server context.')
            . '</div>',
    ]] + $build;
}
return $build;
```

Banner text must match AC-7 exactly. When env var IS set, no banner is shown.

---

### Q4 — Session Health placement: main run() page, not a subsection

**Confirmed via live code (lines 362–464):**

No `run/session-health` route exists in `sectionMap()`. The `run()` page already has a "Health & Resume" table (lines 440–451). `$parity` is destructured at line 365 but **not used in the run page health table** (only used in `observe()`). All needed data is already in scope.

**Dev must extend `health_table` `#rows` in `run()` with:**
- `parity_ok` badge: `isset($parity['parity_ok']) ? ((bool)$parity['parity_ok'] ? '✓ OK' : '✗ FAIL') : '—'`
- `provider`: `(string)($tick['provider'] ?? '—')`
- Last tick timestamp: add as explicit named health row (currently only in `ts_note`)
- Tick sequence number: `count(file($this->hqPath(self::TICKS_RELATIVE), FILE_SKIP_EMPTY_LINES))`

No new route or method required. If PM wants Session Health as a dedicated subsection, that is a PM scope decision requiring a new `sectionMap()` entry and `subRunSessionHealth()` method.
