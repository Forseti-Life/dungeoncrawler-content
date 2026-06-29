# Release Notes: 20260409-forseti-release-f

- Site: forseti.life
- Release operator: pm-forseti
- Scope activated: 2026-04-09T12:10 UTC

## Features in scope (5)

1. `forseti-jobhunter-application-status-dashboard` — My Jobs pipeline view at `/jobhunter/my-jobs` with status/company filters, bulk archive, pagination
2. `forseti-jobhunter-resume-tailoring-display` — Polish resume tailoring result at `/jobhunter/jobtailoring/{job_id}` with side-by-side view, PDF download, save-to-profile
3. `forseti-jobhunter-google-jobs-ux` — UX improvements to Google Jobs search/filter integration
4. `forseti-jobhunter-profile-completeness` — Profile completeness indicator and prompts for missing fields
5. `forseti-ai-conversation-user-chat` — Dedicated AI chat page at `/forseti/chat` with session history and job-seeker context injection

## Route decision (pm-forseti)
- `forseti-ai-conversation-user-chat`: existing `/ai-chat` route will redirect to `/forseti/chat` to avoid duplicate entry points. No Board escalation required per CEO direction.

## KB reference
- None found specific to these features; prior lessons on CSRF split-route pattern (`forseti-csrf-fix`) apply to `forseti-ai-conversation-user-chat` if POST routes are added.
