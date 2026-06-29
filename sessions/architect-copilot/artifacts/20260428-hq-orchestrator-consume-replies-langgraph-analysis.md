# LangGraph Migration Analysis — `consume_replies`

- Date: 2026-04-28
- Author: architect-copilot
- Scope: Refactor the first node of `hq_orchestrator_tick` from script-centric execution to graph-centric execution
- Source review: `20260428-hq-orchestrator-consume-replies-detailed-flow.md`

---

## Goal

Move `consume_replies` from an opaque shell step to a **real LangGraph subgraph**
with:

- explicit state
- explicit node boundaries
- typed intermediate data
- auditable branch decisions
- structured success / warning / error outputs

The objective is not “remove scripts because scripts are bad.” The objective is:

**make the orchestration logic visible, testable, stateful, and graph-native.**

---

## Current architecture problem

Today the `consume_replies` LangGraph node does this:

```python
rc, _ = deps.run_cmd(["bash", "scripts/consume-forseti-replies.sh"], timeout=300)
log.append({"step": "consume_replies", "rc": rc})
```

That means the graph treats the entire reply-ingestion lifecycle as one black
box.

Inside that black box are at least five distinct responsibilities:

1. resolve runtime environment + Drupal access
2. resolve the active CEO fallback seat
3. query Drupal for pending replies
4. validate/reroute/materialize HQ inbox items
5. acknowledge Drupal rows + archive resolved CEO items

These are not “implementation details”; they are the actual orchestration logic.

---

## Why this is a poor LangGraph fit today

## 1. State is externalized instead of modeled

Current state transitions happen through:

- shell variables
- stdout parsing
- ad hoc JSON strings
- filesystem mutations
- direct Drupal DB writes

Instead of:

- state object fields
- graph node return values
- explicit branch markers
- observable outputs per node

## 2. Decision points are hidden

Examples:

- If the configured CEO seat is paused, the script silently searches another one.
- If `to_agent_id` is unknown, the script silently reroutes to CEO.
- If the Drupal table is missing, the step exits successfully.
- If `in_reply_to` maps to a CEO inbox item, that item may be archived.

Those are graph branches in practice, but they are not represented as branches.

## 3. Telemetry is too coarse

The graph log can answer only:

- did the shell step return `0`?

It cannot answer:

- how many replies were pending?
- how many were skipped?
- how many were rerouted to CEO?
- how many inbox items were created?
- how many archive operations occurred?
- which warning conditions were encountered?

## 4. Testing is forced to the script boundary

The dedicated test (`orchestrator/tests/test_consume_forseti_replies.py`) is
already a sign of the current shape:

- it mocks Drush
- executes the script end-to-end
- inspects filesystem side effects

That is useful, but it tests the whole blob, not the orchestration decisions in
composable pieces.

---

## Architectural target

`consume_replies` should become a **subgraph** inside the tick, not a single
shell node.

Recommended posture:

- keep existing behavior semantics first
- move orchestration decisions into Python/LangGraph nodes
- isolate I/O adapters at the edges
- preserve filesystem queue model for now
- replace Bash stdout handoff with typed graph state

This is an **evolutionary refactor**, not a rewrite of the whole HQ model.

---

## Proposed decomposition

## Keep at the edge

These can remain adapter-style functions initially:

- Drupal reply read
- Drupal reply acknowledgment write
- filesystem inbox item write
- filesystem archive move

## Move into graph logic

These should become first-class LangGraph nodes:

1. `resolve_active_ceo_seat`
2. `load_pending_replies`
3. `filter_invalid_replies`
4. `resolve_target_agents`
5. `build_inbox_work_items`
6. `persist_inbox_work_items`
7. `acknowledge_consumed_replies`
8. `archive_resolved_ceo_items`
9. `summarize_reply_ingestion`

---

## Recommended node responsibilities

