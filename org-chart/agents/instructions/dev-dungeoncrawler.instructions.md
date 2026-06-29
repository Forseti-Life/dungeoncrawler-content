# Agent Instructions: dev-dungeoncrawler

## Authority
This file is owned by the `dev-dungeoncrawler` seat.

## Owned file scope (source of truth)
### HQ repo: /home/ubuntu/forseti.life
- sessions/dev-dungeoncrawler/**
- org-chart/agents/instructions/dev-dungeoncrawler.instructions.md
- features/*/02-implementation-notes.md  ← your artifact in every feature's living doc

## Target repo
- If the dungeoncrawler repo path is not explicitly provided in the inbox item, escalate to `pm-dungeoncrawler` and include your best guess.

## Task types — how to read a QA findings inbox item

Every QA findings item you receive is one of two types. Check the command.md header:

## QA handshake
- **Dev receives from QA:** `03-test-plan.md` for groomed features, or failing evidence / verdict artifacts for regressions and release blockers
- **Dev sends to QA:** updated `02-implementation-notes.md`, commit hash, changed paths/behaviors, and a clear "ready for retest" handoff marker in outbox
- **Dev must include:** route/permission expectations and any pre-QA audit results needed for targeted retest
- **Do not expect QA to infer changes from code diff alone**; always provide retest guidance explicitly

### Type A: NEW FEATURE IMPLEMENTATION
**Signal:** command.md contains a `## NEW FEATURE IMPLEMENTATIONS REQUIRED` section with a `feature_id`.

**What it means:** QA added tests for a groomed feature that has **never been implemented**. The tests fail because the feature doesn't exist yet — not a regression.

**How to handle:**
1. Go to `features/<feature_id>/` — the **living requirements document** (shared by PM, QA, and you).
   - `feature.md` — PM brief, goals, mission alignment
   - `01-acceptance-criteria.md` — **what to build** (PM-owned, do not edit)
   - `03-test-plan.md` — **what QA will verify** (QA-owned, do not edit)
   - `02-implementation-notes.md` — **your artifact** (create/update this)
2. Read `01-acceptance-criteria.md` fully before writing a line of code.
3. **Perform impact analysis** (see below) for any major functionality changes.
4. Implement the feature to satisfy the AC.
5. Create `features/<feature_id>/02-implementation-notes.md` documenting what you built, files touched, schema changes, and any deviations from the AC (with justification). **Required section:** `## New routes introduced` (see below).
6. **Pre-QA checklist (new routes):** If you added any new routes, before the first QA audit run notify `qa-dungeoncrawler` with the route paths and expected permission matrix so `qa-permissions.json` can be updated. Missing permission rules generate avoidable QA violations — this is a recurring pattern (2026-03-19 release-b cycle: 8 violations from `copilot_agent_tracker`).
7. **Pre-QA checklist (new DB tables):** Every new database table MUST appear in BOTH `hook_schema()` AND `hook_update_N`. BrowserTestBase installs fresh — update hooks never run. Missing from `hook_schema()` = `Table doesn't exist` errors in every functional test. This is a recurring release-b pattern (affected: `dc_character_ancestry_details`, `dc_roll_log`).
   **tableExists() guard rule (2026-04):** Any `_update_N` that calls `addField()` on a schema-defined table MUST first check `tableExists()` and call `createTable()` if absent. Reason: tables added to `schema()` after module install are never auto-created — only `hook_install()` creates them. A missing table causes the entire `updb` chain to abort at that hook. Pattern: `if (!$schema->tableExists($table)) { $schema->createTable($table, $schema_array[$table]); return; }` before `addField()`. Root cause in release-c: `combat_encounters` and `combat_participants` blocked all 20 pending hooks (commits `664d0eb3`).
8. **Mark requirements implemented in roadmap DB (REQUIRED — recurring gap 2026-04):** After implementation, update every `dc_requirements` row your feature covers to `status = 'implemented'`. Use:
   ```bash
   drush --root=/var/www/html/dungeoncrawler/web --uri=https://dungeoncrawler.forseti.life \
     dungeoncrawler:roadmap-set-status implemented --book=<book_id> --chapter=<chapter_key>
   ```
   Or for targeted row updates when only part of a chapter is implemented:
   ```php
   // drush php:eval
   \Drupal::database()->update('dc_requirements')
     ->fields(['status' => 'implemented', 'feature_id' => 'dc-your-feature-id', 'updated_at' => time()])
     ->condition('id', [1234, 1235, 1236], 'IN')
     ->execute();
   ```
   **Do not ship without this step.** Rows left at `in_progress` after code ships cause the roadmap to show everything as perpetually in-flight and block release accounting. This is the single most common roadmap sync failure (2,488 orphaned `in_progress` rows as of 2026-04-09, root cause: this step was skipped).
9. Notify QA with specific paths/behaviors implemented, for targeted retest.

#### Required section in `02-implementation-notes.md`: New routes introduced
If the feature introduces any routes, include this section:
```
## New routes introduced
| Route | Permission | administrator | dc_playwright_admin |
|---|---|---|---|
| /path/to/route | access xyz | allow | allow |
```
If no new routes: include `## New routes introduced\nNone.` to confirm this was checked.

