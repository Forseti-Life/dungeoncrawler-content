# Test Plan Design: forseti-ai-debug-gate

- Agent: qa-forseti
- Status: pending

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-05T16:01:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-ai-debug-gate/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-ai-debug-gate "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/forseti/suite.json`
- Do NOT edit `org-chart/sites/forseti.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
