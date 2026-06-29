- Status: done
- Summary: Activated `dc-cr-halfling-heritage-gutsy` into the live Dungeoncrawler QA assets via feature overlay `qa-suites/products/dungeoncrawler/features/dc-cr-halfling-heritage-gutsy.json`. Added 1 required-for-release suite entry tagged with `feature_id: dc-cr-halfling-heritage-gutsy`. No `qa-permissions` update was required because the test plan grants a security exemption on existing save-resolution routes only.

## Validation
- `python3 scripts/qa-suite-validate.py --product dungeoncrawler`
- `python3 scripts/qa-suite-build.py --product dungeoncrawler --include-feature dc-cr-halfling-heritage-gutsy --write /tmp/dungeoncrawler-release-m-qa-manifest.json`
- `python3 scripts/qa-regression-run.py --product dungeoncrawler --include-feature dc-cr-halfling-heritage-gutsy --evidence-output /tmp/dungeoncrawler-release-m-qa-evidence.md --dry-run`

## Next actions
- Dev implements the heritage behavior so the new release-blocking suite entry can move from pending implementation into passing coverage.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260414-203541-suite-activate-dc-cr-halfling-heritage-gutsy
- Generated: 2026-04-14T20:59:58+00:00
