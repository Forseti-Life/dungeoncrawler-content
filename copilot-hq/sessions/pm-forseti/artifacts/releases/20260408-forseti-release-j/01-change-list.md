# Change List: 20260408-forseti-release-j

- Release: 20260408-forseti-release-j
- Status: in_progress
- PM: pm-forseti
- Date opened: 2026-04-08

## Features in scope

| Feature ID | Priority | Status | Summary |
|---|---|---|---|
| forseti-jobhunter-profile-form-db-extraction | P2 | in_progress | Extract 3 direct DB calls from UserProfileForm into service/repository layer |
| forseti-agent-tracker-dashboard-controller-db-extraction | P2 | in_progress | Extract 20 direct DB calls from DashboardController into DashboardRepository |
| forseti-jobhunter-resume-tailoring-queue-hardening | P3 | in_progress | Harden ResumeTailoringWorker with retry backoff and dead-letter logging |

## Notes
- All 3 features activated via pm-scope-activate.sh
- Dev inbox items dispatched; QA suite activation items queued
- Predecessor: 20260408-forseti-release-i (CSRF fix + 2 refactors, shipped)
