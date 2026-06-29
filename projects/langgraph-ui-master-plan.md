# Drupal LangGraph UI master plan

Problem:
The console now has a usable shell, flow registry, flow context, and operator
dashboard, but it is not yet a complete LangGraph management UI. We need one
master plan that defines what LangGraph management expects, how that maps to
our Drupal UI, what is already supported, and what is still missing before the
UI can be called complete.

Approach:
1. Treat the LangGraph UI as a control plane for three things: graph
   definition, graph execution, and graph observation/governance.
2. Use the ideal IA as the target frame:
   global `Overview`, `Flows`, `Admin`; then per-flow `Overview`, `Build`,
   `Test`, `Run`, `Observe`, `Release`.
3. Track every LangGraph command and data structure against a concrete Drupal
   route, control, and support level: `Supported`, `Partial`, or `Missing`.
4. Use this file as the master completion contract for the UI. The UI is not
   done until every required capability below is either implemented or
   intentionally deferred.

## Ideal nested workspace contract

| Layer | UI section | Route pattern | Purpose |
| --- | --- | --- | --- |
| Global | Overview | `/admin/reports/drupal-langgraph/langgraph-console` | Operator summary and entry into LangGraph management |
| Global | Flows | `/admin/reports/drupal-langgraph/langgraph-console/flows` | Registry of all flows and entry into flow workspaces |
| Global | Admin | `/admin/reports/drupal-langgraph/langgraph-console/admin` | Runtime roots, artifact health, and control contracts |
| Flow workspace | Overview | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}` | Flow summary and workspace index |
| Flow workspace | Build | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/build` | Graph authoring, structure, and policy |
| Flow workspace | Test | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/test` | Validation, parity, and checkpoint replay |
| Flow workspace | Run | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/run` | Manual execution and execution history |
| Flow workspace | Observe | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/observe` | Traces, metrics, drift, alerts, and progress |
| Flow workspace | Release | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/release` | Versions, promotion, evidence, and troubleshooting |

## Current route contract

