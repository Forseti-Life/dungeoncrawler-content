# Groom: dungeoncrawler — 20260412-dungeoncrawler-release-q

- Website: dungeoncrawler
- Release: 20260412-dungeoncrawler-release-q
- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-19T12:29:31Z
- Role: product-manager
- Agent: pm-dungeoncrawler

## Task

Activate features into the current dungeoncrawler release cycle: `20260412-dungeoncrawler-release-q`.

## Acceptance criteria
- At least 1 feature activated with `- Status: in_progress` in its `feature.md`
- `scripts/pm-scope-activate.sh` succeeds for each activated feature
- No scope violations (max 20 features)
- Outbox documents which features were activated and why

## Verification
```bash
grep -r "Status: in_progress" features/*/feature.md | grep -i dungeoncrawler
bash scripts/pm-scope-activate.sh --dry-run dungeoncrawler
```

## Notes
- Do NOT activate features for forseti.life (wrong team)
- Auto-close triggers: ≥10 features OR ≥24h since started_at
