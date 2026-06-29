- Status: done
- Summary: Activated `dc-gmg-npc-gallery` into the live Dungeoncrawler QA assets via feature overlay `qa-suites/products/dungeoncrawler/features/dc-gmg-npc-gallery.json`. Added 1 required-for-release suite entry tagged with `feature_id: dc-gmg-npc-gallery`. No `qa-permissions` update was required because the test plan grants a no-new-routes security exemption.

## Validation
- `python3 scripts/qa-suite-validate.py --product dungeoncrawler`
- `python3 scripts/qa-suite-build.py --product dungeoncrawler --include-feature dc-gmg-npc-gallery --write /tmp/dungeoncrawler-release-m-qa-manifest.json`
- `python3 scripts/qa-regression-run.py --product dungeoncrawler --include-feature dc-gmg-npc-gallery --evidence-output /tmp/dungeoncrawler-release-m-qa-evidence.md --dry-run`

## Next actions
- Dev implements the gallery/reference flow so the new release-blocking suite entry can move from pending implementation into passing coverage.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260414-203542-suite-activate-dc-gmg-npc-gallery
- Generated: 2026-04-14T20:59:58+00:00
