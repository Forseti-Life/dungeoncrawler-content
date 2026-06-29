# Release Change List: 20260409-forseti-release-j

- Release ID: 20260409-forseti-release-j
- Site: forseti.life
- PM: pm-forseti
- Activated: 2026-04-09T23:54Z
- Features in scope: 3

## Features

| Feature ID | Module | Priority | Description | ROI |
|---|---|---|---|---|
| forseti-jobhunter-hook-install-fix | job_hunter | P1 (bugfix) | Add missing `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` calls to `hook_install()` — fixes fresh install failure (LOW-2 from release-h code review) | 8 |
| forseti-jobhunter-return-to-open-redirect | job_hunter | P2 (security) | Fix `return_to` parameter validation to reject protocol-relative URLs (`//evil.com`) — recurring LOW-1 from code reviews | 10 |
| forseti-agent-tracker-payload-size-limit | copilot_agent_tracker | P2 (security) | Add 64 KB payload size check before `json_decode` in `ApiController.php`, return HTTP 413 on violation (CAT R6) | 6 |

## BA source

- Code review LOW findings from release-h (`sessions/agent-code-review/outbox/20260409-code-review-forseti.life-20260409-forseti-release-h.md`)
- `features/forseti-copilot-agent-tracker/ba-refactor-recommendations.md` (CAT R6)

## Backlog note

All 39 previously tracked forseti features are shipped. All BA inventory items (JH-R1, JH-R2, JH-R3, JH-R4, JH-R5, AI-R1, AI-R2, CAT R1-R5) are addressed. Release-j scope is drawn from code review LOW findings and the last unaddressed BA item (CAT R6). CEO to provide new roadmap input for release-k+ when available.

## Gate status

- Gate 0 (scope activate): ✅ Complete (2026-04-09T23:54Z)
- Gate 1 (dev impl): pending (dev-forseti inbox items dispatched)
- Gate 2 (QA verify): pending
- Gate 4 (coordinated push): pending (waiting on DC release-g wizard completion)
