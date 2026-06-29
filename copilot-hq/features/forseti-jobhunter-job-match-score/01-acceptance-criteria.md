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
