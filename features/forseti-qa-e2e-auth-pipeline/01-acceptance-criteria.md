# Acceptance Criteria: forseti-qa-e2e-auth-pipeline

- Feature: forseti-qa-e2e-auth-pipeline
- Module: qa_suites
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Create a standalone auth cookie provisioning script (`scripts/qa-playwright-auth.sh`) that provisions `FORSETI_COOKIE_AUTHENTICATED` for HTTP-based QA suites (curl/audit tests) via OTL flow, and ensure a second QA user (`qa_tester_authenticated_2`) can be idempotently created for cross-user isolation tests. Note: the `jobhunter-e2e` Playwright suite already self-provisions auth via `jhtr:qa-users-ensure` + `ULI_URL` inline — this feature does NOT change that suite. The auth pipeline script is primarily for URL-audit suites and any future Playwright suite that needs a pre-provisioned session cookie injected externally.

## Background: auth model clarification (important for qa-forseti)

- **Playwright E2E (`jobhunter-e2e`)**: already self-provisions using `drush jhtr:qa-users-ensure --roles=authenticated`, then gets `ULI_URL` via `drush user:login --uid=$UID --no-browser`, and navigates there in the browser. Does NOT use `FORSETI_COOKIE_AUTHENTICATED`.
- **HTTP/curl-based audit suites**: use `FORSETI_COOKIE_AUTHENTICATED` as a `Cookie:` header. Cookie is a Drupal session token (`SESS*=...`) captured by following an OTL URL with curl (`-L -c cookiejar`).
- **`drupal-qa-sessions.py`** (`scripts/drupal-qa-sessions.py`) already handles OTL-mode cookie acquisition for all roles defined in `qa-permissions.json`. The new `qa-playwright-auth.sh` script wraps the authenticated role specifically for ad-hoc use and pipeline consumption.
- **`qa_tester_authenticated_2`**: `jhtr:qa-users-ensure` creates exactly one user per Drupal role. There is no `authenticated_2` role. A second authenticated QA user requires either (a) a `--suffix` option on `jhtr:qa-users-ensure`, or (b) a direct `drush user:create` call in the script. Implementation decision is Dev-owned; AC specifies observable behavior only.

## Acceptance criteria

### AC-1: Auth provisioning script exists and is executable

**Given** `scripts/qa-playwright-auth.sh` is created (implementation by dev-infra or qa-forseti),
**When** run from the HQ root with a local Drupal site available at `DRUPAL_ROOT`,
**Then** the script:
1. Creates/ensures `qa_tester_authenticated` via `drush jhtr:qa-users-ensure --roles=authenticated`
2. Gets a one-time login URL via `drush user:login --uid=$UID --no-browser`
3. Follows the OTL URL with `curl -sS -L -c /tmp/forseti-qa-auth.cookies "$OTL_URL"`
4. Extracts the Drupal session cookie (`SESS*` or `SSESS*`) from the cookie jar
5. Exports `FORSETI_COOKIE_AUTHENTICATED=<cookie-name>=<cookie-value>` to stdout or an env file

**Script signature:**
```bash
# Usage:
#   source <(bash scripts/qa-playwright-auth.sh)           # exports into current shell
#   bash scripts/qa-playwright-auth.sh > /tmp/qa-auth.env  # write to env file
#   source /tmp/qa-auth.env                                 # source into shell
#
# Required env var:
#   DRUPAL_ROOT — path to Drupal installation (e.g. /var/www/html/forseti or /home/ubuntu/forseti.life/sites/forseti)
# Optional:
#   DRUPAL_URI  — drush --uri flag (default: http://localhost)
```

**Verification:**
```bash
# Run against production Drupal root:
DRUPAL_ROOT=/var/www/html/forseti source <(bash /home/ubuntu/forseti.life/copilot-hq/scripts/qa-playwright-auth.sh)
[ -n "$FORSETI_COOKIE_AUTHENTICATED" ] && echo "PASS: cookie provisioned" || echo "FAIL: empty cookie"
```
**PASS condition:** `FORSETI_COOKIE_AUTHENTICATED` is non-empty after sourcing the script.

