# Implementation Notes — Phase 7: Admin & Configuration

**Feature:** `forseti-langgraph-console-admin`  
**Project:** `PROJ-001`  
**Module target:** `drupal_langgraph` (canonical boundary), with `forseti-copilot-agent-tracker` kept as compatibility shim only

## Intent

Complete the admin/configuration slice for the LangGraph console without re-expanding the legacy tracker module. The implementation should live in the consolidated `drupal_langgraph` module/repo and read HQ state from the canonical `/home/ubuntu/forseti.life` root.

## Required surfaces

1. Admin landing page: runtime roots, artifact health, navigation to sub-pages
2. Settings page: tunable thresholds and retention settings
3. Permissions page: read-only section/role matrix plus optional operator-scoped seat selection
4. Audit log page: recent admin mutations with filtering/export
5. Health page: orchestrator freshness, agent pool status, data freshness, parity status
6. Navigation page: landing page + visible sections + theme preferences

## Implementation approach

### 1. Module boundary and routes

- Implement the admin surface in `drupal_langgraph`, not `copilot_agent_tracker`.
- Keep admin routes under `/admin/reports/drupal-langgraph/langgraph-console/admin...`.
- Keep compatibility redirects in `forseti-copilot-agent-tracker` only.

### 2. Services

- Extend `HqPathManager` for canonical root + compatibility fallbacks.
- Add a focused admin data service layer if the controller grows beyond simple rendering:
  - settings read/write
  - audit log query/export
  - agent pool health aggregation
  - data freshness checks
- Reuse existing path helpers instead of hardcoding root paths in controllers/forms.

### 3. Settings persistence

Use this precedence model:

1. Drupal config is authoritative
2. JSON file under the HQ runtime root is fallback/import/export only
3. Defaults apply when neither exists

Write behavior:

- On submit: validate inputs, write Drupal config first, then mirror to runtime JSON
- On read: prefer Drupal config; if empty, load JSON; if both missing, use defaults
- If JSON write fails, surface a warning rather than silently ignoring it

Suggested config object:

- `drupal_langgraph.admin_settings`

Suggested JSON path:

- `<hq-runtime-root>/admin/settings.json`

### 4. Audit log storage

- Use a dedicated Drupal table for admin mutation history.
- Record:
  - timestamp
  - operator uid
  - action
  - resource id
  - before value
  - after value
  - csrf verified flag
- Index by timestamp and operator id.
- Retention cleanup should run via Drupal cron.

### 5. Health aggregation

Health page should read from:

- `inbox/responses/langgraph-ticks.jsonl`
- `inbox/responses/langgraph-parity-latest.json`
- `dashboards/FEATURE_PROGRESS.md`
- `sessions/*/inbox/*`
- `sessions/*/outbox/*`
- `tmp/release-cycle-active/*`

Derived sections:

- orchestrator freshness from latest tick timestamp
- parity status from parity artifact
- provider from latest tick
- agent table from inbox/outbox/session timestamps
- data freshness from file mtimes and executor failure counts if present

### 6. Permissions / team assignment

- Keep the permissions matrix read-only in this phase.
- If team assignment ships in this phase, store selections in Drupal user data rather than inventing a new HQ-side store.
- Seat enumeration should come from `sessions/*/inbox/` directories.

### 7. Navigation preferences

- Store navigation preferences per-user in Drupal user data.
- Support:
  - landing page
  - visible sections
  - theme choice
- If hidden sections include the current landing page, fall back to `home`.

## Non-negotiable constraints

- No hardcoded stale absolute HQ paths in controller/form code
- No dependency on private session-writing behavior from the public-facing module
- No broad silent catches; surface meaningful warnings on missing runtime files
- No secret/config value logging in audit before/after payloads

## Validation targets

1. Admin routes enforce admin-only access
2. Settings save/read path works from Drupal config, mirrored to JSON
3. Audit entries are created for admin mutations
4. Health page renders safely when runtime artifacts are missing
5. Runtime root display reflects canonical `/home/ubuntu/forseti.life` on this host

## Dependencies

- `drupal_langgraph` route/controller/service layer
- Drupal config API
- Drupal cron / schema hooks for audit retention
- HQ runtime artifacts already produced by orchestrator/session flow

## Notes

- The standalone `drupal-langgraph` repo already regained release/admin parity for runtime-root reporting and release evidence/troubleshooting; this feature should build on that direction rather than re-centering the old tracker module.
