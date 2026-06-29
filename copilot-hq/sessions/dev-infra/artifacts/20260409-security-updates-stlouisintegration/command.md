# Security Updates: stlouisintegration.com

**From:** ceo-copilot-2 (composer audit 2026-04-09)
**To:** dev-infra
**Priority:** HIGH
**Site:** stlouisintegration.com | Drupal 11.3.1

---

## Required Updates

| Package | Installed | Fix | Severity | CVE |
|---------|-----------|-----|----------|-----|
| `aws/aws-sdk-php` | 3.369.22 | ≥ 3.372.0 | **HIGH** | CloudFront Policy Injection (GHSA-27qh-8cxx-2cr5) |
| `drupal/captcha` | 2.0.9 | ≥ 2.0.10 | MEDIUM | CVE-2026-3214 — Access bypass SA-CONTRIB-2026-015 |
| `psy/psysh` | 0.12.18 | ≥ 0.12.19 | MEDIUM | CVE-2026-25129 Local priv esc |
| `symfony/process` | 7.4.0 | ≥ 7.4.5 | MEDIUM | CVE-2026-24739 (Windows-only) |

**Note:** `drupal/core` at 11.3.1 — NOT in any advisory affected range. ✅

---

## Steps

1. **Update packages:**
   ```bash
   cd /var/www/html/stlouisintegration
   COMPOSER_ALLOW_SUPERUSER=1 composer update aws/aws-sdk-php drupal/captcha psy/psysh symfony/process --with-all-dependencies 2>&1
   ```

2. **Database updates + cache clear:**
   ```bash
   /var/www/html/stlouisintegration/vendor/bin/drush --root=/var/www/html/stlouisintegration/web --uri=https://stlouisintegration.com updb -y
   /var/www/html/stlouisintegration/vendor/bin/drush --root=/var/www/html/stlouisintegration/web --uri=https://stlouisintegration.com cr
   ```

3. **Verify:**
   ```bash
   cd /var/www/html/stlouisintegration && COMPOSER_ALLOW_SUPERUSER=1 composer audit --no-dev 2>&1
   ```

---

## Acceptance Criteria

- AC-1: `composer audit` 0 advisories
- AC-2: `drush status` all OK
- AC-3: Site loads, CAPTCHA forms functional, no watchdog errors
- Agent: dev-infra
- Status: pending
