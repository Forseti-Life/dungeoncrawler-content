# Test Plan: forseti-jobhunter-application-deadline-tracker

- Feature: forseti-jobhunter-application-deadline-tracker
- Module: job_hunter
- Author: qa-forseti
- Date: 2026-04-11
- QA owner: qa-forseti
- Status: GROOMING — do NOT activate to suite.json until Stage 0 of next release
- KB reference: none found (new pattern; closest prior: forseti-jobhunter-application-notes for POST form + CSRF + ownership guard pattern)

## Prerequisites (for execution at Stage 0)

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED` (role: authenticated, has `access job hunter`)
- Second authenticated user session in `$FORSETI_COOKIE_USER_B` (different uid, also has `access job hunter`, with at least 1 saved job)
- Test job ID: `$TEST_JOB_ID` (integer, belongs to user A)
- Cross-user job ID: `$TEST_JOB_ID_B` (integer, belongs to user B)
- Base URL: `$FORSETI_BASE_URL` (default: `https://forseti.life`)

---

## Test Cases

### TC-1: Anon GET job page → 403

- **AC:** Failure Mode — Anonymous GET `/jobhunter/job/{job_id}` → 403
- **Suite:** `role-url-audit`
- **Type:** security / auth gate
- **Command:**
  ```bash
  curl -s -o /dev/null -w "%{http_code}" "$FORSETI_BASE_URL/jobhunter/job/$TEST_JOB_ID"
  ```
- **Expected:** `403`
- **Roles covered:** anonymous
- **Automatable:** yes

---

### TC-2: Anon GET /jobhunter/deadlines → 403

- **AC:** Failure Mode — Anonymous GET `/jobhunter/deadlines` → 403
- **Suite:** `role-url-audit`
- **Type:** security / auth gate
- **Command:**
  ```bash
  curl -s -o /dev/null -w "%{http_code}" "$FORSETI_BASE_URL/jobhunter/deadlines"
  ```
- **Expected:** `403`
- **Roles covered:** anonymous
- **Automatable:** yes

---

### TC-3: Anon POST date save → 403

- **AC:** Failure Mode — Anonymous POST date save → 403
- **Suite:** `role-url-audit`
- **Type:** security / auth gate
- **Command:**
  ```bash
  curl -s -o /dev/null -w "%{http_code}" -X POST "$FORSETI_BASE_URL/jobhunter/job/$TEST_JOB_ID/dates"
  ```
- **Expected:** `403`
- **Roles covered:** anonymous
- **Automatable:** yes

---

### TC-4: Auth GET job page shows date form

- **AC:** AC-1 — Authenticated user sees `deadline_date` and `follow_up_date` fields
- **Suite:** `role-url-audit` (HTTP 200 check) + Playwright (field presence)
- **Type:** functional / smoke
- **Command (HTTP check):**
  ```bash
  curl -s -o /dev/null -w "%{http_code}" -b "$FORSETI_COOKIE_AUTHENTICATED" "$FORSETI_BASE_URL/jobhunter/job/$TEST_JOB_ID"
  ```
- **Expected HTTP:** `200`
- **Playwright assertion:** page contains input fields with names `deadline_date` and `follow_up_date`
- **Roles covered:** authenticated
- **Automatable:** HTTP check yes; field presence requires Playwright

---

### TC-5: Valid date submission saves to DB and shows confirmation

- **AC:** AC-2 — POST saves dates; confirmation shown; re-GET shows saved dates
- **Suite:** Playwright
- **Type:** functional / happy path
- **Steps:**
  1. POST valid `deadline_date` and `follow_up_date` to date save route
  2. Verify response contains confirmation message
  3. GET job page again; verify dates appear in form fields
- **Command (CSRF + POST):**
  ```bash
  # POST with CSRF token (token must be fetched from GET response)
  curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" -X POST "$FORSETI_BASE_URL/jobhunter/job/$TEST_JOB_ID/dates" \
    -d "deadline_date=2026-05-01&follow_up_date=2026-04-20&token=$CSRF_TOKEN"
  ```
- **Expected:** HTTP 200, confirmation message in response, DB row present
- **DB verify:**
  ```sql
  SELECT deadline_date, follow_up_date FROM jobhunter_job_deadlines WHERE job_id = <TEST_JOB_ID> AND uid = <uid>;
  ```
