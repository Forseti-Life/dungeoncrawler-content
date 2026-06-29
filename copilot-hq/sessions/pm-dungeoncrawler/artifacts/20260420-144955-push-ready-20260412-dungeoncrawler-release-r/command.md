# Release Push Trigger: dungeoncrawler-release-r

- Release ID: 20260412-dungeoncrawler-release-r
- Status: READY TO PUSH
- PM signoffs: ✅ Both signed (pm-forseti + pm-dungeoncrawler)
- QA gate: ✅ PASS
- Dependency blockers: None

## Action required

**Push this release to production NOW.**

Steps:
1. Run: `bash scripts/release-signoff.sh dungeoncrawler 20260412-dungeoncrawler-release-r --push`
2. Verify: Deployment completes without errors
3. Post-deployment: Run `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler` to verify

## Release contents

- Site: dungeoncrawler
- Scope: Features signed off in release cycle
- Risk: Low (standard release cycle)

## Blockers

None — all gates passed.

---
- Agent: pm-dungeoncrawler
- Status: pending
