- Status: done
- Completed: 2026-04-11T01:46:58Z

# Suite Activation: forseti-ai-conversation-job-suggestions

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-10T22:06:02+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-ai-conversation-job-suggestions"`**  
   This links the test to the living requirements doc at `features/forseti-ai-conversation-job-suggestions/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-ai-conversation-job-suggestions-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-ai-conversation-job-suggestions",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-ai-conversation-job-suggestions"`**  
   Example:
   ```json
   {
     "id": "forseti-ai-conversation-job-suggestions-<route-slug>",
     "feature_id": "forseti-ai-conversation-job-suggestions",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-ai-conversation-job-suggestions",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-ai-conversation-job-suggestions

- Feature: forseti-ai-conversation-job-suggestions
- Module: ai_conversation
- Author: ba-forseti
- Date: 2026-04-10
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED` (role: authenticated)
- Test user A has ≥ 1 saved job in `jobhunter_saved_jobs`
- Test user B has 0 saved jobs (for fallback tests)
- Chat endpoint: `/api/chat` (POST, JSON body `{"message": "..."}`)

## Test cases

### TC-1: Trigger phrase fires job suggestions (smoke)

- **Type:** functional / smoke
- **Given:** authenticated user A with 2 saved jobs
- **When:** POST `{"message": "show my saved jobs"}` to `/api/chat`
- **Then:** HTTP 200; response JSON contains `job_suggestions` array with 1–2 entries; each entry has `title`, `company`, `link` keys
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" -X POST https://forseti.life/api/chat -H "Content-Type: application/json" -d '{"message":"show my saved jobs"}' | python3 -m json.tool | grep -c '"title"'` → ≥ 1

---

### TC-2: Multiple trigger phrases all fire suggestions

- **Type:** functional / coverage
- **Given:** authenticated user with saved jobs
- **When:** send each trigger phrase in separate requests: "find me a job", "what jobs match", "job suggestions", "recommend a job"
- **Then:** all requests return `job_suggestions` array with ≥ 1 entry
- **Command:** loop through each phrase; confirm response contains `job_suggestions` key

---

### TC-3: Non-trigger phrase does NOT surface job suggestions

- **Type:** functional / negative path
- **Given:** authenticated user with saved jobs
- **When:** POST `{"message": "What's the weather today?"}`
- **Then:** HTTP 200; response JSON does NOT contain `job_suggestions` key (or key is absent/null)
- **Command:** `curl ... -d '{"message":"What is the weather today?"}' | grep -c 'job_suggestions'` → 0

---

### TC-4: Maximum 3 jobs returned

- **Type:** functional / boundary
- **Given:** authenticated user with 10 saved jobs
- **When:** POST trigger phrase
- **Then:** `job_suggestions` array contains exactly 3 entries
- **Command:** response JSON `job_suggestions` array length → 3

---

### TC-5: Results ordered by most recently saved

- **Type:** functional / ordering
- **Given:** user has 5 saved jobs with different `saved_at` timestamps
- **When:** POST trigger phrase
- **Then:** the 3 returned jobs are the 3 most recently saved (by `saved_at DESC`)
- **Command:**
  ```sql
  SELECT job_id FROM jobhunter_saved_jobs WHERE uid=<uid> ORDER BY saved_at DESC LIMIT 3;
  -- Compare job_ids to those in response
  ```

---

### TC-6: User with no saved jobs receives fallback message

- **Type:** functional / fallback
- **Given:** authenticated user B with 0 saved jobs
- **When:** POST trigger phrase
- **Then:** HTTP 200; response contains fallback text (e.g., "haven't saved any jobs"); `job_suggestions` key absent or empty array
- **Command:** `curl ... (as user B) | grep "haven't saved"` → match (or equivalent fallback text)

---

### TC-7: Unauthenticated request returns 403

- **Type:** security / auth gate
- **Given:** no session cookie
- **When:** POST trigger phrase to `/api/chat`
- **Then:** HTTP 403
- **Command:** `curl -s -o /dev/null -w "%{http_code}" -X POST https://forseti.life/api/chat -d '{"message":"show my saved jobs"}'` → `403`

---

### TC-8: Cross-user isolation — no other user's jobs in response

- **Type:** security / data isolation
- **Given:** user A has 3 saved jobs; user B has 0 saved jobs (different uid)
- **When:** user B sends trigger phrase
- **Then:** user B receives fallback message; none of user A's job titles appear in user B's response
- **Command:** confirm response for user B has no `job_suggestions` entries; confirm DB: `SELECT COUNT(*) FROM jobhunter_saved_jobs WHERE uid=<uid_B>` → 0

---

### TC-9: Link in suggestion points to correct saved-job detail URL

- **Type:** functional / link correctness
- **Given:** saved job with `id=77` exists for the user
- **When:** trigger phrase fires and suggestion for job 77 is returned
- **Then:** `link` field in suggestion is `/jobhunter/saved-jobs/77` (or equivalent detail URL)
- **Command:** check `link` value in response JSON matches expected path pattern

---

### TC-10: No watchdog errors on job-suggestion query

- **Type:** regression / observability
- **Given:** any authenticated user triggers job suggestions
- **When:** suggestions are returned
- **Then:** `drush watchdog:show --count=10` shows no new PHP warnings or errors related to `ai_conversation` or `ChatController`
- **Command:** `drush watchdog:show --count=10 --severity=warning` → no new entries after the request

### Acceptance criteria (reference)

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
- Agent: qa-forseti
- Status: pending
