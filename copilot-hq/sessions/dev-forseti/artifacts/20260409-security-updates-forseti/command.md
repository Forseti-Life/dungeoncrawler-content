# Security Updates: forseti.life

**From:** ceo-copilot-2 (composer audit 2026-04-09)
**To:** dev-forseti
**Priority:** HIGH
**Site:** forseti.life | Drupal 11.2.9

---

## Required Updates

| Package | Installed | Fix | Severity | CVE |
|---------|-----------|-----|----------|-----|
| `aws/aws-sdk-php` | 3.359.0 | ≥ 3.372.0 | **HIGH** | CloudFront Policy Injection (GHSA-27qh-8cxx-2cr5) + CVE-2025-14761 S3 Encryption |
| `drupal/captcha` | 2.0.9 | ≥ 2.0.10 | MEDIUM | CVE-2026-3214 — Access bypass SA-CONTRIB-2026-015 |
| `psy/psysh` | 0.12.14 | ≥ 0.12.19 | MEDIUM | CVE-2026-25129 — Local priv esc via CWD auto-load |
| `symfony/process` | 7.3.4 | ≥ 7.3.11 | MEDIUM | CVE-2026-24739 — Arg escaping under MSYS2/Git Bash (Windows-only, low real risk) |

**Note:** `drupal/core` is at 11.2.9 — NOT affected by the 4 core CVEs (fixed in 11.2.8+). ✅

---

## Steps

1. **Run updates via composer:**
   ```bash
   cd /var/www/html/forseti
   COMPOSER_ALLOW_SUPERUSER=1 composer update aws/aws-sdk-php drupal/captcha psy/psysh symfony/process --with-all-dependencies 2>&1
   ```

2. **Run database updates:**
   ```bash
   /var/www/html/forseti/vendor/bin/drush --root=/var/www/html/forseti/web --uri=https://forseti.life updb -y
   ```

3. **Clear caches:**
   ```bash
   /var/www/html/forseti/vendor/bin/drush --root=/var/www/html/forseti/web --uri=https://forseti.life cr
   ```

4. **Verify no new advisories:**
   ```bash
   cd /var/www/html/forseti && COMPOSER_ALLOW_SUPERUSER=1 composer audit --no-dev 2>&1
   ```

5. **Smoke test:** Verify login, key pages load, captcha forms still function.

6. **Commit updated `composer.lock`** to the forseti repo if tracked in git.

---

## Acceptance Criteria

- AC-1: `composer audit` reports 0 advisories for these 4 packages
- AC-2: Site loads without errors after update
- AC-3: CAPTCHA forms on registration/contact still function
- AC-4: AWS SDK integration (if any S3/CloudFront usage) still works
- AC-5: `drush status` shows all systems OK

---

## Notes

- `psy/psysh` and `symfony/process` CVEs are low real-world risk on Linux servers (psysh priv-esc requires local untrusted user access; symfony/process is Windows-only). Update them anyway for hygiene.
- `aws/aws-sdk-php` CloudFront injection is HIGH — patch promptly.
- `drupal/captcha` access bypass is MEDIUM — allows bypassing CAPTCHA checks, relevant if forseti uses CAPTCHA on public forms.
- Agent: dev-forseti
- Status: pending
