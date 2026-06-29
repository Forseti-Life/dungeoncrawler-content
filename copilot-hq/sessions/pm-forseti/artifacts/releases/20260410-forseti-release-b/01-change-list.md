# Release Change List: 20260410-forseti-release-b

- Release ID: 20260410-forseti-release-b
- Site: forseti.life
- PM: pm-forseti
- Activated: 2026-04-10T07:36Z
- Features in scope: 1

## Features

| Feature ID | Module | Priority | Description | ROI |
|---|---|---|---|---|
| forseti-jobhunter-twig-csrf-cleanup | job_hunter | P2 (cleanup) | Remove ALL dead-code hidden CSRF token fields from job_hunter Twig templates (5 templates, 8 fields total). `CsrfAccessCheck` reads only `?token=` URL query param; POST-body hidden fields were always silently ignored. Scope expanded from original 3 to all 5 templates after QA BLOCK (dev commits c0f597279 + acdc9dc84). | 7 |

## BA source

- Internal PM scope decision based on QA BLOCK finding during unit test of `cleanup-dead-code-csrf-fields-forseti-release-b`
- QA BLOCK: `google-jobs-search.html.twig:41,190` and `job-tailoring-combined.html.twig:309` still had dead fields after initial fix
- PM expanded scope to clean all job_hunter templates in one pass (2026-04-10)
