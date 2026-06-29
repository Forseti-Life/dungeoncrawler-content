- Status: done
- Summary: Activated `dc-ui-encounter-party-rail` into the live Dungeoncrawler QA assets via feature overlay `qa-suites/products/dungeoncrawler/features/dc-ui-encounter-party-rail.json`. Added 1 required-for-release suite entry tagged with `feature_id: dc-ui-encounter-party-rail`. No feature-specific `qa-permissions` rule was added yet because the current route-audit baseline already covers the existing encounter/map surfaces and no concrete new route path is in the implementation yet.

## Validation
- `python3 scripts/qa-suite-validate.py --product dungeoncrawler`
- `python3 scripts/qa-suite-build.py --product dungeoncrawler --include-feature dc-ui-encounter-party-rail --write /tmp/dungeoncrawler-release-m-qa-manifest.json`
- `python3 scripts/qa-regression-run.py --product dungeoncrawler --include-feature dc-ui-encounter-party-rail --evidence-output /tmp/dungeoncrawler-release-m-qa-evidence.md --dry-run`

## Next actions
- Dev implements the rail UI so the new release-blocking suite entry can move from pending implementation into passing coverage.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260414-203542-suite-activate-dc-ui-encounter-party-rail
- Generated: 2026-04-14T20:59:58+00:00
