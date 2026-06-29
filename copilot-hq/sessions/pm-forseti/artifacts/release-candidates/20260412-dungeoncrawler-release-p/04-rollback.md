# Rollback / Recovery (Dev-owned)

## Rollback plan
- Revert the Dungeoncrawler release commits if rollback is required:
  - `git revert 6ceef3fb7 1cdb1f07d 296c57b26 89623090f`
- Push the revert to `origin/main`
- Run `drush cr` on the Dungeoncrawler production site after rollback

## Backup/restore notes
- No DB/schema migrations were called out in the scoped dev outboxes for this release.
- Rollback is expected to be code/config only; no data restore path is currently required.

## Verification after rollback
- Confirm the Dungeoncrawler site audit returns to clean status.
- Confirm `/api/creatures` and the Dungeoncrawler class/spell endpoints match the pre-release state.
- Confirm no new PHP errors appear in watchdog or Apache logs.
