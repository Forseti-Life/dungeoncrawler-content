# Acceptance Criteria: forseti-jobhunter-interview-prep

- Feature: forseti-jobhunter-interview-prep
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Structured interview prep at `/jobhunter/interview-prep/{job_id}`: static prep checklist, persisted notes (new `jobhunter_interview_notes` table), and optional AI-generated tips. Link from My Jobs interview-stage cards.

## Acceptance criteria

### AC-1: Route and access
- `GET /jobhunter/interview-prep/{job_id}` returns 200 for authenticated users with `access job hunter` permission.
- Anonymous access → 403.
- `{job_id}` must be an integer; non-integer → 404.
- Job must belong to current user; otherwise → 403/404.

### AC-2: Prep checklist
- Page renders a static checklist of at least 5 common interview prep items (e.g., Research the company, Review the job description, Prepare STAR stories, Prepare questions to ask, Plan your logistics).
- Checklist is display-only (checkboxes are local-state only in this release — no DB persistence of check state).

### AC-3: Notes field — save
- A `<textarea>` for free-form notes renders on the page.
- POST to `job_hunter.interview_prep_save` (POST-only, CSRF-guarded) saves notes to `jobhunter_interview_notes` (upsert on uid + job_id).
- Notes capped at 10,000 characters server-side; exceeding this → 400 with error message.
- On success: page reloads with saved notes pre-populated.
- Missing/invalid CSRF → 403.

### AC-4: Notes field — load
- On GET page load: if a notes record exists for (uid, job_id), the textarea is pre-populated with saved notes.

### AC-5: AI tips
- A "Get AI Interview Tips" button POSTs to `job_hunter.interview_prep_ai_tips` (POST-only, CSRF-guarded, AJAX).
- Response: inline render of 3–5 bullet tips without page reload.
- Tips are generated using: job title + first 500 chars of job description + user profile summary.
- Missing/invalid CSRF → 403.
- API error → user-facing error message inline; no white screen.

### AC-6: My Jobs link
- Job cards in the `interview` workflow stage on `/jobhunter/my-jobs` include a "Prepare for Interview" link → `/jobhunter/interview-prep/{job_id}`.

### AC-7: DB schema
- `jobhunter_interview_notes` table created via `hook_update_N`.
- Schema: `id` (serial PK), `uid` (int), `job_id` (int), `notes_text` (text, big), `updated` (int).
- Unique key: (`uid`, `job_id`).

### AC-8: CSRF guards
- `grep -A5 "job_hunter.interview_prep_save" job_hunter.routing.yml` shows `_csrf_token: 'TRUE'`.
- `grep -A5 "job_hunter.interview_prep_ai_tips" job_hunter.routing.yml` shows `_csrf_token: 'TRUE'`.

## Security acceptance criteria

### Authentication/permission surface
- Route requires `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`.
- All DB reads/writes scoped to `uid == current_user->id()`.
- Verify: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/interview-prep/1` → 403 (anonymous).

### CSRF expectations
- Notes save and AI tips: POST-only, `_csrf_token: 'TRUE'` (split-route pattern).
- GET route: no CSRF.

### Input validation requirements
- `{job_id}`: integer only. Non-integer → 404.
- Notes textarea: max 10,000 chars server-side; HTML tags stripped before storage.
- AI tips endpoint: job_id resolved server-side; no user-supplied text passed to AI.

### PII/logging constraints
- Notes content must NOT be logged to watchdog.
- AI tip content must NOT be logged. Error events may log job_id + error code.

## Verification commands
```bash
# CSRF guards
grep -A8 "job_hunter.interview_prep_save" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml
grep -A8 "job_hunter.interview_prep_ai_tips" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml

# DB table created
cd /home/ubuntu/forseti.life/sites/forseti && ./vendor/bin/drush php:eval "var_dump(\Drupal::database()->schema()->tableExists('jobhunter_interview_notes'));"

# Anonymous access
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/interview-prep/1
# Expected: 403
```
