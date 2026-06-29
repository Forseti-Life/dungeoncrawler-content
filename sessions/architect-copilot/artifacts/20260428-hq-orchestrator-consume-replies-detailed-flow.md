# HQ Orchestrator Tick — Detailed Flow Review: `consume_replies`

- Date: 2026-04-28
- Author: architect-copilot
- Scope: First node of the live `hq_orchestrator_tick` flow
- Question: Is the first LangGraph node genuinely modeled in LangGraph, or is the real control flow still buried in scripts?

---

## Executive finding

**The first tick node is not meaningfully modeled in LangGraph yet.**

In the current implementation, the LangGraph node `consume_replies` is only a
thin wrapper around:

1. a Bash script (`scripts/consume-forseti-replies.sh`)
2. two inline `drush php:eval` database calls
3. an inline Python routing/materialization block
4. filesystem side effects under `sessions/`
5. a best-effort archive mutation in the CEO inbox

LangGraph only sees **one command execution result code**. It does not model the
intermediate state, decisions, branching, counts, failures, or outputs inside
this reply-ingestion path.

---

## Control-plane view vs. live execution

### What the flow registry says

`drupal-langgraph/src/Service/ProcessFlowRegistryService.php`

- Flow: `hq_orchestrator_tick`
- Entrypoint: `consume_replies`
- Nodes: `consume_replies -> dispatch_commands -> release_cycle -> coordinated_push -> pick_agents -> exec_agents -> health_check -> kpi_monitor -> publish`
- Routing rule: "Always execute tick nodes in pipeline order."

### What the LangGraph engine actually does

`orchestrator/runtime_graph/engine.py`

```python
def consume_replies(s):
    rc, _ = deps.run_cmd(["bash", "scripts/consume-forseti-replies.sh"], timeout=300)
    s["log"].append({"step": "consume_replies", "rc": rc})
    return s
```

So the graph node does **not** expose:

- which replies were found
- which target seats were resolved
- whether routing fell back to CEO
- how many inbox items were created
- whether any resolved items were archived
- whether Drupal rows were partially updated

The graph only records `rc`.

---

## Detailed live process flow

## 1. Tick enters the LangGraph node

Source:

- `orchestrator/run.py`
- `orchestrator/runtime_graph/engine.py`

Flow:

1. `run.py` builds dependency closures and calls `run_tick(...)`.
2. `run_tick()` creates a `StateGraph(dict)`.
3. Entry point is hardcoded to `consume_replies`.
4. The node executes exactly one shell command:
   - `bash scripts/consume-forseti-replies.sh`

### LangGraph state before node

- `ts`
- `log`
- `selected_agents`
- `agent_cap`
- `publish_enabled`
- interval/cooldown fields
- provider metadata

### LangGraph state after node

- only `log += {"step": "consume_replies", "rc": <code>}`

No structured reply-ingestion state is preserved.

---

## 2. Script bootstraps HQ + Drupal path context

Source:

- `scripts/consume-forseti-replies.sh`
- `scripts/lib/site-paths.sh`

Flow:

1. Resolve `ROOT_DIR` as repo root.
2. `cd "$ROOT_DIR"`.
3. Source `scripts/lib/site-paths.sh`.
4. Resolve Drupal path:
   - `FORSETI_SITE_DIR` from env or `/var/www/html/forseti`
   - back-compat alias `FORSITI_SITE_DIR`
5. Resolve `DRUSH_BIN="$FORSITI_SITE_DIR/vendor/bin/drush"`.
6. Hard fail if Drush is missing.

### Side effects

- None yet, except process exit on missing Drush.

### Architectural note

This node already depends on:

- repo filesystem layout
- Drupal install path
- Drush availability
- shell environment aliases

Those dependencies are not represented in the graph state.

---

## 3. Script resolves the active CEO seat

Source:

- `scripts/consume-forseti-replies.sh`
- `scripts/is-agent-paused.sh`
- `org-chart/agents/agents.yaml`

Flow:

1. Read `ORCHESTRATOR_CEO_AGENT` if present.
2. Accept it only if:
   - it starts with `ceo-copilot`
   - `is-agent-paused.sh <id>` returns not paused
3. Otherwise parse all `- id:` entries from `agents.yaml`.
4. Keep the first `ceo-copilot*` seat that is not paused.
5. If all else fails, default to `ceo-copilot`.

### Hidden branching

- env override path
- YAML-discovery path
- paused-seat rejection path
- dead fallback to deprecated `ceo-copilot`

### Why this matters

This is real business logic, but LangGraph cannot see:

- which CEO seat was chosen
- whether fallback happened
- whether the chosen seat is deprecated

---

## 4. Script probes Drupal for the reply table

Source:

