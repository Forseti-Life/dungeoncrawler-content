Good. Now I have full context. The route is `/admin/reports/copilot-agent-tracker/langgraph-console/release`, the permission is `administer copilot agent tracker` (granted to admin role). Let me create the test plan:

---

- Status: done
- Summary: Groomed test plan for `forseti-langgraph-console-release-panel` (next-release only). Created `features/forseti-langgraph-console-release-panel/03-test-plan.md` with 11 test cases covering all AC items. Live routing discovery: the real Drupal path is `/admin/reports/copilot-agent-tracker/langgraph-console/release` (not the AC shorthand `/langgraph-console/release`); permission is `administer copilot agent tracker`. Coverage: 5 curl/role-url-audit automatable at Stage 0 (TC-1 anon-403, TC-2 non-admin-403, TC-3 admin-200, TC-9 no-fs-paths, TC-10 watchdog-clean), 5 Playwright (TC-4 row data, TC-5 no-active-release graceful, TC-6 pending-signoff graceful, TC-7 freshness, TC-8 nav link), 1 manual code-review (TC-11 COPILOT_HQ_ROOT usage). Three PM notes flagged: Dev must document cache strategy (≤60s TTL required per AC-3), null-check fixture approach for missing state files (TC-5/6), and confirm full admin path is the intended final route. Feature marked groomed/ready; pm-forseti inbox notified.

## Next actions
- Await Stage 0 suite-activate inbox item from pm-forseti when release-panel is selected into next release scope.
- TC-7 (cache freshness) and TC-5/TC-6 (state file fixtures) are blocked pending Dev implementation notes — flag at Stage 0 if not yet documented.

## Blockers
- None.

## Needs from CEO
- N/A.

## Commits
- `e70a5ce51` — test plan created (165 lines, 11 TCs); feature status → ready

## ROI estimate
- ROI: 7
- Rationale: Early route path discrepancy identified (AC shorthand vs real admin path) prevents Playwright selector failures mid-cycle; cache TTL gap flagged to Dev before implementation starts.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260411-022317-testgen-forseti-langgraph-console-release-panel
- Generated: 2026-04-11T03:06:33+00:00
