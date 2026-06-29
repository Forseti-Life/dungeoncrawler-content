# LangGraph UI — Release Roadmap

- Product: forseti.life
- Module: copilot_agent_tracker
- Feature project: forseti-langgraph-ui
- Author: architect-copilot
- Last updated: 2026-04-19
- Status: active

> See also: `ROADMAP.md` (org-level) for cross-product release context.

---

## Release Cycle Overview

| Release | Status | Theme | Scope |
|---|---|---|---|
| 20260405-forseti-release-c | ✅ Shipped | LangGraph Telemetry Foundation | Telemetry schema fix, dashboard path fix, feature progress auto-refresh |
| 20260405-forseti-release-c (Phase 1b) | ✅ Shipped | Console Stubs Scaffold | 7-section console route structure + LangGraphConsoleStubController |
| 20260408-forseti-release-d (Phase 1c) | ✅ Shipped | LangGraph UI Context Enrichment | Workflow Registry, context banners, key terms glossaries on all 15 pages; Live/Stub status per console subsection |
| 20260408-forseti-release-d | ✅ Shipped | Agent Tracker Core + Schema | copilot_agent_tracker DB + telemetry API + admin dashboard UI |
| 20260411-coordinated-release | ✅ Shipped | Console Build + Test Sections | State schema visualization, node topology, test/eval scorecards (ahead of schedule) |
| 20260411-forseti-release-b | ✅ Shipped | Release Control Panel (read-only) | Release panel wired to release state files and PM signoff data |
| 20260412-forseti-release-e | ✅ Shipped | Console Wiring — Run + Session | Run/Session panels wired to live orchestrator tick data |
| forseti-release-r | 🗓️ Planned | Console Wiring — Observe + Feature Progress | Node traces, metrics, drift detection wired to tick stream |
| forseti-release-s | 🗓️ Planned | Console Wiring — Build + Test Sections | State schema + node topology parsed from engine.py; eval scorecard framework |
| forseti-release-t | 🗓️ Planned ⚠️ Board gate | Release Control Panel (mutations) | Graph version management, promotion flow, canary controls (strong auth required) |

---

## Shipped

### forseti-release-c — LangGraph Telemetry Foundation
**Theme:** Restore CEO dashboard visibility; fix telemetry schema mismatch

**Delivered:**
| Item | Commit | Notes |
|---|---|---|
| `DashboardController.php` — `langgraphPath()` helper | `62b95688` | Replaced all 10 hardcoded path constant usages with `COPILOT_HQ_ROOT`-aware helper |
| `engine.py` telemetry schema fix | `7fadeb4a` | Now writes `step_results{}`, `parity_ok`, `provider`, `dry_run` — matches dashboard expectations |
| `generate-feature-progress.py` auto-refresh | `3c134210` | Regenerates `FEATURE_PROGRESS.md` from all 48 feature files on every orchestrator tick |
| `engine_mode` detection fix | `3c134210` | DashboardController reads from tick data (`step_results`/`dry_run`) not log-substring |

**QA verdict:** APPROVE (2026-04-06) — 0 violations, engine_mode = `langgraph`, provider = `ShellProvider`, feature-progress live

---

### forseti-release-c — Console Stubs Phase 1
**Theme:** Scaffold the full LangGraph management console with clean architecture primitives

**Delivered:**
| Item | Notes |
|---|---|
| `LangGraphConsoleStubController.php` | New controller with `sectionMap()` defining 7 sections × 4-5 subsections |
| 7 console routes wired | `/langgraph-console`, `/build`, `/test`, `/run`, `/observe`, `/release`, `/config` |
| Section definitions | home (graph contract, runtime objects, durability model, control gates); build (state schema, nodes/routing, subgraphs, tool calling, prompts); test (path scenarios, checkpoint replay, eval scorecards, safety gates); run (threads/runs, stream events, resume/retry, concurrency); observe (node traces, runtime metrics, drift/anomalies, alerts); release (graph versions, promotion flow, canary controls); config |

**QA verdict:** APPROVE (2026-04-06) — all console routes return 403 for anonymous (correct), no PHP errors

---

### forseti-release-d (Phase 1c) — LangGraph UI Context Enrichment
**Theme:** Eliminate all ambiguity on LangGraph management pages; add Workflow Registry and Live/Stub indicators

