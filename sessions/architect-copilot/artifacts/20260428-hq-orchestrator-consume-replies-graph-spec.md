# Graph Object Translation Spec — `consume_replies`

- Date: 2026-04-28
- Author: architect-copilot
- Scope: Graph-ready process flow for replacing the current `scripts/consume-forseti-replies.sh` shell node
- Status: Planning/spec only

---

## Purpose

This document is the graph-ready process flow derived from the live
`consume_replies` review. It is intended to be directly translatable into a
LangGraph subgraph or a decomposed section of `hq_orchestrator_tick`.

---

## Proposed state object

```python
from typing import Any, Dict, List, Optional, TypedDict


class PendingReply(TypedDict):
    id: int
    to_agent_id: str
    in_reply_to: str
    message: str
    created: int


class NormalizedReply(TypedDict):
    id: int
    original_to_agent_id: str
    resolved_to_agent_id: str
    in_reply_to: str
    message: str
    created: int
    rerouted_to_ceo: bool


class InboxWorkItem(TypedDict):
    reply_id: int
    target_agent_id: str
    item_id: str
    command_md: str
    roi: int


class ConsumeRepliesState(TypedDict, total=False):
    repo_root: str
    forseti_site_dir: str
    drush_bin: str

    active_ceo_agent: str
    configured_agents: List[str]

    reply_table_exists: bool
    pending_replies: List[PendingReply]
    valid_replies: List[PendingReply]
    normalized_replies: List[NormalizedReply]
    work_items: List[InboxWorkItem]

    consumed_reply_ids: List[int]
    created_item_ids: List[str]
    archived_source_items: List[str]

    skipped_reply_ids: List[int]
    warnings: List[str]
    errors: List[str]

    created_count: int
    rerouted_count: int
    archived_count: int
```

---

## Node list

## 1. `resolve_runtime_context`

### Purpose

Normalize repo root, Drupal site path, and Drush path.

### Inputs

- env overrides
- repo-relative defaults

### Outputs

- `repo_root`
- `forseti_site_dir`
- `drush_bin`

### Failure

- append error if Drush missing
- route to terminal summary with failure

---

## 2. `load_configured_agents`

### Purpose

Load configured seat IDs from `org-chart/agents/agents.yaml`.

### Inputs

- `repo_root`

### Outputs

- `configured_agents`

### Notes

- this should replace the current regex parse hidden in inline Python

---

## 3. `resolve_active_ceo_agent`

### Purpose

Resolve CEO fallback target.

### Inputs

- env `ORCHESTRATOR_CEO_AGENT`
- `configured_agents`
- pause status lookup

### Outputs

- `active_ceo_agent`
- optional warning if fallback/deprecated default used

### Branches

1. env override valid -> use it
2. else first unpaused `ceo-copilot*` -> use it
3. else fallback -> warning + default

---

## 4. `check_reply_table`

### Purpose

Determine whether `copilot_agent_tracker_replies` exists.

### Inputs

- Drupal/Drush adapter

### Outputs

- `reply_table_exists`

### Branches

1. exists -> continue
2. missing -> short-circuit to summary as explicit no-op state

---

## 5. `load_pending_replies`

### Purpose

Read up to 25 oldest unconsumed replies.

### Inputs

- Drupal/Drush adapter

### Outputs

- `pending_replies`

### Notes

- preserve current query semantics initially

---

## 6. `filter_invalid_replies`

### Purpose

Drop rows with no target or no message.

### Inputs

- `pending_replies`

### Outputs

- `valid_replies`
- `skipped_reply_ids`
- `warnings`

### Branches

1. all invalid -> go to summary
2. any valid -> continue

---

## 7. `resolve_target_agents`

### Purpose

Normalize targets and reroute unknown seats to CEO.

### Inputs

- `valid_replies`
- `configured_agents`
- `active_ceo_agent`

### Outputs

- `normalized_replies`
- `rerouted_count`
- `warnings`

### Rules

- known target -> preserve
- unknown target -> rewrite to `active_ceo_agent` and note reroute

---

## 8. `build_work_items`

### Purpose

Create the graph-visible representation of HQ inbox items before persistence.

### Inputs

- `normalized_replies`

### Outputs

- `work_items`
- `created_item_ids`

### Rules

- preserve current item ID convention
- preserve current `command.md` contract initially
- preserve default ROI value initially

---

## 9. `persist_work_items`

### Purpose

Create `sessions/<agent>/inbox/<item>/command.md` and `roi.txt`.

### Inputs

- `repo_root`
- `work_items`

### Outputs

- `created_count`
- `consumed_reply_ids`
- `errors`

### Failure behavior

- if persistence fails for any item, do not acknowledge that reply as consumed

---

## 10. `acknowledge_consumed_replies`

### Purpose

Mark Drupal rows as consumed and store `hq_item_id`.

### Inputs

- `consumed_reply_ids`
- `work_items`

### Outputs

- warning/error if ack count diverges from persisted item count

### Notes

- this must remain a distinct side-effect node

---

## 11. `archive_resolved_ceo_items`

### Purpose

