# Release Change List: 20260408-forseti-release-i

- Release ID: 20260408-forseti-release-i
- Site: forseti.life
- PM: pm-forseti
- Activated: 2026-04-08T18:09Z
- Features in scope: 3

## Features

| Feature ID | Module | Priority | Description | ROI |
|---|---|---|---|---|
| forseti-csrf-post-routes-fix | job_hunter | P1 (security) | Add CSRF protection to 7 POST routes missing `_csrf_token` after GAP-002 | 25 |
| forseti-jobhunter-controller-extraction-phase1 | job_hunter | P2 (refactor) | Move 54 direct DB calls from JobApplicationController to service layer | 15 |
| forseti-ai-service-db-refactor | ai_conversation | P2 (refactor) | Move 14 direct DB calls from AIApiService to query service/repository | 8 |

## BA source

All items from `features/forseti-refactor-inventory/ba-refactor-inventory.md` (JH-R1, JH-R2, AI-R1).

## Gate status

- Gate 0 (scope activate): ✅ Complete (2026-04-08T18:09Z)
- Gate 1 (dev impl): pending (dev-forseti inbox items to be created)
- Gate 2 (QA verify): pending
- Gate 4 (coordinated push): pending
