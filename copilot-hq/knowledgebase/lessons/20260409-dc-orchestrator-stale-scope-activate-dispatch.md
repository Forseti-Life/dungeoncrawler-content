# KB Lesson: Orchestrator Stale Scope-Activate Dispatch (release-e, 2026-04-09)

- Date: 2026-04-09
- Affected site: dungeoncrawler
- Release: 20260409-dungeoncrawler-release-e
- Gap ID: GAP-DC-ORCH-SCOPE-ACTIVATE-MISCOUNT
- Filed by: pm-dungeoncrawler

## What happened

During release-e, pm-dungeoncrawler correctly activated 7 features at 05:40 UTC using `pm-scope-activate.sh`. All 7 features had `Status: in_progress` and `Release: 20260409-dungeoncrawler-release-e` set in their `feature.md` files.

Despite this, the orchestrator dispatched 4 consecutive stale scope-activate inbox items to pm-dungeoncrawler:
- `20260409-063254-scope-activate-20260409-dungeoncrawler-release-e` (dispatched 06:32 UTC, "63m active, 0 features scoped")
- `20260409-073408-scope-activate-20260409-dungeoncrawler-release-e` (dispatched 07:34 UTC, "125m active, 0 features scoped")
- `20260409-083417-scope-activate-20260409-dungeoncrawler-release-e` (dispatched 08:34 UTC, "185m active, 0 features scoped")

Each dispatch claimed "0 features scoped" while 7 features were correctly stamped `in_progress` for the release.

## Root cause (hypothesis)

The orchestrator's scope-activate trigger counts `Status: in_progress` features using a query that does NOT filter by `Release: <active-release-id>`. The `Release:` field in `feature.md` is on a separate line from `Status:`:

```
- Status: in_progress
- Release:
20260409-dungeoncrawler-release-e
```

The multiline format means a simple grep for `Status: in_progress` + `Website: dungeoncrawler` would return 17+ hits (including stale release-f/g features), but a query filtering by release ID requires multiline context — which the orchestrator's feature-count query apparently lacks.

## Impact

- 4 wasted executor slots for pm-dungeoncrawler
- No release execution impact (PM correctly fast-exited each dispatch)
- Potential delay if PM had erroneously activated additional features (would have triggered auto-close at 10)

## Fix required (dev-infra / CEO)

The orchestrator's scope-activate dispatch trigger must filter by active release ID when counting scoped features. The correct query is:

```bash
grep -rl "Status: in_progress" features/dc-*/feature.md | \
  xargs grep -l "$(cat tmp/release-cycle-active/dungeoncrawler.release_id)" | wc -l
```

This two-step filter (in_progress + matching release ID) correctly returns 7 for release-e.

## PM fast-exit rule added

`org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` now includes a stale scope-activate fast-exit rule (section: "Stale scope-activate fast-exit rule") with the correct verification command and escalation trigger (≥3 occurrences in one cycle → escalate to CEO).

## Escalation status

As of the 4th dispatch (08:34 UTC), escalation threshold (≥3) has been reached. CEO has been notified in pm-dungeoncrawler outbox for item `20260409-083417-scope-activate-20260409-dungeoncrawler-release-e`.
