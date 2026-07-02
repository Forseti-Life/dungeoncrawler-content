# Dungeon Crawler Tester Module

**Module Name**: dungeoncrawler_tester  
**Purpose**: Holds the testing harness and full functional test suite for the Dungeon Crawler content module.  
**Depends on**: `dungeoncrawler_content`

## What’s inside
- PHPUnit configuration tuned for Drupal functional tests.
- Comprehensive functional test suite (routes + controllers).
- Testing module README with run commands and grouping.
- Playwright UI testing suite (hexmap and workflow checks) located in `testing/playwright/`.
- **Testing Dashboard** - A web-based dashboard for quick access to test documentation, commands, and CI status.

## Documentation Verification (2026-02-18)

- `/dungeoncrawler/testing/documentation/*` pages are controller-rendered (`TestingDashboardDocsController`) and are the live source for documentation-home content.
- Stage-failure automation now creates/reuses local tracker rows in repository-root `Issues.md`; GitHub issue mutation is intentionally scoped to `/dungeoncrawler/testing/import-open-issues`.
- Process-flow docs were updated to remove stale queue-worker GitHub assignment timing assumptions and align with local tracker state sync.
- SDLC/Release flow docs are governance/inference guidance; they are not a fully enforced merge orchestrator in module runtime.

## Testing Dashboard

The testing dashboard provides a centralized location for developers to:
- Access all test documentation and guides
- View and copy test commands for quick execution
- Monitor CI failures and testing-related issues
- Review release testing stagegates

**Access the dashboard:**
- URL: `/dungeoncrawler/testing`
- Permission required: `administer site configuration`
- Menu location: Reports > Dungeon Crawler Testing Dashboard
- Navigation entry: tester links appear under the `Testing` menu item in Main navigation.

