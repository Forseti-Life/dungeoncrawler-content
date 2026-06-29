# Security Updates: All 6 Drupal Sites — Complete

**Date:** 2026-04-09
**Status:** ✅ All sites clean — 0 advisories

---

## Summary

Ran `composer audit` across all 6 Drupal sites on the server and applied all security updates.
All sites now report **0 security vulnerability advisories** (production dependencies).

---

## Results by Site

| Site | Before | After | Updates Applied |
|------|--------|-------|-----------------|
| angelicafeliciano.com | 11.2.3 ❌ 11 CVEs | **11.3.6** ✅ | drupal/core + full symfony suite + psysh |
| unicorninvesting.us | 11.2.3 ❌ 11 CVEs | **11.3.6** ✅ | drupal/core + full symfony suite + psysh |
| forseti.life | 11.2.9 ✅ | 11.2.9 ✅ | aws-sdk-php, drupal/captcha, psysh, symfony/process |
| dungeoncrawler.forseti.life | 11.3.3 ✅ | 11.3.3 ✅ | aws-sdk-php 3.369→3.378.1 |
| stlouisintegration.com | 11.3.1 ✅ | 11.3.1 ✅ | aws-sdk-php, drupal/captcha, psysh, symfony/process |
| theoryofconspiracies.com | 11.2.9 ✅ | 11.2.9 ✅ | psysh, symfony/process |

---

## Key CVEs Resolved

- **CVE-2025-13080/81/82/83** — Drupal core DoS, Gadget Chain/Object Injection, Defacement, Info Disclosure (angelicafeliciano + unicorninvesting)
- **GHSA-27qh-8cxx-2cr5** — AWS SDK CloudFront Policy Injection (forseti, dungeoncrawler, stlouisintegration)
- **CVE-2026-3214** — drupal/captcha Access Bypass (forseti, stlouisintegration)
- **CVE-2026-25129** — psy/psysh Local Priv Esc (all sites)
- **CVE-2026-24739** — symfony/process arg escaping (all sites)

---

## DB Updates Applied

- **angelicafeliciano.com:** 13 updates (system 11202/11300, views 11201, block_content x2, ckeditor5 x2, node x2, system rss config, update emails, views date args, views format_plural)
- **unicorninvesting.us:** Same 13 updates
- **forseti.life:** No pending updates
- **dungeoncrawler.forseti.life:** No pending updates
- **stlouisintegration.com:** `ai_conversation_update_8006` FAILED — pre-existing bug (table missing), queued to dev-infra as separate item
- **theoryofconspiracies.com:** Cache clear only

---

## One Residual Issue

**stlouisintegration.com:** `ai_conversation_update_8006` failed — pre-existing DB schema issue.
Queued to `dev-infra` inbox: `20260409-bug-stlouisintegration-ai-conversation-db-update` (ROI 65).
Site is functional; this does not affect the security posture.

---

## Inbox Items Cleanup

The 6 inbox items created earlier in this session (`20260409-security-updates-*`) are now
superseded — all updates have been applied directly.
