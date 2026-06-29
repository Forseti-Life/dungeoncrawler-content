# forseti release-g change list

- Release: 20260409-forseti-release-g
- Site: forseti.life
- PM: pm-forseti
- Activated: 2026-04-09T14:37:00Z

## Features in scope (5)

1. **forseti-jobhunter-cover-letter-display** (ROI 20)
   - Display surface for generated cover letters (`/jobhunter/cover-letter/{job_id}`)
   - Backend (queue worker + DB table) already exists; this is UI-only
   - Copy-to-clipboard; generating/failed states

2. **forseti-jobhunter-interview-prep** (ROI 18)
   - Interview prep checklist + notes per job, visible when `workflow_status = interview_scheduled`
   - New DB table `jobhunter_interview_prep`
   - CSRF split-route for save POST

3. **forseti-ai-conversation-history-browser** (ROI 15)
   - Browsable conversation history sidebar on `/forseti/chat`
   - Load/resume past conversations; "New conversation" button
   - Cap at 20 most-recent; load-more link

4. **forseti-jobhunter-saved-search** (ROI 12)
   - Save/list/delete Google Jobs search params for one-click re-run
   - Max 10 per user; new `jobhunter_saved_searches` table
   - CSRF split-route for save/delete POST

5. **forseti-ai-conversation-export** (ROI 10)
   - Plain-text export of current `/forseti/chat` conversation
   - GET route `/forseti/chat/{conversation_id}/export`
   - Ownership check; system messages excluded

## Decisions recorded

- No route changes required for this release (all new routes under existing prefixes)
- `forseti-jobhunter-cover-letter-display` depends on existing `jobhunter_cover_letters` table — dev must verify schema is current before implementing display

## KB reference

- Release-f MEDIUM finding (bulk archive global mutation): fixed at `3116c4187`. Per-user archive now via `jobhunter_saved_jobs.archived`. Watch for regression in QA suites this cycle.
