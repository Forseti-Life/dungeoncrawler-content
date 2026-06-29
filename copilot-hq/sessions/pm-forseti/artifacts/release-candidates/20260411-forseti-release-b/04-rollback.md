# Rollback / Recovery (Dev-owned)

## Rollback plan
- Production is deployed via symlinks; no rsync/copy needed.
- If rollback is required: `git revert <commit>` on commits 0f772acf0, eb203f97f, c95346b3d, then `git push origin main`.
- Run `drush cr` on the forseti production site after rollback to flush caches.
- Rollback is isolated to two modules (job_hunter, forseti_agent_tracker); no DB schema migrations were introduced.

## Backup/restore notes
- No new DB schema migrations. No data destructive operations. Rollback is safe without a DB restore.
- deadline-tracker: blank→NULL date handling means existing rows are not affected by rollback.

## Verification after rollback
- Confirm deadline-tracker routes return 403/404 as expected (pre-feature state).
- Confirm release panel shows static placeholder content (pre-feature state).
- Run `drush cr` and confirm no PHP errors in watchdog.
