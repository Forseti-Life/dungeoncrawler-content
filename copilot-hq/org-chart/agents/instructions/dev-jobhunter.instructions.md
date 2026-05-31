# Agent Instructions: dev-jobhunter

## Authority
This file is owned by the `dev-jobhunter` seat.

## Owned file scope (source of truth)
### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- sessions/dev-jobhunter/**
- org-chart/agents/instructions/dev-jobhunter.instructions.md
- features/*/02-implementation-notes.md (JobHunter-related)

## Target repos
- Primary: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/`
- CLI scripts: `/home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-*.{sh,php}`
- Playwright bridge: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/playwright/`

## Scope: JobHunter Custom Module & Automation

The **job_hunter** module is a specialized Drupal subsystem for job application automation. Development encompasses:

1. **Core Module (`job_hunter/`):**
   - Job data models (companies, positions, saved jobs, applications)
   - User profile management and completeness validation
   - Application submission workflow and status tracking

2. **Automation Subsystem (CIO - Candidate Intelligence Optimizer):**
   - Drush command interface (`CioAutoApplyCommands.php`)
   - Application queue worker and submission service
   - Playwright-based ATS bridge for platform-specific application submission
   - CLI bootstrap runners (avoid Drush environment constraints)

3. **Processes & Flows:**
   - Candidate discovery and saved job management
   - Automated application submission via CIO command
   - Queue-based submission with retry/manual resume capability
   - Platform-specific form filling and submission (Greenhouse, LinkedIn, etc.)

## Key Files & Architecture

### Module Core
- `src/Service/ApplicationSubmissionService.php` — orchestrates submission prerequisites, profile completeness checks, configurable threshold
- `src/Service/ApplicationSubmitterQueueWorker.php` — processes queued submissions, retries, status updates
- `src/Commands/CioAutoApplyCommands.php` — Drush command entry points for CIO automation
- `drush.services.yml` — command registration and dependency injection
- `src/Hooks/Hooks.php` — schema definitions, entity hooks, cron integration

### Automation Tooling (CLI)
- `/sites/forseti/scripts/jobhunter-cio-auto-apply.php` — Drupal bootstrap runner; loads candidates, queues submissions, processes queue, returns JSON summary
- `/sites/forseti/scripts/run_job_hunter_cio_auto_apply.sh` — lock-based wrapper for cron execution; log fallback to `/tmp` for permission-limited environments
- `/sites/forseti/scripts/jobhunter-cio-growth-loop.sh` — continuous KPI growth loop; supports configurable intervals, max rounds, submitted-total trending

### ATS Bridge
- `playwright/apply.js` — entry point; dispatches to platform handlers
- `playwright/platforms/greenhouse.js` — Greenhouse ATS job application handler
- Platform handlers must return `{ success: true, outcome: 'submitted', confirmation_reference: string }` for successful submissions

### Schema & Configuration
- `job_hunter_companies.yml` — jobs data
- `job_hunter_positions.yml` — position listings
- `job_hunter_saved_jobs` table — user-saved opportunities (with platform metadata)
- `job_hunter_applications` table — submission history and status tracking
- Settings: `job_hunter.settings.application_min_profile_completeness` (default: 70)

## Development Tasks — How to Handle

### Starting a Task

1. **Read the issue/feature brief** — check `features/forseti-jobhunter-*/01-acceptance-criteria.md` if groomed.
2. **Impact analysis:**
   - Does it modify the submission flow? → affects all active CIO runs
   - Does it change schema (profile fields, threshold)? → must update config defaults, migration hooks
   - Does it touch the Playwright bridge? → test against both local mock ATS and live platform
3. **Pre-implementation checklist:**
   - [ ] Verify target database/test schema has all required columns (check `jobhunter_applications.archived`, `jobhunter_saved_jobs.archived`, etc.)
   - [ ] If adding new routes: update `qa-permissions.json` before QA audit runs
   - [ ] If modifying submission thresholds: test with users at boundary conditions (profile 65%, 70%, 75%)
   - [ ] If touching the bridge: ensure Playwright dependencies are locked (`package.json` versions pinned)

### CIO Command Development

The `CioAutoApplyCommands` class handles candidate discovery and submission queuing:

```php
// Entry: drush jhtr:cio-candidates --uid=<uid> --limit=N --rounds=R --retry-manual
// or: php /sites/forseti/scripts/jobhunter-cio-auto-apply.php --uid=<uid> --limit=N --rounds=R --queue-time-limit=180
```

**Common patterns:**
- Load candidates from saved jobs matching CIO criteria
- Queue submissions with optional retry strategy
- Poll queue worker to process submissions in-process
- Return structured result (candidates found, submitted, failed, KPI delta)

**Defensive coding rules:**
- Query `jobhunter_saved_jobs` with schema-aware `SELECT COUNT(*) of archived` guard (field may not exist in all migrations)
- Check profile completeness against configurable threshold; never hardcode 90%
- Log all submission outcomes to watchdog with `severity = INFO` (CIO runs generate 10-50 log lines per session)
- Return JSON summary with `submitted_total_for_user` for KPI tracking

### Testing

**Local mock ATS:**
```bash
cd sites/forseti/web/modules/custom/job_hunter/playwright
node apply.js --mode=test-greenhouse-mock
```
Expected output: `{ success: true, outcome: 'submitted', confirmation_reference: 'TEST-12345' }`

**Full pipeline (Drupal bootstrap):**
```bash
php /sites/forseti/scripts/jobhunter-cio-auto-apply.php --uid=1 --limit=5 --rounds=1 --queue-time-limit=180
```
Expected: JSON with `{ submitted_count, queued_count, failed_count, submitted_total_for_user }`

**Verification after changes:**
1. Run suite: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti` (all jobhunter routes)
2. Clear cache: `drush --uri=https://forseti.life cr`
3. Run growth loop locally: `cd sites/forseti && ./scripts/run_job_hunter_cio_auto_apply.sh` (single round)
4. Check logs: `grep "Job hunter" ~/.local/share/drupal-watchdog.log | tail -20`

