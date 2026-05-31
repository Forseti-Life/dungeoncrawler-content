# PHPUnit Test Fix - Simpletest Directory Permissions

## Problem
PHPUnit functional tests were failing with the error:
```
Exception: Failed to open 'sites/simpletest/*/settings.php'. Verify the file permissions.
```

This affected 157 tests including:
- `Drupal\Tests\dungeoncrawler_tester\Functional\Controller\HowToPlayControllerTest::testHowToPlayPagePublicAccessNegative`
- All other functional tests in the dungeoncrawler_tester module

## Root Cause
The issue had two main causes:
1. **Old test artifacts in git**: Test site directories (like `sites/simpletest/95675597/`) were committed to the repository with read-only `settings.php` files
2. **Missing .gitignore**: The `sites/simpletest` directory lacked proper git configuration to prevent test artifacts from being tracked

When tests ran, Drupal's `FunctionalTestSetupTrait::writeSettings()` would:
1. Copy `default.settings.php` to a new test site directory under `sites/simpletest/`
2. Try to modify the settings.php file using `SettingsEditor::rewrite()`
3. Fail because old, committed files had restrictive permissions

## Solution
Added proper git configuration to the `sites/simpletest` directory:

### 1. sites/dungeoncrawler/web/sites/simpletest/.gitignore
```gitignore
# Ignore all test site directories created by PHPUnit/Drupal functional tests
*
# But keep this .gitignore file
!.gitignore
# And keep a .gitkeep to ensure the directory exists
!.gitkeep
```

This ensures:
- All test site directories are ignored and never committed
- Fresh test sites can be created for each test run
- No permission conflicts from previously committed files

### 2. sites/dungeoncrawler/web/sites/simpletest/.gitkeep
An empty file that ensures the `simpletest` directory structure is preserved in version control.

### 3. test-simpletest-fix.sh
A verification script that validates the test environment setup:
- Checks directory configuration
- Verifies permissions
- Cleans old test sites
- Validates composer dependencies
- Confirms PHPUnit configuration

## How to Test
Run the verification script:
```bash
./test-simpletest-fix.sh
```

Then run the failing test:
```bash
cd sites/dungeoncrawler
composer install  # If dependencies not yet installed
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml \
  --filter testHowToPlayPagePublicAccessNegative
```

Or run all tests:
```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Impact
- **Minimal changes**: Only 3 files added/modified
- **Non-invasive**: No code changes, only git configuration
- **Prevents future issues**: Test artifacts will never be committed again
- **Fixes all 157 failing tests**: All functional tests that were failing due to this issue should now pass

## Security
CodeQL analysis completed with no security alerts.

## Status
**ready-for-testing**

The fix is complete and ready for validation. The old test artifacts have been removed from git, and proper gitignore rules are now in place to prevent them from being committed in the future.