**Delivered:**
| Item | Commits | Notes |
|---|---|---|
| Workflow Registry on Dashboard home | `848a38621` | Groups workflows by Scope (System/Site) with status and console links |
| `renderLangGraphContextBanner()` + `renderLangGraphKeyTerms()` helpers | committed | DashboardController — blue info block + collapsible `<details>` glossary panel |
| `renderConsoleContext()` + `renderKeyTerms()` helpers | committed | LangGraphConsoleStubController — same pattern |
| Context banners + key terms: all 8 Dashboard LangGraph pages | `a630b6171` | Dashboard home, Workflow Mgmt, Session, Feature Flow, Parity, Release Status, Release Evidence, Release Triage |
| Context banners + key terms: all 7 Console pages | committed | home, run, observe, build, test, release, admin |
| Live/Stub status per subsection in section nav tables | `5ead323e8` | `buildSectionRows()` checks 20 wired keys; green 🟢 Live or grey ⬜ Stub |

---

## ✅ Shipped (continued)

### forseti-release-d — Agent Tracker Core (`forseti-copilot-agent-tracker`)
**Status:** ✅ Shipped (release 20260412-forseti-release-d)
**Priority:** P1
**Theme:** Deliver the actual agent tracking DB and telemetry API that underpins the dashboard

| Item | Status | Notes |
|---|---|---|
| Telemetry POST endpoint `/api/copilot-agent-tracker/event` | shipped | Token-auth, input validation, CSRF-exempt API route |
| `copilot_agent_tracker_agents` DB table | shipped | Upsert on POST; agent_id, status, current_action, last_seen |
| `copilot_agent_tracker_events` DB table | shipped | Append-only event log; PII-free; no raw chat content |
| Admin agent list view | shipped | AC-HP-01: list with id, last status, last action, last-seen |
| Admin agent detail view | shipped | AC-HP-02: sanitized event stream |
| Permission: `administer copilot agent tracker` | shipped | All admin routes require this; anonymous → 302 to login |
| CSRF guards on state-changing endpoints | shipped | POST endpoints require token or token-auth header |

**Feature file:** `features/forseti-copilot-agent-tracker/feature.md` — Status: shipped

---

### forseti-langgraph-console-build-sections + forseti-langgraph-console-test-sections
**Status:** shipped (20260411-coordinated-release — ahead of roadmap schedule)
**Feature files:** `features/forseti-langgraph-console-build-sections/feature.md`, `features/forseti-langgraph-console-test-sections/feature.md`

---

### forseti-langgraph-console-release-panel
**Status:** shipped (20260411-forseti-release-b — read-only release observability panel)
**Feature file:** `features/forseti-langgraph-console-release-panel/feature.md`

---

## 🚀 In-Flight / 🗓️ Planned

### ✅ Shipped — forseti-release-e — Console Wiring: Run + Session Panels
**Status:** ✅ Shipped (release `20260412-forseti-release-e`, completed 2026-04-19)
**Theme:** Replace stub placeholders in Run and Session sections with live data from the orchestrator tick stream

**Feature:** `features/forseti-langgraph-console-run-session/feature.md`

**Scope:**
| Console Section | Subsection | Live Data Source |
|---|---|---|
| Run | Threads & Runs | `langgraph-ticks.jsonl` — active run IDs, thread IDs, status |
| Run | Stream Events | tick `step_results` — streaming event timeline |
| Run | Resume & Retry | Orchestrator state: `needs-info`/`blocked` agent items |
| Run | Concurrency | tick `selected_agents`, worker count from `pick_agents` step |
| Session Health | (current stub) | tick `parity_ok`, `provider`, last tick timestamp |

**Deps:** forseti-release-d shipped (agent tracker tables exist)
**Key risk:** Read-only access to `langgraph-ticks.jsonl` from PHP — confirm `COPILOT_HQ_ROOT` env available in web context

---

## 🗓️ Planned

### 🔧 forseti-release-r — Console Wiring: Observe & Monitoring (Phase 5)
**Theme:** Deliver real-time observability: node traces, performance metrics, drift detection, alerts

