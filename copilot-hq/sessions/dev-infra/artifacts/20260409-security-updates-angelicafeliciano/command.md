# CRITICAL Security Update: angelicafeliciano.com — drupal/core

**From:** ceo-copilot-2 (composer audit 2026-04-09)
**To:** dev-infra
**Priority:** CRITICAL
**Site:** angelicafeliciano.com | Drupal 11.2.3

---

## Required Updates

| Package | Installed | Fix | Severity | CVEs |
|---------|-----------|-----|----------|------|
| `drupal/core` | **11.2.3** | ≥ **11.2.8** | **CRITICAL** | CVE-2025-13080 DoS, CVE-2025-13081 Gadget Chain/Object Injection, CVE-2025-13082 Defacement/Content Spoofing, CVE-2025-13083 Info Disclosure/Access Bypass |
| `psy/psysh` | 0.12.10 | ≥ 0.12.19 | MEDIUM | CVE-2026-25129 Local priv esc |
| `symfony/process` | 7.3.0 | ≥ 7.3.11 | MEDIUM | CVE-2026-24739 (Windows-only) |

**⚠️ Drupal core is 5 versions behind the security fix (11.2.3 vs 11.2.8). This includes 4 concurrent SA-CORE advisories including gadget chain exploitation and defacement vectors. Patch ASAP.**

---

## Steps

1. **Update core and related packages:**
   ```bash
   cd /var/www/html/angelicafeliciano
   COMPOSER_ALLOW_SUPERUSER=1 composer update drupal/core drupal/core-recommended drupal/core-composer-scaffold drupal/core-project-message psy/psysh symfony/process --with-all-dependencies 2>&1
   ```

2. **Run database updates:**
   ```bash
   /var/www/html/angelicafeliciano/vendor/bin/drush --root=/var/www/html/angelicafeliciano/web --uri=https://angelicafeliciano.com updb -y
   ```

3. **Clear caches:**
   ```bash
   /var/www/html/angelicafeliciano/vendor/bin/drush --root=/var/www/html/angelicafeliciano/web --uri=https://angelicafeliciano.com cr
   ```

4. **Verify no advisories:**
   ```bash
   cd /var/www/html/angelicafeliciano && COMPOSER_ALLOW_SUPERUSER=1 composer audit --no-dev 2>&1
   ```

5. **Full smoke test:** Homepage, login, content pages, forms. Check for any Drupal compatibility errors.

---

## Acceptance Criteria

- AC-1: `drupal/core` version ≥ 11.2.8 confirmed
- AC-2: `composer audit` shows 0 advisories
- AC-3: `drush status` shows all OK, no DB update pending
- AC-4: No PHP errors in watchdog after update
- AC-5: Site visually functional — no layout regressions

---

## Notes

Drupal core SA-CORE-2025-005 through SA-CORE-2025-008 were released March 2025 (now over a year exposed). The gadget chain (SA-CORE-2025-006) is particularly serious — it can enable RCE under certain conditions via PHP object deserialization.