The dashboard includes:
- **Test Documentation**: Consolidated structure with Getting Started, Execution Playbook, and Failure Triage workflow pages
- **Documentation Home**: `DOCUMENTATION_HOME.md` is the canonical index for tester documentation.
- **Quick Test Commands**: Copy/paste commands for running different test suites
- **Release Testing Stagegates**: Testing workflow and checklist
- **Local issue tracking mode**: Tester failure automation now writes issues to repository-root `Issues.md` instead of creating GitHub issues directly.
- **GitHub integration scope**: GitHub issue API/CLI integration is restricted to `/dungeoncrawler/testing/import-open-issues` for controlled sync from local tracker rows.
- **Issues.md mutation boundary**: Import/reconcile PHP automation is responsible for removing matching Open local rows only after GitHub open-state confirmation; Copilot/LLM issue-work agents must not directly edit `Issues.md` as part of issue execution.
- **Main navigation integration**: Tester navigation now lives under the existing `main` menu (`Testing`), so tester routes are incorporated into the primary site navigation.
- **Documentation submenu organization**: Tester documentation links are grouped under `Testing` → `Documentation` → `Documentation Home`, and `Documentation Home` is expandable to reveal all documentation pages.
- **Issue/PR Report Workflow**: `/dungeoncrawler/testing/import-open-issues/issue-pr-report` now documents process and decision logic for low-to-high PR triage, no-op/superseded close decisions, and verification expectations.
- **Issue/PR local-cache reference**: The issue/PR report now includes an explicit local cache management callout for repository-root `Issues.md` and a direct link to `/dungeoncrawler/testing/import-open-issues`.
- **Issue/PR import status visibility**: The issue/PR report metadata now includes a “Last local import run” line (time/repo/handled-created-skipped-failed/dry-run) sourced from importer-run state.
- **Import page child-flow navigation**: The import-open-issues page now includes a "View Issue/PR Report →" link to make it clear that the issue/PR report is a child flow in the import workflow hierarchy.
- **Open issue import page**: `/dungeoncrawler/testing/import-open-issues` now provides a dashboard form that imports Open rows from `Issues.md` into GitHub (batchable, delay selectable as 5/30/180 seconds with default 5, and Copilot assignment-aware).
- **Import subprocess execution model**: `Run import batch` now launches a detached Drush worker subprocess (`dungeoncrawler_tester:import-open-issues-run`) and stores PID/log metadata in state for operator visibility and kill targeting.
- **Import kill behavior**: `Kill Active Import` now sets cooperative abort state and targets the tracked subprocess PID directly (plus process-pattern fallback), so long-running server-side imports are killable without relying on browser request lifetime.
- **Import metrics summary**: `/dungeoncrawler/testing/import-open-issues` now displays top-of-page metrics for local and GitHub open issues, including local open count, oldest local open issue name, newest GitHub open issue name, and GitHub open issue count.
- **Import-page theme compliance**: `/dungeoncrawler/testing/import-open-issues` now applies centralized, page-scoped styling aligned to theme color variables (body/secondary/tertiary tokens) to avoid low-contrast text and keep card/log/control presentation consistent with dashboard/theme standards.
- **Import-page layout refactor**: `/dungeoncrawler/testing/import-open-issues` now uses a cohesive dashboard-aligned composition (header card, responsive metrics+reconcile top grid, dedicated import-runner form card, and unified form control spacing/typography) while preserving existing import/reconcile behavior.
- **Import workflow commentary**: `/dungeoncrawler/testing/import-open-issues` now includes a top-of-page `Workflow Context` section that explains the intended sequence (run regression/stage-gate tests from `/dungeoncrawler/testing` → log failures to repository-root `Issues.md` → import Open rows into GitHub for Copilot handling) and explicitly warns to respect GitHub throttling/rate limits with controlled batch runs.
- **Queue-style token centralization**: Shared queue/reconcile styling now resolves colors through theme-aware CSS variables (Bootstrap body/secondary/border/status tokens) in `css/queue-management.css` to reduce hard-coded color drift and improve contrast consistency across light theme variants.
- **Open issue import local close-out**: Import now removes matching Open `Issues.md` tracker row(s) after GitHub confirmation—both for newly created issues (`create` + issue fetch) and for already-existing open issues confirmed by tracker-ID title prefix match (`DCT-####` / `DCC-####`) in GitHub search results—instead of changing status to `Closed`.
- **Import write-permission prerequisite**: For local-row removal to succeed, repository-root `Issues.md` must be writable by the Drupal web process user (commonly `www-data` on Apache). Import now logs an explicit warning when file permissions block deletion.
- **Open issue import run status visibility**: The import page now shows whether an import/reconcile run is currently active (operation, repository, start time), and disables action buttons while a run is active.
- **GitHub source-of-truth reconcile action**: The import page now includes a reconcile action that compares local Open `Issues.md` rows with open GitHub issues and removes local Open rows already represented in GitHub by tracker ID (`DCT-####` title prefix).
- **Background reconcile live feed card**: `/dungeoncrawler/testing/import-open-issues` now includes a dedicated reconcile card with queue-management-style controls (run/refresh/refresh-logs, auto-refresh countdown, status/count pills), background tick processing, and a filtered live log feed (`all` / `github` / `deleted` / `warnings`) rendered on-page.
- **Background reconcile availability notice**: The reconcile card on `/dungeoncrawler/testing/import-open-issues` is currently labeled `In development. Do Not use this form.` to discourage operational use while work is in progress.
- **Background reconcile technical block**: Reconcile controls are disabled in the UI and reconcile `start/tick` AJAX endpoints now return a disabled/in-development response so the form cannot be used accidentally.
- **Import GitHub action logging visibility**: Import-page GitHub interactions (search hits, issue create success/failure, Copilot assignment outcomes, and local tracker close-out outcomes) are now written to `dungeoncrawler_tester` watchdog logs and included in the same live feed panel.
- **Docs link handling**: Documentation links resolve to internal Drupal documentation pages (no direct `.md` links); only the testing issues query links to GitHub.
- **Theme compliance**: Documentation pages render with the theme-standard Bootstrap layout (`container` + `row/col`) and `card card-dungeoncrawler` sections for visual consistency.
- **Unified menu source**: Tester-facing links are now managed in `dungeoncrawler_tester.links.menu.yml` under the `main` menu hierarchy.
- **POST route hardening**: Mutative tester AJAX routes require CSRF validation in routing requirements.
- **Stage command validation**: Drush stage-control commands now validate stage IDs against defined stage definitions before writing state.
- **Queue lock lease strategy**: Manual queue-run command now uses a batch-size-aware lock lease instead of a fixed 30-second window to reduce concurrent-run collisions.
- **Failure-text sanitization**: Dashboard stage-control failure reason/excerpt rendering now escapes dynamic state-derived strings before output in `#markup`/`#description` paths.
- **Payload decode hardening**: Queue/watchdog serialized payload reads now use safe decode helpers with `allowed_classes=false` and invalid-payload fallback handling.
- **Controller DI hardening**: Tester controllers now use injected cache/CSRF services instead of static `\Drupal::...` lookups for queue/dashboard AJAX settings and cache paths.
- **Dashboard route decomposition**: Documentation routes now resolve through `TestingDashboardDocsController`, and issue/PR report automation routes resolve through `TestingDashboardIssueAutomationController` to narrow controller surface areas while preserving route behavior.
- **Issue automation action extraction**: Routed issue-report automation actions (`issuePrReport`, bulk-close query AJAX, dead-value close AJAX) now execute in the focused issue automation controller; shared helper methods in the legacy controller were promoted to protected visibility to support incremental decomposition.
- **Monolith duplicate cleanup**: Legacy duplicate routed docs/issue action implementations were removed from `TestingDashboardController` after route + method migration, completing the decomposition pass while preserving shared helper coverage.
- **Monolith dead-code pruning**: Unused private docs/test-callout/roadmap/test-command helpers and stale constants/imports were removed from `TestingDashboardController` after route-method extraction to keep the legacy controller focused on active dashboard and shared helper responsibilities.
- **Shared cache TTL constant**: Issue automation controller now reuses the inherited dashboard cache TTL constant instead of maintaining a duplicate local constant.
- **Issue helper ownership transfer**: PR triage/mutation helper methods (`fetchPullRequestDetails`, PR blocker/next-step logic, issue-reference parsing, dead-value detection, and mutation wrapper) now live in `TestingDashboardIssueAutomationController`, reducing helper footprint in `TestingDashboardController`.
- **Bulk-query helper ownership transfer**: Issue report decision-logic rendering and bulk-close query definition/collection helpers now execute from `TestingDashboardIssueAutomationController`, further narrowing `TestingDashboardController` to core dashboard/shared infrastructure concerns.
- **Issue-report fetch helper transfer**: Issue/PR report data fetch and timeline-link helper methods now execute from `TestingDashboardIssueAutomationController`, removing remaining report-fetch ownership from `TestingDashboardController`.
- **Shared testing-label constant**: `TESTING_ISSUE_LABELS` is now shared from `TestingDashboardController` (protected constant) and reused by focused controllers, removing duplicate label-list definitions.
- **Legacy private-method pruning**: Unused monolithic private methods (`getLastRun`, `buildRunStatus`, `renderIssueList`) were removed from `TestingDashboardController` after extraction work to reduce dead surface area.
- **Issue-only request helper extraction**: `requestGitHubJson` now executes from `TestingDashboardIssueAutomationController`.
- **Route helper ownership transfer**: `safeRouteUrl` now executes in `TestingDashboardIssueAutomationController` (its only active consumer), and was removed from `TestingDashboardController`.
- **Issue timeout constant ownership**: `GITHUB_API_TIMEOUT` now lives in `TestingDashboardIssueAutomationController` (its only active consumer), and was removed from `TestingDashboardController`.
- **Issue date formatter ownership**: Date formatting for issue-report metadata now resolves in `TestingDashboardIssueAutomationController` via its own `create()` wiring, and the dependency was removed from `TestingDashboardController`.
- **Legacy import cleanup**: Removed an unused `ConfigFactoryInterface` import from `TestingDashboardController` during decomposition hardening.
- **Dashboard GitHub cache helper consolidation**: Repeated cache-read logic for dashboard GitHub summary methods now routes through a shared helper in `TestingDashboardController`, including reuse of cached empty-array results for open-testing-issue lookups.
- **Issue-report cache/payload helper consolidation**: Repeated issue-report cache reads now route through a shared helper in `TestingDashboardIssueAutomationController`, and response `items` extraction is normalized to avoid direct shape assumptions.
- **Issue-report context/data loader extraction**: `issuePrReport` and bulk-close AJAX now share a single `TestingDashboardIssueAutomationController` helper for GitHub context + open issue/PR payload setup to reduce duplicated action preamble logic.
- **Issue automation context normalization**: `TestingDashboardIssueAutomationController` now centralizes repository/token/token-candidate context shaping in a dedicated helper and reuses it in dead-value close/report flows.
- **Issue/PR close helper extraction**: Bulk-close and dead-value AJAX flows now reuse dedicated helpers for comment+close issue and PR mutation sequences, removing repeated mutation block logic.
- **Bulk-close outcome aggregation helper**: `runBulkCloseQueryAjax` now routes repeated PR/issue success/error counter updates through a shared helper to reduce duplicated result-accounting branches.
- **Bulk-close list-processing helper extraction**: `runBulkCloseQueryAjax` now delegates repeated issue-number and PR-number close loops to dedicated list helpers, keeping each query case focused on candidate selection.
- **JSON error-response normalization**: Repeated inline `JsonResponse` error payloads in issue automation AJAX handlers now route through a shared helper to keep error response shape single-sourced.
- **JSON request payload decode normalization**: Issue automation AJAX handlers now share a dedicated request-payload decode helper (`json_decode` + safe array fallback) to keep request parsing behavior consistent.
- **Admin permission gate normalization**: Issue automation AJAX handlers now share a dedicated admin-permission guard helper for consistent access-denied response handling.
- **GitHub token guard normalization**: Issue automation AJAX handlers now share dedicated token/token-candidate guard helpers for consistent “token not configured” error handling.
- **JSON success-response normalization**: Issue automation AJAX success payloads now route through a shared helper to keep success response shape and message handling single-sourced.
- **Open-issue number map normalization**: Repeated open-issue lookup-map construction now routes through a shared helper across issue report and bulk-close query paths.
- **Keyed-number list normalization**: Repeated keyed-set (`[number => TRUE]`) to int-list conversions now route through a shared helper in `TestingDashboardIssueAutomationController`.
- **Issue-report data accessor normalization**: Entry points now unpack `repo`/`token`/`token_candidates`/`issues`/`prs` via a shared report-data accessor helper instead of repeated per-key casting.
- **Positive-number extraction normalization**: Repeated issue/PR numeric identifier extraction and `<= 0` guard patterns now reuse a shared helper in `TestingDashboardIssueAutomationController`.
- **AJAX payload number parsing reuse**: Dead-value close payload/candidate numeric field extraction (`pr_number`, `issue_number`) now reuses the shared positive-number helper instead of inline casts.
- **Number-sort comparator normalization**: Issue/PR list sorting by numeric `number` now routes through a shared helper instead of repeated inline `usort` lambdas.
- **AJAX message constant normalization**: Repeated issue-automation AJAX error message strings are now single-sourced as controller constants to reduce drift.
- **AJAX status-code constant normalization**: Repeated issue-automation AJAX HTTP status literals now use shared controller constants for consistent response semantics.
- **GitHub URL builder normalization**: Repeated issue/PR/timeline API URL construction now routes through shared repository/issue/pull URL builder helpers in `TestingDashboardIssueAutomationController`.
- **Report token-missing response normalization**: Issue/PR report fetch methods now share a single helper for the “No GitHub token configured” response payload shape.
- **Report API-error response normalization**: Issue/PR report fetch methods now share a single helper for GitHub API error payload shaping (`items + error`).
- **Report success-result finalization normalization**: Issue/PR report fetch methods now share a helper for successful `items` result assembly + optional cache writes.
- **Repo-aware nav links**: Tester navigation links now build the GitHub issue queue link from configured repository context (`dungeoncrawler_tester.settings`/`ai_conversation.settings`/`TESTER_GITHUB_REPO`).
- **Repo-aware sign-off link**: Stage definitions now build the release sign-off defect link from configured repository context (`dungeoncrawler_tester.settings`/`ai_conversation.settings`/`TESTER_GITHUB_REPO`) instead of a hard-coded repository URL.
- **Filesystem diagnostics**: Queue worker now validates simpletest directory creation/writability and surfaces explicit failure diagnostics in run/state output when setup fails.
- **Temp-state isolation**: GitHub client cooldown/dedupe/lock files now include repository-scoped namespaces to reduce cross-site/process collisions on shared hosts.
- **Auto-enqueue serialization**: Stage auto-enqueue now uses a cross-process lock guard to prevent duplicate queueing under concurrent cron invocations.
- **Issue-sync efficiency/observability**: Stage issue sync now preloads open issues via a paginated bulk read, deduplicates fallback per-issue checks, and writes last-run diagnostics/fetch-failure details to state (`dungeoncrawler_tester.issue_sync_last`).
- **Robust logging**: Dashboard form now lazy-loads logger service to avoid cache-induced initialization errors during command submissions.
- **Serialization-safe DI**: Dashboard form lazy-loads all injected services (state, date formatter, stage definitions, queue, uuid) to survive form cache serialization.
- **Dashboard CSS maintenance**: Removed a duplicate `.stage-grid` selector block in `css/dashboard.css` to keep layout rules single-sourced and easier to maintain.