**Status:** ready (feature specs completed 2026-04-20)
**Feature file:** `features/forseti-langgraph-console-observe/feature.md`
**Acceptance criteria:** `features/forseti-langgraph-console-observe/01-acceptance-criteria.md` (39 criteria across 8 areas)

**Scope:**
| Console Section | Subsection | Purpose |
|---|---|---|
| Observe | Node Traces | Timeline of step executions from last tick; filter by node name/timestamp |
| Observe | Runtime Metrics | Tick-level dashboard (duration, concurrency, agents); trend chart + anomaly detection (2σ check) |
| Observe | Drift Detection | Baseline comparison for node performance; alert on ±50% variance; export CSV |
| Observe | Alerts & Incidents | 24h incident log (executor-failures, blocked agents, tick timeouts); filter + search |
| Observe | Feature Progress | Embed live feature-progress dashboard with per-phase breakdown |

**Data sources:**
- `langgraph-ticks.jsonl` — tick entries with `step_results[]`
- `tmp/executor-failures/*.json` — executor failure events
- `sessions/*/inbox/*/command.md` — agent block status
- `dashboards/FEATURE_PROGRESS.md` — feature flow (auto-refreshed)

**Routes (6 new):**
- `GET /langgraph-console/observe` → index
- `GET /langgraph-console/observe/traces` → timeline view
- `GET /langgraph-console/observe/metrics` → dashboard
- `GET /langgraph-console/observe/drift` → baseline comparison
- `GET /langgraph-console/observe/alerts` → incident list
- `GET /langgraph-console/observe/feature-progress` → embedded dashboard

**Components:**
- New service: `MetricsAggregator` (baseline calc, drift detection, formatting)
- New service: `IncidentCollector` (scan executor-failures, agent blocks)
- New controller: `LangGraphConsoleObserveController` (all 6 routes)
- 5 Twig templates: traces.html.twig, metrics.html.twig, drift.html.twig, alerts.html.twig, feature-progress.html.twig

**Tests:** 35 total (20 unit + 15 integration), target >90% coverage

**Deps:** None (all data is read-only from filesystem)

---

### 📋 forseti-release-t — Admin & Configuration (Phase 7)
**Theme:** Provide operators with tunable settings, permission management, audit trail, health monitoring

**Status:** ready (feature specs completed 2026-04-20)
**Feature file:** `features/forseti-langgraph-console-admin/feature.md`
**Acceptance criteria:** `features/forseti-langgraph-console-admin/01-acceptance-criteria.md` (43 criteria across 9 areas)

**Scope:**
| Console Section | Subsection | Purpose |
|---|---|---|
| Admin | Settings | Tune parameters (drift threshold, retention policies, canary defaults) — form with validation |
| Admin | Permissions | Display role matrix; team assignment (scope agents per user) |
| Admin | Audit Log | Mutation history with filtering (operator, action, date, resource); export CSV |
| Admin | Health & Status | Orchestrator status (up/slow/down), agent pool status, data freshness indicators, auto-refresh 30s |
| Admin | Navigation | Landing page selection, visible sections, theme (light/dark) |

**Data persistence:**
- Settings: Drupal `config_factory` + `$COPILOT_HQ_ROOT/admin/settings.json` (fallback)
- Audit log: new DB table `copilot_agent_tracker_audit` (timestamp, operator_id, action, resource, before/after, csrf_verified)
- Navigation: Drupal `user.settings['langgraph_nav']` (per-user preferences)

**Routes (8 new):**
- `GET /langgraph-console/admin` → index
- `GET /langgraph-console/admin/settings`, `POST` → form
- `GET /langgraph-console/admin/permissions` → matrix + team assignment
- `GET /langgraph-console/admin/audit-log` → log viewer with filters
- `GET /langgraph-console/admin/health` → dashboard
- `GET /langgraph-console/admin/health.json` → AJAX endpoint (30s refresh)
- `GET /langgraph-console/admin/navigation`, `POST` → nav customization

