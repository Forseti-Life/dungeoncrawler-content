# Feature Brief

- Work item id: forseti-jobhunter-profile-completeness
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 1
- Group: profile-resume
- Group Title: Profile & Resume
- Group Sort: 3
- Status: shipped
- Release: 20260409-forseti-release-f
- Shipped at: 2026-04-09T13:58:00Z
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: CEO direction 2026-04-09 (Job Hunter UX polish track 1.4)

## Summary

The `profile-completeness.html.twig` template exists and renders a progress bar with percentage. The template already accepts `completeness`, `is_complete`, and `validation_errors` variables. This feature makes it functionally correct by: ensuring the completeness percentage is computed from real profile fields (not hardcoded), surfacing a missing-field checklist with direct edit links to the relevant profile sections, and embedding the completeness widget on both the profile summary page and the job hunter home dashboard.

## Goal

- Users always know their profile completeness percentage when visiting the profile or home dashboard.
- Users see which specific fields are missing with a one-click path to fix them.
- The completeness calculation is deterministic and testable.

## Acceptance criteria

- AC-1: Completeness calculation — a `ProfileCompletenessService` (or equivalent method on existing profile service) computes a 0–100 integer from real field presence: name, email, resume_text, education_history (at least one entry), work_experience (at least one entry), skills (at least one), linkedin_url, and any other fields defined in the completeness spec.
- AC-2: Missing field checklist — when completeness < 100%, the rendered widget shows a list of missing/incomplete fields. Each list item includes a direct `<a>` link to the profile edit section for that field.
- AC-3: Profile summary page — completeness widget embedded at top of `/jobhunter/profile` (or profile summary route).
- AC-4: Job hunter home dashboard — completeness widget embedded on `/jobhunter` or home route if completeness < 100%.
- AC-5: 100% complete state — when all fields are populated, widget shows a "Profile complete" success state (no checklist shown).
- AC-6: Completeness percentage is consistent — calling the service twice with the same profile data returns the same integer.

## Non-goals

- Gamification / badges / streaks.
- Admin-configurable field weights.
- Completeness email nudges.

## Security acceptance criteria

- Authentication/permission surface: Completeness widget is only rendered for the currently authenticated user's own profile. No route exposes completeness data for other users. Profile edit links must point to routes protected by `_user_is_logged_in: 'TRUE'`.
- CSRF expectations: No POST actions on the completeness widget — read-only display. No CSRF token needed.
- Input validation requirements: Completeness score is computed server-side only; no client-supplied completeness value is trusted.
- PII/logging constraints: No profile field content (e.g., resume text, LinkedIn URL) is written to watchdog at any log level.

## Implementation notes (to be authored by dev-forseti)

- New or extended service: `ProfileCompletenessService` in `src/Service/`.
- Template: `profile-completeness.html.twig` — already scaffolded; wire up real data.
- Embed location 1: profile summary controller/template.
- Embed location 2: job hunter home controller/template (`job-hunter-home.html.twig`).
- Completeness spec (field list + weights): document in `02-implementation-notes.md`.

## Test plan (to be authored by qa-forseti)

- TC-1: Profile with all fields → 100%, no checklist, success state shown.
- TC-2: Profile missing resume_text and education_history → percentage < 100%, both fields appear in checklist.
- TC-3: Checklist links point to correct profile edit sections (returns 200 for authenticated user).
- TC-4: Widget embedded on profile summary page → renders without error.
- TC-5: Widget embedded on home dashboard → renders without error; hidden (or not shown) when completeness = 100%.
- TC-6: Anonymous user → completeness widget not rendered (route requires auth).

## Journal

- 2026-04-09: Feature stub created by ba-forseti (CEO dispatch, release-f grooming).
