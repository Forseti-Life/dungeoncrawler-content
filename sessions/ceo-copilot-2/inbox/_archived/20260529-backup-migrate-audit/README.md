# Audit Backup and Migrate across live Drupal sites

- Agent: ceo-copilot-2
- Requested-by: Board
- Requested-at: 2026-05-29T21:07:54+00:00
- Source: Board command

## Issue

Audit the **Backup and Migrate** module on each live Drupal site currently under CEO operational ownership and confirm:

- the module is installed and enabled
- backup destinations are configured
- backup schedules exist for expected cadences such as daily and weekly
- the effective configuration is suitable for live operations rather than half-configured or dormant

Current live sites in scope:

1. `forseti.life` (`/var/www/html/forseti`)
2. `dungeoncrawler` (`/var/www/html/dungeoncrawler`)

## Required outcome

Produce a live-configuration audit that states, per site:

1. whether `backup_migrate` is enabled
2. which destinations, sources, and schedules exist
3. whether daily, weekly, and any additional recurring backup cadence is configured
4. whether cron is running often enough for scheduled backups to execute
5. what gaps, risks, or remediation items remain

## Acceptance criteria

- Both live Drupal sites are inspected directly from their authoritative docroots.
- The audit identifies the actual Backup and Migrate config entities present on each site.
- The audit confirms or rejects the existence of daily and weekly schedules on each site.
- The audit records any missing destinations, missing schedules, disabled schedules, or stale cron state.
- Any gap is turned into an explicit remediation recommendation instead of assumed acceptable.

## Verification

- Drush output or direct config inspection confirms module enablement and live config entities for each site.
- The resulting audit can answer, for each site, whether scheduled backups are truly configured and operational.

## Notes

- Use the live docroots, not repo-side hybrid checkouts, as the source of truth.
- Do not infer schedule coverage from code defaults; confirm the active stored config/state.

## Status

- Phase 1 complete: inbox item created and scope locked.
- Phase 2 complete: live audit executed directly against both production docroots.
- Phase 3 complete: root-cause check performed for the reported "not generating" condition.
- Phase 4 complete: host-level cron wiring verified.
- Phase 5 complete: last-30-days backup artifact generation verified from live destinations.
- Phase 6 complete: S3/offsite visibility path audited from active config and logs.
- Phase 7 complete: Drupal console destination-listing failure traced to path-format mismatch.
- Phase 8 complete: runtime Backup and Migrate listing logic patched and verified on both live sites.
- Phase 9 complete: S3 bucket ingestion path traced and compared against live backup generation.

## Audit findings

Audit timestamp: `2026-05-29T21:09Z`

### Site: forseti.life

- Module status: `backup_migrate` is installed and enabled.
- Active destinations:
  - `daily_local_backups` -> `/var/backups/forseti/daily/`
  - `weekly_local_backup` -> `/var/backups/forseti/weekly`
  - `private_files` -> `/var/private/forseti/backup_migrate`
- Active sources:
  - `default_db`
  - `entire_site` (present but not scheduled)
  - `private_files`
  - `public_files`
- Active schedules:
  - `daily_backup` -> every `86400` seconds, enabled, keep `7`, source `default_db`, destination `daily_local_backups`
  - `daily_schedule` -> every `86400` seconds, enabled, keep `14`, source `default_db`, destination `private_files`
  - `weekly_backup` -> every `604800` seconds, enabled, keep `20`, source `default_db`, destination `weekly_local_backup`
  - `monthly_schedule` -> every `2592000` seconds, enabled, keep unlimited, source `default_db`, destination `private_files`
- Evidence of execution:
  - Drupal cron last ran at `2026-05-29T21:00:01Z`
  - `backup_migrate.schedule.last_run` shows:
    - `daily_backup` at `2026-05-29T21:00:01Z`
    - `daily_schedule` at `2026-05-29T15:00:01Z`
    - `weekly_backup` at `2026-05-25T15:00:01Z`
    - `monthly_schedule` at `2026-05-11T13:45:23Z`
  - Backup files exist and are current in all three configured destinations.