- `scripts/consume-forseti-replies.sh`

Flow:

1. Run Drush with inline PHP:
   - `tableExists("copilot_agent_tracker_replies")`
2. If table missing:
   - print `Skipping consume replies: copilot_agent_tracker_replies table missing`
   - exit `0`

### Important behavior

Missing reply infrastructure is treated as a **successful no-op**, not a
degraded state or explicit graph branch.

### Architectural note

LangGraph currently cannot distinguish:

- no replies pending
- tracker module absent
- schema missing
- query failure hidden behind Drush behavior

All of those can collapse into the same high-level `rc` outcome.

---

## 5. Script pulls pending replies from Drupal

Source:

- `scripts/consume-forseti-replies.sh`

Query shape:

- table: `copilot_agent_tracker_replies`
- fields:
  - `id`
  - `to_agent_id`
  - `in_reply_to`
  - `message`
  - `created`
- filter: `consumed = 0`
- order: oldest first
- cap: `25`

### Result contract

The Drush PHP block returns a JSON array to Bash:

```json
[
  {
    "id": 123,
    "to_agent_id": "pm-forseti",
    "in_reply_to": "20260427-some-item",
    "message": "Please revisit...",
    "created": 1714300000
  }
]
```

### Architectural note

The graph has no typed state for replies. A structured LangGraph design would
carry this as state and allow downstream routing/validation nodes. Instead, it
is serialized out of Drupal into a shell variable.

---

## 6. Inline Python performs the real routing + materialization

Source:

- inline Python block inside `scripts/consume-forseti-replies.sh`

This is the **real** heart of the node.

### 6.1 Load configured seats

1. Open `org-chart/agents/agents.yaml`.
2. Regex-parse every `- id: ...`.
3. Build a `configured` set.

### 6.2 Process each reply row

For every Drupal reply:

1. Read:
   - `rid`
   - `to_agent`
   - `in_reply_to`
   - `msg`
2. Skip rows with empty target or empty message.
3. Preserve original target in `intended`.
4. If `to_agent` is not a configured seat:
   - route to the resolved CEO seat instead

### 6.3 Generate HQ inbox item ID

Pattern:

```text
YYYYMMDD-reply-keith-<slug>-<reply_id>
```

Where:

- `slug` is derived from `in_reply_to`
- non `[A-Za-z0-9._-]` chars become `-`
- slug is truncated to 50 chars
- fallback is `compose-<rid>`

### 6.4 Create filesystem inbox item

Creates:

```text
sessions/<target-agent>/inbox/<item-id>/
  command.md
  roi.txt
```

`command.md` includes:

- reply provenance
- `drupal_reply_id`
- HQ item ID
- optional note when rerouted to CEO
- raw message body

`roi.txt` is hardcoded to:

```text
5
```

### 6.5 Build stdout handoff contract back to Bash

The Python block prints three ad hoc channels:

- `IDS=<space-separated reply ids>`
- `RESOLVED=<space-separated in_reply_to values>`
- `MAP=<json reply-id -> hq-item-id>`

### Architectural note

This is the clearest evidence that the real control flow is outside LangGraph:

- routing rules live in inline Python, not graph nodes
- state is passed via stdout strings, not typed graph state
- filesystem writes happen inside the same opaque script block
- fixed ROI policy is buried in the script

---

## 7. Bash reparses Python stdout into shell variables

Source:

- `scripts/consume-forseti-replies.sh`

Flow:

1. Parse `IDS=...` using `sed`
2. Parse `RESOLVED=...` using `sed`
3. Parse `MAP=...` using `sed`
4. If `ids` is empty:
   - exit `0`

### Architectural note

This is a fragile serialization boundary:

- Python emits text
- Bash reparses it
- Drupal update step later reparses JSON from env

In a LangGraph-native flow, these would be state fields.

---

## 8. Script marks Drupal rows consumed

Source:

- `scripts/consume-forseti-replies.sh`

Flow:

1. Capture `now = date +%s`
2. Run a second Drush PHP block
3. For each consumed reply ID:
   - set `consumed = 1`
   - set `consumed_at = now`
   - set `hq_item_id` to the generated HQ inbox item ID

### Side effects

- Drupal database mutation
- persistence of HQ/Drupal cross-reference

### Missing graph visibility

LangGraph does not know:

- which rows were updated
- whether update count matches created inbox items
- whether partial update occurred

---

## 9. Script archives resolved CEO inbox items

Source:

- `scripts/consume-forseti-replies.sh`

Flow:

1. For every `in_reply_to` value emitted by Python:
   - look for `sessions/<CEO_AGENT>/inbox/<in_reply_to>`
2. If present:
   - move it to `sessions/<CEO_AGENT>/artifacts/resolved/<item>-<timestamp>`