## Known Issues & Patterns

- **Schema compatibility:** Not all environments have `jobhunter_saved_jobs.archived` column. Use defensive SELECT with CHECK for column existence.
- **Drush environment drift:** CLI bootstrap runner (`jobhunter-cio-auto-apply.php`) is more reliable than Drush commands in some contexts; prefer it for automatable workflows.
- **Profile completeness gate:** Previously hardcoded at 90%; now configurable via `job_hunter.settings.application_min_profile_completeness` with 70 fallback. Test boundary cases.
- **Queue processing timing:** Large batches (limit > 50) may take 3-5 minutes to process; script timeout should be >= 180s.
- **Playwright version lock:** Keep `playwright` npm package aligned with stable release; beta versions may have platform compatibility issues.

## Handoff to QA

**Pre-QA checklist:**
- [ ] All new routes registered in `/sites/forseti/config/sync` routing YAML
- [ ] Database schema changes in `hook_schema()` AND migration hooks
- [ ] New routes added to `qa-permissions.json` with correct permission matrix
- [ ] Watchdog logs verified for new features (no warnings for CIO runs)
- [ ] Growth loop KPI (`submitted_total_for_user`) validates increase over repeated runs

Notify `qa-jobhunter` with:
- Feature ID and brief
- New routes (if any) with expected permission matrix
- Expected CIO behavior (candidate count, submission targets, timeout budgets)
- Any schema changes or config defaults modified

## CLI Tools Reference (Shared Across Seats)

**CIO Automation:**
- `/home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-auto-apply.php` — Single round
- `/home/ubuntu/forseti.life/sites/forseti/scripts/run_job_hunter_cio_auto_apply.sh` — Wrapper with lock/logging
- `/home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-growth-loop.sh` — Continuous loop

**Example usage:**
```bash
# Single run
JOBHUNTER_UID=1 JOBHUNTER_LIMIT=10 JOBHUNTER_ROUNDS=2 JOBHUNTER_QUEUE_TIME_LIMIT=180 \
  /home/ubuntu/forseti.life/sites/forseti/scripts/run_job_hunter_cio_auto_apply.sh

# Growth loop (infinite until interrupted or MAX_RUNS reached)
INTERVAL_SECONDS=300 JOBHUNTER_UID=1 JOBHUNTER_LIMIT=10 JOBHUNTER_ROUNDS=2 MAX_RUNS=0 \
  /home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-growth-loop.sh
```

Check KPI trend: `tail -20 /tmp/jobhunter_growth.log | grep 'submitted_total'`
