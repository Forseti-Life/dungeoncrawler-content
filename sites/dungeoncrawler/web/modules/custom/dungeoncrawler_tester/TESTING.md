# DungeonCrawler Testing Guide

## Running Tests

### Important: Working Directory

Tests **must** be run from the `web/` directory (DRUPAL_ROOT). This is critical because:

1. Drupal's test framework (BrowserTestBase) uses relative paths for test site directories
2. File operations in `FunctionalTestSetupTrait::writeSettings()` use native PHP functions that resolve paths relative to the current working directory
3. Running from any other directory will cause errors like: `Exception: Failed to open 'sites/simpletest/[id]/settings.php'`

### Correct Usage

```bash
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml
```

### Playwright UI Tests (Repository Root)

Playwright is the official UI testing suite for hexmap and workflow smoke tests.

```bash
cd /home/keithaumiller/forseti.life
npm install
npx playwright install chromium
./testing/playwright/setup-auth.sh
node testing/playwright/test-character-creation.js http://localhost:8080 10000
node testing/playwright/test-hexmap.js http://localhost:8080 5000
```

### With Coverage

```bash
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml --coverage-html tests/coverage
```

### Run Specific Test Suite

```bash
# Run only unit tests
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml --testsuite unit

# Run only functional tests
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml --testsuite functional
```

### Run Specific Test Class

```bash
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateAccessTest.php
```

### Run GitHub Client Rate-Limit/Dedupe Unit Test

```bash
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml modules/custom/dungeoncrawler_tester/tests/src/Unit/Service/GithubIssuePrClientTest.php
```

## Test Suites

### Unit Tests
- **Location**: `tests/src/Unit/`
- **Purpose**: Isolated service and unit coverage
- **Requirements**: No database or HTTP server needed

### Functional Tests
- **Location**: `tests/src/Functional/`
- **Purpose**: Browser-based integration tests for controllers, routes, and workflows
- **Requirements**: 
  - Database connection (configured in phpunit.xml)
  - Writable simpletest directory
  - Sufficient memory (512M configured)

## Troubleshooting

### GitHub API Rate-Limit Recovery (Tester Automation)

When tester automation logs repeated GitHub `403`/`429` responses:

1. Check current rate limits:
  ```bash
  gh api rate_limit
  ```
2. Confirm `Retry-After` / `X-RateLimit-Reset` values in logs and wait accordingly.
3. Avoid manually re-triggering mutation-heavy loops until cooldown expires.
4. Re-run the focused unit test (`GithubIssuePrClientTest`) plus targeted stage flow once limits recover.

### "Failed to open settings.php" Error

**Symptom**: 
```
Exception: Failed to open 'sites/simpletest/[id]/settings.php'. Verify the file permissions.
```

**Cause**: Tests are being run from the wrong working directory.

**Solution**: Ensure you `cd sites/dungeoncrawler/web` before running phpunit.

### Permission Errors in simpletest Directory

**Symptom**: Tests fail with permission errors when trying to create test site directories.

**Solution**: 
```bash
# Clean up leftover test directories
rm -rf sites/dungeoncrawler/web/sites/simpletest/*

# Ensure simpletest directory has correct permissions
chmod 775 sites/dungeoncrawler/web/sites/simpletest
```

### Database Connection Errors

**Symptom**: Tests fail with database connection errors.

**Solution**: Verify the `SIMPLETEST_DB` environment variable (from your shell or CI secret) points to a valid MySQL/MariaDB database with proper credentials.

## CI/CD Integration

When running tests in CI/CD pipelines, ensure the working directory is set correctly:

```yaml
# Example GitHub Actions workflow
- name: Run PHPUnit Tests
  working-directory: sites/dungeoncrawler/web
  run: ../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Known Issues

- **PHPUnit 11 Deprecation Warnings**: Currently suppressed with `failOnPhpunitDeprecation="false"`. These should be addressed when migrating to newer PHPUnit/Drupal versions.
- **Code Coverage Driver**: XDebug or PCOV extension required for code coverage reports.
