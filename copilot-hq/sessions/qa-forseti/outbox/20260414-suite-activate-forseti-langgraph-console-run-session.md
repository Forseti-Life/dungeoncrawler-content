# Suite Activation: forseti-langgraph-console-run-session

- Status: done
- Summary: Suite activation for `forseti-langgraph-console-run-session` complete. Added 9 TCs (TC-1..TC-9) to `qa-suites/products/forseti/suite.json` and 1 permission rule to `qa-permissions.json`, all tagged `feature_id: forseti-langgraph-console-run-session`. Suite validates at 300 entries (was 291). Permission rule covers the Run panel route as admin-only (anon/authenticated→403, administrator→200). TC-3 (empty-state) is manual-only until Dev implements the route. TC-9 (read-only check) runs against routing.yml at Gate 2. Commit: `1c61ac2a6`.

## Entries added

| ID | Type | TC |
|---|---|---|
| forseti-langgraph-console-run-session-smoke | functional | TC-1: Run panel loads 200 as admin |
| forseti-langgraph-console-run-session-threads-runs | functional | TC-2: Threads & Runs renders latest tick agent names |
| forseti-langgraph-console-run-session-threads-empty-state | functional | TC-3: empty-state message when no tick data (manual) |
| forseti-langgraph-console-run-session-stream-events | functional | TC-4: Stream Events renders step_results with truncation |
| forseti-langgraph-console-run-session-resume-retry | functional | TC-5: Resume & Retry lists blocked/needs-info outbox items |
| forseti-langgraph-console-run-session-session-health | functional | TC-6: Session Health shows parity_ok from parity file |
| forseti-langgraph-console-run-session-env-warning | functional | TC-7: COPILOT_HQ_ROOT unset shows warning banner |
| forseti-langgraph-console-run-session-acl-anon | security | TC-8: anon access blocked (403/redirect) |
| forseti-langgraph-console-run-session-readonly | security | TC-9: no POST/PATCH/DELETE handlers on run routes |

## Permission rules added

| ID | Path | Expect |
|---|---|---|
| forseti-langgraph-console-run-session-run-panel | `/admin/reports/copilot-agent-tracker/langgraph-console/run` | anon:403, authenticated:403, administrator:200 |

## Gaps / notes
- TC-3 (empty-state when ticks absent) is marked `echo 'Manual: ...'` — requires file system manipulation and is not automatable via curl; marked `required_for_release: true` so it surfaces at Gate 2.
- TC-7 negative case (env unset, banner appears) requires reloading Apache with COPILOT_HQ_ROOT removed — manual-only verification at Gate 2.
- TC-9 (read-only) inspects routing.yml directly; if Dev places routes in a different file, the command path will need updating at Gate 2.

## Next actions
- Dev may proceed with implementation; all 9 TCs are live in suite.
- No blocking items for Dev from QA.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 28
- Rationale: Suite live during Dev implementation gives clear Gate 2 targets including ACL and read-only security checks for the admin console panel.
