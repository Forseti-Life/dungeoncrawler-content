Now I understand the gap. The `applied-toggle-form` expected by the Playwright test is **missing from `my-jobs.html.twig`**. The controller already passes `apply_csrf_token` and the POST route exists, but the template doesn't render the form. I need to add it.

Good. Now let me run `drush cr` and commit:

Now write and commit the outbox:

---

- Status: done
- Summary: Found and fixed the blocking gap: `my-jobs.html.twig` was missing the `form.applied-toggle-form` that the Playwright E2E test requires to mark jobs as applied. The controller and POST route were already implemented — only the template was missing the form. Added `form.applied-toggle-form` (with `have_applied` checkbox, `applied_on_date` date input, "Update" button, and `?token=` CSRF in form action) to all job rows. All other AC items were already in place. Commits: `1f84c8539` (template fix), `7f6a25733` (implementation notes update). Outbox: `c931f8e36`.

## Next actions
- QA to run `jobhunter-e2e` Playwright suite from suite.json
- Verify `report.submission.success = true` in artifacts

## Blockers
- None.

## ROI estimate
- ROI: 60
- Rationale: Unblocks Gate 2 for the P0 e2e-flow feature in release-c; single template addition, zero risk.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260408-022038-impl-forseti-jobhunter-e2e-flow
- Generated: 2026-04-08T02:32:55+00:00
