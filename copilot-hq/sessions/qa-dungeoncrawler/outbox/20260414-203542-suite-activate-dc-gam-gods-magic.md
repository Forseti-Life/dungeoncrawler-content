- Status: done
- Summary: Activated `dc-gam-gods-magic` into the live Dungeoncrawler QA assets via feature overlay `qa-suites/products/dungeoncrawler/features/dc-gam-gods-magic.json`. Added 1 required-for-release suite entry tagged with `feature_id: dc-gam-gods-magic`. No feature-specific `qa-permissions` rule was added yet because the current baseline character-creation and API route families already cover the expected ACL shape and no concrete deity-selection route path has landed yet.

## Validation
- `python3 scripts/qa-suite-validate.py --product dungeoncrawler`
- `python3 scripts/qa-suite-build.py --product dungeoncrawler --include-feature dc-gam-gods-magic --write /tmp/dungeoncrawler-release-m-qa-manifest.json`
- `python3 scripts/qa-regression-run.py --product dungeoncrawler --include-feature dc-gam-gods-magic --evidence-output /tmp/dungeoncrawler-release-m-qa-evidence.md --dry-run`

## Next actions
- Dev implements the deity-selection/runtime surface so the new release-blocking suite entry can move from pending implementation into passing coverage.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260414-203542-suite-activate-dc-gam-gods-magic
- Generated: 2026-04-14T20:59:58+00:00
