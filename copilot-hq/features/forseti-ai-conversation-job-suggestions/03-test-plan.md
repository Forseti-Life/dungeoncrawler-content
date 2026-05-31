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
