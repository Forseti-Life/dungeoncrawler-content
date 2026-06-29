# Test Plan: forseti-langgraph-ui (release-g scope)

- Feature: forseti-langgraph-ui
- Release: 20260408-forseti-release-g
- Module: copilot_agent_tracker
- QA owner: qa-forseti
- PM owner: pm-forseti
- Version: 1.0 — 2026-04-08
- AC source: features/forseti-langgraph-ui/01-acceptance-criteria.md

## Environment notes
- Production server IS the test environment: `https://forseti.life`
- `ALLOW_PROD_QA=1` required for `scripts/site-audit-run.sh`
- Admin credential required for auth-gated routes
- All routes: substitute `BASE_URL` with `https://forseti.life`

## Happy path tests

| Test ID | AC ref | Description | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|
| TP-BUILD-01 | AC-1 | State Schema panel renders for admin | Log in as admin; GET `BASE_URL/admin/reports/copilot-agent-tracker/langgraph-console/build` | 200; page contains State Schema section; LangGraphDeps fields visible in a table | PASS: 200 + schema table with ≥6 field rows. FAIL: non-200, PHP error, or empty section |
| TP-BUILD-02 | AC-1 | State Schema fields correct | Inspect rendered table | Fields include: `run_cmd`, `dispatch_commands_step`, `release_cycle_step`, `coordinated_push_step`, `prioritized_agents`, `health_check_step` | PASS: all 6 listed. FAIL: missing fields |
| TP-BUILD-03 | AC-2 | Nodes & Routing panel renders | Same page as TP-BUILD-01 | Nodes & Routing subsection visible; orchestrator step names listed | PASS: section present with ≥3 step names. FAIL: section absent or empty |
| TP-TEST-01 | AC-3 | Eval Scorecards placeholder renders for admin | GET `BASE_URL/admin/reports/copilot-agent-tracker/langgraph-console/test` | 200; Eval Scorecards subsection visible with placeholder table or empty-state message | PASS: 200 + scorecard table/placeholder visible. FAIL: non-200 or PHP error |

## Auth / permission tests

| Test ID | AC ref | Description | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|
| TP-AUTH-01 | AC-1,2,3 | Build console anon denied | `curl -s -o /dev/null -w "%{http_code}" BASE_URL/admin/reports/copilot-agent-tracker/langgraph-console/build` | 403 | PASS: 403. FAIL: 200 |
| TP-AUTH-02 | AC-3 | Test console anon denied | `curl -s -o /dev/null -w "%{http_code}" BASE_URL/admin/reports/copilot-agent-tracker/langgraph-console/test` | 403 | PASS: 403. FAIL: 200 |

## Regression tests

| Test ID | AC ref | Description | Steps | Expected outcome |
|---|---|---|---|---|
| TP-REG-01 | AC-4 | All 7 console routes still return 403 for anonymous | `curl` each of the 7 console routes without auth | 403 on all 7 |
| TP-REG-02 | AC-4 | All 7 telemetry dashboard routes still return 200 for admin | Log in; GET each dashboard route | 200 on all 7 |
| TP-REG-03 | AC-5 | No XSS in rendered field names | Inspect HTML source of Build page for unescaped `<`, `>`, `"` from schema data | All values HTML-escaped |

## Security checks

| Test ID | AC ref | Description | Steps | Expected outcome |
|---|---|---|---|---|
| TP-SEC-01 | AC-5 | No raw PHP variable output in Twig | Review modified template files | All variables use `{{ var|e }}` or equivalent auto-escape |
| TP-SEC-02 | Security AC | engine.py missing/unreadable handled gracefully | (Dev to verify) Remove read permission on engine.py; load Build page | Empty-state message displayed, not PHP fatal or 500 |
