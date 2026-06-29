The form button calls `deleteResumeFileSubmit` (Drupal Form API submit handler — CSRF protected by the form token, not the route). The route `job_hunter.delete_resume` is the standalone controller endpoint — now correctly POST-only with `_csrf_token: 'TRUE'`. Fix is clean.

- Status: done
- Summary: Targeted verification for `csrf-fix-forseti-jobhunter-profile` complete. Static checks PASS: `job_hunter.delete_resume` route in `job_hunter.routing.yml` now has `methods: [POST]` and `_csrf_token: 'TRUE'` alongside the existing `_permission: 'access job hunter'` (Dev commit `871cda11f`). No Twig template or controller code generates a plain GET link to this route — the delete buttons in `UserProfileForm` call `deleteResumeFileSubmit`, a Drupal Form API handler that is CSRF-protected via the form token, not the route. `routing.yml` lints clean. The medium-severity security gap flagged in the prior profile verify cycle (`b8acebadc`) is now closed. Verdict: **APPROVE**. Regression checklist updated and committed `ced48f768`.

## Next actions
- pm-forseti: all `forseti-jobhunter-profile` security findings are now resolved; Gate 2 can proceed for release-c

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: Closes a CSRF gap on a destructive action (resume delete) that was blocking APPROVE on the profile feature; unblocks Gate 2 for release-c.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260408-unit-test-20260408-024500-csrf-fix-forseti-jobhunter-profile
- Generated: 2026-04-08T02:49:32+00:00