- Audit conclusion:
  - **Configured and operational** for daily, weekly, and monthly database backups.
  - There are **two daily database schedules** writing to different destinations. This is functional but duplicates work.
  - `weekly_backup` is labeled "Weekly Full Site Backup" but its configured source is `default_db`, so it is **not** a full-site backup.

### Site: dungeoncrawler

- Module status: `backup_migrate` is installed and enabled.
- Active destinations:
  - `private_files` -> `/var/private/dungeoncrawler/backup_migrate`
- Active sources:
  - `default_db`
  - `entire_site` (present but not scheduled)
  - `private_files`
  - `public_files`
- Active schedules:
  - `daily_schedule` -> every `86400` seconds, enabled, keep `14`, source `default_db`, destination `private_files`
  - `monthly_schedule` -> every `2592000` seconds, enabled, keep unlimited, source `default_db`, destination `private_files`
- Missing active schedule:
  - **No active weekly schedule exists**
- Evidence of execution:
  - Drupal cron last ran at `2026-05-29T21:00:01Z`
  - `backup_migrate.schedule.last_run` shows:
    - `daily_schedule` at `2026-05-29T00:00:01Z`
    - `monthly_schedule` at `2026-05-11T13:44:54Z`
    - stale entries for removed schedules:
      - `daily_backup` at `2026-05-03T15:00:01Z`
      - `weekly_backup` at `2026-04-27T14:51:57Z`
  - Current backup files exist in `/var/private/dungeoncrawler/backup_migrate`, including backups from `2026-05-29`.
  - Old artifact directories still exist at `/var/backups/dungeoncrawler/daily` and `/var/backups/dungeoncrawler/weekly`, but they are not backed by active config and appear to be legacy leftovers.
- Audit conclusion:
  - **Configured and operational for daily and monthly database backups only.**
  - **Not configured for weekly backups** in the active Backup and Migrate config.
  - Active config is inconsistent with residual state and legacy filesystem artifacts from retired schedules.

## Risks and remediation

1. `forseti.life`: decide whether dual daily schedules are intentional. If not, remove one to reduce duplicate backup generation.
2. `forseti.life`: rename `weekly_backup` or change its source to `entire_site` if a true full-site weekly backup is required.
3. `dungeoncrawler`: create and enable an explicit weekly schedule if weekly coverage is required by policy.
4. `dungeoncrawler`: clean up stale `backup_migrate.schedule.last_run` references and retire legacy `/var/backups/dungeoncrawler/{daily,weekly}` artifacts if they are no longer part of the backup plan.
5. Both sites: current schedules cover database backups; neither site currently has an active scheduled `entire_site` backup.

## Root-cause analysis: why backups appeared not to be generating

Board clarification: `default_db` is the intended backup source.

### Confirmed behavior

- `forseti.life` **is generating backups**. Its schedules are enabled, cron is running, and the next-run math is normal:
  - `daily_backup` last ran at `2026-05-29T21:00:01Z`, next due `2026-05-30T21:00:01Z`
  - `daily_schedule` last ran at `2026-05-29T15:00:01Z`, next due `2026-05-30T15:00:01Z`
  - `weekly_backup` last ran at `2026-05-25T15:00:01Z`, next due `2026-06-01T15:00:01Z`
- `dungeoncrawler` **is generating its active daily/monthly backups**. Its active daily schedule last ran at `2026-05-29T00:00:01Z` and is not due again until `2026-05-30T00:00:01Z`.

### Actual root cause

There is **no module failure preventing backups from being written**. The observed "not generating" condition comes from configuration reality:

1. `dungeoncrawler` no longer has an active `weekly_backup` schedule, so **weekly backups cannot generate**.
2. `dungeoncrawler` no longer has active directory destinations under `/var/backups/dungeoncrawler/daily` or `/var/backups/dungeoncrawler/weekly`, so those legacy locations **will not receive new backups**.
3. The only active destination on `dungeoncrawler` is `private_files` (`/var/private/dungeoncrawler/backup_migrate`), and current backups are being written there.
4. Because schedule execution is based on `last_run + period`, an enabled daily schedule will only fire once per 24 hours. It will not write another backup on every cron run between those times.

### Bottom line

- If the expectation was **"weekly backups should exist on dungeoncrawler"**, the root cause is **missing active weekly schedule config**.
- If the expectation was **"new files should keep appearing in the old `/var/backups/dungeoncrawler/...` directories"**, the root cause is **those destinations are no longer active**.
- If the expectation was **"the daily backup should run again later the same day"**, the root cause is **normal schedule timing**, not failure.

## Cron audit

- Both sites have **host-level root crontab entries** that run Drupal cron every 3 hours:
  - `dungeoncrawler`: `0 */3 * * * ... drush --uri=https://dungeoncrawler.forseti.life cron`
  - `forseti`: `0 */3 * * * ... drush --uri=https://forseti.life cron`
- Both cron entries use `flock` lockfiles, which is good and prevents overlap.
- Both sites have `automated_cron.settings: interval = 0`, which is correct because cron is being driven externally by the host and not by web traffic.
- Both sites show `system.cron_last = 1780088401` (`2026-05-29T21:00:01Z`), matching the host cron cadence.
- Additional monthly forced backup crons exist on the 1st of the month:
  - `dungeoncrawler` at `25 2 1 * *`
  - `forseti` at `30 2 1 * *`

### Cron conclusion

The cron jobs for `forseti` and `dungeoncrawler` are **set up properly** for Drupal cron execution. The missing weekly backup on `dungeoncrawler` is **not** a cron wiring problem; it is a **missing active weekly Backup and Migrate schedule** problem.

## Last-30-days generation verification

Verification window: `2026-04-29T00:00:00Z` through `2026-05-29T21:15Z`

### forseti.life

- `/var/backups/forseti/daily` contains continuous daily backup artifacts from `2026-04-29` through `2026-05-29`.
- `/var/backups/forseti/weekly` contains weekly backup artifacts on:
  - `2026-05-04`
  - `2026-05-11`
  - `2026-05-18`
  - `2026-05-25`
- `/var/private/forseti/backup_migrate` contains recurring database backups from `2026-05-11` through `2026-05-29`, consistent with the active daily and monthly schedules.
- Counts in the window:
  - daily destination: `62` files (`31` backups + `31` `.info`)
  - weekly destination: `8` files (`4` backups + `4` `.info`)
  - private-files destination: `44` files (`22` backups + `22` `.info`)
- Conclusion: **verified running and generating backups throughout the last month**.

### dungeoncrawler

- `/var/private/dungeoncrawler/backup_migrate` contains recurring active backup artifacts from `2026-05-11` through `2026-05-29`.
- Recent active daily generation is visible on:
  - `2026-05-24`
  - `2026-05-25`
  - `2026-05-26`
  - `2026-05-27`
  - `2026-05-28`
  - `2026-05-29`
- Counts in the window:
  - active private-files destination: `44` files (`22` backups + `22` `.info`)
  - legacy `/var/backups/dungeoncrawler/daily`: `10` files (`5` backups + `5` `.info`), stopping at `2026-05-03`
  - legacy `/var/backups/dungeoncrawler/weekly`: `0` files in the window
- State corroboration:
  - active `daily_schedule` last run: `2026-05-29T00:00:01Z`
  - stale removed `daily_backup` last run: `2026-05-03T15:00:01Z`
  - stale removed `weekly_backup` last run: `2026-04-27T14:51:57Z`