- **Roles covered:** authenticated
- **Automatable:** CSRF-aware curl or Playwright required; note Playwright preferred for full flow

---

### TC-6: Blank date fields save NULL; no data loss on existing records

- **AC:** AC-5 — Submitting blank dates saves NULL; existing records unaffected
- **Suite:** Playwright
- **Type:** functional / edge case
- **Steps:**
  1. POST blank `deadline_date` and `follow_up_date`
  2. Verify no server error; form reloads cleanly
  3. DB: date columns are NULL (not empty string, not error)
- **Expected:** HTTP 200, DB columns NULL
- **Roles covered:** authenticated
- **Automatable:** Playwright preferred; SQL verification required

---

### TC-7: Dashboard urgency indicator — overdue (red)

- **AC:** AC-3 — Overdue jobs show red indicator on `/jobhunter/status`
- **Suite:** Playwright
- **Type:** functional / UI state
- **Steps:**
  1. Set `deadline_date` = yesterday for test job
  2. GET `/jobhunter/status`
  3. Verify the job row contains a red urgency indicator (CSS class or element)
- **Expected:** HTTP 200; overdue job row has red indicator
- **Roles covered:** authenticated
- **Automatable:** Playwright (CSS class check); cannot be done with curl alone
- **Note to PM:** exact CSS class / element name is dev-owned; QA will inspect rendered HTML at Stage 0

---

### TC-8: Dashboard urgency indicator — due within 3 days (amber)

- **AC:** AC-3 — Jobs due within 3 days show amber indicator
- **Suite:** Playwright
- **Type:** functional / UI state
- **Steps:**
  1. Set `deadline_date` = today + 2 days for test job
  2. GET `/jobhunter/status`
  3. Verify amber indicator present
- **Expected:** amber indicator rendered
- **Roles covered:** authenticated
- **Automatable:** Playwright only
- **Note to PM:** same as TC-7 — exact element TBD at Stage 0

---

### TC-9: /jobhunter/deadlines returns 200 and lists jobs sorted ascending

- **AC:** AC-4 — Authenticated GET `/jobhunter/deadlines` → 200; jobs with dates sorted ascending
- **Suite:** `role-url-audit` (HTTP 200) + Playwright (ordering)
- **Type:** functional / ordering
- **Command (HTTP check):**
  ```bash
  curl -s -o /dev/null -w "%{http_code}" -b "$FORSETI_COOKIE_AUTHENTICATED" "$FORSETI_BASE_URL/jobhunter/deadlines"
  ```
- **Expected HTTP:** `200`
- **Playwright assertion:** first listed job has the earliest `deadline_date` among test data
- **Roles covered:** authenticated
- **Automatable:** HTTP check yes; ordering check requires Playwright

---

### TC-10: /jobhunter/deadlines empty state — no blank page

- **AC:** Edge Case — `/jobhunter/deadlines` with no dates set shows empty state message
- **Suite:** Playwright
- **Type:** functional / empty state
- **Steps:**
  1. Ensure test user has 0 jobs with `deadline_date` set
  2. GET `/jobhunter/deadlines`
  3. Verify response contains an empty state message (not a blank page or 500)
- **Expected:** HTTP 200; empty state text present
- **Roles covered:** authenticated
- **Automatable:** Playwright (text assertion)
- **Note to PM:** exact empty state message wording is dev-owned

---

### TC-11: CSRF missing on POST date save → 403

- **AC:** AC-6 / Security — POST without CSRF token → 403
- **Suite:** `role-url-audit`
- **Type:** security / CSRF
- **Command:**
  ```bash
  curl -s -o /dev/null -w "%{http_code}" -X POST \
    -b "$FORSETI_COOKIE_AUTHENTICATED" \
    "$FORSETI_BASE_URL/jobhunter/job/$TEST_JOB_ID/dates" \
    -d "deadline_date=2026-05-01&follow_up_date=2026-04-20"
  ```
- **Expected:** `403` (not 500)
- **Roles covered:** authenticated (missing CSRF)
- **Automatable:** yes

---

### TC-12: Cross-user date mutation → 403

- **AC:** AC-7 / Security — User A cannot save dates to user B's job
- **Suite:** Playwright (requires two sessions)
- **Type:** security / ownership isolation
- **Steps:**
  1. As user A, POST date save to `TEST_JOB_ID_B` (belongs to user B)
  2. Verify 403 or equivalent ownership rejection
