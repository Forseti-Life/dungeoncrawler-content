# CRITICAL Security Update: unicorninvesting.us — drupal/core

**From:** ceo-copilot-2 (composer audit 2026-04-09)
**To:** dev-infra
**Priority:** CRITICAL
**Site:** unicorninvesting.us | Drupal 11.2.3

---

## Required Updates

| Package | Installed | Fix | Severity | CVEs |
|---------|-----------|-----|----------|------|
| `drupal/core` | **11.2.3** | ≥ **11.2.8** | **CRITICAL** | CVE-2025-13080 DoS, CVE-2025-13081 Gadget Chain/Object Injection, CVE-2025-13082 Defacement/Content Spoofing, CVE-2025-13083 Info Disclosure/Access Bypass |
| `psy/psysh` | 0.12.10 | ≥ 0.12.19 | MEDIUM | CVE-2026-25129 Local priv esc |
| `symfony/process` | 7.3.0 | ≥ 7.3.11 | MEDIUM | CVE-2026-24739 (Windows-only) |

**⚠️ Same core exposure as angelicafeliciano.com — 4 concurrent Drupal core CVEs. Patch ASAP.**

---

## Steps

1. **Update core and related packages:**
   ```bash
   cd /var/www/html/unicorninvesting
   COMPOSER_ALLOW_SUPERUSER=1 composer update drupal/core drupal/core-recommended drupal/core-composer-scaffold drupal/core-project-message psy/psysh symfony/process --with-all-dependencies 2>&1
   ```

2. **Run database updates:**
   ```bash
   /var/www/html/unicorninvesting/vendor/bin/drush --root=/var/www/html/unicorninvesting/web --uri=https://unicorninvesting.us updb -y
   ```

3. **Clear caches:**
   ```bash
   /var/www/html/unicorninvesting/vendor/bin/drush --root=/var/www/html/unicorninvesting/web --uri=https://unicorninvesting.us cr
   ```

4. **Verify:**
   ```bash
   cd /var/www/html/unicorninvesting && COMPOSER_ALLOW_SUPERUSER=1 composer audit --no-dev 2>&1
   ```

5. **Smoke test:** Homepage, login, content pages.

---

## Acceptance Criteria

- AC-1: `drupal/core` version ≥ 11.2.8 confirmed
- AC-2: `composer audit` shows 0 advisories
- AC-3: `drush status` all OK, no pending DB updates
- AC-4: No new watchdog errors after update
- Agent: dev-infra
- Status: pending
