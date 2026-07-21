# Dungeon Crawler Content Module - Tests

Current test system for `dungeoncrawler_content`.

## Test families in active use

1. **PHPUnit suites** (configured in `phpunit.xml`)
   - `tests/src/Unit`
   - `tests/src/Kernel` (configured, currently no files)
   - `tests/src/Functional`
2. **Drush executable PHP regression scripts** (`tests/*_test.php`)
3. **Node contract/regression scripts** (`tests/*_test.js`)

## Run commands

From Drupal root (`/var/www/html/dungeoncrawler`):

```bash
# Full PHPUnit run for this module.
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml

# Focused suites.
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml --testsuite unit
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml --testsuite functional

# One drush script test.
./vendor/bin/drush php:script web/modules/custom/dungeoncrawler_content/tests/chat_bootstrap_test.php

# One node contract test.
node web/modules/custom/dungeoncrawler_content/tests/action_rail_contract_routing_test.js
```

## Inventory snapshot (updated 2026-07-13)

| Category | Count |
|---|---:|
| PHPUnit test files (`tests/src/**/*Test.php`) | 191 |
| Unit PHPUnit files | 157 |
| Functional PHPUnit files | 34 |
| Kernel PHPUnit files | 0 |
| Top-level drush PHP scripts (`tests/*_test.php`) | 14 |
| Top-level Node scripts (`tests/*_test.js`) | 109 |
| `markTestIncomplete()` occurrences | 9 |
| `TODO` occurrences under `tests/` | 21 |

## Pending / still needed

1. **Finish remaining stubbed unit tests**
   - `tests/src/Unit/Service/CharacterCalculatorTest.php`
   - `tests/src/Unit/Service/CombatCalculatorTest.php`
2. **Finish fixture helper implementation**
   - `tests/src/Unit/Traits/FixtureLoaderTrait.php`
3. **Populate Kernel suite**
   - `tests/src/Kernel` is configured in `phpunit.xml` but currently empty.
4. **Keep backlog-driven expansion moving**
   - Continue executing priorities in `tests/TEST_CASE_MATRIX.md` (P0/P1/P2).
5. **Unify execution in CI**
   - Ensure PHPUnit + drush script tests + node contract tests all run in CI with clear pass/fail reporting.

## Related docs

- `tests/TEST_CASE_MATRIX.md` (prioritized backlog)
- `tests/TESTING_MODULE_README.md` (functional/controller-focused view)
- `../../../../docs/dungeoncrawler/issues/issue-testing-strategy-design.md`
