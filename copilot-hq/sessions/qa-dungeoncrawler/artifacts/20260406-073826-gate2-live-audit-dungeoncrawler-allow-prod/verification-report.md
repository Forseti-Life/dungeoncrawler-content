# Gate 2 Verification Report — DungeonCrawler Live Audit

- **Audit ID:** 20260406-073826-gate2-live-audit-dungeoncrawler-allow-prod
- **Run timestamp:** 20260406-081138
- **Target:** https://dungeoncrawler.forseti.life (production)
- **Auditor:** qa-dungeoncrawler
- **Verdict:** ✅ APPROVE

---

## Summary

Live audit PASS — 0 permission violations, 0 missing assets, 7 failures (all accepted risk: `copilot_agent_tracker` dev/HQ module 404s, not deployed to dungeoncrawler production) — base_url: https://dungeoncrawler.forseti.life.

---

## Acceptance criteria

| # | Criterion | Result |
|---|---|---|
| AC1 | Script exit 0, 0 permission violations | ✅ PASS |
| AC2 | Artifacts present at `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-081138/` | ✅ PASS |
| AC3 | `base_url` confirmed as `https://dungeoncrawler.forseti.life` | ✅ PASS |
| AC4 | All provisional code-level APPROVEs upgraded to full live APPROVE | ✅ PASS |
| AC5 | All failures documented and classified | ✅ PASS |

---

## Findings

### Permission violations
**Count: 0** — No ACL violations detected.

- 45 PM ACL questions suppressed (all expected anon=deny).

### Missing assets (404)
**Count: 0** — All expected assets present.

### Config drift
**Count: 0** — No drift detected.

### Other failures
**Count: 7** — All classified as accepted risk.

| Route name | Path | Status | Classification |
|---|---|---|---|
| `copilot_agent_tracker.langgraph_console_home` | `/admin/reports/copilot-agent-tracker/langgraph-console` | 404 | Accepted risk |
| `copilot_agent_tracker.langgraph_console_build` | `/admin/reports/copilot-agent-tracker/langgraph-console/build` | 404 | Accepted risk |
| `copilot_agent_tracker.langgraph_console_test` | `/admin/reports/copilot-agent-tracker/langgraph-console/test` | 404 | Accepted risk |
| `copilot_agent_tracker.langgraph_console_run` | `/admin/reports/copilot-agent-tracker/langgraph-console/run` | 404 | Accepted risk |
| `copilot_agent_tracker.langgraph_console_observe` | `/admin/reports/copilot-agent-tracker/langgraph-console/observe` | 404 | Accepted risk |
| `copilot_agent_tracker.langgraph_console_release` | `/admin/reports/copilot-agent-tracker/langgraph-console/release` | 404 | Accepted risk |
| `copilot_agent_tracker.langgraph_console_admin` | `/admin/reports/copilot-agent-tracker/langgraph-console/admin` | 404 | Accepted risk |

**Risk classification rationale:** `copilot_agent_tracker` is a dev/HQ-side module providing a LangGraph monitoring console. It is not part of the DungeonCrawler product scope and is not deployed to `dungeoncrawler.forseti.life`. These 404s are identical in pattern to the `dungeoncrawler_tester` 404s accepted in the prior Gate 4 audit. No dungeoncrawler game functionality is affected.

---

## Evidence

- Full audit artifacts: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-081138/`
  - `findings-summary.json` — machine-readable summary
  - `permissions-validation.json` — ACL check results
  - `dungeoncrawler-custom-routes.json` — route list
  - `dungeoncrawler-crawl.json` — crawl results

---

## Gate 2 sign-off

**QA verdict: APPROVE**

All dungeoncrawler game routes, ACL rules, and assets are clean. The 7 failures are dev/HQ module 404s with accepted risk. DungeonCrawler release-c is cleared for Gate 3 (release operator sign-off).
