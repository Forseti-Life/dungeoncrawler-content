# Permissions validation

- Label: dungeoncrawler
- Base URL: https://dungeoncrawler.forseti.life
- Roles run: anon
- Violations: 0
- Probe issues: 13
- Config: org-chart/sites/dungeoncrawler/qa-permissions.json

## Result
- OK: no permission expectation violations detected.

## Probe issues (non-permission)

These are request errors/timeouts (`status=0`) where the probe could not determine allow/deny.

| Role | Source | Status | Path | URL |
|---|---|---:|---|---|
| anon | route | 0 | /admin/reports/genai-debug/1/delete | https://dungeoncrawler.forseti.life/admin/reports/genai-debug/1/delete |
| anon | route | 0 | /admin/reports/genai-debug/delete-all | https://dungeoncrawler.forseti.life/admin/reports/genai-debug/delete-all |
| anon | route | 0 | /dungeoncrawler/objects | https://dungeoncrawler.forseti.life/dungeoncrawler/objects |
| anon | route | 0 | /campaigns/create | https://dungeoncrawler.forseti.life/campaigns/create |
| anon | route | 0 | /characters/create/step/1 | https://dungeoncrawler.forseti.life/characters/create/step/1 |
| anon | route | 0 | /characters/create/step/1/save | https://dungeoncrawler.forseti.life/characters/create/step/1/save |
| anon | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/start | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/import-open-issues/reconcile/start |
| anon | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/tick | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/import-open-issues/reconcile/tick |
| anon | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close |
| anon | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run |
| anon | route | 0 | /dungeoncrawler/testing/queue/run | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/queue/run |
| anon | route | 0 | /dungeoncrawler/testing/queue/item/delete | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/queue/item/delete |
| anon | route | 0 | /dungeoncrawler/testing/queue/item/rerun | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/queue/item/rerun |
