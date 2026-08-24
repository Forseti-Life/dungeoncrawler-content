# Tab Runtime Consistency Implementation Plan

**Initiative**: `tab-runtime-consistency`  
**Depends on**: `TAB_RUNTIME_CONSISTENCY_ARCHITECTURE.md`  
**Policy**: no backward compatibility, no fallback authority, no silent failures

---

## Objective

Collapse the V2 shell onto one runtime authority model so:

1. all tabs render the same committed server state,
2. room chat and system log are synchronized projections of one event stream,
3. action legality cannot drift from turn/encounter truth,
4. state read failures become explicit degraded modes rather than hidden stale renders.

---

## Phase 1 — Freeze current-state contracts and failure inventory

**Owner**: Architecture + Dev

## Deliverables

- code-anchored current-state surface inventory:
  - coordinator read/write lane
  - `/api/combat/state` read lane
  - panel-local caches
  - room chat/session-log refresh paths
- failure inventory from live evidence:
  - `422` retry path
  - `500` state/combat read path
  - divergent tab render sequences
- explicit ban list:
  - no panel-direct authoritative fetches
  - no stale-state silent render
  - no alternate authority by tab

## Exit gate

- every current authority surface and duplicate authority surface is named
- every target-state banned behavior is frozen in writing

---

## Phase 2 — Introduce the single client authority store

**Owner**: Dev

## Deliverables

- new `RuntimeStateStore` in coordinator layer
- one commit/apply API for authoritative snapshots
- explicit `sync_health` state machine:
  - `healthy`
  - `resyncing`
  - `degraded`
  - `read_only_desynced`
- snapshot application guards:
  - monotonic `state_version`
  - monotonic `event_cursor`
  - required `snapshot_id`

## Required code movement

- move authoritative snapshot ownership out of panel-local caches
- remove `GameShell` / `CharacterPanel` / `EncounterSystem` patterns that treat `/api/combat/state` as canonical

## Exit gate

- one browser authority store exists
- coordinator is the only layer allowed to commit authoritative runtime snapshots

---

## Phase 3 — Remove duplicate authoritative read lanes

**Owner**: Dev

## Deliverables

- remove active shell dependence on `/api/combat/state`
- remove panel-direct authoritative combat refreshes
- route all runtime refresh through:
  - `GET /api/game/{campaign_id}/state`
  - `GET /api/game/{campaign_id}/events`

## Hard rules

- `/api/combat/state` becomes non-runtime diagnostic/admin only
- no panel may call an alternate authority endpoint for gameplay truth

## Exit gate

- runtime shell has one authoritative read path only

---

## Phase 4 — Unify room chat and system log on one event stream

**Owner**: Dev

## Deliverables

- canonical event stream contract with one monotonic cursor
- event classes for:
  - turn start
  - action resolved
  - damage applied
  - condition applied
  - public narration
  - player/NPC/GM chat
- room chat projection builder
- system log projection builder

## Required behavior

- room chat and system log share ordering source
- pending optimistic lines are visibly pending until confirmed
- confirmed lines are attached to authoritative event IDs

## Exit gate

- room chat and system log can be proven to render the same action sequence from one cursor space

---

## Phase 5 — Rebuild action rail as a pure projection

**Owner**: Dev

## Deliverables

- action rail projection from:
  - `snapshot_id`
  - `turn`
  - `available_actions`
  - `action_contract`
  - selected actor/target UI state
- explicit desync/degraded banner states
- no hidden grey-out on stale state

## Required behavior

- if authority state is stale or missing, rail blocks with explicit message
- if selected actor mismatches current turn, rail shows authoritative mismatch state
- if snapshot commit fails validation, rail does not render actionable controls

## Exit gate

- action rail is derived only from authoritative store + local selection UI state

---

## Phase 6 — Rebuild panel projections

**Owner**: Dev

## Panels in scope

- map/combat presentation
- character panel
- quest panel
- room view status surfaces
- room chat
- system log

## Deliverables

- projection builders per panel
- per-panel render contract tests
- per-panel telemetry with `snapshot_id`

## Exit gate

- every runtime panel can report the exact committed `snapshot_id` it rendered

---

## Phase 7 — Server payload hardening

**Owner**: Dev

## Deliverables

- guarantee authoritative payload includes:
  - `snapshot_id`
  - `state_version`
  - `event_cursor`
  - `phase`
  - `encounter_id`
  - `active_room_id`
  - `turn`
  - `available_actions`
  - `action_contract`
- remove ambiguous/multi-authority payload shapes
- hard-fail invalid or partial authoritative payloads

## Exit gate

- authoritative client payload shape is deterministic and strict

---

## Phase 8 — Failure policy implementation

**Owner**: Dev + QA

## Deliverables

- deterministic resync transaction for version mismatch:
  1. receive `422` / version mismatch
  2. read authoritative state once
  3. retry once if action remains legal
- degraded/read-only shell mode on repeated read failures
- visible sync-health indicator

## Forbidden behavior

- no stale silent success
- no implicit authority swap
- no hidden retry loop

## Exit gate

- all key failure modes resolve to either:
  - converged authoritative state
  - or explicit blocked/degraded state

---

## Phase 9 — Observability and drift detection

**Owner**: Dev + QA

## Deliverables

- telemetry emitted on:
  - snapshot commit
  - panel render
  - resync
  - degraded-mode entry
- drift dashboards / logs for:
  - panel snapshot mismatch
  - chat/system-log cursor mismatch
  - action-contract age
  - repeated state read failures

## Exit gate

- drift can be measured directly instead of inferred from user reports

---

## Phase 10 — Verification matrix

**Owner**: QA

## Scenario matrix

1. cast spell success with chat + system-log convergence
2. strike success with HP/condition convergence
3. end turn with action rail refresh
4. tab switching during normal play
5. tab switching during in-flight action
6. injected `422` version mismatch
7. injected `/state` `500`
8. injected `/events` `500`
9. repeated failures causing read-only desync mode

## Required assertions

- all visible runtime tabs share the same `snapshot_id`
- room chat and system-log share coherent event order
- action bar never renders from stale authority without degraded banner
- no panel shows a newer or older encounter truth than another panel

## Exit gate

- QA can prove convergence or explicit degraded mode in every fault scenario

---

## Recommended execution order

1. freeze architecture + ban list
2. add `RuntimeStateStore`
3. remove duplicate authoritative read lanes
4. harden authoritative payload contract
5. rebuild action rail
6. rebuild chat/system-log event projection
7. migrate remaining panels
8. add fault-policy handling
9. run convergence matrix

---

## Required code review questions

Every implementation PR in this initiative must answer:

1. Did this change introduce any second authoritative client store?
2. Did this change add any panel-direct gameplay truth fetch?
3. Can the panel render from one committed `snapshot_id` only?
4. Does failure become explicit instead of falling back?
5. Does room chat/system-log ordering still come from one event cursor?

If any answer is “no” or “unclear,” the change is not acceptable.

---

## Success definition

The initiative is complete when:

- the shell has one client authority store,
- the coordinator has one authoritative read/write protocol,
- all tabs render the same committed snapshot,
- room chat and system log are projections of one event stream,
- no panel can silently drift,
- failure is explicit, visible, and measurable.
