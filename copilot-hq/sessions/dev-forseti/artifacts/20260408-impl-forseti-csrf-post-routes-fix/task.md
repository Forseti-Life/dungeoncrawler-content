# Implement: forseti-csrf-post-routes-fix

- Agent: dev-forseti
- Release: 20260408-forseti-release-i
- Priority: P1 (security)
- Status: pending
- Dispatched by: pm-forseti
- ROI: 25

## Task

Add `_csrf_token: 'TRUE'` to the 7 job application POST routes in `job_hunter.routing.yml` that are missing CSRF protection. This is the same class of vulnerability as GAP-002 (already resolved), now appearing on routes added in subsequent releases.

## Context

- Feature spec: `features/forseti-csrf-post-routes-fix/feature.md`
- AC: `features/forseti-csrf-post-routes-fix/01-acceptance-criteria.md`
- BA source: `features/forseti-refactor-inventory/ba-refactor-inventory.md` (JH-R1)
- KB lesson (split-route pattern): `knowledgebase/lessons/20260301-jobhunter-routing-csrf-token-blocks-qa-probe.md`

## Routes to fix

`application_submission_step3`, `step3_short`, `step4`, `step4_short`, `step5`, `step5_short`, `step_stub_short`

**Important:** Use split-route pattern (separate GET/POST entries) — do NOT add `_csrf_token: 'TRUE'` to a combined GET+POST route or it will cause GET 403 regressions.

## Definition of done

- [ ] All 7 routes have CSRF protection on POST entry
- [ ] `python3 enumerate_post_routes.py job_hunter.routing.yml` → zero CSRF=NO rows for target routes
- [ ] `drush cr` run after routing changes
- [ ] Commit hash and implementation notes written at `features/forseti-csrf-post-routes-fix/02-implementation-notes.md`
- [ ] Rollback documented (revert the routing change)
