Now I have the full picture. I need to:
1. Fix the unit suite path and remove the stale `STAGE 0 PENDING` note
2. Upgrade the route-acl command to explicit curl checks covering all GET+POST routes
3. Update `feature_id` to `forseti-qa-suite-fill-jobhunter-submission` on both
4. Add a POST-specific permission rule to `qa-permissions.json`

---

- Status: done
- Summary: Updated 2 existing suite entries in `qa-suites/products/forseti/suite.json` for `forseti-qa-suite-fill-jobhunter-submission`: (1) fixed the unit suite PHPUnit path from `tests/src/Unit/WorkdayWizardServiceTest.php` to `tests/src/Unit/Service/WorkdayWizardServiceTest.php` and removed the stale STAGE 0 PENDING note; (2) replaced the route-acl suite's generic `site-audit-run.sh` command with an explicit curl-based enumeration covering all 8 GET routes (AC-ACL-01), 7 POST routes (AC-ACL-02), and authenticated GET checks (AC-ACL-03, skips gracefully if `FORSETI_COOKIE_AUTHENTICATED` unset). Both entries updated to `feature_id: forseti-qa-suite-fill-jobhunter-submission`. Added 1 new `qa-permissions.json` rule for POST wizard step routes. Validator exits 0. Committed `eb1f7f425`.

## Next actions
- AC-ACL-03 (auth GET → 200) skips if `FORSETI_COOKIE_AUTHENTICATED` is unset — provision this in CI env for full coverage
- Unit suite uses `/var/www/html/forseti` (production path); run notes include fallback dev path for local runs
- Existing `forseti-jobhunter-application-submission` impl-feature permission rules remain intact (5 rules, not modified)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 16
- Rationale: Corrects a broken test path that would have caused the unit suite to fail at Stage 4 regression regardless of Dev implementation quality, and upgrades the route-acl command from a generic audit to targeted per-route ACL verification that will catch regressions with precision.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260409-201832-suite-activate-forseti-qa-suite-fill-jobhunter-submission
- Generated: 2026-04-09T21:15:36+00:00
