Now I have a complete picture. Let me plan and implement:

1. Create `ProfileCompletenessService` (DB-backed, using `jobhunter_job_seeker`, `jobhunter_education_history`, `jobhunter_job_history`)
2. Register service in `services.yml`
3. Update `profile_completeness` theme hook to add `missing_fields`
4. Rewrite `profile-completeness.html.twig` to show checklist with links
5. Update `profileDashboard()` to use new service
6. Embed widget on home dashboard (AC-4)

Now I have all the context. Let me implement:

Now register in services.yml and update the theme hook:

Now update the `profile_completeness` theme hook in `job_hunter.module` to add `missing_fields`:

Now update the Twig template to render the missing-field checklist with edit links:

Now update `UserProfileController::profileDashboard()` to use the new service:

Now I'll update `profileDashboard()` to use `ProfileCompletenessService` and pass `missing_fields`:

Now embed the completeness widget on the home dashboard. Let me check `buildAuthenticatedView()`:

I need to add the completeness widget to `buildAuthenticatedView()`. I'll add it after welcome, using `ProfileCompletenessService`:

Now PHP lint and drush cr:

All clean. Run drush cr:

Now verify the service is registered and the template syntax is good, then commit:

Service is functioning correctly. Now verify anonymous access still returns 403 on profile route:

Anonymous gets 403. Now commit:

Done. Now write the outbox:

---

- Status: done
- Summary: Implemented the profile completeness indicator feature end-to-end. Created `ProfileCompletenessService` that computes a 0–100% score from five DB-backed fields (`full_name`, `resume_node_id`, `skills`, `education_history` count, `work_experience` count) and returns a `getMissingFields()` array with human labels and direct edit-section links. Registered the service in `job_hunter.services.yml`. Added `missing_fields` variable to the `profile_completeness` theme hook and rewrote `profile-completeness.html.twig` to render a clickable missing-field checklist. Updated `UserProfileController::profileDashboard()` to use the new service. Embedded the widget on the home dashboard in `ApplicationSubmissionController::buildAuthenticatedView()` (shown only when completeness < 100%). Verified: service instantiates cleanly, uid=1 returns 20% with 4 correct missing fields and valid edit links, anonymous returns 403 on both route targets, `php -l` clean on all files.

## Next actions
- None; all four inbox items from this release cycle are complete.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 18
- Rationale: Profile completeness reduces new-user drop-off by surfacing exactly which fields are missing with direct fix links; it unblocks job matching quality downstream. Completes the last open feature in the current release cycle.

**Commits this session:**
- `fac55368e` — controller split
- `1a459d59e` — My Jobs pipeline view
- `b6c9f9d4a` — Google Jobs UX (server-side + detail page)
- `dbc0d8b0a` — profile completeness indicator ← this task

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260409-impl-forseti-jobhunter-profile-completeness
- Generated: 2026-04-09T12:43:56+00:00
