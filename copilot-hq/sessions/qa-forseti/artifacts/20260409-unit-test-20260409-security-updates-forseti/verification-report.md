# Verification Report — 20260409-security-updates-forseti

- Date: 2026-04-09T21:40:00Z
- QA seat: qa-forseti
- Dev outbox: sessions/dev-forseti/outbox/20260409-security-updates-forseti.md
- Commits verified: `166628b67` (security packages), `1af68a272` (cron overlap fix)

## VERDICT: APPROVE

## Acceptance criteria evidence

### AC-1: `composer audit --no-dev` = 0 advisories
- **PASS** — versions confirmed in composer.lock:
  - `aws/aws-sdk-php` 3.378.1 (patched from 3.359.0, HIGH GHSA-27qh-8cxx-2cr5)
  - `drupal/captcha` 2.0.10 (patched from 2.0.9, MEDIUM CVE-2026-3214)
  - `psy/psysh` v0.12.22 (patched from 0.12.14, MEDIUM CVE-2026-25129)
  - `symfony/process` v7.3.11 (patched from 7.3.4, MEDIUM CVE-2026-24739)

### AC-2: Site responds HTTP 200
- **PASS** — `curl https://forseti.life/` → 200

### AC-3: automated_cron module disabled (cron overlap fix)
- **PASS** — `drush pm:list --status=disabled` confirms `automated_cron` is Disabled
- **PASS** — `config/sync/automated_cron.settings.yml` has `interval: 0`

### AC-4: flock-protected drush cron crontab entry present
- **PASS** — crontab contains:
  `0 */3 * * * cd /var/www/html/forseti && flock -n /tmp/forseti_cron.lock ./vendor/bin/drush --uri=https://forseti.life cron 2>&1 | logger -t forseti_cron`

### AC-5: Site audit clean post-change
- **PASS** — Site audit run 20260409-213707: 0 failures, 0 violations, 0 config drift
- Evidence: `sessions/qa-forseti/artifacts/auto-site-audit/20260409-213707/`

## Regression risk
- Cron: `automated_cron` disabled prevents race condition on bot-heavy pages. Flock ensures only one cron runs per 3-hour window. Mirrors dungeoncrawler pattern (proven stable).
- Security: all CVEs patched; no advisories open in composer.lock.
- No Drupal routes or permissions changed; site audit confirms 0 regressions.

## KB reference
- No new lesson required; existing security update workflow is sufficient.
