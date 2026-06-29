# Gate R5 Production Audit — 20260412-dungeoncrawler-release-u

**Trigger:** Automated — dispatched by post-coordinated-push.sh immediately after production push.
**Site:** dungeoncrawler
**Release:** 20260412-dungeoncrawler-release-u
**Required within:** 1h (WARN) / 4h (FAIL) of push

## Task

Run the Gate R5 production smoke audit:

```bash
ALLOW_PROD_QA=1 FORSETI_BASE_URL=https://forseti.life bash scripts/site-audit-run.sh forseti-life
```

Review output for regressions. If clean, mark this item done. If issues found, create incident and block next release.

## Priority
- ROI: 90
- Rationale: R5 is the only post-push regression check; a 6h delay (as in release-r) leaves production issues undetected.
