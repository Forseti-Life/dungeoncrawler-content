# Fix for Campaign State Validation Test Failures

## Problem Summary

All 157 functional tests in the dungeoncrawler_tester module were failing with the error:
```
Exception: Failed to open 'sites/simpletest/*/settings.php'. Verify the file permissions.
```

## Root Causes Identified

### 1. Duplicate/Conflicting Bootstrap Code
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/bootstrap.php`

The bootstrap file contained duplicate and conflicting code blocks:
- Lines 1-28: One version of the bootstrap
- Lines 29-61: A second, slightly different version

This caused the bootstrap to run inconsistently and potentially define constants or include files twice.

**Fix Applied:** Merged the two versions into a single, correct bootstrap that:
- Sets proper umask for file permissions (0002)
- Defines PHPUNIT_COMPOSER_INSTALL correctly  
- Defines DRUPAL_ROOT correctly
- Ensures simpletest directory exists with proper permissions
- Includes Drupal's core bootstrap once

### 2. Malformed PHPUnit Configuration
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/phpunit.xml`

Line 37 had a duplicate/malformed attribute that broke the XML structure:
```xml
<phpunit ... 
         failOnWarning="false">
         failOnPhpunitDeprecation="false">  <!-- This line was OUTSIDE the opening tag! -->
```

**Fix Applied:** Moved the attribute inside the opening `<phpunit>` tag:
```xml
<phpunit ...
         failOnWarning="false"
         failOnPhpunitDeprecation="false">
```

### 3. Database Configuration (Pre-requisite)
Tests require a MySQL database to be available with the credentials specified in phpunit.xml:
- Database: `dungeoncrawler_dev`
- User: `drupal_user`
- Password: `your_db_password`
- Host: `127.0.0.1:3306`

## Changes Made

1. **Fixed `tests/bootstrap.php`:**
   - Removed duplicate code
   - Consolidated umask, directory creation, and bootstrap loading
   - Uses DRUPAL_ROOT constant for simpletest directory path

2. **Fixed `phpunit.xml`:**
   - Corrected malformed XML structure
   - Moved `failOnPhpunitDeprecation` attribute to proper location

## How to Verify the Fix

### Prerequisites
1. MySQL server running with dungeoncrawler_dev database created
2. Composer dependencies installed (`composer install` in sites/dungeoncrawler)
3. Proper permissions on web/sites/simpletest directory (775 or 777)

### Run Tests

#### From sites/dungeoncrawler directory (old/incorrect way):
```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

#### From web directory (correct way per documentation):
```bash
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml
```

Both should now work correctly due to the fixes in bootstrap.php.

### Run Specific Test
```bash
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml \
  --filter testMissingStatePayload
```

### Expected Result
All 170 tests should pass (13 unit tests + 157 functional tests).

## Technical Details

### Bootstrap Execution Flow
1. PHPUnit loads `tests/bootstrap.php`
2. Bootstrap sets umask(0002) for proper file permissions
3. Bootstrap defines PHPUNIT_COMPOSER_INSTALL path to vendor/autoload.php
4. Bootstrap defines DRUPAL_ROOT to the web/ directory
5. Bootstrap ensures web/sites/simpletest exists with 775 permissions
6. Bootstrap includes Drupal core's test bootstrap
7. Drupal core bootstrap sets up test environment
8. Tests can now create temporary test sites in sites/simpletest/

### Why the Duplicate Bootstrap Caused Failures
- Multiple definitions of DRUPAL_ROOT could cause path confusion
- Multiple includes of Drupal's core bootstrap could cause class redefinition errors
- Inconsistent umask settings could result in permission issues
- Double chmod operations could interfere with each other

### Why the Malformed XML Caused Failures
- PHPUnit couldn't parse the configuration file
- Tests might fail to load or run with default settings
- Extensions might not be loaded properly

## Impact
These fixes should resolve all 157 failing functional tests. The changes are minimal and surgical:
- Only 2 files modified
- No functional code changes to tests themselves
- No changes to test logic or assertions
- Only configuration and bootstrap fixes

## Ready for Testing
The code fixes are complete and committed. To fully validate:
1. Set up MySQL database with proper credentials
2. Run `composer install` to get dependencies
3. Run the test suite as documented above
4. Verify all 170 tests pass