**Alternative path (already supported):** `drupal-qa-sessions.py` with `--config org-chart/sites/forseti.life/qa-permissions.json` also produces the authenticated cookie. The new script is a simpler wrapper for single-role use.

---

### AC-2: Script is idempotent — safe to run multiple times

**Given** `qa_tester_authenticated` already exists in Drupal,
**When** `scripts/qa-playwright-auth.sh` is run again,
**Then** it succeeds without error (creates user if absent, updates if present — matching `jhtr:qa-users-ensure` behavior).

**Verification:**
```bash
# Run twice; second run must exit 0
DRUPAL_ROOT=/var/www/html/forseti bash scripts/qa-playwright-auth.sh > /dev/null && echo "run1: PASS"
DRUPAL_ROOT=/var/www/html/forseti bash scripts/qa-playwright-auth.sh > /dev/null && echo "run2: PASS (idempotent)"
```
**PASS condition:** Both runs exit 0.

---

### AC-3: Second QA user `qa_tester_authenticated_2` exists via idempotent creation

**Given** a second authenticated test user is needed for cross-user isolation tests,
**When** `scripts/qa-playwright-auth.sh` is run with `--two-users` flag (or equivalent),
**Then** a second Drupal user named `qa_tester_authenticated_2` exists with:
- `status: 1` (active)
- `authenticated` role
- a stub `jobhunter_job_seeker` profile row (needed to access `/jobhunter/*` routes)
- creation is idempotent: running again does not create duplicates

**Implementation note for dev-infra/qa-forseti:**
`jhtr:qa-users-ensure` names users `qa_tester_{role}`. To get a second user of the same role, either:
- Extend `jhtr:qa-users-ensure` with a `--suffix` or `--count` option (preferred; keeps user lifecycle in one place), OR
- Direct `drush user:create qa_tester_authenticated_2 --mail=qa_tester_authenticated_2@qa.local --password=$(openssl rand -hex 8)` + role assignment + jobseeker profile insert

**Verification:**
```bash
DRUPAL_ROOT=/var/www/html/forseti vendor/bin/drush --uri=http://localhost \
  user:information qa_tester_authenticated_2 --format=json 2>/dev/null | python3 -c "
import json, sys
d=json.load(sys.stdin)
uid = list(d.keys())[0]
u = d[uid]
assert u['status'] == '1', 'user inactive'
print('PASS: qa_tester_authenticated_2 exists, uid=' + uid)
"
```
**PASS condition:** Script exits 0, prints "PASS: qa_tester_authenticated_2 exists".

---

### AC-4: `FORSETI_COOKIE_AUTHENTICATED` header produces HTTP 200 on authenticated route

**Given** `FORSETI_COOKIE_AUTHENTICATED` is provisioned by AC-1,
**When** used in a curl request to an auth-required route (`/jobhunter/my-jobs`),
**Then** the response is HTTP 200 (not 403/302).

**Verification:**
```bash
DRUPAL_ROOT=/var/www/html/forseti source <(bash scripts/qa-playwright-auth.sh)
STATUS=$(curl -sS -o /dev/null -w "%{http_code}" \
  -H "Cookie: $FORSETI_COOKIE_AUTHENTICATED" \
  https://forseti.life/jobhunter/my-jobs)
[ "$STATUS" = "200" ] && echo "PASS: authenticated 200" || echo "FAIL: got $STATUS"
```
**PASS condition:** HTTP 200 returned on `/jobhunter/my-jobs` with provisioned cookie.

---

### AC-5: Cross-user isolation test pattern — user A cannot read user B's job records