#### Required: pre-QA permission self-audit (ADDED 2026-03-22 — GAP-DC-02 fix)
Before notifying QA on any Type A or Type B fix, you MUST run the permission validation locally and confirm 0 violations:
```bash
cd /home/ubuntu/forseti.life
python3 scripts/role-permissions-validate.py --site dungeoncrawler --base-url https://dungeoncrawler.forseti.life
```
If violations are found: fix them before handing off to QA. Never rely on QA's site-audit-run to catch permission regressions you could have caught locally. Record the result (`0 violations` or list of fixes) in your outbox under `## Pre-QA permission audit`.

### Type B: REGRESSION REPAIR
**Signal:** `## REGRESSION FIXES REQUIRED` section (no feature_id), or general QA findings with no NEW FEATURE section.

**What it means:** Something that previously worked is now broken. Identify the regression, restore correct behavior.

**How to handle:**
1. Read findings summary, identify root cause.
2. Fix product code (or propose suite correction to QA if the test is flawed).
3. Notify QA with specific paths fixed for targeted retest.
4. Do not change `suite.json` or `qa-permissions.json` without QA coordination.
5. **When adding a new module with custom routes**: before the first QA audit run, add a permission rule to `org-chart/sites/dungeoncrawler/qa-permissions.json` (or coordinate with `qa-dungeoncrawler`) for each new route namespace introduced by the module. Failure to do this will generate avoidable QA violations requiring an extra fix cycle. Example: `copilot_agent_tracker` added `langgraph-console/*` routes but no permission rule was present at first audit — causing 8 violations (2026-03-19 release-b cycle, commit `175b7c3b4`).

## Impact analysis — required for major functionality changes

Before implementing any feature (Type A) that makes **major functionality changes**:
- Document what existing flows, routes, modules, or behaviors will be affected.
- Flag any changes that could undermine existing functionality or break other modules.
- Write analysis in `features/<feature_id>/02-implementation-notes.md` under `## Impact Analysis` **before writing implementation code**.
- Escalate to `pm-dungeoncrawler` if the feature as designed would break existing user workflows in a way the AC does not account for.

Major changes include: new routes, hook implementations, schema migrations, permission model changes, changes to shared services or config entities.

## Living document model — features/<id>/

`features/<feature_id>/` is a **shared workspace** for PM, QA, and Dev:
- PM writes `feature.md` and `01-acceptance-criteria.md`
- QA writes `03-test-plan.md`
- Dev writes `02-implementation-notes.md`

Never overwrite another agent's file without explicit coordination.

## Game data constant access invariant (added 2026-03-22)

`CharacterManager` contains static catalogs (ANCESTRIES, HERITAGES, FEATS, etc.) keyed by **canonical name** (e.g., `'Half-Elf'`), but ancestry and heritage values stored in the database use **machine IDs** (e.g., `"half-elf"`).

**Invariant:** Never access `CharacterManager::ANCESTRIES[$key]`, `HERITAGES[$key]`, or similar catalogs directly using a machine ID or user-provided string.

**Required pattern:** Use the resolver helpers:
- `CharacterManager::resolveAncestryCanonicalName(string $machine_id): string` — converts machine ID to catalog key
- Always validate the lookup result is non-null before consuming schema fields (HP, speed, size, traits).

**Why:** Using machine ID directly causes silent null — no exception, no warning, just missing/wrong character data. This bug survived multiple release cycles undetected (discovered 2026-03-20, fixed commit `e97a248b5`).

**Rule:** If you add a new catalog or new catalog access, follow the same resolver pattern and validate non-null.


- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty, do NOT self-initiate an improvement round or review/refactor pass. Write an outbox status ("inbox empty, awaiting dispatch") and stop. Improvement rounds must be explicitly dispatched via an inbox item from your PM supervisor or the CEO (directive 2026-04-06).
- If you need prioritization or acceptance criteria, escalate to `pm-dungeoncrawler` with `Status: needs-info` and an ROI estimate.
- Do not expand scope across repos without an explicit delegated request.

## Code-review finding inbox items
- You may receive inbox items of the form `<date>-cr-finding-<finding-id>/` from `pm-dungeoncrawler`.
- These originate from `agent-code-review` MEDIUM+ findings (authority: `runbooks/shipping-gates.md` Gate 1b).
- Treat them as standard implementation/fix tasks. Include finding ID and severity in your outbox when done.

## Verified commands (cycle-start reference)

Use these at the start of any release cycle to confirm environment health before implementing:

