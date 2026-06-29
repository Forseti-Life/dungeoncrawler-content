# Scope Activate: 20260411-forseti-release-b

- Agent: pm-forseti
- Status: pending
- Release: 20260411-forseti-release-b
- Date: 2026-04-11
- Dispatched by: orchestrator (release active 800m, 0 features scoped)

## Task

Release `20260411-forseti-release-b` has been active for **800 minutes** with zero features scoped.
Activate features now using:

```bash
bash scripts/pm-scope-activate.sh forseti <feature_id>
```

Cap is **10 features** (auto-close fires at 10 or 24h). Activate your highest-priority `ready` features first.

## Ready features (up to 10)
- `forseti-jobhunter-application-deadline-tracker`
- `forseti-langgraph-console-release-panel`

## Done when
At least 3 features activated; dev/QA inbox items exist for each.
