# Acceptance Criteria: forseti-ai-conversation-job-suggestions

- Feature: forseti-ai-conversation-job-suggestions
- Module: ai_conversation
- Author: ba-forseti
- Date: 2026-04-10

## Summary

Extend `ChatController` (and/or `AIApiService`) so that when a logged-in user's message contains job-search intent keywords, the assistant response includes up to 3 saved-job cards from `jobhunter_saved_jobs` for that user. If the user has no saved jobs, a graceful fallback message is shown. The `ChatController` already queries `jobhunter_job_seeker` and `jobhunter_job_history` for context; this extends that pattern.

## Intent detection keywords (baseline; dev may tune)

Trigger phrase patterns (case-insensitive, any match fires):
- "find me a job", "show my jobs", "what jobs match", "my saved jobs", "job suggestions", "recommend a job", "which jobs", "jobs for me"

## Acceptance criteria

### AC-1: Job-suggestion intent triggers saved-job surfacing

**Given** a logged-in user sends a chat message containing a trigger phrase (e.g., "show my saved jobs"),
**When** `ChatController` processes the message,
**Then** the assistant response includes a structured section listing up to 3 of the user's saved jobs, each with: job title, company name, and a link to the saved-job detail view (`/jobhunter/saved-jobs/{saved_job_id}`).

**Verify:** Authenticated POST to `/api/chat` with body `{"message":"show my saved jobs"}`; response JSON contains `job_suggestions` array with 1–3 entries, each having `title`, `company`, and `link` keys.

---

### AC-2: No cross-user data leak — suggestions scoped to current user

**Given** user A has 3 saved jobs and user B has 0 saved jobs,
**When** user B sends a trigger phrase,
**Then** user B receives the fallback message (AC-3), not user A's jobs.

**Verify:**
```sql
SELECT COUNT(*) FROM jobhunter_saved_jobs WHERE uid = <uid_B>;
-- Expect 0
```
Response for user B contains fallback text, not any job cards.

---

### AC-3: Graceful fallback when user has no saved jobs

**Given** the authenticated user has zero rows in `jobhunter_saved_jobs`,
**When** they send a trigger phrase,
**Then** the assistant responds: "You haven't saved any jobs yet. Visit /jobhunter/discover to find jobs." (or equivalent). No error or empty array is surfaced to the user.

**Verify:** send trigger phrase as a user with 0 saved jobs; response text contains "haven't saved any jobs" (or similar fallback, exact wording is dev-owned).

---

### AC-4: Non-trigger messages are unaffected

**Given** a user sends a message with no trigger phrases (e.g., "What's the weather?"),
**When** `ChatController` processes the message,
**Then** the response does NOT include a `job_suggestions` section; the chat behaves as before this feature.

**Verify:** POST `{"message":"What is the weather today?"}` → response JSON has no `job_suggestions` key or the key is absent/null.

---

### AC-5: Maximum 3 jobs returned (recency order)

**Given** the user has 10 saved jobs,
**When** the suggestion query runs,
**Then** exactly 3 are returned, ordered by most recently saved (`jobhunter_saved_jobs.saved_at DESC` or equivalent).

**Verify:** `drush sql:query "SELECT COUNT(*) FROM jobhunter_saved_jobs WHERE uid=<uid>"` → 10; response contains exactly 3 job cards.

---

## Security acceptance criteria

### SEC-1: Authentication required

The `/api/chat` endpoint already requires `_user_is_logged_in: 'TRUE'`. Verify this gate covers the new job-suggestions code path. Unauthenticated requests must not reach the job-suggestion query.

**Verify:** `curl -s -o /dev/null -w "%{http_code}" -X POST https://forseti.life/api/chat -d '{"message":"show my saved jobs"}'` → `403` (no session cookie).

### SEC-2: No CSRF change needed on existing chat endpoint

The chat endpoint already has CSRF handling. Do NOT change the existing CSRF posture. If a new endpoint is introduced for suggestions (separate route), it must include `_csrf_token: 'TRUE'` on POST-only route entry.

### SEC-3: Job data scoped strictly to current user

The DB query for saved jobs MUST include `WHERE uid = \Drupal::currentUser()->id()`. No admin-bypass or uid parameter accepted from user input.

**Verify:** code review — `grep -n "currentUser" sites/forseti/web/modules/custom/ai_conversation/src/Controller/ChatController.php` confirms uid-scoped query.

### SEC-4: No PII in logs

Job titles and company names in suggestions must NOT be logged to watchdog at DEBUG/NOTICE level. Log only `uid` and suggestion count for audit events.

### SEC-5: No SQL injection — parameterized queries only

The saved-job lookup must use Drupal's database abstraction layer (`$db->select(...)` with `->condition()`), not string-interpolated SQL.
