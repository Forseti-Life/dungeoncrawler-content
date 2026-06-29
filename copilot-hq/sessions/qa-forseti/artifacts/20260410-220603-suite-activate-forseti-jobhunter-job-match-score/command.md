- Status: done
- Completed: 2026-04-11T00:02:23Z

# Suite Activation: forseti-jobhunter-job-match-score

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-10T22:06:03+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-job-match-score"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-job-match-score/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-job-match-score-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-job-match-score",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-job-match-score"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-job-match-score-<route-slug>",
     "feature_id": "forseti-jobhunter-job-match-score",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-job-match-score",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-job-match-score

- Feature: forseti-jobhunter-job-match-score
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-10
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED` (role: authenticated)
- Test user A has skills recorded in `jobhunter_job_seeker.skills` (e.g., "Python, SQL, Django")
- Test user B has NO skills recorded (NULL or empty)
- At least one saved job with requirements data in `jobhunter_job_requirements`
- At least one saved job with NO requirements data (for edge-case test)

## Test cases

### TC-1: Match score badge visible on /jobhunter/my-jobs (smoke)

- **Type:** functional / smoke
- **Given:** authenticated user A with skills and at least one saved job with requirements
- **When:** GET `/jobhunter/my-jobs`
- **Then:** page HTML contains match score badge markup (e.g., `match-score` CSS class or `data-match-score` attribute) for at least one job card
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -c 'match-score'` → ≥ 1

---

### TC-2: Score is non-zero when user has matching skills

- **Type:** functional / happy path
- **Given:** user has skill "Python"; saved job description contains "Python developer"
- **When:** GET `/jobhunter/my-jobs`
- **Then:** match score for that job is > 0%
- **Command:** page HTML contains badge with value > 0 for the Python job card

---

### TC-3: Score is 0% when no skill overlap

- **Type:** functional / no-match path
- **Given:** user has skills "Python, SQL"; job description mentions only "Java, Kubernetes"
- **When:** GET `/jobhunter/my-jobs`
- **Then:** match score badge shows 0% for that job
- **Command:** inspect badge value in page HTML for a non-matching job → "0%"

---

### TC-4: Different users see different scores for the same job

- **Type:** functional / user isolation
- **Given:** user A has "Python, SQL"; user B has "JavaScript, React"; same saved job is Python-focused
- **When:** both users load `/jobhunter/my-jobs`
- **Then:** user A's score for that job is higher than user B's score
- **Command:** compare badge values rendered for each user (or inspect page source per user session)

---

### TC-5: Score updates after profile skills are edited

- **Type:** functional / state refresh
- **Given:** user has skills "Python" (score = X for a job)
- **When:** user adds "SQL" to skills via profile edit, then reloads `/jobhunter/my-jobs`
- **Then:** score for the SQL-mentioning job changes (increases)
- **Command:** record score before and after skills update; confirm they differ

---

### TC-6: Scores are embedded server-side (no extra AJAX fetch required)

- **Type:** functional / render model
- **Given:** authenticated user loads `/jobhunter/my-jobs`
- **When:** page HTML is parsed
- **Then:** match score values are present in the initial HTML response — no pending `fetch()` or `XMLHttpRequest` required to populate them
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep 'match-score'` → present in initial response (not a placeholder like "loading…")

---

### TC-7: Graceful fallback — user with no skills sees 0% or profile prompt

- **Type:** functional / fallback
- **Given:** user B has NULL/empty skills
- **When:** GET `/jobhunter/my-jobs`
- **Then:** each job card shows "0% match" badge or "Complete your profile to see match scores" prompt — no blank badge or PHP error
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED_B" https://forseti.life/jobhunter/my-jobs | grep -E '0%|Complete your profile'` → match

---

### TC-8: No error when job has no requirements data

- **Type:** regression / edge case
- **Given:** a saved job has no row in `jobhunter_job_requirements`
- **When:** GET `/jobhunter/my-jobs` (which includes that job card)
- **Then:** score for that job renders as 0%; no PHP warning or watchdog error
- **Command:** `drush watchdog:show --count=10 --severity=warning` after page load → no new warnings from job_hunter module

---

### TC-9: Score values are in range [0, 100]

- **Type:** validation / boundary
- **Given:** any user with any skill set and any saved job
- **When:** page loads
- **Then:** all badge values are integers between 0 and 100 inclusive
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -oP 'data-match-score="\K[^"]*'` → all values 0–100

---

### TC-10: Raw skills text not exposed in page HTML attributes

- **Type:** security / data exposure
- **Given:** user has skills "Python, SQL, Django" in their profile
- **When:** GET `/jobhunter/my-jobs`
- **Then:** page source does NOT contain the raw skills string in any HTML attribute or JavaScript variable
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -c 'Python, SQL, Django'` → 0

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-job-match-score

