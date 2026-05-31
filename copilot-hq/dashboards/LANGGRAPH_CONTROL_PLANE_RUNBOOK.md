# LangGraph Control Plane Runbook

Generated from current implementation of the Copilot Agent Tracker LangGraph pages.

## Scope
This runbook covers:
- `/admin/reports/copilot-agent-tracker/langgraph` and all LangGraph subpages
- Architecture plane boundaries (Control, Execution, Assurance)
- Operational management controls and release decision flow

---

## 1) Page and Subpage Map

### Root
- **LangGraph Control Plane**: `/admin/reports/copilot-agent-tracker/langgraph`

### Control Plane pages
- **Overview**: `/admin/reports/copilot-agent-tracker/langgraph`
- **Session Health**: `/admin/reports/copilot-agent-tracker/langgraph/session`
- **Parity Health**: `/admin/reports/copilot-agent-tracker/langgraph/parity`

### Execution Plane pages
- **Feature Flow**: `/admin/reports/copilot-agent-tracker/langgraph/feature-progress`
- **Release Control**: `/admin/reports/copilot-agent-tracker/langgraph/release-status`

### Assurance Plane pages
- **Release Evidence**: `/admin/reports/copilot-agent-tracker/langgraph/release-notes`
- **Release Triage**: `/admin/reports/copilot-agent-tracker/langgraph/release-troubleshooting`

Notes:
- Legacy `/admin/reports/copilot-agent-tracker` redirects to the LangGraph dashboard home.
- Page tabs and menu hierarchy are aligned to the routes above.

---

## 2) Architecture Boundaries

### Control Plane
Owns system-level health and control-state snapshot:
- Latest tick presence/freshness
- Runtime mode (`dry_run` vs `live`)
- Publishing enabled/disabled
- Parity pass/fail
- Release-cycle control enabled/disabled

Primary operator intent:
- Determine go/no-go for deeper execution decisions.

### Execution Plane
Owns workload and release progression:
- Feature ownership/progress context
- Release control posture and continuity signal

Primary operator intent:
- Prioritize/assign execution and decide if release flow can continue.

### Assurance Plane
Owns approval evidence and bottleneck diagnosis:
- Release notes/signoff narrative
- Seat-level blocked/needs-info/work-in-progress triage

Primary operator intent:
- Produce approval-grade confidence and clear release blockers.

---

## 3) Data and Signal Sources

### Runtime artifacts (file-backed)
- `inbox/responses/langgraph-ticks.jsonl`
  - Tick timeline
  - `dry_run`, `publish_enabled`, `agent_cap`, `provider`
  - `step_results` and per-step errors
- `inbox/responses/langgraph-parity-latest.json`
  - `parity_ok`
  - selected-agents parity + step-order parity
  - mismatch/error details
- `dashboards/LANGGRAPH_FEATURE_PROGRESS.md`
  - Feature/work-item planning table
- `tmp/release-cycle-active/*.release_id` and related state
  - Team release-cycle current/next identifiers

### Tracker storage (DB-backed)
- `copilot_agent_tracker_agents`
  - Seat status, current action, last seen, metadata (active/next inbox)
- `copilot_agent_tracker_events`
  - Append-only telemetry event stream

### Telemetry ingestion
- `POST /api/copilot-agent-tracker/event`
- Requires `X-Copilot-Agent-Tracker-Token`
- Rejects missing/invalid token and invalid payload
- Stores sanitized event + agent status upsert

---

## 4) Management Controls

### Org Automation Control (dashboard form)
- Reads current state from `scripts/org-control.sh status --json`
- Toggles via `scripts/org-control.sh enable|disable --by <user>`

When to use:
- Emergency stop/start for org-wide automation behavior.

### Release Management Cycle Control (dashboard form)
- Reads state from `scripts/release-management-cycle.sh status --json`
- Toggles via `scripts/release-management-cycle.sh start|stop --by <user> [--reason ...]`

When to use:
- Pause/resume release-cycle + coordinated-push automation while preserving other controls.

---

## 5) Operational Decision Flow

Recommended path:
1. **Overview**: confirm top-level health (tick/parity/publish/release control).
2. **Session Health**: verify cadence and absence of execution errors.
3. **Parity Health**: verify selected-agent and step-order parity.
4. **Feature Flow**: review current execution ownership and readiness context.
5. **Release Control**: verify publish + release control posture and 24h continuity signal.
6. **Release Evidence**: validate release narrative/signoff evidence.
7. **Release Triage** (if needed): clear blocked and needs-info seats.

---

## 6) Incident Routing Matrix

### A) Session stale or error-heavy
- Start: **Session Health**
- Confirm: error count and tick age
- Next: **Parity Health** (check correctness drift)
- Action: hold release approvals until clean ticks resume

### B) Parity failure
- Start: **Parity Health**
- Confirm: mismatch details in parity report
- Next: **Session Health** (runtime instability) and **Overview** (control posture)
- Action: pause release decisions until parity returns PASS

### C) Release blocked
- Start: **Release Control**
- Confirm: `publish_enabled` and release-cycle enabled state
- Next: **Release Triage** to identify seat-level blockers
- Action: clear blocked/needs-info ownership and re-check control state

### D) Evidence gap before approval
- Start: **Release Evidence**
- Confirm: notes/signoff context completeness
- Next: **Release Triage** if missing ownership evidence
- Action: collect missing signoff/testing context before final approval

---

## 7) Ownership Model (Practical)

- **Control Plane owner**: runtime operations / incident commander
- **Execution Plane owner**: PM + delivery seats coordinating work progression
- **Assurance Plane owner**: QA/release approver and seat triage coordinator

Each handoff should preserve a simple status statement:
- Current page signal
- Blocking condition (if any)
- Required next page + decision owner

---

## 8) Minimum Healthy Conditions (Release Go)

Treat release as ready only when all are true:
- Latest tick present and recent
- Session page shows no current execution error condition
- Parity page shows PASS
- `publish_enabled=true`
- release-cycle control is enabled
- Release evidence is complete for planned approval scope
- No unresolved blocker/needs-info seats for release-critical work
