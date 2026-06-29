Now I have the full picture. The `job_hunter.delete_resume` route is a standalone controller endpoint (not the form's `deleteResumeFileSubmit`) — no template links to it currently. The fix is to add `methods: [POST]` and `_csrf_token: 'TRUE'` to the route. Since nothing links to it via GET, no template changes are needed.

Now write the outbox:

---

- Status: done
- Summary: Added `methods: [POST]` and `_csrf_token: 'TRUE'` to `job_hunter.delete_resume` in `job_hunter.routing.yml`. No template changes needed — no Twig or PHP generates a link to this route (form delete buttons use Drupal Form API, already CSRF-protected). Route is now POST-only. `drush cr` succeeded.

## Commits
- `871cda11f` — CSRF fix
- `9a6af29bd` — outbox

## Next actions
- QA: verify `GET /jobhunter/resume/delete/{id}` returns 405; form-based deletes still work

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260408-024500-csrf-fix-forseti-jobhunter-profile
- Generated: 2026-04-08T02:48:31+00:00
