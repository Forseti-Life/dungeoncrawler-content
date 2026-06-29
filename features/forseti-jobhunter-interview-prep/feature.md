# Feature Brief

- Work item id: forseti-jobhunter-interview-prep
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 5
- Group: interviews-offers
- Group Title: Interviews, Offers & Career
- Group Sort: 1
- Status: shipped
- Release: 20260409-forseti-release-g
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO direction 2026-04-09 (interview prep track), BA grooming 2026-04-09

## Summary

The existing route `/jobhunter/interview-followup` (`ApplicationSubmissionController::interviewFollowup`) exists but is underdeveloped. Users who reach the "interview" workflow status have no structured tooling. This feature adds: a per-job interview prep checklist (common questions, company research prompts), a free-form notes field persisted per job, and an optional AI-generated prep tip using the job description and user profile as context. The notes are stored in a new `jobhunter_interview_notes` table (simple schema: `uid`, `job_id`, `notes_text`, `updated`).

## Goal

- Users preparing for an interview can access a structured checklist and take notes on a per-job basis.
- Notes persist across sessions.
- Users can optionally request AI-generated interview tips specific to the job posting.

## Acceptance criteria

- AC-1: Route `/jobhunter/interview-prep/{job_id}` renders for authenticated users with the target job in their My Jobs list.
- AC-2: Checklist section — renders a static list of common interview prep prompts (research company, review job description, prepare STAR answers, prepare questions to ask). Prompts are hardcoded for this release; configurable in a later release.
- AC-3: Notes field — a textarea allowing the user to save free-form notes. On form submit (POST, CSRF-guarded), notes are saved to `jobhunter_interview_notes`. On subsequent page loads, saved notes are pre-populated.
- AC-4: AI tips button — a "Get AI Interview Tips" button POSTs to an AJAX endpoint that calls the AI service with job title + first 500 chars of job description + user profile summary; response rendered inline without page reload.
- AC-5: Link from My Jobs — job cards in the `interview` workflow stage include a "Prepare for Interview" link pointing to `/jobhunter/interview-prep/{job_id}`.
- AC-6: Access control — users can only view/edit notes for their own jobs.

## Non-goals

- Interview scheduling / calendar integration (deferred).
- Sharing prep notes with another user.
- Structured question-and-answer tracking (deferred).

## Security acceptance criteria

- Authentication/permission surface: Route requires `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`. Controller scopes all DB queries to `uid == current_user->id()`. Anonymous access → 403.
- CSRF expectations: Notes save is POST-only with `_csrf_token: 'TRUE'`. AI tips AJAX endpoint is POST-only with `_csrf_token: 'TRUE'`. GET route has no CSRF.
- Input validation requirements: `{job_id}` must be an integer. Notes textarea capped at 10,000 characters server-side. AI tip endpoint input (job_id) must be an integer; no user-supplied text is passed directly to the AI — only server-resolved job description and profile fields.
- PII/logging constraints: Notes content must NOT be logged to watchdog. AI tip request/response content must not be logged. Error messages may log job_id and error code only.

## Implementation notes (to be authored by dev-forseti)

- New DB table: `jobhunter_interview_notes` (`id`, `uid`, `job_id`, `notes_text`, `updated`) — add via `hook_update_N` in `job_hunter.install`. Unique key on (`uid`, `job_id`).
- New routes: `job_hunter.interview_prep` (GET), `job_hunter.interview_prep_save` (POST + CSRF), `job_hunter.interview_prep_ai_tips` (POST + CSRF, AJAX).
- The existing `job_hunter.interview_followup` route can be kept as-is or redirected; do not break it.
- AI call: use existing `AIApiService` (from `ai_conversation` module). Pass context as a brief system message.

## Test plan (to be authored by qa-forseti)

- TC-1: Anonymous access → 403.
- TC-2: Page load for an "interview" stage job → 200, checklist and notes textarea rendered.
- TC-3: Save notes → POST succeeds; reload shows saved notes pre-populated.
- TC-4: Save notes with invalid CSRF → 403.
- TC-5: AI tips button → inline response rendered; no page reload.
- TC-6: AI tips AJAX endpoint — invalid CSRF → 403.
- TC-7: Cross-user notes access → 403/404.
- TC-8: Notes over 10,000 chars → server rejects with 400 or truncates with user message.
- TC-9: My Jobs "interview" stage job card has "Prepare for Interview" link.

## Journal

- 2026-04-09: Feature stub created by ba-forseti (pm-forseti dispatch, release-g grooming).
