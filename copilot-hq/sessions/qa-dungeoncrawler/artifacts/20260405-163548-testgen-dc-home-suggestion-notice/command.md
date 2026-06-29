# Test Plan Design: dc-home-suggestion-notice

- Agent: qa-dungeoncrawler
- Status: pending

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-05T16:35:48+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-home-suggestion-notice/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-home-suggestion-notice "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
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

# Acceptance Criteria (PM-owned)
# Feature: dc-home-suggestion-notice

## Gap analysis reference

Gap analysis: quick check for existing home page block or front-page content region in dungeoncrawler_content.

```bash
grep -rl "home\|front.page\|suggestion" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/ 2>/dev/null | head -10
```

All criteria below are `[NEW]` or `[CONTENT]` — this is a content/UI addition only. No existing suggestion-notice block found. Dev builds a simple block or edits the front page template.

## KB references
- KB: no prior lessons for home page content blocks in dungeoncrawler. Reference `knowledgebase/lessons/` for general Drupal block patterns if needed.

## Happy Path

- [ ] `[NEW]` A short notice is displayed on the Dungeoncrawler home page visible to anonymous and authenticated users.
- [ ] `[CONTENT]` Notice text (or equivalent): "We are actively implementing player suggestions. Keep the ideas coming!"
- [ ] `[NEW]` The notice is positioned prominently on the home page (above the fold or in a banner/info region).
- [ ] `[NEW]` The notice is dismissible OR always visible (PM decision: always visible is acceptable for simplicity).
- [ ] `[NEW]` The notice does not break the existing home page layout on desktop or mobile.

## Edge Cases / Failure Modes

- [ ] `[TEST]` Verify notice renders correctly for anonymous users (no login required).
- [ ] `[TEST]` Verify notice renders correctly for authenticated users.
- [ ] `[TEST]` Verify home page HTTP response is still 200 after change.
- [ ] `[TEST]` Verify no JS errors introduced on page load.

## Definition of Done

- Notice text is visible on the dungeoncrawler home page at `http://localhost:8080/` (dev) and `https://dungeoncrawler.forseti.life/` (prod).
- All criteria above pass QA verification.
- `drush cr` run after any template/block changes.

## Rollback

- Revert the block config or template change.
- Run `drush cr`.
- No database schema changes expected.