| UI section | Route | Current purpose |
| --- | --- | --- |
| Overview | `/admin/reports/drupal-langgraph/langgraph-console` | Operator dashboard and health summary |
| Flows | `/admin/reports/drupal-langgraph/langgraph-console/flows` | Registry of built-in and custom flows |
| New Process Flow | `/admin/reports/drupal-langgraph/langgraph-console/flows/add` | Create draft flow definition |
| Flow Detail / Workspace Overview | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}` | Per-flow summary and workspace index |
| Flow Build | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/build` | Flow-scoped Build workspace with stubbed controls |
| Flow Test | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/test` | Flow-scoped Test workspace with stubbed controls |
| Flow Run | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/run` | Flow-scoped Run workspace with stubbed controls |
| Flow Observe | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/observe` | Flow-scoped Observe workspace with nested controls |
| Flow Release | `/admin/reports/drupal-langgraph/langgraph-console/flows/{flow_id}/release` | Flow-scoped Release workspace with nested controls |
| Admin | `/admin/reports/drupal-langgraph/langgraph-console/admin` | Runtime roots and artifact contract |
| Compatibility Build/Test/Run/Observe/Release | `/admin/reports/drupal-langgraph/langgraph-console/{section}` | Flow-selection landing or redirect into the selected flow workspace |

## LangGraph command model -> UI mapping

| LangGraph management command | User intent | Expected UI surface | Current Drupal control | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| Create flow | Register a new graph/process flow | Flows | `New Process Flow` form | Supported | Draft flow creation works |
| Open flow control panel | Enter a specific flow workspace | Flows | Flow row link / flow detail page | Supported | Also sets selected flow context |
| Select active flow context | Scope lifecycle work to one flow | Flow Detail + nested flow tabs | Auto-selected via flow detail + user.data | Supported | Flow-scoped workspace is now the preferred path |
| Edit flow metadata | Change label, owner, status, entrypoint, version | Build | Nested `Flow Metadata` editor | Supported | Flow edits now live inside the flow workspace |
| Archive flow | Retire a flow without deleting it | Build | Nested `Flow Metadata` editor via `status=archived` | Supported | Archive is currently handled through status changes |
| Define state schema | Describe state model carried across nodes | Build | Nested `State Schema` editor | Supported | Editable inside the flow workspace |
| Add node | Define graph node set | Build | Nested `Nodes` editor | Supported | Line-based editor for now |
| Edit node | Change node definitions | Build | Nested `Nodes` editor | Supported | Rich per-node authoring still future work |
| Connect routing / edges | Define graph transitions and conditionals | Build | Nested `Routing` editor | Supported | Rule-based editor for now |
| Bind tools | Define tools/resources available to the graph | Build | Nested `Tools` editor | Supported | Checkbox-based binding editor |
| Configure prompts / policy | Manage orchestration notes, prompt guardrails, policy | Build | Nested `Prompts & Policy` editor | Supported | Text-based editor for now |
| Validate structure | Check graph completeness and shape | Test | Nested `Validate Structure` report | Supported | Validates saved flow contract against structural and runtime expectations |
| Validate parity | Compare runtime against expected structure | Test | Current parity evidence page | Supported | Runtime parity evidence is already surfaced |
| Replay checkpoint | Re-run from checkpoint / prior state | Test / Run | Nested `Replay Checkpoints` form + inventory + replay requests | Partial | Replay worker now validates checkpoint artifacts and dispatches an artifact-referenced dry-run rerun for `hq_orchestrator_tick`; native state restore is still not available |
| Run now | Trigger manual execution | Run | Nested `Run Now` request form | Partial | Fully executed for `hq_orchestrator_tick`; other flows remain request-only |
| Pause run | Halt flow execution | Run | Nested `Pause / Resume` request form | Partial | Fully executed for `hq_orchestrator_tick` via org control; other flows remain request-only |
| Resume run | Resume paused execution | Run | Nested `Pause / Resume` request form | Partial | Fully executed for `hq_orchestrator_tick` via org control; other flows remain request-only |
| Inspect recent runs | Review recent execution activity | Run | Recent tick timeline | Supported | Present as execution-plane evidence |
| Inspect traces | Inspect node/step traces | Observe | Node Traces subsection | Supported | Artifact-backed |
| Inspect metrics | Review cadence/worker/anomaly metrics | Observe | Runtime Metrics subsection | Supported | Artifact-backed |
| Inspect drift | Review deviations from baseline | Observe | Drift subsection | Supported | Artifact-backed |
| Inspect alerts/incidents | Review failures and blockers | Observe | Alerts & Incidents subsection | Supported | Artifact-backed |
| Inspect flow-scoped feature progress | View LangGraph work status by flow | Observe | Feature Progress subsection | Supported | Explicitly flow-scoped |
| Create version | Save/reify releaseable graph version | Release | Nested `Versions` snapshot form | Supported | Writes version snapshot artifacts for the selected flow |
| Promote version | Promote a version toward release | Release | Nested `Promote` request form + promotion state | Partial | Promotion worker now validates snapshots and records the current promoted version; richer multi-stage release semantics are still pending |
| Review release evidence | Inspect release readiness and signoffs | Release | Release Evidence subsection | Supported | Artifact-backed |
| Troubleshoot release blockers | Work blocker queue and inbox pressure | Release | Release Troubleshooting subsection | Supported | Artifact-backed |
| Inspect runtime roots | Validate filesystem/runtime contract | Admin | Runtime Roots subsection | Supported | Present |
| Inspect artifact health | Validate required artifact files | Admin | Artifact Health table | Supported | Present |

## LangGraph data structure model -> UI mapping

| Data structure | Purpose | Current storage/source | Current UI surface | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| Flow registry entry | Top-level graph/process record | Drupal config `drupal_langgraph.process_flows` + built-ins | Flows registry, flow detail | Supported | Core registry is working |
| Flow ID | Stable machine identifier | Config + route param | Flows, flow detail | Supported | Used for selection/context |
| Flow label + description | Human-readable identity | Config | Flows, flow detail | Supported | Create-only for now |
| Flow status | Draft/active/paused/archived lifecycle state | Config | Flows, flow detail, Build metadata editor | Supported | Editable from nested flow workspace |
| Owner | Responsible module/team/system | Config | Flows, flow detail, Build metadata editor | Supported | Editable from nested flow workspace |
| Graph type | State/subgraph/supervisor/router type | Config | Flows, flow detail, Build metadata editor | Supported | Editable from nested flow workspace |
| Default entrypoint | Main node/entry command | Config | Flows, flow detail, Build metadata editor | Supported | Editable from nested flow workspace |
| Primary section | Best-fit lifecycle section | Config | Flows, flow detail, current flow context | Supported | Used as descriptive metadata |
| Version | Flow version marker | Config | Flows, flow detail, Build metadata editor | Supported | Editable marker, not yet a full version history system |
| State schema summary | Description of carried state | Config | Flow detail, Build state-schema editor | Supported | Now editable in the workspace |
| Nodes | Graph node set | Config array | Flow detail, Build nodes editor | Supported | Now editable in the workspace |
| Routing rules | Graph edges/conditions | Config array | Flow detail, Build routing editor | Supported | Now editable in the workspace |
| Tools | Graph tool bindings/resources | Config array | Flow detail, Build tools editor | Supported | Now editable in the workspace |
| Prompt notes | Prompt/policy/orchestration notes | Config | Flow detail, Build prompts editor | Supported | Now editable in the workspace |
| Selected flow context | Current user-scoped flow | Drupal `user.data` | Current flow panel across sections | Supported | Working |
| Tick stream | Execution timeline + step results | JSONL artifacts | Overview, Run, Observe | Supported | Artifact-backed |
| Parity report | Runtime validation evidence | JSON artifact | Test | Supported | Working |
| Metrics | Runtime aggregate signals | Observe artifacts/services | Observe Metrics | Supported | Working |
| Drift signals | Behavioral drift evidence | Observe artifacts/services | Observe Drift | Supported | Working |
| Incidents/alerts | Error/blocker/anomaly summaries | Observe artifacts/services | Observe Alerts | Supported | Working |
| Feature progress snapshot | LangGraph-owned work progress | Feature progress artifact | Observe Feature Progress | Supported | Working |
| Org/release controls | Runtime enable/disable controls | Control artifacts | Overview, Admin | Supported | Read-only at present |
| Checkpoints | Resume/replay state markers | Runtime logs and checkpoint scripts | Test replay candidate inventory + replay requests | Partial | Replay requests are now consumed for `hq_orchestrator_tick`, but only as artifact-referenced dry-run reruns; native checkpoint restore is still pending |
| Execution commands | Run/pause/resume actions | Private runtime request artifacts | Run request surfaces, Observe control-request visibility | Partial | Shared artifact model exists and `hq_orchestrator_tick` now has a real executor; other flows are still request-only |
| Version history | Version list, provenance, promotion state | Private version snapshots, promotion request artifacts, and promoted-version state | Release workspace, Observe control-request visibility | Partial | Current promoted version is now tracked; richer provenance/history semantics are still pending |

## Section-by-section definition of done

### Overview
- Done when it surfaces operator summary, active issues, next action, and
  current flow without forcing users into raw artifacts first.
- Current state: mostly complete for the operator dashboard.

### Flows
- Done when users can create, open, edit, archive, and version flows from the
  registry and detail surfaces.
- Current state: create/open and edit/archive via the nested Build workspace are done; version history is not.

### Build
- Done when users can author and update the graph contract:
  state schema, nodes, routing, tools, and prompt policy.
- Current state: nested Build workspace exists with live editors; richer graph authoring and validation are still incomplete.

### Test
- Done when users can validate graph shape, parity, and replay/checkpoint paths.
- Current state: nested Test workspace exists with structure validation and parity evidence; replay is still partial.

### Run
- Done when users can manually trigger, pause, resume, and inspect executions.
- Current state: nested Run workspace exists with execution history and request surfaces; execution backend is still not wired.

### Observe
- Done when traces, metrics, drift, alerts, and flow-scoped progress are easy
  to inspect from one coherent flow-aware workspace.
- Current state: nested Observe workspace exists and now includes control-request visibility alongside the main read-only ops surfaces.

### Release
- Done when users can inspect evidence, create versions, and promote versions
  with explicit readiness signals and blockers.
- Current state: nested Release workspace exists with version snapshots, promotion requests, evidence, and troubleshooting; promotion is still partial.

### Admin
- Done when runtime roots, artifact health, and control files are visible and
  any writable controls needed by the UI can be managed safely.
- Current state: read-only inspection exists, including the private Drupal LangGraph artifact roots used by runtime and release controls.

## Completion checklist

- [x] Add top-level console IA and flow registry
- [x] Add flow creation and flow selection context
- [x] Add flow detail with structural summary
- [x] Add operator-focused overview dashboard
- [x] Collapse legacy routes to canonical console routes
- [x] Add nested flow workspace tabs and stub control pages
- [x] Add flow edit/archive actions
- [x] Add Build editors for schema, nodes, routing, tools, and prompt policy
- [x] Add Test actions for structural validation and checkpoint replay
- [x] Add Run actions for manual execution and pause/resume
- [x] Add Release actions for version creation and promotion
- [x] Add explicit writable control surfaces where runtime actions require them
- [x] Audit every command/data structure in this document against the live UI

## Next-phase implementation roadmap

The UI shell and operator-facing information architecture are now in place. The
next phase is about turning the remaining `Partial` surfaces into a real control
plane with backend execution, status tracking, and provenance.

### Workstream 1: control-plane artifact model

Goal:
Create one normalized model for runtime requests, checkpoint replay requests,
version snapshots, and promotion requests so the UI is not just writing loose
JSON files.

Deliverables:
1. Define a shared artifact contract for request IDs, timestamps, actor, flow,
   requested action, current status, status message, and related artifacts.
2. Add shared storage/helpers so Run, Test, Release, Observe, and Admin all read
   the same structured records.
3. Add status values that match actual lifecycle state: `requested`,
   `accepted`, `running`, `completed`, `failed`, `cancelled`.
4. Surface those states consistently in Run, Observe, Release, and Admin.

Definition of done:
Every request-backed control renders from the same data model, and operators can
see request status without opening raw artifact files.

Current status:
- Implemented.
- A shared Drupal service now owns runtime request, replay request, version
  snapshot, and promotion request directories plus normalized record reading.
- Run, Release, Observe, Test, and Admin now render request-backed artifacts
  through that shared model.
- New artifacts include a standard contract with `schema_version`,
  `artifact_type`, `request_id`, `status`, `status_message`, flow identity, and
  actor/timestamp metadata.

### Workstream 2: checkpoint replay and resume

Goal:
Turn checkpoint inventory into an actual replay/resume workflow.

UI work:
1. Expand `Test > Replay Checkpoints` from inventory-only to a selectable replay
   form.
2. Show checkpoint metadata: source flow, timestamp, source artifact, replay
   eligibility, and last replay outcome.
3. Add per-flow replay history in Observe or Run so operators can inspect what
   was resumed and when.

Backend work:
1. Define how a replay request points to a specific checkpoint artifact from the
   existing auto-checkpoint inventory (`inbox/responses/auto-checkpoint*.log`,
   checkpoint loop state, and related scripts).
2. Add a replay executor/consumer that validates checkpoint existence and
   compatibility before handing replay work to the relevant runtime entrypoint.
3. Reuse the shared control-plane artifact model so replay requests and replay
   outcomes publish `requested/accepted/running/completed/failed/cancelled`
   status transitions instead of bespoke result files.
4. Record replay outputs so they feed Run history and Observe incident surfaces.

Definition of done:
An operator can choose a checkpoint, request replay/resume, and later see a
clear success/failure result in the console.

Concrete integration target:
- First implementation should target the built-in `hq_orchestrator_tick` flow,
  because it already has observable checkpoint artifacts and a real LangGraph
  runtime entrypoint via `orchestrator/run.py --once`.

Current status:
- Replay candidate inventory is now separated from checkpoint support artifacts.
- The Test workspace now includes a replay request form backed by the shared
  control-plane artifact model.
- Replay request history is visible per flow.
- A replay worker now validates checkpoint artifacts and dispatches a dry-run
  tick for `hq_orchestrator_tick`, with explicit status text stating that this
  is artifact-referenced rerun behavior rather than native checkpoint restore.

### Workstream 3: runtime execution backend

Goal:
Turn `Run Now` and `Pause / Resume` into real actions rather than request
capture only.

UI work:
1. Keep the existing request forms, but add request status, latest executor
   outcome, and links to resulting run evidence.
2. Show active runtime state for the flow: idle, queued, running, paused,
   failed, completed.
3. Add clear operator feedback when a newer request supersedes an older one.

Backend work:
1. Implement a consumer that reads runtime control requests and applies them to
   the relevant LangGraph execution environment.
2. For the built-in `hq_orchestrator_tick` flow, map UI actions to the real HQ
   runtime hooks:
   - `Run Now` -> `orchestrator/run.py --once`
   - runtime status -> `scripts/orchestrator-loop.sh status`
   - `Pause` / `Resume` -> `scripts/hq-automation.sh stop|start` plus org
     control/reason visibility from `scripts/org-control.sh`
3. Add locking/idempotency rules so duplicate clicks do not trigger conflicting
   runs.
4. Write executor results back to the shared control-plane artifact model.
5. Keep the executor adapter-based so other flows can plug in their own
   LangGraph-native entrypoints later without changing the Drupal UI contract.

Definition of done:
Submitting a runtime request causes real execution state changes and those
results are visible in Run and Observe.

Concrete integration target:
- Phase 1 should fully support `hq_orchestrator_tick`.
- Other flows may remain request-only until they expose equivalent runtime
  entrypoints.

Current status:
- Phase 1 implemented for `hq_orchestrator_tick`.
- The Drupal UI now shows runtime request status, executor outcome, and current
  runtime state for that flow.
- A runtime worker script now consumes `hq_orchestrator_tick` requests and maps
  them to the real HQ hooks:
  - `Run Now` -> `orchestrator/run.py --once`
  - `Pause` / `Resume` -> `scripts/org-control.sh disable|enable`
  - runtime state -> `scripts/orchestrator-loop.sh status`
- The worker is now attached to `scripts/hq-automation-watchdog.sh`, so runtime
  requests are processed by the existing minute-level automation path instead of
  requiring manual execution.
- Other flows still need their own runtime adapters before this workstream can
  be considered complete across the entire flow registry.

### Workstream 4: version history and promotion workflow

Goal:
Turn version snapshots and promotion requests into a coherent release state
model.

UI work:
1. Expand `Release > Versions` from flat snapshots to a browsable per-flow
   version history with provenance and summary details.
2. Expand `Release > Promote` to show current request status, target release
   lane/environment, and latest promotion outcome.
3. Show which version is current, candidate, promoted, superseded, or failed.

Backend work:
1. Add a version/promotion state model that ties snapshots and promotion
   requests together.
2. Implement a promotion consumer/executor that validates requested versions and
   records promotion outcomes.
3. First concrete integration should target the existing release-cycle and
   release promotion tooling already present in the repo (`release-cycle-control`
   state, release-cycle active markers, and post-push/release promotion scripts)
   instead of inventing a parallel release engine.
4. Link promotion results to release evidence and troubleshooting surfaces.

Definition of done:
Operators can follow a flow version from snapshot through promotion outcome
without leaving the LangGraph console.

Current status:
- Promotion requests are now consumed by a watchdog-driven promotion worker.
- Promotion validates that the requested version snapshot exists and that
  release-cycle control is enabled before recording the current promoted version.
- The Release workspace now shows current promotion state alongside version
  snapshots and promotion request history.
- Richer multi-stage promotion semantics (candidate vs shipped vs superseded
  timelines) are still future work.

### Workstream 5: flow lifecycle and authoring maturity

Goal:
Close the biggest remaining non-runtime gaps in flow management itself.

Current status:
Authoring maturity is now complete for the current Drupal LangGraph scope. Flow
detail and Build both surface version snapshot history plus current promotion
state, Build/Test validation now catches duplicate nodes, duplicate routing
rules, unknown tool bindings, and entrypoints that do not match configured
nodes, and the UI now explicitly keeps Build as the single edit surface for the
flow contract while leaving version capture and promotion in Release.

Scope:
1. Add richer flow version browsing from the flow detail/build side, not just
   release-side snapshots.
2. Improve Build authoring beyond text/line-based editing where it materially
   improves correctness: node structure, routing rules, and tool bindings.
3. Add stronger validation between Build and Test so invalid authoring surfaces
   are caught before runtime actions are requested.
4. Decide whether Flows needs explicit archive/version actions outside Build or
   whether nested Build remains the single edit surface.

Definition of done:
Flow authors can safely evolve a flow in the workspace without relying on raw
config literacy.

Concrete design guardrail:
- Flow authoring improvements must stay aligned to LangGraph-native concepts
  (state, nodes, routing, tools, entrypoints) and should not introduce a second
  competing graph model inside Drupal.

### Workstream 6: admin and governance completion

Goal:
Finish the control-plane governance story now that the UI has real writable
surfaces.

Current status:
Admin governance is now in place for the current control-plane artifact model.
The Admin workspace surfaces per-root request volume, requested/running,
failed, stale, and orphaned counts, plus writable control contracts and
retention/cleanup guidance for runtime requests, replay requests, version
snapshots, promotion requests, and promoted-version state.

Scope:
1. Add admin visibility for request volumes, stale requests, failed requests,
   and orphaned artifacts.
2. Surface writable control contracts and retention rules for private artifacts.
3. Decide whether org/release controls remain read-only in Drupal or gain
   explicit operator actions.
4. Add cleanup/retention views for old runtime and release artifacts.

Definition of done:
Admins can understand control-plane health and artifact hygiene without shell
access.

## Proposed execution order

1. Workstream 1 first, because replay, runtime execution, and promotion all need
   the same status/provenance model.
2. Workstream 3 next, because Run has the clearest operator value and already
   has request capture in place.
3. Workstream 4 after that, because Release depends on the same artifact/status
   foundation.
4. Workstream 2 in parallel with or immediately after 3/4, depending on whether
   replay can reuse the same executor framework.
5. Workstream 5 after backend control flow is stable.
6. Workstream 6 throughout, but with final hardening after the main consumers
   exist.

## Tracking notes

- The preferred architecture is now explicitly reflected in the UI:
  `Overview`, `Flows`, and `Admin` are global, while `Build`, `Test`, `Run`,
  `Observe`, and `Release` live under the selected flow workspace.
- Compatibility routes for global lifecycle sections still exist so older entry
  points can redirect into the selected flow or prompt the user to choose one.
- The current UI is now fully mapped and audited. Remaining gaps are not
  unknowns; they are specific implementation workstreams captured above.
