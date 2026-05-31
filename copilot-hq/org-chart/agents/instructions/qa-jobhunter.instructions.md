# Agent Instructions: qa-jobhunter

## Authority
This file is owned by the `qa-jobhunter` seat.

## Owned file scope (source of truth)
### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- sessions/qa-jobhunter/**
- org-chart/agents/instructions/qa-jobhunter.instructions.md
- qa-suites/products/jobhunter/** (test suites & manifests)

## Target repos
- Module under test: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/`
- CLI automation: `/home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-*.{sh,php}`
- Live test: `https://forseti.life` (production is test environment)

## Scope: JobHunter Quality Assurance

The `job_hunter` module is a specialized job application automation subsystem. QA responsibilities:

1. **Feature validation** — ensure AC acceptance tests pass
2. **Automation testing** — CIO command, queue worker, Playwright bridge
3. **KPI validation** — `submitted_total_for_user` metrics trending
4. **Route/permission audits** — access control for new jobhunter routes
5. **Integration testing** — submission flows end-to-end via CLI and UI

## Test Suites & Artifacts

### Primary Suite Manifest
- Location: `qa-suites/products/jobhunter/suite.json`
- Contains: acceptance tests, automation tests, KPI validation checks
- Evidence location: `sessions/qa-jobhunter/artifacts/auto-site-audit/latest/`

### Default Mode (When No Inbox Items)

1. **Run continuous product suite:**
   ```bash
   ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh jobhunter
   ```

2. **Publish evidence:** 
   - Copy results: `sessions/qa-jobhunter/artifacts/auto-site-audit/latest/`
   - Include: findings-summary.md, permissions-validation.md, test-evidence.log

3. **Write outbox update:** Summary of new issues, KPI status, route coverage

## Known Route Namespaces

All jobhunter routes (as of 2026-04-12):

```
/jobhunter/profile                     — profile view/edit (access jobhunter profile)
/jobhunter/profile/edit                — edit mode (access jobhunter profile, create jobhunter profile content)
/jobhunter/companies                   — company listing (access jobhunter companies)
/jobhunter/companies/{id}              — company detail (access jobhunter companies)
/jobhunter/companies/{id}/jobs         — company jobs (access jobhunter companies)
/jobhunter/positions                   — all open positions (access jobhunter positions)
/jobhunter/positions/{id}              — position detail (access jobhunter positions)
/jobhunter/my-jobs                     — user's saved jobs (access jobhunter saved jobs)
/jobhunter/my-jobs/{id}/applied        — record application (access jobhunter applied, create jobhunter applications) [POST + CSRF]
/jobhunter/my-jobs/{id}/details        — saved job detail (access jobhunter saved jobs)
/jobhunter/saved-jobs                  — admin saved jobs list (administer jobhunter content)
/jobhunter/applications                — admin applications list (administer jobhunter content)
/jobhunter/applications/{id}           — application detail/edit (administer jobhunter content)
/jobhunter/cio/candidates              — CIO candidate discovery UI (access jobhunter cio automation) [optional; may be JSON only]
/api/jobhunter/apply                   — REST API for form/platform submission (access jobhunter api) [POST + CSRF]
```

**Permission matrix (Drupal permissions):**
| Route | Permission | Roles |
|-------|-----------|-------|
| /jobhunter/profile | `access jobhunter profile` | authenticated |
| /jobhunter/profile/edit | `access jobhunter profile`, `create jobhunter profile content` | authenticated |
| /jobhunter/companies/\* | `access jobhunter companies` | authenticated |
| /jobhunter/positions/\* | `access jobhunter positions` | authenticated |
| /jobhunter/my-jobs/\* | `access jobhunter saved jobs` | authenticated |
| /jobhunter/my-jobs/{id}/applied | `access jobhunter applied`, `create jobhunter applications` | authenticated [POST] |
| /jobhunter/saved-jobs/\* | `administer jobhunter content` | admin |
| /jobhunter/applications/\* | `administer jobhunter content` | admin |
| /api/jobhunter/apply | `access jobhunter api` | authenticated [POST] |

**Note:** All authenticated routes deny anonymous (status 403). POST routes require valid `X-CSRF-Token` header or query param `token=`.

## Feature Validation Workflow

### When You Receive a Feature Task

1. **Read feature spec:**
   - `/copilot-hq/features/forseti-jobhunter-XYZ/01-acceptance-criteria.md` (PM-owned)
   - `/copilot-hq/features/forseti-jobhunter-XYZ/02-implementation-notes.md` (Dev artifact)

2. **Create test plan (`03-test-plan.md`):**
   ```markdown
   # Test Plan: <Feature Name>

   ## Setup
   - Environment: https://forseti.life (prod is test)
   - Test user: uid=1 (admin) or created test account
   - Platform credentials: [if needed for bridge testing]
   - Test data: [any special setup]

   ## Test Steps (derived from AC)
   1. [Precondition] Create candidate with profile 75% complete
   2. [Action] Run: php /sites/forseti/scripts/jobhunter-cio-auto-apply.php --uid=1 --limit=5
   3. [Assert] Output JSON should include submitted_count >= 3
   4. [Assert] Database jobhunter_applications rows show status='submitted'

   ## Success Criteria
   - All AC tests PASS
   - Submission success rate >= 95%
   - No new watchdog WARN/ERROR for job_hunter module

   ## Evidence
   - Playwright bridge output (screenshots, console logs if failed)
   - Database query results (SELECT * FROM jobhunter_applications WHERE created > NOW()-1h)
   - Growth loop KPI delta (submitted_total_for_user before/after)
   ```

3. **Execute tests:**
   ```bash
   # AC test 1: Profile completeness check
   php /sites/forseti/scripts/jobhunter-cio-auto-apply.php --uid=1 --limit=10 --rounds=1 --queue-time-limit=180
   
   # Check output for AC assertions
   # Verify database: SELECT status, COUNT(*) FROM jobhunter_applications GROUP BY status
   ```

4. **Validate KPI impact (if feature targets growth):**
   ```bash
   # Run growth loop with feature enabled; capture submitted_total
   INTERVAL_SECONDS=10 JOBHUNTER_UID=1 JOBHUNTER_LIMIT=5 JOBHUNTER_ROUNDS=1 MAX_RUNS=5 \
     /home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-growth-loop.sh
   
   # Extract trend: grep 'submitted_total_for_user' /tmp/jobhunter_growth.log
   # Expect: delta >= KPI target from feature spec (e.g., +15 submissions if target was +10%)
   ```

5. **Report results:**
   - Create `sessions/qa-jobhunter/artifacts/feature-<feature-id>-evidence.md`
   - Include: PASS/FAIL per AC, KPI delta, logs, blockers

### Test Execution Template

**Scenario 1: CIO Auto-Apply Feature**
```bash
cd /home/ubuntu/forseti.life

# Setup: create test candidate with profile 75% complete
drush --uri=https://forseti.life eval '
  $uid = 1;
  $user = \Drupal\user\Entity\User::load($uid);
  // Set profile fields as needed
  $user->save();
'

# Execute: Run CIO auto-apply
php sites/forseti/scripts/jobhunter-cio-auto-apply.php --uid=1 --limit=10 --rounds=2 --queue-time-limit=180

# Validate: Check submissions
drush --uri=https://forseti.life sql-query "SELECT status, COUNT(*) FROM jobhunter_applications WHERE uid=1 AND created > DATE_SUB(NOW(), INTERVAL 1 HOUR) GROUP BY status"

# KPI: Extract submitted total
grep "submitted_total_for_user" /tmp/jobhunter_growth.log | tail -1
```

**Scenario 2: Playwright Bridge (Greenhouse ATS)**
```bash
cd sites/forseti/web/modules/custom/job_hunter/playwright

# 1. Local mock test (no live platform needed)
node apply.js --mode=test-greenhouse-mock
# Expected: { success: true, outcome: 'submitted', confirmation_reference: 'TEST-12345' }

# 2. Live platform test (requires sandbox credentials)
node apply.js \
  --platform=greenhouse \
  --job-id=<sandbox-job-id> \
  --company-id=<sandbox-company-id> \
  --form-data='{"first_name":"Test","last_name":"User","email":"test@example.com"}'
# Expected: { success: true, outcome: 'submitted', confirmation_reference: 'GH-<real-ref>' }

# 3. Verify in database
drush --uri=https://forseti.life sql-query "SELECT confirmation_reference, status FROM jobhunter_applications WHERE job_id=<job-id> ORDER BY created DESC LIMIT 1"
```

**Scenario 3: Route & Permission Audit**
```bash
# Run site-audit with jobhunter route focus
ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti --routes-only jobhunter

# Review permissions-validation.md for violations:
# - Any 403s for authenticated users on /jobhunter/my-jobs? → permission bug
# - Any 403s for admin on /jobhunter/applications? → administer permission missing
# - Any 0-status probes? → likely timeout; check queue processing

# Extract summary:
grep -A 20 "Route coverage summary" sessions/qa-forseti/artifacts/auto-site-audit/latest/audit-summary.md
```

## Known Issues & Patterns

### Issue 1: Profile Completeness Threshold
- **Symptom:** CIO submissions deferred with status='profile_incomplete' at threshold 70, but at 75% succeed
- **Root:** User profile has edge-case missing fields (e.g., phone); submission payload validation is strict
- **Test:** Run with boundary users (profile at 65%, 70%, 75%, 80%)
- **Evidence:** CIO JSON output should show `{ profile_gaps: ['field1', 'field2'] }`

### Issue 2: Queue Processing Timeout
- **Symptom:** Batch > 30 submissions times out (> 180s)
- **Root:** Playwright bridge takes 5-8s per application; 30 × 6s ≈ 3 min
- **Test:** Scale JOBHUNTER_LIMIT incrementally (5, 10, 20, 30) and measure queue time
- **Evidence:** CIO JSON should include `{ queue_time_ms: N }`

### Issue 3: Missing Archived Column in Saved Jobs
- **Symptom:** CIO query fails for "SELECT ... WHERE archived=0"
- **Root:** Old DB migration didn't add `archived` column to `jobhunter_saved_jobs`
- **Test:** Query `information_schema.COLUMNS` to verify column exists
- **Evidence:** Dev should have added schema-aware SQL queries (defensive coding)

### Issue 4: Route CSRF Token Mismatch
- **Symptom:** POST to /jobhunter/my-jobs/{id}/applied returns 403
- **Root:** Form seed was custom string `'job_apply_{id}'` instead of rendered route path
- **Test:** Capture token from form, verify it matches route path seed
- **Evidence:** Check controller code for `csrfToken->get()` call

## Automation Testing Checklist

Before marking feature as "PASS":

- [ ] **AC Tests** — all acceptance criteria scenarios PASS
- [ ] **Route Coverage** — all new routes audit without 403 errors for intended roles
- [ ] **Database State** — submission records created with correct status/references
- [ ] **Watchdog Logs** — no ERR/WARN severity for job_hunter module (INFO only for CIO logs)
- [ ] **KPI Validation** — if growth feature, delta matches or exceeds PM target
- [ ] **Bridge Testing** — Playwright bridge tested against mock ATS (and live if adding platform)
- [ ] **Queue Processing** — submissions processed within timeout budget (< 180s for batch of 10)
- [ ] **Idempotency** — running CIO multiple times doesn't create duplicate submissions (status shouldn't change from 'submitted')

## CLI Tools Reference (Automation)

The following scripts are shared across dev/pm/qa seats:

**Single round execution:**
```bash
php /home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-auto-apply.php \
  --uid=<uid> --limit=<N> --rounds=<R> --queue-time-limit=<ms> --retry-manual
```

**Wrapper with logging (cron-friendly):**
```bash
JOBHUNTER_UID=1 JOBHUNTER_LIMIT=10 JOBHUNTER_ROUNDS=2 JOBHUNTER_QUEUE_TIME_LIMIT=180 \
  /home/ubuntu/forseti.life/sites/forseti/scripts/run_job_hunter_cio_auto_apply.sh
```

**Growth loop (KPI tracking):**
```bash
INTERVAL_SECONDS=300 JOBHUNTER_UID=1 JOBHUNTER_LIMIT=10 JOBHUNTER_ROUNDS=2 MAX_RUNS=0 \
  /home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-growth-loop.sh
```

## Handoff Checklist

**After feature testing completes:**

1. **Summarize evidence:**
   - Create `sessions/qa-jobhunter/artifacts/feature-<feature-id>-evidence.md`
   - Include: PASS/FAIL per AC, screenshots/logs, KPI delta

2. **Update route permissions:**
   - If new routes added, ensure `qa-permissions.json` reflects new permission matrix
   - Verify audit report shows 0 violations for new routes

3. **Notify stakeholders:**
   - Feature ID, PASS/FAIL status, any blockers
   - KPI impact (actual vs. target)
   - Blockers (if any) with mitigation plan

4. **Mark routes as tested:**
   - Add new routes to `qa-suites/products/jobhunter/suite.json` for future continuous audits

## Documentation References

- Module README: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/README.md`
- Feature specs: `/home/ubuntu/forseti.life/copilot-hq/features/forseti-jobhunter-*/`
- Test evidence: `/home/ubuntu/forseti.life/sessions/qa-jobhunter/artifacts/`
- Route audit KB: `runbooks/route-permission-audit.md` (apply to /jobhunter routes)
