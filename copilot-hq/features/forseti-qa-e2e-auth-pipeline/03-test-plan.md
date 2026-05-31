# Test Plan: forseti-qa-e2e-auth-pipeline

- Feature: forseti-qa-e2e-auth-pipeline
- Module: qa_suites
- Author: ba-forseti (scaffold — dev-infra + qa-forseti to implement/execute)
- Date: 2026-04-09

## Scope

Verify that `scripts/qa-playwright-auth.sh` provisions `FORSETI_COOKIE_AUTHENTICATED` correctly, that `qa_tester_authenticated_2` can be idempotently created, and that the provisioned cookie enables authenticated access to `/jobhunter/*` routes for HTTP-based QA suites.

## Prerequisites

- `DRUPAL_ROOT` set to `/var/www/html/forseti` (production) or `/home/ubuntu/forseti.life/sites/forseti` (dev path — note: uses different DB from production)
- `scripts/qa-playwright-auth.sh` implemented (dev-infra/qa-forseti)
- Drupal site running and accessible at `http://localhost` or `https://forseti.life`

## Test cases

### TC-1: Script exists and is executable
- Command: `test -f scripts/qa-playwright-auth.sh && test -x scripts/qa-playwright-auth.sh && echo "PASS" || echo "FAIL"`
- Expected: exits 0, prints "PASS"

### TC-2: Script produces non-empty FORSETI_COOKIE_AUTHENTICATED
- Command:
  ```bash
  DRUPAL_ROOT=/var/www/html/forseti source <(bash scripts/qa-playwright-auth.sh)
  [ -n "$FORSETI_COOKIE_AUTHENTICATED" ] && echo "PASS" || echo "FAIL: empty"
  ```
- Expected: "PASS"

### TC-3: Cookie has SESS*/SSESS* format (valid Drupal session cookie)
- Command:
  ```bash
  DRUPAL_ROOT=/var/www/html/forseti source <(bash scripts/qa-playwright-auth.sh)
  echo "$FORSETI_COOKIE_AUTHENTICATED" | grep -qE '^(SESS|SSESS)[a-f0-9]+=' && echo "PASS" || echo "FAIL: bad format"
  ```
- Expected: "PASS"

### TC-4: Idempotency — second run exits 0
- Command:
  ```bash
  DRUPAL_ROOT=/var/www/html/forseti bash scripts/qa-playwright-auth.sh > /dev/null && echo "run1 PASS"
  DRUPAL_ROOT=/var/www/html/forseti bash scripts/qa-playwright-auth.sh > /dev/null && echo "run2 PASS (idempotent)"
  ```
- Expected: both lines print PASS

### TC-5: Provisioned cookie authenticates against /jobhunter/my-jobs
- Command:
  ```bash
  DRUPAL_ROOT=/var/www/html/forseti source <(bash scripts/qa-playwright-auth.sh)
  STATUS=$(curl -sS -o /dev/null -w "%{http_code}" \
    -H "Cookie: $FORSETI_COOKIE_AUTHENTICATED" \
    https://forseti.life/jobhunter/my-jobs)
  [ "$STATUS" = "200" ] && echo "PASS: HTTP $STATUS" || echo "FAIL: HTTP $STATUS"
  ```
- Expected: "PASS: HTTP 200"

### TC-6: qa_tester_authenticated_2 user exists in Drupal
- Command:
  ```bash
  DRUPAL_ROOT=/var/www/html/forseti vendor/bin/drush --uri=http://localhost \
    user:information qa_tester_authenticated_2 --format=json 2>/dev/null | \
    python3 -c "import json,sys; d=json.load(sys.stdin); uid=list(d.keys())[0]; u=d[uid]; \
    assert u['status']=='1'; print('PASS uid=' + uid)"
  ```
- Expected: prints "PASS uid=N"

### TC-7: qa_tester_authenticated_2 has jobhunter_job_seeker profile row
- Command:
  ```bash
  DRUPAL_ROOT=/var/www/html/forseti vendor/bin/drush --uri=http://localhost \
    user:information qa_tester_authenticated_2 --format=json 2>/dev/null | \
    python3 -c "import json,sys; d=json.load(sys.stdin); uid=list(d.keys())[0]; print(uid)" | \
    xargs -I{} mysql -u drupal_user -p forseti_prod -e \
    "SELECT COUNT(*) FROM jobhunter_job_seeker WHERE uid={};"
  ```
  - Expected: returns `1` (profile row exists)
  - Note: if credentials differ, use `drush sql:query "SELECT COUNT(*) FROM jobhunter_job_seeker WHERE uid={}"`

### TC-8: Cross-user isolation — user A session cannot read user B records
- Manual/semi-automated test:
  1. Provision cookie for `qa_tester_authenticated` (user A)
  2. Provision cookie for `qa_tester_authenticated_2` (user B)
  3. Submit at least 1 job application as user B
  4. Request `/jobhunter/my-jobs` API or page as user A
  5. Assert user B's applications do NOT appear in user A's response

- Expected: No cross-user data leakage; either empty results or 403

### TC-9: schema validation passes
- Command: `python3 scripts/qa-suite-validate.py`
- Expected: exits 0

## Execution order

1. TC-1 (script exists — gate)
2. TC-2, TC-3 (cookie provisioned correctly)
3. TC-4 (idempotency)
4. TC-5 (authentication validates on live route)
5. TC-6, TC-7 (second user exists and is configured)
6. TC-8 (cross-user isolation — execute last, requires both users active)
7. TC-9 (schema validation)

## Notes

- `jobhunter-e2e` Playwright suite does NOT need this script — it self-provisions via inline drush commands. Do not modify that suite's command as part of this feature.
- `DRUPAL_ROOT=/var/www/html/forseti` is the production path with the production DB. Use `/home/ubuntu/forseti.life/sites/forseti` only for dev/test scenarios (different DB, same codebase).
- Cookie values must never be committed to git; the script should only print them to stdout/env-file.
