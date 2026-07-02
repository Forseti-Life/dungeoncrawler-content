# Test Failure Fix Implementation

> **Status (Current)**: Historical implementation log.
>
> This file documents one test-failure remediation cycle and should be treated as change history. For current, day-to-day test setup and execution, use:
> - `TESTING.md`
> - `TEST_SETUP.md`
> - `CI_TESTING_SETUP.md`

## Issue Summary
The PHPUnit test `CharacterStateControllerTest::testUpdateCharacterStateNegativeGetMethod` and 156 other tests were failing with the error:
```
Exception: Failed to open 'sites/simpletest/*/settings.php'. Verify the file permissions.
```

## Root Causes Identified

### 1. PHPUnit XML Configuration Error
**File:** `web/modules/custom/dungeoncrawler_tester/phpunit.xml`
**Problem:** Line 37 had a syntax error with duplicate closing attribute:
```xml
failOnWarning="false">
failOnPhpunitDeprecation="false">
```

**Fix:** Consolidated into a single line:
```xml
failOnWarning="false"
failOnPhpunitDeprecation="false">
```

### 2. Bootstrap.php Duplicate Content
**File:** `web/modules/custom/dungeoncrawler_tester/tests/bootstrap.php`
**Problem:** The file contained duplicate/corrupted content from lines 1-28 and 29-61, with two incomplete sections merged together.

**Fix:** Consolidated into a single, clean bootstrap that:
- Defines `PHPUNIT_COMPOSER_INSTALL` pointing to the correct vendor/autoload.php location
- Defines `DRUPAL_ROOT` to point to the web directory  
- Sets umask(0002) for proper file permissions
- Ensures simpletest directory exists with 0775 permissions
- Includes Drupal's core test bootstrap

### 3. Simpletest .gitignore Duplicate Content
**File:** `web/sites/simpletest/.gitignore`
**Problem:** The file had three duplicate sections with slightly different content.

**Fix:** Simplified to a clean version that:
- Ignores all test site directories (*)
- Keeps .gitignore, .gitkeep, and README.md files

### 4. Missing Autoload Symlink
**File:** `web/autoload.php`
**Problem:** Drupal's core test bootstrap expects autoload.php at `web/autoload.php`, but it was missing.

**Fix:** Created symlink from `web/autoload.php` to `../vendor/autoload.php`
- This is the standard Drupal Composer structure
- The symlink allows tests to find the autoloader

## Files Changed
1. `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/phpunit.xml` - Fixed syntax error
2. `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/bootstrap.php` - Removed duplicate content
3. `sites/dungeoncrawler/web/sites/simpletest/.gitignore` - Cleaned up duplicates
4. `sites/dungeoncrawler/web/autoload.php` - Created symlink (not committed, ephemeral)

## Prerequisites for Running Tests

Before these fixes can be verified, the following dependencies must be installed:

```bash
cd sites/dungeoncrawler
composer install
```

This requires:
- PHP 8.3+
- Composer 2.x
- MySQL/MariaDB database (configured in phpunit.xml SIMPLETEST_DB)
- Network access to:
  - packagist.org (Composer package repository)
  - github.com (for GitHub-hosted packages)
  - ftp.drupal.org (for Drupal.org packages)

### Key Dependencies
- drupal/core-dev (includes test framework)
- phpunit/phpunit ^11
- behat/mink (for browser testing)
- symfony/* (various components)

## How to Test

Once dependencies are installed:

### Run the Specific Failing Test
```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit \
  --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml \
  --filter testUpdateCharacterStateNegativeGetMethod
```

### Run All Tests
```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit \
  --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

### Using Setup Script (Alternative)
```bash
cd sites/dungeoncrawler
./setup-tests.sh  # Prepares environment
./vendor/bin/phpunit \
  --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Expected Behavior After Fixes

With these configuration fixes:
1. PHPUnit will parse the XML configuration without errors
2. The bootstrap will execute cleanly without duplicate code issues
3. The simpletest directory will be properly configured for test site creation
4. Tests will be able to create temporary settings.php files with correct permissions
5. The autoloader will be found by Drupal's test framework

The original "Failed to open settings.php" errors should be resolved because:
- The bootstrap now properly sets umask(0002) for readable/writable file creation
- The bootstrap ensures the simpletest directory exists with 0775 permissions
- The simpletest/.gitignore prevents committed test artifacts from causing permission conflicts
- The autoload.php symlink allows the test framework to bootstrap properly

## Limitations

These fixes address the configuration and code issues that were causing test failures. However:

1. **Composer Installation Required:** The actual dependencies must be installed via `composer install` in an environment with proper network access and GitHub authentication.

2. **Database Required:** Tests require a MySQL/MariaDB database as configured in phpunit.xml (SIMPLETEST_DB environment variable).

3. **Web Server (Optional):** Some functional tests may require a running web server at the SIMPLETEST_BASE_URL (http://localhost:8080).

## Verification Status

### ✅ Verified
- PHPUnit XML syntax is valid (verified with PHP's simplexml_load_file)
- Bootstrap PHP syntax is valid (verified with `php -l`)
- Git repository is clean
- Simpletest directory structure is correct

### ⏳ Pending Verification (Requires Dependencies)
- Actual test execution
- Test pass/fail status  
- Integration with CI pipeline

## Security Considerations

The umask(0002) setting in bootstrap.php creates files with:
- Files: 0664 (rw-rw-r--)
- Directories: 0775 (rwxrwxr-x)

This is appropriate for testing environments where the test runner and web server need read/write access. In production, more restrictive permissions should be used.

## Next Steps

1. Ensure `composer install` succeeds in the CI environment
2. Run the test suite to verify all tests pass (157 were failing, likely more tests exist)
3. Add these tests to the CI/CD pipeline if not already present
4. Consider adding a pre-commit hook to validate phpunit.xml syntax

## Summary

These minimal configuration fixes resolve the syntax errors and duplicate content issues that were preventing tests from running. The actual test execution still requires proper dependency installation via Composer, which must be done in an environment with appropriate network access and authentication.
