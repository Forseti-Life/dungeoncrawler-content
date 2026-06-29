Now I have full picture. Let me implement:

1. 2 new POST routes (save + delete)
2. `savedSearchSave()` and `savedSearchDelete()` methods in `GoogleJobsSearchController`
3. Update `searchPage()` to load saved searches and pass them + URL vars to template
4. Update theme hook to add new variables
5. Update Twig template (save button + panel)
6. `job_hunter_update_9043` for the DB table

Now update `GoogleJobsSearchController` — add `savedSearchSave()`, `savedSearchDelete()`, and update `searchPage()` to pass saved searches + URLs to the template:

Now update `searchPage()` to load saved searches and pass them to the template:

Now update the theme hook to add new variables:

Now update the Twig template. I need to add: the save button after the search form, and the saved searches panel at the end:

Now add the saved searches panel at the bottom of the template (before the closing `</div>`):

Now add the DB update hook. Last was `9042`, next is `9043`:

All checks pass. Now commit:

I notice the controller and routing changes aren't staged. Let me check:

The previous commit `2f2658355` already has the controller, routing, and module changes. I just added the `install` hook and template after. Let me commit these:

Implementation complete. Here's the outbox update:

---

- Status: done
- Summary: Implemented `forseti-jobhunter-saved-search` fully. Added `savedSearchSave()` and `savedSearchDelete()` POST methods to `GoogleJobsSearchController`, updated `searchPage()` to load saved searches and pass them + URL to template, added `job_hunter.saved_search_save` and `job_hunter.saved_search_delete` routes (both POST-only, `_csrf_token: 'TRUE'`, `_user_is_logged_in: 'TRUE'`), added `hook_update_9043` creating the `jobhunter_saved_searches` table (id, uid, keywords, location, created, updated + uid index), updated `hook_theme` variables, and updated `google-jobs-search.html.twig` with the Save button (AC-1), saved searches panel (AC-2/AC-3/AC-4), and max-limit message (AC-5). Verified: PHP syntax clean, `update_9043` ran successfully, table confirmed present, anonymous → 403, CSRF guards confirmed in routing.yml. Commits: `2f2658355` (controller + routes + theme hook) + `62c441f56` (install hook + template).

## Next actions
- QA-forseti to run AC-1 through AC-7 verification
- Key tests: save with empty keywords → rejected, max 10 limit enforced server-side, delete removes only owner's record, re-run link pre-populates correct URL params, POST without CSRF → 403

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 12
- Rationale: Removes repeat search friction for active job hunters; low risk as routes, controller, and DB schema are all proven Drupal patterns already in use in this module.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260409-impl-forseti-jobhunter-saved-search
- Generated: 2026-04-09T15:12:48+00:00