### Standard testing documentation structure

- `Getting Started` (`/dungeoncrawler/testing/documentation/getting-started`)
- `Test Execution Playbook` (`/dungeoncrawler/testing/documentation/execution-playbook`)
- `Failure Triage and Issue Workflow` (`/dungeoncrawler/testing/documentation/failure-triage`)
- `Automated Testing Process Flow` (`/dungeoncrawler/testing/documentation/process-flow`) - rendered process-flow page
- Source of truth: `PROCESS_FLOW.md` (canonical sync/async timing and blocking-gates documentation)
- Legacy documentation routes remain available as compatibility aliases and map to these consolidated pages.

### Local issue automation (failures)

- If a stage fails, the queue worker creates or reuses a local issue row in `Issues.md` and pauses the stage.
- Existing linked local issues are respected; duplicate rows are not created for the same stage/test-case title.
- Stage issue sync checks local issue status from `Issues.md` and can auto-resume stages when linked local rows are closed.
- Use `/dungeoncrawler/testing/import-open-issues` to synchronize local Open rows into GitHub.

### Getting a GitHub token (for issue creation)

1) Visit https://github.com/settings/tokens and create a **Fine-grained token** or **classic token** with scope `repo` (issue creation only requires `public_repo` on classic tokens).
2) Set the token in `/admin/config/development/dungeoncrawler-tester` (stored in private state, not config export) or export `TESTER_GITHUB_TOKEN` in the environment.
3) Set the repository to `keithaumiller/forseti.life` (default) or another `owner/repo`. If left blank, the system will use `keithaumiller/forseti.life`.

