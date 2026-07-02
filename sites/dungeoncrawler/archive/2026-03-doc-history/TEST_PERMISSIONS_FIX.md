# Test Permissions Fix

> **Status (Current)**: Historical incident note.
>
> This document records a specific repository cleanup related to `web/sites/simpletest/`. For current test environment preparation and permissions guidance, use:
> - `TEST_SETUP.md`
> - `CI_TESTING_SETUP.md`

## Issue
PHPUnit functional tests were failing with the error:
```
Exception: Failed to open 'sites/simpletest/52709983/settings.php'. Verify the file permissions.
```

## Root Cause
Test site directories and files from `web/sites/simpletest/` were accidentally committed to the repository. These files (particularly `settings.php`) had restrictive permissions that prevented the test framework from modifying them during test execution.

## Solution
Removed all tracked test artifacts from the `web/sites/simpletest/` directory. This directory is already properly ignored by the root `.gitignore` file (line 106: `sites/*/web/sites/simpletest/`).

When tests run, they will now:
1. Create fresh test site directories in `web/sites/simpletest/`
2. Copy and configure settings files with appropriate permissions
3. Clean up after themselves

## Prevention
The `.gitignore` file already prevents these directories from being tracked. Test artifacts should never be committed to version control.
