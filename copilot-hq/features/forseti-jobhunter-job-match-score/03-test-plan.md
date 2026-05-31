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