## Current review status
- First-pass review completed for inventory (unit + functional suites). Functional workflow test remains stubbed.
- Follow-up issues to be opened are staged in [issues_todo.md](../../../issues_todo.md) (workflow implementation, data-backed functional assertions, negative/authorization coverage, shared builders, and content-backed smoke tests).

## Running tests

**Quick Start:**
```bash
cd sites/dungeoncrawler
./tests/run-tests.sh
```

**Playwright UI tests (repository root):**
```bash
cd /home/keithaumiller/forseti.life
npm install
npx playwright install chromium
./testing/playwright/setup-auth.sh
node testing/playwright/test-character-creation.js http://localhost:8080 10000
node testing/playwright/test-hexmap.js http://localhost:8080 5000
```

For complete run instructions, test suites, groups, and examples, see **[tests/README.md](tests/README.md)** - the canonical testing guide.

**Quick tip**: Visit the Testing Dashboard at `/dungeoncrawler/testing` for a complete list of test commands with copy/paste functionality.

### Test Environment Setup

The test suite uses a custom bootstrap (`tests/bootstrap.php`) that ensures the `web/sites/simpletest` directory exists with proper permissions before running tests. This is required for Drupal's BrowserTestBase, which creates temporary test site directories during functional test execution.

If you encounter permission errors like "Failed to open 'sites/simpletest/XXXXXX/settings.php'", verify that:
1. The `web/sites/simpletest` directory exists
2. The directory has write permissions (chmod 775 or 777)
3. The web server user has access to create subdirectories

