# Dungeon Crawler Root-Level Tests

This directory contains tests that are run directly from the `sites/dungeoncrawler` root, outside of the main testing module structure.

## Setup

Before running tests for the first time, run the setup script to ensure the test environment is properly configured:

```bash
cd sites/dungeoncrawler
./tests/setup.sh
```

This script will:
- Create the `web/sites/simpletest/` directory with proper permissions
- Install composer dependencies if needed

## Purpose

These tests are primarily used for automated test runs that reference test files directly by path, rather than through PHPUnit test suites.

## Setting Up the Test Environment

Before running tests, you need to prepare the test environment to ensure proper directory permissions:

```bash
cd sites/dungeoncrawler
bash prepare-test-env.sh
```

This script:
- Creates and sets proper permissions for `web/sites/simpletest/` directory (default: 777 for CI/testing)
- Creates the `/tmp/dungeoncrawler-simpletest/` directory for test files
- Cleans any stale test directories from previous runs

### Permission Options

By default, the script uses 777 permissions (suitable for CI/development). For more restricted environments:

```bash
# Use 775 permissions instead
TEST_DIR_PERMISSIONS=775 bash prepare-test-env.sh

# Ensure your user is in the web server group
sudo usermod -a -G www-data $USER
```

### Why is this needed?

Drupal functional tests (BrowserTestBase) create temporary test sites in `web/sites/simpletest/`. Each test run creates a new subdirectory with its own `settings.php` file. Without proper write permissions, tests will fail with errors like:

```
Exception: Failed to open 'sites/simpletest/*/settings.php'. Verify the file permissions.
```
## Setup

Before running tests for the first time, you need to set up the test environment:

```bash
# From the sites/dungeoncrawler directory
./tests/setup-test-environment.sh
```

This script will:
- Create necessary temporary directories for test file storage
- Set appropriate permissions on simpletest directories
- Ensure default site directories exist

## Running Tests

**Note**: For functional tests like HexMapUiStageGateTest, use the full module path:

```bash
# Run tests from the module's test directory
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/HexMapUiStageGateTest.php
```

## Troubleshooting

If tests fail with permission errors:
1. Run `bash prepare-test-env.sh` to reset the environment
2. Ensure the web server user has write access to `web/sites/simpletest/`
3. Check that `/tmp/dungeoncrawler-simpletest/` is writable

## Note

The main test suite is located in `web/modules/custom/dungeoncrawler_tester/tests/`. This root-level tests directory is supplementary and contains tests that are referenced by specific automated testing workflows.