3. Failure is ignored (`|| true`)

### Important behavior

This is a **second hidden mutation** unrelated to reply creation:

- consuming a reply can also archive an existing CEO inbox item
- archive behavior is best-effort and untracked in graph state

### Architectural note

This should likely be a separate node or substep if it remains part of the
official process model.

---

## 10. LangGraph receives only the shell return code

After all of the above:

1. control returns to `engine.py`
2. LangGraph logs:

```python
{"step": "consume_replies", "rc": rc}
```

No reply IDs, no seat routing summary, no item count, no archive count, no
fallback count, no partial-failure markers.

---

## Inputs, outputs, and side effects

## Inputs

- Drupal DB table `copilot_agent_tracker_replies`
- `org-chart/agents/agents.yaml`
- `scripts/is-agent-paused.sh`
- environment:
  - `ORCHESTRATOR_CEO_AGENT`
  - `FORSETI_SITE_DIR`
  - repo path assumptions

## Outputs

- new HQ inbox items under `sessions/<agent>/inbox/...`
- updated Drupal reply rows (`consumed`, `consumed_at`, `hq_item_id`)
- archived CEO inbox items under `sessions/<CEO_AGENT>/artifacts/resolved/...`
- one coarse graph log entry: `consume_replies rc=<n>`

## Hidden policy embedded in script

- unknown target seats reroute to CEO
- reply batch cap = 25
- message with blank target/body is dropped
- ROI fixed at 5
- missing Drupal table is a successful no-op
- resolved CEO item archive is best-effort

---

## Trigger origin: what is known vs unknown

## Known

- The script assumes Drupal UI or API writes rows into
  `copilot_agent_tracker_replies`.
- QA automation asserts that this table exists as part of the tracker module:
  `qa-suites/products/forseti-agent-tracker/run-copilot-agent-tracker-tests.py`

## Unknown / not found in tracked source during this review

- the tracked PHP source that defines the table schema
- the tracked form/controller/API code that writes reply rows

That means the **consumer path is in repo**, but the **producer path is not
clearly discoverable from tracked source in this checkout**.

For architecture review, that is itself a finding.

---

## Why this suggests “script logic over LangGraph”

The current `consume_replies` node uses LangGraph mainly as:

1. a deterministic scheduler
2. a step logger
3. a wrapper around external commands

The node does **not** use LangGraph to model:

- reply records as state
- target resolution as routing
- CEO fallback as a branch
- item creation as a graph transformation
- archive behavior as a separate side-effect node
- update acknowledgement / reconciliation

Instead, the real state machine is:

- Bash orchestration
- inline Python routing
- inline Drush PHP queries
- shell stdout parsing
- filesystem mutation

That is operationally workable, but it is not yet a graph-native design.

---

## Recommended LangGraph-native decomposition

If this node is refactored toward real LangGraph usage, the first step should
become a small subgraph with explicit state.

### Proposed subgraph: `consume_replies`

1. `resolve_active_ceo_seat`
2. `load_pending_replies`
3. `validate_and_normalize_targets`
4. `materialize_hq_inbox_items`
5. `mark_replies_consumed`
6. `archive_resolved_ceo_items`
7. `emit_reply_ingestion_summary`

### Proposed state fields

- `pending_replies[]`
- `resolved_ceo_agent`
- `created_items[]`
- `rerouted_items[]`
- `consumed_reply_ids[]`
- `archived_reply_targets[]`
- `ingestion_warnings[]`

### Immediate benefit

That would let the graph/logging surface:

- how many replies were ingested
- which seats received work
- whether CEO fallback happened
- whether archive/update steps diverged
- where partial failures occurred

---

## Review-ready conclusions

1. **Yes, the first node is mostly script logic, not graph logic.**
2. **The live reply-ingestion process is materially more detailed than the current flow registry or LangGraph telemetry exposes.**
3. **The highest-value next architecture step is to split `consume_replies` into explicit substeps/state rather than continuing to treat the entire reply-ingestion path as one opaque shell node.**
4. **The trigger producer path should be separately traced**, because the consumer depends on a Drupal table whose tracked producer source was not clearly discoverable in this review.

---

## Primary source files reviewed

- `orchestrator/run.py`
- `orchestrator/runtime_graph/engine.py`
- `orchestrator/README.md`
- `scripts/consume-forseti-replies.sh`
- `scripts/is-agent-paused.sh`
- `scripts/lib/site-paths.sh`
- `orchestrator/tests/test_consume_forseti_replies.py`
- `qa-suites/products/forseti-agent-tracker/run-copilot-agent-tracker-tests.py`
- `drupal-langgraph/src/Service/ProcessFlowRegistryService.php`