The custom bootstrap handles this automatically, but manual intervention may be needed in restricted environments.

## Notes
- Tests enable `dungeoncrawler_content`; this module only houses the test code and config.
- No content types, controllers, or assets are defined here—those stay in the main content module.

## GitHub client architecture (centralized)

- A thin integration layer now exists at [src/Service/GithubIssuePrClient.php](src/Service/GithubIssuePrClient.php) with contract [src/Service/GithubIssuePrClientInterface.php](src/Service/GithubIssuePrClientInterface.php).
- This service centralizes GitHub context resolution (`repo` + token fallback chain) and core issue/PR request methods.
- The client now enforces mutative request serialization with a cross-process lock and a minimum 1-second spacing between POST/PATCH/PUT/DELETE operations.
- The client now applies rate-limit retry handling for 403/429 responses, honoring `Retry-After` and `X-RateLimit-Reset`, with exponential backoff + jitter fallback.
- The client now includes a mutative circuit breaker: repeated 403/429 rate-limit failures trigger a temporary cooldown window that pauses further mutative automation attempts.
- The client now adds mutation dedupe guards for repeated issue/PR close and comment operations, suppressing duplicate mutative calls within a short time window.
- Migration slices completed:
	- [src/Service/StageIssueSyncService.php](src/Service/StageIssueSyncService.php)
	- [src/Form/SdlcResetForm.php](src/Form/SdlcResetForm.php)
	- [src/Form/DeadValueCloseForm.php](src/Form/DeadValueCloseForm.php)
	- [src/Plugin/QueueWorker/TesterRunQueueWorker.php](src/Plugin/QueueWorker/TesterRunQueueWorker.php) for GitHub issue create/comment/assign/search flows
	- [src/Controller/TestingDashboardController.php](src/Controller/TestingDashboardController.php) for centralized GitHub context resolution + mutation + read/list helper paths
	now consume the centralized client instead of direct `http_client` + local config/token resolution logic.
