# Verification Report — 20260322-recover-impl-copilot-agent-tracker

**Decision: APPROVE**

| Field | Value |
|---|---|
| QA seat | qa-forseti |
| Dev item | 20260322-recover-impl-copilot-agent-tracker |
| Dev seat | dev-forseti-agent-tracker |
| Run date | 2026-04-05 |
| Environment | Production (https://forseti.life) |
| Suite | qa-suites/products/forseti-agent-tracker/suite.json |

---

## Test Execution

### Suite: tracker-copilot-agent-tracker (functional, required_for_release)

**Command:**
```
FORSETI_BASE_URL=https://forseti.life \
DRUPAL_ROOT=/var/www/html/forseti \
TELEMETRY_TOKEN=<redacted> \
FORSETI_COOKIE_ADMIN=<redacted> \
python3 qa-suites/products/forseti-agent-tracker/run-copilot-agent-tracker-tests.py
```

**Results: 24/24 PASS**

| Test ID | Description | Result |
|---|---|---|
| dashboard-admin-200 | Dashboard admin → 200 | PASS |
| releases-admin-200 | Releases page admin → 200 | PASS |
| waitingonkeith-admin-200 | Waiting-on-Keith admin → 200 | PASS |
| dashboard-anon-403 | Anon dashboard → 403 | PASS |
| releases-anon-403 | Anon releases → 403 | PASS |
| waitingonkeith-anon-403 | Anon waitingonkeith → 403 | PASS |
| api-no-token-403 | API POST no token → 403 | PASS |
| api-bad-token-403 | API POST bad token → 403 | PASS |
| api-valid-200 | API POST valid token → 200 | PASS |
| api-empty-body-400 | API empty body → 400 | PASS |
| api-empty-body-msg | Empty body message correct | PASS |
| api-missing-summary-400 | Missing summary → 400 | PASS |
| api-missing-summary-msg | Missing summary message correct | PASS |
| api-malformed-json-400 | Malformed JSON → 400 | PASS |
| api-malformed-json-msg | Malformed JSON message correct | PASS |
| drush-updb-clean | drush updb exits 0 | PASS |
| watchdog-clean | 0 error/critical watchdog entries | PASS |
| perf-dashboard-lt3s | Dashboard loads < 3s (actual: 1.90s) | PASS |
| db-table-agents-exists | agents table exists | PASS |
| db-table-events-exists | events table exists | PASS |
| token-nonempty | Telemetry token set | PASS |
| csrf-forged-approve-403 | CSRF: forged approve → 403 | PASS (EXTEND) |
| upsert-dedup-1-row | Double POST same agent_id → 1 row | PASS (EXTEND) |
| hook-uninstall-tables-absent | All 4 tables absent after uninstall | PASS (EXTEND) |

Artifact: `sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/test-results.json`

### Suite: tracker-full-site-audit

**Run:** 20260405-165330  
**Result:** PASS — 0 missing assets, 0 ACL violations, 0 other failures, 0 config drift

Artifact: `sessions/qa-forseti/artifacts/auto-site-audit/20260405-165330/findings-summary.md`

---

## EXTEND Items Verified

1. **CSRF protection on approve/dismiss routes** — forged POST without valid CSRF token returns 403. PASS.
2. **Upsert deduplication** — double POST with same `agent_id` yields exactly 1 row in `copilot_agent_tracker_agents`. Implemented via `database->merge()` in `AgentTrackerStorage`. PASS.
3. **hook_uninstall cleanup** — all 4 module tables (`copilot_agent_tracker_agents`, `copilot_agent_tracker_events`, `copilot_agent_tracker_replies`, `copilot_agent_tracker_inbox_resolutions`) and state key absent after `drush pmu copilot_agent_tracker`. PASS.

---

## Investigation Note

Initial test run failed due to a token mismatch: the `DRUPAL_ROOT` env default pointed to a dev directory whose drush resolves a different database (`drupal_db` vs `forseti_prod`). Once the correct production token was used (`DRUPAL_ROOT=/var/www/html/forseti`), all 24 tests passed. No code defects found.

**Action taken:** The suite.json notes and command should specify `DRUPAL_ROOT=/var/www/html/forseti` explicitly to avoid this confusion in future runs.

---

## Decision

**APPROVE** — All 24/24 test cases pass. Site audit clean. EXTEND items (CSRF, upsert, uninstall) verified end-to-end on production.

No new Dev items identified. PM may proceed to release gate.
