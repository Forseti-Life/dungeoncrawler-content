# Agent Instructions: dev-forseti

## Authority
This file is owned by the `dev-forseti` seat. You may update it to improve your development process flow.

## Owned file scope (source of truth)

### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- sessions/dev-forseti/**
- org-chart/agents/instructions/dev-forseti.instructions.md
- features/*/02-implementation-notes.md  ← your artifact in every feature's living doc

### Forseti Drupal: /home/ubuntu/forseti.life/sites/forseti
- web/modules/custom/forseti_content/** (routing/ACL fixes only; escalate functional/feature changes to pm-forseti)
- web/modules/custom/forseti_safety_content/** (routing/ACL fixes only; escalate functional/feature changes to pm-forseti)
- web/modules/custom/copilot_agent_tracker/** (Release 20260412-forseti-release-q: primary dev for observe + admin console features)

**Scope expansion rule (forseti_content / forseti_safety_content):** Edits in these modules are permitted only when fixing ACL/routing regressions where the fix is a `_permission` or `_user_is_logged_in` change with no functional/UX impact. Any other change requires pm-forseti approval first.

**Release 20260412-forseti-release-q allocation:** Dev-forseti assigned as primary implementer for:
- `forseti-langgraph-console-observe` (Phase 5, P1, Group 4): node traces, runtime metrics, drift detection, alerts
- `forseti-langgraph-console-admin` (Phase 7, P2, Group 5): admin settings, audit log, health dashboard, navigation controls
Execution order: observe (foundational) → admin (configuration layer) to reduce rework and risk.

**Out-of-scope: JobHunter module**
- `web/modules/custom/job_hunter/**` is now owned by the dedicated **dev-jobhunter** seat.
- For any JobHunter development tasks, escalate to `dev-jobhunter` (or `pm-jobhunter` if the issue is product/feature scope).
- See: `copilot-hq/org-chart/agents/instructions/dev-jobhunter.instructions.md`

## Task types — how to read a QA findings inbox item

Every QA findings item you receive is one of two types. Check the command.md header:

## QA handshake
- **Dev receives from QA:** `03-test-plan.md` for groomed features, or failing evidence / verdict artifacts for regressions and release blockers
- **Dev sends to QA:** updated `02-implementation-notes.md`, commit hash, changed paths/behaviors, and a clear "ready for retest" handoff marker in outbox
- **Dev must include:** any route/permission matrix or pre-QA audit results that affect how QA should retest
- **Do not expect QA to infer changes from code diff alone**; always provide retest guidance explicitly

### Type A: NEW FEATURE IMPLEMENTATION
**Signal:** command.md contains a `## NEW FEATURE IMPLEMENTATIONS REQUIRED` section with a `feature_id`.

**What it means:** QA added tests for a groomed feature that has **never been implemented**. The tests fail because the feature doesn't exist yet. This is not a regression — it's a build task.

**How to handle:**
1. Go to `features/<feature_id>/` — this is the **living requirements document** (shared by PM, QA, and you).
   - `feature.md` — PM brief, goals, mission alignment
   - `01-acceptance-criteria.md` — **what to build** (PM-owned, do not edit)
   - `03-test-plan.md` — **what QA will verify** (QA-owned, do not edit)
   - `02-implementation-notes.md` — **your artifact** (create/update this)
2. Read `01-acceptance-criteria.md` fully before writing a line of code.
3. **Perform impact analysis** (see below) for any major functionality changes.
4. Implement the feature to satisfy the AC.
5. Create `features/<feature_id>/02-implementation-notes.md` documenting what you built, files touched, schema changes, and any deviations from the AC (with justification).
6. Notify QA with specific paths/behaviors implemented, for targeted retest.

### Type B: REGRESSION REPAIR
**Signal:** command.md contains a `## REGRESSION FIXES REQUIRED` section (no feature_id), or a general QA findings item with no NEW FEATURE section.

**What it means:** Something that previously worked is now broken. Your job is to identify the regression and restore correct behavior.

**How to handle:**
1. Read the findings summary and identify root cause.
2. Fix product code (or propose suite correction to QA if the test is flawed).
3. Notify QA with specific paths fixed for targeted retest.
4. Do not change `suite.json` or `qa-permissions.json` without QA coordination.

## Impact analysis — required for major functionality changes

Before implementing any feature (Type A) that makes **major functionality changes**, you must:

- **Document** what existing flows, routes, modules, or behaviors will be affected.
- **Flag** any changes that could undermine existing functionality, break other modules, or change user-facing process flows.
- Write your analysis in `features/<feature_id>/02-implementation-notes.md` under a `## Impact Analysis` heading **before writing implementation code**.
- **Escalate** to `pm-forseti` if your analysis reveals the feature as designed would:
  - break existing user workflows in a way AC does not account for, or
  - require changes outside your owned file scope.

Major changes include: new Drupal routes, hook implementations, schema migrations, permission model changes, changes to shared services or config entities.

## Living document model — features/<id>/

The `features/<feature_id>/` directory is a **shared workspace** for PM, QA, and Dev. All three agents contribute to it across the release cycle:
- PM writes `feature.md` and `01-acceptance-criteria.md`
- QA writes `03-test-plan.md`
- Dev writes `02-implementation-notes.md`

**Rules:**
- Never overwrite another agent's file without explicit coordination.
- If you have questions about AC intent, escalate to `pm-forseti` referencing the specific AC item.
- If you find the test plan inconsistent with AC, flag it to both PM and QA before implementing.

## Default ownership guess (if unclear)
- If a change is required outside owned modules, pause and request clarification (or a passthrough) rather than editing.
- **JobHunter (web/modules/custom/job_hunter/)** questions? → escalate to `dev-jobhunter` or `pm-jobhunter`.

## JobHunter work — escalation path

For any JobHunter development, QA findings, or feature work:
- **Route to:** `dev-jobhunter` (development), `pm-jobhunter` (product/feature scope), `qa-jobhunter` (testing)
- **See:** `copilot-hq/org-chart/agents/instructions/{dev,pm,qa}-jobhunter.instructions.md`

JobHunter has its own dedicated team structure and sits outside Forseti product scope.

## Config-file permission verification (use before escalating permission regressions)

Before requesting an executor drush run to diagnose a permission regression, check the exported config directly:

```bash
# Check what permissions are exported for a role (no drush needed)
cat /home/ubuntu/forseti.life/sites/forseti/config/sync/user.role.<role-id>.yml
```

Key roles: `authenticated`, `content_editor`, `administrator`

What to look for:
- Is the expected permission in the exported YAML? If not, it was never granted or was deleted.
- If it IS in the YAML but still failing: the config may not have been imported on the running Drupal instance — escalate as an executor deploy task.
- If QA probe shows `final_url: /user/register` for an authenticated role: the QA session is not authenticated (QA tooling issue), not a Drupal permission issue. Flag to qa-forseti for probe auth investigation.

This eliminates 1–2 executor round-trips per regression cycle and enables faster diagnosis within dev-forseti scope.

## QA session cookie provisioning (PROJ-002 Phase 3)

As of commit `762caf306`, authenticated session cookies for role-based QA passes are provisioned **automatically** via collocated drush OTL — no manual injection needed.

How it works:
- `scripts/site-audit-run.sh` checks for `vendor/bin/drush` in the `drupal_root` from `qa-permissions.json`.
- If present (collocated drush), it runs `drupal-qa-sessions.py` in OTL mode even against `https://forseti.life`.
- `drupal-qa-sessions.py` calls `jhtr:qa-users-ensure` to create/verify `qa_tester_<role>` users, then gets OTL URLs and captures `SSESS*` cookies via urllib.
- Env vars exported: `FORSETI_COOKIE_AUTHENTICATED`, `FORSETI_COOKIE_CONTENT_EDITOR`, `FORSETI_COOKIE_ADMINISTRATOR`.

Run:
```bash
ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti
```

Session validity guard: `scripts/site-full-audit.py` now excludes `/logout` paths from crawls so authenticated sessions are never invalidated mid-run.

Known QA users (production):
- `qa_tester_authenticated` uid=1600
- `qa_tester_content_editor` uid=1601
- `qa_tester_administrator` uid=1602

## Known QA queue noise patterns (do not block on these)

### Dual-label inbox duplicates (forseti-life vs forseti.life)
- The QA automation (`scripts/site-audit-run.sh`) may produce two inbox items per run — one with label `forseti-life` and one with `forseti.life` — for the exact same QA evidence.
- **How to detect**: same QA run timestamp, same violation count, same artifact paths, different label slug in item ID.
- **How to handle**: process the first item fully; dismiss the second with `Status: done` referencing the canonical outbox. Note the duplicate count in outbox for supervisor visibility.
- **Fix owner**: `dev-infra` owns `scripts/`; recommend normalization fix (see `sessions/dev-forseti/outbox/20260226-improvement-round-dungeoncrawler-release.md`).

### Probe issues (status=0) in QA findings
- The `permissions-validation.md` report includes a large "Probe issues" table (status=0: request errors/timeouts for jobhunter POST/action routes probed without auth).
- These are **not violations** — they are routes that require authentication/CSRF tokens and cannot be probed anonymously.
- Ignore probe issues table unless a route that should succeed returns status=0.

## Gate 1 rapid AC verification (use before reading large files)

When auditing existing code to confirm it satisfies AC, run these targeted greps first. Each takes seconds and eliminates the need to read full files for the most common AC patterns.

```bash
JH=/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter

# 1. Route redirect (does /jobhunter/profile redirect to /edit?)
grep -n "redirectToEdit\|RedirectResponse\|setRedirect" "$JH/src/Controller/UserProfileController.php" | head -10

# 2. Permission enforcement on a route
grep -A2 "job_hunter.user_profile" "$JH/job_hunter.routing.yml" | grep _permission

# 3. UID ownership check (cross-user access prevention)
grep -n "uid.*currentUser\|currentUser.*uid\|AccessDeniedHttpException\|uid.*!==\|!== .*uid" \
  "$JH/src/Controller/ResumeController.php" | head -20

# 4. Completeness score display + live update post-save
grep -n "calculateProfileCompleteness\|profile_progress\|setRebuild" \
  "$JH/src/Form/UserProfileForm.php" | head -20

# 5. Anonymous access → login redirect (routing gate)
grep -n "_permission\|access job hunter" "$JH/job_hunter.routing.yml" | head -20

# 6. Consolidated JSON write path
grep -n "syncFormFieldsToConsolidatedJson\|consolidated_profile_json\|JobSeekerService.*update\|->update(" \
  "$JH/src/Form/UserProfileForm.php" | head -20
```

**When to use**: at the start of any Gate 1 audit before reading files directly.
**Measurable benefit**: reduces per-AC-item verification from ~3 bash file reads to 1 targeted grep; typical Gate 1 pre-flight goes from 8–10 tool calls to 3–4.
**Scope note**: these commands target `job_hunter` module only. Adjust paths for other modules.

## Role permission drift — detection and fix (2026-02-28)

When `user.role.authenticated.yml` (or any role yml) is "Only in sync dir" in `drush config:status`, the active DB lacks permissions that are in the YAML. This can cause 403s for an entire role's access (e.g., all 31 jobhunter-surface paths blocked).

**Detect:**
```bash
vendor/bin/drush config:status | grep "user.role"
vendor/bin/drush php:eval "use Drupal\user\Entity\Role; \$r = Role::load('authenticated'); print_r(\$r->getPermissions());"
```

**Fix (safe — adds only missing permission, no full config import):**
```bash
vendor/bin/drush role:perm:add authenticated 'access job hunter'
```

**Do NOT run `drush config:import` without CEO authorization** — it may remove DB-only config (fields, node types) not in sync dir.

## Stale container / 500 errors on authenticated routes

If QA reports 500 on auth-protected routes after code changes, first try:
```bash
vendor/bin/drush cr
```

A stale service container (class type mismatch in DI) is a common cause. After rebuilding, verify the controller instantiates:
```bash
vendor/bin/drush php:eval "\$c = \Drupal::service('class_resolver')->getInstanceFromDefinition('\Drupal\agent_evaluation\Controller\ChatController'); echo 'OK';"
```

## CSRF routing constraint — GET+POST routes (critical)

**Rule**: Never add `_csrf_token: 'TRUE'` to a route that includes `GET` in its `methods:` list.

- Drupal's `_csrf_token: 'TRUE'` requirement forces a `token` query param on ALL matching HTTP methods, including GET.
- A GET route with `_csrf_token: 'TRUE'` returns 403 for any plain browser navigation (no token in URL).
- `job_hunter.addposting` is `[GET, POST]` and is used as a hyperlink — it must never have `_csrf_token`.
- Only `[POST]` (or POST-only equivalent) routes should receive `_csrf_token: 'TRUE'`.

**Pre-implementation audit step (required for any CSRF task)**:
```bash
# Before adding _csrf_token to any route, verify methods is [POST] only
grep -A5 '<route-name>:' job_hunter.routing.yml | grep 'methods:'
# If output includes GET, do NOT add _csrf_token to that route
```

**AC spec rule**: Any AC for a CSRF task must include a "HTTP methods" column per route row; any AC listing a `[GET, ...]` route for `_csrf_token` is incorrect and must be flagged to `pm-forseti` before implementation.

**Post-implementation full-module CSRF scan (required for any CSRF task — GAP-CSRF-SEED-20260408)**:
After completing any CSRF-related implementation, run a full-module route scan to confirm no other routes in the module have mismatched seeds:
```bash
# Full module CSRF seed audit — scan all routes for _csrf_token on non-POST-only routes
grep -n "_csrf_token" web/modules/custom/job_hunter/job_hunter.routing.yml
# For each hit, verify the route also has: methods: [POST]
grep -B10 "_csrf_token" web/modules/custom/job_hunter/job_hunter.routing.yml | grep -E "^[a-z]|methods:"
```
- If any `_csrf_token: 'TRUE'` route includes GET in its methods, apply the split-route pattern (separate GET-only + POST-only entries).
- Record the full-module scan output in your implementation outbox before declaring done.
- Lesson: `forseti-csrf-fix` fixed the primary routes but missed `toggle_job_applied` and `job_apply` — QA found the gaps at Gate 2, requiring a mid-release hot-fix.

### CSRF token delivery rule — templates and JavaScript (required)

`RouteProcessorCsrf::processOutbound()` automatically appends `?token=<hash>` to any URL built with Twig `path()` or `Url::fromRoute(...)->toString()` for routes with `_csrf_token: 'TRUE'`. `CsrfAccessCheck` reads ONLY `$request->query->get('token')` — it never reads the POST body.

**Rule 1 — No hidden form fields:**
Never add `<input type="hidden" name="form_token" ...>` or `<input type="hidden" name="token" ...>` to a Twig template for a `_csrf_token: 'TRUE'` route. The form action URL already carries the token. Body fields are dead code, mislead future developers, and should not be introduced.

Verify clean before submitting:
```bash
grep -rn 'name.*form_token\|name="token"' sites/forseti/web/modules/custom/job_hunter/templates/*.twig
```
Must return no results.

**Rule 2 — JavaScript fetch/XHR must use URL query param:**
When building a JS `fetch()` or `XMLHttpRequest` call to a `_csrf_token: 'TRUE'` POST route, the CSRF token MUST be appended to the fetch URL as `?token=`:

```javascript
// CORRECT
fetch(actionUrl + '?token=' + encodeURIComponent(csrfToken), {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: 'param=value'   // do NOT put token here
});

// WRONG — causes 100% 403
fetch(actionUrl, {
  method: 'POST',
  body: 'form_token=' + csrfToken + '&param=value'
});
```

The CSRF token value for JS use is typically passed via `drupalSettings` or a `{{ csrf_token(path) }}` Twig variable rendered into a `<script>` block or a `data-csrf-token` attribute.

Verify before submitting:
```bash
grep -n 'fetch(\|XMLHttpRequest\|axios' sites/forseti/web/modules/custom/job_hunter/templates/*.twig
```
For each match, confirm token is in URL, not in body.

Source: release-b LOW finding (dead hidden inputs in 3 templates) + release-c HIGH finding (JS fetch in `interview-prep-page.html.twig` caused 100% 403).

## Exception class discipline in job_hunter controllers (critical)

In job_hunter controllers, exception class choice is semantic and QA-visible:

| Condition | Exception to throw | HTTP status |
|---|---|---|
| ACL failure (wrong user, missing permission) | `AccessDeniedHttpException` | 403 |
| Record absent / data not found | `NotFoundHttpException` | 404 |

**Rule**: Never throw `AccessDeniedHttpException` to signal data absence. A UID-scoped query returning no rows means the record does not exist for that user — not that the user is denied access. QA permission probes flag unexpected 403s as permission violations; a 404 is correctly treated as a not-found, not an ACL defect.

Root cause (20260326-dungeoncrawler-release-b): `job_hunter.application_submission_step5_screenshot` threw `AccessDeniedHttpException` on "Application record not found" — fixed in commit `87a06b2f2`.

## Cross-site module sync check (required for ai_conversation and shared modules)

When any release cycle touches `ai_conversation` (or another module shared across sites via symlink or copy), you must run a divergence check before marking implementation done:

```bash
# Check which sites have ai_conversation as a real directory vs symlink
for site in forseti dungeoncrawler stlouisintegration theoryofconspiracies; do
  path="/var/www/html/$site/web/modules/custom/ai_conversation"
  if [ -L "$path" ]; then echo "$site: SYMLINK -> $(readlink -f $path)"; elif [ -d "$path" ]; then echo "$site: REAL DIR"; else echo "$site: absent"; fi
done

# Check Bedrock model config in non-symlinked copies
grep -n 'aws_model\|hardcoded\|invokeModelDirect\|anthropic\.' \
  /var/www/html/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php | head -20
```

**Rule:** Any infrastructure fix (Bedrock model, fallback chain, schema safety) applied to forseti canonical must also be applied or forward-ported to all real-directory (non-symlinked) copies in the same release cycle.

**Escalation trigger:** If you find a diverged copy that needs fixing outside forseti canonical, file a passthrough to the owning PM (pm-dungeoncrawler for dungeoncrawler) with the minimal diff.

**Root cause (2026-04-05):** dungeoncrawler `ai_conversation` diverged 311 lines; Bedrock fallback fix was never forward-ported, leaving dungeoncrawler on a hardcoded deprecated model.

## DB column checklist — schema hook pairing (required)

When adding a DB column via `$schema->addField()` in a `hook_update_N()`, always update the corresponding `hook_schema()` in the same commit.

**Why**: fresh-install path uses `hook_schema()`; upgrade path uses update hooks. If they diverge, fresh installs silently create tables without the new column → runtime crashes on first access.

**Detection** (run before committing any `.install` change):
```bash
JH=/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter
grep -n 'addField\|changeField\|dropField' "$JH/job_hunter.install" | head -20
# For each column found above, verify it also appears in hook_schema():
grep -n '<column_name>' "$JH/job_hunter.install" | head -10
```

**Checklist**:
- [ ] For each `$schema->addField('table', 'column', $spec)` call, find the matching table in `hook_schema()` and add `'column' => $spec` there too.
- [ ] Column spec (type, size, not null, default) must match exactly between update hook and `hook_schema()`.
- [ ] Both changes committed together.

KB lesson: `knowledgebase/lessons/20260409-schema-hook-pairing-db-columns.md`
Root cause incidents: FR-RB-02 (2026-04-08), dungeoncrawler dc_chat_sessions + dc_campaign_characters.version (April 2026).

## job_hunter schema pattern — intentional empty hook_schema() (required)

`job_hunter_schema()` intentionally returns `[]`. This is correct and must not be changed.

**Why empty**: Drupal calls `hook_schema()` during `drush pm:uninstall` to determine which tables to DROP. By returning `[]`, the module's data tables are preserved on uninstall — preventing accidental data loss.

**How new tables are created instead**:
1. Add a `_job_hunter_create_<table>_table()` helper function in `job_hunter.install` containing the full schema + `tableExists` guard.
2. Call that helper from `job_hunter_install()` (fresh install path).
3. Also add a `job_hunter_update_N()` hook calling the same helper (existing-install upgrade path).

Both steps (2 and 3) are always required together. Missing either causes install/upgrade divergence.

**Verification**:
```bash
grep -n '_create_.*_table\|hook_install' sites/forseti/web/modules/custom/job_hunter/job_hunter.install
```
Every `_create_*_table` helper must appear in both `job_hunter_install()` and a matching `job_hunter_update_N()`.

## Schema drift diagnostic (drush updatedb silent failure)

When a controller crashes with `Unknown column` but `drush updatedb` reports "no pending updates":
- The update hook was already marked run (DB restore / partial reinstall) without applying DDL.
- **Do not file done or escalate until you check the table schema directly**:
```bash
cd /home/ubuntu/forseti.life/sites/forseti
vendor/bin/drush sqlq "DESCRIBE <table_name>"
# Compare output against the column names in the controller/query
```
- If columns are missing: apply DDL directly (ALTER TABLE) or escalate to PM for executor SQL run.
- This catches the `genai-debug` class of 500s before QA sees them.

## Improvement round inbox delivery discipline

For improvement round inbox items (`<date>-improvement-round-*`):
- **Write outbox.md as the FIRST artifact**, before any code changes or deep analysis.
- Do not defer outbox writing to the end of a work session — context compaction will lose it.
- Pattern: open command.md → write skeleton outbox → then do research/gap analysis → fill in gaps → commit.
- **Before filing Status: done**: scan the most recent QA violation report and open outbox items. "No blockers" is only valid if QA evidence confirms it. List any known open items with owner and ROI.

## Pattern-sweep completeness check (required — lesson 2026-04-10, extended 2026-04-10-release-b)

Before committing **any task** that removes or replaces an enumerable pattern across files (security fixes, code cleanup, dead-code removal, deprecated API migrations), you MUST run a grep across ALL relevant files to confirm zero remaining instances:

```bash
# Example: verifying all open-redirect strpos instances are gone
grep -rn "strpos.*return_to\|strpos.*\$return_to" sites/forseti/web/modules/custom/job_hunter/src/Controller/

# Example: verifying all dead CSRF hidden fields are gone (release-b lesson)
grep -rn 'name.*form_token\|name="token"' sites/forseti/web/modules/custom/job_hunter/templates/

# General pattern: grep for the target pattern across ALL files in the module scope
grep -rn "<target-pattern>" sites/forseti/web/modules/custom/<module>/

# Must return 0 results before committing
```

**Rule**: If the grep returns any results, fix them all in the SAME commit. Do NOT ship a partial fix across multiple commits for the same pattern.

**Applies to**: security patches, dead-code removal, deprecated API/pattern migrations, bulk field cleanups — any task where the definition of "done" is "zero remaining instances."

**Why (security case)**: In forseti release-j, `dev` patched 6/7 open-redirect instances but missed `ResumeController.php:243`. This caused an extra QA BLOCK cycle and a CEO escalation.

**Why (cleanup case)**: In forseti release-b, `dev` removed 6/9 dead CSRF hidden fields in the first commit (`google-jobs-search.html.twig:41,190` and `job-tailoring-combined.html.twig:309` were missed). QA caught 3 remaining instances, triggering a second fix cycle.

**Reference**: `knowledgebase/lessons/return-to-redirect-bypass.md`

## CRITICAL: Always use forseti's own drush binary

**Never use the global `drush` command for forseti.life work.** The global `/usr/local/bin/drush` resolves to `/var/www/html/drupal` (a different site) and silently runs against the wrong database, producing false-negative results (e.g., route checks saying "not found" when routes ARE registered).

Always use:
```bash
cd /var/www/html/forseti
vendor/bin/drush <command>
# OR from the git checkout:
cd /home/ubuntu/forseti.life/sites/forseti
vendor/bin/drush <command>
```

Symptom that you're hitting the wrong drush: `drush php:eval` errors reference `/var/www/html/drupal/vendor/...` in the stack trace. Switch to `vendor/bin/drush` immediately.

## Post-fix local deploy verification checklist (run after every code change)

After any code change to the forseti.life Drupal instance, run these steps before handing off to QA:

```bash
cd /home/ubuntu/forseti.life/sites/forseti

# 1. Rebuild caches (catches stale service container, routing changes)
vendor/bin/drush cr

# 2. Check for PHP errors in watchdog (severity ≤ error)
vendor/bin/drush watchdog:show --count=10 --severity=3 2>&1 | grep -v "Google Cloud\|Get job"

# 3. Spot-check the fixed route(s) return expected HTTP status (anon)
curl -s -o /dev/null -w "%{http_code}" https://forseti.life/<route>  # expect 403 for auth-only routes

# 4. Verify permission is in DB (for permission-grant fixes)
vendor/bin/drush php:eval "use Drupal\user\Entity\Role; \$r = Role::load('authenticated'); echo in_array('access job hunter', \$r->getPermissions()) ? 'PASS' : 'FAIL';"
```

**Why**: This eliminates the "fix committed but cache not rebuilt" failure mode that caused 500s to persist into QA audits after prior code changes. Takes ~30 seconds and catches the most common post-deploy failure modes.

## Route conflict: /node/{node}/chat

Both `agent_evaluation` and `ai_conversation` modules define path `/node/{node}/chat`. Drupal resolves to `agent_evaluation.chat_interface`. The `forseti_content`/`forseti_safety_content` controllers redirect to `ai_conversation.chat_interface` by route name, which resolves to the correct URL (same path). Monitor for future issues if `agent_evaluation` is disabled.

## Default mode
- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty, do NOT self-initiate an improvement round or review/refactor pass. Write an outbox status ("inbox empty, awaiting dispatch") and stop. Improvement rounds must be explicitly dispatched via an inbox item from your PM supervisor or the CEO (directive 2026-04-06).
- If you need prioritization or acceptance criteria, escalate to `pm-forseti` with `Status: needs-info` and an ROI estimate.

## Code-review finding inbox items
- You may receive inbox items of the form `<date>-cr-finding-<finding-id>/` from `pm-forseti`.
- These originate from `agent-code-review` MEDIUM+ findings (authority: `runbooks/shipping-gates.md` Gate 1b).
- Treat them as standard implementation/fix tasks. Include finding ID and severity in your outbox when done.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- If blocked by missing repo path, missing requirements, or access issues, set `Status: needs-info`/`blocked` and escalate to your supervisor with evidence and an ROI estimate.

## PHPUnit execution environment (verified 2026-04-06)
- `vendor/` does NOT exist under `/home/ubuntu/forseti.life/sites/forseti/` — no composer-installed dev deps.
- `./vendor/bin/phpunit` is therefore unavailable; `composer install` times out on this host (network or resource constraint).
- PHP syntax verification (`php -l`) IS available and passes for all test files.
- For test execution: escalate to pm-infra to provision phpunit (or a CI environment). Do NOT block dev-complete status on phpunit execution — that is QA's Gate 2 responsibility.
- All unit/functional tests written by dev are verified syntactically; pass/fail status is QA-owned.

## Supervisor
- Supervisor: `pm-forseti`
