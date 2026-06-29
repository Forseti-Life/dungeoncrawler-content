# Permissions validation

- Label: dungeoncrawler
- Base URL: http://localhost:8080
- Roles run: anon, authenticated, content_editor, administrator
- Violations: 0
- Probe issues: 40
- Config: org-chart/sites/dungeoncrawler/qa-permissions.json

## Result
- OK: no permission expectation violations detected.

## Probe issues (non-permission)

These are request errors/timeouts (`status=0`) where the probe could not determine allow/deny.

| Role | Source | Status | Path | URL |
|---|---|---:|---|---|
| anon | route | 0 | /admin/reports/genai-debug/1/delete | http://localhost:8080/admin/reports/genai-debug/1/delete |
| anon | route | 0 | /admin/reports/genai-debug/delete-all | http://localhost:8080/admin/reports/genai-debug/delete-all |
| anon | route | 0 | /characters/create/step/1/save | http://localhost:8080/characters/create/step/1/save |
| anon | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/start | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/start |
| anon | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/tick | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/tick |
| anon | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close | http://localhost:8080/dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close |
| anon | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run | http://localhost:8080/dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run |
| anon | route | 0 | /dungeoncrawler/testing/queue/run | http://localhost:8080/dungeoncrawler/testing/queue/run |
| anon | route | 0 | /dungeoncrawler/testing/queue/item/delete | http://localhost:8080/dungeoncrawler/testing/queue/item/delete |
| anon | route | 0 | /dungeoncrawler/testing/queue/item/rerun | http://localhost:8080/dungeoncrawler/testing/queue/item/rerun |
| authenticated | route | 0 | /admin/reports/genai-debug/1/delete | http://localhost:8080/admin/reports/genai-debug/1/delete |
| authenticated | route | 0 | /admin/reports/genai-debug/delete-all | http://localhost:8080/admin/reports/genai-debug/delete-all |
| authenticated | route | 0 | /characters/create/step/1/save | http://localhost:8080/characters/create/step/1/save |
| authenticated | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/start | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/start |
| authenticated | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/tick | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/tick |
| authenticated | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close | http://localhost:8080/dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close |
| authenticated | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run | http://localhost:8080/dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run |
| authenticated | route | 0 | /dungeoncrawler/testing/queue/run | http://localhost:8080/dungeoncrawler/testing/queue/run |
| authenticated | route | 0 | /dungeoncrawler/testing/queue/item/delete | http://localhost:8080/dungeoncrawler/testing/queue/item/delete |
| authenticated | route | 0 | /dungeoncrawler/testing/queue/item/rerun | http://localhost:8080/dungeoncrawler/testing/queue/item/rerun |
| content_editor | route | 0 | /admin/reports/genai-debug/1/delete | http://localhost:8080/admin/reports/genai-debug/1/delete |
| content_editor | route | 0 | /admin/reports/genai-debug/delete-all | http://localhost:8080/admin/reports/genai-debug/delete-all |
| content_editor | route | 0 | /characters/create/step/1/save | http://localhost:8080/characters/create/step/1/save |
| content_editor | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/start | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/start |
| content_editor | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/tick | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/tick |
| content_editor | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close | http://localhost:8080/dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close |
| content_editor | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run | http://localhost:8080/dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run |
| content_editor | route | 0 | /dungeoncrawler/testing/queue/run | http://localhost:8080/dungeoncrawler/testing/queue/run |
| content_editor | route | 0 | /dungeoncrawler/testing/queue/item/delete | http://localhost:8080/dungeoncrawler/testing/queue/item/delete |
| content_editor | route | 0 | /dungeoncrawler/testing/queue/item/rerun | http://localhost:8080/dungeoncrawler/testing/queue/item/rerun |
| administrator | route | 0 | /admin/reports/genai-debug/1/delete | http://localhost:8080/admin/reports/genai-debug/1/delete |
| administrator | route | 0 | /admin/reports/genai-debug/delete-all | http://localhost:8080/admin/reports/genai-debug/delete-all |
| administrator | route | 0 | /characters/create/step/1/save | http://localhost:8080/characters/create/step/1/save |
| administrator | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/start | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/start |
| administrator | route | 0 | /dungeoncrawler/testing/import-open-issues/reconcile/tick | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/tick |
| administrator | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close | http://localhost:8080/dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close |
| administrator | route | 0 | /dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run | http://localhost:8080/dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run |
| administrator | route | 0 | /dungeoncrawler/testing/queue/run | http://localhost:8080/dungeoncrawler/testing/queue/run |
| administrator | route | 0 | /dungeoncrawler/testing/queue/item/delete | http://localhost:8080/dungeoncrawler/testing/queue/item/delete |
| administrator | route | 0 | /dungeoncrawler/testing/queue/item/rerun | http://localhost:8080/dungeoncrawler/testing/queue/item/rerun |
