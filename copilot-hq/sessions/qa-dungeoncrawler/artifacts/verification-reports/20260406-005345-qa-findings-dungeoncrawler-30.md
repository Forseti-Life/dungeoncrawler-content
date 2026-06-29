# Gate 2 Verification Report — 20260406-005345-qa-findings-dungeoncrawler-30

- Feature/Item: QA findings retest — 30 audit failures from 20260406-005345
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260406-005345-qa-findings-dungeoncrawler-30.md
- Verified by: qa-dungeoncrawler
- Date: 2026-04-06
- Verdict: **APPROVE**

---

## Context

The original audit run 20260406-005345 reported 30 "Other failures (4xx/5xx)". Dev diagnosed the failures and filed a passthrough to dev-infra. QA re-ran the audit (20260406-141228) to verify resolution.

---

## Original failures (30)

| Count | Source | Pattern | Original status |
|---:|---|---|---|
| 23 | `dungeoncrawler_tester` routes | `/dungeoncrawler/testing/*` | 404 at audit time |
| 7 | `copilot_agent_tracker` routes | `/admin/reports/copilot-agent-tracker/langgraph-console*` | 404 (persistent) |

---

## Re-audit results — run 20260406-141228

**Failures remaining: 7**

```
Audit run: 20260406-141228
Other failures (4xx/5xx): 7
Suppressed (already decided as anon=deny): 45
Permission violations: 0
Missing assets (404): 0
```

### Group 1: dungeoncrawler_tester (23 routes) — RESOLVED ✅

All 23 `dungeoncrawler_tester` routes now return 403 (auth-required, module enabled). They are correctly suppressed by the `dungeoncrawler-testing-area` deny rule in `qa-permissions.json`. Zero failures attributed to this group in re-audit. Dev's diagnosis confirmed: the 404s were transient — routes were unresolvable during the post-Gate-4 deployment/cache-rebuild window at 00:53.

### Group 2: copilot_agent_tracker (7 routes) — TOOLING FALSE POSITIVE ⚠️

Still returning 404. These are NOT product defects:

| Route | Path | Status |
|---|---|---|
| `copilot_agent_tracker.langgraph_console` | `/admin/reports/copilot-agent-tracker/langgraph-console` | 404 |
| `copilot_agent_tracker.langgraph_console_build` | `/admin/reports/copilot-agent-tracker/langgraph-console/build` | 404 |
| `copilot_agent_tracker.langgraph_console_test` | `/admin/reports/copilot-agent-tracker/langgraph-console/test` | 404 |
| `copilot_agent_tracker.langgraph_console_run` | `/admin/reports/copilot-agent-tracker/langgraph-console/run` | 404 |
| `copilot_agent_tracker.langgraph_console_observe` | `/admin/reports/copilot-agent-tracker/langgraph-console/observe` | 404 |
| `copilot_agent_tracker.langgraph_console_release` | `/admin/reports/copilot-agent-tracker/langgraph-console/release` | 404 |
| `copilot_agent_tracker.langgraph_console_admin` | `/admin/reports/copilot-agent-tracker/langgraph-console/admin` | 404 |

**Root cause**: `copilot_agent_tracker` module is disabled on dungeoncrawler, but `drupal-custom-routes-audit.py` scans all `.routing.yml` files on disk regardless of module enabled state. Routes from disabled modules return 404, which bypasses `qa-permissions.json` suppression (suppression only fires on 401/403 responses, not 404).

**Infrastructure disposition**: Dev filed passthrough to dev-infra at `sessions/dev-dungeoncrawler/artifacts/passthrough-dev-infra-route-audit-disabled-modules.md`. Fix required in `drupal-custom-routes-audit.py` to skip routes from disabled modules.

---

## AC verification result

| AC item | Dev claim | QA result |
|---|---|---|
| 23 dungeoncrawler_tester 404s were transient | Transient (deployment window) | ✅ CONFIRMED — 0 failures on re-run |
| 7 copilot_agent_tracker 404s are tooling false positives | False positive (module disabled on dungeoncrawler) | ✅ CONFIRMED — module state verified, passthrough filed |
| No product code defects | Correct — no routing/controller fixes needed | ✅ CONFIRMED |

---

## Verdict

**APPROVE** — All 30 original failures are accounted for:
- 23 were transient and have self-resolved (now 0 failures from `dungeoncrawler_tester`)
- 7 are persistent tooling false positives (disabled module routes on disk), not product defects; dev-infra passthrough filed and in queue

Outstanding action (dev-infra): process passthrough `passthrough-dev-infra-route-audit-disabled-modules.md` to suppress disabled-module routes from future audit runs. Until fixed, these 7 will continue to appear as failures in every audit.

---

## Evidence

- Re-audit run: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-141228/findings-summary.md`
- Failures JSON: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-141228/findings-summary.json` (7 items, all copilot_agent_tracker)
- Dev passthrough: `sessions/dev-dungeoncrawler/artifacts/passthrough-dev-infra-route-audit-disabled-modules.md`
- dungeoncrawler_tester re-check: `curl -o /dev/null -w "%{http_code}" https://dungeoncrawler.forseti.life/dungeoncrawler/testing` → 403 ✅