**Given** `qa_tester_authenticated` (user A) and `qa_tester_authenticated_2` (user B) both exist,
**And** each has at least 1 job application record (or the isolation test asserts 403/empty on cross-user access attempts),
**When** user A's session cookie is used to request a resource scoped to user B's UID,
**Then** the response is either 403 (explicit denial) or an empty record set (no data leakage).

**Test pattern (reference — qa-forseti to implement as TC in relevant suite):**
```bash
# Provision two cookies
DRUPAL_ROOT=/var/www/html/forseti
COOKIE_A=$(DRUPAL_ROOT=$DRUPAL_ROOT bash scripts/qa-playwright-auth.sh --user=qa_tester_authenticated)
COOKIE_B=$(DRUPAL_ROOT=$DRUPAL_ROOT bash scripts/qa-playwright-auth.sh --user=qa_tester_authenticated_2)

# user A tries to access user B's application list:
# Expected: 403 or empty results, never user B's data
STATUS=$(curl -sS -o /dev/null -w "%{http_code}" \
  -H "Cookie: $COOKIE_A" \
  "https://forseti.life/jobhunter/my-jobs")
[ "$STATUS" = "200" ] || [ "$STATUS" = "403" ] && echo "PASS: cross-user boundary holds"
```

**Note on scope:** The specific test case implementation (which route to probe, what response body to check) is qa-forseti's responsibility. This AC only requires the test pattern to be present and the two users to exist. The isolation assertion must be: no job record belonging to user B's UID appears in user A's API/page response.

**PASS condition:** Test case(s) TC-11 or TC-16 pattern exist in `jobhunter-e2e` or a relevant fill suite, asserting cross-user record isolation for at least 1 route.

---

### AC-6: `python3 scripts/qa-suite-validate.py` passes after any suite.json changes

**Given** this feature may involve adding/updating `test_cases` to suites that reference cross-user tests,
**When** validation is run,
**Then** exits 0 with no schema errors.

**Verification:**
```bash
cd /home/ubuntu/forseti.life/copilot-hq && python3 scripts/qa-suite-validate.py
```
**PASS condition:** Exits 0.

---

## Non-goals

- Do NOT modify `jobhunter-e2e` suite command — it already self-provisions auth via `jhtr:qa-users-ensure` + `ULI_URL`
- Do NOT add production passwords to any tracked file
- Do NOT run destructive operations against production data
- Cookie values must never be committed to the git repo

## Open questions (for pm-forseti/dev-infra to resolve)

| ID | Question | Recommended default |
|---|---|---|
| OQ-1 | Should `qa-playwright-auth.sh` delegate to `drupal-qa-sessions.py` internally, or be a separate implementation? | Recommend: thin wrapper around `drupal-qa-sessions.py` to avoid duplicating OTL logic |
| OQ-2 | `qa_tester_authenticated_2` naming: should `jhtr:qa-users-ensure` get a `--count=2` option, or should the auth script create the second user independently? | Recommend: extend `jhtr:qa-users-ensure` with `--count` — keeps user lifecycle in one drush command; cleaner cleanup |
| OQ-3 | Which suite should carry the cross-user isolation test cases (TC-11/TC-16)? `jobhunter-e2e` has run_notes saying these are "flagged for manual execution or future multi-user support". | Recommend: add to `jobhunter-e2e` as additional test_cases once AC-3 is satisfied |

## Definition of done

- [ ] AC-1: `scripts/qa-playwright-auth.sh` exists, is executable, produces non-empty `FORSETI_COOKIE_AUTHENTICATED`
- [ ] AC-2: Script is idempotent (double-run exits 0)
- [ ] AC-3: `qa_tester_authenticated_2` exists in Drupal (verified via `drush user:information`)
- [ ] AC-4: Provisioned cookie returns HTTP 200 on `/jobhunter/my-jobs`
- [ ] AC-5: Cross-user isolation test pattern present in a suite with at least 1 asserting TC
- [ ] AC-6: `python3 scripts/qa-suite-validate.py` exits 0
- [ ] `feature.md` Status → ready
- [ ] `03-test-plan.md` created