Move referenced CEO inbox items to resolved artifacts.

### Inputs

- `active_ceo_agent`
- `normalized_replies`
- `repo_root`

### Outputs

- `archived_source_items`
- `archived_count`
- `warnings`

### Notes

- best-effort behavior may be preserved initially, but should be visible in state

---

## 12. `summarize_consume_replies`

### Purpose

Produce the structured tick log payload for this subgraph.

### Inputs

- all accumulated state

### Outputs

- log-ready summary object with:
  - `reply_table_exists`
  - `pending_count`
  - `valid_count`
  - `created_count`
  - `rerouted_count`
  - `archived_count`
  - `warning_count`
  - `error_count`

---

## Edge model

```text
resolve_runtime_context
  -> load_configured_agents
  -> resolve_active_ceo_agent
  -> check_reply_table

check_reply_table
  -> [missing] summarize_consume_replies
  -> [exists]  load_pending_replies

load_pending_replies
  -> filter_invalid_replies

filter_invalid_replies
  -> [none-valid] summarize_consume_replies
  -> [has-valid]  resolve_target_agents

resolve_target_agents
  -> build_work_items
  -> persist_work_items
  -> acknowledge_consumed_replies
  -> archive_resolved_ceo_items
  -> summarize_consume_replies
```

---

## Graph-object sketch

```python
from langgraph.graph import StateGraph


graph = StateGraph(ConsumeRepliesState)

graph.add_node("resolve_runtime_context", resolve_runtime_context)
graph.add_node("load_configured_agents", load_configured_agents)
graph.add_node("resolve_active_ceo_agent", resolve_active_ceo_agent)
graph.add_node("check_reply_table", check_reply_table)
graph.add_node("load_pending_replies", load_pending_replies)
graph.add_node("filter_invalid_replies", filter_invalid_replies)
graph.add_node("resolve_target_agents", resolve_target_agents)
graph.add_node("build_work_items", build_work_items)
graph.add_node("persist_work_items", persist_work_items)
graph.add_node("acknowledge_consumed_replies", acknowledge_consumed_replies)
graph.add_node("archive_resolved_ceo_items", archive_resolved_ceo_items)
graph.add_node("summarize_consume_replies", summarize_consume_replies)

graph.set_entry_point("resolve_runtime_context")

graph.add_edge("resolve_runtime_context", "load_configured_agents")
graph.add_edge("load_configured_agents", "resolve_active_ceo_agent")
graph.add_edge("resolve_active_ceo_agent", "check_reply_table")

graph.add_conditional_edges(
    "check_reply_table",
    route_after_check_reply_table,
    {
        "summarize": "summarize_consume_replies",
        "load": "load_pending_replies",
    },
)

graph.add_edge("load_pending_replies", "filter_invalid_replies")

graph.add_conditional_edges(
    "filter_invalid_replies",
    route_after_filter_invalid_replies,
    {
        "summarize": "summarize_consume_replies",
        "resolve": "resolve_target_agents",
    },
)

graph.add_edge("resolve_target_agents", "build_work_items")
graph.add_edge("build_work_items", "persist_work_items")
graph.add_edge("persist_work_items", "acknowledge_consumed_replies")
graph.add_edge("acknowledge_consumed_replies", "archive_resolved_ceo_items")
graph.add_edge("archive_resolved_ceo_items", "summarize_consume_replies")
graph.set_finish_point("summarize_consume_replies")
```

---

## Tick integration options

## Option A — Replace the current node with a subgraph call

Keep `hq_orchestrator_tick` mostly intact and replace the existing
`consume_replies` node with a Python function that invokes this subgraph.

### Pros

- smaller integration change
- preserves current top-level tick shape
- easiest migration path

### Cons

- top-level tick still logs one parent step unless telemetry is expanded

## Option B — Promote subnodes into top-level tick graph

Expose the `consume_replies` decomposition directly in the main tick graph.

### Pros

- maximum visibility
- full node-by-node telemetry

### Cons

- larger dashboard/parity/schema change
- higher migration surface

### Recommendation

Start with **Option A**, but emit richer nested telemetry immediately.

---

## Required adapters

To keep the graph clean, define adapter interfaces around:

1. `read_pending_replies()`
2. `mark_replies_consumed(reply_to_item_map)`
3. `is_agent_paused(agent_id)`
4. `write_inbox_item(agent_id, item_id, command_md, roi)`
5. `archive_ceo_item(ceo_agent, inbox_item_id)`

The graph should call adapters, not shell snippets.

---

## Minimum telemetry contract

When this subgraph completes, the parent tick should be able to log:

```json
{
  "step": "consume_replies",
  "reply_table_exists": true,
  "pending_count": 3,
  "valid_count": 3,
  "created_count": 3,
  "rerouted_count": 1,
  "archived_count": 2,
  "warning_count": 1,
  "error_count": 0
}
```

That is the minimum threshold for saying the step is graph-visible rather than
just script-executed.

---

## Recommended next implementation move

Implement the adapter-backed Python module first, then wire the subgraph, then
retire the inline Python block, then retire the shell wrapper once parity is
confirmed.