- Conclusion: **verified running and generating backups in the active private-files destination**, but **weekly generation has not occurred in the last month** because there is no active weekly schedule.

## S3 and Drupal console root cause

### Why the backups are not showing up in S3

On both live sites, the **active Backup and Migrate configuration contains no S3 destination at all**.

- `forseti.life` active destinations:
  - `daily_local_backups` -> type `Directory`
  - `private_files` -> type `Directory`
  - `weekly_local_backup` -> type `Directory`
- `dungeoncrawler` active destinations:
  - `private_files` -> type `Directory`

There are **no active `backup_migrate` config entities referencing**:

- `s3`
- `aws`
- `amazon`
- `bucket`
- `amazonaws.com`

There are also **no enabled S3/AWS backup integration modules** on either live site. The only relevant enabled module found was `backup_migrate` itself.

### Why they are not showing up in the Drupal console

The Drupal Backup and Migrate UI only reflects **active configured destinations and the backups stored in them**.

Because the live config only defines **local Directory destinations**, the UI can only show backups from:

- `/var/backups/forseti/daily`
- `/var/backups/forseti/weekly`
- `/var/private/forseti/backup_migrate`
- `/var/private/dungeoncrawler/backup_migrate`

It will **not** show:

1. an S3 bucket that is not represented by an active destination plugin/config entity
2. legacy directories that are no longer active destinations on `dungeoncrawler`
3. uploads that never occurred because no S3 destination or transfer step exists in the live configuration

### Bottom line

The reason the backups are missing from both **S3** and the **Drupal console** is the same root cause:

- the live sites are configured only for **local directory backups**
- there is **no active S3 destination**
- there is **no active upload/transfer configuration to S3**
- therefore nothing is being sent to S3, and the Drupal UI has no S3 destination to display

## Why the Drupal console says "There are no backups in this destination"

This is a **separate UI/listing issue** from the S3 problem.

The Backup and Migrate destination labeled **Private Files Directory** is configured with a plain filesystem path:

- `forseti`: `/var/private/forseti/backup_migrate`
- `dungeoncrawler`: `/var/private/dungeoncrawler/backup_migrate`

But the destination listing code in Backup and Migrate checks the configured destination path as though it should be a **stream-wrapper path**. In `DirectoryDestination::getAllFileNames()` it does:

1. read the destination `directory`
2. ask Drupal for the stream scheme
3. if the scheme is invalid, emit:
   - `Your :// stream is not configured.`
4. return an empty file list

For an absolute path like `/var/private/forseti/backup_migrate`, the stream scheme is empty, so the module emits exactly the warning you saw and then renders:

- `There are no backups in this destination.`

### Important distinction

- **Backup writing is working** because the save path is a real writable directory outside the web root.
- **Backup listing in the Drupal UI is broken** because the module's listing logic rejects the absolute-path destination as having no valid stream scheme.

### Practical root cause

The destination is configured in a way that is valid for writing files but incompatible with this part of the module's browser UI.

If you want the Drupal console to list these backups correctly, the destination needs to be represented in a form the module can browse consistently, typically via a configured stream-wrapper path such as `private://backup_migrate`, rather than a bare absolute filesystem path.

## Fix applied

Applied a surgical runtime patch to the live Backup and Migrate module on both sites:

- `web/modules/contrib/backup_migrate/src/Core/Destination/DirectoryDestination.php`

Change made:

- allow **absolute filesystem paths** to be listed as valid directory destinations
- continue warning only when a **non-empty stream scheme** is present and invalid

Behavioral result:

- the module no longer treats `/var/private/...` destinations as an invalid `://` stream
- the Drupal Backup and Migrate UI can now enumerate backups stored in those absolute-path destinations

## Fix verification

Post-patch verification from the live destination objects:

- `forseti` `private_files` destination now lists `22` backups
- `dungeoncrawler` `private_files` destination now lists `23` backups

Example listed files after the fix:

