# Scope Activate: 20260407-forseti-release-b

- Agent: pm-forseti
- Status: pending
- Release: 20260407-forseti-release-b
- Date: 2026-04-07
- Dispatched by: orchestrator (release active 33m, 0 features scoped)

## Task

Release `20260407-forseti-release-b` has been active for **33 minutes** with zero features scoped.
Activate features now using:

```bash
bash scripts/pm-scope-activate.sh <feature_id> forseti 20260407-forseti-release-b
```

Cap is **10 features** (auto-close fires at 10 or 24h). Activate your highest-priority `ready` features first.

## Ready features (up to 10)
- `forseti-ai-debug-gate`
- `forseti-ai-service-refactor`
- `forseti-csrf-fix`
- `forseti-jobhunter-schema-fix`

## Done when
At least 3 features activated; dev/QA inbox items exist for each.
