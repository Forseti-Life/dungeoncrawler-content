# Security Review Report

Date: 2026-02-18  
Scope: Repository-wide static review of custom Drupal code, site settings, scripts, ETL/config files, and mobile environment files.

## Executive Summary

The review identified multiple high-risk findings that can expose credentials, weaken request integrity controls, and leak sensitive diagnostics in deployed environments.

Key concerns:
- Hardcoded secrets and database credentials are stored in tracked files.
- Multiple authenticated POST endpoints in `job_hunter` explicitly disable CSRF route protection.
- Development/debug settings are enabled in default Drupal settings files.
- Repository ignore rules do not protect current sensitive files and paths.

## Findings

### 1) Hardcoded credentials in tracked runtime configuration (Critical)

Observed in:
- `.env`
- `sites/forseti/web/sites/default/settings.php`
- `sites/forseti/web/sites/default/settings.amisafe.php`
- `sites/dungeoncrawler/web/sites/default/settings.php`
- `sites/dungeoncrawler/web/sites/default/settings.local.php`
- `h3-geolocation/config/mysql_config.json`

Risk:
- Credential exposure in repository history and local clones.
- Easier lateral movement if any single environment is compromised.

### 2) CSRF disabled on POST routes in job_hunter (High)

Observed in:
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
  - `job_hunter.job_discovery_search_ajax`
  - `job_hunter.save_job`
  - `job_hunter.tailor_resume_ajax`
  - `job_hunter.add_skill_to_profile_ajax`
  - `job_hunter.refresh_skills_gap_ajax`

Risk:
- Authenticated users can be tricked into unintended state-changing actions via cross-site requests.
- Inconsistent request-integrity enforcement across module endpoints.

### 3) Debug/development behavior active in default settings files (High)

Observed in:
- `sites/forseti/web/sites/default/settings.php`
- `sites/dungeoncrawler/web/sites/default/settings.php`

Examples include verbose logging, disabled optimization, and runtime error display toggles in default settings paths.

Risk:
- Increased information disclosure.
- Production hardening drift and uncertain environment separation.

### 4) Incomplete .gitignore coverage for active sensitive files (High)

Observed in:
- `.gitignore` currently ignores legacy paths but not current tracked sensitive files such as root `.env` and current Drupal `sites/*/web/sites/default/settings*.php` patterns.

Risk:
- Continued accidental commits of secrets and local override files.

### 5) Hardcoded passwords in scripts and test utilities (Medium)

Observed in:
- `verify-test-fix.sh`
- `testing/amisafe/interactive_filter_test.sh`
- `testing/apitesting/generate_resolution_5_citywide.py`
- multiple database setup/export scripts under `script/` and `database-exports/`

Risk:
- Test/development credentials become de facto defaults.
- Increased chance of secret reuse and operational leaks.

### 6) Test-user credentials embedded in module docs/code (Medium)

Observed in:
- `sites/forseti/web/modules/custom/nfr/TEST_USER_CREDENTIALS.md`
- `sites/forseti/web/modules/custom/nfr/src/Controller/NFRValidationController.php`

Risk:
- Weak credential hygiene and possible accidental reuse in non-test contexts.

## Priority Order

1. Remove/externalize secrets from tracked files and rotate affected credentials.
2. Re-enable CSRF protection on state-changing POST endpoints and enforce token validation.
3. Separate development-only settings from default settings and remove debug exposure in default paths.
4. Harden repository guardrails: update `.gitignore`, add secret scanning checks in CI.
5. Normalize scripts/docs to require environment-provided secrets.

## Issues Tracked

See `Issues.md` entries:
- DCC-0331 through DCC-0337.

## Phase 2 Planning

Detailed remediation tasks, validation criteria, and completion evidence requirements are documented in:
- `SECURITY_REMEDIATION_PLAN_2026-02-18.md`