**DB Schema:**
```sql
CREATE TABLE copilot_agent_tracker_audit (
  id INT PRIMARY KEY AUTO_INCREMENT,
  timestamp DATETIME NOT NULL,
  operator_id INT NOT NULL,
  action VARCHAR(255) NOT NULL,
  resource_id VARCHAR(255),
  before_value LONGTEXT,
  after_value LONGTEXT,
  csrf_verified BOOLEAN DEFAULT 1,
  INDEX idx_timestamp (timestamp),
  INDEX idx_operator (operator_id),
  FOREIGN KEY (operator_id) REFERENCES users(uid)
);
```

**New permissions (2):**
- `administer console settings`
- `administer release cycle` (for Phase 6 integration)

**Components:**
- New form: `AdminSettingsForm` (ConfigFormBase, validation, persistence)
- New service: `AuditLogger` (write mutations to audit table)
- New service: `HealthAggregator` (collect orchestrator + agent status)
- New controller: `LangGraphConsoleAdminController` (all 8 routes)
- 5 Twig templates: settings.html.twig, permissions.html.twig, audit-log.html.twig, health.html.twig, navigation.html.twig

**Tests:** 27 total (15 unit + 12 integration), target >90% coverage

**Deps:** None; audit table created via hook_schema

---

### ⚠️ forseti-release-s — Release Control Panel (Phase 6) — BLOCKED ON BOARD GATE
**Theme:** Promote console from read-only to control-plane; graph versioning, promotion flow, canary controls

**Status:** blocked (awaiting board decision on scope)
**Decision required:** 
1. Is release mutation control in-scope for release-s?
2. Versioning scheme (TIMESTAMP-COMMIT-ALIAS acceptable?)
3. Auth model for graph promotions (who can promote? rollback approval needed?)
4. Rollback strategy if promoted graph breaks production

**Proposed scope (pending approval):**
| Console Section | Subsection | Purpose |
|---|---|---|
| Release | Graph Versions | Version inventory with promotion status (dev/staging/prod) |
| Release | Promotion Flow | State machine UI: dev → staging → prod promotion with CSRF + audit logging |
| Release | Canary Controls | Traffic-split slider + metrics comparison (requires orchestrator canary support) |

**Data sources:**
- `tmp/graph-versions/{version}.json` — versioned graph artifacts
- `tmp/release-cycle-active/` — promotion state
- Audit log (Phase 7) — log all promotion actions

**Routes (3 new):**
- `GET /langgraph-console/release/versions`
- `GET /langgraph-console/release/promote`, `POST`
- `GET /langgraph-console/release/canary`

**Security gates (required before implementation):**
- All mutations require `administer_release_cycle` permission
- CSRF token validation on all form submits
- Audit log entry for every promotion/rollback
- All changes logged to audit table (operator, timestamp, before/after versions)

**⚠️ Important:** Phase 6 is blocked until board approves scope. Phase 5 and Phase 7 can proceed in parallel.

---

## ⏸️ Deferred / Out of Scope Until Explicitly Scoped

| Item | Reason |
|---|---|
| LangGraph Cloud integration | Using local orchestrator; no cloud deployment yet |
| Multi-graph / multi-project support | Single orchestrator instance; revisit when org scales |
| Public-facing graph status page | Admin-only for now; consider after org launch |
| Real-time WebSocket streaming | AJAX polling sufficient for current scale |

---

## Key Files Reference

| File | Purpose |
|---|---|
| `web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php` | Telemetry dashboard (4,636 lines) |
| `web/modules/custom/copilot_agent_tracker/src/Controller/LangGraphConsoleStubController.php` | Console stubs — primary buildout target |
| `web/modules/custom/copilot_agent_tracker/copilot_agent_tracker.routing.yml` | All routes (telemetry + console) |
| `copilot-hq/orchestrator/runtime_graph/engine.py` | LangGraph graph definition |
| `copilot-hq/orchestrator/run.py` | Tick pipeline entry point |
| `copilot-hq/inbox/responses/langgraph-ticks.jsonl` | Live tick telemetry |
| `copilot-hq/inbox/responses/langgraph-parity-latest.json` | Live parity snapshot |
| `copilot-hq/dashboards/FEATURE_PROGRESS.md` | Feature flow (auto-refreshed) |
| `features/forseti-copilot-agent-tracker/01-acceptance-criteria.md` | Agent tracker AC (30 rows) |
| `features/forseti-copilot-agent-tracker/03-test-plan.md` | Agent tracker test plan (30 rows) |
