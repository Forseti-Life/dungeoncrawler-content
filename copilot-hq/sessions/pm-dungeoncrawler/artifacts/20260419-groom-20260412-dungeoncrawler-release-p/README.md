# Groom: dungeoncrawler — 20260412-dungeoncrawler-release-p

- Website: dungeoncrawler
- Release: 20260412-dungeoncrawler-release-p
- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-19T05:06:00Z
- Role: product-manager
- Agent: pm-dungeoncrawler

## Task

Activate features into the current dungeoncrawler release cycle: `20260412-dungeoncrawler-release-p`.

The release currently has **0 features scoped** (just advanced from release-o). Select and activate the highest-ROI dungeoncrawler features from the backlog.

## Acceptance criteria
- At least 1 feature activated with `- Status: in_progress` in its `feature.md`
- `scripts/pm-scope-activate.sh` succeeds for each activated feature
- Release cycle has at least 1 in-progress feature and no scope violations (max 20)
- Outbox documents which features were activated and why

## Verification
```bash
grep -r "Status: in_progress" features/*/feature.md | grep -i dungeoncrawler
bash scripts/pm-scope-activate.sh --dry-run dungeoncrawler
```

## Notes
- Do NOT activate features for forseti.life (wrong team)
- Auto-close triggers: ≥10 features OR ≥24h since started_at (2026-04-19T00:36:26Z)
- Release-p started_at: 2026-04-19T00:36:26Z
