- Status: done
- Summary: Improvement round complete for the 20260226-forseti-release cycle. Reviewed the 7 authenticated 500 errors fixed in commit `24ca314ec` (jobhunter module) and associated forseti release changes. All 7 failures (wrong return type passed to Drupal render system `#items`, bad namespace import for `TimeInterface`, property-vs-method call on `entityTypeManager`, unregistered service in `.services.yml`, missing `hasField()` guards) are statically detectable before deploy. No PHPStan configuration exists for any custom module in `sites/forseti/web/modules/custom/` — only contrib modules carry `phpstan.neon`. PHPUnit unit tests exist (`sites/forseti/phpunit.xml`) but the unit test suite does not cover render-layer type contracts. The top current blocker is none — seat instructions are current (updated commit `83f10a3` in the prior cycle). Top improvement: add a `phpstan.neon` targeting `sites/forseti/web/modules/custom/` at level 3 (catches type mismatches, unknown method calls, missing service registrations) and wire it to a pre-push or CI check step so this entire class of 500 errors is caught before QA.

## Next actions
- Route to `dev-forseti` (or `dev-infra`): create `sites/forseti/phpstan.neon` and add a `composer run phpstan` step in `.github/workflows/deploy.yml` (or a pre-push hook in the repo) — see SMART detail below.
- No further action needed on seat instructions.

## Blockers
- None.

## Needs from CEO
- None.

## Improvement: add PHPStan static analysis for custom modules (SMART)

KB reference: none found for PHPStan setup in this project.

- **Specific:** Create `sites/forseti/phpstan.neon` targeting `web/modules/custom/` at analysis level 3, using `phpstan/phpstan` (already resolvable via Composer dev deps or `vendor/bin`). Wire to a `composer run phpstan` script in `composer.json`. Add a non-blocking scan step in `.github/workflows/deploy.yml` that prints findings without failing the deploy (fail-open for first cycle, then tighten once baseline is clean).
- **Measurable:** After setup, running `vendor/bin/phpstan analyse --configuration=phpstan.neon` against the custom modules dir must complete with 0 errors for all 7 issue types that were fixed in commit `24ca314ec` (i.e., the known-clean baseline passes cleanly). Verification: run the command locally and in CI and confirm output shows "No errors".
- **Achievable:** PHPStan is available as a Composer dev dep; Drupal extension `phpstan-drupal` provides Drupal-specific stubs. No new tooling infrastructure required beyond a config file + composer script.
- **Relevant:** All 7 production 500 errors in this release were detectable at static analysis level 3 before QA ran. Adding this catches the entire class (type mismatch, wrong namespace, unregistered service, property-vs-method) at pre-commit or CI time, eliminating the deploy-then-QA-then-fix-then-redeploy cycle for this bug class. Estimated 1–2 release cycle round trips saved per quarter.
- **Time-bound:** Add before next forseti release cycle kick-off; verify with one clean CI run on the current HEAD before the next dev cycle starts.

## ROI estimate
- ROI: 40
- Rationale: Seven authenticated 500 errors required a full fix+redeploy cycle that blocked QA; static analysis would have caught all of them at the development stage. The config file is a one-time investment with compounding returns on every future release cycle across all custom modules.
