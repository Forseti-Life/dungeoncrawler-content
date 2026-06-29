# Acceptance Criteria — forseti-ai-debug-gate

- Feature: Verify and lock GenAiDebugController to admin-only access
- Release target: 20260402-forseti-release-b
- PM owner: pm-forseti
- Date groomed: 2026-04-05
- Priority: P1

## Gap analysis reference

Feature type: `needs-testing` — Implementation likely exists (admin routing). Need verification and regression tests.
All criteria are `[TEST-ONLY]` unless Dev must correct a misconfigured route.

PM decision: GenAiDebugController remains in production code; all routes must require `administer site configuration` or equivalent admin permission. No risk acceptance for non-admin exposure.

## Knowledgebase check
- None found specific to `ai_conversation` module debug access.
- Related pattern: `knowledgebase/lessons/20260227-routing-permission-mismatch-companyresearch.md` — watch for route-level `_permission` mismatches in `ai_conversation.routing.yml`.

## Happy Path

- [ ] `[TEST-ONLY]` `grep "GenAiDebugController" ai_conversation.routing.yml` — every route shows `_permission: 'administer site configuration'` (or stricter).
- [ ] `[TEST-ONLY]` Admin user accessing `/admin/ai-conversation/debug` (or equivalent route): HTTP 200.

## Edge Cases

- [ ] `[TEST-ONLY]` Authenticated non-admin user accessing any `GenAiDebugController` route: HTTP 403.

## Failure Modes

- [ ] `[TEST-ONLY]` Anonymous user accessing any `GenAiDebugController` route: HTTP 403.
- [ ] `[TEST-ONLY]` If a route is found with non-admin permission during Dev audit: escalate as P0 blocker; do not accept risk.

## Permissions / Access Control

- [ ] Anonymous user behavior: 403 on all `GenAiDebugController` routes.
- [ ] Authenticated non-admin behavior: 403 on all `GenAiDebugController` routes.
- [ ] Admin behavior: 200 on all `GenAiDebugController` routes.

## Data Integrity

- [ ] No DB migration required. Routing config change only.
- [ ] Rollback path: revert `ai_conversation.routing.yml` + `drush cr`.

## Dev definition of done
- [ ] All `GenAiDebugController` routes in `ai_conversation.routing.yml` confirmed to require admin permission.
- [ ] If any route requires a fix: commit the routing change, update notes.

## QA test path
- `web/modules/custom/ai_conversation/tests/src/Functional/GenAiDebugAccessTest.php` — new test:
  - Admin: 200 on at least one debug route.
  - Authenticated non-admin: 403.
  - Anonymous: 403.