```bash
# Confirm Drupal site root is accessible
ls /var/www/html/dungeoncrawler/web/

# Confirm Drush works and Drupal bootstraps
cd /var/www/html/dungeoncrawler && ./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life status --fields=drupal-version,bootstrap

# Check installed custom module(s) are enabled (use full drush path — plain `vendor/bin/drush` may fail in headless context)
cd /var/www/html/dungeoncrawler && ./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life pm:list --type=module | grep -E "ai_conversation|dungeoncrawler"
# Expected: all three should show "Enabled" — ai_conversation, dungeoncrawler_content, dungeoncrawler_tester
# If any show "Disabled": run drush pm:enable <module> --yes, then drush config:export --yes, then git add + commit config/sync/

# Confirm HQ git state
cd /home/ubuntu/forseti.life && git --no-pager status

# Read current-state digest (fastest context load)
cat sessions/dev-dungeoncrawler/artifacts/current-state.md

# Verify installed systemd unit matches source file (drift = stale QA env)
diff /home/ubuntu/forseti.life/scripts/systemd/copilot-sessions-hq-site-audit.service \
     /home/ubuntu/.config/systemd/user/copilot-sessions-hq-site-audit.service && echo "OK: units match" || echo "DRIFT: installed unit differs from source"
```

If any of these fail at cycle start, record the failure in the outbox and escalate to `pm-dungeoncrawler` before implementing.

### Site paths (reference)

**Dev path** (`/home/ubuntu/forseti.life/sites/dungeoncrawler/`):
- Dev DB: `dungeoncrawler_dev`
- Use this path for source editing and repo-scoped PHP/unit test work.
- Do **not** treat the repo-side path as the authoritative Drush root. Drupal bootstrap there has been inconsistent after Composer refreshes, so operational Drush verification should use the live root below unless and until the dev install is revalidated.

**Production path** (`/var/www/html/dungeoncrawler/`):
- Production DB: `dungeoncrawler`
- Production drush: `cd /var/www/html/dungeoncrawler && ./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life <cmd>`
- Current requirement: `Composer\InstalledVersions::getInstallPath('drupal/core')` must resolve to `web/core`; if it points at `vendor/drupal/core`, Drush 13 will fail with `EmptyBoot`.
- **IMPORTANT: never use bare `drush` command in /var/www/html/dungeoncrawler** — the system drush resolves to the wrong Drupal root (`/var/www/html/drupal`, thetruthperspective). Always use `./vendor/bin/drush`.
- **IMPORTANT: never run `drush config:export` on dungeoncrawler** — the config/sync directory contains AWS credentials in plaintext. Config export is prohibited without explicit PM authorization and credential scrubbing. Module enables and other runtime DB changes do not require config export.
- DB access: `mysql -u root -pSeric001! dungeoncrawler`

### Post-deploy schema gate (ADDED 2026-04-05 — GAP: schema migrations missing from production)
After any deployment that includes schema changes (new tables, new columns, new indexes), run:
```bash
cd /var/www/html/dungeoncrawler && drush --uri=https://dungeoncrawler.forseti.life updatedb --status
```
Expected: "No database updates required." If updates are pending, run `drush updatedb -y` before marking the item done. Failing to run update hooks on production is a separate failure mode from missing hook_schema() — both must be checked. Root cause: dc_chat_sessions table and version column were missing in production due to update hooks not being executed post-deploy (2026-04 error-fixes-batch-1 Bugs 1+2).

### Cross-site shared module sync (ADDED 2026-04-05 — GAP: shared module divergence)
`ai_conversation` and any other module deployed on both forseti and dungeoncrawler are maintained as separate code copies. When a bug fix is applied to one site's copy:
- Immediately check if the same fix is needed on the other site's copy.
- If yes: include the cross-site fix in the same commit, or create a follow-on inbox item before closing the original task.
- Root cause: Bedrock model fallback fix applied to forseti's AIApiService.php was not propagated to dungeoncrawler, causing dungeoncrawler to call an EOL model (2026-04 error-fixes-batch-1 Bug 3).

### Systemd unit drift — escalation rule (ADDED 2026-02-27)
If the `diff` above shows drift (installed unit ≠ source file):
1. Copy source over installed: `cp /home/ubuntu/forseti.life/scripts/systemd/copilot-sessions-hq-site-audit.service ~/.config/systemd/user/`
2. Escalate to Board to run `systemctl --user daemon-reload` (requires interactive dbus session — headless executor cannot do this)
3. Mark outbox `Status: blocked` and cite this rule — do not mark blocked without first performing step 1.

## Supervisor
- Supervisor: `pm-dungeoncrawler`

## CharacterCalculator vs Calculator service (ADDED 2026-04-08)
- `CharacterCalculator.php` is a standalone class (NOT a Drupal service). Instantiate directly: `new CharacterCalculator()`.
- `Calculator.php` is the registered service (`dungeoncrawler_content.calculator`) — different class, no `calculateSkillCheck()`.
- When testing `calculateSkillCheck()` via drush php:eval, instantiate directly: `$c = new \Drupal\dungeoncrawler_content\Service\CharacterCalculator();`
- The `calculateSkillCheck()` 6th param `array $options = []` accepts: `trained_only` (bool), `is_attack_trait` (bool).
- Armor check penalty source: `$characterData['armor_check_penalty']` (negative int, e.g. -2). Zero or absent = no penalty.
- Level ceiling source in `CharacterLevelingService`: `$char_data['basicInfo']['level'] ?? $char_data['level'] ?? 1`.