- Feature: forseti-jobhunter-job-match-score
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-10

## Summary

Surface a match score (0–100%) on each saved-job card at `/jobhunter/my-jobs`. Score is computed server-side using keyword intersection between the user's skills field (`jobhunter_job_seeker.skills`) and the job's requirements/description tokens from `jobhunter_job_requirements`. `ProfileCompletenessService` already extracts the skills list; this feature adds a scoring function and a badge on each job card.

## Score computation model (baseline)

- Tokenize user skills (comma-separated or space-separated list from `jobhunter_job_seeker.skills`).
- Tokenize job description/requirements text (from `jobhunter_job_requirements.description` or equivalent).
- Score = (# skill tokens found in job text) / (# user skill tokens) × 100, clamped to [0, 100].
- If user has no skills on profile, score is 0 with a prompt to complete profile (see AC-5).
- Implementation detail (tokenization strategy) is Dev-owned; AC specifies observable output only.

## Acceptance criteria

### AC-1: Match score badge visible on saved-job cards

**Given** a logged-in user has at least one saved job and has skills recorded in their profile,
**When** they load `/jobhunter/my-jobs`,
**Then** each saved-job card displays a match score badge (e.g., "72% match") computed from their profile skills vs. the job description.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'match-score'`

---

### AC-2: Score is user-scoped — different users see different scores for the same job

**Given** user A has skills "Python, SQL" and user B has skills "JavaScript, React",
**When** both view the same saved job (e.g., a Python developer role),
**Then** user A's badge shows a higher score than user B's badge for that job.

**Verify:** query `jobhunter_job_seeker.skills` for each uid; compare rendered badge values in the page HTML.

---

### AC-3: Score updates when profile skills are updated

**Given** the user adds new skills to their profile,
**When** they reload `/jobhunter/my-jobs`,
**Then** the match score badges reflect the updated skills (no stale cache from prior session without new skills).

**Verify:** update skills via profile edit, reload `/jobhunter/my-jobs`; confirm score badges changed.

---

### AC-4: Score is computed on page load (no separate async request required)

**Given** a user loads `/jobhunter/my-jobs`,
**When** the page renders,
**Then** scores are already embedded in the HTML (server-side render). No secondary AJAX call is required to populate scores.

**Verify:** page source HTML contains `data-match-score` attributes (or equivalent badge markup) without any pending async fetch.

---

### AC-5: Graceful fallback when user profile has no skills

**Given** a logged-in user has no skills recorded (`jobhunter_job_seeker.skills` is NULL or empty),
**When** they load `/jobhunter/my-jobs`,
**Then** each job card shows "0% match" or a "Complete your profile to see match scores" prompt — no error or missing badge.

**Verify:** clear skills for test user; reload page; badge area shows fallback text, not a blank/broken element.

---

### AC-6: Score does not error on jobs with no requirements text

**Given** a saved job has no requirements data in `jobhunter_job_requirements`,
**When** the scoring function runs for that job,
**Then** the score is 0% with no PHP warning or Drupal watchdog error.

**Verify:** `drush watchdog:show --count=20` after loading `/jobhunter/my-jobs` shows no new PHP warnings related to score computation.

---

## Security acceptance criteria

### SEC-1: Authentication required

The `/jobhunter/my-jobs` route already requires `_user_is_logged_in: 'TRUE'`. The score computation code must not be invoked for unauthenticated requests.

### SEC-2: No new input surface

Match score is computed purely from DB data (user's own skills + job description). No user-supplied query parameter controls score computation. No injection surface is introduced.

### SEC-3: User data scoped to current user

The skills query MUST use `WHERE uid = \Drupal::currentUser()->id()`. No uid parameter may be accepted from the URL or POST body.

**Verify:** code review — skills query uses `currentUser()->id()`.

### SEC-4: No sensitive data in rendered HTML

The match score badge must show only the numeric score (0–100%). Raw skills text or job description tokens must NOT be embedded in HTML attributes or JS variables visible to the browser.

**Verify:** page source grep for `skills=` or `description=` in data attributes → absent.

### SEC-5: No PII / logging constraints

Score values and job data must NOT be logged to watchdog at DEBUG level. Log only `uid` and job count for performance monitoring.
- Agent: qa-forseti
- Status: pending