- `forseti`: `backup-2026-05-29T15-00-02.mysql.gz`
- `dungeoncrawler`: `backup-2026-05-29T00-00-25.mysql.gz`

Drupal caches were rebuilt on both live sites after the patch.

## How `drupalbackupsforseti` gets its files

The S3 bucket is **not** populated by Drupal or Backup and Migrate directly.

It is populated by **root crontab shell jobs** using the AWS CLI:

1. **Database backup upload job** — every 3 hours at minute `10`
   - tag: `drupalbackupsforseti_sync`
   - logic:
     - loop over site names
     - scan `/var/private/<site>/backup_migrate`
     - read each `.info` file
     - keep only files where `bam_scheduleid = "monthly_schedule"`
     - upload the `.info` file and its paired backup file to:
       - `s3://drupalbackupsforseti/<site>/...`

2. **Public files sync job** — daily at `01:40`
   - tag: `drupalbackupsforseti_files_sync`
   - logic:
     - `aws s3 sync` site files directories into:
       - `s3://drupalbackupsforseti/dungeoncrawler/files/`
       - `s3://drupalbackupsforseti/forseti/files/`

## What is wrong with the current design

The S3 database-backup uploader is working exactly as written, but the implementation is **far narrower than the intended policy**.

### Confirmed behavior

- AWS CLI is installed and authenticated as:
  - `arn:aws:iam::647731524551:user/forseti`
- The bucket `s3://drupalbackupsforseti/` is accessible.
- A manual run of the current cron upload logic succeeds.

### Actual limitation

The upload job only ships backups whose `.info` metadata contains:

- `bam_scheduleid = "monthly_schedule"`

That means it **does not upload**:

- daily backups
- weekly backups
- ad-hoc/manual backups
- any backup stored outside `/var/private/<site>/backup_migrate`

### Evidence

Local private backup directories contain many daily backups across the month, but the S3 bucket contains only the monthly-schedule database backups:

- `forseti` local private backups:
  - many `daily_schedule` backups from `2026-05-11` through `2026-05-29`
  - monthly-schedule backups on `2026-05-11`
- `forseti` S3 database backups:
  - `backup-2026-05-11T12-53-30.mysql.gz`
  - `backup-2026-05-11T13-45-24.mysql.gz`

- `dungeoncrawler` local private backups:
  - many `daily_schedule` backups from `2026-05-11` through `2026-05-29`
  - monthly-schedule backups on `2026-05-11`
- `dungeoncrawler` S3 database backups:
  - `backup-2026-04-27T15-28-43.mysql.gz`
  - `backup-2026-05-11T12-53-06.mysql.gz`
  - `backup-2026-05-11T13-44-59.mysql.gz`

## Root cause

If the expectation is **"all Drupal backups should be dumped to `drupalbackupsforseti`"**, the current implementation does **not** meet that requirement.

The root cause is that the bucket-upload cron is hard-coded to copy only **monthly_schedule** database backups, rather than all required backup classes.

## Ongoing monitoring directive

Keep this inbox item **open** and reuse it whenever CEO comes online over the next few months.

At each CEO check-in, verify:

1. new monthly `default_db` backups were generated locally for both sites
2. the corresponding monthly backup artifacts appear in `s3://drupalbackupsforseti/<site>/`
3. Drupal cron last-run timestamps remain fresh
4. the Backup and Migrate UI still lists local backups correctly after the runtime patch
5. no new drift appears between local monthly backups and S3 monthly backups

### Current monitoring baseline

- Local monthly database backups exist for both `forseti` and `dungeoncrawler`
- S3 bucket access is healthy
- S3 ingestion path is host cron + AWS CLI, monthly schedule only
- Drupal UI listing fix is applied and verified on both sites

### Hold-open instruction

Do **not** archive this inbox item yet. Treat it as an active operational monitoring item until the monthly backup/S3 behavior has remained stable for the next few months.