- Remaining direct GitHub callsites are minimal and mostly internal legacy helper surface that can be removed in a cleanup pass; operational read/mutation flows now route through the centralized client.

## GitHub rate-limit runbook

- Core limits reference (subject to GitHub policy updates):
	- Unauthenticated REST: 60 requests/hour per IP.
	- Authenticated REST: 5,000 requests/hour per user/token.
	- Authenticated GraphQL: 5,000 points/hour.
	- GitHub Actions `GITHUB_TOKEN`: 1,000 requests/hour per repository (15,000/hour for Enterprise Cloud resources).
	- GitHub App installations: baseline 5,000/hour, scalable up to 12,500/hour (15,000/hour on Enterprise Cloud).
- Check current limits from CLI:
	- `gh api rate_limit`
- Secondary rate-limit handling expectations:
	- Honor `Retry-After` and `X-RateLimit-Reset` before retrying.
	- Use exponential backoff and avoid high-frequency polling loops.
	- Prefer conditional/cached reads and max page sizes to reduce repetitive calls.
	- Keep mutative operations serialized to avoid anti-spam triggering.
- Recovery workflow when mutative cooldown is active:
	1. Review recent `dungeoncrawler_tester` logs for repeated 403/429 failures.
	2. Wait for cooldown to expire; avoid restarting high-frequency mutation loops.
	3. Verify quota with `gh api rate_limit` before resuming manual or automated runs.
	4. Resume stage/queue processing and confirm normal mutation success in logs.

