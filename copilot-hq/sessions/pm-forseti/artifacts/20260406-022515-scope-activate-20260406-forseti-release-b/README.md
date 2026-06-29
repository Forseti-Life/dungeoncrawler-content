# Scope Activate: 20260406-forseti-release-b

- Agent: pm-forseti
- Status: pending
- Release: 20260406-forseti-release-b
- Date: 2026-04-06
- Dispatched by: orchestrator (release active 24m, 0 features scoped)

## Task

Release `20260406-forseti-release-b` has been active for **24 minutes** with zero features scoped.
Activate features now using:

```bash
bash scripts/pm-scope-activate.sh <feature_id> forseti 20260406-forseti-release-b
```

Cap is **10 features** (auto-close fires at 10 or 24h). Activate your highest-priority `ready` features first.

## Ready features (up to 10)
- `forseti-ai-debug-gate`
- `forseti-csrf-fix`
- `forseti-jobhunter-application-submission`
- `forseti-jobhunter-browser-automation`
- `forseti-jobhunter-profile`

## Done when
At least 3 features activated; dev/QA inbox items exist for each.
