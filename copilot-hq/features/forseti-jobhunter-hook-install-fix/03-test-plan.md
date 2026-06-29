# Test Plan — forseti-jobhunter-hook-install-fix

- Feature: Add missing table creation calls to job_hunter_install()
- QA owner: qa-forseti
- Release target: 20260409-forseti-release-j
- Date written: 2026-04-09

## Overview

This is a minimal additive bugfix. Two PHP function calls are added to `hook_install()`. Verification is primarily static (grep-based). No functional behavior change for existing sites.

---

## Test Cases

### TC-01 — Static: both helpers called in hook_install()
- **Suite:** static grep
- **AC item:** Both `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` called in `job_hunter_install()`
- **Steps:**
  1. `grep -A60 "function job_hunter_install" sites/forseti/web/modules/custom/job_hunter/job_hunter.install | grep "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table"`
- **Expected:** Both function names appear in output (one line each)
- **Roles covered:** N/A (static)
- **Automated:** Yes — single grep invocation

### TC-02 — Static: no duplicate calls
- **Suite:** static grep
- **AC item:** No duplicate invocations of the two new helpers
- **Steps:**
  1. `grep -c "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table" sites/forseti/web/modules/custom/job_hunter/job_hunter.install`
- **Expected:** Count matches expected call sites (install function call + function definition = 2 per helper, total 4)
- **Automated:** Yes

### TC-03 — Table creation succeeds on simulated fresh install
- **Suite:** PHP unit/functional (optional; static check is primary)
- **AC item:** Fresh install creates both tables without error
- **Steps:**
  1. Invoke `job_hunter_install()` in a test environment with empty schema
  2. Confirm `jobhunter_interview_notes` and `jobhunter_saved_searches` tables exist after invoke
- **Expected:** Both tables created; no PHP exception thrown
- **Automated:** Optional — static check is sufficient for Gate 2

## Pass/Block criteria

- **PASS:** TC-01 output contains both helper function names; no regressions on existing functionality
- **BLOCK:** Either helper function missing from grep output; or TC-03 throws exception in test environment
