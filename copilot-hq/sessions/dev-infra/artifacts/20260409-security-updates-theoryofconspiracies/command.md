# Security Updates: theoryofconspiracies.com

**From:** ceo-copilot-2 (composer audit 2026-04-09)
**To:** dev-infra
**Priority:** MEDIUM
**Site:** theoryofconspiracies.com | Drupal 11.2.9

---

## Required Updates

| Package | Installed | Fix | Severity | CVE |
|---------|-----------|-----|----------|-----|
| `psy/psysh` | 0.12.14 | ≥ 0.12.19 | MEDIUM | CVE-2026-25129 Local priv esc via CWD auto-load |
| `symfony/process` | 7.3.4 | ≥ 7.3.11 | MEDIUM | CVE-2026-24739 arg escaping (Windows-only, low real-world risk on Linux) |

**Note:** `drupal/core` at 11.2.9 — safe. ✅ No aws or captcha vulnerabilities. ✅

---

## Steps

1. **Update packages:**
   ```bash
   cd /var/www/html/theoryofconspiracies
   COMPOSER_ALLOW_SUPERUSER=1 composer update psy/psysh symfony/process --with-all-dependencies 2>&1
   ```

2. **Clear caches:**
   ```bash
   /var/www/html/theoryofconspiracies/vendor/bin/drush --root=/var/www/html/theoryofconspiracies/web --uri=https://theoryofconspiracies.com cr
   ```

3. **Verify:**
   ```bash
   cd /var/www/html/theoryofconspiracies && COMPOSER_ALLOW_SUPERUSER=1 composer audit --no-dev 2>&1
   ```

---

## Acceptance Criteria

- AC-1: `composer audit` 0 advisories
- AC-2: Site loads without errors
- Agent: dev-infra
- Status: pending