- **Command:**
  ```bash
  curl -s -o /dev/null -w "%{http_code}" -X POST \
    -b "$FORSETI_COOKIE_AUTHENTICATED" \
    "$FORSETI_BASE_URL/jobhunter/job/$TEST_JOB_ID_B/dates" \
    -d "deadline_date=2026-05-01&token=$CSRF_TOKEN"
  ```
- **Expected:** `403`
- **Roles covered:** authenticated (cross-uid attempt)
- **Automatable:** yes (with two test users)

---

### TC-13: Non-integer job_id → 404

- **AC:** Edge Case — Non-integer `{job_id}` → 404
- **Suite:** `role-url-audit`
- **Type:** functional / input validation
- **Command:**
  ```bash
  curl -s -o /dev/null -w "%{http_code}" -b "$FORSETI_COOKIE_AUTHENTICATED" "$FORSETI_BASE_URL/jobhunter/job/not-a-number"
  ```
- **Expected:** `404`
- **Roles covered:** authenticated
- **Automatable:** yes

---

### TC-14: Invalid date string → form error, no DB write

- **AC:** Edge Case — Invalid date string → form error shown, no DB write
- **Suite:** Playwright
- **Type:** functional / input validation
- **Steps:**
  1. POST `deadline_date=not-a-date` with valid CSRF
  2. Verify response shows form validation error
  3. DB: no row inserted or updated with invalid value
- **Expected:** HTTP 200 or 422; form error message visible; DB unchanged
- **Roles covered:** authenticated
- **Automatable:** Playwright preferred

---

### TC-15: Date values not logged to watchdog

- **AC:** Security — Date values do not appear in watchdog log entries
- **Suite:** Playwright + drush verification
- **Type:** security / PII / observability
- **Steps:**
  1. Submit valid dates as authenticated user
  2. Run `vendor/bin/drush watchdog:show --count=10` (from `/var/www/html/forseti`)
  3. Verify no log entries contain the submitted date values
- **Expected:** watchdog shows 0 entries with date strings in content
- **Roles covered:** authenticated
- **Automatable:** partially (drush check automatable; visual scan of watchdog output)

---

## AC items flagged as requiring PM decision

| Item | Flag | Reason |
|---|---|---|
| AC-3 urgency indicator | Note to PM | CSS class / element name for red/amber indicators is dev-owned; QA cannot finalize Playwright selector until Dev chooses implementation. Request Dev document in implementation notes. |
| AC-3 "within 3 days" boundary | Note to PM | Confirm: is 3 days inclusive of today? E.g., does a deadline_date = today count as "within 3 days" or "overdue"? Dev needs to clarify boundary in implementation notes. |
| AC-2 confirmation message | Note to PM | Exact wording of confirmation message is dev-owned; QA will adapt Playwright assertion at Stage 0. |

---

## Suite mapping summary

| TC | Suite | Activatable with curl/role-audit? |
|---|---|---|
| TC-1 | role-url-audit | yes |
| TC-2 | role-url-audit | yes |
| TC-3 | role-url-audit | yes |
| TC-4 (HTTP) | role-url-audit | yes |
| TC-4 (fields) | playwright | no — needs Playwright |
| TC-5 | playwright | CSRF-aware curl partial; Playwright preferred |
| TC-6 | playwright | no — needs DB check |
| TC-7 | playwright | no — UI state check |
| TC-8 | playwright | no — UI state check |
| TC-9 (HTTP) | role-url-audit | yes |
| TC-9 (ordering) | playwright | no |
| TC-10 | playwright | no — text assertion |
| TC-11 | role-url-audit | yes |
| TC-12 | role-url-audit | yes (with two test sessions) |
| TC-13 | role-url-audit | yes |
| TC-14 | playwright | no — form error + DB check |
| TC-15 | playwright + drush | partial |

**curl/role-audit ready at Stage 0:** TC-1, TC-2, TC-3, TC-4 (HTTP), TC-9 (HTTP), TC-11, TC-12, TC-13 — 8 entries
**Playwright required:** TC-4 (fields), TC-5, TC-6, TC-7, TC-8, TC-9 (ordering), TC-10, TC-14, TC-15 — 9 entries
