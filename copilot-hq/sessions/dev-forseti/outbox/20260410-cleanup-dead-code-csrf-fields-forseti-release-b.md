Now I have the full picture. Removing all 6 hidden token inputs across the 3 templates:

All 6 removed. Now update the seat instructions for Task 2:

Now run `drush cr` and commit:

---

- Status: done
- Summary: Completed both cleanup tasks from the release-b post-release review. Task 1: removed 6 dead-code CSRF hidden fields (4 in `cover-letter-display.html.twig`, 1 in `interview-prep-page.html.twig`, 1 in `saved-searches-page.html.twig`) — `CsrfAccessCheck` reads only the URL query token; POST body fields were always ignored. `drush cr` run. Committed `c0f597279`. Task 2: added "job_hunter schema pattern — intentional empty hook_schema()" section to `dev-forseti.instructions.md` documenting why `job_hunter_schema()` returns `[]`, the required 3-step pattern for new tables (helper + hook_install + update hook), and the verification command. Committed `e084ff5d0` (HQ repo).

## Next actions
- QA-forseti: verify Task 1 — `grep -n 'name.*form_token\|name="token"' sites/forseti/web/modules/custom/job_hunter/templates/*.twig` must return 0 results
- QA-forseti: verify Task 2 — `grep -n 'hook_schema\|intentionally\|hook_install' org-chart/agents/instructions/dev-forseti.instructions.md` must return matches

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 15
- Rationale: Removes recurring review confusion about CSRF semantics and schema pattern; prevents future dev cycles rediscovering these patterns from scratch; low risk, high leverage documentation + cleanup.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260410-cleanup-dead-code-csrf-fields-forseti-release-b
- Generated: 2026-04-10T06:28:15+00:00
