# Issues TODO (to file after first-pass)

## Implement character creation workflow functional tests
- Implement full 8-step wizard flow: navigate, fill valid data, submit, persist character, and view completed character.
- Add navigation coverage (forward/back), validation per step, and data persistence across steps.
- Replace `markTestIncomplete()` in [tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php](sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php#L45-L97).
- Use fixtures/factories for reproducible character inputs.
- **Acceptance:** Workflow suite runs without incomplete markers, asserts real page content/state, covers success and validation failures.

## Deepen functional test assertions with data-backed fixtures
- Expand controller/route tests to assert response bodies/fields, not just status codes or class existence (campaign state, entity lifecycle, character state, API endpoints).
- Introduce reusable content/character/campaign fixtures or lightweight factories for functional runs.
- Target suites: campaign state ([tests/src/Functional/CampaignStateAccessTest.php](sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateAccessTest.php), [tests/src/Functional/CampaignStateValidationTest.php](sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateValidationTest.php)), entity lifecycle ([tests/src/Functional/EntityLifecycleTest.php](sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/src/Functional/EntityLifecycleTest.php)), character state/API routes/controllers under `tests/src/Functional/Controller` and `tests/src/Functional/Routes`.
- **Acceptance:** Functional suites assert meaningful payload fields/content; fixtures/factories are shared; minimal reliance on “not 404/405” assertions.

## Strengthen negative and authorization coverage across functional suites
- Add explicit deny cases where only happy paths exist (campaign select/tavern routes needing real entities, dashboard/admin routes, character edit/delete without ownership).
- Ensure 403/404/405 paths are asserted alongside success paths in Routes + Controller suites.
- Revisit campaign routes to include invalid IDs, ownership mismatches, and missing data scenarios.
- **Acceptance:** Each suite includes allowed and denied flows with specific status/message assertions; coverage reflected in README inventory.

## Refactor functional test data setup into reusable builders
- Create shared builders/helpers to provision campaigns, characters, and entities for functional tests to reduce duplication.
- Centralize JSON request helpers used in campaign/entity tests for consistency.
- Update affected tests to consume the helpers.
- **Acceptance:** Common setup lives in shared helpers/traits; individual tests are slimmer and reuse standardized creation methods.

## Add content-backed smoke tests for public controllers
- For page controllers (Home/World/About/HowToPlay/Credits/Testing), add minimal content fixtures or assertions that key blocks/nodes render (beyond generic substrings).
- Validate cache headers where applicable (e.g., testing page max-age).
- **Acceptance:** Controller tests verify expected content structure/blocks, not just status/text fragments; cache assertions remain valid.