## File Inventory
| File | Purpose | First pass |
| --- | --- | --- |
| [README.md](README.md) | Module overview and usage notes | Reviewed |
| [dungeoncrawler_tester.info.yml](dungeoncrawler_tester.info.yml) | Module metadata and dependency on dungeoncrawler_content | Reviewed |
| [phpunit.xml](phpunit.xml) | PHPUnit configuration (suites, coverage, env, custom bootstrap) | Updated |
| [tests/bootstrap.php](tests/bootstrap.php) | Custom bootstrap ensuring simpletest directory permissions | New |
| [tests/README.md](tests/README.md) | Test suite structure and quick commands | Updated |
| [tests/TESTING_MODULE_README.md](tests/TESTING_MODULE_README.md) | Detailed test instructions and grouping | Updated |
| [tests/fixtures/characters/level_1_fighter.json](tests/fixtures/characters/level_1_fighter.json) | Character fixture: level 1 fighter | Updated |
| [tests/fixtures/characters/level_1_wizard.json](tests/fixtures/characters/level_1_wizard.json) | Character fixture: level 1 wizard | Updated |
| [tests/fixtures/characters/level_5_rogue.json](tests/fixtures/characters/level_5_rogue.json) | Character fixture: level 5 rogue | Updated |
| [tests/fixtures/pf2e_reference/core_mechanics.json](tests/fixtures/pf2e_reference/core_mechanics.json) | PF2e reference data | Reviewed |
| [tests/fixtures/schemas/ancestries_test.json](tests/fixtures/schemas/ancestries_test.json) | Schema fixture: ancestries | Reviewed |
| [tests/fixtures/schemas/backgrounds_test.json](tests/fixtures/schemas/backgrounds_test.json) | Schema fixture: backgrounds | Reviewed |
| [tests/fixtures/schemas/classes_test.json](tests/fixtures/schemas/classes_test.json) | Schema fixture: classes | Reviewed |
| [tests/src/Functional/CampaignStateAccessTest.php](tests/src/Functional/CampaignStateAccessTest.php) | Functional: campaign state access | Reviewed |
| [tests/src/Functional/CampaignStateValidationTest.php](tests/src/Functional/CampaignStateValidationTest.php) | Functional: campaign state validation | Reviewed |
| [tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php](tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php) | Functional: character creation workflow | Reviewed (tests incomplete) |
| [tests/src/Functional/Controller/AboutControllerTest.php](tests/src/Functional/Controller/AboutControllerTest.php) | Functional: About controller | Reviewed |
| [tests/src/Functional/Controller/CampaignControllerTest.php](tests/src/Functional/Controller/CampaignControllerTest.php) | Functional: campaign controller | Reviewed |
| [tests/src/Functional/Controller/CharacterApiControllerTest.php](tests/src/Functional/Controller/CharacterApiControllerTest.php) | Functional: character API controller | Reviewed |
| [tests/src/Functional/Controller/CharacterCreationControllerTest.php](tests/src/Functional/Controller/CharacterCreationControllerTest.php) | Functional: character creation controller | Reviewed |
| [tests/src/Functional/Controller/CharacterCreationStepControllerTest.php](tests/src/Functional/Controller/CharacterCreationStepControllerTest.php) | Functional: character creation step controller | Reviewed |
| [tests/src/Functional/Controller/CharacterListControllerTest.php](tests/src/Functional/Controller/CharacterListControllerTest.php) | Functional: character list controller | Reviewed |
| [tests/src/Functional/Controller/CharacterStateControllerTest.php](tests/src/Functional/Controller/CharacterStateControllerTest.php) | Functional: character state controller | Reviewed |
| [tests/src/Functional/Controller/CharacterViewControllerTest.php](tests/src/Functional/Controller/CharacterViewControllerTest.php) | Functional: character view controller | Reviewed |
| [tests/src/Functional/Controller/CombatActionControllerTest.php](tests/src/Functional/Controller/CombatActionControllerTest.php) | Functional: combat actions controller | Reviewed |
| [tests/src/Functional/Controller/CombatApiControllerTest.php](tests/src/Functional/Controller/CombatApiControllerTest.php) | Functional: combat API controller | Reviewed |
| [tests/src/Functional/Controller/CombatControllerTest.php](tests/src/Functional/Controller/CombatControllerTest.php) | Functional: combat controller | Reviewed |
| [tests/src/Functional/Controller/CombatEncounterApiControllerTest.php](tests/src/Functional/Controller/CombatEncounterApiControllerTest.php) | Functional: combat encounter API controller | Reviewed |
| [tests/src/Functional/Controller/CreditsControllerTest.php](tests/src/Functional/Controller/CreditsControllerTest.php) | Functional: credits controller | Reviewed |
| [tests/src/Functional/Controller/DashboardControllerTest.php](tests/src/Functional/Controller/DashboardControllerTest.php) | Functional: dashboard controller | Reviewed |
| [tests/src/Functional/Controller/DungeonControllerTest.php](tests/src/Functional/Controller/DungeonControllerTest.php) | Functional: dungeon controller | Reviewed |
| [tests/src/Functional/Controller/HexMapControllerTest.php](tests/src/Functional/Controller/HexMapControllerTest.php) | Functional: hex map controller | Reviewed |
| [tests/src/Functional/Controller/HomeControllerTest.php](tests/src/Functional/Controller/HomeControllerTest.php) | Functional: home controller | Reviewed |
| [tests/src/Functional/Controller/HowToPlayControllerTest.php](tests/src/Functional/Controller/HowToPlayControllerTest.php) | Functional: how-to-play controller | Reviewed |
| [tests/src/Functional/Controller/TestingPageControllerTest.php](tests/src/Functional/Controller/TestingPageControllerTest.php) | Functional: testing dashboard controller | Reviewed |
| [tests/src/Functional/Controller/WorldControllerTest.php](tests/src/Functional/Controller/WorldControllerTest.php) | Functional: world controller | Reviewed |
| [tests/src/Functional/EntityLifecycleTest.php](tests/src/Functional/EntityLifecycleTest.php) | Functional: entity lifecycle | Reviewed |
| [tests/src/Functional/Routes/AdminRoutesTest.php](tests/src/Functional/Routes/AdminRoutesTest.php) | Functional: admin routes | Reviewed |
| [tests/src/Functional/Routes/ApiRoutesTest.php](tests/src/Functional/Routes/ApiRoutesTest.php) | Functional: API routes | Reviewed |
| [tests/src/Functional/Routes/CampaignRoutesTest.php](tests/src/Functional/Routes/CampaignRoutesTest.php) | Functional: campaign routes | Reviewed |
| [tests/src/Functional/Routes/CharacterRoutesTest.php](tests/src/Functional/Routes/CharacterRoutesTest.php) | Functional: character routes | Reviewed |
| [tests/src/Functional/Routes/DemoRoutesTest.php](tests/src/Functional/Routes/DemoRoutesTest.php) | Functional: demo routes | Reviewed |
| [tests/src/Functional/Routes/PublicRoutesTest.php](tests/src/Functional/Routes/PublicRoutesTest.php) | Functional: public routes | Reviewed |
| [tests/src/Unit/Service/CharacterCalculatorTest.php](tests/src/Unit/Service/CharacterCalculatorTest.php) | Unit: character calculator | Updated |
| [tests/src/Unit/Service/CombatCalculatorTest.php](tests/src/Unit/Service/CombatCalculatorTest.php) | Unit: combat calculator | Updated |
| [tests/src/Unit/Service/GithubIssuePrClientTest.php](tests/src/Unit/Service/GithubIssuePrClientTest.php) | Unit: GitHub client dedupe/idempotency guards | New |
| [tests/src/Unit/Traits/FixtureLoaderTrait.php](tests/src/Unit/Traits/FixtureLoaderTrait.php) | Shared fixture helper trait | Updated |