| Node | Responsibility | Pure logic or side effect |
|---|---|---|
| `resolve_active_ceo_seat` | Pick the CEO fallback seat from env + `agents.yaml` + pause status | mostly logic |
| `load_pending_replies` | Read up to 25 unconsumed Drupal replies | side effect |
| `filter_invalid_replies` | Drop rows with missing target/body and record warnings | logic |
| `resolve_target_agents` | Validate target seats and reroute unknown targets to CEO | logic |
| `build_inbox_work_items` | Produce canonical item IDs and `command.md` payloads | logic |
| `persist_inbox_work_items` | Create `sessions/<agent>/inbox/<item>/...` | side effect |
| `acknowledge_consumed_replies` | Mark Drupal rows consumed and attach HQ item IDs | side effect |
| `archive_resolved_ceo_items` | Move referenced CEO inbox items to resolved artifacts | side effect |
| `summarize_reply_ingestion` | Emit structured telemetry/log summary back into tick state | logic |

---

## What should not remain hidden in adapters

These are policy decisions and should live in graph-visible logic:

- unknown target -> CEO fallback
- batch cap = 25
- reply with blank target/body -> skipped
- ROI assignment strategy
- archive decision based on `in_reply_to`
- handling of missing reply table

Adapters should only do I/O. Policy belongs in the graph layer.

---

## Migration phases

## Phase 1 — Extract Python parity module

Create a Python module that reproduces current behavior without changing
outcomes:

- CEO seat resolution
- target validation / reroute
- item ID generation
- command payload generation
- archive target selection

Result:

- the inline Python block disappears
- logic becomes unit-testable
- shell script becomes thinner

## Phase 2 — Replace shell node with Python node chain

Move `consume_replies` from:

- one shell command

to:

- multiple Python graph nodes

while still using adapter helpers for:

- Drupal reads/writes
- filesystem writes/moves

## Phase 3 — Structured telemetry

Extend tick state and telemetry with fields like:

- `reply_ingestion.pending_count`
- `reply_ingestion.created_count`
- `reply_ingestion.rerouted_count`
- `reply_ingestion.archived_count`
- `reply_ingestion.warning_count`
- `reply_ingestion.skipped_reasons[]`

This is the point where the dashboard becomes materially more useful.

## Phase 4 — Optional adapter cleanup

After parity is stable:

- replace Drush `php:eval` usage with a narrower data-access interface if useful
- decide whether reply ingestion should talk to Drupal via DB, command, or API

This is lower priority than making the logic graph-native.

---

## Recommended first implementation boundary

Do **not** start by rewriting the entire script.

Best first cut:

1. keep Drupal read/write adapter behavior unchanged
2. keep filesystem queue model unchanged
3. eliminate inline Python logic first
4. promote reply-routing decisions into Python functions/state
5. then split the node into subnodes

That minimizes behavioral drift while still moving the actual orchestration
toward LangGraph.

---

## Acceptance criteria for the migration

The `consume_replies` refactor is successful when:

1. LangGraph state captures reply-ingestion details, not just `rc`
2. target reroutes are explicit and counted
3. created inbox items are known to the graph before persistence
4. Drupal acknowledgment is a distinct node/step
5. archive behavior is explicit, isolated, and logged
6. existing reply semantics remain functionally equivalent
7. unit tests can cover routing/materialization logic without invoking Bash

---

## Key risks

| Risk | Why it matters | Mitigation |
|---|---|---|
| Behavior drift | HQ inbox item naming and routing are operationally important | preserve current item ID + payload contract first |
| Partial writes | files created but Drupal ack fails, or vice versa | keep acknowledgment as explicit post-persist node and log divergence |
| Hidden producer contract | reply table producer path is not clearly tracked in repo | document current assumptions and keep DB contract stable initially |
| Over-refactor | rewriting adapters and policy at once raises failure risk | separate graph decomposition from adapter redesign |

---

## Recommendation

Proceed with a **graph-first decomposition of `consume_replies`** and treat the
current Bash script as a legacy integration wrapper to be shrunk or retired.

The current implementation proves the workflow is valuable. It does **not**
prove that the current script boundary is the right long-term LangGraph shape.

The right next move is to make the workflow visible as stateful graph logic.