## Dashboard / Testing Page

Access the testing dashboard at: `/dungeoncrawler/testing`

This page provides the primary testing workflow hub for documentation, stagegates, commands, and issue triage.

### Queue Management Integration

The testing dashboard includes **embedded queue management** for background test execution. There is no separate queue management page.

**Queue Management Features**:
- Real-time queue status monitoring
- Queue item inspection and management
- Manual queue execution controls
- Activity logs from watchdog entries
- Auto-refresh capability with countdown

**Architecture**:
- Queue UI is embedded directly in the testing dashboard at `/dungeoncrawler/testing`
- AJAX endpoints for queue operations:
  - `/dungeoncrawler/testing/queue/run` - Execute queued items
  - `/dungeoncrawler/testing/queue/status` - Get queue status
  - `/dungeoncrawler/testing/queue/logs` - Retrieve activity logs
  - `/dungeoncrawler/testing/queue/item/delete` - Remove queue item
  - `/dungeoncrawler/testing/queue/item/rerun` - Re-queue failed item
- Frontend assets: `queue-management.js` and `queue-management.css`
- Template: `dungeoncrawler-tester-queue-management.html.twig`

**Note**: There is no separate queue management page. The obsolete route `/dungeoncrawler/testing/queue-management` was removed in DCT-0139. All queue management functionality is embedded directly into the main testing dashboard at `/dungeoncrawler/testing`.

## Content Module Documentation

For installation, configuration, routes, permissions, database schema, and other runtime/product details, see the [dungeoncrawler_content module README](../dungeoncrawler_content/README.md).

## License

Proprietary - Forseti Life
